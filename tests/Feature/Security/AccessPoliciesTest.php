<?php

namespace Tests\Feature\Security;

use App\Models\Enquiry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** R-72 (Q-21): the enquiry policy says who reads and handles an enquiry, and the routes follow it. */
class AccessPoliciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_enquiry_policy_follows_the_people_involved(): void
    {
        Storage::fake('local');
        Mail::fake();
        $owner = User::factory()->withPersonalTeam()->create(['email_verified_at' => now()]);
        $admin = User::factory()->withPersonalTeam()->create(['email_verified_at' => now()]);
        $editor = User::factory()->withPersonalTeam()->create(['email_verified_at' => now()]);
        $customer = User::factory()->withPersonalTeam()->create(['email_verified_at' => now()]);
        $stranger = User::factory()->withPersonalTeam()->create(['email_verified_at' => now()]);
        $acme = Team::forceCreate(['user_id' => $owner->id, 'name' => 'Acme Boreholes', 'personal_team' => false]);
        $acme->profileOrNew()->forceFill(['visibility' => 'public', 'description' => 'We drill boreholes.'])->save();
        $acme->users()->attach($admin, ['role' => 'admin']);
        $acme->users()->attach($editor, ['role' => 'editor']);

        $this->actingAs($customer)->post('/business/'.$acme->uuid.'/message', ['subject' => 'Borehole quote', 'category' => 'quote', 'body' => 'We need a borehole drilled on a smallholding near Nelspruit, about 80 metres.'])->assertRedirect();
        $enquiry = Enquiry::latest('id')->firstOrFail();

        $this->assertTrue($customer->can('view', $enquiry) && $customer->can('replyAsCustomer', $enquiry));
        $this->assertFalse($customer->can('handle', $enquiry));
        foreach ([$owner, $admin] as $handler) {
            $this->assertTrue($handler->can('view', $enquiry) && $handler->can('handle', $enquiry));
            $this->assertFalse($handler->can('replyAsCustomer', $enquiry));
        }
        $this->assertFalse($editor->can('handle', $enquiry), 'a member it is not assigned to does not handle it');
        $this->assertFalse($stranger->can('view', $enquiry));

        // Assigned: the member handles it while they are in the business, and not after they leave.
        $enquiry->forceFill(['assigned_to' => $editor->id])->save();
        $this->assertTrue($editor->fresh()->can('handle', $enquiry->fresh()));
        $acme->users()->detach($editor);
        $this->assertFalse($editor->fresh()->can('handle', $enquiry->fresh()));

        // The routes answer from the policy, with 404 for anyone else.
        $this->actingAs($stranger)->get('/inbox/'.$enquiry->uuid)->assertNotFound();
        $this->actingAs($owner)->get('/inbox/'.$enquiry->uuid)->assertRedirect(route('business.inbox.show', [$acme, $enquiry]));
        $this->actingAs($customer)->get('/inbox/'.$enquiry->uuid)->assertOk();
        $this->actingAs($owner)->post('/inbox/'.$enquiry->uuid.'/reply', ['body' => 'Not the customer.'])->assertNotFound();
    }
}
