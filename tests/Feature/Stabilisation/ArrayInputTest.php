<?php

namespace Tests\Feature\Stabilisation;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * R-49. A parameter the code reads as text can arrive as a list (`topic[]=x`). Casting or passing it on raised an
 * error, so anybody could make these addresses answer 500 and fill the error log that the operations alert reads.
 * A list where text is expected is now treated as if the parameter was not sent.
 */
class ArrayInputTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string, 2: array<string, mixed>, 3: bool}> */
    public static function requests(): array
    {
        return [
            'end session hint' => ['GET', '/oauth/end-session', ['id_token_hint' => ['x']], false],
            'end session state' => ['GET', '/oauth/end-session', ['state' => ['x'], 'post_logout_redirect_uri' => ['x']], false],
            'authorize client' => ['GET', '/oauth/authorize', ['client_id' => ['x']], false],
            'authorize other fields' => ['GET', '/oauth/authorize', ['client_id' => 'x', 'scope' => ['x'], 'state' => ['x'], 'nonce' => ['x'], 'code_challenge' => ['x']], false],
            'token client' => ['POST', '/oauth/token', ['client_id' => ['x'], 'grant_type' => ['x']], false],
            'resource registry platform' => ['POST', '/api/ecosystem/resources', ['platform' => ['x']], false],
            'questions topic' => ['GET', '/questions', ['topic' => ['x']], true],
            'questions filters' => ['GET', '/questions', ['has' => ['x'], 'sort' => ['x'], 'q' => ['x']], true],
            'solutions topic' => ['GET', '/solutions', ['topic' => ['x']], true],
            'solutions filters' => ['GET', '/solutions', ['has' => ['x'], 'sort' => ['x'], 'q' => ['x'], 'verified' => ['x']], true],
            'search results' => ['GET', '/solution-results', ['search' => ['x'], 'topic' => ['x']], false],
        ];
    }

    /** @param  array<string, mixed>  $input */
    #[DataProvider('requests')]
    public function test_a_list_where_text_is_expected_is_not_a_server_error(string $method, string $uri, array $input, bool $signedIn): void
    {
        if ($signedIn) {
            $user = User::factory()->create();
            $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
            $user->forceFill(['current_team_id' => $team->id])->save();
            $this->actingAs($user->fresh());
        }

        $response = $method === 'GET' ? $this->get($uri.'?'.http_build_query($input)) : $this->post($uri, $input);

        $this->assertLessThan(500, $response->getStatusCode(), $uri.' answered '.$response->getStatusCode());
    }
}
