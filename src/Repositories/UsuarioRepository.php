<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Acceso a datos de usuarios solicitantes (único lugar con SQL, §3).
 * Los nombres de campos coinciden exactamente con database/schema.sql (§8).
 */
final class UsuarioRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null null si el código no existe. */
    public function buscarPorCodigo(string $codigo): ?array
    {
        $sentencia = $this->pdo->prepare(
            'SELECT id, codigo, nombre, cargo, area, activo
             FROM usuarios
             WHERE codigo = :codigo',
        );
        $sentencia->execute([':codigo' => $codigo]);
        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }
}
