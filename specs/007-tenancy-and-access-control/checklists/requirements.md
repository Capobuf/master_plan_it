# Checklist — Tenancy requirements

- [ ] Every tenant-bound entity has explicit ownership.
- [ ] Every cross-tenant relationship is forbidden and testable.
- [ ] Every route and action has a role result.
- [ ] Reports, prints and exports are tenant-scoped.
- [ ] Attachments and downloads are tenant-scoped.
- [ ] Scheduled work and commands fail closed without tenant ownership.
- [ ] Administrator identity is preserved when acting in a tenant.
- [ ] Editor cannot manage users or global operations.
- [ ] Viewer cannot write data.
- [ ] Tenant deletion is unavailable.
- [ ] Tenant current context is visible in side navigation and breadcrumbs.
- [ ] Remaining high-priority clarifications are closed before implementation.
