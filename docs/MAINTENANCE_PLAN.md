# Maintenance plan

## Every release

- Run the full quality-check command set from the root README.
- Apply migrations before switching traffic to the new release.
- Verify `APP_ENV=production`, `APP_DEBUG=false`, the canonical `APP_URL`, mail delivery, storage, and trusted proxy settings.
- Smoke-test registration, verification, password reset, publish, collaboration application/acceptance, upload, report, and account deletion.

## Weekly

- Review application errors, audit logs, moderation queues, and support tickets.
- Confirm the scheduler is healthy.
- Run `composer audit` and `npm audit --audit-level=high`.

## Monthly

- Install supported dependency updates in a tested branch.
- Restore a recent backup into a disposable environment and verify it.
- Review upload storage growth and the result of `uploads:prune`.
- Check rate-limit and moderation thresholds against real abuse patterns.

## Before product expansion

Measure first. Add Redis, search infrastructure, queues, or additional caching only when current database queries or synchronous work are demonstrated bottlenecks.
