<?php

namespace App\Services\Demo;

use App\Events\Knowledge\QuestionSubmitted;
use App\Models\Attachment;
use App\Models\BusinessAnnouncement;
use App\Models\BusinessCategory;
use App\Models\BusinessLocation;
use App\Models\BusinessProfileEvent;
use App\Models\BusinessProject;
use App\Models\BusinessService;
use App\Models\DemoRecord;
use App\Models\Questions;
use App\Models\SolutionConfirmation;
use App\Models\Solutions;
use App\Models\Steps;
use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\Topic;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Attachments\TeamMediaService;
use App\Services\Enquiries\EnquiryService;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\InAppNotices;
use App\Services\Profile\ProfilePhoto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Writes the demonstration content of database/demo/content.php (R-49): two fictional companies with complete
 * business pages, four fictional people, and a small body of questions, answers, solutions and one enquiry. It
 * exists so the owner can show InfoDot working on a site that has little content yet, without inventing a single
 * customer or number. The demonstration moderator is created only when asked for (`$withModerator`, or addModerator()
 * later): on a real site it would hold real moderation powers.
 *
 * It writes the way the application writes: pictures through the media pipeline, answers through AnswerService,
 * the enquiry through EnquiryService, notices through InAppNotices. So achievements, notices and the search
 * index are whatever the application's own rules make of these actions, and nothing is typed in by hand. While it
 * runs, DemoRegistry writes down every record created, which is what labels the content and lets DemoRemover
 * take exactly it away again.
 */
class DemoInstaller
{
    /** @var array<string, User> */
    private array $people = [];

    /** @var array<string, Team> */
    private array $companies = [];

    /** @var array<string, Solutions> */
    private array $solutions = [];

    /** Files written to disk so far, as [disk, path]: removed again if the install fails part-way. @var list<array{0: string, 1: string}> */
    private array $written = [];

    public function __construct(
        private readonly DemoRegistry $registry,
        private readonly DemoArtwork $artwork,
        private readonly TeamMediaService $media,
        private readonly ProfilePhoto $photos,
        private readonly AnswerService $answers,
        private readonly EnquiryService $enquiries,
        private readonly InAppNotices $notices,
    ) {}

    /**
     * Install everything, or nothing: the database work is one transaction and the files of a failed run are deleted.
     *
     * @return array<string, string> one-time passwords by email address; they are stored nowhere else
     *
     * @throws RuntimeException when demonstration content is already installed, or an address is taken by a real account
     */
    public function install(bool $withModerator = false): array
    {
        if ($this->registry->installed()) {
            throw new RuntimeException('Demonstration content is already installed. Nothing was changed.');
        }

        $content = $this->content();
        // The moderator is a real moderator on a real site, so it exists only when asked for.
        $content['people'] = array_filter($content['people'], fn (array $person) => $withModerator || ! ($person['moderator'] ?? false));

        $passwords = $this->passwordsFor($content['people']);

        $this->run(fn () => $this->build($content, $passwords));

        return $passwords;
    }

    /**
     * Add the demonstration moderator to content that was installed without one.
     *
     * @return array<string, string> its one-time password by email address
     *
     * @throws RuntimeException when nothing is installed, the moderator exists already, or its address is taken
     */
    public function addModerator(): array
    {
        if (! $this->registry->installed()) {
            throw new RuntimeException('No demonstration content is installed. Nothing was changed.');
        }

        if ($this->moderator() !== null) {
            throw new RuntimeException('The demonstration moderator already exists. Nothing was changed.');
        }

        $people = array_filter($this->content()['people'], fn (array $person) => (bool) ($person['moderator'] ?? false));
        $passwords = $this->passwordsFor($people);

        $this->run(function () use ($people, $passwords): void {
            foreach ($people as $person) {
                $this->person($person, $passwords[$person['email']]);
            }
        });

        return $passwords;
    }

    /** The demonstration moderator, when the install was asked for one. */
    public function moderator(): ?User
    {
        return User::whereIn('id', $this->registry->ids(User::class))->where('is_moderator', true)->orderBy('id')->first();
    }

    /**
     * A fresh password for each of these people, after checking no real account holds one of their addresses.
     *
     * @param  array<string, array<string, mixed>>  $people
     * @return array<string, string>
     */
    private function passwordsFor(array $people): array
    {
        $emails = array_column($people, 'email');

        if ($taken = User::whereIn('email', $emails)->value('email')) {
            throw new RuntimeException('An account already uses '.$taken.' and it is not a demonstration account. Nothing was changed.');
        }

        $passwords = [];
        foreach ($emails as $email) {
            $passwords[$email] = $this->password();
        }

        return $passwords;
    }

    /** One transaction with every created record registered; the files of a failed run are deleted. */
    private function run(\Closure $work): void
    {
        // The content must be findable when the command returns, with no worker running (Scout indexes on save).
        config(['scout.queue' => false]);

        try {
            $this->registry->recording(fn () => DB::transaction($work));
        } catch (Throwable $e) {
            foreach ($this->written as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
            $this->registry->flush();

            throw $e;
        }

        $this->registry->flush();
        Cache::forget('sitemap.xml');
    }

    /**
     * New one-time passwords for the demonstration accounts; whoever held the old ones is signed out of "remember me".
     *
     * @return array<string, string>
     */
    public function rotate(): array
    {
        $passwords = [];

        foreach (User::whereIn('id', $this->registry->ids(User::class))->orderBy('id')->get() as $user) {
            $passwords[$user->email] = $this->password();
            $user->forceFill(['password' => Hash::make($passwords[$user->email]), 'remember_token' => Str::random(60)])->save();
        }

        return $passwords;
    }

    /** @return array{people: array<string, array<string, mixed>>, companies: array<string, array<string, mixed>>, questions: array<string, array<string, mixed>>, solutions: array<string, array<string, mixed>>, confirmation: array<string, mixed>, enquiry: array<string, mixed>} */
    public function content(): array
    {
        return require database_path('demo/content.php');
    }

    private function password(): string
    {
        return Str::password(20, symbols: false);
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, string>  $passwords
     */
    private function build(array $content, array $passwords): void
    {
        foreach ($content['people'] as $key => $person) {
            $this->people[$key] = $this->person($person, $passwords[$person['email']]);
        }

        foreach ($content['companies'] as $key => $company) {
            $this->companies[$key] = $this->company($company);
        }

        foreach ($content['solutions'] as $key => $solution) {
            $this->solutions[$key] = $this->solution($solution);
        }

        foreach ($content['questions'] as $question) {
            $this->question($question);
        }

        $this->confirm($content['confirmation']);
        $this->enquire($content['enquiry']);
    }

    /** @param array<string, mixed> $person */
    private function person(array $person, string $password): User
    {
        $user = User::create(['name' => $person['name'], 'email' => $person['email'], 'password' => Hash::make($password)]);
        // Verified, because the addresses are on a reserved domain: no verification mail could ever arrive.
        $user->forceFill(['email_verified_at' => now(), 'is_moderator' => (bool) ($person['moderator'] ?? false)])->save();

        // The personal workspace every account gets at sign-up (see CreateNewUser).
        $user->ownedTeams()->save(Team::forceCreate([
            'user_id' => $user->id,
            'name' => explode(' ', $user->name, 2)[0]."'s Team",
            'personal_team' => true,
        ]));

        $picture = $this->artwork->portrait($user->name, DemoArtwork::AREAS[$person['ground']]);
        $this->photos->store($user, $picture);
        $this->discard($picture);
        $this->written[] = [(string) config('jetstream.profile_photo_disk', 'public'), (string) $user->profile_photo_path];

        return $user;
    }

    /** @param array<string, mixed> $company */
    private function company(array $company): Team
    {
        $owner = $this->people[$company['owner']];

        /** @var Team $team */
        $team = $owner->ownedTeams()->create(['name' => $company['name'], 'personal_team' => false]);
        $team->forceFill(['industry' => $company['industry']])->save();

        $profile = $team->profile()->make($company['profile'] + ['visibility' => TeamProfile::PUBLIC]);
        $profile->save();
        $team->setRelation('profile', $profile);
        BusinessProfileEvent::record($team, $owner, 'listing_changed', 'created as '.TeamProfile::PUBLIC);

        // Only categories the directory already has; a missing one is skipped, never created.
        $team->categories()->sync(BusinessCategory::whereIn('slug', $company['categories'])->where('active', true)->pluck('id')->all());

        foreach ($company['services'] as $i => $data) {
            $service = new BusinessService($data);
            $service->team_id = $team->id;
            $service->sort = $i + 1;
            $service->save();
        }

        $project = new BusinessProject($company['project']);
        $project->team_id = $team->id;
        $project->sort = 1;
        $project->save();

        $location = new BusinessLocation($company['location']);
        $location->team_id = $team->id;
        $location->sort = 1;
        $location->save();

        $announcement = new BusinessAnnouncement($company['announcement'] + ['published_at' => now()]);
        $announcement->team_id = $team->id;
        $announcement->user_id = $owner->id;
        $announcement->save();

        foreach ($company['members'] as $member => $role) {
            $team->users()->attach($this->people[$member], ['role' => $role]);
            $this->people[$member]->forceFill(['current_team_id' => $team->id])->save();
        }
        $owner->forceFill(['current_team_id' => $team->id])->save();

        foreach (['logo' => $this->artwork->logo((string) $team->code), 'cover' => $this->artwork->cover((string) $team->code)] as $role => $picture) {
            $stored = $this->media->put($owner, $team, $role, $picture);
            $this->discard($picture);
            $this->written[] = [Attachment::DISK, $stored->path];
        }

        return $team->refresh();
    }

    /** @param array<string, mixed> $data */
    private function solution(array $data): Solutions
    {
        $author = $this->people[$data['by']];

        $solution = new Solutions;
        $solution->user_id = $author->id;
        $solution->team_id = $author->current_team_id;
        $solution->visibility = $data['public'] ? Solutions::VISIBILITY_PUBLIC : Solutions::VISIBILITY_COMPANY;
        $solution->solution_title = $data['title'];
        $solution->solution_description = $data['description'];
        $solution->tags = $data['tags'];
        $solution->duration = $data['duration'];
        $solution->duration_type = $data['duration_type'];
        $solution->steps = count($data['steps']);
        $solution->save();
        $solution->topics()->sync($this->topicIds($data['topics']));

        foreach ($data['steps'] as [$heading, $body]) {
            $step = new Steps;
            $step->user_id = $author->id;
            $step->solution_id = $solution->id;
            $step->solution_heading = $heading;
            $step->solution_body = $body;
            $step->save();
        }

        foreach ($data['comments'] ?? [] as $comment) {
            $this->comment($solution, 'solution', $comment);
        }

        return $solution;
    }

    /** @param array<string, mixed> $data */
    private function question(array $data): void
    {
        $asker = $this->people[$data['by']];

        $question = new Questions;
        $question->user_id = $asker->id;
        $question->team_id = $asker->current_team_id;
        $question->visibility = $data['public'] ? Questions::VISIBILITY_PUBLIC : Questions::VISIBILITY_COMPANY;
        $question->question = $data['question'];
        $question->description = $data['description'];
        $question->save();
        $question->topics()->sync($this->topicIds($data['topics']));

        // The same event the ask form raises, so milestones follow by the application's own rules.
        event(new QuestionSubmitted('infodot:question:'.$question->id, [
            'question_id' => $question->id,
            'author_uuid' => $asker->uuid,
            'tenant_team_id' => $question->team_id,
            'visibility' => $question->visibility,
        ]));

        foreach ($data['answers'] as $entry) {
            $answer = $this->answers->publish($question, $this->people[$entry['by']], $entry['body']);

            if ($entry['accept'] ?? false) {
                $this->answers->accept($answer, $asker);
            }
        }

        foreach ($data['comments'] ?? [] as $comment) {
            $this->comment($question, 'question', $comment);
        }
    }

    /**
     * A comment, and the notice its owner gets, as the comment box writes them (App\Livewire\Comments).
     *
     * @param  array{by: string, body: string}  $data
     */
    private function comment(Questions|Solutions $on, string $kind, array $data): void
    {
        $author = $this->people[$data['by']];

        $comment = $on->comments()->make(['body' => $data['body']]);
        $comment->user()->associate($author);
        $comment->save();

        $this->notices->record(UserNotice::COMMENT_RECEIVED, User::find($on->user_id), $kind, (int) $on->getKey(), 'infodot:comment:'.$comment->getKey(), $author);
    }

    /** @param array<string, mixed> $data */
    private function confirm(array $data): void
    {
        $solution = $this->solutions[$data['solution']];
        $person = $this->people[$data['by']];

        SolutionConfirmation::create(['solution_id' => $solution->id, 'user_id' => $person->id]
            + array_intersect_key($data, array_flip(['business_industry', 'applied_part', 'results', 'timeframe', 'recommend'])));

        $this->notices->record(UserNotice::SOLUTION_CONFIRMED, User::find($solution->user_id), 'solution', $solution->id, 'infodot:tried:'.$solution->id.':'.$person->id, $person);
    }

    /** @param array<string, mixed> $data */
    private function enquire(array $data): void
    {
        $enquiry = $this->enquiries->start($this->people[$data['from']], $this->companies[$data['to']], [
            'subject' => $data['subject'], 'category' => $data['category'], 'body' => $data['body'],
        ]);

        $this->enquiries->reply($enquiry, $this->people[$data['reply_by']], $data['reply']);
    }

    /**
     * Topics that exist on this site, by name. A site that has not published the business topics yet simply files
     * the content under fewer topics: creating topics is a moderator's decision, not the demonstration's.
     *
     * @param  list<string>  $names
     * @return list<int>
     */
    private function topicIds(array $names): array
    {
        return Topic::whereIn('slug', array_map(fn (string $n) => Str::slug($n), $names))->where('active', true)
            ->limit(3)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** The drawn picture's temporary file, once the pipeline has stored its own copy. */
    private function discard(UploadedFile $picture): void
    {
        @unlink((string) $picture->getRealPath());
    }

    /**
     * What is on the register, counted by kind, for the commands' reports.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];

        foreach (DemoRecord::query()->toBase()->selectRaw('record_type, count(*) as n')->groupBy('record_type')->orderBy('record_type')->get() as $row) {
            $counts[class_basename((string) $row->record_type)] = (int) $row->n;
        }

        return $counts;
    }

    /**
     * The demonstration companies, for the commands' pointers.
     *
     * @return Collection<int, Team>
     */
    public function companies(): Collection
    {
        return Team::whereIn('id', $this->registry->ids(Team::class))->where('personal_team', false)->orderBy('id')->get();
    }
}
