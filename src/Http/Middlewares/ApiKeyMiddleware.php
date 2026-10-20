<?php
declare(strict_types=1);

namespace App\Http\Middlewares;

use App\Http\Request;
use App\Http\Response;
use App\Support\Config;

/**
 * Autenticación por API key (AGENTS.md §4.3): todo request a /api/* exige el
 * encabezado X-API-Key. La comparación usa hash_equals (timing-safe, sin
 * cortes por igualdad parcial). Política fail-closed: si la clave configurada
 * está vacía, NADIE autentica y se registra el problema de configuración.
 *
 * Excepción: /api/ping es la ruta de SALUD (no de negocio). Responde sin clave
 * para que los balanceadores/health checks de la plataforma (Render, etc.) puedan
 * verificar que el proceso vive. No expone datos de negocio.
 */
final class ApiKeyMiddleware
{
    /** Rutas públicas que NO exigen API key (solo salud). */
    private const RUTAS_PUBLICAS = ['/api/ping'];

    /** @param callable(Request): Response $siguiente */
    public function __invoke(Request $peticion, callable $siguiente): Response
    {
        if (in_array($peticion->ruta(), self::RUTAS_PUBLICAS, true)) {
            return $siguiente($peticion);
        }

        $configurada = Config::apiKey();
        $enviada = $peticion->header('X-API-Key');

        if ($configurada === '') {
            error_log('[api-key] La API key configurada está vacía; toda petición es rechazada (fail-closed).');

            return Response::error('NO_AUTORIZADO', 'Autenticación no configurada en el servidor.', 401);
        }

        if ($enviada === null || !hash_equals($configurada, $enviada)) {
            return Response::error('NO_AUTORIZADO', 'Falta la clave API o la clave enviada no es válida.', 401);
        }

        return $siguiente($peticion);
    }
}
