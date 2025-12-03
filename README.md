# Harba Appointment Booking System

Containerised Symfony 6.4 + React/Vite implementation of the Harba Fullstack Developer case study. The backend exposes a JWT-protected REST API (providers, services, bookings, slot holds) and the frontend consumes those endpoints with role-aware UX for consumers, providers and admins.

## Project structure

```
.
├── backend/     # Symfony application (Doctrine, JWT, Nelmio/Swagger)
├── frontend/    # React + Vite client
├── docker/      # PHP & Caddy images + config
├── docker-compose.yml
├── .env.example # Backend + frontend env variables
```

## Quick start

1. **Prerequisites**: Docker Desktop (or any recent Docker engine) running locally.
2. **One-shot setup (Windows)**: double-click `setup.bat`. The script copies env files, builds containers, installs Composer/NPM deps, runs migrations, generates JWT keys and loads fixtures.
3. **Manual setup (all platforms)**:
   ```powershell
   Copy-Item .env.example .env         # configure DEFAULT_URI, DB creds, etc.
   docker compose up --build           # start php, Caddy, MySQL, Vite
   docker compose run --rm php composer install
   docker compose run --rm frontend npm install
   docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
   docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
   ```
4. Frontend: http://localhost:5173  
   Backend base URL: http://localhost:8080/api

### Sample users

| Email                   | Password  | Roles                         |
| ----------------------- | --------- | ----------------------------- |
| `admin@example.com`     | `12344321`| `R_ADMIN`                     |
| `postman@example.com`   | `postman1`| `R_ADMIN` (debug/Postman user)|
| `provider@example.com`  | `12344321`| `R_PROVIDER` (Alpha Marina)   |
| `consumer1@example.com` | `12344321`| `R_CONSUMER`                  |
| `consumer2@example.com` | `12344321`| `R_CONSUMER`                  |

### Backend testing

```powershell
docker compose run --rm php php bin/phpunit
```

## API documentation
- **POstman**: https://documenter.getpostman.com/view/23613570/2sB3dMxr34
- **Swagger UI**: http://localhost:8080/api/docs  
- **Raw OpenAPI JSON**: http://localhost:8080/api/docs.json  
  (NelimioApiDocBundle autogenerates both; the UI is available as soon as the stack is up.)

## Environment variables

Copy `.env.example` to `.env` (root) to configure:
- `DEFAULT_URI` – public base URL (used in Swagger server list).
- `CORS_ALLOW_ORIGIN` – frontend origin (`http://localhost:5173` for dev).
- `DATABASE_URL`, `JWT_PASSPHRASE`, etc.

Frontend expects `VITE_API_URL` (already set in `.env.example`) to point to `http://localhost:8080/api`.

## Useful commands

```powershell
# Start stack
docker compose up --build

# Run migrations
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Reload fixtures / demo data
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction

# Run frontend in dev mode (hot reload without Docker)
cd frontend && npm run dev
```

## Case study coverage

- JWT auth with role-based access control (`R_CONSUMER`, `R_PROVIDER`, `R_ADMIN`).
- Providers, services, working hours, slot generation and booking conflicts.
- Slot holds (“reserved for you”) to prevent double-booking during confirmation.
- REST API consumed by the React frontend (no hard-coded data).
- Dockerised stack (`docker compose up --build`) and Windows-friendly `setup.bat`.
- PHPUnit coverage for auth, slot generation, booking manager, voters, etc.
- API documentation delivered via Swagger (`/api/docs`) plus Postman guidance above.

See `TODO.md` for remaining nice-to-haves (soft-delete option, Makefile scripts, final verification checklist).

