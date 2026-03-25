<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OAuthCallbackResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => (bool) ($this->resource['success'] ?? false),
            'data' => [
                'provider' => (string) ($this->resource['provider'] ?? ''),
                'redirect_url' => (string) ($this->resource['redirect_url'] ?? ''),
            ],
            'error' => isset($this->resource['error_code'])
                ? ['code' => (string) $this->resource['error_code']]
                : null,
        ];
    }
}
