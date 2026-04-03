<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    /**
     * Ensure a Firebase thread id exists for chat security rules and Flutter streams.
     */
    public function firebaseBootstrap(Request $request, Inquiry $inquiry): JsonResponse
    {
        $this->authorize('view', $inquiry);

        if (! $inquiry->firebase_thread_id) {
            $inquiry->forceFill(['firebase_thread_id' => (string) Str::uuid()])->save();
        }

        return response()->json([
            'inquiry_id' => $inquiry->id,
            'firebase_thread_id' => $inquiry->firebase_thread_id,
        ]);
    }
}
