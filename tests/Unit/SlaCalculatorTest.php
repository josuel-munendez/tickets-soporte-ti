<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\SlaCalculator;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * RN-08 / AGENTS.md §6.8 — Cálculo del SLA.
 *
 * Alta +4 h | Media +24 h | Baja +72 h. El «ahora» entra por parámetro, así que
 * los escenarios (en tiempo, por vencer, vencido, cerrado) se prueban sin reloj.
 */
final class SlaCalculatorTest extends TestCase
{
    private DateTimeImmutable $creado;

    protected function setUp(): void
    {
        // Fecha fija para que las pruebas sean deterministas.
        $this->creado = new DateTimeImmutable('2026-10-19 08:00:00');
    }

    public static function horasPorPrioridad(): array
    {
        return [
            'Alta suma 4 horas' => ['Alta', 4],
            'Media suma 24 horas' => ['Media', 24],
            'Baja suma 72 horas' => ['Baja', 72],
        ];
    }

    #[DataProvider('horasPorPrioridad')]
    public function test_calcula_el_vencimiento_segun_prioridad(string $prioridad, int $horas): void
    {
        $esperado = $this->creado->modify("+{$horas} hours");

        self::assertEquals($esperado, SlaCalculator::calcularVence($this->creado, $prioridad));
        self::assertSame($horas, SlaCalculator::horasDePrioridad($prioridad));
    }

    public function test_prioridad_desconocida_es_error_de_programacion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SlaCalculator::horasDePrioridad('Urgente');
    }

    public function test_ticket_cerrado_no_tiene_indicador(): void
    {
        $vence = SlaCalculator::calcularVence($this->creado, 'Alta');

        self::assertNull(
            SlaCalculator::indicador('CERRADO', $this->creado, $vence, $this->creado->modify('+10 days')),
        );
    }

    public function test_recien_creado_esta_en_tiempo(): void
    {
        $vence = SlaCalculator::calcularVence($this->creado, 'Media'); // +24 h

        self::assertSame(
            SlaCalculator::EN_TIEMPO,
            SlaCalculator::indicador('ABIERTO', $this->creado, $vence, $this->creado->modify('+1 hour')),
        );
    }

    public function test_pasa_a_por_vencer_cuando_queda_menos_del_25_por_ciento(): void
    {
        $vence = SlaCalculator::calcularVence($this->creado, 'Media'); // +24 h
        // Quedan 5 h de 24 h: 5/24 ≈ 20.8 % < 25 % ⇒ POR_VENCER.
        $ahora = $vence->modify('-5 hours');

        self::assertSame(
            SlaCalculator::POR_VENCER,
            SlaCalculator::indicador('EN CURSO', $this->creado, $vence, $ahora),
        );
    }

    public function test_justo_antes_del_umbral_sigue_en_tiempo(): void
    {
        $vence = SlaCalculator::calcularVence($this->creado, 'Alta'); // +4 h = 240 min
        // Quedan exactamente 25 % (60 min): el umbral es estricto (<), así que sigue EN_TIEMPO.
        $ahora = $vence->modify('-60 minutes');

        self::assertSame(
            SlaCalculator::EN_TIEMPO,
            SlaCalculator::indicador('ABIERTO', $this->creado, $vence, $ahora),
        );
    }

    public function test_pasado_el_vencimiento_esta_vencido(): void
    {
        $vence = SlaCalculator::calcularVence($this->creado, 'Alta'); // +4 h = 12:00
        $ahora = $this->creado->modify('+5 hours'); // 13:00

        self::assertSame(
            SlaCalculator::VENCIDO,
            SlaCalculator::indicador('ABIERTO', $this->creado, $vence, $ahora),
        );
    }

    public function test_justo_en_el_vencimiento_todavia_no_esta_vencido(): void
    {
        $vence = SlaCalculator::calcularVence($this->creado, 'Alta');
        // now == vence: la regla es now > vence, así que aún no está vencido
        // y como el tiempo restante es 0 (< 25 %), queda POR_VENCER.
        self::assertSame(
            SlaCalculator::POR_VENCER,
            SlaCalculator::indicador('EN CURSO', $this->creado, $vence, $vence),
        );
    }
}
