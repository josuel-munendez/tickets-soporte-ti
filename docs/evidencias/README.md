# Evidencias

| Archivo | Qué es |
|---|---|
| `ejemplo-export.csv` | Ejemplo real de la salida de `GET /api/tickets/export.csv`: 25 tickets con los mismos filtros/columnas de la tabla. Sirve como evidencia del punto extra "Exportar a CSV el listado filtrado". |

> Para regenerarlo: en la SPA, botón **Exportar CSV**; o por `curl`:
>
> ```bash
> curl -s -H "X-API-Key: $MI_CLAVE" "$BASE/tickets/export.csv" -o tickets.csv
> ```
>
> El archivo es solo de demostración; los CSV exportados en pruebas no se versionan
> (`.gitignore` ignora `tickets_*.csv`).
