<?php

namespace Tests\Feature;

use App\Models\BusinessCategory;
use App\Models\BusinessCollection;
use App\Models\BusinessFeature;
use App\Models\BusinessService;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R-33 Phase 4b: collections, similar businesses, browse by place, featured placements under
 * published rules, and analytics from recorded events only.
 */
class BusinessDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 Version/17.0 Safari/605.1.15';

    private User $owner;

    private User $editor;

    private User $visitor;

    private User $reviewer;

    private Team $acme;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->owner = User::factory()->withPersonalTeam()->create();
        $this->editor = User::factory()->withPersonalTeam()->create();
        $this->visitor = User::factory()->withPersonalTeam()->create();
        $this->reviewer = User::factory()->withPersonalTeam()->create();
        $this->reviewer->forceFill(['is_moderator' => true])->save();

        $this->acme = $this->business('Acme Boreholes', 'public', ['location' => 'Nelspruit, Mpumalanga'], $this->owner);
        $this->acme->users()->attach($this->editor, ['role' => 'editor']);
        $this->acme->services()->save(new BusinessService(['name' => 'Borehole drilling']));
    }

    /** @param array<string, mixed> $profile */
    private function business(string $name, string $visibility, array $profile = [], ?User $owner = null): Team
    {
        $owner ??= User::factory()->withPersonalTeam()->create();
        $team = Team::forceCreate(['user_id' => $owner->id, 'name' => $name, 'personal_team' => false]);
        $team->profileOrNew()->forceFill(array_merge(['visibility' => $visibility, 'description' => 'A business called '.$name.'.'], $profile))->save();

        return $team;
    }

    private function asGuest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('User-Agent', self::BROWSER);
    }

    private function stat(Team $team, string $metric): int
    {
        return (int) DB::table('business_stats_daily')->where('team_id', $team->id)->where('metric', $metric)->sum('count');
    }

    public function test_people_keep_private_collections(): void
    {
        $this->actingAs($this->visitor)->post('/business/'.$this->acme->uuid.'/save', ['new_name' => 'Contractors', 'note' => 'Quoted R45 000 in March'])->assertRedirect();
        $this->actingAs($this->visitor)->post('/business/'.$this->acme->uuid.'/save', ['new_name' => 'Contractors'])->assertRedirect();
        $collection = BusinessCollection::sole();
        $this->assertSame(1, $collection->items()->count(), 'saving twice keeps one entry');

        $this->actingAs($this->visitor)->get('/collections/'.$collection->id)->assertOk()->assertSee('Acme Boreholes');
        $this->actingAs($this->owner)->get('/collections/'.$collection->id)->assertNotFound();
        $this->actingAs($this->owner)->delete('/collections/'.$collection->id)->assertNotFound();

        // A business that is no longer visible to the person stays in the list but is not named.
        $this->acme->profile->forceFill(['visibility' => 'private'])->save();
        $this->actingAs($this->visitor)->get('/collections/'.$collection->id)->assertOk()
            ->assertSee('no longer available to you')->assertDontSee('Acme Boreholes</a>', false);
        $this->actingAs($this->visitor)->post('/business/'.$this->acme->uuid.'/save', ['new_name' => 'Suppliers'])->assertNotFound();

        $this->actingAs($this->visitor)->delete('/collections/'.$collection->id)->assertRedirect();
        $this->assertModelMissing($collection);
    }

    public function test_similar_businesses_share_a_category_or_service_and_say_which(): void
    {
        $cat = BusinessCategory::orderBy('sort')->firstOrFail();
        $this->acme->categories()->attach($cat);
        $twin = $this->business('Lowveld Drilling', 'public');
        $twin->categories()->attach($cat);
        $driller = $this->business('Bushveld Boreholes', 'members');
        $driller->services()->save(new BusinessService(['name' => 'Borehole drilling and testing']));
        $hidden = $this->business('Hidden Drillers', 'private');
        $hidden->categories()->attach($cat);
        $this->business('Unrelated Bakery', 'public');

        $this->asGuest()->get('/business/'.$this->acme->uuid)->assertOk()
            ->assertSee('Similar businesses')->assertSee('Lowveld Drilling')->assertSee('Same category: '.$cat->name)
            ->assertDontSee('Bushveld Boreholes')->assertDontSee('Hidden Drillers')->assertDontSee('Unrelated Bakery');

        $this->actingAs($this->visitor)->get('/business/'.$this->acme->uuid)->assertSee('Bushveld Boreholes')->assertSee('Also offers: Borehole drilling and testing');
    }

    public function test_browse_by_place_uses_what_listed_businesses_gave(): void
    {
        $this->acme->locations()->create(['label' => 'Workshop', 'city' => 'Mbombela', 'province' => 'Mpumalanga']);
        $this->business('Secret Co', 'private', ['location' => 'Hiddenville, Nowhere']);

        $this->asGuest()->get('/businesses')->assertOk()
            ->assertSee('Browse by place')->assertSee('Mbombela')->assertSee('Nelspruit')->assertSee('Mpumalanga')
            ->assertDontSee('Hiddenville');
        $this->get('/businesses?location=Mbombela')->assertSee('Acme Boreholes');
    }

    public function test_featuring_follows_the_published_rules_and_is_labelled(): void
    {
        $this->asGuest()->get('/businesses/featured')->assertOk()->assertSee('not a rating, a recommendation, a review of quality, or a verification');

        // No logo yet: the rule is named and nothing is requested.
        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/feature', [])->assertSessionHasErrors('reason');
        $this->actingAs($this->owner)->get('/business/'.$this->acme->uuid.'/edit')->assertSee('Shows its logo');

        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/media', ['role' => 'logo', 'file' => UploadedFile::fake()->image('logo.png', 300, 300)])->assertRedirect();
        $this->actingAs($this->editor)->post('/business/'.$this->acme->uuid.'/feature', [])->assertForbidden();
        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/feature', ['reason' => 'New rig in Mpumalanga.'])->assertRedirect()->assertSessionHasNoErrors();
        $feature = BusinessFeature::sole();
        $this->assertSame(BusinessFeature::REQUESTED, $feature->status);
        $this->actingAs($this->reviewer)->get('/moderation/trust')->assertOk()->assertSee('New rig in Mpumalanga.');

        $insider = User::factory()->withPersonalTeam()->create();
        $insider->forceFill(['is_moderator' => true])->save();
        $this->acme->users()->attach($insider, ['role' => 'editor']);
        $this->actingAs($insider)->post('/moderation/trust/features/'.$feature->id.'/grant', ['days' => 14, 'note' => 'Granting my own.'])->assertForbidden();
        $this->actingAs($this->reviewer)->post('/moderation/trust/features/'.$feature->id.'/grant', ['days' => 90, 'note' => 'Meets every rule.'])->assertSessionHasErrors('days');
        $this->actingAs($this->reviewer)->post('/moderation/trust/features/'.$feature->id.'/grant', ['days' => 14, 'note' => 'Meets every rule.'])->assertRedirect();

        $this->asGuest()->get('/businesses')->assertSee('Featured')->assertSee('published rules')->assertSee('Acme Boreholes');

        // Ending it tells the business, in words that name it.
        $this->actingAs($this->reviewer)->post('/moderation/trust/features/'.$feature->id.'/end')->assertRedirect();
        $ended = UserNotice::where('user_id', $this->owner->id)->where('ref', 'like', '%:ended')->sole();
        $this->assertStringContainsString('“Acme Boreholes”’s featured placement has ended.', app(InAppNotices::class)->describe($ended)['text']);
        $this->actingAs($this->owner)->post('/business/'.$this->acme->uuid.'/feature', [])->assertRedirect();
        $this->actingAs($this->reviewer)->post('/moderation/trust/features/'.BusinessFeature::latest('id')->value('id').'/grant', ['days' => 7, 'note' => 'Meets every rule.'])->assertRedirect();

        // A business that stops meeting the rules stops showing, even mid-placement.
        $this->acme->profile->forceFill(['visibility' => 'members'])->save();
        $this->asGuest()->get('/businesses')->assertDontSee('published rules');
    }

    public function test_views_and_searches_are_counted_from_real_visitors_only(): void
    {
        $this->asGuest()->get('/business/'.$this->acme->uuid)->assertOk();
        $this->get('/business/'.$this->acme->uuid)->assertOk(); // same visitor, same day
        $this->assertSame(1, $this->stat($this->acme, 'view'));

        // The business's own people, reviewers and crawlers are not counted.
        $this->actingAs($this->editor)->get('/business/'.$this->acme->uuid);
        $this->actingAs($this->reviewer)->get('/business/'.$this->acme->uuid);
        $this->app['auth']->forgetGuards();
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/business/'.$this->acme->uuid);
        $this->assertSame(1, $this->stat($this->acme, 'view'));

        $this->asGuest()->get('/businesses?q='.urlencode('borehole drilling in Nelspruit'))->assertSee('Acme Boreholes');
        $this->assertSame(1, $this->stat($this->acme, 'search'));
        $this->assertSame(1, $this->stat($this->acme, 'service:'.$this->acme->services()->sole()->id));
        $this->assertDatabaseHas('business_search_terms_daily', ['team_id' => $this->acme->id, 'term' => 'borehole drilling', 'count' => 1]);

        $this->post('/business/'.$this->acme->uuid.'/track/call')->assertNoContent();
        $this->post('/business/'.$this->acme->uuid.'/track/call')->assertNoContent();
        $this->post('/business/'.$this->acme->uuid.'/track/website')->assertNoContent();
        $this->post('/business/'.$this->acme->uuid.'/track/hack')->assertNotFound();
        $this->assertSame(1, $this->stat($this->acme, 'call'));
        $this->assertSame(1, $this->stat($this->acme, 'website'));
    }

    public function test_the_insights_page_shows_recorded_numbers_and_hides_rare_searches(): void
    {
        $day = now()->toDateString();
        DB::table('business_stats_daily')->insert([
            ['team_id' => $this->acme->id, 'day' => $day, 'metric' => 'view', 'count' => 12],
            ['team_id' => $this->acme->id, 'day' => $day, 'metric' => 'search', 'count' => 30],
            ['team_id' => $this->acme->id, 'day' => $day, 'metric' => 'call', 'count' => 4],
        ]);
        DB::table('business_search_terms_daily')->insert([
            ['team_id' => $this->acme->id, 'day' => $day, 'term' => 'borehole drilling', 'count' => 9],
            ['team_id' => $this->acme->id, 'day' => $day, 'term' => 'rare personal words', 'count' => 1],
        ]);

        $this->actingAs($this->owner)->get('/business/'.$this->acme->uuid.'/insights')->assertOk()
            ->assertSee('Page views')->assertSee('12')->assertSee('borehole drilling')
            ->assertDontSee('rare personal words')->assertSee('Plus 1 appearances from searches seen fewer than 3 times.');
        $this->actingAs($this->editor)->get('/business/'.$this->acme->uuid.'/insights')->assertForbidden();
        $this->actingAs($this->visitor)->get('/business/'.$this->acme->uuid.'/insights')->assertForbidden();
    }
}
