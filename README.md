<p align="center">
  <img src="public/images/cover-gradient.svg" alt="Waasabi header" width="100%">
</p>

# Waasabi

**Project hub for long-form writeups, Q&A, and focused reviews**

Waasabi is a Laravel app for sharing student or maker projects, asking questions, and keeping a calm, reading-first feed with saves, reviews, and profiles.

---

## Demo / Production

- Demo: not published yet
- Production: not published yet

---

## Project status

**Prototype**

Demo data is available when database tables are not present.

---

## Screenshots (placeholders)

| Feed | Project | Q&A |
| --- | --- | --- |
| <img src="public/images/cover-1.svg" alt="Feed placeholder" width="300"> | <img src="public/images/cover-2.svg" alt="Project placeholder" width="300"> | <img src="public/images/cover-3.svg" alt="Q&A placeholder" width="300"> |

| Profile | Read later | Admin |
| --- | --- | --- |
| <img src="public/images/cover-4.svg" alt="Profile placeholder" width="300"> | <img src="public/images/cover-gradient.svg" alt="Read later placeholder" width="300"> | <img src="public/images/cover-1.svg" alt="Admin placeholder" width="300"> |

---

## How it works (high level)

1. Makers publish a project writeup or a short question with media and tags.
2. Readers browse the feed, save posts for later, and leave comments or structured reviews.
3. Activity signals (saves, returns, upvotes) shape the showcase and reading flow.
4. Admins moderate users, comments, reviews, and reported content.

---

## Features

### Readers

* Feed with project and question streams plus filters (fresh, best, reading)
* Read-later library with reading progress
* Search spotlight across posts, questions, and people
* Upvote, save, share, and report flows

### Makers

* Publish flow with Markdown editor (Tiptap), cover, album, tags, and status
* Structured reviews: improve, why, and how
* Q&A threads with answers and replies
* Profiles with projects, questions, and comments

### Admin / moderation

* Role management
* Reported posts queue
* Comment, review, and post removal tools

### Localization

* English and Finnish UI

---

## Security & access control

* Session-based auth and CSRF protection
* Role checks for admin actions
* Throttling for login, registration, publish, and report endpoints
* Content reporting workflow

---

## Tech stack

### Backend

* Laravel 12
* Blade templates
* Eloquent ORM + Query Builder

### Frontend

* Vite
* Tailwind CSS
* TypeScript
* Tiptap editor + Markdown conversion
* Lucide icons

### Storage & infrastructure

* MySQL / MariaDB
* Database-backed sessions, cache, and queues (default)
* Redis optional

---

## Server requirements

* PHP 8.2+ with extensions:
  * pdo_mysql
  * mbstring
  * openssl
  * json
  * ctype
  * fileinfo
  * tokenizer
  * xml
  * bcmath (recommended)
* Composer 2.x
* Node.js 18+ and npm
* MySQL / MariaDB 10.5+ (or compatible)

---

## Quick start (local)

```bash
git clone <your-repo-url>
cd the-hub

cp .env.example .env
composer install
npm install

php artisan key:generate
# create a database and set DB_* variables in .env
php artisan migrate

php artisan serve   # http://localhost:8000
npm run dev         # assets + HMR
```

If using queues (`QUEUE_CONNECTION=database`), run a worker in another terminal:

```bash
php artisan queue:work
```

---

## Build and deploy to your server

```bash
git clone <your-repo-url>
cd the-hub

cp .env.example .env
# set APP_ENV=production
# set APP_DEBUG=false
# set APP_URL=https://your-domain
# configure database credentials

composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force

npm install
npm run build
```

Deployment notes:

* Ensure `storage/` and `bootstrap/cache/` are writable by the web server.
* Point the web server document root to `public/`.
* Keep `php artisan queue:work` running under supervisor or systemd if queues are used.

---

## Useful commands

* Run tests: `php artisan test`
* Dev mode (all services): `composer run dev`
* Rebuild frontend assets: `npm run build`
* Clear config cache: `php artisan config:clear`

---

## TODO

* [ ] Persist comment votes server-side
* [ ] Move demo data into seeders
* [ ] Add feature tests for core routes and JSON endpoints
* [ ] Improve error handling for JSON endpoints

---

## License

License is not specified yet.

---

## Author

Created and maintained by **KristopherZlo**.
