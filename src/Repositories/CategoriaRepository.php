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

    /** RN-04: la categoría de un ticket nuevo debe existir en BD. */
    public function existe(int $id): bool
    {
        $sentencia = $this->pdo->prepare('SELECT 1 FROM categorias WHERE id = :id');
        $sentencia->execute([':id' => $id]);

        return $sentencia->fetch() !== false;
    }
}
