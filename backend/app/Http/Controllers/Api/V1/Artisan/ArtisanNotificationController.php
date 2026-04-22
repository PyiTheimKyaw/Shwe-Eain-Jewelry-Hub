<?php

namespace App\Http\Controllers\Api\V1\Artisan;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArtisanNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = $request->user()
            ->notifications()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $items]);
    }
}
