<?php
declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Base de las excepciones con significado HTTP (formato único de error, AGENTS.md §5).
 * El Front Controller las captura y las traduce a JSON {"error":{code,message,fields}}
 * sin exponer nunca trazas PHP o SQL al cliente (§4.4).
 */
abstract class ApiException extends Exception
{
    /** @param array<string, string> $fields Errores por campo (solo se usan en 422). */
    public function __construct(
        string $mensaje,
        private readonly int $estado,
        private readonly string $codigo,
        private readonly array $fields = [],
    ) {
        parent::__construct($mensaje);
    }

    /** Código HTTP de la respuesta. */
    public function estado(): int
    {
        return $this->estado;
    }

    /** Código del negocio del error (ej. VALIDACION, TRANSICION_INVALIDA). */
    public function codigo(): string
    {
        return $this->codigo;
    }

    /** @return array<string, string> Mapa {campo: mensaje} para 422. */
    public function fields(): array
    {
        return $this->fields;
    }
}
