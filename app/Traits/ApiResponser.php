<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

trait ApiResponser
{
    /**
     * Resposta de Sucesso.
     */
    protected function successResponse($data, $message = null, $code = 200): JsonResponse
    {
        return response()->json([
            'status'  => 'Success',
            'message' => $message,
            'data'    => $data
        ], $code);
    }

    /**
     * Resposta de Erro com Log automático.
     */
    protected function errorResponse(Throwable $exception, string $message = 'Erro na operação.', int $code = 422): JsonResponse
    {
        Log::error($message, [
            'exception' => $exception->getMessage(),
            'file'      => $exception->getFile(),
            'line'      => $exception->getLine(),
            'trace'     => $exception->getTrace(),
        ]);

        return response()->json([
            'status'  => 'Error',
            'message' => $message,
            'details' => config('app.debug') ? $exception->getMessage() : null,
        ], $code);
    }
}