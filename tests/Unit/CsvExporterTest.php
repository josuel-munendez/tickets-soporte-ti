<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\CsvExporter;
use PHPUnit\Framework\TestCase;

/**
 * Exportación CSV (mejora opcional «+10 pts»). Clase pura: se prueba el
 * entrecomillado RFC 4180 y la neutralización de fórmulas (CSV injection).
 */
final class CsvExporterTest extends TestCase
{
    public function test_genera_encabezado_y_filas_entrecomilladas(): void
    {
        $csv = CsvExporter::generar(
            ['Número', 'Estado'],
            [['numero' => 'TK-2026-000001', 'estado' => 'ABIERTO']],
            ['numero', 'estado'],
        );

        $lineas = explode("\r\n", trim($csv));
        self::assertSame('"Número";"Estado"', $lineas[0]);
        self::assertSame('"TK-2026-000001";"ABIERTO"', $lineas[1]);
    }

    public function test_duplica_las_comillas_internas(): void
    {
        $csv = CsvExporter::generar(['x'], [['x' => 'dijo "hola"']], ['x']);

        self::assertStringContainsString('"dijo ""hola"""', $csv);
    }

    public function test_neutraliza_formulas_peligrosas(): void
    {
        foreach (['=1+1', '+SUM(A1)', '-2+3', '@cmd'] as $peligroso) {
            $csv = CsvExporter::generar(['x'], [['x' => $peligroso]], ['x']);
            self::assertStringContainsString('"\'', $csv, "No se neutralizó: {$peligroso}");
        }
    }

    public function test_celda_vacia_no_recibe_apostrofo(): void
    {
        $csv = CsvExporter::generar(['x'], [['x' => '']], ['x']);

        self::assertStringContainsString('""', $csv);
        self::assertStringNotContainsString('"\'"', $csv);
    }

    public function test_columna_ausente_se_exporta_vacia(): void
    {
        $csv = CsvExporter::generar(['a', 'b'], [['a' => 'uno']], ['a', 'b']);

        self::assertStringContainsString('"uno";""', $csv);
    }
}
