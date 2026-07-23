<?php

use App\Models\OAuthConnection;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery\MockInterface;

/**
 * Socialite::fake() doesn't compose with ->stateless() — FakeProvider has no stateless()
 * method of its own, so it forwards the call to the real underlying provider via __call(),
 * which returns the real provider (not the fake), silently dropping the fake for the
 * subsequent ->user()/->redirect() call. Mocking the facade directly avoids that.
 */
function fakeSocialiteProvider(string $provider = 'google'): MockInterface&Provider
{
    $mock = Mockery::mock(Provider::class);
    $mock->shouldReceive('stateless')->andReturnSelf();

    Socialite::shouldReceive('driver')->with($provider)->andReturn($mock);

    return $mock;
}

it('returns a redirect url for a provider', function () {
    $provider = fakeSocialiteProvider();
    $provider->shouldReceive('redirect')->andReturn(new RedirectResponse('https://accounts.google.com/o/oauth2/authorize?client_id=fake'));

    $response = $this->getJson('/api/v1/auth/social/google/redirect');

    $response->assertOk()->assertJsonPath('data.url', 'https://accounts.google.com/o/oauth2/authorize?client_id=fake');
});

it('creates a new user and issues a token on first social login', function () {
    $provider = fakeSocialiteProvider();
    $socialiteUser = (new SocialiteUser)->map([
        'id' => 'google-123',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'avatar' => 'https://example.com/avatar.png',
    ]);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    $response = $this->postJson('/api/v1/auth/social/google/callback', ['code' => 'fake-code']);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'ada@example.com')
        ->assertJsonStructure(['data' => ['access_token', 'user']]);

    $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    $this->assertDatabaseHas('oauth_connections', [
        'provider' => 'google',
        'provider_id' => 'google-123',
    ]);
});

it('links to an existing user on repeat social login instead of duplicating', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $provider = fakeSocialiteProvider();
    $socialiteUser = (new SocialiteUser)->map([
        'id' => 'google-123',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    $response = $this->postJson('/api/v1/auth/social/google/callback', ['code' => 'fake-code']);

    $response->assertOk()->assertJsonPath('data.user.id', $user->id);
    expect(User::count())->toBe(1);
    expect(OAuthConnection::where('user_id', $user->id)->where('provider', 'google')->exists())->toBeTrue();
});

it('reuses the same connection on subsequent logins from the same provider account', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);
    OAuthConnection::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
    ]);

    $provider = fakeSocialiteProvider();
    $socialiteUser = (new SocialiteUser)->map([
        'id' => 'google-123',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    $response = $this->postJson('/api/v1/auth/social/google/callback', ['code' => 'fake-code']);

    $response->assertOk()->assertJsonPath('data.user.id', $user->id);
    expect(OAuthConnection::where('user_id', $user->id)->where('provider', 'google')->count())->toBe(1);
});

it('logs in with github using the same generic provider flow', function () {
    $provider = fakeSocialiteProvider('github');
    $socialiteUser = (new SocialiteUser)->map([
        'id' => 'gh-456',
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'nickname' => 'gracehopper',
        'avatar' => 'https://example.com/grace.png',
    ]);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    $response = $this->postJson('/api/v1/auth/social/github/callback', ['code' => 'fake-code']);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'grace@example.com');

    $this->assertDatabaseHas('oauth_connections', [
        'provider' => 'github',
        'provider_id' => 'gh-456',
    ]);
});

it('falls back to nickname for the name when github provides no name', function () {
    $provider = fakeSocialiteProvider('github');
    $socialiteUser = (new SocialiteUser)->map([
        'id' => 'gh-789',
        'name' => null,
        'nickname' => 'octocat',
        'email' => 'octocat@example.com',
    ]);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    $response = $this->postJson('/api/v1/auth/social/github/callback', ['code' => 'fake-code']);

    $response->assertOk()->assertJsonPath('data.user.name', 'octocat');
});
