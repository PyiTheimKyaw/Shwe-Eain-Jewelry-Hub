<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Inquiry::query()->with(['customer', 'attachments'])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        return response()->json($q->paginate(30));
    }

    public function show(Inquiry $inquiry): JsonResponse
    {
        $inquiry->load(['customer', 'attachments', 'quotes']);

        return response()->json(['data' => $inquiry]);
    }

    public function update(Request $request, Inquiry $inquiry): JsonResponse
    {
        $this->authorize('update', $inquiry);

        $data = $request->validate([
            'status' => ['sometimes', 'in:open,reviewing,quoted,closed'],
            'notes_internal' => ['nullable', 'string', 'max:10000'],
        ]);

        $inquiry->update($data);

        return response()->json(['data' => $inquiry]);
    }
}
