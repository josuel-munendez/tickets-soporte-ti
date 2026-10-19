# 07 — Pruebas

> **Herramienta:** PHPUnit 13 · **Ejecutar:** `composer install && ./vendor/bin/phpunit` (o `composer test`).
> **Config:** [`phpunit.xml`](../phpunit.xml) con dos suites: `Unit` e `Integration`.

## 1. Estrategia

| Suite | Qué prueba | Necesita BD | Velocidad |
| --- | --- | --- | --- |
| **Unit** | Clases puras de `Domain` (transiciones, SLA, tope, CSV) | No | Instantánea |
| **Integration** | Servicio real contra MySQL (transacciones, historial, tope) | Sí (`config/config.php`) | ~0.5 s |

Las pruebas de integración se **omiten** (skipped) si no hay configuración o BD, para que la
suite completa sea ejecutable en cualquier entorno. Crean sus propios datos y los **borran** al
terminar (no ensucian el `seed`).

## 2. Matriz de casos ↔ requisitos/reglas

| Caso de prueba | Cubre | Tipo |
| --- | --- | --- |
| `EstadoTicketTest::test_permite_la_transicion_valida` | RN-06 (ABIERTO→EN CURSO, EN CURSO→CERRADO) | Unit |
| `EstadoTicketTest::test_rechaza_la_transicion_invalida` | RN-06 (saltar, retroceder, reabrir, repetir, estado desconocido) | Unit |
| `SlaCalculatorTest::test_calcula_el_vencimiento_segun_prioridad` | RN-08 (Alta +4h, Media +24h, Baja +72h) | Unit |
| `SlaCalculatorTest::test_indicador_*` | RN-08 (EN_TIEMPO / POR_VENCER / VENCIDO / null) | Unit |
| `SlaCalculatorTest::test_umbral_por_vencer_es_25%` | RN-08 (borde del 25 %) | Unit |
| `TopeTecnicoTest::test_el_tope_es_cinco` | RN-05 | Unit |
| `TopeTecnicoTest::test_la_decision_de_cupo_respeta_el_borde` | RN-05 (4 recibe, 5 no) | Unit |
| `CsvExporterTest::*` | RF-17 (RFC 4180, neutralización de fórmulas) | Unit |
| `FlujoTicketTest::test_flujo_completo_abierto_a_cerrado_con_historial` | RN-06 + RN-09 | Integration |
| `FlujoTicketTest::test_no_permite_saltar_de_abierto_a_cerrado` | RN-06 (409 + ROLLBACK) | Integration |
| `FlujoTicketTest::test_cerrar_sin_solucion_es_validacion_422` | RN-06 | Integration |
| `FlujoTicketTest::test_usuario_inactivo_no_puede_crear_ticket` | RN-02 | Integration |
| `FlujoTicketTest::test_tecnico_al_tope_rechaza_y_no_quema_consecutivo` | RN-01 + RN-05 | Integration |

## 3. Resultado

```text
OK (38 tests, 65 assertions)   # 33 Unit + 5 Integration
```

## 4. Pruebas manuales (checklist de la Definición de Terminado, AGENTS.md §13)

| # | Prueba | Comando / acción | Esperado |
| --- | --- | --- | --- |
| 1 | 9 endpoints responden | `curl` de cada ruta | Códigos del contrato y formato de error |
| 2 | Sin API key | `curl /api/tickets` sin encabezado | `401 NO_AUTORIZADO` |
| 3 | ABIERTO→CERRADO | `PATCH .../estado` con `CERRADO` | `409 TRANSICION_INVALIDA` |
| 4 | Cerrar sin solución | `PATCH` `CERRADO` sin `observacion` | `422 VALIDACION` |
| 5 | Técnico al tope | POST a un técnico con 5 activos | `409 TECNICO_AL_TOPE` |
| 6 | Tope en reasignación | PATCH `.../tecnico` a técnico lleno | `409 TECNICO_AL_TOPE` |
| 7 | 20 POST en paralelo | `xargs -P20 curl …` | 20 números únicos |
| 8 | Sin trazas | Forzar un error 500 | `500 ERROR_INTERNO` (sin SQL/trazas) |
| 9 | Guards | `bash scripts/guards.sh` | `Guards: TODO OK` |
| 10 | Export CSV | `GET /api/tickets/export.csv` | `text/csv` + `Content-Disposition` |

### Prueba de concurrencia (consecutivo)

```bash
BASE=http://localhost:8080/api
K="X-API-Key: mi-clave-secreta-123"
seq 1 20 | xargs -P20 -I{} curl -s -X POST -H "$K" -H 'Content-Type: application/json' \
  -d '{"usuario_codigo":"U001","categoria_id":1,"descripcion":"Prueba de concurrencia número {}: falla la impresora.","prioridad":"Baja","tecnico_id":3}' \
  "$BASE/tickets" | grep -o '"numero":"[^"]*"' | sort | uniq -d
# Salida vacía = ningún número repetido.
```

## 5. Barreras automáticas (`scripts/guards.sh`)

Se ejecuta antes de cada commit y falla si:

- un archivo PHP no pasa `php -l`;
- se intenta commitear `config/config.php` o hay secretos evidentes;
- aparece `innerHTML` en el JS/HTML;
- hay SQL concatenado con variables o SQL/PDO fuera de `Repositories`/`Support`;
- queda depuración olvidada (`var_dump`, `print_r`, `dd`).

## 6. Cómo reproducir todo

```bash
# 1) Base de datos limpia
mysql -u root -p soporte_ti < database/schema.sql
mysql -u root -p soporte_ti < database/seed.sql

# 2) Pruebas
composer install
./vendor/bin/phpunit --testdox

# 3) Guardas
bash scripts/guards.sh
```
