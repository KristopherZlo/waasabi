# Codebase map

- `app/Http/Controllers/CommunityPageController.php` — page payloads for the React community interface.
- `app/Http/Middleware/HandleInertiaRequests.php` — allow-listed shared account data, copy, CSRF, and flash state.
- `resources/views/studio.blade.php` — Inertia root document.
- `resources/js/studio/app.tsx` — React and Inertia bootstrap.
- `resources/js/studio/pages/` — feed, work, editor, collaboration, people, profile, settings, notifications, saved work, authentication, and moderation screens.
- `resources/js/studio/components.tsx` — persistent top bar, bottom pill menu, forms, cards, pagination, avatars, and JSON actions.
- `resources/js/studio/RichEditor.tsx` — Tiptap editor and image-upload flow.
- `resources/js/studio/studio.css` — responsive product UI.
- `resources/lang/en/studio.php` — copy for the new interface.
- `scribble-generator/` — deterministic avatar artwork retained from The Hub.

- `app/Http/Requests/` — authorization and validation at mutation boundaries.
- `app/Models/` — domain relationships and casts.
- `app/Policies/` — object-level authorization.
- `app/Services/` — collaboration, Markdown, moderation, uploads, notifications, support, and legacy feed mapping.
- `routes/partials/` — routes grouped by product area.
- `config/projects.php` — creative categories, media types, licenses, statuses, and upload rules.
- `config/inertia.php` — studio page discovery and SSR setting.
- `database/migrations/` — production schema, including the standalone-work/project distinction.
- `database/factories/` and `database/seeders/` — test records and optional local examples.
- `resources/views/` — support, password recovery, complete admin tools, and older pages that remain operational.
- `tests/Feature/StudioTest.php` — work-to-project, creation, and shared discussion contracts.
- `tests/Feature/` — application authorization and product regression coverage.

The fast moderation queue is `/admin`. The complete account, report, media, analytics, and system toolbox remains at `/admin/tools`.
