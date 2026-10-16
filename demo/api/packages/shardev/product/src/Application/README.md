# Application layer (demo)

Use cases and DTOs. This layer is the only place business rules live.

- One use case = one intent (`CreateProduct`, `UpdateProduct`, `ListProducts`).
- `Commands/` mutate, `Queries/` read, `Data/` are the input/output shapes,
  `Validation/` holds the validation rules.
- REST and GraphQL both call these same use cases, so a change here changes
  both transports at once (see `ProductUseCaseParityTest`).

Forbidden here: Eloquent models, query builders, `HttpException`, `abort()`,
`Illuminate\Http`, and any authorization decision. Use the repository and the
`AuthorizationManager` through the context instead.