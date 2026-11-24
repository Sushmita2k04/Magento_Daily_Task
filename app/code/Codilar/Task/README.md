# Codilar_Task

Task management for Magento 2.4.9: admin grid and form, REST API and a public storefront list.

## Features

| Area | What it does |
|---|---|
| Admin | **Tasks** menu with **Dashboard** (landing page), Manage Tasks (grid) and Add New Task. Create, edit, delete, mass delete (with confirmation and per-task failure report). |
| Dashboard | Live analytics: summary cards, status and priority charts, completion %, recent and overdue tasks, with date / status / priority filters. |
| REST | Create, read, update, delete and search under `/rest/V1/codilar-tasks`. |
| Storefront | Public list at `/tasks` (all tasks, paginated, cached). |
| Security | Three ACL resources (view / save / delete), enforced on routes, controllers and UI. |

## Data model (`codilar_task`)

| Column | Type | Notes |
|---|---|---|
| `task_id` | int, PK | |
| `title` | varchar(255) | required, trimmed |
| `description` | text, null | up to 65 535 bytes |
| `status` | smallint | **0 Pending, 1 In Progress, 2 Completed** (source of truth) |
| `priority` | smallint | **0 Low, 1 Medium (default), 2 High** |
| `due_date` | date, null | date only, no time, no timezone shift |
| `is_done` | smallint | legacy flag, always derived from `status` (see below) |
| `created_at` | timestamp | set by the database, never taken from input |
| `updated_at` | timestamp | maintained by the database (`ON UPDATE`), read-only in the API |

### status and is_done

`status` is the single source of truth. `is_done` is `1` only when `status = 2`.
It is rewritten from `status` in `Model\Task::beforeSave()`, so Admin, REST, the repository
and any direct resource save cannot persist an inconsistent pair.

* A legacy client that sends only `is_done` still works: `1` becomes Completed; `0` on a Completed task
  becomes Pending; `0` on a task that is not completed changes nothing.
* A request that sends both and they disagree (e.g. `status: 0, is_done: 1`) is rejected with HTTP 400.
* Raw SQL that bypasses Magento can still create a mismatch; this query finds any:
  `SELECT task_id FROM codilar_task WHERE is_done <> (status = 2);`

## Permissions (ACL)

```
Codilar_Task::task              Tasks (parent; kept from the first version of this module)
├─ Codilar_Task::task_dashboard View Task Dashboard   -> dashboard page and its JSON data
├─ Codilar_Task::task_view      View Tasks            -> grid, edit form (read), GET / search
├─ Codilar_Task::task_save      Create / Edit Tasks   -> Add New, Save, POST / PUT
└─ Codilar_Task::task_delete    Delete Tasks          -> Delete, Mass Delete, DELETE
```

* The top-level **Tasks** menu needs `Codilar_Task::task`. The admin role editor ticks the parent
  automatically when a child is ticked.
* A data patch (`GrantChildTaskPermissions`) gave the three original child permissions to roles and
  integrations that already held the old parent permission, so existing access kept working.
  `GrantDashboardPermission` gives **View Task Dashboard** to every role that already has **View Tasks**.
  Both run once at upgrade; a role or integration created afterwards must be granted the permissions it needs.
* Unauthorised REST calls return **HTTP 401** (Magento's behaviour for integrations).
* Buttons, row actions and the mass-delete action are hidden for users without the matching
  permission; the controllers enforce it regardless.

## REST API

Authenticate with an admin token or an integration. Examples (replace `$TOKEN`):

```bash
# create
curl -X POST http://HOST/rest/V1/codilar-tasks -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"task":{"title":"Write docs","description":"README","status":1,"priority":2,"due_date":"2026-12-31"}}'

# read / update (only supplied fields change) / delete
curl http://HOST/rest/V1/codilar-tasks/5 -H "Authorization: Bearer $TOKEN"
curl -X PUT http://HOST/rest/V1/codilar-tasks/5 -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" -d '{"task":{"status":2}}'
curl -X DELETE http://HOST/rest/V1/codilar-tasks/5 -H "Authorization: Bearer $TOKEN"

# search: open tasks, highest priority first, 20 per page
curl -g "http://HOST/rest/V1/codilar-tasks/search?searchCriteria[filter_groups][0][filters][0][field]=status&searchCriteria[filter_groups][0][filters][0][value]=2&searchCriteria[filter_groups][0][filters][0][condition_type]=neq&searchCriteria[sortOrders][0][field]=priority&searchCriteria[sortOrders][0][direction]=DESC&searchCriteria[pageSize]=20" \
  -H "Authorization: Bearer $TOKEN"
```

| Route | Method | ACL |
|---|---|---|
| `/V1/codilar-tasks` | POST (also updates if the body has `task_id`; kept for compatibility) | `task_save` |
| `/V1/codilar-tasks/search` | GET | `task_view` |
| `/V1/codilar-tasks/:taskId` | GET | `task_view` |
| `/V1/codilar-tasks/:taskId` | PUT | `task_save` |
| `/V1/codilar-tasks/:taskId` | DELETE | `task_delete` |

Rules and limits:

* Validation errors return **400** with one message per problem (`title`, `status`, `priority`, `due_date`,
  `description`, `is_done` conflicts). An unknown id returns **404**.
* On `PUT`, Magento overwrites a `task_id` in the body with the id in the URL, so the URL always wins.
* `created_at` and `updated_at` sent by a client are ignored.
* Search accepts filters/sorting only on task columns (otherwise 400) and returns at most **200** items per page.

## Task Dashboard

**Tasks > Dashboard** (`/codilar_task/dashboard`) is the first item of the Tasks menu, so it is the landing page
for anyone with the *View Task Dashboard* permission (others land on Manage Tasks).

| Element | How it is calculated (all from the live `codilar_task` table) |
|---|---|
| Total / Completed / Pending / In Progress | counts of tasks by `status` inside the active filters |
| Overdue | tasks that are **not Completed** and whose `due_date` is before today (store timezone); tasks without a due date are never overdue |
| Completion % | `completed / total * 100`, one decimal, `0` when there are no tasks |
| Status / Priority charts | counts per status / priority (Chart.js, bundled with Magento) |
| Recent tasks | 5 newest by creation time |
| Overdue tasks | 5 overdue tasks, earliest due date first, then higher priority; the badge shows the full overdue count |

**Filters** (all figures, charts and lists follow them; they are kept in the URL so a view can be bookmarked):

* *Date range* applies to **Created date** (default) or **Due date**. Dates are store-timezone days;
  created dates are converted to whole-day UTC bounds. Presets: All time, Last 7 / 30 days, This month.
* *Status* and *Priority*: multi-select chips (none selected = all).

**How it works**

* One grouped SQL query returns every number (`GROUP BY status, priority`, at most 9 rows); the two lists
  are two more limited queries without COUNT. Three queries per refresh, no cache (the data is always live).
* Filter values are parsed strictly (`Model\Dashboard\DashboardFilterParser`): ISO dates only, `from <= to`,
  statuses/priorities checked against the option sources. Invalid input returns HTTP 400 with readable messages
  and never reaches SQL. All values are bound parameters.
* `GET /codilar_task/dashboard/data` (admin, secret-key protected, `Codilar_Task::task_dashboard`) returns
  `{"success": true, "data": {...}}` for the same filter parameters: `date_field=created|due`,
  `date_from=YYYY-MM-DD`, `date_to=YYYY-MM-DD`, `status[]=0..2`, `priority[]=0..2`.
* The page renders the first data set on the server, so it paints without a round trip; later changes call the
  endpoint (debounced, stale responses ignored). Task text is written with `textContent`, never as HTML.
* Colours live in one place (`ViewModel\Adminhtml\DashboardConfig`): Pending amber, In Progress violet,
  Completed green, Overdue red; priority Low slate, Medium blue, High red.

**What the dashboard deliberately does not show:** trends, "completed this week", cycle time or any other
history. Only the *current* status is stored (`updated_at` changes on every edit), so those numbers cannot be
computed truthfully. They would need an append-only status history table, for example
`codilar_task_status_history(history_id, task_id FK ON DELETE CASCADE, old_status, new_status, changed_at,
source)` written whenever `status` changes. This is not part of the current module.

## Admin notes

* Grid: ID, Title, Status, Priority, Due Date, Created, Updated, row actions; filters and sorting on all.
* Mass delete asks for confirmation, deletes each task independently, and reports which tasks were
  deleted and which failed (with a safe reason; details go to the log). Messages list up to 10 tasks.
* The date picker posts the admin locale format (e.g. `12/31/26`); `Model\DateInputConverter` converts it
  to `Y-m-d` **strictly** (impossible dates such as `2/30/26` are rejected, never rolled forward).

## Storefront

* `GET /tasks` shows every task (by design there is no per-customer ownership or login requirement).
* Order: Pending, In Progress, Completed; then higher priority; then earliest due date.
* 12 per page, `?p=N`; invalid page numbers are clamped.
* Output is escaped; overdue = due date before today (store time) and not Completed.
* Cached by the full-page cache; saving or deleting any task through Magento clears it
  (`Model\Task` cache tag `codilar_task`). Direct SQL changes do not; flush the cache if you make any.

### Adding a Hyvä (or other) frontend later

All data and paging logic live in `ViewModel\TaskList` (no HTML, no theme code). A Hyvä build needs only a
new template/styles that call the same view model; no backend change.

## Structure

```
Api/            TaskInterface, TaskRepositoryInterface, TaskSearchResultsInterface
Model/          Task (status/is_done rule, cache tag), TaskRepository (validation, merge, search),
                TaskValidator, TaskMassDeleter, DateInputConverter, Source/{Status,Priority},
                ResourceModel/Task(+Collection)
                Dashboard/{DashboardFilter,DashboardFilterParser,DashboardStatistics,DashboardService,TaskRowMapper}
Controller/     Adminhtml/Task/{Index,NewAction,Edit,Save,Delete,MassDelete}, Adminhtml/Dashboard/{Index,Data},
                Index/Index (storefront)
Block, Ui/      admin buttons, form data provider, row actions, mass action
ViewModel/      TaskList (storefront), Adminhtml/DashboardConfig (dashboard page config)
Setup/Patch/    BackfillTaskStatus (status from legacy is_done), GrantChildTaskPermissions, GrantDashboardPermission
etc/            module, db_schema(+whitelist), acl, di, webapi, adminhtml/{menu,routes}, frontend/routes
view/           adminhtml layouts, ui_components, dashboard template/js/css; frontend layout/template/css
i18n/en_US.csv  Test/Unit/...
```

## Install / upgrade

```bash
php bin/magento setup:upgrade
php bin/magento cache:flush
```

Production: also `setup:di:compile` and `setup:static-content:deploy`. If you pull changes into an
environment that already has generated code, clear `generated/code/Codilar/Task` (or recompile) so
interceptors match the current constructors.

The schema change is additive (new columns and indexes only); existing rows are kept. Take a database
backup before upgrading as usual.

## Tests and checks

```bash
cd dev/tests/unit
../../../vendor/bin/phpunit -c phpunit.xml.dist ../../../app/code/Codilar/Task/Test/Unit
cd ../../.. && vendor/bin/phpcs --standard=Magento2 app/code/Codilar/Task
```

## Known limits

* Concurrent edits are last-write-wins per field (updates are merged field by field, so unrelated fields survive).
* `due_date` has no time and no timezone; "overdue" uses the store's current date.
* No ownership, assignee or comments; the storefront list is public.
* Tasks without a due date sort before dated tasks within the same status and priority.
* The dashboard's date filter has no index on `created_at`; this is fine for thousands of tasks. If the table grows
  past roughly 100k rows, add an index on `created_at`.
