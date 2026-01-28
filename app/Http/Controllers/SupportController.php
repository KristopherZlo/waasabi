<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Services\SupportArticleService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    public function index(Request $request, SupportArticleService $supportArticleService)
    {
        $ticketsReady = safeHasTable('support_tickets');
        $user = Auth::user();
        $isBanned = false;
        if ($user && safeHasColumn('users', 'is_banned')) {
            $isBanned = (bool) $user->is_banned;
        }
        $isStaff = $user && $user->can('support');

        $supportTickets = collect();
        $threads = [];
        $activeTicket = null;
        if ($ticketsReady && ($user || $isStaff)) {
            $ticketQuery = SupportTicket::query()->with(['respondedBy', 'resolvedBy', 'user']);
            if (!$isStaff) {
                $ticketQuery->where('user_id', $user?->id ?? 0);
            }

            $supportTickets = $ticketQuery
                ->orderByDesc('created_at')
                ->get();

            $formatDate = static function ($value): string {
                if (!$value) {
                    return '';
                }
                try {
                    return Carbon::parse($value)->format('d.m.Y H:i');
                } catch (\Throwable $e) {
                    return (string) $value;
                }
            };

            $toTimestamp = static function ($value): ?int {
                if (!$value) {
                    return null;
                }
                try {
                    return Carbon::parse($value)->getTimestamp();
                } catch (\Throwable $e) {
                    return null;
                }
            };

            $threads = $supportTickets->mapWithKeys(function (SupportTicket $ticket) use ($formatDate, $toTimestamp) {
                $messages = [];
                $sequence = 0;
                $push = static function (array $payload) use (&$messages, &$sequence, $toTimestamp): void {
                    $sequence += 1;
                    $createdAtRaw = $payload['created_at_raw'] ?? null;
                    $sortValue = $toTimestamp($createdAtRaw);
                    $messages[] = array_merge($payload, [
                        '_sort' => $sortValue ?? PHP_INT_MAX,
                        '_seq' => $sequence,
                    ]);
                };

                $ownerName = $ticket->user?->name ?? __('ui.support.portal_guest');
                $push([
                    'author_type' => 'user',
                    'author_id' => $ticket->user_id,
                    'author_name' => $ownerName,
                    'body' => (string) ($ticket->body ?? ''),
                    'created_at' => $formatDate($ticket->created_at),
                    'created_at_raw' => $ticket->created_at,
                ]);

                $meta = is_array($ticket->meta) ? $ticket->meta : [];
                $metaMessages = is_array($meta['messages'] ?? null) ? $meta['messages'] : [];
                foreach ($metaMessages as $message) {
                    $body = trim((string) ($message['body'] ?? ''));
                    if ($body === '') {
                        continue;
                    }
                    $push([
                        'author_type' => (string) ($message['author_type'] ?? 'support'),
                        'author_id' => $message['author_id'] ?? null,
                        'author_name' => (string) ($message['author_name'] ?? ''),
                        'body' => $body,
                        'created_at' => $formatDate($message['created_at'] ?? null),
                        'created_at_raw' => $message['created_at'] ?? null,
                    ]);
                }

                $response = trim((string) ($ticket->response ?? ''));
                if ($response !== '') {
                    $responseAlreadyPresent = false;
                    foreach ($messages as $message) {
                        if (($message['author_type'] ?? '') === 'support' && trim((string) ($message['body'] ?? '')) === $response) {
                            $responseAlreadyPresent = true;
                            break;
                        }
                    }
                    if (!$responseAlreadyPresent) {
                        $push([
                            'author_type' => 'support',
                            'author_id' => $ticket->responded_by,
                            'author_name' => $ticket->respondedBy?->name ?? __('ui.support.portal_support_team'),
                            'body' => $response,
                            'created_at' => $formatDate($ticket->responded_at),
                            'created_at_raw' => $ticket->responded_at,
                        ]);
                    }
                }

                usort($messages, static fn ($a, $b) => ($a['_sort'] <=> $b['_sort']) ?: ($a['_seq'] <=> $b['_seq']));
                $messages = array_map(static function (array $message): array {
                    unset($message['_sort'], $message['_seq'], $message['created_at_raw']);
                    return $message;
                }, $messages);

                return [$ticket->id => $messages];
            })->all();

            $activeTicketId = (int) $request->query('ticket', 0);
            if ($activeTicketId > 0) {
                $activeTicket = $supportTickets->firstWhere('id', $activeTicketId);
            }
            if (!$activeTicket && $request->query('tab') === 'tickets' && $isStaff) {
                $activeTicket = $supportTickets->first();
            }
        }

        $supportArticleEntries = $supportArticleService->articleEntries();
        $supportKbSectionsResolved = $supportArticleService->buildSectionEntries(
            $supportArticleService->kbSections(),
            $supportArticleEntries,
        );
        $supportLegalArticles = $supportArticleService->filterEntriesByGroup($supportArticleEntries, 'legal');

        return view('support.index', [
            'support_tickets' => $supportTickets,
            'support_tickets_ready' => $ticketsReady,
            'support_can_open_ticket' => (bool) ($user && !$isBanned && $ticketsReady),
            'support_is_banned' => $isBanned,
            'support_is_staff' => (bool) $isStaff,
            'support_threads' => $threads,
            'support_active_ticket' => $activeTicket,
            'support_articles' => $supportArticleEntries,
            'support_kb_sections' => $supportKbSectionsResolved,
            'support_legal_articles' => $supportLegalArticles,
            'current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload(),
        ]);
    }

    public function kb(string $slug, SupportArticleService $supportArticleService)
    {
        $slug = Str::slug($slug);
        $article = $supportArticleService->findArticle($slug, 'kb');
        if (!$article) {
            abort(404);
        }

        [$title, $summary] = $supportArticleService->resolveTitleSummary($article);

        $markdownPath = $supportArticleService->resolveKnowledgePath($slug);
        if (!$markdownPath) {
            abort(404);
        }

        $supportArticleEntries = $supportArticleService->articleEntries();
        $supportKbSectionsResolved = $supportArticleService->buildSectionEntries(
            $supportArticleService->kbSections(),
            $supportArticleEntries,
        );
        $documentSection = $supportArticleService->findSectionTitleForSlug($supportKbSectionsResolved, $slug);

        return view('support.document', [
            'document_title' => $title,
            'document_summary' => $summary,
            'document_section' => $documentSection,
            'document_slug' => $slug,
            'document_back_url' => route('support', ['tab' => 'home']),
            'markdown_path' => $markdownPath,
            'document_nav_sections' => $supportKbSectionsResolved,
            'current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload(),
        ]);
    }

    public function docs(string $slug, SupportArticleService $supportArticleService)
    {
        $slug = Str::slug($slug);
        $article = $supportArticleService->findArticle($slug, 'legal');
        if (!$article) {
            abort(404);
        }

        [$title, $summary] = $supportArticleService->resolveTitleSummary($article);

        $markdownPath = $article['markdown'] ?? null;
        if (!$markdownPath || !file_exists(base_path($markdownPath))) {
            abort(404);
        }

        $supportArticleEntries = $supportArticleService->articleEntries();
        $supportLegalArticles = $supportArticleService->filterEntriesByGroup($supportArticleEntries, 'legal');
        $legalSectionTitle = trim((string) __('ui.support.sections.legal.title'));
        $legalSectionSummary = trim((string) __('ui.support.sections.legal.summary'));
        $documentNavSections = [
            [
                'id' => 'legal',
                'title' => $legalSectionTitle,
                'summary' => $legalSectionSummary,
                'items' => $supportLegalArticles,
                'count' => count($supportLegalArticles),
            ],
        ];

        return view('support.document', [
            'document_title' => $title,
            'document_summary' => $summary,
            'document_section' => $legalSectionTitle,
            'document_slug' => $slug,
            'document_back_url' => route('support', ['tab' => 'home']),
            'markdown_path' => $markdownPath,
            'document_nav_sections' => $documentNavSections,
            'current_user' => app(\App\Services\UserPayloadService::class)->currentUserPayload(),
        ]);
    }

    public function ticketNew()
    {
        return redirect()->route('support', ['tab' => 'new']);
    }
}
