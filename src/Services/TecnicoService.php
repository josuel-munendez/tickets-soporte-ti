<?php
declare(strict_types=1);

namespace App\Services;

use App\Domain\Constantes;
use App\Repositories\TecnicoRepository;

/**
 * Consulta de técnicos (§3, §7): la lista muestra carga actual y permite a la
 * SPA preseleccionar al de menor carga y deshabilitar a los del tope (RN-05).
 */
final class TecnicoService
{
    public function __construct(private readonly TecnicoRepository $tecnicos)
    {
    }

    /** @return list<array<string, mixed>> */
    public function listarConCarga(): array
    {
        return array_map(
            function (array $fila): array {
                $activa = (int) $fila['carga_activa'];

                return [
                    'id' => (int) $fila['id'],
                    'nombre' => $fila['nombre'],
                    'carga_activa' => $activa,
                    'disponible' => $activa < Constantes::MAX_TICKETS_TECNICO,
                ];
            },
            $this->tecnicos->listarActivosConCarga(),
        );
    }
}
