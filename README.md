# Harba Appointment Booking System

WIP monorepo for the Harba Fullstack Developer case study. The goal is to deliver a containerised Symfony (PHP 8.2) + React/Vite (TypeScript) application that exposes a secure booking API and a minimal frontend that consumes it.

## Project structure

```
.
├── backend/     # Symfony application (API, Doctrine, JWT auth)
├── frontend/    # React + Vite client that talks to the API
├── docker/      # PHP & Caddy images + config
├── docker-compose.yml
├── .env.example # Backend + frontend env variables
└── TODO.md      # Detailed implementation plan
```

## Getting started (Docker)

1. Copy environment defaults:
   ```powershell
   Copy-Item .env.example .env
   ```
   Edit `.env` to match your local setup (e.g. `DEFAULT_URI`, `CORS_ALLOW_ORIGIN`, database credentials).
2. Build & start the stack (php-fpm, Caddy, MySQL, Vite dev server):
   ```powershell
   docker compose up --build
   ```
3. Install backend dependencies and bootstrap Symfony (after the first `up`):
   ```powershell
   docker compose run --rm php composer install
   ```
4. Install frontend dependencies:
   ```powershell
   docker compose run --rm frontend npm install
   ```

The API will be reachable at `http://localhost:8080/api`, and the frontend dev server at `http://localhost:5173`.

### Frontend workflow

```
cd frontend
npm run dev      # start Vite dev server (http://localhost:5173)
npm run build    # type-check + production bundle
npm run preview  # preview production build
```

Environment variables:

- `VITE_API_URL` (default `http://localhost:8080/api`)

### UI & Tailwind

- The React client ships with Tailwind CSS (see `frontend/tailwind.config.js`). Utility classes are available globally via `src/index.css`, and common patterns such as `.card`, `.panel`, `.input-field`, and `.btn-*` are defined in a `@layer components` block for reuse.
- Adjust colors, fonts, or spacing tokens by editing the `extend` section in `tailwind.config.js`, then run `npm run build` (or `npm run dev`) to pick up the changes.

### Backend testing

```
docker compose run --rm php php bin/phpunit
```

### Seed demo data

```
docker compose run --rm php php bin/console doctrine:fixtures:load
```

Seeded users:

- `admin@example.com` / `Password123!` (`R_ADMIN`)
- `provider@example.com` / `Password123!` (`R_PROVIDER` linked to the first provider)
- `consumer1@example.com` / `Password123!`, `consumer2@example.com` / `Password123!`

### API reference

- `/api/auth/register` / `/api/auth/login` return JWTs (Authorization: Bearer).
- `/api/providers` list providers, `/api/services` manage global service definitions (admin create/update/delete).
- `/api/providers/:id/slots?serviceId=…&from=YYYY-MM-DD&to=YYYY-MM-DD` lists availability.
- `/api/bookings` create/cancel bookings (`POST` expects `serviceId`, `providerId`, `startDateTime`); `/api/bookings/me`, `/api/bookings/providers/:id`, `/api/bookings` (admin) provide role-scoped listings.
- Swagger / OpenAPI docs available at `http://localhost:8080/api/docs`.

