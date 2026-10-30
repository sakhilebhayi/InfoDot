<?php

namespace App\Services\Knowledge;

use App\Models\ModerationAction;
use App\Models\Solutions;
use App\Models\Steps;
use App\Models\Team;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Publishes the curated library entries in database/knowledge/ as public solutions of the official InfoDot account
 * (R-14). Each file is one entry; its `key` is written to solutions.seed_key, so a second run updates the entry in
 * place instead of publishing a duplicate. Everything goes through the ordinary Solutions/Steps/Topic models — the
 * same shapes the create form writes — so the entries behave like any other solution (search, topics, comments,
 * moderation, the Verified library) with no parallel content system.
 *
 * The whole batch is validated before anything is written: one bad file stops the run. A new entry whose title an
 * existing community solution already uses is skipped and reported, never silently doubled.
 */
class KnowledgeSeeder
{
    public const DURATION_TYPES = ['hours', 'days', 'weeks', 'months', 'years', 'infinite'];

    /** What the create form allows, enforced here too so seeded entries could have been typed in by hand. */
    private const MAX_STEPS = 50;

    private const MAX_STEP_BODY = 5000;

    private const MAX_DESCRIPTION = 20000;

    /**
     * Read and validate every entry file. Returns the parsed entries; $errors collects "file: problem" lines and the
     * caller must publish nothing while it is non-empty.
     *
     * @param  list<string>  $files
     * @param  list<string>  $errors
     * @return list<array{key: string, title: string, topics: list<string>, tags: string, duration: int, duration_type: string, evidence: string, description: string, steps: list<array{heading: string, body: string}>}>
     */
    public function parseAll(array $files, array &$errors): array
    {
        $entries = [];
        $keys = [];

        foreach ($files as $file) {
            $entry = $this->parseFile($file, $errors);

            if ($entry === null) {
                continue;
            }

            if (isset($keys[$entry['key']])) {
                $errors[] = basename($file).': key "'.$entry['key'].'" is already used by '.$keys[$entry['key']].'.';

                continue;
            }

            $keys[$entry['key']] = basename($file);
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * One file: front matter between two "---" lines (key, title, topics, tags, duration, duration_type, evidence),
     * then the discovery summary, then one "## Heading" section per step. The last section must be the sources.
     *
     * @param  list<string>  $errors
     */
    public function parseFile(string $file, array &$errors): ?array
    {
        $name = basename($file);
        $raw = (string) file_get_contents($file);
        $raw = str_replace("\r\n", "\n", $raw);

        if (! preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $raw, $m)) {
            $errors[] = $name.': no front matter (the file must start with --- ... --- lines).';

            return null;
        }

        $fields = [];
        foreach (explode("\n", $m[1]) as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (! preg_match('/\A([a-z_]+):\s*(.*)\z/', $line, $f)) {
                $errors[] = $name.': front-matter line not understood: "'.trim($line).'".';

                return null;
            }
            $fields[$f[1]] = trim($f[2]);
        }

        $bad = count($errors);

        foreach (['key', 'title', 'topics', 'tags', 'duration', 'duration_type', 'evidence'] as $required) {
            if (($fields[$required] ?? '') === '') {
                $errors[] = $name.': front matter is missing "'.$required.'".';
            }
        }

        if (count($errors) > $bad) {
            return null;
        }

        $key = $fields['key'];
        if (! preg_match('/\A[a-z0-9][a-z0-9-]{2,80}\z/', $key)) {
            $errors[] = $name.': key must be 3-81 characters of lower-case letters, digits and hyphens.';
        }
        if ($key !== pathinfo($file, PATHINFO_FILENAME)) {
            $errors[] = $name.': key must match the file name ("'.$key.'" vs "'.pathinfo($file, PATHINFO_FILENAME).'").';
        }

        if (mb_strlen($fields['title']) < 3 || mb_strlen($fields['title']) > 255) {
            $errors[] = $name.': title must be 3-255 characters.';
        }

        $topics = array_values(array_filter(array_map('trim', explode('|', $fields['topics'])), fn ($t) => $t !== ''));
        if (count($topics) < 1 || count($topics) > Topic::MAX_PER_ITEM) {
            $errors[] = $name.': 1-'.Topic::MAX_PER_ITEM.' topics, separated by "|".';
        }
        foreach ($topics as $topic) {
            if (mb_strlen($topic) < Topic::NAME_MIN || mb_strlen($topic) > Topic::NAME_MAX || Str::slug($topic) === '') {
                $errors[] = $name.': topic name "'.$topic.'" must be '.Topic::NAME_MIN.'-'.Topic::NAME_MAX.' characters.';
            }
        }

        if (mb_strlen($fields['tags']) < 3 || mb_strlen($fields['tags']) > 255) {
            $errors[] = $name.': tags must be 3-255 characters.';
        }

        $duration = (int) $fields['duration'];
        if ((string) $duration !== $fields['duration'] || $duration < 1 || $duration > 10000) {
            $errors[] = $name.': duration must be a whole number from 1 to 10000.';
        }
        if (! in_array($fields['duration_type'], self::DURATION_TYPES, true)) {
            $errors[] = $name.': duration_type must be one of '.implode(', ', self::DURATION_TYPES).'.';
        }

        // The body: everything before the first "## " heading is the discovery summary; each section is one step.
        $parts = preg_split('/^## +/m', $m[2]);
        $description = trim($parts[0]);
        $description .= ($description === '' ? '' : "\n\n").'Evidence: '.$fields['evidence'];

        if (mb_strlen($description) < 3 || mb_strlen($description) > self::MAX_DESCRIPTION) {
            $errors[] = $name.': the summary before the first "## " must be 3-'.self::MAX_DESCRIPTION.' characters.';
        }

        $steps = [];
        foreach (array_slice($parts, 1) as $section) {
            $heading = trim(strtok($section, "\n"));
            $body = trim((string) substr($section, strlen(strtok($section, "\n"))));

            if ($heading === '' || mb_strlen($heading) > 255) {
                $errors[] = $name.': a step heading is empty or over 255 characters.';
            }
            if ($body === '' || mb_strlen($body) > self::MAX_STEP_BODY) {
                $errors[] = $name.': the body under "'.$heading.'" is empty or over '.self::MAX_STEP_BODY.' characters.';
            }

            $steps[] = ['heading' => $heading, 'body' => $body];
        }

        if (count($steps) < 2 || count($steps) > self::MAX_STEPS) {
            $errors[] = $name.': 2-'.self::MAX_STEPS.' "## " sections (the guidance, then the sources).';
        } else {
            $last = $steps[count($steps) - 1];
            if (! Str::startsWith($last['heading'], 'Sources')) {
                $errors[] = $name.': the last section must be the sources (heading starting with "Sources").';
            } elseif (! str_contains($last['body'], 'http')) {
                $errors[] = $name.': the sources section has no link.';
            } elseif (! str_contains($last['body'], 'Last reviewed')) {
                $errors[] = $name.': the sources section must say when the entry was last reviewed ("Last reviewed: ...").';
            }
        }

        if (count($errors) > $bad) {
            return null;
        }

        return [
            'key' => $key,
            'title' => $fields['title'],
            'topics' => $topics,
            'tags' => $fields['tags'],
            'duration' => $duration,
            'duration_type' => $fields['duration_type'],
            'evidence' => $fields['evidence'],
            'description' => $description,
            'steps' => $steps,
        ];
    }

    /**
     * The account the entries belong to, created on first use: verified, random password, its own personal workspace,
     * no moderator rights (its entries are verified by human moderators like anyone else's, or not at all).
     */
    public function officialAccount(): User
    {
        $email = (string) config('knowledge.official.email');

        if ($email === '') {
            throw new \RuntimeException('knowledge.official.email is not set.');
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            return $user;
        }

        return DB::transaction(function () use ($email): User {
            $user = User::forceCreate([
                'name' => (string) config('knowledge.official.name', 'InfoDot'),
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'email_verified_at' => now(),
            ]);

            $team = Team::forceCreate([
                'user_id' => $user->id,
                'name' => $user->name,
                'personal_team' => true,
            ]);

            $user->forceFill(['current_team_id' => $team->id])->save();

            return $user;
        });
    }

    /**
     * Publish or update one parsed entry. Returns 'created', 'updated', or a "skipped: ..." reason. Each entry is one
     * transaction: a failure part-way leaves nothing half-written.
     */
    public function publish(array $entry, User $official, bool $force = false): string
    {
        return DB::transaction(function () use ($entry, $official, $force): string {
            $existing = Solutions::withoutGlobalScopes()->where('seed_key', $entry['key'])->first();

            if (! $existing && ! $force) {
                // A community solution with the same title means a person already covered this: a human decides, not the batch.
                $taken = Solutions::withoutGlobalScopes()
                    ->whereNull('seed_key')
                    ->whereRaw('LOWER(solution_title) = ?', [mb_strtolower($entry['title'])])
                    ->exists();

                if ($taken) {
                    return 'skipped: a community solution already has this title — review it, then rerun with --force to publish anyway.';
                }
            }

            $solution = $existing ?? new Solutions;
            $solution->user_id = $official->id;
            $solution->team_id = $official->current_team_id;
            $solution->visibility = Solutions::VISIBILITY_PUBLIC;
            $solution->solution_title = $entry['title'];
            $solution->solution_description = $entry['description'];
            $solution->tags = $entry['tags'];
            $solution->duration = $entry['duration'];
            $solution->duration_type = $entry['duration_type'];
            $solution->steps = count($entry['steps']);
            $solution->seed_key = $entry['key'];
            $solution->save();

            Steps::where('solution_id', $solution->id)->delete();
            foreach ($entry['steps'] as $step) {
                $row = new Steps;
                $row->user_id = $official->id;
                $row->solution_id = $solution->id;
                $row->solution_heading = $step['heading'];
                $row->solution_body = $step['body'];
                $row->save();
            }

            $solution->topics()->sync($this->topicIds($entry['topics'], $official));

            return $existing ? 'updated' : 'created';
        });
    }

    /**
     * The ids of the named topics, creating any that do not exist yet (active, at the end of the display order, on the
     * moderation trail). Topics are normally a moderator's to create; a seeding run is the console acting for the
     * platform, so the addition is recorded against the official account the same way.
     *
     * @param  list<string>  $names
     * @return list<int>
     */
    private function topicIds(array $names, User $official): array
    {
        $ids = [];

        foreach ($names as $name) {
            $slug = Str::slug($name);
            $topic = Topic::where('slug', $slug)->first();

            if (! $topic) {
                $topic = Topic::create([
                    'slug' => $slug, 'name' => $name, 'description' => null, 'active' => true,
                    'sort_order' => min(65000, ((int) Topic::max('sort_order')) + 10),
                ]);
                ModerationAction::record($topic, $official, ModerationAction::TOPIC_ADDED, $name.' (knowledge:seed)');
            }

            $ids[] = $topic->id;
        }

        return array_values(array_unique($ids));
    }
}
