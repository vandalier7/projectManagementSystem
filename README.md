# Project Dashboard — API

Laravel 12 REST API for Project Dashboard, a role-based project and task
management system. The Next.js client lives in
[projectManagementFrontend](https://github.com/vandalier7/projectManagementFrontend).

## Running it

```bash
cp .env.example .env
docker compose up -d          # PHP-FPM, Nginx (:8080), MySQL, Redis, queue worker
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

The API is served at `http://localhost:8080/api`. Seeded logins:
`admin@temp.net / adminpass` (admin) and `vandalier@gmail.com / qwertyuiop` (member).

### Tests

```bash
php artisan test
```

Tests use an in-memory SQLite database, so they need no Docker or MySQL.

## Architecture

```
app/
├── Http/
│   ├── Controllers/Api/       Thin HTTP layer: validate → authorize → delegate → respond
│   ├── Requests/Task/         Form Requests (validation for task create/update)
│   └── Resources/             JSON shapes (TaskResource, DashboardTaskResource)
├── Policies/                  Who may do what (ProjectPolicy, TaskPolicy)
├── Services/
│   ├── TaskLifecycle/         Task Lifecycle Engine (custom-built component)
│   └── NotificationService.php
├── Models/                    Eloquent models + relationship helpers
└── Jobs/                      Queued work (theme colour extraction)
```

### Authentication and identity

Every route except `POST /login` requires a Sanctum bearer token. Controllers
always act as `$request->user()`. **Never** decide who is acting from a user id
sent by the client: an earlier version did this (`acting_as_user_id`, `?user=`),
which let any user act as an admin. `tests/Feature/AuthorizationTest.php`
guards against that coming back.

### Roles

| Role | Can |
|---|---|
| Admin | Everything: users, projects, any task |
| Project lead | Create, edit, delete and review tasks in their project; manage members, logo, banner, theme colour |
| Member | View their projects; start, submit and take back their own tasks |

Project-level rules are in `ProjectPolicy`; task-level coarse rules are in
`TaskPolicy`. Status transitions have finer rules, enforced by the engine below.

### Task Lifecycle Engine

`app/Services/TaskLifecycle/TaskLifecycleEngine.php` owns every task status
change. Each change runs in a database transaction with a row lock
(`lockForUpdate`), so two people acting on the same task at the same time
can't interleave.

```
todo ──start──▶ in_progress ──submit──▶ submitted ──approve──▶ done
                     ▲                      │
                     └──── take back ───────┤
                                            └──reject──▶ closed  +  new revision (todo)
```

- **Start** an unassigned task to claim it. A lead or admin who starts a task
  doesn't need to submit it.
- **Submit** needs a link; only the assignee can submit or take back.
- **Approve / reject** is for the project lead or an admin. Rejecting closes the
  task and creates a revision linked by `previous_task_id`, so the full history
  is kept.
- Tasks that don't need submission (or are assigned to the lead) can go
  straight from `in_progress` to `done`.
- In the system ("General") project, anyone can complete an unassigned task.

`TaskRevisionChain` loads revision chains to any depth. It uses one query per
level of the chain, not one per task.

### Main endpoints

| Method | Path | Notes |
|---|---|---|
| POST | `/login`, `/logout` | |
| GET | `/projects`, `/projects/{id}` | Project detail includes tasks with nested `previous_task` chains |
| GET | `/projects/system`, `/projects/system/tasks` | The system project and the current user's view of its tasks |
| GET | `/projects/{id}/heartbeat` | Last-updated timestamp for live board syncing |
| POST/PUT/DELETE | `/tasks`, `/tasks/{id}` | Status changes go through the lifecycle engine |
| GET | `/tasks/mine`, `/tasks/{id}/history` | Current user's open tasks; a task's revision chain |
| POST | `/tasks/reorder` | Lead/admin; `{ ordered_ids: [...] }` |
| * | `/projects/{id}/members`, `/users`, `/notifications` | |
