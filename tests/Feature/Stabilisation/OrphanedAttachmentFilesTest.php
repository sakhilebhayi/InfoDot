<?php

namespace Tests\Feature\Stabilisation;

use App\Actions\Jetstream\CreateTeam;
use App\Models\Answer;
use App\Models\Attachment;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Steps;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Jetstream\Contracts\DeletesUsers;
use Tests\Feature\Knowledge\Concerns\MakesCommunity;
use Tests\TestCase;

/**
 * R-49. Deleting an account removes the person's solutions, steps and answers, and their businesses, in the database
 * (by cascade), which skips the code that deletes an attachment's file. The files a deleted person or business had
 * uploaded stayed on disk for good with rows pointing at nothing. The daily attachments:prune now also removes an
 * attachment whose owner record no longer exists, and its file with it.
 */
class OrphanedAttachmentFilesTest extends TestCase
{
    use MakesCommunity;
    use RefreshDatabase;

    private function fileOn(Model $owner, int $userId, string $name): Attachment
    {
        $path = 'attachments/test/'.Str::random(12).'-'.$name;
        Storage::disk('local')->put($path, 'file body');

        $attachment = new Attachment;
        $attachment->forceFill([
            'user_id' => $userId, 'attachable_type' => $owner->getMorphClass(), 'attachable_id' => $owner->getKey(),
            'kind' => Attachment::IMAGE, 'path' => $path, 'name' => $name, 'mime' => 'image/jpeg', 'size' => 9,
        ])->save();

        return $attachment;
    }

    public function test_files_of_a_deleted_account_and_its_business_are_removed_by_the_daily_prune(): void
    {
        Storage::fake('local');

        $leaver = $this->person('Leaver');
        $stayer = $this->person('Stayer');
        $business = app(CreateTeam::class)->create($leaver, ['name' => 'Leaver Trading', 'visibility' => 'private']);
        $leaver->switchTeam(Team::where('user_id', $leaver->id)->where('personal_team', true)->firstOrFail());

        $solution = $this->solution($leaver);
        $step = Steps::forceCreate(['solution_id' => $solution->id, 'user_id' => $leaver->id, 'solution_heading' => 'First', 'solution_body' => 'Do it carefully.']);
        $answer = $this->answer($this->question($stayer), $leaver);
        $keptQuestion = $this->question($stayer, 'public', 'A question that stays');

        $gone = [
            $this->fileOn($solution, $leaver->id, 'solution.jpg'),
            $this->fileOn($step, $leaver->id, 'step.jpg'),
            $this->fileOn($answer, $leaver->id, 'answer.jpg'),
            $this->fileOn($business, $leaver->id, 'logo.jpg'),
        ];
        $kept = $this->fileOn($keptQuestion, $stayer->id, 'kept.jpg');
        $withdrawn = $this->answer($keptQuestion, $this->person('Third'));
        $keptOnWithdrawn = $this->fileOn($withdrawn, $withdrawn->user_id, 'withdrawn.jpg');
        $withdrawn->delete(); // a soft delete: the row is still there and may come back

        app(DeletesUsers::class)->delete($leaver->fresh());

        $this->assertNull(Solutions::withoutGlobalScopes()->find($solution->id));
        $this->assertNull(Answer::withTrashed()->find($answer->id));
        $this->assertNull(Team::find($business->id));
        $this->assertNotNull(Questions::withoutGlobalScopes()->find($keptQuestion->id));

        $this->travel(2)->days();
        $this->artisan('attachments:prune')->assertExitCode(0);

        foreach ($gone as $attachment) {
            $this->assertNull(Attachment::find($attachment->id), $attachment->name.' row is still there');
            $this->assertFalse(Storage::disk('local')->exists($attachment->path), $attachment->name.' file is still there');
        }

        foreach ([$kept, $keptOnWithdrawn] as $attachment) {
            $this->assertNotNull(Attachment::find($attachment->id), $attachment->name.' row was removed');
            $this->assertTrue(Storage::disk('local')->exists($attachment->path), $attachment->name.' file was removed');
        }
    }

    public function test_a_file_attached_a_moment_ago_is_left_alone(): void
    {
        Storage::fake('local');
        $person = $this->person();
        $question = $this->question($person);
        $attachment = $this->fileOn($question, $person->id, 'new.jpg');
        Questions::withoutGlobalScopes()->whereKey($question->id)->toBase()->delete();

        $this->artisan('attachments:prune')->assertExitCode(0);

        $this->assertNotNull(Attachment::find($attachment->id));
    }
}
