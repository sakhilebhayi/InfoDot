<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\BusinessCategory;
use App\Models\BusinessService;
use App\Models\Team;
use App\Models\User;
use App\Services\Directory\BusinessDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R-33 Phase 1: the business directory. Who can find a business is the business's own choice
 * (public, InfoDot users, private); search is over what businesses published and says why each
 * result matched; contact actions use only the business's published details.
 */
class BusinessDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $editor;

    private User $outsider;

    private Team $acme;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->owner = User::factory()->withPersonalTeam()->create();
        $this->editor = User::factory()->withPersonalTeam()->create();
        $this->outsider = User::factory()->withPersonalTeam()->create();

        $this->acme = $this->business('Acme Boreholes', 'public', [
            'description' => 'We drill and equip boreholes for farms and towns.',
            'location' => 'Nelspruit, Mpumalanga',
            'contact_phone' => '+27 13 555 0100',
            'contact_email' => 'hello@acme.example',
            'business_type' => 'company',
        ], $this->owner);
        $this->acme->users()->attach($this->editor, ['role' => 'editor']);
    }

    /** @param array<string, mixed> $profile */
    private function business(string $name, string $visibility, array $profile = [], ?User $owner = null): Team
    {
        $owner ??= User::factory()->withPersonalTeam()->create();
        $team = Team::forceCreate(['user_id' => $owner->id, 'name' => $name, 'personal_team' => false]);
        $team->profileOrNew()->forceFill(array_merge(['visibility' => $visibility, 'description' => 'A business called '.$name.'.'], $profile))->save();

        return $team;
    }

    private function signOut(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_visitors_see_only_public_businesses_and_signed_in_people_also_see_members_only_ones(): void
    {
        $this->business('Members Only Mining', 'members');
        $this->business('Hidden Holdings', 'private');
        $this->business('Empty Listing', 'public', ['description' => null]);

        $this->get('/businesses?all=1')->assertOk()
            ->assertSee('Acme Boreholes')
            ->assertDontSee('Members Only Mining')
            ->assertDontSee('Hidden Holdings')
            ->assertDontSee('Empty Listing')
            ->assertSee('Sign in');

        $this->actingAs($this->outsider)->get('/businesses?all=1')->assertOk()
            ->assertSee('Acme Boreholes')
            ->assertSee('Members Only Mining')
            ->assertDontSee('Hidden Holdings')
            ->assertDontSee('Empty Listing');
    }

    public function test_a_natural_question_searches_services_and_location_and_says_why_it_matched(): void
    {
        $this->acme->services()->save(new BusinessService(['name' => 'Mining equipment supply', 'description' => 'Pumps and drill rods for small mines.']));
        $this->business('Cape Mining Supplies', 'public', ['location' => 'Cape Town, Western Cape', 'description' => 'Mining equipment supplier.']);
        $this->business('Lowveld Bakery', 'public', ['location' => 'Nelspruit, Mpumalanga', 'description' => 'Bread and cakes.']);

        $this->get('/businesses?q='.urlencode('Mining equipment suppliers in Mpumalanga.'))->assertOk()
            ->assertSee('Acme Boreholes')
            ->assertSee('Matched on')
            ->assertSee('service “Mining equipment supply”', false)
            ->assertSee('Mpumalanga')
            ->assertDontSee('Cape Mining Supplies')
            ->assertDontSee('Lowveld Bakery');
    }

    public function test_the_query_parser_reads_the_briefs_examples(): void
    {
        $d = app(BusinessDirectory::class);

        $this->assertSame([['mining', 'equipment', 'supplier'], 'Mpumalanga'], $d->parse('Mining equipment suppliers in Mpumalanga.'));
        $this->assertSame([['accounting'], 'Johannesburg'], $d->parse('accounting firms in Johannesburg'));
        $this->assertSame([['solar', 'installer'], 'Durban'], $d->parse('Solar installers near Durban'));
        $this->assertSame([['plumbing'], null], $d->parse('plumbing'));
    }

    public function test_filters_narrow_by_category_type_and_verification(): void
    {
        $water = BusinessCategory::orderBy('sort')->firstOrFail();
        $this->acme->categories()->attach($water);
        $this->business('Other Co', 'public', ['business_type' => 'sole_proprietor']);

        $this->get('/businesses?category='.$water->slug)->assertSee('Acme Boreholes')->assertDontSee('Other Co');
        $this->get('/businesses?type=sole_proprietor')->assertSee('Other Co')->assertDontSee('Acme Boreholes');
        // Nothing here is verified, so the verified filter honestly finds nothing.
        $this->get('/businesses?verified=1')->assertSee('No listed business matches that yet')->assertDontSee('Acme Boreholes');
    }

    public function test_the_business_page_answers_to_its_visibility(): void
    {
        $members = $this->business('Members Only Mining', 'members');
        $private = $this->business('Hidden Holdings', 'private', [], $insider = User::factory()->withPersonalTeam()->create());
        $moderator = User::factory()->withPersonalTeam()->create();
        $moderator->forceFill(['is_moderator' => true])->save();

        $this->get('/business/'.$this->acme->uuid)->assertOk()->assertSee('Acme Boreholes');
        $this->get('/business/'.$members->uuid)->assertNotFound();
        $this->get('/business/'.$private->uuid)->assertNotFound();

        $this->actingAs($this->outsider)->get('/business/'.$members->uuid)->assertOk();
        $this->actingAs($this->outsider)->get('/business/'.$private->uuid)->assertNotFound();
        $this->actingAs($insider)->get('/business/'.$private->uuid)->assertOk();
        $this->actingAs($moderator)->get('/business/'.$private->uuid)->assertOk();
    }

    public function test_contact_actions_use_only_what_the_business_published(): void
    {
        $this->get('/business/'.$this->acme->uuid)->assertOk()
            ->assertSee('href="tel:+27135550100"', false)
            ->assertSee('href="mailto:hello@acme.example"', false)
            ->assertSee('On the map')
            // The people behind the business are never exposed by the page.
            ->assertDontSee($this->owner->email)
            ->assertDontSee($this->editor->email);
    }

    public function test_public_branding_opens_for_visitors_and_other_files_still_ask_them_to_sign_in(): void
    {
        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/media', ['role' => 'logo', 'file' => UploadedFile::fake()->image('logo.png', 300, 300)])->assertRedirect();
        $logo = Attachment::where('role', 'logo')->sole();
        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/media', ['role' => 'gallery', 'file' => UploadedFile::fake()->image('yard.jpg', 800, 600)])->assertRedirect();
        $members = $this->business('Members Only Mining', 'members', [], $other = User::factory()->withPersonalTeam()->create());
        $this->actingAs($other)->post('/business/'.$members->uuid.'/media', ['role' => 'logo', 'file' => UploadedFile::fake()->image('m.png', 300, 300)])->assertRedirect();
        $membersLogo = Attachment::where('role', 'logo')->whereKeyNot($logo->id)->sole();

        $this->signOut();
        $this->get('/attachments/'.$logo->uuid)->assertOk();
        // The listing carries the current logo, not the newest file of any kind.
        $this->get('/businesses?all=1')->assertSee('/attachments/'.$logo->uuid, false);
        $this->get('/attachments/'.$membersLogo->uuid)->assertRedirect('/login');

        // A replaced logo is history: not for visitors.
        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/media', ['role' => 'logo', 'file' => UploadedFile::fake()->image('new.png', 300, 300)])->assertRedirect();
        $this->signOut();
        $this->get('/attachments/'.$logo->uuid)->assertRedirect('/login');
    }

    public function test_owners_choose_the_listing_and_editors_cannot(): void
    {
        $ids = BusinessCategory::orderBy('sort')->limit(4)->pluck('id')->all();

        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/directory', ['visibility' => 'members', 'business_type' => 'cooperative', 'categories' => array_slice($ids, 0, 2)])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('members', $this->acme->profile()->first()->visibility);
        $this->assertSame('cooperative', $this->acme->profile()->first()->business_type);
        $this->assertCount(2, $this->acme->categories()->get());

        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/directory', ['visibility' => 'public', 'categories' => $ids])->assertSessionHasErrors('categories');
        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/directory', ['visibility' => 'everyone'])->assertSessionHasErrors('visibility');
        $this->actingAs($this->editor)->post('/business/'.$this->acme->uuid.'/directory', ['visibility' => 'public'])->assertForbidden();
        $this->actingAs($this->outsider)->post('/business/'.$this->acme->uuid.'/directory', ['visibility' => 'private'])->assertForbidden();
    }

    public function test_services_are_added_and_removed_only_by_the_business(): void
    {
        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/services', ['name' => 'Borehole drilling', 'description' => 'Up to 200 m.'])->assertRedirect();
        $service = $this->acme->services()->sole();
        $this->assertSame('Borehole drilling', $service->name);

        $this->actingAs($this->editor)->post('/business/'.$this->acme->uuid.'/services', ['name' => 'Something'])->assertForbidden();

        $other = $this->business('Other Co', 'public', [], $otherOwner = User::factory()->withPersonalTeam()->create());
        $this->actingAs($otherOwner)->delete('/business/'.$other->uuid.'/services/'.$service->id)->assertNotFound();
        $this->assertModelExists($service);

        $this->get('/business/'.$this->acme->uuid)->assertSee('Borehole drilling');
        $this->actingAs($this->owner)->delete('/business/'.$this->acme->uuid.'/services/'.$service->id)->assertRedirect();
        $this->assertModelMissing($service);
    }

    public function test_the_sitemap_lists_public_businesses_only(): void
    {
        $members = $this->business('Members Only Mining', 'members');
        Cache::forget('sitemap.xml');

        $this->get('/sitemap.xml')->assertOk()
            ->assertSee(route('businesses.index'), false)
            ->assertSee(route('business.show', $this->acme), false)
            ->assertDontSee(route('business.show', $members), false);
    }

    public function test_only_public_business_pages_may_be_indexed(): void
    {
        $members = $this->business('Members Only Mining', 'members');

        $this->get('/business/'.$this->acme->uuid)->assertDontSee('noindex');
        $this->actingAs($this->outsider)->get('/business/'.$members->uuid)->assertSee('<meta name="robots" content="noindex">', false);
    }
}
