<p align="center">
  <img src="public/images/cover-gradient.svg" alt="Waasabi header" width="100%">
</p>

# Waasabi

**Forum hub for project writeups, Q&A, and focused reviews**

Waasabi is a calm, reading-first forum for makers and students: long project writeups and practical questions in one feed. It is built for thoughtful feedback and sustained reading rather than fast, noisy threads.

---

## Status

- Prototype
- Demo/production: not published yet
- If database tables are missing, demo content is loaded from `routes/web.php`

---

## Implemented

- Two content types: projects (long-form) and questions
- Markdown editor (Tiptap), covers and albums, project status (in-progress / paused / done)
- Comment threads + structured project reviews (improve / why / how)
- Feed with filters (fresh / best / reading) and infinite loading
- Tags, showcases, and a reading-now block
- Search across posts, tags, and authors
- Saves, upvotes, reading progress, follows
- Profiles, badges, privacy/notification settings
- Notifications with read/unread state
- Support portal and basic moderation tools

## Experimental

- Weighted reporting with auto-hide thresholds
- Text moderation heuristics (low-quality detection)
- Optional image scanning via AWS Rekognition

## Planned

- Move large route closures into controllers/services
- Seed demo content via seeders
- Add feature tests for core routes and JSON actions

---

## Technical overview

### Backend
- PHP 8.2, Laravel 12
- Blade templates + routes in `routes/web.php` (closures + controllers)
- Eloquent ORM + Query Builder
- Sessions, email verification, access policies
- Queues and cache (database by default, Redis optional)

### Frontend
- Vite, TypeScript, Tailwind CSS 4
- Tiptap editor + Markdown <-> HTML (marked/turndown)
- Progressive navigation: replace `<main>` and re-hydrate (custom)

### Data and entities
Primary tables:
`users`, `posts`, `post_comments`, `post_reviews`, `post_upvotes`, `post_saves`, `user_follows`,
`reading_progress`, `content_reports`, `moderation_logs`, `user_notifications`, `support_tickets`,
`topbar_promos`.

### Security and anti-spam
- CSRF protection and server-side validation
- Rate limiting per action (see `config/waasabi.php`)
- Honeypot + Cloudflare Turnstile (optional)
- Moderation and audit logs

### Localization
- English and Finnish (`resources/lang/en`, `resources/lang/fi`)

---

## Quick start (local)

```bash
git clone https://github.com/KristopherZlo/waasabi
cd waasabi

cp .env.example .env
# Configure DB_* in .env

composer install
npm install

php artisan key:generate
php artisan migrate
php artisan storage:link

php artisan serve   # http://localhost:8000
npm run dev         # Vite + HMR
```

---

## License

Closed-source; terms are in `LICENSE`.

---

## Author

KristopherZlo.
