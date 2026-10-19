<?php
declare(strict_types=1);

namespace App\Exceptions;

/**
 * Error de validación de datos de entrada → HTTP 422, código VALIDACION (§5).
 * Ej.: usuario inactivo, descripción corta, prioridad desconocida, JSON malformado.
 */
final class ValidacionException extends ApiException
{
    /** @param array<string, string> $fields {campo: mensaje} para marcar errores en el formulario. */
    public function __construct(string $mensaje, array $fields = [])
    {
        parent::__construct($mensaje, 422, 'VALIDACION', $fields);
    }
}
