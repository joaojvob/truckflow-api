<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelCteRequest;
use App\Http\Resources\FreightFiscalDocumentResource;
use App\Models\Freight;
use App\Models\FreightFiscalDocument;
use App\Services\DocumentStorageService;
use App\Services\FiscalDocumentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FreightFiscalController extends Controller
{
    public function __construct(
        protected FiscalDocumentService $fiscalDocumentService,
        protected DocumentStorageService $documentStorage,
    ) {}

    /**
     * Lista documentos fiscais de um frete.
     * GET /freights/{freight}/fiscal-documents
     */
    public function index(Freight $freight): JsonResponse
    {
        $this->authorize('viewFiscal', $freight);

        $documents = $this->fiscalDocumentService->listForFreight($freight);

        return response()->json([
            'data' => FreightFiscalDocumentResource::collection($documents),
        ]);
    }

    /**
     * Emite CT-e para frete concluído.
     * POST /freights/{freight}/fiscal-documents/cte
     */
    public function emitCte(Freight $freight): JsonResponse
    {
        $this->authorize('emitFiscal', $freight);

        $document = $this->fiscalDocumentService->emitCte($freight);

        return response()->json([
            'data'    => FreightFiscalDocumentResource::make($document),
            'message' => 'CT-e emitido com sucesso!',
        ], 201);
    }

    /**
     * Detalhes de um documento fiscal.
     * GET /freights/{freight}/fiscal-documents/{fiscalDocument}
     */
    public function show(Freight $freight, FreightFiscalDocument $fiscalDocument): JsonResponse
    {
        $this->authorize('viewFiscal', $freight);
        $this->ensureDocumentBelongsToFreight($freight, $fiscalDocument);

        $fiscalDocument->load('creator');

        return response()->json([
            'data' => FreightFiscalDocumentResource::make($fiscalDocument),
        ]);
    }

    /**
     * Cancela CT-e autorizado.
     * POST /freights/{freight}/fiscal-documents/{fiscalDocument}/cancel
     */
    public function cancel(CancelCteRequest $request, Freight $freight, FreightFiscalDocument $fiscalDocument): JsonResponse
    {
        $this->authorize('emitFiscal', $freight);
        $this->ensureDocumentBelongsToFreight($freight, $fiscalDocument);

        $document = $this->fiscalDocumentService->cancelCte(
            $fiscalDocument,
            $request->validated('reason'),
        );

        return response()->json([
            'data'    => FreightFiscalDocumentResource::make($document),
            'message' => 'CT-e cancelado com sucesso.',
        ]);
    }

    /**
     * Download do XML do CT-e.
     * GET /freights/{freight}/fiscal-documents/{fiscalDocument}/xml
     */
    public function downloadXml(Freight $freight, FreightFiscalDocument $fiscalDocument): StreamedResponse
    {
        $this->authorize('viewFiscal', $freight);
        $this->ensureDocumentBelongsToFreight($freight, $fiscalDocument);

        return $this->documentStorage->download(
            $fiscalDocument->xml_path,
            "cte-{$fiscalDocument->access_key}.xml",
        );
    }

    /**
     * Download do DACTE (PDF).
     * GET /freights/{freight}/fiscal-documents/{fiscalDocument}/pdf
     */
    public function downloadPdf(Freight $freight, FreightFiscalDocument $fiscalDocument): StreamedResponse
    {
        $this->authorize('viewFiscal', $freight);
        $this->ensureDocumentBelongsToFreight($freight, $fiscalDocument);

        return $this->documentStorage->download(
            $fiscalDocument->pdf_path,
            "dacte-{$fiscalDocument->access_key}.pdf",
        );
    }

    private function ensureDocumentBelongsToFreight(Freight $freight, FreightFiscalDocument $fiscalDocument): void
    {
        abort_unless($fiscalDocument->freight_id === $freight->id, 404);
    }
}
