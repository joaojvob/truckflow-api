<?php

namespace Tests\Unit\Enums;

use App\Enums\FreightStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FreightStatusTest extends TestCase
{
    #[DataProvider('allowedTransitionsProvider')]
    public function test_can_transition_to_allowed_status(FreightStatus $from, FreightStatus $to): void
    {
        $this->assertTrue($from->canTransitionTo($to));
    }

    #[DataProvider('forbiddenTransitionsProvider')]
    public function test_cannot_transition_to_forbidden_status(FreightStatus $from, FreightStatus $to): void
    {
        $this->assertFalse($from->canTransitionTo($to));
    }

    public function test_completed_and_cancelled_are_terminal_states(): void
    {
        foreach (FreightStatus::cases() as $target) {
            $this->assertFalse(FreightStatus::Completed->canTransitionTo($target));
            $this->assertFalse(FreightStatus::Cancelled->canTransitionTo($target));
        }
    }

    public static function allowedTransitionsProvider(): array
    {
        return [
            'pending to assigned'     => [FreightStatus::Pending, FreightStatus::Assigned],
            'assigned to accepted'    => [FreightStatus::Assigned, FreightStatus::Accepted],
            'accepted to ready'       => [FreightStatus::Accepted, FreightStatus::Ready],
            'ready to in_transit'     => [FreightStatus::Ready, FreightStatus::InTransit],
            'in_transit to completed' => [FreightStatus::InTransit, FreightStatus::Completed],
            'rejected to assigned'    => [FreightStatus::Rejected, FreightStatus::Assigned],
        ];
    }

    public static function forbiddenTransitionsProvider(): array
    {
        return [
            'pending to completed'   => [FreightStatus::Pending, FreightStatus::Completed],
            'assigned to in_transit' => [FreightStatus::Assigned, FreightStatus::InTransit],
            'ready to completed'     => [FreightStatus::Ready, FreightStatus::Completed],
        ];
    }
}
