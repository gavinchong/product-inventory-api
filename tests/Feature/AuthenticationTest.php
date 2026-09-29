<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_user_and_a_working_bearer_token(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertOk()->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token');
        $token = $response->json('data.token');
        $this->assertNotNull(PersonalAccessToken::findToken($token));
        $this->getJson('/api/products', ['Authorization' => 'Bearer '.$token])->assertOk();
    }

    public function test_invalid_credentials_are_rejected_without_issuing_a_token(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);
        foreach ([$user->email, 'missing@example.com'] as $email) {
            $this->postJson('/api/login', ['email' => $email, 'password' => 'wrong-password'])
                ->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_requires_valid_email_and_password(): void
    {
        $this->postJson('/api/login', ['email' => 'invalid'])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_logout_revokes_only_current_token_and_it_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current');
        $other = $user->createToken('other');
        $this->postJson('/api/logout', [], ['Authorization' => 'Bearer '.$current->plainTextToken])->assertNoContent();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);

        // Reset cached guards to model a separate HTTP request with the revoked token.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/products', ['Authorization' => 'Bearer '.$current->plainTextToken])->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/products', ['Authorization' => 'Bearer '.$other->plainTextToken])->assertOk();
    }

    public function test_logout_requires_authentication_and_invalid_tokens_are_rejected(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized();
        $this->getJson('/api/products', ['Authorization' => 'Bearer invalid-token'])->assertUnauthorized();
    }
}
