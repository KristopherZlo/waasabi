<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminSupportTicketResponseRequest;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;

class AdminSupportController extends Controller
{
    public function respond(AdminSupportTicketResponseRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $moderator = $request->user();
        if (!$moderator) {
            return redirect()->route('login');
        }
        if (!safeHasTable('support_tickets')) {
            abort(503);
        }

        $data = $request->validated();

        $response = trim((string) ($data['response'] ?? ''));
        $status = (string) $data['status'];
        if ($response !== '' && $status === 'open') {
            $status = 'waiting';
        }

        $updates = [
            'status' => $status,
        ];

        if ($response !== '') {
            $updates['response'] = $response;
            $updates['responded_at'] = now();
            $updates['responded_by'] = $moderator->id;

            $meta = is_array($ticket->meta) ? $ticket->meta : [];
            $messages = is_array($meta['messages'] ?? null) ? $meta['messages'] : [];
            $messages[] = [
                'author_type' => 'support',
                'author_id' => $moderator->id,
                'author_name' => $moderator->name ?? __('ui.support.portal_support_team'),
                'body' => $response,
                'created_at' => now()->toDateTimeString(),
            ];
            $meta['messages'] = $messages;
            $updates['meta'] = $meta;
        }

        if ($status === 'closed') {
            $updates['resolved_at'] = now();
            $updates['resolved_by'] = $moderator->id;
        } else {
            $updates['resolved_at'] = null;
            $updates['resolved_by'] = null;
        }

        $ticket->fill($updates)->save();

        if ($response !== '' && $ticket->user) {
            $ticket->user->sendNotification(
                __('ui.support.title'),
                __('ui.support.notification_support_reply', ['id' => $ticket->id, 'subject' => $ticket->subject]),
                route('support', ['tab' => 'tickets', 'ticket' => $ticket->id]),
            );
        }

        logModerationAction(
            $request,
            $moderator,
            $response !== '' ? 'support_reply' : 'support_status',
            'support_ticket',
            (string) ($ticket->id ?? ''),
            null,
            $response !== '' ? $response : null,
            [
                'subject' => $ticket->subject,
                'status' => $status,
                'ticket_user' => $ticket->user?->name,
            ],
        );

        return redirect()->back()->with('toast', __('ui.admin.support_ticket_saved'));
    }
}
