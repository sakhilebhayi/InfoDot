<?php

namespace App\Services\Demo;

use App\Models\Answer;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\DemoRecord;
use App\Models\Enquiry;
use App\Models\EnquiryMessage;
use App\Models\Like;
use App\Models\Mention;
use App\Models\Questions;
use App\Models\SolutionConfirmation;
use App\Models\Solutions;
use App\Models\Steps;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

/**
 * Answers one question for the whole application: is this record demonstration content (R-49)? The register
 * (`demo_records`) is read once per request and kept in memory, so labelling a list costs one small query however
 * long the list is. It is also the only writer of the register:
 *  - everything created while `demo:install` runs is written down, whichever part of the application created it;
 *  - what a demonstration account later writes while signed in (an answer, a comment, an enquiry) is written down
 *    too, so it is labelled and leaves with the rest. Only content is, never a moderation or audit record.
 */
class DemoRegistry
{
    /** What a signed-in demonstration account can add to the register by using the application. */
    private const CONTENT = [
        Questions::class, Answer::class, Solutions::class, Steps::class, Comment::class, SolutionConfirmation::class,
        Enquiry::class, EnquiryMessage::class, Attachment::class, UserNotice::class, Like::class, Mention::class,
    ];

    /** @var array<class-string, array<string, true>>|null */
    private ?array $index = null;

    private bool $recording = false;

    /** Whether this record is on the register. */
    public function has(?Model $model): bool
    {
        return $model !== null && $model->getKey() !== null && isset($this->index()[$model::class][(string) $model->getKey()]);
    }

    public function isDemoUser(?User $user): bool
    {
        return $this->has($user);
    }

    public function isDemoTeam(?Team $team): bool
    {
        return $this->has($team);
    }

    /** Whether the person with this uuid is a demonstration account (events carry uuids, not models). */
    public function isDemoUserUuid(?string $uuid): bool
    {
        $ids = $this->ids(User::class);

        return $uuid !== null && $uuid !== '' && $ids !== [] && User::whereIn('id', $ids)->where('uuid', $uuid)->exists();
    }

    /**
     * The keys on the register for one model.
     *
     * @param  class-string<Model>  $class
     * @return list<string>
     */
    public function ids(string $class): array
    {
        return array_map('strval', array_keys($this->index()[$class] ?? []));
    }

    public function installed(): bool
    {
        return $this->index() !== [];
    }

    /**
     * Run the installer's work with everything it creates written to the register.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public function recording(Closure $work): mixed
    {
        $this->recording = true;

        try {
            return $work();
        } finally {
            $this->recording = false;
        }
    }

    /** Called for every model the application creates (see AppServiceProvider). */
    public function created(Model $model): void
    {
        if ($model instanceof DemoRecord || $model->getKey() === null) {
            return;
        }

        if (! $this->recording && ! $this->writtenByDemoAccount($model)) {
            return;
        }

        $key = (string) $model->getKey();
        DemoRecord::firstOrCreate(['record_type' => $model::class, 'record_id' => $key], ['created_at' => now()]);

        if ($this->index !== null) {
            $this->index[$model::class][$key] = true;
        }
    }

    /** Forget what was read: the register changed underneath (install, remove). */
    public function flush(): void
    {
        $this->index = null;
    }

    private function writtenByDemoAccount(Model $model): bool
    {
        // Nothing is installed on most sites and in most requests: cost nothing then.
        if (! in_array($model::class, self::CONTENT, true) || ! $this->installed()) {
            return false;
        }

        $user = Auth::user();

        return $user instanceof User && $this->has($user);
    }

    /** @return array<class-string, array<string, true>> */
    private function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $index = [];

        try {
            foreach (DemoRecord::query()->toBase()->get(['record_type', 'record_id']) as $row) {
                $index[$row->record_type][(string) $row->record_id] = true;
            }
        } catch (QueryException) {
            // The code is deployed before its migration runs: until the table exists there is no demonstration content.
            return [];
        }

        return $this->index = $index;
    }
}
