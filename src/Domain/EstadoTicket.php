<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Máquina de estados del ticket (RN-06 / AGENTS.md §6.6). Clase PURA del dominio:
 * las transiciones se declaran como DATOS (mapa) y no como cascadas de if (§10 O).
 *
 * Flujo exclusivo: ABIERTO ➔ EN CURSO ➔ CERRADO. Prohibido saltar, retroceder o
 * reabrir; cualquier otra combinación es una transición inválida (409).
 */
final class EstadoTicket
{
    /** Transición permitida: estado actual => único estado siguiente (RN-06). */
    private const TRANSICIONES = [
        'ABIERTO' => 'EN CURSO',
        'EN CURSO' => 'CERRADO',
        'CERRADO' => null, // estado final: no admite transición
    ];

    /** ¿El texto recibido corresponde a un estado conocido del dominio? */
    public static function esValido(string $estado): bool
    {
        return in_array($estado, Constantes::ESTADOS, true);
    }

    /** Siguiente estado permitido, o null si el estado no admite transición. */
    public static function siguiente(string $estadoActual): ?string
    {
        return self::TRANSICIONES[$estadoActual] ?? null;
    }

    /** ¿Es legal pasar de $actual a $nuevo? (Prohibido igual, saltar o retroceder). */
    public static function permiteTransicion(string $actual, string $nuevo): bool
    {
        return self::siguiente($actual) === $nuevo;
    }

    private function __construct()
    {
    }
}
