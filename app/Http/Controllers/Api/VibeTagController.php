<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VibeTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VibeTagController extends Controller
{
    /**
     * Display a listing of vibe tags.
     */
    public function index(): JsonResponse
    {
        $tags = VibeTag::all()->pluck('name');

        return response()->json($tags);
    }

    /**
     * Store a newly created vibe tag.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:vibe_tags,name|max:255',
        ]);

        $tag = VibeTag::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
        ]);

        return response()->json($tag->name, 201);
    }
}
