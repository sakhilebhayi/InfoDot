<?php

namespace App\Services\Demo;

use App\Models\Attachment;
use App\Models\DemoRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Searchable;
use RuntimeException;

/**
 * Takes the demonstration content away again (R-49), exactly: the records on the register, their files, and the
 * rows that cannot exist without them. Nothing is found by name, address or guesswork.
 *
 * "Rows that cannot exist without them" are found from the database itself: a row that points at a demonstration
 * record through `user_id`, `team_id`, a cascading foreign key or a polymorphic pair (a membership, a category
 * link, a session, a licence row the application made lazily). Most are bookkeeping and go silently. A row that
 * hangs on demonstration content but belongs to a real person (their answer to a demonstration question, their
 * enquiry to a demonstration company) is different: by default removal stops and says what it found, and only
 * `withAttached` deletes those too. Records are deleted with plain queries, so no "team deleted" webhook or mail
 * is ever sent about a company that never existed.
 */
class DemoRemover
{
    /** Tables that only tell someone about something: never a reason to stop, always removed with their subject. */
    private const NOTICES = ['user_notices', 'notifications'];

    /** Reference columns followed by name where the schema declares no foreign key (the older tables). */
    private const BY_NAME = ['user_id' => 'users', 'team_id' => 'teams', 'question_id' => 'questions', 'solution_id' => 'solutions', 'answer_id' => 'answers', 'enquiry_id' => 'enquiries'];

    public function __construct(private readonly DemoRegistry $registry) {}

    /**
     * Real people's rows that hang on demonstration content, counted by table. Empty on a site where only the
     * demonstration accounts touched the demonstration content.
     *
     * @return array<string, int>
     */
    public function attached(): array
    {
        return array_map('count', $this->plan()['foreign']);
    }

    /**
     * Remove everything on the register. Returns how many rows went, by table.
     *
     * @return array<string, int>
     *
     * @throws RuntimeException when real people's rows are attached and $withAttached is false
     */
    public function remove(bool $withAttached = false): array
    {
        $plan = $this->plan();

        if ($plan['foreign'] !== [] && ! $withAttached) {
            throw new RuntimeException('Real accounts have added to the demonstration content ('
                .collect($plan['foreign'])->map(fn (array $ids, string $table) => count($ids).' in '.$table)->implode(', ')
                .'). Nothing was removed.');
        }

        config(['scout.queue' => false]);
        $files = $this->files($plan['keyed']);
        $this->leaveSearch();

        $removed = DB::transaction(function () use ($plan): array {
            $removed = [];

            // Link rows first, then the newest rows before the rows they were made for.
            foreach ($plan['links'] as [$table, $column, $ids]) {
                $removed[$table] = ($removed[$table] ?? 0) + $this->chunked($ids, fn (array $chunk) => DB::table($table)->whereIn($column, $chunk)->delete());
            }

            foreach (array_reverse($plan['order']) as [$table, $key, $id]) {
                $removed[$table] = ($removed[$table] ?? 0) + DB::table($table)->where($key, $id)->delete();
            }

            if (Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->whereIn('email', $plan['emails'])->delete();
            }
            DemoRecord::query()->delete();

            return array_filter($removed);
        });

        foreach ($files as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }

        $this->registry->flush();
        Cache::forget('sitemap.xml');

        return $removed;
    }

    /**
     * Everything removal would delete, worked out without changing anything.
     *
     * @return array{keyed: array<string, array<string, true>>, order: list<array{0: string, 1: string, 2: string}>, links: list<array{0: string, 1: string, 2: list<string>}>, foreign: array<string, list<string>>, emails: list<string>}
     */
    private function plan(): array
    {
        /** @var array<string, array<string, true>> $keyed table => keys to delete */
        $keyed = [];
        $order = [];
        /** @var array<string, list<string>> $aliases table => the names a polymorphic column may use for it */
        $aliases = [];
        $keys = [];

        foreach (DemoRecord::query()->orderBy('id')->get() as $record) {
            if (! class_exists($record->record_type) || ! is_subclass_of($record->record_type, Model::class)) {
                continue;
            }

            $model = new ($record->record_type);
            $table = $model->getTable();
            $keys[$table] = $model->getKeyName();
            $aliases[$table] = array_values(array_unique([$record->record_type, $model->getMorphClass()]));

            if (! isset($keyed[$table][$record->record_id])) {
                $keyed[$table][$record->record_id] = true;
                $order[] = [$table, $keys[$table], $record->record_id];
            }
        }

        $demoUsers = $keyed['users'] ?? [];
        $foreign = [];
        $links = [];
        $schema = $this->schema();

        // Follow references until nothing new turns up: a row hanging on a row that hangs on demonstration content goes too.
        for ($pass = 0; $pass < 8; $pass++) {
            $found = false;
            $links = [];

            foreach ($schema as $table => $shape) {
                if ($table === 'demo_records') {
                    continue;
                }

                foreach ($shape['references'] as $column => $parent) {
                    $parentIds = array_map('strval', array_keys($keyed[$parent] ?? []));
                    if ($parentIds === []) {
                        continue;
                    }

                    $found = $this->collect($table, $shape, fn ($q) => $q->whereIn($column, $parentIds), $column, $parentIds, $keyed, $order, $links, $foreign, $demoUsers, $keys) || $found;
                }

                foreach ($shape['morphs'] as $typeColumn => $idColumn) {
                    foreach ($aliases as $parent => $names) {
                        $parentIds = array_map('strval', array_keys($keyed[$parent] ?? []));
                        if ($parentIds === [] || $shape['key'] === null) {
                            continue;
                        }

                        $found = $this->collect($table, $shape, fn ($q) => $q->whereIn($typeColumn, $names)->whereIn($idColumn, $parentIds), null, [], $keyed, $order, $links, $foreign, $demoUsers, $keys) || $found;
                    }
                }
            }

            if (! $found) {
                break;
            }
        }

        $emails = $demoUsers === [] ? [] : DB::table('users')->whereIn('id', array_keys($demoUsers))->pluck('email')->map(fn ($e) => (string) $e)->all();

        return ['keyed' => $keyed, 'order' => $order, 'links' => $links, 'foreign' => $foreign, 'emails' => $emails];
    }

    /**
     * Add one table's rows that match a reference to the plan. Returns whether anything new was found.
     *
     * @param  array{key: ?string, owner: bool, references: array<string, string>, morphs: array<string, string>}  $shape
     * @param  list<string>  $parentIds
     * @param  array<string, array<string, true>>  $keyed
     * @param  list<array{0: string, 1: string, 2: string}>  $order
     * @param  list<array{0: string, 1: string, 2: list<string>}>  $links
     * @param  array<string, list<string>>  $foreign
     * @param  array<string, true>  $demoUsers
     * @param  array<string, string>  $keys
     */
    private function collect(string $table, array $shape, \Closure $where, ?string $column, array $parentIds, array &$keyed, array &$order, array &$links, array &$foreign, array $demoUsers, array &$keys): bool
    {
        // A table with no key of its own is a link table: its rows are deleted by what they point at.
        if ($shape['key'] === null) {
            if ($column !== null) {
                $links[] = [$table, $column, $parentIds];
            }

            return false;
        }

        $found = false;
        $select = $shape['owner'] ? [$shape['key'], 'user_id'] : [$shape['key']];

        foreach ($this->matches($table, $where, $select, $parentIds, $column) as $row) {
            $id = (string) $row->{$shape['key']};
            if (isset($keyed[$table][$id])) {
                continue;
            }

            $keyed[$table][$id] = true;
            $keys[$table] = $shape['key'];
            $order[] = [$table, $shape['key'], $id];
            $found = true;

            // Reached through something other than its own author, and its author is a real person.
            $owner = $shape['owner'] ? $row->user_id : null;
            if ($column !== 'user_id' && $owner !== null && ! isset($demoUsers[(string) $owner]) && ! in_array($table, self::NOTICES, true)) {
                $foreign[$table][] = $id;
            }
        }

        return $found;
    }

    /**
     * @param  list<string>  $select
     * @param  list<string>  $parentIds
     * @return list<object>
     */
    private function matches(string $table, \Closure $where, array $select, array $parentIds, ?string $column): array
    {
        // Polymorphic lookups carry their own (short) id lists; plain references are chunked so the query stays small.
        if ($column === null) {
            return $where(DB::table($table))->get($select)->all();
        }

        $rows = [];
        foreach (array_chunk($parentIds, 500) as $chunk) {
            array_push($rows, ...DB::table($table)->whereIn($column, $chunk)->get($select)->all());
        }

        return $rows;
    }

    /**
     * Every table's shape, read once: its key, whether it names an author, and which columns point at which table.
     *
     * @return array<string, array{key: ?string, owner: bool, references: array<string, string>, morphs: array<string, string>}>
     */
    private function schema(): array
    {
        $shapes = [];

        // This database only: on a shared MySQL server the account may see other applications' databases too.
        foreach (array_column(Schema::getTables(Schema::getCurrentSchemaName()), 'name') as $table) {
            $columns = array_column(Schema::getColumns($table), 'name');
            $references = [];

            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                // A cascading key says the row cannot outlive its parent. "Set null" and "restrict" say the opposite.
                if (count($foreignKey['columns']) === 1 && strtolower((string) $foreignKey['on_delete']) === 'cascade') {
                    $references[$foreignKey['columns'][0]] = $foreignKey['foreign_table'];
                }
            }

            foreach (self::BY_NAME as $column => $parent) {
                if (in_array($column, $columns, true) && $table !== $parent) {
                    $references[$column] ??= $parent;
                }
            }

            $morphs = [];
            foreach ($columns as $column) {
                if (str_ends_with($column, '_type') && in_array(substr($column, 0, -5).'_id', $columns, true)) {
                    $morphs[$column] = substr($column, 0, -5).'_id';
                }
            }

            $shapes[$table] = [
                'key' => in_array('id', $columns, true) ? 'id' : null,
                'owner' => in_array('user_id', $columns, true),
                'references' => $references,
                'morphs' => $morphs,
            ];
        }

        return $shapes;
    }

    /**
     * The files behind the rows that are about to go: attachments on the private disk, profile pictures on the public one.
     *
     * @param  array<string, array<string, true>>  $keyed
     * @return list<array{0: string, 1: string}>
     */
    private function files(array $keyed): array
    {
        $files = [];

        foreach ($this->rows('attachments', array_keys($keyed['attachments'] ?? []), ['path', 'thumb_path']) as $row) {
            foreach (array_filter([$row->path, $row->thumb_path]) as $path) {
                $files[] = [Attachment::DISK, (string) $path];
            }
        }

        foreach ($this->rows('users', array_keys($keyed['users'] ?? []), ['profile_photo_path']) as $row) {
            if ($row->profile_photo_path) {
                $files[] = [(string) config('jetstream.profile_photo_disk', 'public'), (string) $row->profile_photo_path];
            }
        }

        return $files;
    }

    /**
     * @param  list<int|string>  $ids
     * @param  list<string>  $columns
     * @return list<object>
     */
    private function rows(string $table, array $ids, array $columns): array
    {
        $rows = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            array_push($rows, ...DB::table($table)->whereIn('id', $chunk)->get($columns)->all());
        }

        return $rows;
    }

    /** Take the searchable demonstration records out of the search index before their rows go. */
    private function leaveSearch(): void
    {
        foreach (DemoRecord::query()->toBase()->distinct()->pluck('record_type') as $class) {
            if (! class_exists($class) || ! in_array(Searchable::class, class_uses_recursive($class), true)) {
                continue;
            }

            foreach (array_chunk($this->registry->ids($class), 200) as $chunk) {
                $class::withoutGlobalScopes()->whereIn((new $class)->getKeyName(), $chunk)->get()->unsearchable();
            }
        }
    }

    /**
     * @param  list<string>  $ids
     * @param  \Closure(list<string>): int  $delete
     */
    private function chunked(array $ids, \Closure $delete): int
    {
        $n = 0;
        foreach (array_chunk($ids, 500) as $chunk) {
            $n += $delete($chunk);
        }

        return $n;
    }
}
