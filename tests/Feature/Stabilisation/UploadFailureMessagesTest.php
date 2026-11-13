<?php

namespace Tests\Feature\Stabilisation;

use App\Livewire\Profile\UpdateProfileInformationForm;
use App\Models\Attachment;
use App\Models\User;
use App\Support\UploadLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A request larger than PHP accepts never reaches a controller: PHP empties it and Laravel answers 413 with no words.
 * The attachment uploader showed "The upload failed. Try again." and the profile picture form "The photo failed to
 * upload.", and trying again could not help. Both callers are now told the file is too large and what the limit is,
 * and an upload that arrived broken says whether to retry.
 */
class UploadFailureMessagesTest extends TestCase
{
    use RefreshDatabase;

    private const TOO_LARGE = '/^That file is too large to upload\. The limit is \d+ MB\.$/';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        Storage::fake(Attachment::DISK);
        $this->user = User::factory()->withPersonalTeam()->create();
    }

    /** A body larger than post_max_size: PHP has already thrown the content away, only the declared length is left. */
    private function oversized(string $uri, array $headers = [])
    {
        return $this->actingAs($this->user)->call('POST', $uri, [], [], [], array_merge(['CONTENT_LENGTH' => (string) (2 ** 40)], $headers));
    }

    private function broken(int $error): UploadedFile
    {
        return new UploadedFile(__FILE__, 'IMG_0001.jpg', 'image/jpeg', $error, true);
    }

    private function livewireUploadUrl(): string
    {
        return URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), [], false);
    }

    public function test_the_limit_is_what_php_accepts_in_whole_megabytes(): void
    {
        $bytes = UploadedFile::getMaxFilesize();

        $this->assertSame((int) floor($bytes / 1048576), UploadLimit::megabytes());
        $this->assertSame('That file is too large to upload. The limit is '.UploadLimit::megabytes().' MB.', UploadLimit::tooLargeMessage());
    }

    public function test_an_oversized_attachment_post_tells_a_json_caller_why(): void
    {
        $r = $this->oversized('/attachments', ['HTTP_ACCEPT' => 'application/json'])->assertStatus(413);

        $this->assertMatchesRegularExpression(self::TOO_LARGE, $r->json('message'));
        // resources/js/attachments.js shows errors.file[0], then message.
        $this->assertSame($r->json('message'), $r->json('errors.file.0'));
    }

    public function test_an_oversized_livewire_upload_answers_in_the_one_shape_livewire_reads(): void
    {
        // Livewire's uploader passes the response body on only for a 422; any other status becomes "failed to upload".
        $r = $this->oversized($this->livewireUploadUrl(), ['HTTP_ACCEPT' => 'application/json'])->assertStatus(422);

        $this->assertMatchesRegularExpression(self::TOO_LARGE, $r->json('errors')['files.0'][0]);

        Livewire::actingAs($this->user)->test(UpdateProfileInformationForm::class)
            ->call('_uploadErrored', 'photo', $r->getContent(), false)
            ->assertHasErrors('photo')
            ->assertSee(UploadLimit::tooLargeMessage());
    }

    public function test_an_oversized_form_post_gets_a_page_that_says_so(): void
    {
        $this->oversized('/attachments')->assertStatus(413)
            ->assertSee('That file is too large')
            ->assertSee('The limit for one upload is '.UploadLimit::megabytes().' MB.')
            ->assertSee('Nothing was saved')
            ->assertSee('Go back and choose a smaller file');
    }

    public function test_a_file_over_phps_per_file_limit_is_called_too_large_not_failed(): void
    {
        $r = $this->actingAs($this->user)->post('/attachments', ['file' => $this->broken(UPLOAD_ERR_INI_SIZE)], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertMatchesRegularExpression(self::TOO_LARGE, $r->json('errors.file.0'));
        $this->assertSame(0, Attachment::count());
    }

    public function test_an_upload_cut_off_on_the_way_says_to_try_again(): void
    {
        $r = $this->actingAs($this->user)->post('/attachments', ['file' => $this->broken(UPLOAD_ERR_PARTIAL)], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame('The upload was cut off before it finished. Check your connection and try again.', $r->json('errors.file.0'));
    }

    public function test_a_fault_on_the_server_is_not_blamed_on_the_person_and_is_logged(): void
    {
        Log::spy();

        $r = $this->actingAs($this->user)->post('/attachments', ['file' => $this->broken(UPLOAD_ERR_NO_TMP_DIR)], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame('We could not save that file because of a problem on our side. Please try again in a few minutes.', $r->json('errors.file.0'));
        Log::shouldHaveReceived('error')->withArgs(fn ($m, $ctx = []) => str_contains($m, 'upload') && ($ctx['php_upload_error'] ?? null) === UPLOAD_ERR_NO_TMP_DIR && ! str_contains(json_encode($ctx), 'IMG_0001'))->once();
    }

    public function test_a_broken_livewire_upload_reaches_the_form_with_its_reason(): void
    {
        $r = $this->actingAs($this->user)->post($this->livewireUploadUrl(), ['files' => [$this->broken(UPLOAD_ERR_INI_SIZE)]], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertMatchesRegularExpression(self::TOO_LARGE, $r->json('errors')['files.0'][0]);

        Livewire::actingAs($this->user)->test(UpdateProfileInformationForm::class)
            ->call('_uploadErrored', 'photo', $r->getContent(), false)
            ->assertHasErrors('photo')
            ->assertSee(UploadLimit::tooLargeMessage());
    }

    public function test_a_good_upload_is_untouched(): void
    {
        $this->actingAs($this->user)->post('/attachments', ['file' => UploadedFile::fake()->image('ok.jpg', 600, 400)], ['Accept' => 'application/json'])->assertCreated();

        Storage::fake('tmp-for-tests'); // the disk Livewire's temporary uploads use under test
        $this->actingAs($this->user)->post($this->livewireUploadUrl(), ['files' => [UploadedFile::fake()->image('ok.jpg', 600, 400)]], ['Accept' => 'application/json'])->assertOk()->assertJsonStructure(['paths']);
    }
}
