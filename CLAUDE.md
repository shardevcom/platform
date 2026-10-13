# CLAUDE.md — Instrucciones para Claude

> Proyecto: shardev — plataforma API modular sobre Laravel. Lee `BACKLOG.md` como backlog normativo.

## Contexto que debes asumir
- Laravel sigue siendo Laravel; `packages/` es la capa plataforma.
- Dos reglas madre: (1) REST/GraphQL son adapters sin lógica; (2) Repository = frontera autorizada, no wrapper de Model.
- Solo `Product` como demo (`sku,ean,brand_id,active`). No implementes otros dominios aunque los veas mencionados como ejemplos futuros.

## Cómo ejecutar una tarea T-XXX
1. Abre `BACKLOG.md`, localiza el T-ID (ID, Fase, Título, Descripción, Dependencias, Aceptación, Estimación).
2. Abre `backlog/00-index.md` (reglas) y el `backlog/fase-*.md` correspondiente.
3. Respeta el DAG en `backlog/dependencias.md`. Implementa contra contratos si la dependencia aún no existe.
4. Entrega código mínimo + test del criterio de aceptación. Ejecuta mentalmente `composer test` y los greps anti-violación antes de declarar DONE.

## Estilo de código esperado
- Controllers/Resolvers <50 LOC, delegan a Use Case con DTO.
- Use Cases orquestan; Repositories autorizan + ejecutan lifecycle + persisten vía adapter.
- Validación en un solo lugar (DTO). Auth en un solo lugar (AuthorizationManager).
- `config/platform.php` mínimo; `.env` sqlite por defecto.

## Al responder
- Indica T-ID implementado, archivos tocados, test/comando de verificación y qué guard de T-110 cubre tu cambio.
- Si detectas ambigüedad de diseño, prioriza: separación responsabilidades > seguridad > extensibilidad > testabilidad > simplicidad > performance (visión).
