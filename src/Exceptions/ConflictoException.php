<?php
declare(strict_types=1);

namespace App\Exceptions;

/**
 * Conflicto con el estado actual del recurso → HTTP 409 (§5).
 * El código se parametriza porque un 409 puede ser TRANSICION_INVALIDA (RN-06)
 * o TECNICO_AL_TOPE (RN-05), según la regla de negocio que lo lance.
 */
final class ConflictoException extends ApiException
{
    public function __construct(string $mensaje, string $codigo = 'TRANSICION_INVALIDA')
    {
        parent::__construct($mensaje, 409, $codigo);
    }
}
