<?php
declare(strict_types=1);

namespace App\Http;

use App\Exceptions\ValidacionException;

/**
 * Envoltorio inmutable de la petición HTTP (AGENTS.md §3, capa Http).
 * Traduce las superglobales a un API tipado: Controllers, Router y middlewares
 * nunca leen $_SERVER, $_GET ni el body directamente.
 */
final class Request
{
    /**
     * @param array<string, mixed> $query   Parámetros de la query string.
     * @param array<string, string> $headers Encabezados en minúsculas.
     */
    public function __construct(
        private readonly string $metodo,
        private readonly string $ruta,
        private readonly array $query,
        private readonly array $headers,
        private readonly string $body,
    ) {
    }

    public static function desdeGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $clave => $valor) {
            if (!is_string($valor)) {
                continue;
            }
            if (str_starts_with($clave, 'HTTP_')) {
                $nombre = str_replace('_', '-', substr($clave, 5));
                $headers[strtolower($nombre)] = $valor;
            } elseif ($clave === 'CONTENT_TYPE' || $clave === 'CONTENT_LENGTH') {
                $headers[strtolower(str_replace('_', '-', $clave))] = $valor;
            }
        }

        $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            is_string($ruta) && $ruta !== '' ? $ruta : '/',
            $_GET,
            $headers,
            file_get_contents('php://input') ?: '',
        );
    }

    public function metodo(): string
    {
        return $this->metodo;
    }

    public function ruta(): string
    {
        return $this->ruta;
    }

    /** Parámetro de la query string por nombre (nombre evita confundirse con PDO::query, guard §12). */
    public function parametro(string $clave, mixed $defecto = null): mixed
    {
        return $this->query[$clave] ?? $defecto;
    }

    /** Encabezado por nombre sin importar mayúsculas (ej. 'X-API-Key'). */
    public function header(string $nombre): ?string
    {
        return $this->headers[strtolower($nombre)] ?? null;
    }

    /**
     * Body decodificado como JSON.
     *
     * @return array<string, mixed>
     * @throws ValidacionException 422 si el body existe pero no es JSON válido.
     */
    public function json(): array
    {
        if (trim($this->body) === '') {
            return [];
        }

        $datos = json_decode($this->body, true);
        if (!is_array($datos)) {
            throw new ValidacionException('El cuerpo de la petición debe ser JSON válido.');
        }

        return $datos;
    }
}
