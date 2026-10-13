# Repositories (demo)

The persistence boundary. One repository per resource.

- Extends `AbstractResourceRepository` and uses `EloquentResourcePersistence`
  so column allow-listing, tenant scope and conflict mapping stay in one place.
- Implements no business rules and no validation: QueryOptions and the DTOs are
  already allow-listed before they arrive.
- Opens no transactions. A transaction is a use-case boundary, not a
  repository one.

Forbidden here: deciding authorization, importing HTTP, and opening
transactions.