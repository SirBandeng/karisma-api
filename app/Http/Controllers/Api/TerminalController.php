<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Terminal;
use Illuminate\Http\Request;

class TerminalController extends Controller
{
    // GET /api/terminals — list semua terminal
    public function index()
    {
        $terminals = Terminal::withCount('users')->get();

        return response()->json($terminals);
    }

    // POST /api/terminals — tambah terminal baru
    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|unique:terminals,name',
            'location' => 'nullable|string',
        ]);

        $terminal = Terminal::create([
            'name'      => $request->name,
            'location'  => $request->location,
            'is_active' => true,
        ]);

        return response()->json([
            'message'  => 'Terminal berhasil ditambahkan.',
            'terminal' => $terminal,
        ], 201);
    }

    // PUT /api/terminals/{terminal} — update terminal
    public function update(Request $request, Terminal $terminal)
    {
        $request->validate([
            'name'      => 'sometimes|string|unique:terminals,name,' . $terminal->id,
            'location'  => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $terminal->update($request->only(['name', 'location', 'is_active']));

        return response()->json([
            'message'  => 'Terminal berhasil diupdate.',
            'terminal' => $terminal,
        ]);
    }
}
