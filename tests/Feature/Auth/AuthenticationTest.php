<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Drop the resolved guard between requests.
     *
     * Laravel's RequestGuard memoises the user it resolved, and the guard
     * instance outlives a single request inside the test process. Without this,
     * a request made after a token was revoked would still be served from that
     * memoised user instead of re-reading the token.
     */
    private function forgetAuthenticatedUser(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_a_user_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => ['user' => ['id', 'name', 'email', 'created_at'], 'token'],
            ])
            ->assertJsonPath('data.user.email', 'jane@example.com');

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $this->assertSame(1, User::firstWhere('email', 'jane@example.com')->tokens()->count());
    }

    public function test_registration_never_exposes_the_password(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertCreated()
            ->assertJsonMissingPath('data.user.password');

        $user = User::firstWhere('email', 'jane@example.com');
        $this->assertNotSame('secret-password', $user->password);
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_registration_validates_its_input(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/register', [
            'name' => 'Jane Doe',
            'email' => 'taken@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame(1, User::where('email', 'taken@example.com')->count());
    }

    public function test_a_user_can_log_in_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secret-password',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'jane@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'data' => ['user', 'token']])
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_login_rejects_a_wrong_password_without_issuing_a_token(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secret-password',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'The provided credentials are incorrect.');

        $this->assertSame(0, $user->tokens()->count());
    }

    /**
     * An unknown email and a wrong password must be indistinguishable, otherwise
     * the endpoint can be used to discover which addresses are registered.
     */
    public function test_login_does_not_reveal_whether_an_email_is_registered(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secret-password',
        ]);

        $wrongPassword = $this->postJson('/api/v1/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]);

        $unknownEmail = $this->postJson('/api/v1/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);

        $wrongPassword->assertUnauthorized();
        $unknownEmail->assertUnauthorized();
        $this->assertSame(
            $wrongPassword->json('message'),
            $unknownEmail->json('message'),
        );
    }

    public function test_login_validates_its_input(): void
    {
        $response = $this->postJson('/api/v1/login', ['email' => 'not-an-email']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $credentials = ['email' => 'jane@example.com', 'password' => 'wrong-password'];

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/api/v1/login', $credentials)->assertUnauthorized();
        }

        $this->postJson('/api/v1/login', $credentials)->assertStatus(429);
    }

    public function test_the_current_user_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_the_current_user_endpoint_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/v1/logout')->assertUnauthorized();
    }

    public function test_logout_revokes_the_token_used_for_the_request(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secret-password',
        ]);

        $token = $this->postJson('/api/v1/login', [
            'email' => 'jane@example.com',
            'password' => 'secret-password',
        ])->json('data.token');

        $this->withToken($token)->postJson('/api/v1/logout')->assertOk();

        $this->assertSame(0, $user->fresh()->tokens()->count());

        $this->forgetAuthenticatedUser();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    /**
     * Signing out on one device must not sign the user out everywhere.
     */
    public function test_logout_leaves_tokens_issued_to_other_devices_intact(): void
    {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secret-password',
        ]);

        $credentials = ['email' => 'jane@example.com', 'password' => 'secret-password'];
        $phoneToken = $this->postJson('/api/v1/login', $credentials)->json('data.token');
        $laptopToken = $this->postJson('/api/v1/login', $credentials)->json('data.token');

        $this->withToken($phoneToken)->postJson('/api/v1/logout')->assertOk();

        $this->assertSame(1, $user->fresh()->tokens()->count());

        $this->forgetAuthenticatedUser();
        $this->withToken($phoneToken)->getJson('/api/v1/me')->assertUnauthorized();

        $this->forgetAuthenticatedUser();
        $this->withToken($laptopToken)->getJson('/api/v1/me')->assertOk();
    }
}
