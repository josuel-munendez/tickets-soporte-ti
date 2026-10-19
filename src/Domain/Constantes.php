<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Constantes del dominio (AGENTS.md §6): son DATOS del negocio, no ifs dispersos.
 * Centralizarlas aquí permite cambiar una regla en un solo lugar (§10 O).
 */
final class Constantes
{
    /** RN-05: máximo de tickets activos (ABIERTO/EN CURSO) por técnico. */
    public const MAX_TICKETS_TECNICO = 5;

    /** RN-06: máquina de estados en orden estricto; prohibido saltar o retroceder. */
    public const ESTADOS = ['ABIERTO', 'EN CURSO', 'CERRADO'];

    /** RN-04: prioridades válidas de un ticket. */
    public const PRIORIDADES = ['Alta', 'Media', 'Baja'];

    /** Estados que cuentan como "carga activa" de un técnico (RN-05). */
    public const ESTADOS_ACTIVOS = ['ABIERTO', 'EN CURSO'];

    private function __construct()
    {
    }
}
