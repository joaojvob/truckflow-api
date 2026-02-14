<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignDriverRequest;
use App\Http\Requests\RejectFreightRequest;
use App\Http\Requests\ReviewDopingTestRequest;
use App\Http\Requests\SubmitChecklistRequest;
use App\Http\Requests\SubmitDopingTestRequest;
use App\Http\Resources\FreightResource;
use App\Models\DopingTest;
use App\Models\Freight;
use App\Models\User;
use App\Services\FreightWorkflowService;
use Illuminate\Http\JsonResponse;

class FreightWorkflowController extends Controller
{
    public function __construct(
        protected FreightWorkflowService $workflowService,
    ) {}

    /**
     * Gestor atribui motorista ao frete.
     * POST /freights/{freight}/assign
     */
    public function assign(AssignDriverRequest $request, Freight $freight): JsonResponse
    {
        $this->authorize('update', $freight);

        $driver = User::findOrFail($request->validated('driver_id'));

        $freight = $this->workflowService->assignDriver($freight, $driver);

        return response()->json([
            'data'    => FreightResource::make($freight),
            'message' => 'Motorista atribuído ao frete com sucesso!',
        ]);
    }

    /**
     * Motorista aceita o frete.
     * POST /freights/{freight}/accept
     */
    public function accept(Freight $freight): JsonResponse
    {
        $this->authorize('respond', $freight);

        $freight = $this->workflowService->acceptFreight($freight);

        return response()->json([
            'data'    => FreightResource::make($freight),
            'message' => 'Frete aceito com sucesso!',
        ]);
    }

    /**
     * Motorista recusa o frete.
     * POST /freights/{freight}/reject
     */
    public function reject(RejectFreightRequest $request, Freight $freight): JsonResponse
    {
        $this->authorize('respond', $freight);

        $freight = $this->workflowService->rejectFreight($freight, $request->validated('reason'));

        return response()->json([
            'data'    => FreightResource::make($freight),
            'message' => 'Frete recusado.',
        ]);
    }

    /**
     * Motorista envia exame de doping.
     * POST /freights/{freight}/doping
     */
    public function submitDoping(SubmitDopingTestRequest $request, Freight $freight): JsonResponse
    {
        $this->authorize('respond', $freight);

        $filePath = $request->file('file')->store(
            "doping-tests/{$freight->tenant_id}",
            'private',
        );

        $dopingTest = $this->workflowService->submitDopingTest($freight, $filePath);

        return response()->json([
            'data'    => $dopingTest,
            'message' => 'Exame de doping enviado com sucesso!',
        ], 201);
    }

    /**
     * Gestor analisa o exame de doping.
     * POST /freights/{freight}/doping/{dopingTest}/review
     */
    public function reviewDoping(ReviewDopingTestRequest $request, Freight $freight, DopingTest $dopingTest): JsonResponse
    {
        $this->authorize('update', $freight);

        $dopingTest = $this->workflowService->reviewDopingTest(
            $dopingTest,
            $request->validated('approved'),
            $request->validated('notes'),
        );

        return response()->json([
            'data'    => $dopingTest,
            'message' => $dopingTest->isApproved() ? 'Doping aprovado!' : 'Doping reprovado.',
        ]);
    }

    /**
     * Motorista envia checklist pré-viagem.
     * POST /freights/{freight}/checklist
     */
    public function submitChecklist(SubmitChecklistRequest $request, Freight $freight): JsonResponse
    {
        $this->authorize('respond', $freight);

        $freight = $this->workflowService->submitChecklist($freight, $request->validated('items'));

        return response()->json([
            'data'    => FreightResource::make($freight),
            'message' => 'Checklist enviado com sucesso!',
        ]);
    }

    /**
     * Gestor libera a viagem.
     * POST /freights/{freight}/approve
     */
    public function approve(Freight $freight): JsonResponse
    {
        $this->authorize('update', $freight);

        $freight = $this->workflowService->approveTrip($freight);

        return response()->json([
            'data'    => FreightResource::make($freight),
            'message' => 'Viagem liberada com sucesso!',
        ]);
    }

    /**
     * Motorista inicia a viagem.
     * POST /freights/{freight}/start
     */
    public function start(Freight $freight): JsonResponse
    {
        $this->authorize('start', $freight);

        $freight = $this->workflowService->startTrip($freight);

        return response()->json([
            'data'    => FreightResource::make($freight),
            'message' => 'Viagem iniciada com sucesso!',
        ]);
    }

    /**
     * Motorista finaliza a viagem.
     * POST /freights/{freight}/complete
     */
    public function complete(Freight $freight): JsonResponse
    {
        $this->authorize('complete', $freight);

        $freight = $this->workflowService->completeTrip(
            $freight,
            request('rating'),
            request('notes'),
        );

        return response()->json([
            'data'    => FreightResource::make($freight),
            'message' => 'Viagem finalizada com sucesso!',
        ]);
    }
}
