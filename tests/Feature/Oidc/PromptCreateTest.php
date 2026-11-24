<?php

namespace Tests\Feature\Oidc;

/** R-54 phase 2: a platform's "Create account" opens InfoDot's registration page first (prompt=create); nothing else changes. */
class PromptCreateTest extends OidcTestCase
{
    public function test_a_signed_out_visitor_with_prompt_create_lands_on_registration(): void
    {
        [, $challenge] = $this->pkcePair();

        $this->get('/oauth/authorize?'.http_build_query($this->authorizeQuery($challenge, ['prompt' => 'create'])))
            ->assertRedirectContains(route('register').'?f=');
    }

    public function test_without_it_a_signed_out_visitor_lands_on_login(): void
    {
        [, $challenge] = $this->pkcePair();

        $this->get('/oauth/authorize?'.http_build_query($this->authorizeQuery($challenge)))->assertRedirectContains(route('login').'?f=');
    }

    public function test_any_other_prompt_value_changes_nothing_and_is_never_an_address(): void
    {
        [, $challenge] = $this->pkcePair();

        foreach (['login', 'https://evil.example/', 'CREATE', 'create create'] as $value) {
            $this->get('/oauth/authorize?'.http_build_query($this->authorizeQuery($challenge, ['prompt' => $value])))
                ->assertRedirectContains(route('login').'?f=');
        }
    }
}
