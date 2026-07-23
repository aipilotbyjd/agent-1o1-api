<?php

namespace App\Services;

use App\Models\OAuthConnection;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Passport\AccessToken;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthService
{
    public function __construct(private readonly TwoFactorAuthService $twoFactorAuthService) {}

    /**
     * @return array{user: User}|array{two_factor_challenge: string}
     */
    public function register(string $name, string $email, string $password): array
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        event(new Registered($user));

        return ['user' => $user];
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, user: User}|array{two_factor_challenge: string}
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new HttpException(422, 'These credentials do not match our records.');
        }

        if ($user->hasTwoFactorEnabled()) {
            return ['two_factor_challenge' => $this->createTwoFactorChallenge($user, $password)];
        }

        return [...$this->issuePasswordGrantToken($email, $password), 'user' => $user];
    }

    /**
     * Completes a 2FA login challenge and issues a token via the password grant — same
     * shape as a normal login (access_token + refresh_token), for frontend parity.
     *
     * The password is held encrypted in the cache alongside the challenge token (5 min TTL,
     * single-use, deleted immediately below) specifically so this step can complete a real
     * password-grant exchange instead of falling back to a refresh-token-less personal
     * access token.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, user: User}
     */
    public function completeTwoFactorChallenge(string $challengeToken, string $code): array
    {
        $challenge = Cache::get("2fa-challenge:{$challengeToken}");

        if (! $challenge) {
            throw new HttpException(422, 'This two-factor challenge has expired.');
        }

        $user = User::findOrFail($challenge['user_id']);

        if (! $this->twoFactorAuthService->verifyCode($user, $code)) {
            throw new HttpException(422, 'The provided two-factor code is invalid.');
        }

        Cache::forget("2fa-challenge:{$challengeToken}");

        $password = Crypt::decryptString($challenge['password']);

        return [...$this->issuePasswordGrantToken($user->email, $password), 'user' => $user];
    }

    public function logout(User $user): void
    {
        /** @var AccessToken|null $token */
        $token = $user->token();

        if (! $token?->id) {
            return;
        }

        RefreshToken::where('access_token_id', $token->id)->update(['revoked' => true]);
        $token->revoke();
    }

    public function logoutAll(User $user): void
    {
        $tokenIds = $user->tokens()->pluck('id');

        RefreshToken::whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
        $user->tokens()->update(['revoked' => true]);
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    public function refresh(string $refreshToken): array
    {
        return $this->requestOauthToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->passwordClientId(),
            'client_secret' => $this->passwordClientSecret(),
            'scope' => '',
        ], 'This refresh token is invalid or has expired.');
    }

    public function forgotPassword(string $email): void
    {
        Password::sendResetLink(['email' => $email]);
    }

    public function resetPassword(string $email, string $token, string $password): void
    {
        $status = Password::reset(
            ['email' => $email, 'token' => $token, 'password' => $password],
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();

                $tokenIds = $user->tokens()->pluck('id');
                RefreshToken::whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
                $user->tokens()->update(['revoked' => true]);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new HttpException(422, __($status));
        }
    }

    public function changePassword(User $user, string $newPassword, bool $revokeOtherTokens): void
    {
        $user->forceFill(['password' => $newPassword])->save();

        if ($revokeOtherTokens) {
            $currentTokenId = $user->token()?->id;

            $tokensQuery = $user->tokens()
                ->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId));

            $tokenIds = $tokensQuery->pluck('id');
            RefreshToken::whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
            $tokensQuery->update(['revoked' => true]);
        }
    }

    /**
     * @return Collection<int, Token>
     */
    public function sessions(User $user): Collection
    {
        return $user->tokens()->where('revoked', false)->latest()->get();
    }

    public function revokeSession(User $user, string $tokenId): void
    {
        $token = $user->tokens()->where('id', $tokenId)->firstOrFail();

        $token->revoke();
        $token->refreshToken?->revoke();
    }

    public function socialRedirectUrl(string $provider): string
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($provider);

        return $driver->stateless()->redirect()->getTargetUrl();
    }

    /**
     * @return array{access_token: string, user: User}
     */
    public function handleSocialCallback(string $provider): array
    {
        /** @var AbstractProvider $driver */
        $driver = Socialite::driver($provider);

        $socialUser = $driver->stateless()->user();

        $connection = OAuthConnection::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        $user = $connection?->user ?? User::where('email', $socialUser->getEmail())->first();

        if (! $user) {
            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? $socialUser->getEmail(),
                'email' => $socialUser->getEmail(),
                'password' => Str::password(32),
                'email_verified_at' => now(),
            ]);
        }

        if (! $connection) {
            OAuthConnection::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
            ]);
        }

        $token = $user->createToken('social-login-'.$provider);

        return ['access_token' => $token->accessToken, 'user' => $user];
    }

    private function createTwoFactorChallenge(User $user, string $password): string
    {
        $challengeToken = Str::random(64);

        Cache::put("2fa-challenge:{$challengeToken}", [
            'user_id' => $user->id,
            'password' => Crypt::encryptString($password),
        ], now()->addMinutes(5));

        return $challengeToken;
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    private function issuePasswordGrantToken(string $email, string $password): array
    {
        return $this->requestOauthToken([
            'grant_type' => 'password',
            'client_id' => $this->passwordClientId(),
            'client_secret' => $this->passwordClientSecret(),
            'username' => $email,
            'password' => $password,
            'scope' => '',
        ], 'These credentials do not match our records.');
    }

    /**
     * Dispatched in-process through the HTTP kernel rather than a real HTTP call —
     * `Http::post(url('/oauth/token'))` would require an actual server listening on that
     * URL, which doesn't exist during tests (no live server bound to the test database).
     * This also avoids a real network round-trip in production for a self-call.
     *
     * @param  array<string, string>  $parameters
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    private function requestOauthToken(array $parameters, string $failureMessage): array
    {
        $response = app(Kernel::class)->handle(Request::create('/oauth/token', 'POST', $parameters));

        if ($response->getStatusCode() !== 200) {
            throw new HttpException(422, $failureMessage);
        }

        return json_decode($response->getContent(), true);
    }

    private function passwordClientId(): string
    {
        return $this->requireConfig('passport.password_client_id');
    }

    private function passwordClientSecret(): string
    {
        return $this->requireConfig('passport.password_client_secret');
    }

    private function requireConfig(string $key): string
    {
        $value = config($key);

        if (! $value) {
            throw new RuntimeException("Missing config [{$key}]. Run `php artisan passport:client --password` and set the resulting env vars.");
        }

        return $value;
    }
}
