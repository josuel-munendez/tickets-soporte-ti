<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Constantes;
use App\Domain\EstadoTicket;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * RN-06 / AGENTS.md §6.6 — Máquina de estados del ticket.
 *
 * Flujo exclusivo: ABIERTO ➔ EN CURSO ➔ CERRADO. Se prohíbe saltar, retroceder
 * o reabrir. Clase pura: sin PDO ni HTTP, por eso se prueba de forma unitaria.
 */
final class EstadoTicketTest extends TestCase
{
    /** Casos de transición permitidos (el único camino válido). */
    public static function transicionesValidas(): array
    {
        return [
            'ABIERTO pasa a EN CURSO' => ['ABIERTO', 'EN CURSO'],
            'EN CURSO pasa a CERRADO' => ['EN CURSO', 'CERRADO'],
        ];
    }

    /** Casos prohibidos: saltar, retroceder, repetir y reabrir. */
    public static function transicionesInvalidas(): array
    {
        return [
            'saltar de ABIERTO a CERRADO' => ['ABIERTO', 'CERRADO'],
            'retroceder de EN CURSO a ABIERTO' => ['EN CURSO', 'ABIERTO'],
            'retroceder de CERRADO a EN CURSO' => ['CERRADO', 'EN CURSO'],
            'reabrir de CERRADO a ABIERTO' => ['CERRADO', 'ABIERTO'],
            'repetir el mismo estado ABIERTO' => ['ABIERTO', 'ABIERTO'],
            'repetir el mismo estado CERRADO' => ['CERRADO', 'CERRADO'],
            'partir de un estado desconocido' => ['PENDIENTE', 'ABIERTO'],
        ];
    }

    #[DataProvider('transicionesValidas')]
    public function test_permite_la_transicion_valida(string $actual, string $nuevo): void
    {
        self::assertTrue(EstadoTicket::permiteTransicion($actual, $nuevo));
        self::assertSame($nuevo, EstadoTicket::siguiente($actual));
    }

    #[DataProvider('transicionesInvalidas')]
    public function test_rechaza_la_transicion_invalida(string $actual, string $nuevo): void
    {
        self::assertFalse(EstadoTicket::permiteTransicion($actual, $nuevo));
    }

    public function test_cerrado_no_admite_siguiente_estado(): void
    {
        self::assertNull(EstadoTicket::siguiente('CERRADO'));
    }

    public function test_estado_desconocido_no_tiene_siguiente(): void
    {
        self::assertNull(EstadoTicket::siguiente('PENDIENTE'));
    }

    public function test_reconoce_solo_los_estados_del_dominio(): void
    {
        foreach (Constantes::ESTADOS as $estado) {
            self::assertTrue(EstadoTicket::esValido($estado), "«{$estado}» debería ser válido.");
        }

        self::assertFalse(EstadoTicket::esValido('PENDIENTE'));
        self::assertFalse(EstadoTicket::esValido('en curso')); // sensible a mayúsculas
    }
}
