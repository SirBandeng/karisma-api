<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    // Hirarki role — role lebih tinggi bisa akses route role di bawahnya
    protected array $hierarchy = [
        'superadmin' => ['superadmin', 'admin', 'petugas'],
        'admin'      => ['admin', 'petugas'],
        'petugas'    => ['petugas'],
    ];

    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $allowedRoles = $this->hierarchy[$user->role] ?? [];

        if (!in_array($role, $allowedRoles)) {
            return response()->json([
                'message' => 'Unauthorized. Akses ditolak.'
            ], 403);
        }

        return $next($request);
    }
}
