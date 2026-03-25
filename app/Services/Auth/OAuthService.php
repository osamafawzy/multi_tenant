<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Auth\Providers\AppleOAuthProvider;
use App\Services\Auth\Providers\GoogleOAuthProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class OAuthService
{
    public function __construct(
        private readonly GoogleOAuthProvider $googleOAuthProvider,
        private readonly AppleOAuthProvider $appleOAuthProvider,
    ) {
    }

    public function getOAuthLinks(?string $returnUrl = null): array
    {
        $resolvedReturnUrl = $this->resolveReturnUrl($returnUrl);
        $stateData = $this->createState($resolvedReturnUrl);

        return [
            'google_url' => $this->googleOAuthProvider->authorizationUrl($stateData['state']),
            'apple_url' => $this->appleOAuthProvider->authorizationUrl($stateData['state'], $stateData['nonce']),
        ];
    }

    public function handleProviderCallback(array $payload, string $provider): array
    {
        if ($this->hasProviderDeclined($payload)) {
            $stateData = $this->consumeState((string) ($payload['state'] ?? ''));
            if ($stateData === null) {
                return $this->errorResult('invalid_state');
            }

            return $this->errorResult('provider_declined');
        }

        $code = (string) ($payload['code'] ?? '');
        $stateData = $this->consumeState((string) ($payload['state'] ?? ''));

        if ($stateData === null) {
            return $this->errorResult('invalid_state');
        }

        if ($code === '') {
            return $this->errorResult('invalid_response');
        }

        try {
            $tokens = $this->exchangeCodeForTokens($provider, $code);
            $userData = $this->extractUserData($provider, $tokens, $stateData, $payload);

            if (!$userData) {
                return $this->errorResult('invalid_user');
            }

            $user = $this->findOrCreateUser($userData, $provider);
            $accessToken = $user->createToken('auth_token')->plainTextToken;

            return [
                'success' => true,
                'data' => [
                    'token' => $accessToken,
                    'provider' => $provider,
                    'user' => $user,
                ],
                'error' => null,
            ];
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'invalid_state') {
                return $this->errorResult('invalid_state');
            }

            Log::error('OAuth callback runtime error', [
                'provider' => $provider,
                'exception' => $exception::class,
            ]);

            return $this->errorResult('server_error');
        } catch (\Throwable $exception) {
            Log::error('OAuth callback error', [
                'provider' => $provider,
                'exception' => $exception::class,
            ]);

            return $this->errorResult('server_error');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeCodeForTokens(string $provider, string $code): array
    {
        return match ($provider) {
            'google' => $this->googleOAuthProvider->exchangeCodeForTokens($code),
            'apple' => $this->appleOAuthProvider->exchangeCodeForTokens($code),
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $tokens
     * @param array{id: string, iat: int, exp: int, return_url: string, nonce: string} $stateData
     * @return array{id: string, email: string|null, name: string, email_verified: bool}|null
     */
    private function extractUserData(string $provider, array $tokens, array $stateData, array $callbackPayload): ?array
    {
        return match ($provider) {
            'google' => $this->googleOAuthProvider->extractUserData($tokens),
            'apple' => $this->appleOAuthProvider->extractUserData(
                $tokens,
                $stateData['nonce'],
                is_array($callbackPayload['user_payload'] ?? null) ? $callbackPayload['user_payload'] : null,
            ),
            default => null,
        };
    }

    /**
     * @param array{id: string, email: string|null, name: string, email_verified: bool} $userData
     */
    private function findOrCreateUser(array $userData, string $provider): User
    {
        $providerId = $provider === 'google' ? 'google_id' : 'apple_id';

        $user = User::query()->where($providerId, $userData['id'])->first();

        if (
            !$user
            && $userData['email_verified'] === true
            && !empty($userData['email'])
        ) {
            $user = User::query()->where('email', $userData['email'])->first();
            if ($user) {
                $user->update([$providerId => $userData['id']]);
            }
        }

        if (!$user) {
            $user = User::query()->create([
                'name' => $userData['name'] ?: 'User',
                'email' => $userData['email'],
                'password' => Hash::make(Str::random(64)),
                $providerId => $userData['id'],
            ]);
        }

        return $user;
    }

    private function errorResult(string $errorCode): array
    {
        return [
            'success' => false,
            'data' => null,
            'error' => [
                'code' => $errorCode,
                'message' => $this->errorMessage($errorCode),
            ],
        ];
    }

    private function hasProviderDeclined(array $payload): bool
    {
        $error = $payload['error'] ?? null;

        return is_string($error) && $error !== '';
    }

    /**
     * @return array{state: string, nonce: string}
     */
    private function createState(string $returnUrl): array
    {
        $issuedAt = now()->timestamp;
        $expiresAt = now()->addMinutes(10)->timestamp;
        $stateId = (string) Str::uuid();
        $nonce = Str::random(64);

        $payload = [
            'id' => $stateId,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'return_url' => $returnUrl,
            'nonce' => $nonce,
        ];

        $encodedPayload = $this->encodeStatePayload($payload);
        $signature = hash_hmac('sha256', $encodedPayload, $this->stateSigningKey());
        $state = $encodedPayload . '.' . $signature;

        Cache::put(
            $this->stateCacheKey($stateId),
            ['hash' => hash('sha256', $state)],
            now()->addSeconds(max(1, $expiresAt - $issuedAt)),
        );

        return [
            'state' => $state,
            'nonce' => $nonce,
        ];
    }

    /**
     * @return array{id: string, iat: int, exp: int, return_url: string, nonce: string}|null
     */
    private function consumeState(string $state): ?array
    {
        if ($state === '') {
            return null;
        }

        $segments = explode('.', $state, 2);
        if (count($segments) !== 2 || $segments[0] === '' || $segments[1] === '') {
            return null;
        }

        [$encodedPayload, $signature] = $segments;
        $expectedSignature = hash_hmac('sha256', $encodedPayload, $this->stateSigningKey());

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $base64Payload = strtr($encodedPayload, '-_', '+/');
        $padding = (4 - strlen($base64Payload) % 4) % 4;
        $payloadJson = base64_decode($base64Payload . str_repeat('=', $padding), true);
        $payload = is_string($payloadJson) ? json_decode($payloadJson, true) : null;

        if (
            !is_array($payload)
            || !is_string($payload['id'] ?? null)
            || !is_int($payload['iat'] ?? null)
            || !is_int($payload['exp'] ?? null)
            || !is_string($payload['return_url'] ?? null)
            || !is_string($payload['nonce'] ?? null)
            || strlen($payload['nonce']) < 32
        ) {
            return null;
        }

        if ($payload['exp'] < now()->timestamp || !$this->isAllowedReturnUrl($payload['return_url'])) {
            return null;
        }

        $cachedState = Cache::pull($this->stateCacheKey($payload['id']));
        if (!is_array($cachedState) || !is_string($cachedState['hash'] ?? null)) {
            return null;
        }

        if (!hash_equals($cachedState['hash'], hash('sha256', $state))) {
            return null;
        }

        return $payload;
    }

    private function stateSigningKey(): string
    {
        $appKey = (string) config('app.key', '');

        if (Str::startsWith($appKey, 'base64:')) {
            $decodedKey = base64_decode(Str::after($appKey, 'base64:'), true);

            if (is_string($decodedKey) && $decodedKey !== '') {
                return $decodedKey;
            }
        }

        if ($appKey === '') {
            throw new RuntimeException('OAuth state signing key is not configured.');
        }

        return $appKey;
    }

    private function stateCacheKey(string $stateId): string
    {
        return 'oauth:state:' . $stateId;
    }

    /**
     * @param array{id: string, iat: int, exp: int, return_url: string, nonce: string} $payload
     */
    private function encodeStatePayload(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private function resolveReturnUrl(?string $returnUrl): string
    {
        $defaultReturnUrl = (string) config('app.frontend_url');
        $candidate = $returnUrl ?: $defaultReturnUrl;

        if (!$this->isAllowedReturnUrl($candidate)) {
            return $defaultReturnUrl;
        }

        return $candidate;
    }

    private function isAllowedReturnUrl(string $url): bool
    {
        $allowedFrontend = (string) config('app.frontend_url');

        return $this->sameOrigin($url, $allowedFrontend);
    }

    private function sameOrigin(string $candidateUrl, string $allowedBaseUrl): bool
    {
        $candidate = parse_url($candidateUrl);
        $allowed = parse_url($allowedBaseUrl);

        if (!is_array($candidate) || !is_array($allowed)) {
            return false;
        }

        $candidateScheme = strtolower((string) ($candidate['scheme'] ?? ''));
        $candidateHost = strtolower((string) ($candidate['host'] ?? ''));
        $candidatePort = $this->normalizeOriginPort($candidateScheme, $candidate['port'] ?? null);

        $allowedScheme = strtolower((string) ($allowed['scheme'] ?? ''));
        $allowedHost = strtolower((string) ($allowed['host'] ?? ''));
        $allowedPort = $this->normalizeOriginPort($allowedScheme, $allowed['port'] ?? null);

        if ($candidateScheme === '' || $candidateHost === '' || $allowedScheme === '' || $allowedHost === '') {
            return false;
        }

        return $candidateScheme === $allowedScheme
            && $candidateHost === $allowedHost
            && $candidatePort === $allowedPort;
    }

    private function errorMessage(string $errorCode): string
    {
        return match ($errorCode) {
            'provider_declined' => 'OAuth provider declined the authorization request.',
            'invalid_state' => 'OAuth state is invalid or expired.',
            'invalid_response' => 'OAuth provider response is missing required data.',
            'invalid_user' => 'Unable to resolve a valid user from OAuth provider data.',
            default => 'Unexpected OAuth callback failure.',
        };
    }

    private function normalizeOriginPort(string $scheme, mixed $port): ?int
    {
        if (is_int($port)) {
            return $port;
        }

        if (is_string($port) && is_numeric($port)) {
            return (int) $port;
        }

        return match (strtolower($scheme)) {
            'http' => 80,
            'https' => 443,
            default => null,
        };
    }
}
