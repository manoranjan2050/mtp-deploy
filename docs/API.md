# API — MTP Deploy

## Principles
- REST, JSON, versioned under `/api/v1`.
- Auth via Laravel Sanctum personal access tokens (Module 1 issues/manages tokens;
  Module 17 ships the public API surface + outbound webhooks).
- Every token has scoped **abilities** (e.g. `websites:read`, `deployments:write`) —
  never a single all-or-nothing token, checked via Sanctum's `ability:` middleware
  (any-of semantics — a token needs the specific ability *or* `*`).
- A token's abilities gate *which endpoints are reachable at all*; the same
  Eloquent policies the Filament UI uses (`WebsitePolicy`, `DeploymentPolicy`, ...)
  still govern *which records* the token's owner can act on underneath that —
  defense in depth, not either/or. A `developer`-role user's token only ever sees
  websites they created, exactly like that role in the panel.
- Rate limited per-token (`throttle:api`, 120 req/min, keyed by user id) + a much
  stricter `throttle:deploy-api` (10 req/min) on deploy-triggering endpoints.
- All list endpoints paginate (`?page=`, `?per_page=`, capped at 100).
- Errors follow `{"message": "...", "errors": {...}}` (Laravel's default
  `FormRequest`/`ValidationException` shape) with correct HTTP status codes.

## Module 1 + 17 endpoints (built)
| Method | Path | Ability | Purpose |
|---|---|---|---|
| GET | `/api/v1/user` | `profile:read` | Current authenticated user |
| GET | `/api/v1/auth/tokens` | `profile:read` | List the current user's tokens (metadata only) |
| POST | `/api/v1/auth/tokens` | `profile:read` | Issue a new personal access token (requires password re-auth) |
| DELETE | `/api/v1/auth/tokens/{id}` | `profile:read` | Revoke a token |
| GET | `/api/v1/user/sessions` | `sessions:write` | List active sessions |
| DELETE | `/api/v1/user/sessions/{id}` | `sessions:write` | Revoke a session |
| GET | `/api/v1/websites` | `websites:read` | List websites (scoped like the panel) |
| GET | `/api/v1/websites/{id}` | `websites:read` | Show a website |
| POST | `/api/v1/websites` | `websites:write` | Create + provision a website |
| PATCH | `/api/v1/websites/{id}` | `websites:write` | Update name/PHP version/aliases, republishes nginx |
| DELETE | `/api/v1/websites/{id}` | `websites:write` | Deprovision + soft-delete |
| POST | `/api/v1/websites/{id}/suspend` | `websites:write` | Toggle suspend/reinstate |
| POST | `/api/v1/websites/{id}/clone` | `websites:write` | Clone to a new domain |
| GET | `/api/v1/websites/{id}/deployments` | `deployments:read` | List a website's deployments |
| POST | `/api/v1/websites/{id}/deployments` | `deployments:write` | Trigger a deployment (`DeploymentTrigger::Api`) |
| POST | `/api/v1/deployments/{id}/rollback` | `deployments:write` | Roll back to a prior deployment's commit |

## Planned surface (not yet built, disclosed gap)
Databases, Cron, and Backups REST endpoints are **not built in this pass** —
Module 17 shipped a complete, tested vertical (auth self-service, Websites,
Deployments, outbound webhooks) rather than a shallow pass across every
resource. The controller-reuses-existing-Action pattern established here
(`WebsiteController`/`DeploymentController` calling the same Actions the
Filament UI calls) makes adding the rest a low-risk follow-up:

| Module | Endpoints |
|---|---|
| 4 Database Manager | `GET/POST /databases`, `POST /databases/{id}/backup`, `POST /databases/{id}/restore` |
| 11 Cron | `GET/POST/PATCH/DELETE /cron-jobs`, `POST /cron-jobs/{id}/run` |
| 13 Backups | `GET /backups`, `POST /backups/{id}/restore`, `GET /backups/{id}/download` |
| 18 Multi Server | `GET/POST /servers`, `GET /servers/{id}/health` |

## Webhooks (inbound, Module 5)
Inbound Git-provider webhooks are **not** authenticated via Sanctum — they carry a
per-website random webhook token in the URL path plus the provider's own HMAC
signature header (`X-Hub-Signature-256` for GitHub, equivalent for GitLab/Bitbucket),
verified against a per-website secret before the payload is trusted.

## Webhooks (outbound, Module 17 — as built)
Each user manages their own `webhook_endpoints` (self-service, same pattern as
Module 16's notification channels — no new permission) at
`/admin/webhook-endpoints`: a target URL and a checklist of subscribed events.
Currently firing events: `deployment.succeeded`, `deployment.failed` (see
`App\Enums\WebhookEvent`) — fired from `GitDeploymentService` alongside the
existing notification dispatch, to the *deploying user's own* endpoints only,
never a global broadcast.

Delivery is queued (`App\Jobs\DispatchWebhookJob`, `$tries = 5`, backoff
`[10, 30, 60, 300, 900]` seconds) so a slow/unreachable receiving endpoint
never blocks the request that triggered the event. The request body is:

```json
{"event": "deployment.succeeded", "data": {"deployment_id": 1, "website_id": 2, "domain": "example.com", "status": "success", "commit_sha": "abc123"}}
```

signed with `X-MTP-Signature: sha256={hmac_sha256(body, endpoint_secret)}` —
the receiving end recomputes the same HMAC over the raw body to verify
authenticity, the same verification model GitHub/Stripe use for their own
outbound webhooks. The endpoint's secret is generated server-side
(`Str::random(40)`), shown to the user exactly once at creation time, and
stored **encrypted** at rest.

Backup/SSL-expiring-soon webhook events are not wired yet — disclosed gap,
see docs/Features.md.

## OpenAPI
Not generated for this pass — the endpoint table above plus this document
serve as the spec. A generated OpenAPI 3 spec (via `dedoc/scramble` or
hand-written) remains a reasonable future addition once the Databases/Cron/
Backups endpoints round out the surface, but was never a hard requirement
for Module 17 (see the original note in this file's history).
