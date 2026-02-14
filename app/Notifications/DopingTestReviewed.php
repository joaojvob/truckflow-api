<?php

namespace App\Notifications;

use App\Models\DopingTest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DopingTestReviewed extends Notification
{
    use Queueable;

    public function __construct(
        protected DopingTest $dopingTest,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $status  = $this->dopingTest->isApproved() ? 'aprovado' : 'reprovado';
        $emoji   = $this->dopingTest->isApproved() ? '✅' : '❌';

        return [
            'type'        => 'doping_test_reviewed',
            'freight_id'  => $this->dopingTest->freight_id,
            'status'      => $this->dopingTest->status->value,
            'notes'       => $this->dopingTest->reviewer_notes,
            'message'     => "{$emoji} Seu exame de doping foi {$status}.",
        ];
    }
}
