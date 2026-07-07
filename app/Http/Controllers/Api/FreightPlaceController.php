<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchPlacesRequest;
use App\Models\Freight;
use App\Services\PlaceSearchService;
use Illuminate\Http\JsonResponse;

class FreightPlaceController extends Controller
{
    public function __construct(
        protected PlaceSearchService $placeSearchService,
    ) {}

    public function search(SearchPlacesRequest $request, Freight $freight): JsonResponse
    {
        $this->authorize('view', $freight);

        $places = $this->placeSearchService->searchNearFreight($freight, $request->validated());

        return response()->json([
            'data' => $places,
        ]);
    }
}
