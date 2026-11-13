<?php

namespace Tests\Feature\Stabilisation;

use App\Livewire\Profile\UpdateProfileInformationForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R-48 S-03. Production 2026-11-12, four times: saving a profile picture answered 500 with "Missing required
 * parameter for [Route: profile.show]". The app's own route named profile.show (/user/profile/{id}) replaces
 * Jetstream's, and Jetstream redirects to that name with no id after a photo is saved. The saved pictures could
 * not be shown either: the host has no public storage link, so /storage/... reached nothing.
 */
class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function jpeg(int $width, int $height, string $name = 'IMG_0001.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }

    private function save(User $user, UploadedFile $photo)
    {
        return Livewire::actingAs($user)->test('profile.update-profile-information-form')
            ->set('photo', $photo)
            ->call('updateProfileInformation');
    }

    public function test_the_profile_form_is_the_apps_own_component(): void
    {
        $this->actingAs(User::factory()->withPersonalTeam()->create());

        Livewire::test('profile.update-profile-information-form')->assertOk();
        $this->assertInstanceOf(UpdateProfileInformationForm::class, Livewire::new('profile.update-profile-information-form'));
    }

    public function test_saving_a_picture_stores_it_and_returns_to_the_account_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->save($user, $this->jpeg(800, 600))->assertHasNoErrors()->assertRedirect(route('profile.edit'));

        $path = $user->fresh()->profile_photo_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_phone_sized_picture_is_accepted_and_stored_small(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->save($user, $this->jpeg(4032, 3024))->assertHasNoErrors();

        $path = $user->fresh()->profile_photo_path;
        $this->assertStringEndsWith('.jpg', $path);
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertLessThanOrEqual(640, max($width, $height));
        $this->assertSame(640, $width);
    }

    public function test_replacing_a_picture_removes_the_old_file(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->save($user, $this->jpeg(400, 400));
        $first = $user->fresh()->profile_photo_path;

        $this->save($user->fresh(), $this->jpeg(500, 500));
        $second = $user->fresh()->profile_photo_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_removing_a_picture_clears_it(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->save($user, $this->jpeg(400, 400));
        $path = $user->fresh()->profile_photo_path;

        Livewire::actingAs($user->fresh())->test('profile.update-profile-information-form')->call('deleteProfilePhoto');

        $this->assertNull($user->fresh()->profile_photo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_file_that_is_not_a_picture_is_refused_with_a_message(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->save($user, UploadedFile::fake()->create('notes.pdf', 200, 'application/pdf'))->assertHasErrors('photo');

        $this->assertNull($user->fresh()->profile_photo_path);
    }

    public function test_a_picture_over_the_size_limit_is_refused_with_a_message(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->save($user, UploadedFile::fake()->image('huge.jpg', 100, 100)->size(10241))->assertHasErrors('photo');

        $this->assertNull($user->fresh()->profile_photo_path);
    }

    public function test_the_form_states_the_limits(): void
    {
        $html = $this->actingAs(User::factory()->withPersonalTeam()->create())->get('/user/profile/edit')->assertOk()->getContent();

        $this->assertStringContainsString('JPG, PNG or WebP, up to 10 MB', $html);
        $this->assertStringContainsString('accept="image/jpeg,image/png,image/webp"', $html);
    }

    public function test_a_stored_picture_is_served_by_the_application(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->save($user, $this->jpeg(400, 400));
        $user = $user->fresh();

        $this->assertStringContainsString('/storage/profile-photos/', $user->profile_photo_url);

        $response = $this->get(parse_url($user->profile_photo_url, PHP_URL_PATH));
        $response->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));

        // still there after signing out and back in
        $this->actingAs($user)->get(parse_url($user->profile_photo_url, PHP_URL_PATH))->assertOk();
    }

    public function test_only_profile_pictures_are_served_from_that_address(): void
    {
        Storage::disk('public')->put('profile-photos/abc.jpg', 'x');
        Storage::disk('public')->put('other/secret.txt', 'x');

        $this->get('/storage/profile-photos/missing.jpg')->assertNotFound();
        $this->get('/storage/other/secret.txt')->assertNotFound();
        $this->get('/storage/profile-photos/..%2Fother%2Fsecret.txt')->assertNotFound();
        $this->get('/storage/profile-photos/abc.php')->assertNotFound();
    }

    public function test_jetstreams_old_profile_address_goes_to_the_account_page(): void
    {
        $this->actingAs(User::factory()->withPersonalTeam()->create())->get('/user/profile')->assertRedirect(route('profile.edit'));
    }
}
