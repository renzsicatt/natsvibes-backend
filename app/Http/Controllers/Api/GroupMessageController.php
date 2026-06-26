<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GroupMessage;
use App\Models\Hangout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupMessageController extends Controller
{
    public function index(Request $request, Hangout $hangout): JsonResponse
    {
        $this->assertMember($request, $hangout);
        $messages = $hangout->messages()->with('sender.profile')->latest('id')->cursorPaginate(50);

        return response()->json(['data' => $messages]);
    }

    public function store(Request $request, Hangout $hangout): JsonResponse
    {
        $this->assertMember($request, $hangout);
        abort_if(in_array($hangout->status, ['cancelled', 'completed'], true), 409);
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $message = GroupMessage::create(['hangout_id' => $hangout->id, 'sender_id' => $request->user()->id, 'message_text' => $validated['body'], 'type' => 'message']);

        return response()->json(['data' => $message->load('sender.profile')], 201);
    }

    public function announcement(Request $request, Hangout $hangout): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $hangout->host_id === $request->user()->id, 403);
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $message = GroupMessage::create(['hangout_id' => $hangout->id, 'sender_id' => $request->user()->id, 'message_text' => $validated['body'], 'type' => 'announcement']);

        return response()->json(['data' => $message], 201);
    }

    private function assertMember(Request $request, Hangout $hangout): void
    {
        abort_unless($request->user()->isAdmin() || $hangout->activeMembers()->whereKey($request->user()->id)->exists(), 403);
    }
}
