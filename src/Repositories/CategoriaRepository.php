<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Acceso a datos del catálogo de categorías (§3).
 * El catálogo vive en BD, nunca quemado en el HTML (§7, RN-04).
 */
final class CategoriaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listarTodas(): array
    {
        $sentencia = $this->pdo->query('SELECT id, nombre FROM categorias ORDER BY nombre ASC');

        return $sentencia->fetchAll();
    }
}
