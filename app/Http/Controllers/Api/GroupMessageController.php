<?php

namespace App\Http\Controllers\Api;

use App\Events\GroupMessageCreated;
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
        $messages = $hangout->messages()->with(['sender.profile', 'replyTo.sender', 'reactions'])->latest('id')->cursorPaginate(50);

        return response()->json(['data' => $messages]);
    }

    public function store(Request $request, Hangout $hangout): JsonResponse
    {
        $this->assertMember($request, $hangout);
        abort_if(in_array($hangout->status, ['cancelled', 'completed'], true), 409);
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'reply_to_id' => ['nullable', 'integer', 'exists:group_messages,id'],
        ]);
        if (isset($validated['reply_to_id'])) {
            abort_unless(GroupMessage::whereKey($validated['reply_to_id'])->where('hangout_id', $hangout->id)->exists(), 422, 'Reply target must be in this hangout.');
        }
        $message = GroupMessage::create(['hangout_id' => $hangout->id, 'sender_id' => $request->user()->id, 'message_text' => $validated['body'], 'reply_to_id' => $validated['reply_to_id'] ?? null, 'type' => 'message']);
        GroupMessageCreated::dispatch($message);

        return response()->json(['data' => $message->load(['sender.profile', 'replyTo.sender', 'reactions'])], 201);
    }

    public function announcement(Request $request, Hangout $hangout): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $hangout->host_id === $request->user()->id, 403);
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $message = GroupMessage::create(['hangout_id' => $hangout->id, 'sender_id' => $request->user()->id, 'message_text' => $validated['body'], 'type' => 'announcement']);
        GroupMessageCreated::dispatch($message);

        return response()->json(['data' => $message], 201);
    }

    public function update(Request $request, GroupMessage $message): JsonResponse
    {
        abort_unless($message->sender_id === $request->user()->id, 403);
        abort_unless($message->type === 'message' && $message->created_at->isAfter(now()->subMinutes(15)), 409, 'Messages can only be edited for 15 minutes.');
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $message->update(['message_text' => $validated['body'], 'edited_at' => now()]);
        GroupMessageCreated::dispatch($message);

        return response()->json(['data' => $message->fresh(['sender.profile', 'replyTo.sender', 'reactions'])]);
    }

    public function destroy(Request $request, GroupMessage $message): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $message->sender_id === $request->user()->id, 403);
        $message->update(['deleted_by' => $request->user()->id]);
        if ($request->user()->isAdmin()) {
            DB::table('admin_actions')->insert([
                'admin_id' => $request->user()->id, 'action_type' => 'message_removed',
                'details' => json_encode(['hangout_id' => $message->hangout_id, 'sender_id' => $message->sender_id]),
                'target_type' => GroupMessage::class, 'target_id' => $message->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $message->delete();

        return response()->json(null, 204);
    }

    public function react(Request $request, GroupMessage $message): JsonResponse
    {
        $this->assertMember($request, $message->hangout);
        $validated = $request->validate(['emoji' => ['required', 'string', 'max:16', 'in:👍,❤️,😂,😮,😢,🔥']]);
        $existing = $message->reactions()->where(['user_id' => $request->user()->id, 'emoji' => $validated['emoji']])->first();
        if ($existing) {
            $existing->delete();
        } else {
            $message->reactions()->create(['user_id' => $request->user()->id, 'emoji' => $validated['emoji']]);
        }

        return response()->json(['data' => $message->reactions()->get()]);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $query = GroupMessage::withTrashed()->with(['sender', 'hangout'])->latest();
        if ($request->boolean('reported_only')) {
            $query->whereNotNull('reported_at');
        }

        return response()->json(['data' => $query->cursorPaginate(50)]);
    }

    private function assertMember(Request $request, Hangout $hangout): void
    {
        abort_unless($request->user()->isAdmin() || $hangout->activeMembers()->whereKey($request->user()->id)->exists(), 403);
    }
}
