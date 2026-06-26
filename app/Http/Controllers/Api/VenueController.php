<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\VenueTag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class VenueController extends Controller
{
    /**
     * Display a listing of the active venues.
     */
    public function index(): JsonResponse
    {
        $venues = Venue::with('tags')->get();
        
        $formatted = $venues->map(function ($venue) {
            $array = $venue->toArray();
            $array['vibe_tags'] = $venue->tags->pluck('name')->toArray();
            $array['reservation_required'] = (bool) $venue->reservation_required;
            return $array;
        });

        return response()->json($formatted);
    }

    /**
     * Store a newly created venue in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'area' => 'required|string|max:255',
            'address' => 'required|string',
            'maps_link' => 'nullable|string',
            'venue_type' => 'required|string|max:255',
            'price_range' => 'required|string|in:$,$$,$$$',
            'reservation_required' => 'boolean',
            'vibe_tags' => 'nullable|array',
        ]);

        $venue = Venue::create([
            'name' => $validated['name'],
            'area' => $validated['area'],
            'address' => $validated['address'],
            'maps_link' => $validated['maps_link'] ?? null,
            'venue_type' => $validated['venue_type'],
            'price_range' => $validated['price_range'],
            'reservation_required' => $validated['reservation_required'] ?? false,
            'status' => 'active'
        ]);

        if (!empty($validated['vibe_tags'])) {
            $tagIds = [];
            foreach ($validated['vibe_tags'] as $tagName) {
                if (empty(trim($tagName))) continue;
                $tag = VenueTag::firstOrCreate(
                    ['name' => trim($tagName)],
                    ['slug' => Str::slug(trim($tagName))]
                );
                $tagIds[] = $tag->id;
            }
            $venue->tags()->sync($tagIds);
        }

        $venue->load('tags');
        $response = $venue->toArray();
        $response['vibe_tags'] = $venue->tags->pluck('name')->toArray();
        $response['reservation_required'] = (bool) $venue->reservation_required;

        return response()->json($response, 201);
    }

    /**
     * Display the specified venue.
     */
    public function show(Venue $venue): JsonResponse
    {
        $venue->load('tags');
        $response = $venue->toArray();
        $response['vibe_tags'] = $venue->tags->pluck('name')->toArray();
        $response['reservation_required'] = (bool) $venue->reservation_required;

        return response()->json($response);
    }

    /**
     * Update the specified venue in storage.
     */
    public function update(Request $request, Venue $venue): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'area' => 'string|max:255',
            'address' => 'string',
            'maps_link' => 'nullable|string',
            'venue_type' => 'string|max:255',
            'price_range' => 'string|in:$,$$,$$$',
            'reservation_required' => 'boolean',
            'status' => 'string|in:active,inactive',
            'vibe_tags' => 'nullable|array',
        ]);

        $venue->update($validated);

        if (isset($validated['vibe_tags'])) {
            $tagIds = [];
            foreach ($validated['vibe_tags'] as $tagName) {
                if (empty(trim($tagName))) continue;
                $tag = VenueTag::firstOrCreate(
                    ['name' => trim($tagName)],
                    ['slug' => Str::slug(trim($tagName))]
                );
                $tagIds[] = $tag->id;
            }
            $venue->tags()->sync($tagIds);
        }

        $venue->load('tags');
        $response = $venue->toArray();
        $response['vibe_tags'] = $venue->tags->pluck('name')->toArray();
        $response['reservation_required'] = (bool) $venue->reservation_required;

        return response()->json($response);
    }

    /**
     * Remove the specified venue from storage.
     */
    public function destroy(Venue $venue): JsonResponse
    {
        $venue->delete();
        return response()->json(['message' => 'Venue deleted successfully']);
    }
}
