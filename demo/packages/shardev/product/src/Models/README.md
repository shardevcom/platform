# Models (demo)

Eloquent models. Storage shape only.

- One model per table, `$fillable` mirroring the resource definition fields.
- No relationships beyond what eager loading needs, no casts beyond plain
  column types, no scopes used as business rules.

Forbidden here: validation, authorization, and any decision that depends on
the request context. A model knows the table; a use case knows the intent.