# RobotiKU Backend — AGENTS.md

Laravel 13 API for school management. API-only; web routes are just a welcome page.

## Dev commands

```bash
composer setup          # Full initial project setup
composer dev            # Runs server + queue:listen + pail (logs) + Vite concurrently
composer test           # config:clear then php artisan test
php artisan test        # Laravel test runner (SQLite :memory: per phpunit.xml)
php artisan pail        # Tail live log viewer
php artisan queue:listen --tries=1 --timeout=0
./vendor/bin/pint       # Laravel Pint (PSR-12) — run before committing
```

Run `composer test` **after** making changes; it clears config first.

## Architecture

- **All routes** in `routes/api.php` under `prefix('v1')`. Auth via `auth:sanctum` (Bearer token).
- **Three auth models**:
  - `User` (internal — has `role` field, uses passwords)
  - `SchoolAdmin` (per-school, `Authenticatable` + `HasApiTokens`)
  - `StudentParent` (plain Eloquent model, passwordless lookup)
- **RBAC**: custom `CheckRole` middleware, alias `role:`. Only applies to `User` instances — `SchoolAdmin` and `StudentParent` get 403. Roles: `super_admin`, `admin`, `admin_keuangan`, `marketing`, `trainer`.
- **API response format** (via `App\Traits\ApiResponse`):
  ```json
  { "status": true|false, "data": ..., "message": "..." }
  ```
  Controllers `use ApiResponse;` then call `$this->success(...)` / `$this->error(...)`.
- **WhatsApp** uses a gateway abstraction (`WhatsappGateway` interface) with `FonnteGateway` and `LogGateway` drivers. Provider selected via `wa_provider` Setting.
- **Rate limiting**: `login` (10/min per IP), `api` (60/min per user/token, fallback IP).

## Key config defaults

| Feature        | Default driver | Notes                          |
|----------------|----------------|---------------------------------|
| Session        | `database`     | `sessions` table               |
| Cache          | `database`     | `cache` / `cache_locks` tables |
| Queue          | `database`     | `jobs` / `job_batches` tables  |
| Database (dev) | `mysql`        | `.env` points to local MySQL    |
| Database (test)| `sqlite`       | `:memory:`, no setup needed     |

## Code conventions

- **Formatting**: `vendor/bin/pint` (Laravel Pint, PSR-12). 4 spaces, LF endings (`.editorconfig`).
- **Commit format**: `<type>(scope): (deskripsi)` — types: `feat`, `fix`, `refactor`, `docs`, `style`, `chore`, `perf`, `revert`.
- **Branching**: `main` (production) → `development` (staging) → `feature/<namaFitur>`.

## Domain layout

```
app/
  Exports/StudentsExport.php       # Maatwebsite Excel export
  Imports/StudentsImport.php       # Maatwebsite Excel import
  Models/                          # 31 Eloquent models
  Services/                        # Business logic (BillingCycle, Payment, Promo, WhatsApp, etc.)
  Support/                         # ImageStorage, Phone helper, LandingSchema
  Traits/ApiResponse.php           # Consistent JSON responses
  Http/
    Controllers/Api/V1/            # Grouped by domain: Admin, Auth, Bayar, Canvas, Karyawan, Keuangan, Landing, Murid, Ortu, Sekolah
    Middleware/CheckRole.php        # RBAC enforcement
    Requests/                      # Form requests per domain
```

## Gotchas

- `robotiku` file in project root is a **SQLite database** (not a script). It holds local dev session/cache/queue state.
- CORS accepts `FRONTEND_URL` env (default `localhost:3000`). `supports_credentials: true`.
- Sanctum token expiration: 120 minutes.
- Migration count: 60 files — run `php artisan migrate:fresh` when schema is out of sync.
- `CheckRole` rejects non-`User` models (SchoolAdmin, StudentParent get 403 even if authenticated).
