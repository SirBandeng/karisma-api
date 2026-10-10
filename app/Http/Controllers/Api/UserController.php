<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $auth = $request->user();

        $users = User::where('role', 'petugas')
            ->when($auth->role === 'admin', fn($q) => $q->where('terminal_id', $auth->terminal_id))
            ->select('id', 'name', 'username', 'is_active', 'terminal_id', 'created_at')
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $auth = $request->user();

        $request->validate([
            'name'     => 'required|string|max:255',
            'username' => 'required|string|max:191|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name'        => $request->name,
            'username'    => $request->username,
            'password'    => Hash::make($request->password),
            'role'        => 'petugas',
            'is_active'   => true,
            'terminal_id' => $auth->terminal_id, // otomatis ikut terminal admin
        ]);

        return response()->json([
            'message' => 'Akun petugas berhasil dibuat.',
            'user'    => $user->only('id', 'name', 'username', 'role', 'is_active', 'terminal_id'),
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $auth = $request->user();

        // Admin hanya bisa update petugas di terminalnya sendiri
        if ($auth->role === 'admin' && $user->terminal_id !== $auth->terminal_id) {
            return response()->json(['message' => 'Unauthorized. Akses ditolak.'], 403);
        }

        $request->validate([
            'name'     => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:191|unique:users,username,' . $user->id,
            'password' => 'sometimes|string|min:6',
        ]);

        if ($request->filled('name'))     $user->name     = $request->name;
        if ($request->filled('username')) $user->username = $request->username;
        if ($request->filled('password')) $user->password = Hash::make($request->password);

        $user->save();

        return response()->json([
            'message' => 'Data petugas berhasil diperbarui.',
            'user'    => $user->only('id', 'name', 'username', 'role', 'is_active', 'terminal_id'),
        ]);
    }

    public function toggle(Request $request, User $user)
    {
        $auth = $request->user();

        // Admin hanya bisa toggle petugas di terminalnya sendiri
        if ($auth->role === 'admin' && $user->terminal_id !== $auth->terminal_id) {
            return response()->json(['message' => 'Unauthorized. Akses ditolak.'], 403);
        }

        if ($user->role !== 'petugas') {
            return response()->json(['message' => 'Akun ini tidak bisa dinonaktifkan.'], 403);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'message'   => 'Status akun berhasil diubah.',
            'is_active' => $user->is_active,
        ]);
    }
}
