<?php

declare(strict_types=1);

namespace App\Services\Auth\Providers;

use Google_Client;
use Illuminate\Support\Facades\Http;

class GoogleOAuthProvider
{
    public function authorizationUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => (string) config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeCodeForTokens(string $code): array
    {
        return Http::asForm()
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => (string) config('services.google.client_id'),
                'client_secret' => (string) config('services.google.client_secret'),
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
                'code' => $code,
            ])
            ->json();
    }

    private function redirectUri(): string
    {
        return (string) (config('services.google.redirect') ?: route('auth.oauth.google.callback'));
    }

    /**
     * @param array<string, mixed> $tokens
     * @return array{id: string, email: string|null, name: string, email_verified: bool}|null
     */
    public function extractUserData(array $tokens): ?array
    {
        try {
            $client = new Google_Client([
                'client_id' => (string) config('services.google.client_id'),
            ]);
            $payload = $client->verifyIdToken((string) ($tokens['id_token'] ?? ''));

            if (!$payload) {
                return null;
            }

            return [
                'id' => (string) $payload['sub'],
                'email' => isset($payload['email']) ? (string) $payload['email'] : null,
                'name' => isset($payload['name']) ? (string) $payload['name'] : 'Google User',
                'email_verified' => filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOL),
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
