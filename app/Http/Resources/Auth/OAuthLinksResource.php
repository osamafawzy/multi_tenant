<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OAuthLinksResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = (array) ($this->resource['data'] ?? []);

        return [
            'success' => (bool) ($this->resource['success'] ?? true),
            'data' => [
                'google_url' => (string) ($data['google_url'] ?? ''),
                'apple_url' => (string) ($data['apple_url'] ?? ''),
            ],
            'error' => $this->resource['error'] ?? null,
        ];
    }
}
