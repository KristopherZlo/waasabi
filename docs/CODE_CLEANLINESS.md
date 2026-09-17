# Code cleanliness

- Keep routes declarative. Put non-trivial request behavior in a controller.
- Validate untrusted input with a Form Request or controller validation before use.
- Authorize object access with policies or gates; never rely on hidden buttons.
- Assume the migrated schema exists. Do not add table-existence fallbacks or fake runtime data.
- Enforce relationship integrity with foreign keys and uniqueness with database indexes.
- Prefer Eloquent relationships and SQL filtering over loading collections to filter in PHP.
- Keep public discovery restricted to public, approved content from active users.
- Store all user-visible copy in the locale files.
- Keep Blade usable without JavaScript. JavaScript may enhance, not replace, core navigation and forms.
- Delete unused abstractions, imports, compatibility branches, and dependencies when their caller disappears.
- Add the smallest regression test that proves a fixed security, integrity, or user-flow bug stays fixed.
- Run Pint, PHPUnit, TypeScript checking, the Vite build, and dependency audits before release.
