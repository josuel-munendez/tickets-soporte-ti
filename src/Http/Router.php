<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Enrutador declarativo de la API (AGENTS.md §3, capa Http).
 * Registra rutas con patrones {param}, aplica la pila de middlewares
 * (ej. ApiKeyMiddleware) y responde 404/405 con el formato único de error (§5).
 */
final class Router
{
    /** @var list<array{metodo: string, partes: list<string>, handler: callable}> */
    private array $rutas = [];

    /** @var list<callable> Middlewares encadenados, en orden de registro. */
    private array $middlewares = [];

    /** @param callable(Request, array<string, string>): Response $handler */
    public function agregar(string $metodo, string $patron, callable $handler): void
    {
        $this->rutas[] = [
            'metodo' => strtoupper($metodo),
            'partes' => $this->partes($patron),
            'handler' => $handler,
        ];
    }

    public function usarMiddleware(callable $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    public function resolver(Request $peticion): Response
    {
        $partesSolicitadas = $this->partes($peticion->ruta());
        $rutaEncontrada = false;
        $metodosPermitidos = [];

        foreach ($this->rutas as $ruta) {
            $parametros = $this->coincidir($ruta['partes'], $partesSolicitadas);
            if ($parametros === null) {
                continue;
            }
            // El path coincide: aunque falle el método, ya sabemos que la ruta existe
            $rutaEncontrada = true;
            $metodosPermitidos[] = $ruta['metodo'];

            if ($ruta['metodo'] !== $peticion->metodo()) {
                continue;
            }

            return $this->ejecutarMiddleware($peticion, $ruta['handler'], $parametros);
        }

        if ($rutaEncontrada) {
            return Response::error('METODO_NO_PERMITIDO', 'Método no permitido para esta ruta.', 405)
                ->conEncabezado('Allow', implode(', ', array_unique($metodosPermitidos)));
        }

        return Response::error('NO_ENCONTRADO', 'Ruta no encontrada.', 404);
    }

    /** @return list<string> Segmentos de la ruta sin las barras vacías. */
    private function partes(string $ruta): array
    {
        return array_values(array_filter(explode('/', $ruta), static fn (string $p): bool => $p !== ''));
    }

    /**
     * Compara segmento a segmento: {param} captura el valor; el resto debe ser literal.
     *
     * @param  list<string> $patron
     * @param  list<string> $solicitud
     * @return array<string, string>|null null si la ruta no coincide.
     */
    private function coincidir(array $patron, array $solicitud): ?array
    {
        if (count($patron) !== count($solicitud)) {
            return null;
        }

        $parametros = [];
        foreach ($patron as $i => $segmento) {
            if (preg_match('/^\{(\w+)\}$/', $segmento, $coincidencias) === 1) {
                $parametros[$coincidencias[1]] = rawurldecode($solicitud[$i]);
                continue;
            }
            if ($segmento !== $solicitud[$i]) {
                return null;
            }
        }

        return $parametros;
    }

    /**
     * Encadena los middlewares alrededor del handler: el último registrado
     * es el más externo. El handler final recibe los parámetros capturados.
     *
     * @param array<string, string> $parametros
     */
    private function ejecutarMiddleware(Request $peticion, callable $handler, array $parametros): Response
    {
        $ejecutar = static fn (Request $req): Response => $handler($req, $parametros);
        foreach (array_reverse($this->middlewares) as $middleware) {
            $siguiente = $ejecutar;
            $ejecutar = static fn (Request $req): Response => $middleware($req, $siguiente);
        }

        return $ejecutar($peticion);
    }
}
