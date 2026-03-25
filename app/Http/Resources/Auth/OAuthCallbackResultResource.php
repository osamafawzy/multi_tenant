<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\JsonResource;

class OAuthCallbackResultResource extends JsonResource
{
    private int $statusCode = Response::HTTP_OK;

    /**
     * @var array<string, string>
     */
    private array $headers = [];

    public function withStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function withNoCacheHeaders(): self
    {
        $this->headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        return $this;
    }

    public function toArray(Request $request): array
    {
        $success = (bool) ($this->resource['success'] ?? false);
        $data = $this->resource['data'] ?? null;

        return [
            'success' => $success,
            'data' => $success ? [
                'token' => (string) data_get($data, 'token', ''),
                'provider' => (string) data_get($data, 'provider', ''),
                'user' => new AuthUserResource(data_get($data, 'user')),
            ] : null,
            'error' => $success ? null : [
                'code' => (string) data_get($this->resource, 'error.code', ''),
                'message' => (string) data_get($this->resource, 'error.message', ''),
            ],
        ];
    }

    public function withResponse(Request $request, \Illuminate\Http\JsonResponse $response): void
    {
        $response->setStatusCode($this->statusCode);

        foreach ($this->headers as $header => $value) {
            $response->headers->set($header, $value);
        }
    }
}
