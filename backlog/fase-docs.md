# Fase Docs — T-120…T-122

## T-120 — Docs — README generado + smoke
- **Desc:** 11 secciones visión §20 + ejemplos ejecutables REST + GraphQL + `platform doctor` + `package make`.
- **Dep:** T-070, T-073, T-081
- **Aceptación:** Dev nuevo <10 min → `GET /api/v1/products → 200` y `{ products { id name } }` con sqlite.
- **Est:** M

## T-121 — Docs — Observabilidad + cache decorator
- **Desc:** `request_id/correlation_id` en lifecycle; contratos Logger/Meter/Tracer sin vendor; patrón `CachedProductRepository` + invalidación eventos. Sin cache manual por repo.
- **Dep:** T-050
- **Aceptación:** `correlation_id` en logs `execute()`; doc diagrama `Repo→Cache→Persistence` + ejemplo compilable.
- **Est:** M

## T-122 — Docs — Hardening seguridad & performance
- **Checklist:** auth-before-persistence, validación única, allow-list F/I/S, mass assignment, rate limit, token expiry/revocación, auditabilidad, errores seguros, sin whereRaw/SQL arbitrario, sin eager arbitrario, N+1, límites page/complexity/depth.
- **Dep:** T-042, T-074, T-071
- **Aceptación:** 100% marcado con evidencia (test o grep); `composer test` verde.
- **Est:** S
