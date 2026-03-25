<?php

declare(strict_types=1);

namespace App\Services\Auth\Providers;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AppleOAuthProvider
{
    public function authorizationUrl(string $state): string
    {
        return 'https://appleid.apple.com/auth/authorize?' . http_build_query([
            'client_id' => (string) config('services.apple.client_id'),
            'redirect_uri' => route('auth.oauth.apple.callback'),
            'response_type' => 'code',
            'scope' => 'email name',
            'state' => $state,
            'response_mode' => 'form_post',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeCodeForTokens(string $code): array
    {
        try {
            $response = Http::asForm()->post('https://appleid.apple.com/auth/token', [
                'client_id' => (string) config('services.apple.client_id'),
                'client_secret' => $this->generateClientSecret(),
                'redirect_uri' => route('auth.oauth.apple.callback'),
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Apple token exchange request failed', [
                'exception' => $exception::class,
            ]);

            return [];
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            Log::warning('Apple token exchange returned non-array payload', [
                'status' => $response->status(),
            ]);

            return [];
        }

        if (!$response->successful()) {
            Log::warning('Apple token exchange returned unsuccessful response', [
                'status' => $response->status(),
                'error' => is_string($payload['error'] ?? null) ? $payload['error'] : null,
                'error_description' => is_string($payload['error_description'] ?? null) ? $payload['error_description'] : null,
            ]);

            return [];
        }

        if (!is_string($payload['id_token'] ?? null) || $payload['id_token'] === '') {
            Log::warning('Apple token exchange missing expected id_token', [
                'status' => $response->status(),
                'has_access_token' => is_string($payload['access_token'] ?? null) && $payload['access_token'] !== '',
                'token_type' => is_string($payload['token_type'] ?? null) ? $payload['token_type'] : null,
            ]);

            return [];
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $tokens
     * @return array{id: string, email: string|null, name: string, email_verified: bool}|null
     */
    public function extractUserData(array $tokens): ?array
    {
        $idToken = $tokens['id_token'] ?? null;
        if (!is_string($idToken) || $idToken === '') {
            return null;
        }

        try {
            $appleKeys = Http::get('https://appleid.apple.com/auth/keys')->json();
            $header = $this->decodeJwtHeader($idToken);
            if (!is_array($header) || !is_string($header['kid'] ?? null) || $header['kid'] === '' || !is_array($appleKeys)) {
                return null;
            }

            $verificationKey = $this->resolveVerificationKey($appleKeys, $header['kid']);
            if ($verificationKey === null) {
                return null;
            }

            $payload = JWT::decode($idToken, $verificationKey);
            if (!$this->hasValidClaims($payload)) {
                return null;
            }

            return [
                'id' => (string) $payload->sub,
                'email' => property_exists($payload, 'email') ? (string) $payload->email : null,
                'name' => 'Apple User',
                'email_verified' => property_exists($payload, 'email_verified')
                    ? filter_var($payload->email_verified, FILTER_VALIDATE_BOOL)
                    : false,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJwtHeader(string $jwt): ?array
    {
        $tokenParts = explode('.', $jwt);
        $headerPart = $tokenParts[0] ?? '';
        if ($headerPart === '') {
            return null;
        }

        $base64Header = strtr($headerPart, '-_', '+/');
        $padding = (4 - strlen($base64Header) % 4) % 4;
        $decodedHeader = base64_decode($base64Header . str_repeat('=', $padding), true);
        if (!is_string($decodedHeader) || $decodedHeader === '') {
            return null;
        }

        $header = json_decode($decodedHeader, true);

        return is_array($header) ? $header : null;
    }

    private function resolveVerificationKey(array $appleKeys, string $kid): ?Key
    {
        if (class_exists(JWK::class) && isset($appleKeys['keys']) && is_array($appleKeys['keys'])) {
            try {
                $parsedKeys = JWK::parseKeySet($appleKeys);
                $candidate = $parsedKeys[$kid] ?? null;

                return $candidate instanceof Key ? $candidate : null;
            } catch (\Throwable) {
            }
        }

        $key = collect($appleKeys['keys'] ?? [])->firstWhere('kid', $kid);
        if (!is_array($key)) {
            return null;
        }

        return $this->resolveKeyFromX5c($key);
    }

    /**
     * @param array<string, mixed> $jwk
     */
    private function resolveKeyFromX5c(array $jwk): ?Key
    {
        $x5c = $jwk['x5c'] ?? null;
        if (!is_array($x5c) || !is_string($x5c[0] ?? null) || $x5c[0] === '') {
            return null;
        }

        $pem = "-----BEGIN CERTIFICATE-----\n"
            . chunk_split($x5c[0], 64, "\n")
            . "-----END CERTIFICATE-----\n";
        $publicKey = openssl_pkey_get_public($pem);

        if ($publicKey === false) {
            return null;
        }

        return new Key($publicKey, 'RS256');
    }

    private function generateClientSecret(): string
    {
        $header = ['alg' => 'ES256', 'kid' => (string) config('services.apple.key_id')];
        $claims = [
            'iss' => (string) config('services.apple.team_id'),
            'iat' => now()->timestamp,
            'exp' => now()->addHour()->timestamp,
            'aud' => 'https://appleid.apple.com',
            'sub' => (string) config('services.apple.client_id'),
        ];

        $privateKeyPath = $this->resolvePrivateKeyPath((string) config('services.apple.private_key_path', ''));
        $privateKey = file_get_contents($privateKeyPath);

        if (!is_string($privateKey) || $privateKey === '') {
            throw new RuntimeException('Apple private key is unreadable.');
        }

        return JWT::encode($claims, (string) $privateKey, 'ES256', null, $header);
    }

    private function resolvePrivateKeyPath(string $configuredPath): string
    {
        $trimmedPath = trim($configuredPath);
        if ($trimmedPath === '') {
            throw new RuntimeException('Apple private key path is not configured.');
        }

        $resolved = $trimmedPath;
        if (!str_starts_with($trimmedPath, '/') && !preg_match('/^[A-Za-z]:[\\\\\\/]/', $trimmedPath)) {
            $resolved = base_path($trimmedPath);
        }

        $realPath = realpath($resolved);
        if ($realPath === false || !is_file($realPath) || !is_readable($realPath)) {
            throw new RuntimeException('Apple private key file is missing or unreadable.');
        }

        return $realPath;
    }

    private function hasValidClaims(object $payload): bool
    {
        $issuer = property_exists($payload, 'iss') ? (string) $payload->iss : '';
        $subject = property_exists($payload, 'sub') ? (string) $payload->sub : '';
        $expiresAt = property_exists($payload, 'exp') ? (int) $payload->exp : 0;
        $audienceClaim = property_exists($payload, 'aud') ? $payload->aud : null;
        $expectedAudience = (string) config('services.apple.client_id');

        if ($issuer !== 'https://appleid.apple.com' || $subject === '' || $expiresAt <= now()->timestamp) {
            return false;
        }

        if (is_string($audienceClaim)) {
            return hash_equals($expectedAudience, $audienceClaim);
        }

        if (is_array($audienceClaim)) {
            foreach ($audienceClaim as $audience) {
                if (is_string($audience) && hash_equals($expectedAudience, $audience)) {
                    return true;
                }
            }
        }

        return false;
    }

}
