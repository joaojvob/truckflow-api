<?php

namespace App\Services;

use App\Contracts\FiscalDocumentProvider;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\FreightStatus;
use App\Models\Freight;
use App\Models\FreightFiscalDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Orquestra emissão, consulta e cancelamento de CT-e vinculado a fretes concluídos.
 */
class FiscalDocumentService
{
    public function __construct(
        protected FiscalDocumentProvider $fiscalProvider,
        protected DocumentStorageService $documentStorage,
    ) {}

    /**
     * Emite CT-e para frete concluído via microserviço fiscal.
     */
    public function emitCte(Freight $freight): FreightFiscalDocument
    {
        if ($freight->status !== FreightStatus::Completed) {
            throw ValidationException::withMessages([
                'status' => 'CT-e só pode ser emitido para fretes concluídos.',
            ]);
        }

        if ($freight->fiscalDocuments()
            ->where('type', FiscalDocumentType::Cte)
            ->whereIn('status', [FiscalDocumentStatus::Processing, FiscalDocumentStatus::Authorized])
            ->exists()) {
            throw ValidationException::withMessages([
                'fiscal' => 'Já existe um CT-e em processamento ou autorizado para este frete.',
            ]);
        }

        $fiscalSettings = $this->resolveTenantFiscalSettings($freight);

        return DB::transaction(function () use ($freight, $fiscalSettings) {
            try {
                $result = $this->fiscalProvider->emitCte($freight, $fiscalSettings);
            } catch (ValidationException $exception) {
                throw $exception;
            }

            $xmlPath = $this->storeFiscalFile(
                content: $result['xml_content'],
                directory: "fiscal-documents/{$freight->tenant_id}/{$freight->id}/cte",
                filename: 'cte-'.$result['access_key'].'.xml',
                isBase64: false,
            );

            $pdfPath = $this->storeFiscalFile(
                content: $result['pdf_base64'],
                directory: "fiscal-documents/{$freight->tenant_id}/{$freight->id}/cte",
                filename: 'dacte-'.$result['access_key'].'.pdf',
                isBase64: true,
            );

            $status = FiscalDocumentStatus::tryFrom($result['status'] ?? '') ?? FiscalDocumentStatus::Authorized;

            $document = FreightFiscalDocument::create([
                'tenant_id'       => $freight->tenant_id,
                'freight_id'      => $freight->id,
                'created_by'      => auth()->id(),
                'type'            => FiscalDocumentType::Cte,
                'status'          => $status,
                'access_key'      => $result['access_key'],
                'protocol_number' => $result['protocol_number'] ?? null,
                'xml_path'        => $xmlPath,
                'pdf_path'        => $pdfPath,
                'payload'         => [
                    'message' => $result['message'] ?? null,
                    'issuer'  => $fiscalSettings,
                ],
                'authorized_at' => $status === FiscalDocumentStatus::Authorized ? now() : null,
            ]);

            $freight->recordActivity(
                action: 'cte_issued',
                description: "CT-e emitido para o frete: {$freight->cargo_name}",
                payload: [
                    'access_key'      => $document->access_key,
                    'protocol_number' => $document->protocol_number,
                ],
            );

            return $document->fresh(['freight', 'creator']);
        });
    }

    /**
     * Lista documentos fiscais de um frete.
     */
    public function listForFreight(Freight $freight): \Illuminate\Database\Eloquent\Collection
    {
        return $freight->fiscalDocuments()
            ->latest()
            ->get();
    }

    /**
     * Cancela CT-e autorizado.
     */
    public function cancelCte(FreightFiscalDocument $document, string $reason): FreightFiscalDocument
    {
        if ($document->type !== FiscalDocumentType::Cte) {
            throw ValidationException::withMessages([
                'fiscal' => 'Apenas CT-e pode ser cancelado por este endpoint.',
            ]);
        }

        if (! $document->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'Apenas CT-e autorizado pode ser cancelado.',
            ]);
        }

        $result = $this->fiscalProvider->cancelCte($document->access_key, $reason);

        $document->update([
            'status'          => FiscalDocumentStatus::Cancelled,
            'cancelled_at'    => now(),
            'protocol_number' => $result['protocol_number'] ?? $document->protocol_number,
            'payload'         => array_merge($document->payload ?? [], [
                'cancellation' => [
                    'reason'  => $reason,
                    'message' => $result['message'] ?? null,
                ],
            ]),
        ]);

        $document->freight->recordActivity(
            action: 'cte_cancelled',
            description: "CT-e cancelado: {$document->access_key}",
            payload: ['reason' => $reason],
        );

        return $document->fresh(['freight', 'creator']);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTenantFiscalSettings(Freight $freight): array
    {
        $freight->loadMissing('tenant');
        $fiscal = $freight->tenant?->settings['fiscal'] ?? null;

        if (! is_array($fiscal) || empty($fiscal['cnpj'])) {
            throw ValidationException::withMessages([
                'fiscal' => 'Configure os dados fiscais da empresa (CNPJ, IE, razão social) antes de emitir CT-e.',
            ]);
        }

        $required = ['cnpj', 'ie', 'razao_social', 'uf', 'municipio'];
        foreach ($required as $field) {
            if (empty($fiscal[$field])) {
                throw ValidationException::withMessages([
                    'fiscal' => "Campo fiscal obrigatório ausente: {$field}.",
                ]);
            }
        }

        return $fiscal;
    }

    private function storeFiscalFile(string $content, string $directory, string $filename, bool $isBase64): string
    {
        $binary = $isBase64 ? base64_decode($content, true) : $content;

        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                'fiscal' => 'Arquivo fiscal retornado pelo serviço está vazio.',
            ]);
        }

        $path = $directory.'/'.$filename;
        Storage::disk(DocumentStorageService::DISK)->put($path, $binary);

        return $path;
    }
}
