# PRD: Fix Deletion Broadcasts and Add Comprehensive Tests

## Summary

The project deletion flow is broken because:
1. `ProjectDeleteCommand` in CLI doesn't broadcast status updates via Reverb
2. Web app runs deletion synchronously instead of dispatching a job
3. No test coverage exists

## Tasks

### lp-cli-delete-broadcasts

**Title:** Add ReverbBroadcaster to ProjectDeleteCommand

**Type:** task

**Priority:** P1

**Description:**
Update `app/Commands/ProjectDeleteCommand.php` to broadcast deletion status updates via `ReverbBroadcaster`.

**Acceptance Criteria:**
- Inject `ReverbBroadcaster` into handle() method
- Broadcast `deleting` status at start
- Broadcast `removing_orchestrator` when calling MCP
- Broadcast `removing_files` before deleting directory
- Broadcast `deleted` on success or `delete_failed` on failure
- Event name: `project.deletion.status`
- Channels: `provisioning` and `project.{slug}`

---

### lp-web-delete-job

**Title:** Create DeleteProjectJob in web app

**Type:** task

**Priority:** P1

**Deps:** lp-cli-delete-broadcasts

**Description:**
Create `web/app/Jobs/DeleteProjectJob.php` similar to CreateProjectJob. The job should call the CLI `project:delete` command which handles broadcasting.

**Acceptance Criteria:**
- Create DeleteProjectJob class implementing ShouldQueue
- Constructor takes: slug, deleteRepo (bool), keepDb (bool)
- timeout = 60, tries = 1
- handle() executes CLI `launchpad project:delete --slug={slug} --force --json`
- CLI handles all broadcasting

---

### lp-web-api-async-delete

**Title:** Update API controller for async deletion

**Type:** task

**Priority:** P1

**Deps:** lp-web-delete-job

**Description:**
Update `web/app/Http/Controllers/Api/ApiController.php` deleteProject() method to dispatch DeleteProjectJob instead of executing synchronously.

**Acceptance Criteria:**
- deleteProject() dispatches DeleteProjectJob
- Returns 202 Accepted with {success: true, status: 'deleting', slug: string}
- No longer blocks on CLI execution

---

### lp-web-tests-api

**Title:** Create Pest tests for API endpoints

**Type:** task

**Priority:** P2

**Deps:** lp-web-api-async-delete

**Description:**
Create `web/tests/Feature/ProjectApiTest.php` with tests for create and delete endpoints.

**Acceptance Criteria:**
- test_create_project_validates_required_fields (422 on missing name)
- test_create_project_dispatches_job (Queue::fake, assert job pushed)
- test_delete_project_dispatches_job (Queue::fake, assert DeleteProjectJob)
- test_delete_returns_202

---

### lp-web-tests-jobs

**Title:** Create Pest tests for Jobs

**Type:** task

**Priority:** P2

**Deps:** lp-web-api-async-delete

**Description:**
Create `web/tests/Feature/CreateProjectJobTest.php` and `web/tests/Feature/DeleteProjectJobTest.php`.

**Acceptance Criteria:**
- Test CreateProjectJob builds correct CLI command with all options
- Test DeleteProjectJob builds correct CLI command
- Use Process::fake() to mock CLI execution

---

### lp-web-e2e-test

**Title:** Create E2E broadcast test script

**Type:** task

**Priority:** P2

**Deps:** lp-web-api-async-delete

**Description:**
Create `web/tests/e2e-broadcast-test.php` that tests the full project lifecycle with WebSocket verification.

**Acceptance Criteria:**
- Uses Pusher SDK to connect to Reverb
- Creates project via API
- Verifies provisioning broadcasts (provisioning, ready)
- Deletes project via API
- Verifies deletion broadcasts (deleting, deleted)
- Auto-cleanup via deletion

