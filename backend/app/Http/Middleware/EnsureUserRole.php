<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * @param  string  ...$roles  admin, artisan, customer
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $allowed = array_map(fn (string $r) => UserRole::from($r), $roles);

        foreach ($allowed as $role) {
            if ($user->role === $role) {
                return $next($request);
            }
        }

        abort(403, 'Insufficient permissions.');
    }
}
