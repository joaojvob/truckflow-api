<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * Lista as notificações do usuário autenticado.
     * GET /notifications
     */
    public function index(): JsonResponse
    {
        $notifications = auth()->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $notifications]);
    }

    /**
     * Lista apenas as não lidas.
     * GET /notifications/unread
     */
    public function unread(): JsonResponse
    {
        $notifications = auth()->user()
            ->unreadNotifications()
            ->latest()
            ->paginate(20);

        return response()->json([
            'data'  => $notifications,
            'count' => auth()->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Marca uma notificação como lida.
     * POST /notifications/{id}/read
     */
    public function markAsRead(string $id): JsonResponse
    {
        $notification = auth()->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notificação marcada como lida.',
        ]);
    }

    /**
     * Marca todas as notificações como lidas.
     * POST /notifications/read-all
     */
    public function markAllAsRead(): JsonResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'Todas as notificações foram marcadas como lidas.',
        ]);
    }
}
