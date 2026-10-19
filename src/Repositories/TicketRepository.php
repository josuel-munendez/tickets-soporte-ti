<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Constantes;
use PDO;

/**
 * Acceso a datos de tickets (§3: único lugar con SQL; 100% sentencias preparadas,
 * sin concatenar variables al texto de la consulta — §4.1).
 * Los nombres de columna (creado_en, vence_en, cerrado_en, solucion...) coinciden
 * exactamente con database/schema.sql (§8).
 */
final class TicketRepository
{
    /** Orden fijo y seguro: creación descendente (decisión D1, spec fase 3). */
    private const ORDEN = 'ORDER BY t.creado_en DESC, t.id DESC';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Listado paginado con filtros. Las condiciones se arman con textos
     * estáticos y los valores SIEMPRE viajan enlazados (§4.1).
     *
     * @param array<string, mixed> $filtros Valores ya validados por el Service.
     * @return array{filas: list<array<string, mixed>>, total: int}
     */
    public function listar(array $filtros, int $pagina, int $porPagina): array
    {
        [$condiciones, $parametros] = $this->condicionesFiltros($filtros);

        $donde = implode(' AND ', $condiciones);

        $conteo = $this->pdo->prepare(
            "SELECT COUNT(*) AS total
             FROM tickets t
             JOIN usuarios u ON u.id = t.usuario_id
             WHERE {$donde}",
        );
        $conteo->execute($parametros);
        $total = (int) $conteo->fetch()['total'];

        $desplazamiento = ($pagina - 1) * $porPagina;
        $listado = $this->pdo->prepare(
            "SELECT t.id, t.numero, t.descripcion, t.prioridad, t.estado, t.solucion,
                    t.creado_en, t.vence_en,
                    u.codigo  AS usuario_codigo,
                    u.nombre  AS usuario_nombre,
                    u.cargo   AS usuario_cargo,
                    u.area    AS usuario_area,
                    c.nombre  AS categoria_nombre,
                    te.id     AS tecnico_id,
                    te.nombre AS tecnico_nombre
             FROM tickets t
             JOIN usuarios u   ON u.id = t.usuario_id
             JOIN categorias c ON c.id = t.categoria_id
             JOIN tecnicos te  ON te.id = t.tecnico_id
             WHERE {$donde}
             " . self::ORDEN . '
             LIMIT :limite OFFSET :desplazamiento',
        );
        foreach ($parametros as $clave => $valor) {
            $listado->bindValue($clave, $valor);
        }
        // Con prepares nativos (EMULATE_PREPARES=false, §4.2) LIMIT/OFFSET exigen enteros reales (D5)
        $listado->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $listado->bindValue(':desplazamiento', $desplazamiento, PDO::PARAM_INT);
        $listado->execute();

        return ['filas' => $listado->fetchAll(), 'total' => $total];
    }

    /** @return array<string, mixed>|null null si el número no existe. */
    public function buscarPorNumero(string $numero): ?array
    {
        $sentencia = $this->pdo->prepare(
            "SELECT t.id, t.numero, t.descripcion, t.prioridad, t.estado, t.solucion,
                    t.creado_en, t.vence_en, t.cerrado_en,
                    u.codigo  AS usuario_codigo,
                    u.nombre  AS usuario_nombre,
                    u.cargo   AS usuario_cargo,
                    u.area    AS usuario_area,
                    c.nombre  AS categoria_nombre,
                    te.id     AS tecnico_id,
                    te.nombre AS tecnico_nombre
             FROM tickets t
             JOIN usuarios u   ON u.id = t.usuario_id
             JOIN categorias c ON c.id = t.categoria_id
             JOIN tecnicos te  ON te.id = t.tecnico_id
             WHERE t.numero = :numero",
        );
        $sentencia->execute([':numero' => $numero]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * Historial completo de un ticket en orden cronológico (§6.9: toda creación,
     * cambio de estado o reasignación queda registrada).
     *
     * @return list<array<string, mixed>>
     */
    public function historialDe(int $ticketId): array
    {
        $sentencia = $this->pdo->prepare(
            'SELECT h.creado_en, h.tipo_cambio, h.estado_anterior, h.estado_nuevo, h.observacion,
                    ta.nombre AS tecnico_anterior,
                    tn.nombre AS tecnico_nuevo
             FROM ticket_historial h
             LEFT JOIN tecnicos ta ON ta.id = h.tecnico_anterior_id
             LEFT JOIN tecnicos tn ON tn.id = h.tecnico_nuevo_id
             WHERE h.ticket_id = :ticket_id
             ORDER BY h.creado_en ASC, h.id ASC',
        );
        $sentencia->execute([':ticket_id' => $ticketId]);

        return $sentencia->fetchAll();
    }

    /**
     * Total de tickets por estado para el tablero (§7). El Service completa
     * con cero los estados sin tickets para no partir de la BD.
     *
     * @return array<string, int>
     */
    public function conteosPorEstado(): array
    {
        $filas = $this->pdo
            ->query('SELECT estado, COUNT(*) AS total FROM tickets GROUP BY estado')
            ->fetchAll();

        $conteos = [];
        foreach ($filas as $fila) {
            $conteos[$fila['estado']] = (int) $fila['total'];
        }

        return $conteos;
    }

    /** Tickets no cerrados con SLA vencido, para el tablero (RN-08). */
    public function vencidosActivos(): int
    {
        $total = $this->pdo
            ->query("SELECT COUNT(*) AS total FROM tickets WHERE estado <> 'CERRADO' AND vence_en < NOW()")
            ->fetch()['total'];

        return (int) $total;
    }

    /**
     * Condiciones estáticas + parámetros enlazados: ninguna variable se
     * concatena al SQL (§4.1); solo crece la lista de condiciones.
     *
     * @param  array<string, mixed> $filtros
     * @return array{0: list<string>, 1: array<string, string>}
     */
    private function condicionesFiltros(array $filtros): array
    {
        $condiciones = ['1 = 1'];
        $parametros = [];

        if ($filtros['estado'] !== null) {
            $condiciones[] = 't.estado = :estado';
            $parametros[':estado'] = $filtros['estado'];
        }
        if ($filtros['prioridad'] !== null) {
            $condiciones[] = 't.prioridad = :prioridad';
            $parametros[':prioridad'] = $filtros['prioridad'];
        }
        if ($filtros['tecnico'] !== null) {
            $condiciones[] = 't.tecnico_id = :tecnico';
            $parametros[':tecnico'] = $filtros['tecnico'];
        }
        if ($filtros['q'] !== null && $filtros['q'] !== '') {
            // %, _ y \ del usuario se escapan: MySQL usa \ como escape del LIKE por defecto (D2).
            // Con prepares nativos un placeholder no puede repetirse: dos nombres, mismo valor (ver ai-log).
            $condiciones[] = '(t.numero LIKE :q_numero OR u.nombre LIKE :q_usuario)';
            $valorBusqueda = '%' . addcslashes((string) $filtros['q'], '\\%_') . '%';
            $parametros[':q_numero'] = $valorBusqueda;
            $parametros[':q_usuario'] = $valorBusqueda;
        }

        return [$condiciones, $parametros];
    }
}
