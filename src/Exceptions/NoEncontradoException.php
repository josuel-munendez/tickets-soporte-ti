<?php
declare(strict_types=1);

namespace App\Exceptions;

/**
 * Recurso inexistente → HTTP 404, código NO_ENCONTRADO (§5).
 * Ej.: ticket o usuario con ese número/código no hallados.
 */
final class NoEncontradoException extends ApiException
{
    public function __construct(string $mensaje = 'Recurso no encontrado.')
    {
        parent::__construct($mensaje, 404, 'NO_ENCONTRADO');
    }
}
