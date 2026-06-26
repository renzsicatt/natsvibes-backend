<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => Block::with('blocked.profile')->where('blocker_id', $request->user()->id)->latest()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['blocked_user_id' => ['required', 'integer', 'exists:users,id', 'not_in:'.$request->user()->id]]);
        $block = Block::firstOrCreate(['blocker_id' => $request->user()->id, 'blocked_id' => $validated['blocked_user_id']]);

        return response()->json(['data' => $block], $block->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, Block $block): JsonResponse
    {
        abort_unless($block->blocker_id === $request->user()->id, 403);
        $block->delete();

        return response()->json(null, 204);
    }
}
