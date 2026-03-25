<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use JsonException;

class OAuthCallbackRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $error = $this->normalizeNullableString($this->input('error'));
        $errorDescription = $this->normalizeNullableString($this->input('error_description'));

        $normalized = [
            'error' => $error,
            'error_description' => $errorDescription,
        ];

        if ($this->provider() === 'apple') {
            $rawUser = $this->input('user');

            if (is_array($rawUser)) {
                $encoded = json_encode($rawUser, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $normalized['user'] = is_string($encoded) ? $encoded : null;
            } elseif (is_string($rawUser)) {
                $trimmedUser = trim($rawUser);
                $normalized['user'] = $trimmedUser === '' ? null : $trimmedUser;
            }
        }

        $this->merge($normalized);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $provider = $this->provider();

        $rules = [
            'code' => ['nullable', 'required_without:error', 'string', 'max:2048'],
            'state' => ['nullable', 'required_with:code,error', 'string', 'max:4096'],
            'error' => ['nullable', 'required_without:code', 'string', 'max:191'],
            'error_description' => ['nullable', 'required_with:error', 'string', 'max:1024'],
        ];

        if ($provider === 'apple') {
            $rules['user'] = ['sometimes', 'string', 'max:8192'];
        }

        if ($provider === 'google') {
            $rules['scope'] = ['sometimes', 'string', 'max:1024'];
        }

        return $rules;
    }

    /**
     * @return ($key is null ? array<string, mixed> : mixed)
     */
    public function validated($key = null, $default = null): mixed
    {
        if ($key !== null) {
            return parent::validated($key, $default);
        }

        $validated = parent::validated($key, $default);

        if ($this->provider() !== 'apple') {
            return $validated;
        }

        $parsedUser = $this->parseAppleUserPayload($validated['user'] ?? null);

        if ($parsedUser !== null) {
            $validated['user_payload'] = $parsedUser;
        }

        return $validated;
    }

    private function provider(): string
    {
        if ($this->routeIs('auth.oauth.apple.callback')) {
            return 'apple';
        }

        return 'google';
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseAppleUserPayload(mixed $value): ?array
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
