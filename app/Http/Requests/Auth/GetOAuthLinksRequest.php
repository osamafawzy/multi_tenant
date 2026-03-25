<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GetOAuthLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'return_url' => [
                'nullable',
                'string',
                'url',
                'max:2048',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $returnUrl = $this->input('return_url');

            if (!is_string($returnUrl) || $returnUrl === '') {
                return;
            }

            if (!$this->isAllowedOrigin($returnUrl)) {
                $validator->errors()->add('return_url', 'The return_url origin is not allowed.');
            }
        });
    }

    private function isAllowedOrigin(string $returnUrl): bool
    {
        $frontendUrl = (string) config('app.frontend_url');

        return $this->sameOrigin($returnUrl, $frontendUrl);
    }

    private function sameOrigin(string $candidateUrl, string $allowedBaseUrl): bool
    {
        $candidate = parse_url($candidateUrl);
        $allowed = parse_url($allowedBaseUrl);

        if (!is_array($candidate) || !is_array($allowed)) {
            return false;
        }

        $candidateScheme = strtolower((string) ($candidate['scheme'] ?? ''));
        $candidateHost = strtolower((string) ($candidate['host'] ?? ''));
        $candidatePort = $candidate['port'] ?? null;

        $allowedScheme = strtolower((string) ($allowed['scheme'] ?? ''));
        $allowedHost = strtolower((string) ($allowed['host'] ?? ''));
        $allowedPort = $allowed['port'] ?? null;

        if ($candidateScheme === '' || $candidateHost === '' || $allowedScheme === '' || $allowedHost === '') {
            return false;
        }

        return $candidateScheme === $allowedScheme
            && $candidateHost === $allowedHost
            && $candidatePort === $allowedPort;
    }
}
