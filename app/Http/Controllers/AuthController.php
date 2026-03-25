<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\SocialLoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Google_Client;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function socialLogin(SocialLoginRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $provider = $request->provider;
            $token = $request->token;

            // Validate token based on provider
            if ($provider === 'google') {
                $userData = $this->validateGoogleToken($token);
            } else {
                $userData = $this->validateAppleToken($token);
            }

            if (!$userData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token'
                ], 401);
            }

            // Find or create user
            $user = $this->findOrCreateUser($userData, $provider);

            // Generate Sanctum token
            $accessToken = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $user,
                    'access_token' => $accessToken,
                    'token_type' => 'Bearer',
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Social login error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Server error'
            ], 500);
        }
    }

    private function validateGoogleToken($token)
    {
        try {
            $client = new Google_Client(['client_id' => config('services.google.client_id')]);
            $payload = $client->verifyIdToken($token);

            if ($payload) {
                return [
                    'id' => $payload['sub'],
                    'email' => $payload['email'],
                    'name' => $payload['name'],
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Google token validation failed: ' . $e->getMessage());
            return null;
        }
    }

    private function validateAppleToken($token)
    {
        try {
            $appleKeys = Http::get('https://appleid.apple.com/auth/keys')->json();

            $tokenParts = explode('.', $token);
            $header = json_decode(base64_decode($tokenParts[0] . str_repeat('=', (4 - strlen($tokenParts[0]) % 4) % 4)), true);

            $key = collect($appleKeys['keys'])->firstWhere('kid', $header['kid']);
            if (!$key) return null;

            $pemKey = $this->convertJWKToPEM($key);
            $payload = JWT::decode($token, new Key($pemKey, 'RS256'));

            if ($payload->aud !== config('services.apple.client_id')) {
                return null;
            }

            return [
                'id' => $payload->sub,
                'email' => property_exists($payload, 'email') ? $payload->email : null,
                'name' => property_exists($payload, 'name') ? ($payload->name->first_name ?? 'Apple User') : 'Apple User',
            ];

        } catch (\Exception $e) {
            Log::error('Apple token validation failed: ' . $e->getMessage());
            return null;
        }
    }

    private function convertJWKToPEM($key)
    {
        $n = base64_decode(strtr($key['n'], '-_', '+/'));
        $e = base64_decode(strtr($key['e'], '-_', '+/'));

        $modulus = pack('Ca*a*', 2, $this->encodeLength(strlen($n)), $n);
        $publicExponent = pack('Ca*a*', 2, $this->encodeLength(strlen($e)), $e);
        $rsa = pack('Ca*a*a*', 48, $this->encodeLength(strlen($modulus) + strlen($publicExponent)), $modulus, $publicExponent);
        $rsaEncoded = base64_encode($rsa);

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split($rsaEncoded, 64) . "-----END PUBLIC KEY-----\n";
    }

    private function encodeLength($length)
    {
        if ($length <= 0x7F) {
            return chr($length);
        }
        $temp = ltrim(pack('N', $length), chr(0));
        return pack('Ca*', 0x80 | strlen($temp), $temp);
    }

    private function findOrCreateUser($userData, $provider)
    {
        $providerId = $provider === 'google' ? 'google_id' : 'apple_id';

        $user = User::where($providerId, $userData['id'])->first();

        if (!$user && isset($userData['email'])) {
            $user = User::where('email', $userData['email'])->first();
            if ($user) {
                $user->update([$providerId => $userData['id']]);
            }
        }

        if (!$user) {
            $user = User::create([
                'name' => $userData['name'] ?? 'User',
                'email' => $userData['email'],
                'password' => bcrypt('123456'),
                $providerId => $userData['id'],
            ]);
        }

        return $user;
    }
}
