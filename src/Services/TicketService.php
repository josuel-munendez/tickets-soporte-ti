<?php
declare(strict_types=1);

namespace App\Services;

use App\Domain\Constantes;
use App\Domain\EstadoTicket;
use App\Domain\SlaCalculator;
use App\Exceptions\ConflictoException;
use App\Exceptions\NoEncontradoException;
use App\Exceptions\ValidacionException;
use App\Repositories\CategoriaRepository;
use App\Repositories\TecnicoRepository;
use App\Repositories\TicketRepository;
use App\Repositories\UsuarioRepository;
use App\Support\Database;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Reglas de tickets (§3): no conoce HTTP ni ejecuta SQL — valida la entrada,
 * orquesta al TicketRepository dentro de transacciones (creación) y formatea la
 * respuesta del contrato (fechas ISO-8601 -05:00 e indicador SLA, §5 y RN-08).
 */
final class TicketService
{
    public const PAGINA_DEFECTO = 1;
    public const POR_PAGINA_DEFECTO = 10;
    public const POR_PAGINA_MAXIMO = 50; // §5: per_page máximo 50

    private const DESCRIPCION_MINIMA = 20;  // RN-03
    private const DESCRIPCION_MAXIMA = 1000; // RN-03

    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly TecnicoRepository $tecnicos,
        private readonly UsuarioRepository $usuarios,
        private readonly CategoriaRepository $categorias,
        private readonly Database $database,
    ) {
    }

    /**
     * Crea un ticket ABIERTO con consecutivo seguro (RN-01) y su historial (RN-09),
     * todo en una transacción (POST /api/tickets, §5).
     *
     * @param  array<string, mixed> $datos Body crudo del request.
     * @return array<string, mixed> Detalle del ticket creado.
     */
    public function crear(array $datos): array
    {
        [$usuarioCodigo, $categoriaId, $descripcion, $prioridad, $tecnicoId] = $this->validarCreacion($datos);

        // RN-02 / RN-04: existencia y vigencia antes de tomar bloqueos (decisión D2, plan fase 4).
        $usuario = $this->usuarios->buscarPorCodigo($usuarioCodigo);
        if ($usuario === null || (int) $usuario['activo'] !== 1) {
            throw new ValidacionException('Datos inválidos.', ['usuario_codigo' => 'El usuario no existe o está inactivo.']);
        }
        if (!$this->categorias->existe($categoriaId)) {
            throw new ValidacionException('Datos inválidos.', ['categoria_id' => 'La categoría no existe.']);
        }

        $ahora = new DateTimeImmutable('now');
        $numero = $this->crearEnTransaccion(
            (int) $usuario['id'],
            $categoriaId,
            $descripcion,
            $prioridad,
            $tecnicoId,
            $ahora,
        );

        return $this->detalle($numero);
    }

    /**
     * Cambia el estado de un ticket siguiendo la máquina de estados (RN-06,
     * PATCH /api/tickets/{numero}/estado). Estado desconocido → 422; transición
     * ilegal → 409; cerrar exige observación (la solución) → 422.
     *
     * @param  array<string, mixed> $datos Body crudo del request.
     * @return array<string, mixed> Detalle actualizado del ticket.
     */
    public function cambiarEstado(string $numero, array $datos): array
    {
        $estadoNuevo = is_string($datos['estado'] ?? null) ? trim($datos['estado']) : '';
        if (!EstadoTicket::esValido($estadoNuevo)) {
            throw new ValidacionException(
                'Datos inválidos.',
                ['estado' => 'Valores permitidos: ' . implode(', ', Constantes::ESTADOS) . '.'],
            );
        }

        $observacion = is_string($datos['observacion'] ?? null) ? trim($datos['observacion']) : '';
        if ($estadoNuevo === 'CERRADO' && $observacion === '') {
            // RN-06: cerrar exige la solución aplicada.
            throw new ValidacionException('Datos inválidos.', ['observacion' => 'Debe indicar la solución aplicada.']);
        }

        $this->database->transaction(function () use ($numero, $estadoNuevo, $observacion): void {
            // RN-06: leer el ticket con FOR UPDATE ANTES de validar la transición.
            $ticket = $this->tickets->bloquearPorNumero($numero);
            if ($ticket === null) {
                throw new NoEncontradoException("Ticket no encontrado: {$numero}");
            }

            $estadoActual = $ticket['estado'];
            if (!EstadoTicket::permiteTransicion($estadoActual, $estadoNuevo)) {
                throw new ConflictoException(
                    "Transición no permitida: {$estadoActual} → {$estadoNuevo}.",
                    'TRANSICION_INVALIDA',
                );
            }

            $ahora = new DateTimeImmutable('now');
            $cierra = $estadoNuevo === 'CERRADO';
            $this->tickets->actualizarEstado(
                (int) $ticket['id'],
                $estadoNuevo,
                $cierra ? $observacion : null,
                $cierra ? $ahora->format('Y-m-d H:i:s') : null,
                $ahora->format('Y-m-d H:i:s'),
            );

            // RN-09: historial del cambio en la misma transacción.
            $this->tickets->insertarHistorial([
                'ticket_id' => (int) $ticket['id'],
                'tipo_cambio' => 'ESTADO',
                'estado_anterior' => $estadoActual,
                'estado_nuevo' => $estadoNuevo,
                'tecnico_anterior_id' => null,
                'tecnico_nuevo_id' => null,
                'observacion' => $observacion === '' ? null : $observacion,
                'creado_en' => $ahora->format('Y-m-d H:i:s'),
            ]);
        });

        return $this->detalle($numero);
    }

    /**
     * Reasigna el técnico de un ticket (RN-05/RN-07, PATCH /api/tickets/{numero}/tecnico).
     * Un ticket CERRADO no se reasigna (409); el técnico nuevo debe estar activo y
     * con cupo; reasignar al MISMO técnico no cuenta ni falla (no-op, RN-05).
     *
     * @param  array<string, mixed> $datos Body crudo del request.
     * @return array<string, mixed> Detalle actualizado del ticket.
     */
    public function reasignarTecnico(string $numero, array $datos): array
    {
        $errores = [];
        $tecnicoNuevoId = $this->enteroRequerido($datos['tecnico_id'] ?? null, 'tecnico_id', $errores);
        if ($errores !== []) {
            throw new ValidacionException('Datos inválidos.', $errores);
        }

        $observacion = is_string($datos['observacion'] ?? null) ? trim($datos['observacion']) : '';

        $this->database->transaction(function () use ($numero, $tecnicoNuevoId, $observacion): void {
            // Orden de bloqueos §6.1: ticket → técnico.
            $ticket = $this->tickets->bloquearPorNumero($numero);
            if ($ticket === null) {
                throw new NoEncontradoException("Ticket no encontrado: {$numero}");
            }

            // RN-07: un ticket cerrado no admite reasignación.
            if ($ticket['estado'] === 'CERRADO') {
                throw new ConflictoException('No se puede reasignar un ticket cerrado.', 'TRANSICION_INVALIDA');
            }

            $tecnicoActualId = (int) $ticket['tecnico_id'];
            if ($tecnicoActualId === $tecnicoNuevoId) {
                return; // RN-05: mismo técnico ⇒ no cuenta, no se registra historial
            }

            // RN-05: el técnico nuevo debe existir, estar activo y tener cupo (bajo bloqueo).
            $tecnico = $this->tecnicos->bloquearPorId($tecnicoNuevoId);
            if ($tecnico === null || (int) $tecnico['activo'] !== 1) {
                throw new ValidacionException('Datos inválidos.', ['tecnico_id' => 'El técnico no existe o está inactivo.']);
            }
            if ($this->tecnicos->contarActivos($tecnicoNuevoId) >= Constantes::MAX_TICKETS_TECNICO) {
                throw new ConflictoException('El técnico alcanzó el máximo de tickets activos.', 'TECNICO_AL_TOPE');
            }

            $ahora = new DateTimeImmutable('now');
            $this->tickets->actualizarTecnico((int) $ticket['id'], $tecnicoNuevoId, $ahora->format('Y-m-d H:i:s'));

            // RN-09: historial de la reasignación (estado_nuevo NO nulo ⇒ se repite el vigente).
            $this->tickets->insertarHistorial([
                'ticket_id' => (int) $ticket['id'],
                'tipo_cambio' => 'REASIGNACION',
                'estado_anterior' => $ticket['estado'],
                'estado_nuevo' => $ticket['estado'],
                'tecnico_anterior_id' => $tecnicoActualId,
                'tecnico_nuevo_id' => $tecnicoNuevoId,
                'observacion' => $observacion === '' ? null : $observacion,
                'creado_en' => $ahora->format('Y-m-d H:i:s'),
            ]);
        });

        return $this->detalle($numero);
    }

    /**
     * Transacción de creación (RN-01/RN-05/RN-09). Orden de bloqueos §6.1:
     * técnico → consecutivo (el ticket aún no existe). Si el tope falla, el
     * ROLLBACK deja el consecutivo sin quemar.
     */
    private function crearEnTransaccion(
        int $usuarioId,
        int $categoriaId,
        string $descripcion,
        string $prioridad,
        int $tecnicoId,
        DateTimeImmutable $ahora,
    ): string {
        return $this->database->transaction(function () use (
            $usuarioId,
            $categoriaId,
            $descripcion,
            $prioridad,
            $tecnicoId,
            $ahora,
        ): string {
            // 1) Bloquear la fila del técnico ANTES del consecutivo (evita deadlocks, §6.1).
            $tecnico = $this->tecnicos->bloquearPorId($tecnicoId);
            if ($tecnico === null || (int) $tecnico['activo'] !== 1) {
                throw new ValidacionException('Datos inválidos.', ['tecnico_id' => 'El técnico no existe o está inactivo.']);
            }

            // 2) RN-05: tope de carga bajo el bloqueo del técnico.
            if ($this->tecnicos->contarActivos($tecnicoId) >= Constantes::MAX_TICKETS_TECNICO) {
                throw new ConflictoException(
                    'El técnico alcanzó el máximo de tickets activos.',
                    'TECNICO_AL_TOPE',
                );
            }

            // 3) Consecutivo anual seguro (INSERT IGNORE + SELECT FOR UPDATE + UPDATE).
            $anio = (int) $ahora->format('Y');
            $numero = sprintf('TK-%d-%06d', $anio, $this->tickets->siguienteConsecutivo($anio));

            // 4) RN-08: vence_en = creado_en + horas de la prioridad.
            $vence = SlaCalculator::calcularVence($ahora, $prioridad);
            $ticketId = $this->tickets->insertar([
                'numero' => $numero,
                'usuario_id' => $usuarioId,
                'categoria_id' => $categoriaId,
                'descripcion' => $descripcion,
                'prioridad' => $prioridad,
                'tecnico_id' => $tecnicoId,
                'creado_en' => $ahora->format('Y-m-d H:i:s'),
                'vence_en' => $vence->format('Y-m-d H:i:s'),
            ]);

            // 5) RN-09: historial inicial ('CREACION') en la misma transacción.
            $this->tickets->insertarHistorial([
                'ticket_id' => $ticketId,
                'tipo_cambio' => 'CREACION',
                'estado_anterior' => null,
                'estado_nuevo' => 'ABIERTO',
                'tecnico_anterior_id' => null,
                'tecnico_nuevo_id' => $tecnicoId,
                'observacion' => 'Ticket registrado',
                'creado_en' => $ahora->format('Y-m-d H:i:s'),
            ]);

            return $numero;
        });
    }

    /**
     * Valida el body de creación y junta TODOS los errores de formato en una
     * sola respuesta 422 (decisión D4, CA-8, §5).
     *
     * @param  array<string, mixed> $datos
     * @return array{0: string, 1: int, 2: string, 3: string, 4: int}
     */
    private function validarCreacion(array $datos): array
    {
        $errores = [];

        $usuarioCodigo = is_string($datos['usuario_codigo'] ?? null) ? trim($datos['usuario_codigo']) : '';
        if ($usuarioCodigo === '') {
            $errores['usuario_codigo'] = 'El código del usuario es obligatorio.';
        }

        $categoriaId = $this->enteroRequerido($datos['categoria_id'] ?? null, 'categoria_id', $errores);
        $tecnicoId = $this->enteroRequerido($datos['tecnico_id'] ?? null, 'tecnico_id', $errores);

        $descripcion = is_string($datos['descripcion'] ?? null) ? trim($datos['descripcion']) : '';
        $longitud = mb_strlen($descripcion);
        if ($longitud < self::DESCRIPCION_MINIMA || $longitud > self::DESCRIPCION_MAXIMA) {
            $errores['descripcion'] = 'La descripción debe tener entre '
                . self::DESCRIPCION_MINIMA . ' y ' . self::DESCRIPCION_MAXIMA . ' caracteres.';
        }

        $prioridad = is_string($datos['prioridad'] ?? null) ? $datos['prioridad'] : '';
        if (!in_array($prioridad, Constantes::PRIORIDADES, true)) {
            $errores['prioridad'] = 'Valores permitidos: ' . implode(', ', Constantes::PRIORIDADES) . '.';
        }

        if ($errores !== []) {
            throw new ValidacionException('Datos inválidos.', $errores);
        }

        return [$usuarioCodigo, $categoriaId, $descripcion, $prioridad, $tecnicoId];
    }

    /**
     * Entero positivo obligatorio (acepta int o cadena numérica).
     *
     * @param array<string, string> $errores Se completa por referencia.
     */
    private function enteroRequerido(mixed $valor, string $campo, array &$errores): int
    {
        if (is_int($valor) && $valor > 0) {
            return $valor;
        }
        if (is_string($valor) && preg_match('/^\d+$/', $valor) === 1 && (int) $valor > 0) {
            return (int) $valor;
        }

        $errores[$campo] = 'Debe ser el id numérico.';

        return 0;
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
