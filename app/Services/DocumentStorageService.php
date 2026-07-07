<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Centraliza operações de armazenamento de documentos sensíveis (CNH, CRLV, doping).
 *
 * Todos os arquivos são persistidos no disco privado, isolados por tenant no path.
 */
class DocumentStorageService
{
    /** Disco Laravel usado para documentos privados. */
    public const DISK = 'private';

    public function __construct(
        protected SystemLogger $systemLogger,
    ) {}

    /**
     * Armazena um arquivo enviado no diretório informado.
     *
     * @param  UploadedFile  $file  Arquivo validado pelo Form Request.
     * @param  string  $directory  Pasta relativa dentro do disco (ex.: driver-documents/1/5/cnh).
     * @return string Caminho relativo gerado pelo Laravel Storage.
     *
     * @throws RuntimeException Se a gravação falhar.
     */
    public function store(UploadedFile $file, string $directory): string
    {
        try {
            $path = $file->store($directory, self::DISK);
        } catch (\Throwable $exception) {
            $this->systemLogger->error(
                'Falha ao armazenar documento.',
                $exception,
                ['channel' => 'storage', 'directory' => $directory],
                'storage',
            );

            throw new RuntimeException('Não foi possível salvar o documento.', 0, $exception);
        }

        if ($path === false) {
            $this->systemLogger->warning('Storage retornou path vazio.', ['channel' => 'storage', 'directory' => $directory], null, 'storage');

            throw new RuntimeException('Não foi possível salvar o documento.');
        }

        return $path;
    }

    /**
     * Substitui um arquivo existente: grava o novo e remove o anterior, se houver.
     *
     * @param  UploadedFile  $file  Novo arquivo.
     * @param  string  $directory  Pasta de destino.
     * @param  string|null  $oldPath  Caminho do arquivo anterior a ser removido.
     * @return string Caminho do novo arquivo.
     */
    public function replace(UploadedFile $file, string $directory, ?string $oldPath): string
    {
        $path = $this->store($file, $directory);
        $this->delete($oldPath);

        return $path;
    }

    /**
     * Remove um arquivo do disco privado, ignorando paths nulos ou inexistentes.
     *
     * @param  string|null  $path  Caminho relativo no storage.
     */
    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        try {
            if (Storage::disk(self::DISK)->exists($path)) {
                Storage::disk(self::DISK)->delete($path);
            }
        } catch (\Throwable $exception) {
            $this->systemLogger->warning(
                'Falha ao remover documento do storage.',
                ['channel' => 'storage', 'path' => $path],
                $exception,
                'storage',
            );
        }
    }

    /**
     * Retorna resposta HTTP de download do arquivo.
     *
     * @param  string|null  $path  Caminho relativo no storage.
     * @param  string  $filename  Nome sugerido ao navegador (ex.: cnh-5.pdf).
     * @return StreamedResponse
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 404 se o arquivo não existir.
     */
    public function download(?string $path, string $filename): StreamedResponse
    {
        abort_unless($path && Storage::disk(self::DISK)->exists($path), 404, 'Documento não encontrado.');

        return Storage::disk(self::DISK)->download($path, $filename);
    }

    /**
     * Verifica se o caminho aponta para um arquivo existente no disco privado.
     *
     * @param  string|null  $path  Caminho relativo no storage.
     */
    public function has(?string $path): bool
    {
        return $path && Storage::disk(self::DISK)->exists($path);
    }
}
