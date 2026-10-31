<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Questions;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R-29 Phase 1: the business profile, its media (logo, cover, gallery) and who may shape it.
 * Replaced branding is kept on the record — that history is what impersonation review will
 * compare against in a later phase.
 */
class BusinessProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $editor;

    private User $outsider;

    private Team $acme;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->owner = User::factory()->withPersonalTeam()->create();
        $this->acme = Team::forceCreate(['user_id' => $this->owner->id, 'name' => 'Acme Boreholes', 'personal_team' => false]);
        $this->owner->switchTeam($this->acme);

        $this->admin = User::factory()->withPersonalTeam()->create();
        $this->acme->users()->attach($this->admin, ['role' => 'admin']);

        $this->editor = User::factory()->withPersonalTeam()->create();
        $this->acme->users()->attach($this->editor, ['role' => 'editor']);

        $this->outsider = User::factory()->withPersonalTeam()->create();
    }

    public function test_owner_and_admin_shape_the_profile_and_members_and_outsiders_do_not(): void
    {
        $fields = ['description' => 'We drill and equip boreholes.', 'industry' => 'Water services', 'website' => 'https://acme.example'];

        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/profile', $fields)->assertRedirect();
        $this->assertSame('Water services', $this->acme->profile()->first()->industry);

        $this->actingAs($this->admin)->post('/business/'.$this->acme->uuid.'/profile', ['description' => 'Updated by the administrator.'])->assertRedirect();
        $this->assertSame('Updated by the administrator.', $this->acme->profile()->first()->fresh()->description);

        $this->actingAs($this->editor)->post('/business/'.$this->acme->uuid.'/profile', $fields)->assertForbidden();
        $this->actingAs($this->outsider)->post('/business/'.$this->acme->uuid.'/profile', $fields)->assertForbidden();
        $this->actingAs($this->editor)->get('/business/'.$this->acme->uuid.'/edit')->assertForbidden();
    }

    public function test_the_public_page_shows_the_profile_to_any_signed_in_person(): void
    {
        $this->acme->profileOrNew()->fill([
            'description' => 'We drill and equip boreholes.',
            'industry' => 'Water services',
            'registration_number' => '2019/123456/07',
        ])->save();

        $this->actingAs($this->outsider)->get('/business/'.$this->acme->uuid)
            ->assertOk()
            ->assertSee('Acme Boreholes')
            ->assertSee('We drill and equip boreholes.')
            ->assertSee('2019/123456/07');
    }

    public function test_a_personal_workspace_has_no_business_page(): void
    {
        $personal = $this->owner->personalTeam();

        $this->actingAs($this->owner)->get('/business/'.$personal->uuid)->assertNotFound();
        $this->actingAs($this->owner)->get('/business/'.$personal->uuid.'/edit')->assertForbidden();
    }

    public function test_a_logo_replaces_and_the_old_one_stays_on_the_record(): void
    {
        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/media', [
            'role' => 'logo',
            'file' => UploadedFile::fake()->image('logo.png', 400, 400),
        ])->assertRedirect();

        $first = $this->acme->currentMedia('logo');
        $this->assertNotNull($first);
        $this->assertSame('logo', $first->role);
        Storage::disk('local')->assertExists($first->path);

        $this->actingAs($this->admin)->post('/business/'.$this->acme->uuid.'/media', [
            'role' => 'logo',
            'file' => UploadedFile::fake()->image('logo2.jpg', 600, 600),
            'crop' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.8, 'h' => 0.8],
        ])->assertRedirect();

        $second = $this->acme->currentMedia('logo');
        $this->assertNotSame($first->id, $second->id);

        // The replaced logo is history, not gone: its row and file both remain.
        $this->assertNotNull($first->fresh()->superseded_at);
        Storage::disk('local')->assertExists($first->path);
        $this->assertSame(2, $this->acme->media()->where('role', 'logo')->count());
    }

    public function test_current_branding_is_visible_to_everyone_and_history_is_not(): void
    {
        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/media', [
            'role' => 'cover', 'file' => UploadedFile::fake()->image('yard.jpg', 1600, 900),
        ])->assertRedirect();
        $old = $this->acme->currentMedia('cover');

        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/media', [
            'role' => 'cover', 'file' => UploadedFile::fake()->image('yard2.jpg', 1600, 900),
        ])->assertRedirect();
        $current = $this->acme->currentMedia('cover');

        $this->actingAs($this->outsider)->get('/attachments/'.$current->uuid)->assertOk();
        $this->actingAs($this->outsider)->get('/attachments/'.$old->uuid)->assertNotFound();
        $this->actingAs($this->owner)->get('/attachments/'.$old->uuid)->assertOk();
    }

    public function test_only_images_and_sane_crops_are_accepted(): void
    {
        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/media', [
            'role' => 'logo',
            'file' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/media', [
            'role' => 'logo',
            'file' => UploadedFile::fake()->image('tiny.png', 60, 60),
        ])->assertSessionHasErrors('file');

        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/media', [
            'role' => 'logo',
            'file' => UploadedFile::fake()->image('logo.png', 400, 400),
            'crop' => ['x' => 0.9, 'y' => 0.9, 'w' => 0.9, 'h' => 0.9],
        ])->assertSessionHasErrors();

        $this->assertSame(0, Attachment::count());
    }

    public function test_gallery_images_carry_captions_and_come_down_without_being_deleted(): void
    {
        $this->actingAs($this->admin)->post('/business/'.$this->acme->uuid.'/media', [
            'role' => 'gallery',
            'file' => UploadedFile::fake()->image('rig.jpg', 1200, 900),
            'caption' => 'Rig 2 on site in Lichtenburg',
        ])->assertRedirect();

        $shot = $this->acme->gallery()->first();
        $this->assertSame('Rig 2 on site in Lichtenburg', $shot->caption);

        $this->actingAs($this->outsider)->get('/business/'.$this->acme->uuid)
            ->assertOk()->assertSee('Rig 2 on site in Lichtenburg');

        // A member without the profile role cannot take it down; an administrator can.
        $this->actingAs($this->editor)->delete('/business/'.$this->acme->uuid.'/media/'.$shot->uuid)->assertForbidden();
        $this->actingAs($this->admin)->delete('/business/'.$this->acme->uuid.'/media/'.$shot->uuid)->assertRedirect();

        $this->assertTrue($this->acme->gallery()->isEmpty());
        $this->assertNotNull($shot->fresh()->superseded_at);
        Storage::disk('local')->assertExists($shot->path);
    }

    public function test_outsiders_see_only_public_activity(): void
    {
        Questions::withoutSyncingToSearch(fn () => Questions::create([
            'user_id' => $this->owner->id, 'team_id' => $this->acme->id, 'visibility' => 'public',
            'question' => 'Which pump suits a 90 m borehole?', 'description' => 'x', 'status' => 0,
        ]));
        Questions::withoutSyncingToSearch(fn () => Questions::create([
            'user_id' => $this->owner->id, 'team_id' => $this->acme->id, 'visibility' => 'company',
            'question' => 'Why did the Mahikeng job overrun?', 'description' => 'x', 'status' => 0,
        ]));

        $this->actingAs($this->outsider)->get('/business/'.$this->acme->uuid)
            ->assertOk()
            ->assertSee('Which pump suits a 90 m borehole?')
            ->assertDontSee('Why did the Mahikeng job overrun?');

        $this->actingAs($this->owner)->get('/business/'.$this->acme->uuid)
            ->assertOk()
            ->assertSee('Why did the Mahikeng job overrun?');
    }
}
