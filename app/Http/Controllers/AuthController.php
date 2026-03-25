<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Auth\GetOAuthLinksRequest;
use App\Http\Requests\Auth\OAuthCallbackRequest;
use App\Http\Resources\Auth\OAuthCallbackResultResource;
use App\Http\Resources\Auth\OAuthLinksResource;
use App\Services\Auth\OAuthService;

class AuthController extends Controller
{
    public function __construct(
        private readonly OAuthService $oAuthService,
    ) {
    }

    public function getOAuthLinks(GetOAuthLinksRequest $request): OAuthLinksResource
    {
        $links = $this->oAuthService->getOAuthLinks($request->validated('return_url'));

        return new OAuthLinksResource([
            'success' => true,
            'data' => $links,
            'error' => null,
        ]);
    }

    public function handleGoogleOAuthCallback(OAuthCallbackRequest $request): OAuthCallbackResultResource
    {
        return $this->handleCallback($request, 'google');
    }

    public function handleAppleOAuthCallback(OAuthCallbackRequest $request): OAuthCallbackResultResource
    {
        return $this->handleCallback($request, 'apple');
    }

    private function handleCallback(OAuthCallbackRequest $request, string $provider): OAuthCallbackResultResource
    {
        $result = $this->oAuthService->handleProviderCallback($request->validated(), $provider);

        return (new OAuthCallbackResultResource($result))
            ->withStatusCode($this->resolveCallbackStatusCode($result))
            ->withNoCacheHeaders();
    }

    private function resolveCallbackStatusCode(array $result): int
    {
        if (($result['success'] ?? false) === true) {
            return 200;
        }

        return match ((string) data_get($result, 'error.code', 'server_error')) {
            'invalid_state', 'invalid_response', 'invalid_user' => 422,
            'provider_declined' => 400,
            default => 500,
        };
    }
}
