<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hangout;
use App\Models\GroupMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GroupMessageController extends Controller
{
    /**
     * Fetch messages for a specific hangout.
     */
    public function index(Request $request, $hangoutId): JsonResponse
    {
        $user = $request->user();
        
        // Ensure user is host or member of the hangout
        $hangout = Hangout::findOrFail($hangoutId);
        if ($hangout->host_id !== $user->id && !$hangout->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Unauthorized to access this hangout chat.'], 403);
        }

        $messages = GroupMessage::with('sender')
            ->where('hangout_id', $hangoutId)
            ->orderBy('created_at', 'asc')
            ->get();

        $formatted = $messages->map(function ($msg) use ($user) {
            return [
                'id' => $msg->id,
                'sender' => $msg->sender->name,
                'text' => $msg->message_text,
                'time' => $msg->created_at->timezone('Asia/Manila')->format('g:i A'),
                'isMe' => (int) $msg->sender_id === (int) $user->id
            ];
        });

        return response()->json($formatted);
    }

    /**
     * Send a new message to a specific hangout.
     */
    public function store(Request $request, $hangoutId): JsonResponse
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'message_text' => 'required|string|max:2000'
        ]);

        $hangout = Hangout::findOrFail($hangoutId);
        if ($hangout->host_id !== $user->id && !$hangout->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Unauthorized to send messages to this hangout chat.'], 403);
        }

        $msg = GroupMessage::create([
            'hangout_id' => $hangoutId,
            'sender_id' => $user->id,
            'message_text' => $validated['message_text']
        ]);

        $formatted = [
            'id' => $msg->id,
            'sender' => $user->name,
            'text' => $msg->message_text,
            'time' => $msg->created_at->timezone('Asia/Manila')->format('g:i A'),
            'isMe' => true
        ];

        return response()->json($formatted, 201);
    }
}
