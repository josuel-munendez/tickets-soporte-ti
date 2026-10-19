<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Constantes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * RN-05 / AGENTS.md §6.5 — Control de carga máxima del técnico.
 *
 * La cuota es un DATO del negocio (Constantes::MAX_TICKETS_TECNICO = 5) y la
 * carga se mide solo en los estados activos (ABIERTO / EN CURSO). Esta prueba
 * fija el contrato que las capas de servicio usan al comparar la carga.
 */
final class TopeTecnicoTest extends TestCase
{
    public function test_el_tope_es_cinco(): void
    {
        self::assertSame(5, Constantes::MAX_TICKETS_TECNICO);
    }

    public function test_solo_abierto_y_en_curso_cuentan_como_carga_activa(): void
    {
        self::assertSame(['ABIERTO', 'EN CURSO'], Constantes::ESTADOS_ACTIVOS);
        self::assertNotContains('CERRADO', Constantes::ESTADOS_ACTIVOS);
    }

    /**
     * Replica la decisión del servicio: con carga >= tope ya no recibe tickets.
     * Se comprueba el borde exacto (4 recibe, 5 no).
     */
    #[DataProvider('cargas')]
    public function test_la_decision_de_cupo_respeta_el_borde(int $carga, bool $puedeRecibir): void
    {
        self::assertSame($puedeRecibir, $carga < Constantes::MAX_TICKETS_TECNICO);
    }

    public static function cargas(): array
    {
        return [
            'sin carga recibe' => [0, true],
            'carga 4 recibe' => [4, true],
            'carga 5 al tope no recibe' => [5, false],
            'carga 6 por encima no recibe' => [6, false],
        ];
    }
}
