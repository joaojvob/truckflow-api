<?php

namespace App\Services;

use App\Enums\SystemLogLevel;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Registro centralizado de erros e eventos técnicos do sistema.
 */
class SystemLogger
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'authorization',
        'secret',
        'api_key',
    ];

    /**
     * Persiste log estruturado e espelha no canal Monolog.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(
        SystemLogLevel $level,
        string $message,
        array $context = [],
        ?Throwable $exception = null,
        string $channel = 'app',
    ): SystemLog {
        $request = $this->currentRequest();
        $sanitized = $this->sanitize($context);

        $entry = SystemLog::withoutGlobalScopes()->create([
            'tenant_id'          => $context['tenant_id'] ?? $request?->user()?->tenant_id,
            'user_id'            => $context['user_id'] ?? $request?->user()?->id,
            'level'              => $level,
            'channel'            => $channel,
            'message'            => Str::limit($message, 500),
            'context'            => $sanitized ?: null,
            'exception_class'    => $exception ? $exception::class : null,
            'exception_message'  => $exception ? Str::limit($exception->getMessage(), 1000) : null,
            'trace'              => $this->shouldStoreTrace() && $exception
                ? Str::limit($exception->getTraceAsString(), 8000)
                : null,
            'request_id'         => $context['request_id'] ?? $request?->attributes->get('request_id'),
            'method'             => $request?->method(),
            'url'                => $request?->fullUrl(),
            'ip'                 => $request?->ip(),
        ]);

        Log::log($level->value, $message, array_filter([
            'channel'    => $channel,
            'context'    => $sanitized,
            'exception'  => $exception?->getMessage(),
            'request_id' => $entry->request_id,
        ]));

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $message, ?Throwable $exception = null, array $context = [], string $channel = 'app'): SystemLog
    {
        return $this->log(SystemLogLevel::Error, $message, $context, $exception, $channel);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $message, array $context = [], ?Throwable $exception = null, string $channel = 'app'): SystemLog
    {
        return $this->log(SystemLogLevel::Warning, $message, $context, $exception, $channel);
    }

    /**
     * Converte exceção em registro padronizado (usado pelo handler global).
     *
     * @param  array<string, mixed>  $context
     */
    public function fromException(Throwable $exception, array $context = []): SystemLog
    {
        $level = $this->resolveLevel($exception);

        return $this->log(
            level: $level,
            message: $exception->getMessage() ?: class_basename($exception),
            context: $context,
            exception: $exception,
            channel: $context['channel'] ?? 'exception',
        );
    }

    private function resolveLevel(Throwable $exception): SystemLogLevel
    {
        $code = method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500;

        return $code >= 500 ? SystemLogLevel::Error : SystemLogLevel::Warning;
    }

    private function shouldStoreTrace(): bool
    {
        return config('app.debug') || config('telemetry.log_trace');
    }

    private function currentRequest(): ?Request
    {
        return app()->bound('request') ? request() : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitize(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
