<?php
declare(strict_types=1);

namespace App\Services;

use App\Domain\Constantes;
use App\Domain\SlaCalculator;
use App\Exceptions\NoEncontradoException;
use App\Exceptions\ValidacionException;
use App\Repositories\TecnicoRepository;
use App\Repositories\TicketRepository;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Reglas de consulta de tickets (§3): no conoce HTTP ni ejecuta SQL — valida
 * los filtros, orquesta al TicketRepository y formatea la respuesta del
 * contrato (fechas ISO-8601 -05:00 y indicador SLA, §5 y RN-08).
 */
final class TicketService
{
    public const PAGINA_DEFECTO = 1;
    public const POR_PAGINA_DEFECTO = 10;
    public const POR_PAGINA_MAXIMO = 50; // §5: per_page máximo 50

    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly TecnicoRepository $tecnicos,
    ) {
    }

    /**
     * Listado paginado y filtrable (§5 GET /api/tickets).
     *
     * @param  array<string, mixed> $filtros Valores crudos de la query string.
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function listar(array $filtros): array
    {
        $pagina = $this->entero($filtros, 'page', self::PAGINA_DEFECTO, 1, null);
        $porPagina = $this->entero($filtros, 'per_page', self::POR_PAGINA_DEFECTO, 1, self::POR_PAGINA_MAXIMO);
        $estado = $this->enum($filtros, 'estado', Constantes::ESTADOS);
        $prioridad = $this->enum($filtros, 'prioridad', Constantes::PRIORIDADES);
        $tecnico = $this->tecnico($filtros);
        $q = $this->texto($filtros, 'q');

        $resultado = $this->tickets->listar(
            ['estado' => $estado, 'prioridad' => $prioridad, 'tecnico' => $tecnico, 'q' => $q],
            $pagina,
            $porPagina,
        );

        $ahora = new DateTimeImmutable('now');
        $data = array_map(fn (array $fila): array => $this->formatoTicket($fila, $ahora), $resultado['filas']);

        return [
            'data' => $data,
            'meta' => [
                'page' => $pagina,
                'per_page' => $porPagina,
                'total' => $resultado['total'],
            ],
        ];
    }

    /** Detalle completo con historial (§5 GET /api/tickets/{numero}). */
    public function detalle(string $numero): array
    {
        $fila = $this->tickets->buscarPorNumero($numero);
        if ($fila === null) {
            throw new NoEncontradoException("Ticket no encontrado: {$numero}");
        }

        $detalle = $this->formatoTicket($fila, new DateTimeImmutable('now'));
        $detalle['descripcion'] = $fila['descripcion'];
        $detalle['solucion'] = $fila['solucion'];
        $detalle['cerrado'] = $fila['cerrado_en'] === null ? null : $this->iso($fila['cerrado_en']);
        $detalle['historial'] = array_map(
            fn (array $evento): array => $this->formatoHistorial($evento),
            $this->tickets->historialDe((int) $fila['id']),
        );

        return $detalle;
    }

    /** Métricas del tablero superior (§5 GET /api/resumen, §7). */
    public function resumen(): array
    {
        $conteos = $this->tickets->conteosPorEstado();
        $porEstado = [];
        foreach (Constantes::ESTADOS as $estado) {
            $porEstado[$estado] = $conteos[$estado] ?? 0;
        }

        $cargaPorTecnico = array_map(
            function (array $fila): array {
                $activa = (int) $fila['carga_activa'];

                return [
                    'tecnico_id' => (int) $fila['id'],
                    'nombre' => $fila['nombre'],
                    'carga_activa' => $activa,
                    'disponible' => $activa < Constantes::MAX_TICKETS_TECNICO, // RN-05
                ];
            },
            $this->tecnicos->listarActivosConCarga(),
        );

        return [
            'por_estado' => $porEstado,
            'vencidos' => $this->tickets->vencidosActivos(),
            'carga_por_tecnico' => $cargaPorTecnico,
        ];
    }

    /** Formato de fila de la lista/detalle del contrato (§5). */
    private function formatoTicket(array $fila, DateTimeImmutable $ahora): array
    {
        $creado = $this->fecha($fila['creado_en']);
        $vence = $this->fecha($fila['vence_en']);

        return [
            'numero' => $fila['numero'],
            'usuario' => $fila['usuario_nombre'],
            'usuario_codigo' => $fila['usuario_codigo'],
            'categoria' => $fila['categoria_nombre'],
            'prioridad' => $fila['prioridad'],
            'tecnico' => $fila['tecnico_nombre'],
            'tecnico_id' => (int) $fila['tecnico_id'],
            'estado' => $fila['estado'],
            'creado' => $creado->format(DateTimeInterface::ATOM),
            'vence' => $vence->format(DateTimeInterface::ATOM),
            'sla' => SlaCalculator::indicador($fila['estado'], $creado, $vence, $ahora), // RN-08
        ];
    }

    private function formatoHistorial(array $evento): array
    {
        return [
            'fecha' => $this->iso($evento['creado_en']),
            'tipo_cambio' => $evento['tipo_cambio'],
            'estado_anterior' => $evento['estado_anterior'],
            'estado_nuevo' => $evento['estado_nuevo'],
            'tecnico_anterior' => $evento['tecnico_anterior'],
            'tecnico_nuevo' => $evento['tecnico_nuevo'],
            'observacion' => $evento['observacion'],
        ];
    }

    /** Convierte 'YYYY-MM-DD HH:MM:SS' de BD a DateTimeImmutable en hora local (D6). */
    private function fecha(string $valor): DateTimeImmutable
    {
        return new DateTimeImmutable($valor);
    }

    private function iso(string $valor): string
    {
        return $this->fecha($valor)->format(DateTimeInterface::ATOM);
    }

    /** @param array<string, mixed> $filtros */
    private function entero(array $filtros, string $clave, int $defecto, int $minimo, ?int $maximo): int
    {
        $valor = $filtros[$clave];
        if ($valor === null || $valor === '') {
            return $defecto;
        }

        $rango = $maximo === null ? "mínimo {$minimo}" : "entre {$minimo} y {$maximo}";
        if (!is_string($valor) || preg_match('/^\d+$/', $valor) !== 1) {
            throw new ValidacionException('Parámetro inválido.', [$clave => "Debe ser un número entero ({$rango})."]);
        }

        $entero = (int) $valor;
        if ($entero < $minimo || ($maximo !== null && $entero > $maximo)) {
            throw new ValidacionException('Parámetro inválido.', [$clave => "Fuera de rango: debe ser {$rango}."]);
        }

        return $entero;
    }

    /** @param array<string, mixed> $filtros @param list<string> $permitidos */
    private function enum(array $filtros, string $clave, array $permitidos): ?string
    {
        $valor = $filtros[$clave];
        if ($valor === null || $valor === '') {
            return null;
        }

        if (!is_string($valor) || !in_array($valor, $permitidos, true)) {
            throw new ValidacionException(
                'Parámetro inválido.',
                [$clave => 'Valores permitidos: ' . implode(', ', $permitidos) . '.'],
            );
        }

        return $valor;
    }

    /** @param array<string, mixed> $filtros */
    private function tecnico(array $filtros): ?int
    {
        $valor = $filtros['tecnico'];
        if ($valor === null || $valor === '') {
            return null;
        }

        if (!is_string($valor) || preg_match('/^\d+$/', $valor) !== 1) {
            throw new ValidacionException('Parámetro inválido.', ['tecnico' => 'Debe ser el id numérico del técnico.']);
        }

        return (int) $valor;
    }

    /** @param array<string, mixed> $filtros */
    private function texto(array $filtros, string $clave): ?string
    {
        $valor = $filtros[$clave];
        if ($valor === null || $valor === '') {
            return null;
        }

        if (!is_string($valor)) {
            throw new ValidacionException('Parámetro inválido.', [$clave => 'Debe ser texto.']);
        }

        return $valor;
    }
}
