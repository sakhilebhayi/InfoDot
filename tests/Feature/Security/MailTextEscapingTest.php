<?php

namespace Tests\Feature\Security;

use App\Mail\EnquiryActivity;
use App\Mail\LicenceNotice;
use App\Mail\VerificationEmailCode;
use App\Models\Enquiry;
use App\Models\Team;
use App\Models\User;
use App\Models\UserNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Mail\TeamInvitation as TeamInvitationMail;
use Tests\TestCase;

/**
 * R-72 (E-05, E-11): names and subjects that people type reach mail sent from InfoDot's own domain, to addresses they choose. They must
 * appear as plain words: never a link, an image or Markdown, so InfoDot's mail cannot be used to deliver someone else's phishing link.
 */
class MailTextEscapingTest extends TestCase
{
    use RefreshDatabase;

    private const HOSTILE = '[Claim your prize](https://evil.example/steal) ![x](https://evil.example/p.png) <a href="https://evil.example">here</a>';

    private function assertNoHostileLink(string $html): void
    {
        // The address may remain as broken text, but never as something a mail app would make clickable.
        $this->assertStringNotContainsString('https://evil.example', $html);
        $this->assertStringNotContainsString('href="https://evil', $html);
        $this->assertStringNotContainsString('<img src="https://evil.example', $html);
        $this->assertStringContainsString('Claim your prize', $html, 'the words themselves still show');
    }

    private function team(): Team
    {
        $owner = User::factory()->create();

        return Team::forceCreate(['user_id' => $owner->id, 'name' => self::HOSTILE, 'personal_team' => false]);
    }

    public function test_a_team_invitation_shows_the_company_name_as_plain_words(): void
    {
        $team = $this->team();
        $invitation = $team->teamInvitations()->create(['email' => 'guest@example.com', 'role' => 'editor']);

        $html = (new TeamInvitationMail($invitation))->render();

        $this->assertNoHostileLink($html);
        $this->assertMatchesRegularExpression('/Accept the invitation.*Create one with this email address/s', $html, 'accepting comes first');
    }

    public function test_enquiry_mail_shows_names_and_subject_as_plain_words(): void
    {
        $team = $this->team();
        $customer = User::factory()->create(['name' => self::HOSTILE]);
        $enquiry = new Enquiry(['subject' => self::HOSTILE, 'category' => 'general']);
        $enquiry->team_id = $team->id;
        $enquiry->user_id = $customer->id;
        $enquiry->status = Enquiry::NEW;
        $enquiry->save();

        $html = (new EnquiryActivity(UserNotice::ENQUIRY_RECEIVED, $enquiry, $team->owner, false))->render();

        $this->assertNoHostileLink($html);
    }

    public function test_licence_mail_shows_the_company_name_as_plain_words(): void
    {
        $team = $this->team();

        $mail = new LicenceNotice('Your licence renews soon', ['A line of the reminder.'], $team, $team->owner);

        $this->assertNoHostileLink($mail->render());
        $this->assertStringNotContainsString('https://evil.example', $mail->envelope()->subject);
    }

    public function test_the_verification_code_is_not_in_the_subject_and_the_business_name_is_plain(): void
    {
        $mail = new VerificationEmailCode('482913', self::HOSTILE, 15);

        $this->assertStringNotContainsString('482913', $mail->envelope()->subject);
        $html = $mail->render();
        $this->assertStringContainsString('482913', $html);
        $this->assertNoHostileLink($html);
    }
}
