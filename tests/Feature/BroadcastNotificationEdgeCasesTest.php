<?php

namespace Tests\Feature;

use App\Http\Controllers\Notifications\NotificationController;
use App\Models\User;
use App\Notifications\DatabaseNotificationChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Mockery;
use Tests\TestCase;

class DummyDatabaseNotification extends Notification
{
    public function toArray($notifiable)
    {
        return [
            'message' => 'edge-case',
            'id' => 42,
        ];
    }
}

class BroadcastNotificationEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_authorize_broadcast_channels(): void
    {
        $response = $this->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-App.Models.User.1',
        ]);

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_authorize_own_private_user_channel(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-App.Models.User.'.$user->id,
        ]);

        $response->assertOk();
        $response->assertSee('auth');
    }

    public function test_authenticated_user_cannot_authorize_other_users_private_channel(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user)->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-App.Models.User.'.$other->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_the_question_channel_authorises_nobody_until_it_is_scoped_to_a_company(): void
    {
        // It used to say yes to every signed-in person. The event is not dispatched today; if it ever is, a private question must not go out
        // to everyone, so the channel refuses until it checks the company and the item's visibility.
        $user = User::factory()->create();

        $this->actingAs($user)->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-question',
        ])->assertStatus(403);
    }

    public function test_database_notification_channel_stores_standard_payload_shape(): void
    {
        $notifiable = Mockery::mock();
        $databaseRoute = Mockery::mock();
        $notification = new DummyDatabaseNotification;
        $notification->id = 'notif-edge-001';

        $notifiable->shouldReceive('routeNotificationFor')
            ->once()
            ->with('database')
            ->andReturn($databaseRoute);

        $databaseRoute->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $payload) {
                return $payload['id'] === 'notif-edge-001'
                    && $payload['type'] === 'DummyDatabaseNotification'
                    && $payload['data']['message'] === 'edge-case'
                    && $payload['read_at'] === null;
            }))
            ->andReturnTrue();

        $channel = new DatabaseNotificationChannel;

        $this->assertTrue((bool) $channel->send($notifiable, $notification));
    }

    public function test_notification_controller_returns_notifications_view(): void
    {
        $controller = new NotificationController;
        $view = $controller->index();

        $this->assertSame('notifications.index', $view->name());
    }
}
