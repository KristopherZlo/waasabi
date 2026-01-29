<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Services\SupportStaffService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    public function store(Request $request, SupportStaffService $supportStaff)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!safeHasTable('support_tickets')) {
            abort(503);
        }

        $data = $request->validate([
            'kind' => ['required', Rule::in(['question', 'bug', 'complaint'])],
            'subject' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'min:20', 'max:8000'],
        ]);

        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'kind' => $data['kind'],
            'subject' => trim((string) $data['subject']),
            'body' => trim((string) $data['body']),
            'status' => 'open',
            'meta' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'locale' => app()->getLocale(),
            ],
        ]);

        $ticketId = (int) ($ticket->id ?? 0);
        $toast = $ticketId > 0
            ? __('ui.support.ticket_toast_created', ['id' => $ticketId])
            : __('ui.support.ticket_toast_created_generic');

        if ($ticketId > 0) {
            $supportStaff->notify(
                __('ui.support.title'),
                __('ui.support.notification_new_ticket', ['id' => $ticketId, 'subject' => $ticket->subject]),
                route('support', ['tab' => 'tickets', 'ticket' => $ticketId]),
                $user->id,
            );
        }

        return redirect()->route('support', ['tab' => 'tickets'])->with('toast', $toast);
    }

    public function storeMessage(Request $request, SupportTicket $ticket, SupportStaffService $supportStaff)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!safeHasTable('support_tickets')) {
            abort(503);
        }
        $isStaff = $user->can('support');
        if (!$isStaff && safeHasColumn('users', 'is_banned') && $user->is_banned) {
            abort(403);
        }
        if (!$isStaff && $ticket->user_id !== $user->id) {
            abort(403);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:4000'],
        ]);

        $messageBody = trim((string) $data['message']);
        if ($messageBody === '') {
            return redirect()->back();
        }

        $meta = is_array($ticket->meta) ? $ticket->meta : [];
        $messages = is_array($meta['messages'] ?? null) ? $meta['messages'] : [];
        $messages[] = [
            'author_type' => $isStaff ? 'support' : 'user',
            'author_id' => $user->id,
            'author_name' => $user->name ?? ($isStaff ? __('ui.support.portal_support_team') : __('ui.support.portal_you')),
            'body' => $messageBody,
            'created_at' => now()->toDateTimeString(),
        ];
        $meta['messages'] = $messages;

        $ticket->meta = $meta;
        if ($isStaff) {
            $ticket->status = 'waiting';
            $ticket->responded_at = now();
            $ticket->responded_by = $user->id;
            $ticket->response = $messageBody;
            $ticket->resolved_at = null;
            $ticket->resolved_by = null;
        } elseif ($ticket->status !== 'open') {
            $ticket->status = 'open';
            $ticket->resolved_at = null;
            $ticket->resolved_by = null;
        }
        $ticket->save();

        if ($isStaff) {
            if ($ticket->user && $ticket->user->id !== $user->id) {
                $ticket->user->sendNotification(
                    __('ui.support.title'),
                    __('ui.support.notification_support_reply', ['id' => $ticket->id, 'subject' => $ticket->subject]),
                    route('support', ['tab' => 'tickets', 'ticket' => $ticket->id]),
                );
            }
        } else {
            $supportStaff->notify(
                __('ui.support.title'),
                __('ui.support.notification_user_message', ['id' => $ticket->id, 'subject' => $ticket->subject]),
                route('support', ['tab' => 'tickets', 'ticket' => $ticket->id]),
                $user->id,
            );
        }

        return redirect()->route('support', ['tab' => 'tickets', 'ticket' => $ticket->id]);
    }
}
