<?php

namespace Tests\Feature\Stabilisation;

use App\Models\Attachment;
use App\Models\User;
use App\Support\ImageBudget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R-48 S-04. Images up to 50 megapixels were decoded whole to make a thumbnail. Past PHP's memory limit that is a
 * fatal error no catch block sees: a 500, and a stored file with no database row. The thumbnail is optional, so a
 * picture too large to decode in the memory available is now stored without one.
 */
class LargeImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private string $limit;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Attachment::DISK);
        $this->limit = (string) ini_get('memory_limit');
    }

    protected function tearDown(): void
    {
        ini_set('memory_limit', $this->limit);
        parent::tearDown();
    }

    private function leaveRoomFor(int $megabytes): void
    {
        ini_set('memory_limit', (string) (memory_get_usage(true) + $megabytes * 1024 * 1024));
    }

    public function test_the_budget_refuses_a_picture_that_would_not_fit_and_accepts_one_that_does(): void
    {
        $this->leaveRoomFor(60);

        $this->assertFalse(ImageBudget::fits(4032, 3024)); // 12 MP needs about 61 MB plus headroom
        $this->assertTrue(ImageBudget::fits(1600, 1200));
        $this->assertFalse(ImageBudget::fits(0, 100));

        ini_set('memory_limit', '-1');
        $this->assertTrue(ImageBudget::fits(8064, 6048));
    }

    public function test_a_picture_too_large_to_decode_is_stored_without_a_thumbnail_not_refused_or_crashed(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $photo = UploadedFile::fake()->image('IMG_0001.jpg', 4032, 3024);
        $this->leaveRoomFor(60);

        $response = $this->actingAs($user)->post('/attachments', ['file' => $photo], ['Accept' => 'application/json']);

        $response->assertCreated();
        $attachment = Attachment::firstOrFail();
        $this->assertNull($attachment->thumb_path);
        $this->assertSame(4032, $attachment->meta['width']);
        Storage::disk(Attachment::DISK)->assertExists($attachment->path);
        $this->assertNull($response->json('thumb'));
    }

    public function test_an_ordinary_picture_still_gets_its_thumbnail(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)->post('/attachments', ['file' => UploadedFile::fake()->image('small.jpg', 1200, 900)], ['Accept' => 'application/json'])->assertCreated();

        $attachment = Attachment::firstOrFail();
        $this->assertNotNull($attachment->thumb_path);
        Storage::disk(Attachment::DISK)->assertExists($attachment->thumb_path);
    }

    public function test_every_stored_file_has_its_row_before_the_thumbnail_is_attempted(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $rowsSeenWhenThumbWasWritten = null;

        Storage::disk(Attachment::DISK)->buildTemporaryUrlsUsing(fn () => '');
        Attachment::updating(function (Attachment $a) use (&$rowsSeenWhenThumbWasWritten) {
            if ($a->isDirty('thumb_path')) {
                $rowsSeenWhenThumbWasWritten = Attachment::count();
            }
        });

        $this->actingAs($user)->post('/attachments', ['file' => UploadedFile::fake()->image('small.jpg', 1200, 900)], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame(1, $rowsSeenWhenThumbWasWritten, 'the row must exist before the decode, so a crash there cannot leave a file nothing points to');
    }
}
