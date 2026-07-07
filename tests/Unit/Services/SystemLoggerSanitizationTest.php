<?php

namespace Tests\Unit\Services;

use App\Services\SystemLogger;
use Tests\TestCase;

class SystemLoggerSanitizationTest extends TestCase
{
    public function test_sensitive_fields_are_redacted_in_context(): void
    {
        $logger = app(SystemLogger::class);

        $entry = $logger->error('Teste de sanitização', null, [
            'password'   => 'secret123',
            'token'      => 'abc',
            'freight_id' => 42,
        ], 'test');

        $this->assertSame('[redacted]', $entry->context['password']);
        $this->assertSame('[redacted]', $entry->context['token']);
        $this->assertSame(42, $entry->context['freight_id']);
    }
}
