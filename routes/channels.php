<?php

use App\Models\Freight;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenant.{tenantId}.freight.{freightId}', function (User $user, int $tenantId, int $freightId) {
    if ($user->tenant_id !== $tenantId) {
        return false;
    }

    $freight = Freight::query()->find($freightId);

    if (! $freight || $freight->tenant_id !== $tenantId) {
        return false;
    }

    if ($user->isAdmin()) {
        return true;
    }

    if ($user->isManager()) {
        return $freight->created_by === $user->id;
    }

    return $freight->driver_id === $user->id;
});
