<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Services\FirebaseInquiryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

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

    /**
     * Mint a Firebase custom token so the client can sign in to Firestore chat.
     */
    public function firebaseToken(Request $request, Inquiry $inquiry, FirebaseInquiryService $firebase): JsonResponse
    {
        $this->authorize('view', $inquiry);

        if (! $firebase->isConfigured()) {
            return response()->json([
                'message' => 'Firebase is not configured on the server. Set FIREBASE_CREDENTIALS in .env.',
            ], 503);
        }

        try {
            $token = $firebase->mintChatToken($request->user(), $inquiry);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 503);
        }

        return response()->json([
            'firebase_token' => $token,
            'firebase_thread_id' => $inquiry->firebase_thread_id,
            'auth_uid' => 'laravel_'.$request->user()->id,
        ]);
    }
}
