# Error catalogue

Status: `PROPOSED TARGET — STABLE CODES`

Errors are explicit, user-safe and diagnostic. Validation errors identify fields; authorization errors do not reveal other-tenant existence. Unexpected failures include a correlation ID and retain sanitized server diagnostics.

| Code | HTTP/UI class | Meaning |
|---|---|---|
| `AUTHENTICATION_REQUIRED` | 401/login | No authenticated active user. |
| `ACCOUNT_INACTIVE` | 403/login | User is deactivated. |
| `TENANT_CONTEXT_REQUIRED` | 403/select context | Tenant-bound operation without valid context. |
| `TENANT_INACTIVE` | 403 | Tenant user attempted access to inactive tenant. |
| `RESOURCE_NOT_FOUND` | 404-safe | Missing or unauthorized/other-tenant resource; no existence leak. |
| `PERMISSION_DENIED` | 403 | Required explicit ability absent. |
| `PLATFORM_ABILITY_PROTECTED` | 422/403 | Tenant role attempted protected platform ability. |
| `STALE_VERSION` | 409 | `lock_version` no longer current. |
| `TENANT_RELATION_MISMATCH` | 422 | Related records do not share tenant. |
| `INVALID_MONEY` | 422 | Invalid decimal/scale/range. |
| `VAT_REQUIRED` | 422 | Non-zero amount lacks valid VAT/default. |
| `AMOUNT_RECONCILIATION_FAILED` | 409 | Net + VAT != Gross or allocation residual mismatch. |
| `DATE_MODE_CONFLICT` | 422 | Spend date and period mode conflict/incomplete. |
| `YEAR_OVERLAP` | 422 | Planning years overlap in tenant. |
| `COST_CENTER_CYCLE` | 422 | Parent change creates cycle. |
| `ACTIVE_DESCENDANT_EXISTS` | 422 | Parent deactivation blocked. |
| `MASTER_DATA_INACTIVE` | 422 | Inactive vendor/cost center selected for new work. |
| `REFERENCED_RECORD_DELETE_DENIED` | 409 | Referenced master data cannot be deleted. |
| `EXPENSE_KIND_CONFLICT` | 422 | Kind/funding/Extra invariant invalid. |
| `PLAFOND_REFERENCE_INVALID` | 422 | Plafond tenant/year/type invalid. |
| `ACTUAL_CONFIRMATION_INVALID` | 422 | Non-Actual or invalid Actual confirmation request. |
| `REVISION_RESTORE_INVALID` | 409 | Snapshot cannot satisfy current invariants/references. |
| `REVISION_BATCH_INCOMPLETE` | 500/correlation | Aggregate changed without complete version correlation. |
| `PROJECT_STAGE_INVALID` | 422 | Stage/deferred target invalid. |
| `CONTRACT_TERM_OVERLAP` | 422 | Contract terms overlap. |
| `GENERATION_SOURCE_DUPLICATE` | 409 | Source key already exists. |
| `GENERATION_SUPPRESSED` | 409 | Occurrence suppressed until explicit resume. |
| `GENERATION_NOT_APPLICABLE` | 422 | Term/rule does not cover selected year. |
| `GENERATED_RECORD_USER_AUTHORITATIVE` | 409 | Sync attempted to overwrite manual/confirmed record. |
| `BUDGET_VERSION_PUBLISHED` | 409 | Mutation attempted on immutable published version. |
| `BUDGET_VERSION_INCOMPLETE` | 422 | Draft cannot publish due inconsistent totals/availability. |
| `COMPARISON_DIMENSION_UNAVAILABLE` | 422/UI unavailable | Requested comparison dimension absent from one source. |
| `OUTPUT_SCOPE_INVALID` | 422 | Scope is not filtered or complete selected report/year. |
| `EXPORT_LIMIT_EXCEEDED` | 422 | Bounded export limit exceeded; no partial file. |
| `IMPORT_MANIFEST_INVALID` | 422 | Missing/version/checksum schema error. |
| `IMPORT_TARGET_IMMUTABLE` | 409 | Run target tenant change attempted. |
| `IMPORT_COLLISION` | blocked/quarantine | Identity collision requires operator decision. |
| `IMPORT_BLOCKERS_PRESENT` | 409 | Apply attempted with unresolved blockers. |
| `IMPORT_RECONCILIATION_REQUIRED` | 409 | Apply/sign-off missing current reconciliation. |
| `BACKUP_DEPENDENCY_UNAVAILABLE` | blocking preflight | Package/ZIP/mysqldump not available. |
| `BACKUP_CREATION_FAILED` | failed operation | Archive creation failed explicitly. |
| `BACKUP_NOT_VERIFIED` | 409 | Restore attempted/accepted from Created-only backup. |
| `RESTORE_VERIFICATION_FAILED` | failed operation | Empty-environment restore/smoke failed. |
| `AUDIT_RETENTION_INVALID` | 422 | Retention months outside approved bounds. |
| `DESTRUCTIVE_CONFIRMATION_REQUIRED` | 409/UI modal | Reinforced confirmation token/input missing. |
| `NOTIFICATION_DELIVERY_FAILED` | visible warning | Database notification exists; optional email failed. |
| `DEPENDENCY_LOCK_FAILED` | blocking plan gate | Exact package target cannot resolve on PHP 8.3.32. |

## Handling rules

- No catch-and-ignore.
- No random fallback values, tenant, VAT, path, package version or role.
- Transaction rollback precedes user response.
- File cleanup failure is recorded and surfaced; orphan cleanup is an explicit command with audit, not an ignored exception.
- Import/migration row errors are data, not application crashes; unexpected importer bugs still fail the run.
- Domain exception messages are translated through tenant language, while codes remain stable English identifiers.
