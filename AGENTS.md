# AGENTS.md

Repository guidance for coding agents working in this Laravel/Inertia weight
tracker. Keep changes small, preserve the user's personal data, and follow the
patterns already present in the app.

## Project Snapshot

- Laravel 12 application on PHP 8.2+ with Inertia.js 2 and React 19.
- Vite 7, Tailwind CSS v4, and shadcn/ui-style primitives in
  `resources/js/components/ui/`.
- SQLite is the local database. The real development database may contain
  historical personal weight data.
- Core features: weight entries, waist measurements, goals, achievements,
  trend/prediction widgets, macOS Notes.app sync, and optional Withings import.
- Optional Withings SDK is a local Composer path repository at
  `packages/withings-sdk`.

## Non-Negotiable Safety Rules

- Do not run destructive database commands such as `php artisan migrate:fresh`,
  `php artisan db:wipe`, or reset/seed flows unless the user explicitly asks.
- Avoid changing live database state unless the task requires it and the impact
  is clear. Prefer tests that use the in-memory test database.
- Ask before running long-lived dev processes: `composer run dev`,
  `php artisan serve`, or `npm run dev`.
- Treat Notes.app and Withings operations as real external integrations. Ask
  before running commands that sync Notes or fetch remote Withings data unless
  the task specifically requires that action.
- Do not commit, amend, rebase, or otherwise rewrite git history unless asked.
- Preserve unrelated user changes in the worktree.

## Common Commands

### Development

- `composer run dev` - full local stack: Laravel server, queue listener, Pail
  logs, and Vite. Ask first.
- `php artisan serve` - Laravel server only. Ask first.
- `npm run dev` - Vite only. Ask first.
- `npm run build` - production frontend build.

### Testing and Quality

- `composer run test` - clears config, then runs Laravel's test command.
- `php artisan test --filter=Name` - preferred for targeted backend tests.
- `./vendor/bin/phpunit --filter Name` - direct PHPUnit alternative.
- `./vendor/bin/pint` - Laravel Pint formatting.

There is no frontend test script in `package.json`; use `npm run build` for a
basic React/Vite verification when frontend code changes.

### Application Commands

- `php artisan weight:generate-list` - generate the formatted weight list.
- `php artisan notes:get-weight` - read the `weight` note from macOS Notes.app.
- `php artisan notes:update-weight` - write generated data back to Notes.app.

## Architecture Map

### Backend

- Routes live in `routes/web.php`; the root route renders the tracker.
- `app/Http/Controllers/WeightController.php` currently coordinates most
  user-facing behavior: entries, goals, waist measurements, sync, and Withings.
- `app/Models/WeightEntry.php` stores `date` and `weight_kg`.
- `app/Models/WaistMeasurement.php` stores `date` and `waist_cm`.
- `app/Models/WeightGoal.php` and `app/Models/Achievement.php` support goal and
  achievement UI.
- `app/Actions/GenerateWeightListAction.php` formats the Notes-compatible list,
  including summaries.
- `app/Actions/RefreshWithingsAction.php` handles Withings token refresh flow.
- `app/Services/NotesAppService.php` contains AppleScript integration.
- `app/Services/WeightPredictionService.php` contains prediction math and has
  focused feature tests.

### Frontend

- Main Inertia page: `resources/js/Pages/WeightTracker.jsx`.
- App entry: `resources/js/app.jsx`.
- Shared state: `resources/js/contexts/WeightContext.jsx`.
- UI and feature components live under `resources/js/components/`.
- shadcn/ui primitives live under `resources/js/components/ui/`.
- Use the `@` alias for imports from `resources/js`.

### Data Flow

- Weights are stored in kilograms. The frontend may convert pounds to kilograms
  with the factor `0.453592`.
- Waist measurements are stored in centimeters.
- Dates are commonly serialized as `Y-m-d` for forms/charts and formatted for
  display in controller props.
- Multiple weight entries can exist for the same date. Some features use the
  first entry, some average by date, and some use the lowest daily value; check
  the local code before changing assumptions.
- Notes sync updates content between `== start` and `== end` markers and formats
  lines for Notes.app compatibility.

## Implementation Guidance

- Prefer existing Laravel conventions: validation in controllers for current
  flows, model casts/fillables on models, and actions/services for reusable
  behavior.
- Keep React changes consistent with the existing component style and Tailwind
  v4 setup. Use existing UI primitives before adding new ones.
- Keep controller prop shapes stable unless you update every consuming
  component.
- For prediction, summary, or date parsing behavior, add or update targeted
  tests near the relevant service/action.
- Avoid broad refactors in `WeightController` unless the task is specifically
  about architecture.
- For Withings changes, remember the SDK comes from the local path repository;
  inspect `packages/withings-sdk` before assuming third-party behavior.

## Verification

- Backend service/action change: run the narrowest relevant PHPUnit/Laravel test
  filter first.
- Controller or route change: add or run a focused feature test when practical.
- Frontend change: run `npm run build` unless the change is trivial.
- Formatting-only PHP changes: run Pint on touched files or the full Pint
  command if scoped formatting is not available.
- If verification would touch personal data or real integrations, stop and ask
  before running it.
