<?php

namespace App\Actions\Jetstream;

use App\Services\Participation\ParticipationClient;
use App\Services\Privacy\SeminarDataEraser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Jetstream\Contracts\DeletesTeams;
use Laravel\Jetstream\Contracts\DeletesUsers;
use Throwable;

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
        $this->eraseFromMemory($user);
        // Upcoming seminars are cancelled and tickets refunded first: that talks to Billing, so it runs outside the transaction.
        app(SeminarDataEraser::class)->settle($user);

        DB::transaction(function () use ($user) {
            app(SeminarDataEraser::class)->erase($user);
            $this->deleteTeams($user);
            $user->deleteProfilePhoto();
            $user->tokens->each->delete();
            $user->delete();
        });
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
     * Delete the teams and team associations attached to the user.
     *
     * @param  mixed  $user
     * @return void
     */
    protected function deleteTeams($user)
    {
        $user->teams()->detach();

        $user->ownedTeams->each(function ($team) {
            $this->deletesTeams->delete($team);
        });
    }
}
