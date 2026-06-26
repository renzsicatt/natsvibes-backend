<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\VenueTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VenueController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Venue::with(['tags', 'photos'])->whereIn('status', ['listed', 'verified', 'featured', 'active']);
        if ($request->filled('area')) {
            $query->where('area', $request->string('area'));
        }
        if ($request->filled('venue_type')) {
            $query->where('venue_type', $request->string('venue_type'));
        }
        if ($request->filled('budget_max')) {
            $query->where('budget_min', '<=', $request->integer('budget_max'));
        }

        return response()->json(['data' => $query->orderByDesc('is_featured')->orderBy('name')->cursorPaginate(25)]);
    }

    public function show(Venue $venue): JsonResponse
    {
        abort_unless(in_array($venue->status, ['listed', 'verified', 'featured', 'active'], true), 404);

        return response()->json(['data' => $venue->load(['tags', 'photos'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $venue = DB::transaction(function () use ($validated): Venue {
            $venue = Venue::create($this->attributes($validated));
            $this->syncTags($venue, $validated['tags'] ?? []);

            return $venue;
        });

        return response()->json(['data' => $venue->load('tags')], 201);
    }

    public function update(Request $request, Venue $venue): JsonResponse
    {
        $validated = $this->validated($request, true);
        DB::transaction(function () use ($venue, $validated): void {
            $venue->update($this->attributes($validated, true));
            if (array_key_exists('tags', $validated)) {
                $this->syncTags($venue, $validated['tags']);
            }
        });

        return response()->json(['data' => $venue->fresh('tags')]);
    }

    public function destroy(Venue $venue): JsonResponse
    {
        $venue->update(['status' => 'archived']);
        $venue->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'],
            'area' => [$required, 'string', 'max:100'], 'city' => ['sometimes', 'string', 'max:100'],
            'address' => [$required, 'string', 'max:1000'], 'google_maps_url' => ['nullable', 'url:http,https'],
            'instagram_url' => ['nullable', 'url:http,https'], 'venue_type' => [$required, 'string', 'max:100'],
            'budget_min' => ['nullable', 'integer', 'min:0'], 'budget_max' => ['nullable', 'integer', 'gte:budget_min'],
            'opening_hours' => ['nullable', 'array'], 'reservation_required' => ['sometimes', 'boolean'],
            'reservation_notes' => ['nullable', 'string', 'max:2000'], 'group_capacity_min' => ['nullable', 'integer', 'min:1'],
            'group_capacity_max' => ['nullable', 'integer', 'gte:group_capacity_min'],
            'status' => ['sometimes', Rule::in(['draft', 'listed', 'verified', 'featured', 'archived', 'closed', 'active'])],
            'is_verified' => ['sometimes', 'boolean'], 'is_featured' => ['sometimes', 'boolean'],
            'tags' => ['sometimes', 'array', 'max:20'], 'tags.*' => ['string', 'max:80'],
        ]);
    }

    private function attributes(array $data, bool $partial = false): array
    {
        $attributes = collect($data)->except('tags')->all();
        if (isset($data['name'])) {
            $attributes['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(6));
        }
        if (isset($data['google_maps_url'])) {
            $attributes['maps_link'] = $data['google_maps_url'];
        }
        $attributes['price_range'] ??= '$$';
        if (! $partial) {
            $attributes['status'] ??= 'draft';
        }

        return $attributes;
    }

    private function syncTags(Venue $venue, array $names): void
    {
        $ids = collect($names)->filter()->map(fn (string $name) => VenueTag::firstOrCreate(['slug' => Str::slug($name)], ['name' => trim($name)])->id);
        $venue->tags()->sync($ids);
    }
}
