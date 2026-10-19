<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyNote;
use Illuminate\Http\Request;

class DailyNoteController extends Controller
{
    public function show(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        $note = DailyNote::where('date', $request->query('date'))
            ->where('terminal_id', $request->user()->terminal_id)
            ->first();

        return response()->json([
            'note' => $note ? [
                'id'         => $note->id,
                'date'       => $note->date,
                'note'       => $note->note,
                'updated_at' => $note->updated_at,
                'updated_by' => $note->updater?->name ?? $note->creator->name,
            ] : null,
        ]);
    }

    public function upsert(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'note' => 'required|string|max:1000',
        ]);

        $terminalId = $request->user()->terminal_id;
        $userId     = $request->user()->id;

        $note = DailyNote::where('date', $request->date)
            ->where('terminal_id', $terminalId)
            ->first();

        if ($note) {
            $note->update([
                'note'       => $request->note,
                'updated_by' => $userId,
            ]);
        } else {
            $note = DailyNote::create([
                'date'        => $request->date,
                'terminal_id' => $terminalId,
                'note'        => $request->note,
                'created_by'  => $userId,
                'updated_by'  => null,
            ]);
        }

        return response()->json([
            'message' => 'Catatan berhasil disimpan.',
            'note'    => [
                'id'         => $note->id,
                'date'       => $note->date,
                'note'       => $note->note,
                'updated_at' => $note->updated_at,
                'updated_by' => $note->updater?->name ?? $note->creator->name,
            ],
        ]);
    }

    public function destroy(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        DailyNote::where('date', $request->query('date'))
            ->where('terminal_id', $request->user()->terminal_id)
            ->delete();

        return response()->json(['message' => 'Catatan berhasil dihapus.']);
    }
}
