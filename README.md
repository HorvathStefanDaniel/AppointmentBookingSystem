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

