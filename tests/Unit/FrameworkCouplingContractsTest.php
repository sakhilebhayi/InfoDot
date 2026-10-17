<?php

namespace Tests\Unit;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\CreateTeam;
use App\Actions\Jetstream\DeleteTeam;
use App\Actions\Jetstream\DeleteUser;
use App\Actions\Jetstream\InviteTeamMember;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Actions\Jetstream\UpdateTeamName;
use App\Models\Membership;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\Contracts\AddsTeamMembers;
use Laravel\Jetstream\Contracts\CreatesTeams;
use Laravel\Jetstream\Contracts\DeletesTeams;
use Laravel\Jetstream\Contracts\DeletesUsers;
use Laravel\Jetstream\Contracts\InvitesTeamMembers;
use Laravel\Jetstream\Contracts\RemovesTeamMembers;
use Laravel\Jetstream\Contracts\UpdatesTeamNames;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Membership as JetstreamMembership;
use Laravel\Jetstream\Team as JetstreamTeam;
use Laravel\Jetstream\TeamInvitation as JetstreamTeamInvitation;
use Laravel\Sanctum\HasApiTokens;
use Tests\TestCase;

class FrameworkCouplingContractsTest extends TestCase
{
    public function test_fortify_contract_bindings_resolve_to_app_actions(): void
    {
        $this->assertInstanceOf(CreateNewUser::class, $this->app->make(CreatesNewUsers::class));
        $this->assertInstanceOf(UpdateUserProfileInformation::class, $this->app->make(UpdatesUserProfileInformation::class));
        $this->assertInstanceOf(UpdateUserPassword::class, $this->app->make(UpdatesUserPasswords::class));
        $this->assertInstanceOf(ResetUserPassword::class, $this->app->make(ResetsUserPasswords::class));
    }

    public function test_fortify_rate_limiters_remain_registered_under_expected_names(): void
    {
        $this->assertNotNull(RateLimiter::limiter('login'));
        $this->assertNotNull(RateLimiter::limiter('two-factor'));
    }

    public function test_jetstream_contract_bindings_resolve_to_app_actions(): void
    {
        $this->assertInstanceOf(CreateTeam::class, $this->app->make(CreatesTeams::class));
        $this->assertInstanceOf(UpdateTeamName::class, $this->app->make(UpdatesTeamNames::class));
        $this->assertInstanceOf(AddTeamMember::class, $this->app->make(AddsTeamMembers::class));
        $this->assertInstanceOf(InviteTeamMember::class, $this->app->make(InvitesTeamMembers::class));
        $this->assertInstanceOf(RemoveTeamMember::class, $this->app->make(RemovesTeamMembers::class));
        $this->assertInstanceOf(DeleteTeam::class, $this->app->make(DeletesTeams::class));
        $this->assertInstanceOf(DeleteUser::class, $this->app->make(DeletesUsers::class));
    }

    public function test_jetstream_roles_and_permissions_remain_registered(): void
    {
        $adminRole = Jetstream::findRole('admin');
        $editorRole = Jetstream::findRole('editor');

        $this->assertNotNull($adminRole);
        $this->assertNotNull($editorRole);
        $this->assertSame(['read'], Jetstream::$defaultPermissions);
        $this->assertSame(['create', 'read', 'update', 'delete'], $adminRole->permissions);
        $this->assertSame(['read', 'create', 'update'], $editorRole->permissions);
    }

    public function test_route_service_provider_home_constant_is_the_home_page(): void
    {
        $this->assertSame('/home', RouteServiceProvider::HOME);
    }

    public function test_user_model_keeps_framework_auth_traits(): void
    {
        $traits = class_uses_recursive(User::class);

        $this->assertContains(HasApiTokens::class, $traits);
        $this->assertContains(HasTeams::class, $traits);
        $this->assertContains(HasProfilePhoto::class, $traits);
        $this->assertContains(Notifiable::class, $traits);
        $this->assertContains(TwoFactorAuthenticatable::class, $traits);
    }

    public function test_team_related_models_keep_jetstream_base_classes(): void
    {
        $this->assertTrue(is_subclass_of(Team::class, JetstreamTeam::class));
        $this->assertTrue(is_subclass_of(Membership::class, JetstreamMembership::class));
        $this->assertTrue(is_subclass_of(TeamInvitation::class, JetstreamTeamInvitation::class));
    }
}
