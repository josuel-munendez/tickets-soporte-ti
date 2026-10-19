<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\CategoriaRepository;

/**
 * Consulta del catálogo de categorías (§3): siempre desde BD,
 * nunca quemado en el HTML (§7).
 */
final class CategoriaService
{
    public function __construct(private readonly CategoriaRepository $categorias)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        return $this->categorias->listarTodas();
    }
}
