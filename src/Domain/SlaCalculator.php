<?php
declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Cálculo del SLA (RN-08 / AGENTS.md §6.8). Clase PURA del dominio: no conoce
 * PDO, HTTP ni el reloj del sistema — el "ahora" entra por parámetro, lo que
 * la hace trivial de testear con PHPUnit (fase 8).
 *
 * Reglas:
 *  - Alta +4 h | Media +24 h | Baja +72 h desde la creación.
 *  - VENCIDO:    now > vence y el ticket no está cerrado.
 *  - POR_VENCER: queda menos del 25% del tiempo total del ticket.
 *  - EN_TIEMPO:  cualquier otro caso activo.
 *  - null:       ticket CERRADO (ya no aplica indicador).
 */
final class SlaCalculator
{
    /** Horas de solución por prioridad: dato, no cascada de if (§10 O). */
    public const HORAS_POR_PRIORIDAD = ['Alta' => 4, 'Media' => 24, 'Baja' => 72];

    public const VENCIDO = 'VENCIDO';
    public const POR_VENCER = 'POR_VENCER';
    public const EN_TIEMPO = 'EN_TIEMPO';

    /** Fracción del tiempo total que, al faltarse, dispara POR_VENCER (25%). */
    private const UMBRAL_POR_VENCER = 0.25;

    public static function horasDePrioridad(string $prioridad): int
    {
        return self::HORAS_POR_PRIORIDAD[$prioridad]
            ?? throw new \InvalidArgumentException("Prioridad desconocida: {$prioridad}");
    }

    /** vence_en = creado_en + horas de la prioridad (calculado al crear y guardado, RN-08). */
    public static function calcularVence(DateTimeImmutable $creado, string $prioridad): DateTimeImmutable
    {
        return $creado->modify('+' . self::horasDePrioridad($prioridad) . ' hours');
    }

    public static function indicador(
        string $estado,
        DateTimeImmutable $creado,
        DateTimeImmutable $vence,
        DateTimeImmutable $ahora,
    ): ?string {
        if ($estado === 'CERRADO') {
            return null;
        }

        if ($ahora > $vence) {
            return self::VENCIDO;
        }

        $tiempoTotal = $vence->getTimestamp() - $creado->getTimestamp();
        $tiempoRestante = $vence->getTimestamp() - $ahora->getTimestamp();

        if ($tiempoRestante < self::UMBRAL_POR_VENCER * $tiempoTotal) {
            return self::POR_VENCER;
        }

        return self::EN_TIEMPO;
    }
}
