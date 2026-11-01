<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\BusinessAnnouncement;
use App\Models\BusinessProfileEvent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R-33 Phase 2: what a business shows on its page — offerings with pictures and published prices,
 * a portfolio, locations, announcements, brochures — and which contact details are public. Only
 * the business's owner and administrators manage it; every change is on the audit trail.
 */
class BusinessShowcaseTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $editor;

    private Team $acme;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->owner = User::factory()->withPersonalTeam()->create();
        $this->editor = User::factory()->withPersonalTeam()->create();
        $this->acme = $this->business('Acme Boreholes', 'public', $this->owner);
        $this->acme->users()->attach($this->editor, ['role' => 'editor']);
    }

    private function business(string $name, string $visibility, ?User $owner = null): Team
    {
        $owner ??= User::factory()->withPersonalTeam()->create();
        $team = Team::forceCreate(['user_id' => $owner->id, 'name' => $name, 'personal_team' => false]);
        $team->profileOrNew()->forceFill([
            'visibility' => $visibility,
            'description' => 'We drill and equip boreholes.',
            'location' => 'Nelspruit, Mpumalanga',
            'contact_phone' => '+27 13 555 0100',
            'contact_email' => 'hello@acme.example',
        ])->save();

        return $team;
    }

    private function url(string $path = ''): string
    {
        return '/business/'.$this->acme->uuid.$path;
    }

    private function signOut(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_an_offering_shows_its_picture_and_the_price_the_business_wrote(): void
    {
        $this->actingAs($this->owner)->post($this->url('/services'), [
            'name' => 'Solar borehole pump', 'kind' => 'product', 'description' => 'Pumps up to 5 000 litres a day.',
            'price_note' => 'From R18 500 installed', 'image' => UploadedFile::fake()->image('pump.jpg', 800, 600),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $service = $this->acme->services()->sole();
        $picture = Attachment::where('role', 'offering')->sole();
        $this->assertSame($service->id, $picture->meta['ref']);

        $this->signOut();
        $this->get($this->url())->assertOk()
            ->assertSee('Products')
            ->assertSee('Solar borehole pump')
            ->assertSee('From R18 500 installed')
            ->assertSee('/attachments/'.$picture->uuid, false);
        $this->get('/attachments/'.$picture->uuid)->assertOk();

        // A new picture replaces the old one; the old one stays on the record but is no longer public.
        $this->actingAs($this->owner)->post($this->url('/services/'.$service->id), ['name' => 'Solar borehole pump', 'kind' => 'product', 'image' => UploadedFile::fake()->image('pump2.jpg', 800, 600)])->assertRedirect();
        $this->assertNotNull($picture->fresh()->superseded_at);
        $this->signOut();
        $this->get('/attachments/'.$picture->uuid)->assertRedirect('/login');
    }

    public function test_only_the_businesss_managers_change_its_items(): void
    {
        $this->actingAs($this->owner)->post($this->url('/services'), ['name' => 'Borehole drilling'])->assertRedirect();
        $service = $this->acme->services()->sole();

        $this->actingAs($this->editor)->post($this->url('/services'), ['name' => 'Something'])->assertForbidden();
        $this->actingAs($this->editor)->post($this->url('/projects'), ['title' => 'Something'])->assertForbidden();
        $this->actingAs($this->editor)->post($this->url('/locations'), ['label' => 'HQ', 'city' => 'Durban'])->assertForbidden();
        $this->actingAs($this->editor)->post($this->url('/announcements'), ['title' => 'Hello', 'body' => 'Something to say.'])->assertForbidden();

        // Business B's owner cannot reach Business A's items through B's address.
        $other = $this->business('Other Co', 'public', $otherOwner = User::factory()->withPersonalTeam()->create());
        $this->actingAs($otherOwner)->post('/business/'.$other->uuid.'/services/'.$service->id, ['name' => 'Hijacked'])->assertNotFound();
        $this->actingAs($otherOwner)->delete('/business/'.$other->uuid.'/services/'.$service->id)->assertNotFound();
        $this->assertSame('Borehole drilling', $service->fresh()->name);
    }

    public function test_a_portfolio_project_carries_up_to_six_pictures_and_removing_it_retires_them(): void
    {
        $this->actingAs($this->owner)->post($this->url('/projects'), [
            'title' => 'Borehole for a citrus farm', 'summary' => '180 m, solar pump, 40 000 l tank.', 'year' => 2025, 'location' => 'Hazyview',
            'image' => UploadedFile::fake()->image('site.jpg', 1200, 900), 'caption' => 'The rig on site',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $project = $this->acme->projects()->sole();

        for ($i = 0; $i < 5; $i++) {
            $this->post($this->url('/projects/'.$project->id), ['title' => $project->title, 'image' => UploadedFile::fake()->image("p$i.jpg", 800, 600)])->assertSessionHasNoErrors();
        }
        $this->post($this->url('/projects/'.$project->id), ['title' => $project->title, 'image' => UploadedFile::fake()->image('p7.jpg', 800, 600)])->assertSessionHasErrors('limit');
        $this->assertSame(6, Attachment::where('role', 'project')->whereNull('superseded_at')->count());

        $this->get($this->url())->assertSee('Portfolio')->assertSee('Borehole for a citrus farm')->assertSee('The rig on site')->assertSee('Hazyview');

        $this->delete($this->url('/projects/'.$project->id))->assertRedirect();
        $this->assertModelMissing($project);
        $this->assertSame(0, Attachment::where('role', 'project')->whereNull('superseded_at')->count());
        $this->assertSame(6, Attachment::where('role', 'project')->count(), 'pictures stay on the record');
    }

    public function test_a_street_address_shows_only_when_the_business_publishes_it(): void
    {
        $this->actingAs($this->owner)->post($this->url('/locations'), [
            'label' => 'Workshop', 'address' => '14 Industrial Road', 'city' => 'Mbombela', 'province' => 'Mpumalanga', 'show_address' => 0,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $location = $this->acme->locations()->sole();

        $this->signOut();
        $this->get($this->url())->assertSee('Workshop')->assertSee('Mbombela, Mpumalanga')->assertDontSee('14 Industrial Road');
        // Searching by the location's town finds the business.
        $this->get('/businesses?location=Mbombela')->assertSee('Acme Boreholes');

        $this->actingAs($this->owner)->post($this->url('/locations/'.$location->id), [
            'label' => 'Workshop', 'address' => '14 Industrial Road', 'city' => 'Mbombela', 'province' => 'Mpumalanga', 'show_address' => 1,
        ])->assertRedirect();
        $this->signOut();
        $this->get($this->url())->assertSee('14 Industrial Road')->assertSee('Directions')
            ->assertSee(rawurlencode('14 Industrial Road, Mbombela, Mpumalanga'), false);
    }

    public function test_announcements_show_until_withdrawn_or_ended(): void
    {
        $this->actingAs($this->owner)->post($this->url('/announcements'), ['title' => 'New branch in Witbank', 'body' => 'Our second rig is now based in eMalahleni.'])->assertRedirect();
        $announcement = BusinessAnnouncement::sole();
        $this->get($this->url())->assertSee('New branch in Witbank');

        $this->acme->announcements()->create(['title' => 'Old news', 'body' => 'This already ended.', 'published_at' => now()->subMonth(), 'ends_at' => now()->subDay()]);
        $this->get($this->url())->assertDontSee('Old news');

        $this->post($this->url('/announcements/'.$announcement->id.'/withdraw'))->assertRedirect();
        $this->get($this->url())->assertDontSee('New branch in Witbank');
        $this->assertModelExists($announcement);
    }

    public function test_brochures_must_be_pdfs_and_follow_the_pages_visibility(): void
    {
        $pdf = UploadedFile::fake()->createWithContent('profile.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $this->actingAs($this->owner)->post($this->url('/brochures'), ['title' => 'Company profile 2026', 'file' => $pdf])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post($this->url('/brochures'), ['title' => 'Not a PDF', 'file' => UploadedFile::fake()->image('x.jpg')])->assertSessionHasErrors('file');
        $brochure = Attachment::where('role', 'brochure')->sole();

        $this->signOut();
        $this->get($this->url())->assertSee('Company profile 2026');
        $this->get('/attachments/'.$brochure->uuid)->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->acme->profile->forceFill(['visibility' => 'members'])->save();
        $this->get('/attachments/'.$brochure->uuid)->assertRedirect('/login');
    }

    public function test_contact_details_the_business_keeps_off_the_page_never_show(): void
    {
        $this->actingAs($this->owner)->post($this->url('/profile'), [
            'description' => 'We drill and equip boreholes.', 'contact_phone' => '+27 13 555 0100', 'contact_email' => 'hello@acme.example',
            'public_contact' => ['phone' => 0, 'email' => 1, 'website' => 1, 'links' => 1],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($this->acme->profile()->first()->shows('phone'));

        $this->get($this->url())->assertSee('Kept off the page by your choice: business phone');

        $this->signOut();
        $this->get($this->url())->assertOk()
            ->assertDontSee('555 0100')
            ->assertDontSee('tel:', false)
            ->assertSee('mailto:hello@acme.example', false);
    }

    public function test_managers_preview_the_page_as_a_visitor_and_others_cannot_switch_modes(): void
    {
        $this->actingAs($this->owner)->get($this->url().'?as=visitor')->assertOk()
            ->assertSee('Preview: this is the page as someone outside Acme Boreholes sees it.')
            ->assertSee('Anyone can open it')
            ->assertDontSee('Edit profile')
            ->assertSee('noindex');

        $this->actingAs($this->editor)->get($this->url().'?as=visitor')->assertOk()->assertDontSee('Preview: this is the page');
    }

    public function test_every_change_is_on_the_audit_trail_with_who_made_it(): void
    {
        $this->actingAs($this->owner)->post($this->url('/services'), ['name' => 'Borehole drilling'])->assertRedirect();
        $this->post($this->url('/directory'), ['visibility' => 'members'])->assertRedirect();
        $this->post($this->url('/announcements'), ['title' => 'Holiday hours', 'body' => 'Closed from 24 December to 2 January.'])->assertRedirect();

        $this->assertSame(['announcement_published', 'listing_changed', 'service_added'], BusinessProfileEvent::orderByDesc('id')->pluck('action')->all());
        $this->assertSame([$this->owner->id], BusinessProfileEvent::distinct()->pluck('user_id')->all());
        $this->assertSame('public → members', BusinessProfileEvent::where('action', 'listing_changed')->value('detail'));

        $this->get($this->url('/edit'))->assertSee('Recent changes')->assertSee('added an offering: Borehole drilling');
        $this->actingAs($this->editor)->get($this->url('/edit'))->assertForbidden();
    }
}
