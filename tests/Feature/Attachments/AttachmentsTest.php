<?php

namespace Tests\Feature\Attachments;

use App\Livewire\Answers;
use App\Livewire\Comments;
use App\Models\Attachment;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Steps;
use App\Models\Topic;
use App\Models\User;
use App\Services\Attachments\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** R-15 acceptance: upload rules, binding, the permission matrix, serving with Range, pruning, extraction. */
class AttachmentsTest extends TestCase
{
    use RefreshDatabase;

    /** A real, tiny, uncompressed PDF with readable text, so fileinfo and the parser both accept it. */
    private function pdfBytes(string $text = 'Quarterly cash figures'): string
    {
        $stream = "BT /F1 12 Tf 20 700 Td ($text) Tj ET";
        $objects = [
            "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n",
            "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n",
            "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n",
            '4 0 obj<</Length '.strlen($stream).">>stream\n".$stream."\nendstream endobj\n",
            "5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer<</Size 6/Root 1 0 R>>\nstartxref\n".$xref."\n%%EOF";
    }

    /** Enough of an MP4 header for fileinfo to call it video/mp4. */
    private function mp4Bytes(): string
    {
        return "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat('A', 4096);
    }

    private function member(): User
    {
        return User::factory()->withPersonalTeam()->create();
    }

    private function uploadFor(User $user, UploadedFile $file): Attachment
    {
        return app(AttachmentService::class)->upload($user, $file);
    }

    public function test_an_image_upload_is_stored_sniffed_and_unbound(): void
    {
        $user = $this->member();

        $response = $this->actingAs($user)->post('/attachments', ['file' => UploadedFile::fake()->image('shop front.jpg', 640, 480)]);

        $response->assertCreated()->assertJsonStructure(['uuid', 'kind', 'name', 'size', 'url', 'thumb']);
        $attachment = Attachment::where('uuid', $response->json('uuid'))->firstOrFail();
        $this->assertSame(Attachment::IMAGE, $attachment->kind);
        $this->assertFalse($attachment->isBound());
        $this->assertNotNull($attachment->expires_at);
        $this->assertTrue(Storage::disk('local')->exists($attachment->path));
        $this->assertSame(640, $attachment->meta['width']);
        $this->assertNotNull($attachment->thumb_path, 'GD made a thumbnail');
    }

    public function test_disguised_and_unsupported_files_are_refused(): void
    {
        $user = $this->member();

        // An executable pretending to be an image: content sniffing wins over the name.
        $this->actingAs($user)->post('/attachments', [
            'file' => UploadedFile::fake()->createWithContent('photo.jpg', "\x7fELF\x02\x01\x01".str_repeat("\0", 64)),
        ])->assertSessionHasErrors('file');

        // SVG is scriptable and deliberately unsupported.
        $this->actingAs($user)->post('/attachments', [
            'file' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ])->assertSessionHasErrors('file');

        // A PDF whose extension says image: the pair must agree.
        $this->actingAs($user)->post('/attachments', [
            'file' => UploadedFile::fake()->createWithContent('scan.png', $this->pdfBytes()),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, Attachment::count());
    }

    public function test_size_caps_and_the_daily_quota_hold(): void
    {
        config(['attachments.kinds.image.max_bytes' => 1024, 'attachments.daily.files' => 2]);
        $user = $this->member();

        $this->actingAs($user)->post('/attachments', ['file' => UploadedFile::fake()->image('big.jpg', 600, 600)])
            ->assertSessionHasErrors('file');

        config(['attachments.kinds.image.max_bytes' => 10 * 1024 * 1024]);
        $this->actingAs($user)->post('/attachments', ['file' => UploadedFile::fake()->image('a.jpg')])->assertCreated();
        $this->actingAs($user)->post('/attachments', ['file' => UploadedFile::fake()->image('b.jpg')])->assertCreated();
        $this->actingAs($user)->post('/attachments', ['file' => UploadedFile::fake()->image('c.jpg')])
            ->assertSessionHasErrors('file');
    }

    public function test_an_unbound_upload_is_owner_only_and_removable(): void
    {
        $owner = $this->member();
        $other = $this->member();
        $attachment = $this->uploadFor($owner, UploadedFile::fake()->image('mine.jpg'));

        $this->actingAs($owner)->get(route('attachments.show', $attachment))->assertOk();
        $this->actingAs($other)->get(route('attachments.show', $attachment))->assertNotFound();
        $this->actingAs($other)->delete(route('attachments.destroy', $attachment))->assertNotFound();

        $path = $attachment->path;
        $this->actingAs($owner)->delete(route('attachments.destroy', $attachment))->assertNoContent();
        $this->assertNull(Attachment::find($attachment->id));
        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    public function test_a_question_binds_uploads_and_visibility_follows_the_question(): void
    {
        $asker = $this->member();
        $teamMate = $this->member();
        $asker->currentTeam->users()->attach($teamMate, ['role' => 'member']);
        $outsider = $this->member();
        $topic = Topic::first() ?? Topic::create(['slug' => 't', 'name' => 'Topic', 'active' => true, 'sort_order' => 10]);

        $a = $this->uploadFor($asker, UploadedFile::fake()->image('report page.png'));
        $b = $this->uploadFor($asker, UploadedFile::fake()->createWithContent('budget.pdf', $this->pdfBytes()));

        $this->actingAs($asker)->post(route('questions.add'), [
            'question' => 'Why is the project over budget?',
            'description' => 'The figures are attached.',
            'topics' => [$topic->id],
            'attachments' => [
                ['uuid' => $b->uuid, 'caption' => 'The current budget'],
                ['uuid' => $a->uuid, 'caption' => ''],
            ],
        ])->assertRedirect('questions');

        $question = Questions::withoutGlobalScopes()->latest('id')->firstOrFail();
        $bound = $question->attachments;
        $this->assertSame([$b->uuid, $a->uuid], $bound->pluck('uuid')->all(), 'form order is display order');
        $this->assertSame('The current budget', $bound[0]->caption);
        $this->assertNull($bound[0]->expires_at);

        // Company-only question: the team sees the file, an outsider does not even learn it exists.
        $this->actingAs($teamMate)->get(route('attachments.show', $bound[0]))->assertOk();
        $this->actingAs($outsider)->get(route('attachments.show', $bound[0]))->assertNotFound();
        $this->actingAs($outsider)->get(route('attachments.thumb', $bound[1]))->assertNotFound();

        // Published, everyone signed in may see it.
        $question->forceFill(['visibility' => Questions::VISIBILITY_PUBLIC])->save();
        $this->actingAs($outsider)->get(route('attachments.show', $bound[0]))->assertOk();
    }

    public function test_a_solution_binds_files_to_itself_and_to_its_steps(): void
    {
        $author = $this->member();
        $topic = Topic::first() ?? Topic::create(['slug' => 't', 'name' => 'Topic', 'active' => true, 'sort_order' => 10]);

        $whole = $this->uploadFor($author, UploadedFile::fake()->createWithContent('sop.pdf', $this->pdfBytes()));
        $stepShot = $this->uploadFor($author, UploadedFile::fake()->image('screen.png'));

        $this->actingAs($author)->post(route('solutions.add'), [
            'solution_title' => 'Reconcile the bank every Monday',
            'solution_description' => 'A repeatable monthly close.',
            'tags-input' => 'bookkeeping, reconciliation',
            'topics' => [$topic->id],
            'duration' => 2,
            'duration_type' => 'hours',
            'publish' => 1,
            'solution_heading' => ['Open the statement', 'Tick the entries'],
            'solution_body' => ['Download the month.', 'Match line by line.'],
            'attachments' => [['uuid' => $whole->uuid, 'caption' => 'The SOP document']],
            'step_attachments' => [1 => [['uuid' => $stepShot->uuid, 'caption' => 'What matching looks like']]],
        ])->assertRedirect(route('solutions'));

        $solution = Solutions::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertSame([$whole->uuid], $solution->attachments->pluck('uuid')->all());

        $second = Steps::where('solution_id', $solution->id)->orderBy('id')->get()[1];
        $this->assertSame([$stepShot->uuid], $second->attachments->pluck('uuid')->all());

        // The page shows both, and deleting the solution removes every file, the steps' included.
        $this->actingAs($author)->get(route('solutions.view', ['id' => $solution->id]))
            ->assertOk()->assertSee('The SOP document')->assertSee('What matching looks like');

        $paths = [$whole->fresh()->path, $stepShot->fresh()->path];
        $solution->delete();
        $this->assertSame(0, Attachment::count());
        foreach ($paths as $path) {
            $this->assertFalse(Storage::disk('local')->exists($path));
        }
    }

    public function test_answers_and_comments_carry_attachments_through_livewire(): void
    {
        $asker = $this->member();
        $helper = $this->member();
        $topic = Topic::first() ?? Topic::create(['slug' => 't', 'name' => 'Topic', 'active' => true, 'sort_order' => 10]);

        $question = new Questions;
        $question->forceFill([
            'user_id' => $asker->id, 'team_id' => $asker->current_team_id,
            'visibility' => Questions::VISIBILITY_PUBLIC, 'question' => 'How do I price a service?', 'description' => 'Details.',
        ])->save();
        $question->topics()->sync([$topic->id]);

        $proof = $this->uploadFor($helper, UploadedFile::fake()->image('calc.png'));
        Livewire::actingAs($helper)->test(Answers::class, ['question' => $question])
            ->set('body', 'Work out the cost floor first, then set the margin deliberately.')
            ->set('attachmentsPayload', json_encode([['uuid' => $proof->uuid, 'caption' => 'The worked example']]))
            ->call('publish')->assertHasNoErrors();

        $this->assertSame('The worked example', $question->answers()->first()->attachments->first()->caption);

        $shot = $this->uploadFor($asker, UploadedFile::fake()->image('context.png'));
        Livewire::actingAs($asker)->test(Comments::class, ['model' => $question, 'question' => $question])
            ->set('newCommentState.body', 'Here is the missing context.')
            ->set('attachmentsPayload', json_encode([['uuid' => $shot->uuid, 'caption' => '']]))
            ->call('postComment')->assertHasNoErrors();

        $this->assertSame($shot->uuid, $question->comments()->first()->attachments->first()->uuid);
    }

    public function test_video_streams_with_range_support(): void
    {
        $user = $this->member();
        $video = $this->uploadFor($user, UploadedFile::fake()->createWithContent('demo.mp4', $this->mp4Bytes()));
        $this->assertSame(Attachment::VIDEO, $video->kind);

        $whole = $this->actingAs($user)->get(route('attachments.show', $video));
        $whole->assertOk()->assertHeader('Accept-Ranges', 'bytes');

        $part = $this->actingAs($user)->get(route('attachments.show', $video), ['Range' => 'bytes=4-11']);
        $part->assertStatus(206)->assertHeader('Content-Range', 'bytes 4-11/'.$video->size);
        $this->assertSame('ftypmp42', $part->streamedContent());

        $this->actingAs($user)->get(route('attachments.show', $video), ['Range' => 'bytes='.$video->size.'-'])->assertStatus(416);
    }

    public function test_hidden_content_restricts_its_files_to_the_people_involved(): void
    {
        $author = $this->member();
        $stranger = $this->member();
        $moderator = $this->member();
        $moderator->forceFill(['is_moderator' => true])->save();

        $file = $this->uploadFor($author, UploadedFile::fake()->image('evidence.jpg'));
        $question = new Questions;
        $question->forceFill([
            'user_id' => $author->id, 'team_id' => $author->current_team_id,
            'visibility' => Questions::VISIBILITY_PUBLIC, 'question' => 'A public question?', 'description' => 'x',
        ])->save();
        app(AttachmentService::class)->bind([['uuid' => $file->uuid]], $question, $author);

        $this->actingAs($stranger)->get(route('attachments.show', $file))->assertOk();

        $question->forceFill(['hidden_at' => now(), 'hidden_by' => $moderator->id])->save();

        $this->actingAs($stranger)->get(route('attachments.show', $file))->assertNotFound();
        $this->actingAs($author)->get(route('attachments.show', $file))->assertOk();
        $this->actingAs($moderator)->get(route('attachments.show', $file))->assertOk();
    }

    public function test_prune_deletes_expired_unbound_uploads_only(): void
    {
        $user = $this->member();
        $stale = $this->uploadFor($user, UploadedFile::fake()->image('old.jpg'));
        $fresh = $this->uploadFor($user, UploadedFile::fake()->image('new.jpg'));
        $stale->forceFill(['expires_at' => now()->subHour()])->save();
        $stalePath = $stale->path;

        $this->artisan('attachments:prune')->assertExitCode(0);

        $this->assertNull(Attachment::find($stale->id));
        $this->assertFalse(Storage::disk('local')->exists($stalePath));
        $this->assertNotNull(Attachment::find($fresh->id));
    }

    public function test_pdf_text_is_extracted_and_joins_the_search_document(): void
    {
        $author = $this->member();
        $pdf = $this->uploadFor($author, UploadedFile::fake()->createWithContent('figures.pdf', $this->pdfBytes('Quarterly cash figures')));

        $question = new Questions;
        $question->forceFill([
            'user_id' => $author->id, 'team_id' => $author->current_team_id,
            'visibility' => Questions::VISIBILITY_PUBLIC, 'question' => 'Where does the cash go?', 'description' => 'See the attached figures.',
        ])->save();
        app(AttachmentService::class)->bind([['uuid' => $pdf->uuid]], $question, $author);

        app(AttachmentService::class)->extractText($pdf->fresh());

        $extracted = $pdf->fresh();
        $this->assertStringContainsString('Quarterly cash figures', (string) $extracted->extracted_text);
        $this->assertSame(1, $extracted->meta['pages']);
        $this->assertStringContainsString('Quarterly cash figures', $question->fresh()->toSearchableArray()['attachment_text']);
    }
}
