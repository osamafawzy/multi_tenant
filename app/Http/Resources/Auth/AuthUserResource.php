<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'name' => data_get($this->resource, 'name'),
            'email' => data_get($this->resource, 'email'),
            'google_id' => data_get($this->resource, 'google_id'),
            'apple_id' => data_get($this->resource, 'apple_id'),
        ];
    }
}
