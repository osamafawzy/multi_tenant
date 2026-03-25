<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class OAuthCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $provider = $this->provider();

        $rules = [
            'code' => ['nullable', 'required_without:error', 'string', 'max:2048'],
            'state' => ['nullable', 'required_with:code', 'string', 'max:4096'],
            'error' => ['nullable', 'string', 'max:191'],
            'error_description' => ['nullable', 'string', 'max:1024'],
        ];

        if ($provider === 'apple') {
            $rules['user'] = ['sometimes', 'string', 'max:8192'];
        }

        if ($provider === 'google') {
            $rules['scope'] = ['sometimes', 'string', 'max:1024'];
        }

        return $rules;
    }

    private function provider(): string
    {
        if ($this->routeIs('auth.oauth.apple.callback')) {
            return 'apple';
        }

        return 'google';
    }
}
