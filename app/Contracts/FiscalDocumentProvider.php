<?php

namespace App\Contracts;

use App\Models\Freight;

/**
 * Contrato para emissão de documentos fiscais de transporte (CT-e).
 *
 * Implementação padrão: microserviço Java {@see truckflow-fiscal}.
 */
interface FiscalDocumentProvider
{
    /**
     * Solicita emissão de CT-e para um frete concluído.
     *
     * @param  array<string, mixed>  $fiscalSettings  Dados fiscais do tenant (CNPJ, IE, etc.).
     * @return array{
     *     access_key: string,
     *     protocol_number: string,
     *     status: string,
     *     xml_content: string,
     *     pdf_base64: string,
     *     message?: string
     * }
     */
    public function emitCte(Freight $freight, array $fiscalSettings): array;

    /**
     * Consulta status de um CT-e pela chave de acesso.
     *
     * @return array{access_key: string, status: string, protocol_number?: string, message?: string}
     */
    public function consultCte(string $accessKey): array;

    /**
     * Solicita cancelamento de CT-e autorizado.
     *
     * @return array{access_key: string, status: string, protocol_number?: string, message?: string}
     */
    public function cancelCte(string $accessKey, string $reason): array;
}
