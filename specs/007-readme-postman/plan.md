# Plan — Fase 7: README y colección Postman / `.http`

> **Spec:** `specs/007-readme-postman/spec.md`

## Archivos
```text
README.md                                            # guía de instalación y uso + declaración de IA
postman/Soporte-TI.postman_collection.json           # colección v2.1 importable (22 requests + asserts)
postman/Soporte-TI.local.postman_environment.json    # entorno Local (base_url, api_key)
postman/soporte-ti.http                              # archivo .http (VS Code REST Client / JetBrains)
```

## Decisiones y alternativas descartadas
- **D1 — Se entregan Postman Y `.http`.** El enunciado permite "colección Postman o archivo .http"; se dan
  ambos porque el `.http` es texto plano (fácil de revisar en el diff de Git) y la colección JSON es la que
  suele pedirse en la sustentación. Mismo conjunto de casos en los dos.
- **D2 — Colección en formato clásico v2.1, no en el formato YAML "spec" del workspace.** El v2.1 es el
  formato de importación reconocido por Postman y por otros clientes; el árbol `postman/postman/` que crea el
  ide es un almacén local y no se importa.
- **D3 — La clave en el README es una variable de shell (`MI_CLAVE`) con placeholder.** El guard §12 busca
  `API_KEY="…"`; usar ese literal, aunque fuera de ejemplo, haría fallar la barrera. El README indica que la
  clave real vive solo en `config/config.php`.
- **D4 — Aserciones en la colección.** Cada request de la colección trae un script que verifica status y, en
  los errores, el `error.code`; así la colección sirve como prueba, no solo como documentación.

## Punto de defensa
El README es la puerta de entrada del evaluador: reproduce la instalación completa y el contrato de la API
(§5), y deja explícita la declaración de IA (§15). La colección y el archivo .http permiten ejecutar los
mismos casos que valida la Definición de Terminado (§13).
