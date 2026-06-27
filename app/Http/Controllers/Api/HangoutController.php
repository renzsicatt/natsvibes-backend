<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hangout;
use App\Models\Venue;
use App\Notifications\ActivityNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HangoutController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'area' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'budget_max' => ['nullable', 'integer', 'min:0'],
            'vibe_tag' => ['nullable', 'integer', 'exists:vibe_tags,id'],
            'verified_host' => ['nullable', 'boolean'],
        ]);

        $query = Hangout::query()
            ->with(['host.profile', 'venue.photos', 'vibeTags'])
            ->withCount(['activeMembers as members_count'])
            ->withCount(['joinRequests as waitlist_count' => fn (Builder $query) => $query->where('status', 'waitlisted')])
            ->withExists(['favorites as is_favorited' => fn (Builder $q) => $q->where('user_id', $request->user()->id)])
            ->whereIn('status', ['open', 'full'])
            ->where('date_time', '>', now())
            ->when($validated['area'] ?? null, fn (Builder $q, string $area) => $q->where('area', $area))
            ->when($validated['from'] ?? null, fn (Builder $q, string $from) => $q->where('date_time', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $q, string $to) => $q->where('date_time', '<=', $to))
            ->when($validated['budget_max'] ?? null, fn (Builder $q, int $max) => $q->where('budget_min', '<=', $max))
            ->when($validated['vibe_tag'] ?? null, fn (Builder $q, int $tag) => $q->whereHas('vibeTags', fn (Builder $t) => $t->whereKey($tag)))
            ->when(($validated['verified_host'] ?? false), fn (Builder $q) => $q->whereHas('host.profile', fn (Builder $p) => $p->where('host_verification_status', 'approved')))
            ->orderBy('date_time');

        return response()->json(['data' => $query->cursorPaginate(20)]);
    }

    public function show(Request $request, Hangout $hangout): JsonResponse
    {
        abort_if(in_array($hangout->status, ['draft', 'flagged'], true) && ! $this->canManage($request, $hangout), 404);

        $hangout->load(['host.profile', 'venue.photos', 'vibeTags', 'activeMembers.profile']);
        $hangout->loadExists(['favorites as is_favorited' => fn (Builder $q) => $q->where('user_id', $request->user()->id)]);
        $data = $hangout->toArray();
        $isMember = $hangout->activeMembers->contains($request->user());
        if (! $isMember && ! $request->user()->isAdmin()) {
            unset($data['host_notes']);
            $data['members'] = [];
        }

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->canHost(), 403, 'Host verification is required.');
        $validated = $this->validateHangout($request);
        $venue = Venue::whereKey($validated['venue_id'])->whereIn('status', ['listed', 'verified', 'featured', 'active'])->firstOrFail();

        $hangout = DB::transaction(function () use ($request, $validated, $venue): Hangout {
            $hangout = Hangout::create([
                ...collect($validated)->except('vibe_tag_ids')->all(),
                'host_id' => $request->user()->id,
                'area' => $venue->area,
                'date_time' => $validated['scheduled_at'],
                'request_cutoff_at' => $validated['request_cutoff_at'] ?? now()->parse($validated['scheduled_at'])->subHours(2),
                'status' => 'open',
                'invite_code' => Str::lower(Str::random(12)),
            ]);
            $hangout->members()->attach($request->user()->id, ['role' => 'host', 'status' => 'active', 'joined_at' => now()]);
            $hangout->vibeTags()->sync($validated['vibe_tag_ids'] ?? []);

            return $hangout;
        });

        return response()->json(['data' => $hangout->load(['venue', 'vibeTags', 'activeMembers'])], 201);
    }

    public function update(Request $request, Hangout $hangout): JsonResponse
    {
        abort_unless($this->canManage($request, $hangout), 403);
        abort_if(in_array($hangout->status, ['cancelled', 'completed'], true), 409, 'Terminal hangouts cannot be updated.');
        $validated = $this->validateHangout($request, true);
        $hangout->update(collect($validated)->except(['vibe_tag_ids', 'scheduled_at'])->all() +
            (isset($validated['scheduled_at']) ? ['date_time' => $validated['scheduled_at']] : []));
        if (array_key_exists('vibe_tag_ids', $validated)) {
            $hangout->vibeTags()->sync($validated['vibe_tag_ids']);
        }

        return response()->json(['data' => $hangout->fresh(['venue', 'vibeTags'])]);
    }

    public function cancel(Request $request, Hangout $hangout): JsonResponse
    {
        abort_unless($this->canManage($request, $hangout), 403);
        abort_if(in_array($hangout->status, ['cancelled', 'completed'], true), 409);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $hangout->update(['previous_status' => $hangout->status, 'status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $validated['reason']]);
        $hangout->activeMembers()->whereKeyNot($request->user()->id)->each(fn ($member) => $member->notify(new ActivityNotification('hangout_cancelled', ['hangout_id' => $hangout->id])));

        return response()->json(['data' => $hangout]);
    }

    public function complete(Request $request, Hangout $hangout): JsonResponse
    {
        abort_unless($this->canManage($request, $hangout), 403);
        abort_unless(in_array($hangout->status, ['ongoing', 'open', 'full'], true) && $hangout->date_time->isPast(), 409);
        $hangout->update(['status' => 'completed']);

        return response()->json(['data' => $hangout]);
    }

    public function myHangouts(Request $request): JsonResponse
    {
        $items = Hangout::with(['venue', 'vibeTags'])->whereHas('activeMembers', fn (Builder $q) => $q->whereKey($request->user()->id))->latest('date_time')->cursorPaginate(20);

        return response()->json(['data' => $items]);
    }

    public function invite(Request $request, string $code): JsonResponse
    {
        $hangout = Hangout::with(['host.profile', 'venue.photos', 'vibeTags'])
            ->withCount(['activeMembers as members_count'])->where('invite_code', $code)->firstOrFail();
        abort_if(in_array($hangout->status, ['draft', 'flagged'], true) && ! $this->canManage($request, $hangout), 404);

        return response()->json(['data' => ['hangout' => $hangout, 'share_url' => 'natsvibe://hangouts/'.$hangout->invite_code]]);
    }

    private function validateHangout(Request $request, bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$sometimes, 'string', 'max:255'],
            'venue_id' => [$sometimes, 'integer', 'exists:venues,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'rules' => ['nullable', 'string', 'max:2000'],
            'host_notes' => ['nullable', 'string', 'max:2000'],
            'scheduled_at' => [$sometimes, 'date', 'after_or_equal:'.now()->addHours(2)->toIso8601String(), 'before_or_equal:'.now()->addDays(60)->toIso8601String()],
            'request_cutoff_at' => ['nullable', 'date', 'before:scheduled_at'],
            'timezone' => ['sometimes', 'timezone:all'],
            'group_size_limit' => ['sometimes', 'integer', 'min:3', 'max:10'],
            'budget_min' => ['nullable', 'integer', 'min:0'],
            'budget_max' => ['nullable', 'integer', 'gte:budget_min'],
            'currency' => ['sometimes', Rule::in(['PHP'])],
            'vibe_tag_ids' => ['sometimes', 'array', 'max:10'],
            'vibe_tag_ids.*' => ['integer', 'exists:vibe_tags,id'],
        ]);
    }

    private function canManage(Request $request, Hangout $hangout): bool
    {
        return $request->user()->isAdmin() || $hangout->host_id === $request->user()->id;
    }
}
