<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Generador de CSV (AGENTS.md §12, podría «+10 pts»: exportar CSV). Clase PURA
 * del dominio: no conoce HTTP ni PDO; recibe filas ya formateadas y devuelve el
 * texto CSV, de modo que es trivial de testear con PHPUnit.
 *
 * Se aplican tres defensas al exportar:
 *  1. Escape de fórmula: si una celda empieza por =, +, - o @ se antepone un
 *     apóstrofo, para que una hoja de cálculo no la interprete como fórmula
 *     (inyección CSV / CSV injection).
 *  2. Entrecomillado RFC 4180: toda celda se encierra en comillas dobles y las
 *     comillas internas se duplican.
 *  3. Separador y terminador explícitos (;, \r\n) para compatibilidad regional.
 */
final class CsvExporter
{
    private const SEPARADOR = ';';
    private const FIN_DE_LINEA = "\r\n";
    private const PREFIJOS_PELIGROSOS = ['=', '+', '-', '@'];

    /**
     * @param  list<string>              $encabezados
     * @param  list<array<string, mixed>> $filas
     * @param  list<string>              $columnas  Orden y claves a exportar.
     */
    public static function generar(array $encabezados, array $filas, array $columnas): string
    {
        $lineas = [self::linea($encabezados)];

        foreach ($filas as $fila) {
            $celdas = [];
            foreach ($columnas as $columna) {
                $celdas[] = self::celda($fila[$columna] ?? '');
            }
            $lineas[] = implode(self::SEPARADOR, $celdas);
        }

        return implode(self::FIN_DE_LINEA, $lineas) . self::FIN_DE_LINEA;
    }

    /** @param list<mixed> $valores */
    private static function linea(array $valores): string
    {
        return implode(self::SEPARADOR, array_map(self::celda(...), $valores));
    }

    private static function celda(mixed $valor): string
    {
        $texto = (string) $valor;

        // 1) Neutraliza fórmulas antes de entrecomillar.
        if ($texto !== '' && in_array($texto[0], self::PREFIJOS_PELIGROSOS, true)) {
            $texto = "'" . $texto;
        }

        // 2) Entrecomillado RFC 4180 (duplica comillas internas).
        return '"' . str_replace('"', '""', $texto) . '"';
    }
}
