<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Acceso a datos de técnicos (§3). La carga activa cuenta tickets en
 * ABIERTO/EN CURSO (RN-05) y se calcula en SQL para traer todo en una vuelta.
 */
final class TecnicoRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Técnicos activos con su carga actual, del de menor a mayor carga
     * (la SPA sugiere por defecto el de menor carga, §7).
     *
     * @return list<array<string, mixed>>
     */
    public function listarActivosConCarga(): array
    {
        $sentencia = $this->pdo->prepare(
            "SELECT te.id, te.nombre,
                    (SELECT COUNT(*) FROM tickets t
                      WHERE t.tecnico_id = te.id
                        AND t.estado IN ('ABIERTO', 'EN CURSO')) AS carga_activa
             FROM tecnicos te
             WHERE te.activo = 1
             ORDER BY carga_activa ASC, te.nombre ASC",
        );
        $sentencia->execute();

        return $sentencia->fetchAll();
    }

    /**
     * Bloquea la fila del técnico (SELECT ... FOR UPDATE) dentro de la transacción
     * de creación: serializa el chequeo del tope de carga (RN-05, §6.1).
     *
     * @return array<string, mixed>|null null si el id no existe.
     */
    public function bloquearPorId(int $id): ?array
    {
        $sentencia = $this->pdo->prepare('SELECT id, nombre, activo FROM tecnicos WHERE id = :id FOR UPDATE');
        $sentencia->execute([':id' => $id]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    /** Tickets activos (ABIERTO/EN CURSO) del técnico: base del tope de carga (RN-05). */
    public function contarActivos(int $tecnicoId): int
    {
        $sentencia = $this->pdo->prepare(
            "SELECT COUNT(*) AS total FROM tickets
             WHERE tecnico_id = :tecnico_id AND estado IN ('ABIERTO', 'EN CURSO')",
        );
        $sentencia->execute([':tecnico_id' => $tecnicoId]);

        return (int) $sentencia->fetch()['total'];
    }
}
