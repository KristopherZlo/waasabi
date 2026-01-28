<?php

namespace App\Services;

use Illuminate\Support\Str;

class SupportArticleService
{
    public function articles(): array
    {
        return [
            [
                'id' => 'support-terms',
                'group' => 'legal',
                'slug' => 'terms-of-service',
                'title' => 'ui.support.articles.terms.title',
                'summary' => 'ui.support.articles.terms.summary',
                'path' => '/support/docs/terms-of-service',
                'markdown' => 'docs/legal/published/TERMS_OF_SERVICE.md',
                'tags' => ['terms', 'tos', 'rules'],
            ],
            [
                'id' => 'support-privacy',
                'group' => 'legal',
                'slug' => 'privacy-policy',
                'title' => 'ui.support.articles.privacy.title',
                'summary' => 'ui.support.articles.privacy.summary',
                'path' => '/support/docs/privacy-policy',
                'markdown' => 'docs/legal/published/PRIVACY_POLICY.md',
                'tags' => ['privacy', 'data', 'policy'],
            ],
            [
                'id' => 'support-cookies',
                'group' => 'legal',
                'slug' => 'cookie-policy',
                'title' => 'ui.support.articles.cookies.title',
                'summary' => 'ui.support.articles.cookies.summary',
                'path' => '/support/docs/cookie-policy',
                'markdown' => 'docs/legal/published/COOKIE_POLICY.md',
                'tags' => ['cookies', 'tracking'],
            ],
            [
                'id' => 'support-guidelines',
                'group' => 'legal',
                'slug' => 'community-guidelines',
                'title' => 'ui.support.articles.guidelines.title',
                'summary' => 'ui.support.articles.guidelines.summary',
                'path' => '/support/docs/community-guidelines',
                'markdown' => 'docs/legal/published/COMMUNITY_GUIDELINES.md',
                'tags' => ['community', 'rules', 'moderation'],
            ],
            [
                'id' => 'support-notice',
                'group' => 'legal',
                'slug' => 'notice-and-action',
                'title' => 'ui.support.articles.notice.title',
                'summary' => 'ui.support.articles.notice.summary',
                'path' => '/support/docs/notice-and-action',
                'markdown' => 'docs/legal/published/NOTICE_AND_ACTION.md',
                'tags' => ['moderation', 'complaints', 'reports'],
            ],
            [
                'id' => 'support-legal',
                'group' => 'legal',
                'slug' => 'legal-notice',
                'title' => 'ui.support.articles.legal.title',
                'summary' => 'ui.support.articles.legal.summary',
                'path' => '/support/docs/legal-notice',
                'markdown' => 'docs/legal/published/LEGAL_NOTICE.md',
                'tags' => ['legal', 'company', 'notice'],
            ],
            [
                'id' => 'support-ticket',
                'group' => 'support',
                'title' => 'ui.support.articles.ticket.title',
                'summary' => 'ui.support.articles.ticket.summary',
                'path' => '/support?tab=new',
                'tags' => ['support', 'tickets', 'chat'],
            ],
            [
                'id' => 'support-kb-roles',
                'group' => 'kb',
                'slug' => 'roles-badges-progression',
                'title' => 'ui.support.articles.roles_badges_progression.title',
                'summary' => 'ui.support.articles.roles_badges_progression.summary',
                'path' => '/support/kb/roles-badges-progression',
                'tags' => ['roles', 'badges', 'progression', 'maker', 'support', 'moderator', 'admin'],
            ],
            [
                'id' => 'support-kb-posts',
                'group' => 'kb',
                'slug' => 'posts-and-questions',
                'title' => 'ui.support.articles.posts_questions.title',
                'summary' => 'ui.support.articles.posts_questions.summary',
                'path' => '/support/kb/posts-and-questions',
                'tags' => ['posts', 'questions', 'publish', 'writing', 'editor', 'tags'],
            ],
            [
                'id' => 'support-kb-feedback',
                'group' => 'kb',
                'slug' => 'feedback-and-upvotes',
                'title' => 'ui.support.articles.feedback.title',
                'summary' => 'ui.support.articles.feedback.summary',
                'path' => '/support/kb/feedback-and-upvotes',
                'tags' => ['comments', 'reviews', 'upvotes', 'feedback'],
            ],
            [
                'id' => 'support-kb-feed',
                'group' => 'kb',
                'slug' => 'feed-and-discovery',
                'title' => 'ui.support.articles.feed_discovery.title',
                'summary' => 'ui.support.articles.feed_discovery.summary',
                'path' => '/support/kb/feed-and-discovery',
                'tags' => ['feed', 'filters', 'top-projects', 'search', 'tags', 'showcase'],
            ],
            [
                'id' => 'support-kb-notifications',
                'group' => 'kb',
                'slug' => 'notifications-and-read-later',
                'title' => 'ui.support.articles.notifications_read_later.title',
                'summary' => 'ui.support.articles.notifications_read_later.summary',
                'path' => '/support/kb/notifications-and-read-later',
                'tags' => ['notifications', 'read-later', 'alerts', 'saves'],
            ],
            [
                'id' => 'support-kb-settings',
                'group' => 'kb',
                'slug' => 'profile-settings-and-theme',
                'title' => 'ui.support.articles.profile_settings.title',
                'summary' => 'ui.support.articles.profile_settings.summary',
                'path' => '/support/kb/profile-settings-and-theme',
                'tags' => ['profile', 'settings', 'theme', 'privacy', 'notifications'],
            ],
            [
                'id' => 'support-kb-moderation',
                'group' => 'kb',
                'slug' => 'moderation-reports-bans',
                'title' => 'ui.support.articles.moderation_reports_bans.title',
                'summary' => 'ui.support.articles.moderation_reports_bans.summary',
                'path' => '/support/kb/moderation-reports-bans',
                'tags' => ['moderation', 'reports', 'bans', 'rules', 'nsfw'],
            ],
            [
                'id' => 'support-kb-service',
                'group' => 'kb',
                'slug' => 'support-service',
                'title' => 'ui.support.articles.support_service.title',
                'summary' => 'ui.support.articles.support_service.summary',
                'path' => '/support/kb/support-service',
                'tags' => ['support', 'tickets', 'help', 'contact'],
            ],
        ];
    }

    public function kbSections(): array
    {
        return [
            [
                'id' => 'account',
                'title' => 'ui.support.sections.account.title',
                'summary' => 'ui.support.sections.account.summary',
                'items' => [
                    'roles-badges-progression',
                    'profile-settings-and-theme',
                ],
            ],
            [
                'id' => 'publishing',
                'title' => 'ui.support.sections.publishing.title',
                'summary' => 'ui.support.sections.publishing.summary',
                'items' => [
                    'posts-and-questions',
                    'feedback-and-upvotes',
                    'feed-and-discovery',
                ],
            ],
            [
                'id' => 'safety',
                'title' => 'ui.support.sections.safety.title',
                'summary' => 'ui.support.sections.safety.summary',
                'items' => [
                    'moderation-reports-bans',
                ],
            ],
            [
                'id' => 'notifications',
                'title' => 'ui.support.sections.notifications.title',
                'summary' => 'ui.support.sections.notifications.summary',
                'items' => [
                    'notifications-and-read-later',
                ],
            ],
            [
                'id' => 'support',
                'title' => 'ui.support.sections.support.title',
                'summary' => 'ui.support.sections.support.summary',
                'items' => [
                    'support-service',
                ],
            ],
        ];
    }

    public function findArticle(string $slug, string $group): ?array
    {
        $slug = Str::slug($slug);
        foreach ($this->articles() as $article) {
            if (($article['group'] ?? '') === $group && ($article['slug'] ?? '') === $slug) {
                return $article;
            }
        }
        return null;
    }

    public function resolveTitleSummary(array $article, ?string $locale = null): array
    {
        $locale = $this->normalizeLocale($locale);
        $titleKey = (string) ($article['title'] ?? '');
        $summaryKey = (string) ($article['summary'] ?? '');
        $title = $this->translateKey($titleKey, $locale);
        $summary = $summaryKey !== '' ? $this->translateKey($summaryKey, $locale) : '';
        return [$title, $summary];
    }

    public function resolveKnowledgePath(string $slug, ?string $locale = null): ?string
    {
        $slug = Str::slug($slug);
        $locale = $this->normalizeLocale($locale);
        $candidates = [
            "docs/knowledge/{$locale}/{$slug}.md",
            "docs/knowledge/en/{$slug}.md",
        ];
        foreach ($candidates as $candidate) {
            if (is_file(base_path($candidate))) {
                return $candidate;
            }
        }
        return null;
    }

    public function articleEntries(?string $locale = null): array
    {
        $locale = $this->normalizeLocale($locale);
        return collect($this->articles())
            ->map(function (array $article) use ($locale) {
                $titleKey = (string) ($article['title'] ?? '');
                $title = $this->translateKey($titleKey, $locale);
                if ($title === '') {
                    return null;
                }
                $summaryKey = (string) ($article['summary'] ?? '');
                $summary = $summaryKey !== '' ? $this->translateKey($summaryKey, $locale) : '';
                $tags = array_filter(array_map('strval', $article['tags'] ?? []));
                $markdownPath = (string) ($article['markdown'] ?? '');
                if ($markdownPath === '' && !empty($article['slug']) && ($article['group'] ?? '') === 'kb') {
                    $markdownPath = (string) ($this->resolveKnowledgePath((string) $article['slug'], $locale) ?? '');
                }
                $content = $markdownPath !== '' ? $this->readMarkdownText($markdownPath) : '';
                $search = implode(' ', array_filter([$title, $summary, implode(' ', $tags), $content]));
                $path = trim((string) ($article['path'] ?? ''));
                $url = $path !== '' ? url($path) : null;
                $id = (string) ($article['id'] ?? Str::slug($title));
                $slug = (string) ($article['slug'] ?? Str::slug($title));
                $group = (string) ($article['group'] ?? 'kb');

                return [
                    'id' => $id,
                    'slug' => $slug,
                    'group' => $group,
                    'title' => $title,
                    'summary' => $summary,
                    'url' => $url,
                    'search' => $search,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function buildSectionEntries(array $sections, array $articleEntries, ?string $locale = null): array
    {
        $locale = $this->normalizeLocale($locale);
        $articleMap = collect($articleEntries)->keyBy('slug')->all();
        $resolved = [];
        foreach ($sections as $section) {
            $items = [];
            foreach ($section['items'] ?? [] as $slug) {
                $article = $articleMap[$slug] ?? null;
                if ($article) {
                    $items[] = $article;
                }
            }
            if (empty($items)) {
                continue;
            }
            $titleKey = (string) ($section['title'] ?? '');
            $summaryKey = (string) ($section['summary'] ?? '');
            $resolved[] = [
                'id' => (string) ($section['id'] ?? Str::slug($titleKey)),
                'title' => $this->translateKey($titleKey, $locale),
                'summary' => $summaryKey !== '' ? $this->translateKey($summaryKey, $locale) : '',
                'items' => $items,
                'count' => count($items),
            ];
        }
        return $resolved;
    }

    public function filterEntriesByGroup(array $entries, string $group): array
    {
        return array_values(array_filter($entries, static function (array $entry) use ($group): bool {
            return ($entry['group'] ?? '') === $group;
        }));
    }

    public function findSectionTitleForSlug(array $sectionEntries, string $slug): string
    {
        foreach ($sectionEntries as $section) {
            foreach ($section['items'] ?? [] as $sectionItem) {
                if (($sectionItem['slug'] ?? '') === $slug) {
                    return (string) ($section['title'] ?? '');
                }
            }
        }
        return '';
    }

    private function normalizeLocale(?string $locale): string
    {
        $locale = $locale ?: app()->getLocale();
        return in_array($locale, ['en', 'fi'], true) ? $locale : 'en';
    }

    private function translateKey(string $key, ?string $locale): string
    {
        if ($key === '') {
            return '';
        }
        return trim((string) __($key, [], $locale));
    }

    private function readMarkdownText(?string $path): string
    {
        if (!$path) {
            return '';
        }
        $fullPath = base_path($path);
        if (!is_file($fullPath)) {
            return '';
        }
        $contents = file_get_contents($fullPath);
        if ($contents === false) {
            return '';
        }
        return $this->stripMarkdown((string) $contents);
    }

    private function stripMarkdown(string $markdown): string
    {
        $markdown = preg_replace('/```.*?```/s', ' ', $markdown);
        $markdown = preg_replace('/`[^`]*`/', ' ', $markdown);
        $markdown = preg_replace('/!\[[^\]]*\]\([^)]+\)/', ' ', $markdown);
        $markdown = preg_replace('/\[(.*?)\]\([^)]+\)/', '$1', $markdown);
        $markdown = preg_replace('/^#+\s*/m', '', $markdown);
        $markdown = preg_replace('/[*_>#+-]/', ' ', $markdown);
        $markdown = preg_replace('/\s+/', ' ', $markdown);
        return trim((string) $markdown);
    }
}
