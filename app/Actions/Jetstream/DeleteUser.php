<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Services\Participation\ParticipationClient;
use App\Services\Privacy\SeminarDataEraser;
use App\Services\Sso\BackchannelLogoutService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\DeletesTeams;
use Laravel\Jetstream\Contracts\DeletesUsers;
use Throwable;

/**
 * Deletes a person's account and what goes with it. A business the person owns alone goes too, with its files. A
 * business that has other members does not (R-49): deletion is refused until its ownership has been transferred or
 * the business itself has been deleted, so nobody loses their company because its owner left InfoDot.
 */
class DeleteUser implements DeletesUsers
{
    /**
     * The team deleter implementation.
     *
     * @var DeletesTeams
     */
    protected $deletesTeams;

    /**
     * Create a new action instance.
     *
     * @return void
     */
    public function __construct(DeletesTeams $deletesTeams)
    {
        $this->deletesTeams = $deletesTeams;
    }

    /**
     * Delete the given user.
     *
     * @param  mixed  $user
     * @return void
     */
    public function delete($user)
    {
        // Before anything is erased or cancelled: a refusal must leave the account exactly as it was.
        $this->refuseWhileOthersDependOnTheirBusinesses($user);

        $this->eraseFromMemory($user);
        // Upcoming seminars are cancelled and tickets refunded first: that talks to Finance, so it runs outside the transaction.
        app(SeminarDataEraser::class)->settle($user);
        $this->endSessionsElsewhere($user);

        DB::transaction(function () use ($user) {
            // Hold the person's row, so a request that is creating a business for them finishes before the teams are read.
            DB::table($user->getTable())->where($user->getKeyName(), $user->getKey())->lockForUpdate()->exists();
            // Asked again under the lock: someone may have joined one of the businesses since the first look.
            $this->refuseWhileOthersDependOnTheirBusinesses($user);

            app(SeminarDataEraser::class)->erase($user);
            $this->deleteTeams($user);
            $user->deleteProfilePhoto();
            $user->tokens->each->delete();
            $user->delete();
        });
    }

    /**
     * The businesses this person owns that other people are members of. While there is one, the account stays.
     *
     * @param  mixed  $user
     * @return Collection<int, Team>
     */
    public function businessesOthersDependOn($user): Collection
    {
        return $user->ownedTeams()->where('personal_team', false)
            ->whereHas('users', fn ($members) => $members->where('users.id', '!=', $user->getKey()))
            ->orderBy('name')->get();
    }

    /** What the person is told, by name, with the two ways forward. */
    public static function refusal(Collection $businesses): string
    {
        $names = $businesses->pluck('name')->map(fn ($name) => '“'.$name.'”')->join(', ', ' and ');

        return $businesses->count() === 1
            ? "Your account cannot be deleted while you own $names, because other people are members of it. Transfer its ownership to one of them, or delete the company, in its team settings; then delete your account."
            : "Your account cannot be deleted while you own $names, because other people are members of them. For each one, transfer its ownership to a member or delete the company in its team settings; then delete your account.";
    }

    /**
     * @param  mixed  $user
     *
     * @throws ValidationException
     */
    protected function refuseWhileOthersDependOnTheirBusinesses($user): void
    {
        $businesses = $this->businessesOthersDependOn($user);

        if ($businesses->isNotEmpty()) {
            throw ValidationException::withMessages(['account' => self::refusal($businesses)]);
        }
    }

    /**
     * A person who let Dot.Memory remember their topics is erased there too. Best effort and never in the way of deleting
     * the account: if Memory cannot be reached, its own retention removes what it holds.
     *
     * @param  mixed  $user
     * @return void
     */
    protected function eraseFromMemory($user)
    {
        if (! $user->sharesExpertise()) {
            return;
        }

        try {
            app(ParticipationClient::class)->erase($user);
        } catch (Throwable $e) {
            Log::warning('participation.erase_on_account_deletion_failed', ['exception' => $e::class]);
        }
    }

    /**
     * The Dot platforms the person is signed in to are told to end their sessions and the provider tokens are revoked,
     * the same way a central sign-out does it. It runs before the row goes (the platforms are told who by the account's
     * stable id) and never stands in the way of deleting the account.
     *
     * @param  mixed  $user
     * @return void
     */
    protected function endSessionsElsewhere($user)
    {
        try {
            app(BackchannelLogoutService::class)->propagate($user);
        } catch (Throwable $e) {
            Log::warning('sso.logout_on_account_deletion_failed', ['exception' => $e::class]);
        }
    }

    /**
     * Delete the teams and team associations attached to the user.
     *
     * @param  mixed  $user
     * @return void
     */
    protected function deleteTeams($user)
    {
        $user->teams()->detach();

        // Read now, never from what the model already holds: a business created a moment ago must go too.
        $user->ownedTeams()->get()->each(function ($team) {
            $this->deletesTeams->delete($team);
        });
    }
}
