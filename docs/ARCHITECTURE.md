# Architecture

## Runtime

waasabi is a Laravel 12 application with an Inertia 3 and React 19 product interface. Laravel owns routing, authorization, validation, persistence, and HTML entry responses. React owns the main community screens and follows server-provided page props; it is not a separate API application.

1. Global middleware applies sessions, CSRF protection, security headers, locale, throttling, account restrictions, and Inertia shared props.
2. Route files in `routes/partials/` keep read pages separate from mutation endpoints.
3. `CommunityPageController` builds explicit public payloads for the feed, work, profiles, people, collaboration, settings, notifications, and moderation.
4. Existing focused controllers process publishing, collaboration decisions, comments, reports, membership, uploads, and account actions.
5. Eloquent models and database constraints store community state. SQLite is used locally.
6. Inertia renders `resources/views/studio.blade.php`; Vite loads the React shell and lazy page chunks.

Support, password recovery, the full administration toolbox, and a few operational pages still use their proven Blade screens. The primary community interface no longer depends on the old Blade feed or its navigation scripts.

## Product model

- `Post` represents a standalone work, a project, or a question. `is_project` is the explicit boundary between a finished or one-off work and a project with updates, a team, followers, and collaboration requests.
- A standalone work can become a project without changing its URL or losing discussion and appreciation.
- `ProjectUpdate` stores the project journal. Public updates move the project in the feed and notify project followers.
- `CollaborationRequest` can belong to a project or stand alone. A candidate can optionally link a project of their own, join, leave, and apply again.
- `ProjectMember` records participation and edit permission separately. Acceptance never grants edit access automatically.
- Profiles contain work, contributions, open requests, skills, availability, portfolio link, avatar, banner, and a featured work.
- `PostComment`, `PostReview`, saves, appreciations, author follows, and project follows provide the community layer.

Public discovery always requires public visibility, approved moderation state, and a non-banned author. Owners retain access to drafts. Moderators can inspect queued or hidden content.

## Frontend

The persistent shell preserves the top bar, centered feed, side context, and bottom pill navigation from The Hub. The interaction model is rebuilt around three clear entry points: share a work, start a project, or ask for help.

`resources/js/studio/` contains the React application:

- `pages/` contains route-level screens.
- `components.tsx` contains the shell and the small shared interaction set.
- `RichEditor.tsx` is a lazy-loaded Tiptap editor with Markdown conversion, pasted and dropped image uploads, preview, and accessible controls.
- `studio.css` contains the responsive design system.

The editor stores text fields locally and also supports server drafts. Files remain explicit uploads. The logo and deterministic scribble avatar generator come from The Hub.

## Security boundaries

- Form Requests validate publishing, uploads, comments, reports, profile changes, and collaboration input.
- Policies and gates protect visibility, editing, administration, support, and moderation.
- Shared Inertia user data is allow-listed; Eloquent user records are never sent wholesale.
- Markdown is rendered through the existing sanitizer.
- File uploads use MIME, size, dimension, and count limits.
- Rate limiters cover authentication, publishing, interactions, uploads, support, and profile actions.
- Foreign keys and unique indexes protect memberships and idempotent actions.
- The first administrator is created only through `admin:create`.

## Operations

Laravel's scheduler prunes unattached uploads. Production must call `schedule:run` every minute. The application currently uses synchronous jobs, so it needs no queue worker until measured load justifies one.
