<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hangout;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = $request->user()->favorites()->with('favoritable')->latest()->cursorPaginate(25);

        return response()->json(['data' => $items]);
    }

    public function favoriteVenue(Request $request, Venue $venue): JsonResponse
    {
        return $this->store($request, $venue);
    }

    public function favoriteHangout(Request $request, Hangout $hangout): JsonResponse
    {
        return $this->store($request, $hangout);
    }

    public function unfavoriteVenue(Request $request, Venue $venue): JsonResponse
    {
        return $this->destroy($request, $venue);
    }

    public function unfavoriteHangout(Request $request, Hangout $hangout): JsonResponse
    {
        return $this->destroy($request, $hangout);
    }

    private function store(Request $request, Model $model): JsonResponse
    {
        $favorite = $request->user()->favorites()->firstOrCreate([
            'favoritable_type' => $model->getMorphClass(), 'favoritable_id' => $model->getKey(),
        ]);

        return response()->json(['data' => $favorite], $favorite->wasRecentlyCreated ? 201 : 200);
    }

    private function destroy(Request $request, Model $model): JsonResponse
    {
        $request->user()->favorites()->whereMorphedTo('favoritable', $model)->delete();

        return response()->json(['data' => ['favorited' => false]]);
    }
}
