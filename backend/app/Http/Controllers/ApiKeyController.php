<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Services\EncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ApiKeyController extends Controller
{
    public function __construct(
        private EncryptionService $encryption
    ) {}

    public function index(Request $request): JsonResponse
    {
        $apiKeys = $request->user()->apiKeys()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($key) {
                $decrypted = $this->encryption->decrypt($key->key_encrypted);
                return [
                    'id' => $key->id,
                    'service' => $key->service,
                    'name' => $key->name,
                    'key_masked' => $decrypted ? $this->encryption->mask($decrypted) : '****',
                    'credits_remaining' => $key->credits_remaining,
                    'is_active' => $key->is_active,
                    'last_used_at' => $key->last_used_at,
                    'created_at' => $key->created_at,
                ];
            });

        return response()->json(['api_keys' => $apiKeys]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service' => ['required', 'string', 'in:openrouter,kie'],
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string'],
        ]);

        $apiKey = $request->user()->apiKeys()->create([
            'service' => $validated['service'],
            'name' => $validated['name'],
            'key_encrypted' => $this->encryption->encrypt($validated['key']),
        ]);

        return response()->json([
            'message' => 'API key created successfully',
            'api_key' => [
                'id' => $apiKey->id,
                'service' => $apiKey->service,
                'name' => $apiKey->name,
                'key_masked' => $this->encryption->mask($validated['key']),
                'is_active' => $apiKey->is_active,
                'created_at' => $apiKey->created_at,
            ],
        ], 201);
    }

    public function update(Request $request, ApiKey $apiKey): JsonResponse
    {
        $this->authorize('update', $apiKey);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'key' => ['sometimes', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['key'])) {
            $validated['key_encrypted'] = $this->encryption->encrypt($validated['key']);
            unset($validated['key']);
        }

        $apiKey->update($validated);

        $decrypted = $this->encryption->decrypt($apiKey->key_encrypted);

        return response()->json([
            'message' => 'API key updated successfully',
            'api_key' => [
                'id' => $apiKey->id,
                'service' => $apiKey->service,
                'name' => $apiKey->name,
                'key_masked' => $decrypted ? $this->encryption->mask($decrypted) : '****',
                'is_active' => $apiKey->is_active,
                'updated_at' => $apiKey->updated_at,
            ],
        ]);
    }

    public function destroy(ApiKey $apiKey): JsonResponse
    {
        $this->authorize('delete', $apiKey);

        $apiKey->delete();

        return response()->json([
            'message' => 'API key deleted successfully',
        ]);
    }

    public function test(ApiKey $apiKey): JsonResponse
    {
        $this->authorize('view', $apiKey);

        $decryptedKey = $this->encryption->decrypt($apiKey->key_encrypted);

        if (!$decryptedKey) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to decrypt API key',
            ], 500);
        }

        $result = match ($apiKey->service) {
            'openrouter' => $this->testOpenRouterApiKey($decryptedKey, $apiKey),
            'kie' => $this->testKieApiKey($decryptedKey, $apiKey),
            default => ['success' => false, 'message' => 'Unknown service'],
        };

        // Return appropriate HTTP status based on test result
        $statusCode = $result['success'] ? 200 : 400;
        return response()->json($result, $statusCode);
    }

    private function testKieApiKey(string $key, ApiKey $apiKey): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $key,
            ])->get('https://api.kie.ai/api/v1/chat/credit');

            if ($response->successful()) {
                $data = $response->json();

                // kie.ai returns credits as integer in data field directly
                // Response: {"code": 200, "msg": "success", "data": 100}
                $credits = $data['data'] ?? 0;

                $apiKey->update([
                    'credits_remaining' => (int) $credits,
                    'last_used_at' => now(),
                ]);

                return [
                    'success' => true,
                    'message' => 'API key is valid',
                    'credits' => (int) $credits,
                ];
            }

            return [
                'success' => false,
                'message' => 'API key validation failed',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ];
        }
    }

    private function testOpenRouterApiKey(string $key, ApiKey $apiKey): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $key,
            ])->get('https://openrouter.ai/api/v1/auth/key');

            if ($response->successful()) {
                $data = $response->json();

                // Update credits if available
                $credits = $data['data']['limit_remaining'] ?? $data['data']['usage'] ?? null;
                if ($credits !== null) {
                    $apiKey->update([
                        'credits_remaining' => (int) $credits,
                        'last_used_at' => now(),
                    ]);
                } else {
                    $apiKey->update(['last_used_at' => now()]);
                }

                return [
                    'success' => true,
                    'message' => 'OpenRouter API key is valid',
                    'credits' => $credits,
                    'label' => $data['data']['label'] ?? null,
                ];
            }

            $error = $response->json();
            return [
                'success' => false,
                'message' => $error['error']['message'] ?? 'API key validation failed',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ];
        }
    }
}
