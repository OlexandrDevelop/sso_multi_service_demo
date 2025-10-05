# SSO Multi-Service Demo (Laravel + React)

This repository contains three services that demonstrate Single Sign-On (SSO) across multiple apps:

- Service A: Authentication server (Laravel + Passport), pure backend.
- Service B: Business service (Laravel backend + React frontend).
- Service C: Business service (Laravel backend + React frontend).

The goal: sign in once on Service A, then seamlessly access Services B and C.

---

## Quick Start

Prerequisites:
- PHP 8.2+
- Composer
- Node.js 18+ and npm

Run everything (3 backends + 2 frontends):

```bash
make dev
```

URLs:
- Service A (Auth): http://localhost:8001
- Service B (Backend): http://localhost:8002
- Service C (Backend): http://localhost:8003
- Frontend B: http://localhost:5173
- Frontend C: http://localhost:5174

Default credentials:
- Email: `admin@example.com`
- Password: `password`

Stop all processes:
```bash
make stop
```

---

## Repository Structure

```
.
├── Makefile                  # Install, seed, run (dev), stop targets
├── logs/
│   └── sso.log               # Shared SSO log channel across all services
├── services/
│   ├── service-auth/         # Service A: Auth (Laravel + Passport)
│   │   ├── app/
│   │   │   ├── Http/Controllers/AuthController.php   # Auth endpoints
│   │   │   ├── Http/Resources/UserResource.php       # Response resource
│   │   │   └── Services/Auth/AuthService.php         # Token issue/validation
│   │   ├── config/
│   │   │   ├── auth.php     # api guard: passport
│   │   │   ├── sso.php      # cookie options
│   │   │   └── logging.php  # adds shared 'sso' channel
│   │   ├── database/seeders/DefaultUserSeeder.php    # default admin user
│   │   ├── resources/views/login.blade.php           # login form (POST /login)
│   │   ├── routes/
│   │   │   ├── api.php      # /api/auth/* endpoints
│   │   │   └── web.php      # /login (view + POST)
│   │   └── bootstrap/app.php# web routing config
│   ├── service-b/            # Service B: Backend (Laravel)
│   │   ├── app/Http/Middleware/SsoAuthenticate.php   # SSO middleware
│   │   ├── config/
│   │   │   ├── sso.php      # auth base URL + cookie options
│   │   │   ├── cors.php     # CORS for frontends
│   │   │   └── logging.php  # shared 'sso' channel
│   │   ├── routes/api.php   # /api/sso/me, /api/protected/ping (protected)
│   │   └── bootstrap/app.php# adds api routing + alias('sso.auth')
│   └── service-c/            # Service C: Backend (Laravel)
│       ├── app/Http/Middleware/SsoAuthenticate.php
│       ├── config/
│       │   ├── sso.php
│       │   ├── cors.php
│       │   └── logging.php
│       ├── routes/api.php
│       └── bootstrap/app.php
└── web/
    ├── app-b/                # Frontend for Service B (React + Vite)
    │   ├── package.json
    │   ├── vite.config.ts
    │   ├── tsconfig.json
    │   ├── index.html
    │   └── src/
    │       ├── main.tsx
    │       └── App.tsx       # Reads #token / sessionStorage / GET /api/auth/token
    └── app-c/                # Frontend for Service C (React + Vite)
        ├── package.json
        ├── vite.config.ts
        ├── tsconfig.json
        ├── index.html
        └── src/
            ├── main.tsx
            └── App.tsx
```

---

## How the SSO Works

- Single sign-on is implemented via Service A (Laravel Passport). A issues OAuth2 access tokens.
- Frontends (B/C) work like this:
  1) On first load, try to read a token in this order: URL fragment `#token=...` → `sessionStorage` → `GET http://localhost:8001/api/auth/token` (with `credentials: 'include'`).
  2) If a token is obtained, call the backend (B/C) with `Authorization: Bearer <token>`.
  3) Backends (B/C) validate tokens via Service A using the SSO middleware (it calls `GET /api/auth/me` on Service A with Bearer).
  4) If no token is available, frontend redirects user to `http://localhost:8001/login?redirect=<current URL>`. Service A will authenticate and redirect back to the frontend with `#token=<access_token>`.

- A also sets the cookie `sso_token` for convenience (used by `/api/auth/token` and `/api/auth/me`). For local development, cookie defaults: `SameSite=None`, `Secure=false`.

### Key Endpoints (Service A)
- `POST /login` (web): form login that establishes Laravel session and sets the SSO token cookie; then redirects back with `#token`.
- `POST /api/auth/login` (api): JSON login variant (if you consume A as API).
- `GET /api/auth/token`: returns a token when the cookie or Bearer token is available; if a valid Laravel session exists without an SSO cookie, it mints one.
- `GET /api/auth/me`: resolves the user from Bearer or cookie token.
- `POST /api/auth/logout`: revokes token and clears cookie.
- `GET /api/auth/public-key`: returns Passport public key.

### Protected Endpoints (B/C)
- `GET /api/sso/me` (protected by `sso.auth` middleware) — returns the authenticated user (validated via A).
- `GET /api/protected/ping` — sample protected endpoint.

### Shared Token Store
- Tokens are stored on Service A in standard Passport tables: `oauth_access_tokens`, `oauth_refresh_tokens`, `oauth_clients`.

---

## Environment Configuration

### Service A (.env)
- `APP_URL=http://localhost:8001`
- `DB_CONNECTION=sqlite` with `DB_DATABASE=database/database.sqlite` (created by installers)
- `SSO_COOKIE_NAME=sso_token`
- `SSO_COOKIE_DOMAIN=` (empty for local)
- `SSO_COOKIE_SAMESITE=None` (local dev)
- `SSO_COOKIE_SECURE=false` (local dev)
- Seed user (optional overrides):
  - `SEED_USER_EMAIL`, `SEED_USER_PASSWORD`, `SEED_USER_NAME`

### Service B/C (.env)
- `APP_URL=http://localhost:8002` (B) / `http://localhost:8003` (C)
- `DB_CONNECTION=sqlite` (created by installers)
- `SSO_AUTH_BASE_URL=http://localhost:8001`
- `SSO_COOKIE_NAME=sso_token`
- `SSO_COOKIE_DOMAIN=`
- `SSO_COOKIE_SAMESITE=None`
- `SSO_COOKIE_SECURE=false`

### Frontend B (web/app-b/.env)
- `VITE_API_BASE=http://localhost:8002`
- `VITE_AUTH_BASE=http://localhost:8001`
- `VITE_SERVICE_C_URL=http://localhost:5174`

### Frontend C (web/app-c/.env)
- `VITE_API_BASE=http://localhost:8003`
- `VITE_AUTH_BASE=http://localhost:8001`

---

## Logging

All three services write SSO-related events to a single file:

- Shared log channel: `sso` (see `config/logging.php` in each service)
- File: `./logs/sso.log` (repository root)
- Tail in dev:

```bash
tail -f logs/sso.log
```

You will see entries like:
- `auth.login.success`, `auth.token.request`, `auth.token.minted_from_session`
- `auth.me.success`, `auth.me.invalid_token`
- `sso.auth.validate.start/success/failed` (from B/C middleware)

---

## Development Flow

- Use `make dev` to install, seed, and start everything.
- Default admin user is created by `DefaultUserSeeder` (see credentials above).
- Frontends (B/C) are simple SPAs that:
  - Store token in `sessionStorage`.
  - Accept token via `#token` after login redirect.
  - Fallback to `GET /api/auth/token` for cookie → token conversion.

---

## Production Notes

- Serve over HTTPS; set `SSO_COOKIE_SECURE=true` and proper `SSO_COOKIE_DOMAIN`.
- Consider a reverse proxy (same origin for B/C) to share browser storage if desired.
- Replace in-memory SQLite with a real database.
- Harden CORS: restrict origins to deployed frontend hosts.
- Rotate keys and manage token lifetimes/scopes in Passport.
- Future extensions: roles/permissions, 2FA (TOTP/SMS/WebAuthn), refresh tokens, device flows.

---

## Troubleshooting

- Looping redirect from C to A:
  - Ensure Service A session exists (login through the A login form at `/login`).
  - Ensure `GET /api/auth/token` returns a token for C (check `logs/sso.log`).
  - Check cookies in the browser for `localhost:8001` — `sso_token` should exist for local dev.

- `401` from B/C:
  - Confirm frontend sends `Authorization: Bearer <token>`.
  - Confirm B/C can reach A and that `SSO_AUTH_BASE_URL` is set.

- Cookie not set:
  - For HTTP local dev, `SSO_COOKIE_SAMESITE=None` and `SSO_COOKIE_SECURE=false` are required.

- Update dependencies:
  - Backends: `composer install`
  - Frontends: `npm i` inside `web/app-b` and `web/app-c`

---

## License

This repository is provided as an example/demo. Use at your own discretion.
