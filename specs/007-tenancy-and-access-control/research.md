# Research — Tenancy and access control

## Required research before implementation

Use primary, current sources only:

- official Laravel authentication, authorization, middleware, validation, filesystem, queues and scheduler documentation;
- official database documentation for foreign keys, composite indexes, uniqueness and transactional behaviour;
- official Livewire documentation if reactive tenant switching is necessary;
- official hosting documentation relevant to PHP and MySQL shared hosting.

## Alternatives to evaluate

1. Laravel-native single-database tenancy.
2. Maintained tenancy packages.
3. Separate databases per tenant.
4. Custom domains or subdomains.

## Evaluation order

1. Correct isolation.
2. Prevention of cross-tenant access.
3. Simplicity.
4. Standard Laravel capabilities.
5. Testability.
6. Maintainability.
7. PHP and MySQL hosting compatibility.
8. Dependency count.
9. Migration suitability.
10. Adequate performance.

## Current product conclusion

The approved requirements do not currently justify separate databases, subdomains, impersonation, multi-tenant user memberships or cross-tenant economic analytics. Laravel-native single-database tenancy is therefore the baseline to validate, not an unreviewed final implementation choice.
