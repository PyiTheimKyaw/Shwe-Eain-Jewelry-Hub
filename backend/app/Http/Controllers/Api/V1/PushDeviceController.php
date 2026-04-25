<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushDeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'max:32'],
            'app_version' => ['nullable', 'string', 'max:64'],
        ]);

        $device = PushDeviceToken::query()->updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => (string) ($data['platform'] ?? 'unknown'),
                'app_version' => $data['app_version'] ?? null,
                'is_active' => true,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['data' => $device], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        PushDeviceToken::query()
            ->where('token', $data['token'])
            ->where('user_id', $request->user()->id)
            ->update(['is_active' => false, 'last_seen_at' => now()]);

        return response()->json(['message' => 'Push token deactivated.']);
    }
}
