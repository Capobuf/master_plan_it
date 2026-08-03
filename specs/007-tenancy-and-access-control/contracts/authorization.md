# Contract — Authorization

`Allow` always means server-side authorization in a valid tenant context.

| Resource or action | Administrator | Editor same tenant | Viewer same tenant | User other tenant |
|---|---:|---:|---:|---:|
| List tenant business records | Allow | Allow | Allow read-only | Deny |
| View tenant business record | Allow | Allow | Allow | Deny |
| Create expense | Allow | Allow | Deny | Deny |
| Modify expense | Allow | Allow within invariants | Deny | Deny |
| Add Estimate or Quote row | Allow | Allow | Deny | Deny |
| Add Actual row | Allow | Allow | Deny | Deny |
| Modify, replace or delete recorded Actual | Deny by invariant | Deny by invariant | Deny | Deny |
| Replace non-Actual row | Allow | Allow | Deny | Deny |
| Manage plafond | Allow | Allow | Deny | Deny |
| Manage vendors | Allow | Allow | Deny | Deny |
| Manage cost centers | Allow | Allow | Deny | Deny |
| Configure financial years | Allow | Deny | Deny | Deny |
| Manage projects and contracts | Allow | Allow | Deny | Deny |
| View reports and dashboard | Allow | Allow | Allow | Deny |
| Print tenant output | Allow | Allow | Allow | Deny |
| Export tenant data | Allow | Allow | Allow | Deny |
| Create scenarios | Allow | Allow | Deny | Deny |
| View tenant audit | Allow | Allow | Allow | Deny |
| Manage attachments | Allow | Allow, subject to open retention rule | Read/download | Deny |
| Import tenant data | Allow | Deny | Deny | Deny |
| Run migration | Allow | Deny | Deny | Deny |
| Backup or restore | Allow | Deny | Deny | Deny |
| Create or manage tenant | Allow | Deny | Deny | Deny |
| Create or manage tenant users | Allow | Deny | Deny | Deny |
| Switch tenant context | Allow | Deny | Deny | Deny |
| View global operational overview | Allow | Deny | Deny | Deny |

Open decisions must be updated after Q-016 through Q-020 and later clarification rounds.
