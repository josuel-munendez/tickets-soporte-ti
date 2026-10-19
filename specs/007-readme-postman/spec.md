# Spec — Fase 7: README y colección Postman / `.http`

> **Estado:** Implementado
> **Reglas:** AGENTS.md §13 (Definición de Terminado), §15 (declaración de uso de IA), §5 (contrato de la API).

## Alcance
Documentación de entrega que permita a un tercero instalar, ejecutar y probar el sistema sin leer el código:
- `README.md` con instalación, endpoints, ejemplos `curl` y declaración de uso de IA.
- Colección Postman (JSON v2.1) **y** archivo `.http` con los 9 endpoints y los casos de error.

## Criterios de aceptación
- **CA-1** El README permite poner el sistema en marcha: requisitos, `config/config.php`, creación de la BD,
  carga de `schema.sql` y `seed.sql`, y levantado del servidor.
- **CA-2** Tabla con los 9 endpoints, sus códigos OK y de error, y el formato único de error.
- **CA-3** Ejemplos `curl` ejecutables de cada operación (listar, detalle, crear, cambiar estado, reasignar,
  catálogos, resumen).
- **CA-4** Pruebas manuales de las reglas clave: 401, 404, 405, 409 (transición y tope), 422 y concurrencia.
- **CA-5** La colección Postman y el `.http` cubren los 9 endpoints + casos de error (401/404/405/409/422).
- **CA-6** El README incluye la **declaración de uso de IA** de AGENTS.md §15.
- **CA-7** Los artefactos pasan `scripts/guards.sh` (sin secretos reales ni `API_KEY="…"` de ejemplo).

## Fuera de alcance
- `docs/00`…`07` y `openapi.yaml` (fase de Doc-as-Code aparte).
