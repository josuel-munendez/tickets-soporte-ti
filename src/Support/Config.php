<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Carga segura de la configuración externa (AGENTS.md §4.6).
 * Los secretos viven en config/config.php (ignorado por Git); aquí solo se leen.
 * Estado único y perezoso: se carga una vez desde el Front Controller y en tests.
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $valores = [];

    private static bool $cargado = false;

    private function __construct()
    {
    }

    /**
     * Carga el archivo de configuración.
     *
     * @throws RuntimeException si el archivo no existe o no retorna un array
     *                          (el handler global la convierte en 500 sin exponer la traza, §4.4).
     */
    public static function cargar(string $rutaArchivo): void
    {
        if (!is_file($rutaArchivo)) {
            throw new RuntimeException("No existe el archivo de configuración: {$rutaArchivo}");
        }

        $valores = require $rutaArchivo;
        if (!is_array($valores)) {
            throw new RuntimeException("El archivo de configuración debe retornar un array: {$rutaArchivo}");
        }

        self::$valores = $valores;
        self::$cargado = true;
    }

    /** Lee una clave anidada con punto (ej. 'db.host'); si falta, devuelve $defecto. */
    public static function obtener(string $clave, mixed $defecto = null): mixed
    {
        if (!self::$cargado) {
            throw new RuntimeException('Config sin cargar: llame a Config::cargar() antes de leer claves.');
        }

        $nodo = self::$valores;
        foreach (explode('.', $clave) as $segmento) {
            if (!is_array($nodo) || !array_key_exists($segmento, $nodo)) {
                return $defecto;
            }
            $nodo = $nodo[$segmento];
        }

        return $nodo;
    }

    /** Atajo tipado para la API key (comparada con hash_equals en ApiKeyMiddleware). */
    public static function apiKey(): string
    {
        $apiKey = self::obtener('api_key', '');

        return is_string($apiKey) ? $apiKey : '';
    }

    /** Solo para tests: limpia el estado estático entre casos. */
    public static function reiniciar(): void
    {
        self::$valores = [];
        self::$cargado = false;
    }
}
