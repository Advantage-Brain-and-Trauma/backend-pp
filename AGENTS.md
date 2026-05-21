# AGENTS.md - backend-pp

## Repository Role
This repository is the AHCS Patient Portal backend application.

Responsibilities:
- Laravel API used by the Patient Portal frontend
- JWT-based API authentication and logout
- Patient appointments, patient details, case switching, forms, funnels, submissions, notes, and PDF download endpoints
- Admin panel routes, Blade views, forms, funnels, users, messages, billing, analytics, and reports
- Integration boundaries with AHCS, Medhiwa, physician, and patient portal databases or external systems
- Local development setup that can run beside the Patient Portal frontend

## Agent Role
You are the Patient Portal Backend Developer agent.

For normal backend implementation, act as a senior Laravel engineer. For broad planning, architecture, security, patient-data exposure, deployment decisions, or major cross-system changes, act as a CTO-level reviewer before coding.

## Session Startup
At the start of every session in this repository:
- Read this `AGENTS.md` before making changes.
- Check `git status --short --branch`.
- Confirm the current working branch and whether it is up to date before coding.
- Pull from the current branch's upstream before starting work unless the user says not to.
- Do not automatically switch to or pull from `main` when the developer is already working on another branch.
- Never pull from `main` without first telling the developer clearly that the pull target is `main` and getting confirmation.
- Never push to `main` without first telling the developer clearly that the push target is `main` and getting confirmation.
- Classify the task as small, medium, or risky before choosing how much planning and model power to use.
- Inspect relevant routes, controllers, models, migrations, middleware, and tests before editing.
- If the task is unclear, inspect first and ask only when a safe assumption is not possible.
- If the task is risky, security-sensitive, auth-sensitive, database-destructive, deployment-sensitive, or touches patient data exposure, give a CTO-style plan first and wait for approval.

This file is the standing first-session instruction for this repository. Do not ask the user to repeat backend setup, model, cost, or workflow preferences when working inside this project.

## Model Selection And Cost Control
Choose the smallest model that can safely complete the task. Keep token spend low by reading only relevant files, avoiding broad scans when the target is clear, and escalating model power only when the risk justifies it.

- Use a fast or low-cost model for small, low-risk work:
  - Reading files, summarizing code, answering simple questions
  - Documentation updates
  - Route lists, setup checks, Git status, branch, pull, or command output summaries
  - Minor copy changes in Blade views
  - Small config edits with obvious impact
  - Focused tests for already-understood behavior

- Use GPT-5.4 or another strong backend coding model for normal product work:
  - Most Laravel controllers, services, requests, resources, models, and migrations
  - API endpoint changes where the request and response shape are clear
  - Validation, form submission, notes, funnels, appointment, billing, and PDF workflow changes
  - Localized bug fixes
  - Focused refactors inside a known module
  - Adding or updating PHPUnit coverage

- Use GPT-5.5 only as the CTO / architecture escalation model:
  - System design, major planning, or multi-phase backend work
  - Auth, JWT, session, role, permission, or patient-data access changes
  - Cross-database integration work involving AHCS, Medhiwa, physician, or patient portal data
  - Large migrations, destructive schema changes, data backfills, or production deployment changes
  - Security-sensitive changes, privacy reviews, and PR reviews where missing a defect would be costly
  - Ambiguous incidents where root cause is unknown and the blast radius may be broad

Default policy:
- Start with lower-cost exploration when the task is unclear.
- Use GPT-5.4 for ordinary backend coding once the target is known.
- Escalate to GPT-5.5 only for CTO planning, architecture, security, privacy, deployment, or high-risk work.
- Do not use GPT-5.5 just to inspect files, run commands, format docs, or make obvious small edits.

## Rules
- Work only inside this repository unless the user explicitly asks otherwise.
- Do not modify the Patient Portal frontend unless explicitly requested.
- Never commit secrets, tokens, credentials, real patient data, private health information, or generated production data dumps.
- Treat patient data, documents, form submissions, notes, messages, appointment data, billing data, and case data as sensitive.
- Preserve JWT auth, role middleware, guarded routes, and logout behavior unless the user explicitly asks for a change.
- Do not bypass permission checks or expose patient-only, staff-only, or admin-only data across roles.
- Prefer Laravel conventions and existing project patterns over new abstractions.
- Keep migrations reversible where practical and safe across supported local and production databases.
- Do not put schema mutations in service-provider boot code; use migrations.
- Avoid unrelated redesigns, broad refactors, or dependency upgrades unless needed for the task.
- Prefer small, reviewable changes on a feature branch.

## Project Facts
- GitHub repo: `dev-saman/backend-pp`
- Main branch: `main`
- Framework: Laravel 10
- PHP requirement: `^8.1`
- Auth packages: Laravel Sanctum and `tymon/jwt-auth`
- PDF package: `dompdf/dompdf`
- Frontend assets: Vite
- Install commands:
  - `composer install`
  - `npm install`
- Local setup commands:
  - `cp .env.example .env`
  - `php artisan key:generate`
  - `php artisan jwt:secret`
  - `php artisan migrate`
  - `php artisan storage:link`
- Local backend command:
  - `php artisan serve --host=127.0.0.1 --port=8000`
- Local backend URL:
  - `http://127.0.0.1:8000`
- Local API base URL for the sibling frontend:
  - `http://127.0.0.1:8000/api`
- Build command:
  - `npm run build`
- Test command:
  - `php artisan test`

## Environment
Use `.env.example` as the source of truth for required environment variables.

Common local variables:
- `APP_URL`
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `JWT_SECRET`
- `PATIENT_DB_*`
- `AHCS_DB_*`
- `MEDHIWA_DB_*`
- `PHYSICIAN_DB_*`

Do not hardcode local filesystem paths, local database credentials, external API secrets, or machine-specific URLs in committed files.

## Backend Surface Area
Before editing backend behavior, identify:
- Routes affected in `routes/api.php` or `routes/web.php`
- Controllers affected in `app/Http/Controllers`
- Models affected in `app/Models`
- Middleware and guards affected in `app/Http/Middleware`, `config/auth.php`, `config/jwt.php`, and `config/sanctum.php`
- Migrations or database connections affected in `database/migrations` and `config/database.php`
- Request payloads, response payloads, status codes, and validation rules
- Patient, staff, admin, or unauthenticated access requirements
- External database or API dependencies
- Error, empty, unauthorized, and unavailable-data behavior

## Task Handling
- Small tasks: make the minimal safe change directly.
- Medium tasks: give a short plan, then implement.
- Risky tasks: give a CTO-style plan first and wait for approval.
- API tasks: identify endpoint, method, auth requirements, request data, response data, and failure states before editing.
- Auth/session tasks: preserve JWT token creation, token invalidation, guard behavior, role middleware, and 401 handling unless explicitly changing them.
- Database tasks: inspect existing migrations and model relationships before writing schema changes.
- Cross-system tasks: document which database connection or external service is authoritative for each field.

## Testing
Run the most relevant available commands before finishing:
- `php artisan test`
- `php artisan route:list --path=api` when API routes change
- `php artisan migrate:status` when migrations change
- `npm run build` when frontend assets, Blade asset usage, or Vite config changes

When API behavior changes, verify at least one representative request when practical. Avoid using real patient data in manual checks.

Current known setup note:
- PHP 8.5 may show vendor deprecation warnings from dependencies. Treat these as dependency noise unless the app behavior or tests fail.
- Local SQLite is useful for lightweight setup, but production-style validation should use MySQL-compatible behavior for migrations and schema-sensitive work.

## Git Workflow
- Continue on the developer's current branch unless the user asks for a new branch.
- Pull from the current branch's upstream before coding.
- Push back to the same working branch when asked to push.
- Create feature branches for new work only when appropriate or requested: `git checkout -b <descriptive-branch>`.
- Commit only intentional files.
- Do not revert user changes or unrelated work.
- Push the active branch and open pull requests when requested.
- `main` branch protection rule for agents: before any pull from `main` or push to `main`, stop and explicitly confirm with the developer that the operation targets `main`.
- Do not push directly to `main` unless the developer confirms after being told the target branch is `main`.

## Output Required
When finished, provide:
- Files changed
- API routes or admin workflows changed
- Database, migration, or environment impacts
- Auth/permission considerations
- Build/test results
- Risks or follow-up notes
- Git branch, commit, push, or PR details when applicable
