<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrailerRequest;
use App\Http\Requests\UpdateTrailerRequest;
use App\Http\Resources\TrailerResource;
use App\Models\Trailer;
use App\Services\TrailerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TrailerController extends Controller
{
    public function __construct(
        protected TrailerService $trailerService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Trailer::class);

        $trailers = Trailer::with('driver')
            ->latest()
            ->paginate(15);

        return TrailerResource::collection($trailers);
    }

    public function store(StoreTrailerRequest $request): JsonResponse
    {
        $this->authorize('create', Trailer::class);

        $trailer = $this->trailerService->create($request->validated());

        return response()->json([
            'data'    => TrailerResource::make($trailer),
            'message' => 'Reboque registrado com sucesso!',
        ], 201);
    }

    public function show(Trailer $trailer): JsonResponse
    {
        $this->authorize('view', $trailer);

        $trailer->load(['driver', 'freights']);

        return response()->json([
            'data' => TrailerResource::make($trailer),
        ]);
    }

    public function update(UpdateTrailerRequest $request, Trailer $trailer): JsonResponse
    {
        $this->authorize('update', $trailer);

        $trailer = $this->trailerService->update($trailer, $request->validated());

        return response()->json([
            'data'    => TrailerResource::make($trailer),
            'message' => 'Reboque atualizado com sucesso!',
        ]);
    }

    public function destroy(Trailer $trailer): JsonResponse
    {
        $this->authorize('delete', $trailer);

        $trailer->delete();

        return response()->json([
            'message' => 'Reboque excluído com sucesso!',
        ]);
    }
}
