<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Chamados de suporte: motoristas/gestores abrem tickets; admin/super respondem.
 */
class SupportTicketController extends Controller
{
    /**
     * GET /support/tickets
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SupportTicket::query()
            ->with('user')
            ->withCount('messages')
            ->latest();

        // Motorista e gestor veem apenas os próprios chamados; admin/super veem todos (do tenant/contexto).
        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json([
            'data' => SupportTicketResource::collection($query->get()),
        ]);
    }

    /**
     * POST /support/tickets
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject'  => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'in:general,technical,billing,fiscal,freight'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'message'  => ['required', 'string', 'max:5000'],
        ]);

        $ticket = DB::transaction(function () use ($request, $validated) {
            $ticket = SupportTicket::create([
                'user_id'       => $request->user()->id,
                'subject'       => $validated['subject'],
                'category'      => $validated['category'] ?? 'general',
                'priority'      => $validated['priority'] ?? 'normal',
                'status'        => 'open',
                'last_reply_at' => now(),
            ]);

            $ticket->messages()->create([
                'user_id'  => $request->user()->id,
                'body'     => $validated['message'],
                'is_staff' => false,
            ]);

            return $ticket;
        });

        return response()->json([
            'data'    => SupportTicketResource::make($ticket->load(['user', 'messages.user'])),
            'message' => 'Chamado aberto com sucesso.',
        ], 201);
    }

    /**
     * GET /support/tickets/{ticket}
     */
    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeAccess($request, $ticket);

        return response()->json([
            'data' => SupportTicketResource::make($ticket->load(['user', 'messages.user'])),
        ]);
    }

    /**
     * POST /support/tickets/{ticket}/reply
     */
    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeAccess($request, $ticket);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $isStaff = $request->user()->isAdmin();

        $ticket->messages()->create([
            'user_id'  => $request->user()->id,
            'body'     => $validated['message'],
            'is_staff' => $isStaff,
        ]);

        $ticket->update([
            'status'        => $isStaff ? 'answered' : 'open',
            'last_reply_at' => now(),
        ]);

        return response()->json([
            'data'    => SupportTicketResource::make($ticket->fresh(['user', 'messages.user'])),
            'message' => 'Resposta enviada.',
        ]);
    }

    /**
     * POST /support/tickets/{ticket}/close
     */
    public function close(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeAccess($request, $ticket);

        $ticket->update([
            'status'    => 'closed',
            'closed_at' => now(),
        ]);

        return response()->json([
            'data'    => SupportTicketResource::make($ticket->fresh(['user', 'messages.user'])),
            'message' => 'Chamado encerrado.',
        ]);
    }

    private function authorizeAccess(Request $request, SupportTicket $ticket): void
    {
        $user = $request->user();

        if (! $user->isAdmin() && $ticket->user_id !== $user->id) {
            abort(403, 'Você não tem acesso a este chamado.');
        }
    }
}
