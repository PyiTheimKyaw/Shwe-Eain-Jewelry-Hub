<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ArtisanProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = User::query()->orderByDesc('id');

        if ($request->filled('role')) {
            $q->where('role', $request->string('role'));
        }

        return response()->json($q->paginate(30));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', 'in:admin,artisan'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::from($data['role']),
        ]);

        if ($user->role === UserRole::Artisan) {
            ArtisanProfile::create([
                'user_id' => $user->id,
                'is_active' => true,
            ]);
        }

        return response()->json(['data' => $user], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['name'])) {
            $user->update(['name' => $data['name']]);
        }

        if ($user->role === UserRole::Artisan && isset($data['is_active'])) {
            $user->artisanProfile?->update(['is_active' => $data['is_active']]);
        }

        $user->load('artisanProfile');

        return response()->json(['data' => $user]);
    }
}
