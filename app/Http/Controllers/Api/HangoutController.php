<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hangout;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HangoutController extends Controller
{
    /**
     * Display a listing of the active hangouts.
     */
    public function index(): JsonResponse
    {
        // Load relationships: host (user), venue, and members (users)
        $hangouts = Hangout::with(['host.profile', 'venue', 'members'])->get();
        
        // Transform the collection to match the exact API signature required by the mobile app
        $formatted = $hangouts->map(function ($hangout) {
            return [
                'id' => $hangout->id,
                'title' => $hangout->title,
                'area' => $hangout->area,
                'date_time' => $hangout->date_time->format('Y-m-d H:i:s'),
                'group_size_limit' => $hangout->group_size_limit,
                'members_count' => $hangout->members->count() + 1, // members + host
                'budget_range' => $hangout->budget_range,
                'description' => $hangout->description,
                'status' => $hangout->status,
                'venue' => [
                    'id' => $hangout->venue->id,
                    'name' => $hangout->venue->name,
                    'area' => $hangout->venue->area,
                    'address' => $hangout->venue->address,
                    'venue_type' => $hangout->venue->venue_type,
                    'price_range' => $hangout->venue->price_range,
                ],
                'host' => [
                    'name' => $hangout->host->name,
                    'age' => $hangout->host->profile ? $hangout->host->profile->age : 25,
                    'city' => $hangout->host->profile ? $hangout->host->profile->city : 'Makati',
                    'bio' => $hangout->host->profile ? $hangout->host->profile->bio : '',
                    'avatar_url' => $hangout->host->profile ? $hangout->host->profile->avatar_url : 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&q=80',
                    'is_verified' => true,
                    'vibe_tags' => ['Casual', 'Nightlife']
                ],
                'vibe_tags' => ['Drinks', 'Social'],
                'members' => array_merge([$hangout->host->name], $hangout->members->pluck('name')->toArray())
            ];
        });

        return response()->json($formatted);
    }

    /**
     * Store a newly created hangout.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'venue_id' => 'required|exists:venues,id',
            'date_time' => 'required|string',
            'area' => 'required|string',
            'description' => 'nullable|string',
            'group_size_limit' => 'integer|min:3|max:10',
            'budget_range' => 'string',
            'host_id' => 'required|exists:users,id'
        ]);

        $timestamp = strtotime($validated['date_time']);
        if (!$timestamp) {
            return response()->json([
                'message' => 'The date time field must be a valid date or relative time (e.g., "Saturday, 9:00 PM").'
            ], 422);
        }
        $formattedDateTime = date('Y-m-d H:i:s', $timestamp);

        $hangout = Hangout::create([
            'title' => $validated['title'],
            'venue_id' => $validated['venue_id'],
            'date_time' => $formattedDateTime,
            'area' => $validated['area'],
            'description' => $validated['description'] ?? null,
            'group_size_limit' => $validated['group_size_limit'] ?? 6,
            'budget_range' => $validated['budget_range'] ?? '$$',
            'host_id' => $validated['host_id'],
            'status' => 'open'
        ]);

        return response()->json($hangout, 201);
    }

    /**
     * Display a specific hangout details.
     */
    public function show($id): JsonResponse
    {
        $hangout = Hangout::with(['host.profile', 'venue', 'members'])->findOrFail($id);
        
        $formatted = [
            'id' => $hangout->id,
            'title' => $hangout->title,
            'area' => $hangout->area,
            'date_time' => $hangout->date_time->format('Y-m-d H:i:s'),
            'group_size_limit' => $hangout->group_size_limit,
            'members_count' => $hangout->members->count() + 1,
            'budget_range' => $hangout->budget_range,
            'description' => $hangout->description,
            'status' => $hangout->status,
            'venue' => [
                'id' => $hangout->venue->id,
                'name' => $hangout->venue->name,
                'area' => $hangout->venue->area,
                'address' => $hangout->venue->address,
                'venue_type' => $hangout->venue->venue_type,
                'price_range' => $hangout->venue->price_range,
            ],
            'host' => [
                'name' => $hangout->host->name,
                'age' => $hangout->host->profile ? $hangout->host->profile->age : 25,
                'city' => $hangout->host->profile ? $hangout->host->profile->city : 'Makati',
                'bio' => $hangout->host->profile ? $hangout->host->profile->bio : '',
                'avatar_url' => $hangout->host->profile ? $hangout->host->profile->avatar_url : 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&q=80',
                'is_verified' => true,
                'vibe_tags' => ['Casual', 'Nightlife']
            ],
            'vibe_tags' => ['Drinks', 'Social'],
            'members' => array_merge([$hangout->host->name], $hangout->members->pluck('name')->toArray())
        ];

        return response()->json($formatted);
    }

    /**
     * Get hangouts the authenticated user hosts or is a member of.
     */
    public function myHangouts(Request $request): JsonResponse
    {
        $user = $request->user();

        $hangouts = Hangout::with(['host.profile', 'venue', 'members.profile'])
            ->where('host_id', $user->id)
            ->orWhereHas('members', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->latest()
            ->get();

        $formatted = $hangouts->map(function ($hangout) {
            return [
                'id' => $hangout->id,
                'title' => $hangout->title,
                'venue_name' => $hangout->venue->name,
                'members_names' => array_merge([$hangout->host->name], $hangout->members->pluck('name')->toArray()),
                'time_summary' => $hangout->date_time->format('g:i A')
            ];
        });

        return response()->json($formatted);
    }
}
