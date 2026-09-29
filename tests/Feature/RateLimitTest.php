<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('rate-limit-test@example.com|127.0.0.1');
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        User::factory()->create([
            'email' => 'rate-limit-test@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => 'rate-limit-test@example.com',
                'password' => 'wrong-password',
            ]);

            $response->assertStatus(422);
        }

        $response = $this->postJson('/api/login', [
            'email' => 'rate-limit-test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }

    public function test_authenticated_api_allows_requests_below_rate_limit(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/products');

        $response->assertOk();
    }

    public function test_authenticated_api_is_rate_limited_after_sixty_requests(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        for ($i = 0; $i < 60; $i++) {
            $response = $this->getJson('/api/products');

            $response->assertOk();
        }

        $response = $this->getJson('/api/products');

        $response->assertStatus(429);
    }
}
