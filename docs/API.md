# API — MTP Deploy

## Principles
- REST, JSON, versioned under `/api/v1`.
- Auth via Laravel Sanctum personal access tokens (Module 1 issues/manages tokens;
  Module 17 is where the full public API surface + webhooks ships).
- Every token has scoped **abilities** (e.g. `websites:read`, `deployments:write`) —
  never a single all-or-nothing token.
- Rate limited per-token (`throttle:api` + a stricter custom limiter on
  write/deploy-triggering endpoints).
- All list endpoints paginate (`?page=`, `?per_page=`, capped at 100).
- Errors follow `{"message": "...", "errors": {...}}` (Laravel's default
  `FormRequest`/`ValidationException` shape) with correct HTTP status codes.

## Module 1 endpoints (this phase)
| Method | Path | Purpose |
|---|---|---|
| POST | `/api/v1/auth/tokens` | Issue a new personal access token (requires password re-auth) |
| DELETE | `/api/v1/auth/tokens/{id}` | Revoke a token |
| GET | `/api/v1/auth/tokens` | List the current user's tokens (metadata only, never the plaintext secret after creation) |
| GET | `/api/v1/user` | Current authenticated user (via token) |
| GET | `/api/v1/user/sessions` | List active sessions |
| DELETE | `/api/v1/user/sessions/{id}` | Revoke a session |

## Planned surface (later modules, sketched now for stability)
| Module | Endpoints |
|---|---|
| 3 Website Manager | `GET/POST /websites`, `GET/PATCH/DELETE /websites/{id}`, `POST /websites/{id}/suspend`, `POST /websites/{id}/clone` |
| 4 Database Manager | `GET/POST /databases`, `POST /databases/{id}/backup`, `POST /databases/{id}/restore` |
| 5/6 Deployment | `POST /websites/{id}/deploy`, `GET /websites/{id}/deployments`, `POST /websites/{id}/rollback`, `POST /webhooks/{provider}/{token}` (unauthenticated, HMAC-signature verified) |
| 11 Cron | `GET/POST/PATCH/DELETE /cron-jobs`, `POST /cron-jobs/{id}/run` |
| 13 Backups | `GET /backups`, `POST /backups/{id}/restore`, `GET /backups/{id}/download` |
| 18 Multi Server | `GET/POST /servers`, `GET /servers/{id}/health` |

## Webhooks (inbound, Module 5)
Inbound Git-provider webhooks are **not** authenticated via Sanctum — they carry a
per-website random webhook token in the URL path plus the provider's own HMAC
signature header (`X-Hub-Signature-256` for GitHub, equivalent for GitLab/Bitbucket),
verified against a per-website secret before the payload is trusted.

## Webhooks (outbound, Module 17)
User-configurable outbound webhooks fire on domain events (`DeploymentSucceeded`,
`DeploymentFailed`, `SslCertificateExpiringSoon`, etc.) with an HMAC signature the
receiving end can verify, and are retried with backoff via a queued job.

## OpenAPI
A generated OpenAPI 3 spec (via `dedoc/scramble` or hand-written) will accompany the
full API in Module 17; not required for Module 1's small internal surface.
