<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Auth\GetOAuthLinksRequest;
use App\Http\Requests\Auth\OAuthCallbackRequest;
use App\Http\Resources\Auth\OAuthCallbackResultResource;
use App\Http\Resources\Auth\OAuthLinksResource;
use App\Services\Auth\OAuthService;
use Illuminate\Http\RedirectResponse;

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

    public function handleGoogleOAuthCallback(OAuthCallbackRequest $request): RedirectResponse|OAuthCallbackResultResource
    {
        return $this->handleCallback($request, 'google');
    }

    public function handleAppleOAuthCallback(OAuthCallbackRequest $request): RedirectResponse|OAuthCallbackResultResource
    {
        return $this->handleCallback($request, 'apple');
    }

    private function handleCallback(OAuthCallbackRequest $request, string $provider): RedirectResponse|OAuthCallbackResultResource
    {
        $result = $this->oAuthService->handleProviderCallback($request->validated(), $provider);

        if (!$request->expectsJson()) {
            return redirect()->to((string) $result['redirect_url']);
        }

        return new OAuthCallbackResultResource($result);
    }
}
