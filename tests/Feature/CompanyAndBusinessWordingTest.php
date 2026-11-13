<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Jetstream\Mail\TeamInvitation;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * R-49. One rule for three words. "Company" is the workspace a person belongs to and manages (settings, people, seats,
 * the switcher). "Business" is its public face (business page, directory, enquiries). "Team" is never shown: it is the
 * framework's name for the same thing. The one exception is the stored name of a personal workspace ("Nomvula's Team"),
 * which is data, not copy.
 */
class CompanyAndBusinessWordingTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->withPersonalTeam()->create(['name' => 'Olive Owner', 'email_verified_at' => Carbon::now()]);
        $company = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false, 'name' => 'Ledger Works']);
        $company->users()->attach(User::factory()->create(['name' => 'Mel Member']), ['role' => 'member']);
        $owner->forceFill(['current_team_id' => $company->id])->save();

        return $owner->fresh();
    }

    /** What a person reads on a page: tags, scripts, styles and attributes that are not shown are dropped. */
    private function visible(string $html): string
    {
        $html = (string) preg_replace('#<(script|style|template)\b.*?</\1>#si', ' ', $html);
        preg_match_all('/\b(?:aria-label|title|placeholder|alt|value)="([^"]*)"/', $html, $attributes);

        return html_entity_decode(strip_tags($html).' '.implode(' ', $attributes[1]));
    }

    public function test_the_workspace_screens_say_company_and_never_team(): void
    {
        $owner = $this->owner();
        $company = $owner->currentTeam;

        $pages = [
            'company settings' => $this->actingAs($owner)->get('/teams/'.$company->id)->assertOk()->assertSee('Company settings')->assertSee('Company name')->assertSee('People in the company')->assertSee('Delete company'),
            'create a company' => $this->actingAs($owner)->get('/teams/create')->assertOk()->assertSee('Create a company')->assertSee('Company name')->assertSee('Who can see the business page'),
            'people' => $this->actingAs($owner)->get('/people')->assertOk(),
            'home' => $this->actingAs($owner)->get('/home')->assertOk()->assertSee('Company settings')->assertSee('Create a company'),
            'questions' => $this->actingAs($owner)->get('/questions')->assertOk(),
            'solutions' => $this->actingAs($owner)->get('/solutions')->assertOk(),
            'business editor' => $this->actingAs($owner)->get('/business/'.$company->uuid.'/edit')->assertOk()->assertSee('People and roles'),
        ];

        foreach ($pages as $name => $page) {
            // Names of people and companies are data; the personal workspace is called "Olive's Team" by the framework.
            $text = str_replace(["Olive's Team", "Olive Owner's Team"], '', $this->visible((string) $page->getContent()));
            $this->assertSame(0, preg_match('/\bteams?\b/i', $text, $m, PREG_OFFSET_CAPTURE), $name.' shows the word "team": …'.substr($text, max(0, ($m[0][1] ?? 0) - 80), 160).'…');
        }
    }

    public function test_the_invitation_email_says_company(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $invitation = $owner->currentTeam->teamInvitations()->create(['email' => 'new@example.test', 'role' => 'member']);

        $mail = new TeamInvitation($invitation);
        $text = $this->visible($mail->render());

        $this->assertStringContainsString('You have been invited to join Ledger Works on InfoDot.', $text);
        $this->assertStringContainsString('can be accepted for 14 days', $text);
        $this->assertSame(0, preg_match('/\bteam\b/i', $text));
        $mail->assertHasSubject('Invitation to join a company on InfoDot');
    }

    public function test_no_view_writes_team_in_a_translated_string(): void
    {
        foreach ((new Finder)->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
            preg_match_all("/__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $file->getContents(), $m);

            foreach ($m[1] as $string) {
                $string = (string) preg_replace('/:team\b/', '', $string);   // a placeholder for the company's name
                $this->assertSame(0, preg_match('/\bteams?\b/i', $string), $file->getRelativePathname().' says "team" to the reader: '.$string);
            }
        }
    }
}
