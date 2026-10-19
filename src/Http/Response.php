<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Respuesta JSON inmutable (AGENTS.md §3, capa Http).
 * Toda la API responde application/json + X-Content-Type-Options: nosniff (§4.7)
 * y los errores usan siempre el formato único {"error":{code,message,fields}} (§5).
 */
final class Response
{
    /** @var array<string, string> Encabezados adicionales (ej. Allow, Content-Disposition). */
    private array $encabezados;

    /** Cuerpo no-JSON (ej. CSV). Si es null se serializa $datos como JSON. */
    private ?string $cuerpoCrudo = null;

    private string $tipoContenido = 'application/json; charset=utf-8';

    /**
     * @param array<string, mixed>  $datos      Cuerpo JSON de la respuesta.
     * @param array<string, string> $encabezados Encabezados adicionales (ej. Allow).
     */
    private function __construct(
        private readonly int $estado,
        private readonly array $datos,
        array $encabezados = [],
    ) {
        $this->encabezados = $encabezados;
    }

    /** @param array<string, mixed> $datos */
    public static function json(array $datos, int $estado = 200): self
    {
        return new self($estado, $datos);
    }

    /**
     * Respuesta cruda (no JSON) para descargas, ej. exportación CSV (§7).
     * Fuerza el tipo MIME y un nombre de archivo seguro.
     */
    public static function descarga(string $cuerpo, string $tipoContenido, string $nombreArchivo): self
    {
        $respuesta = new self(200, []);
        $respuesta->cuerpoCrudo = $cuerpo;
        $respuesta->tipoContenido = $tipoContenido;
        $respuesta->encabezados['Content-Disposition'] = 'attachment; filename="' . $nombreArchivo . '"';

        return $respuesta;
    }

    /**
     * Fábrica del formato único de error, sin excepciones (§5):
     * 401 NO_AUTORIZADO, 404 NO_ENCONTRADO, 405 METODO_NO_PERMITIDO,
     * 409 TRANSICION_INVALIDA/TECNICO_AL_TOPE, 422 VALIDACION, 500 ERROR_INTERNO.
     *
     * @param array<string, string> $fields
     */
    public static function error(string $codigo, string $mensaje, int $estado, array $fields = []): self
    {
        return new self($estado, [
            'error' => [
                'code' => $codigo,
                'message' => $mensaje,
                'fields' => (object) $fields, // objeto vacío {} aunque no haya fields
            ],
        ]);
    }

    /** Copia de la respuesta con un encabezado extra (ej. Allow en 405). */
    public function conEncabezado(string $nombre, string $valor): self
    {
        $encabezados = $this->encabezados;
        $encabezados[$nombre] = $valor;

        return new self($this->estado, $this->datos, $encabezados);
    }

    public function estado(): int
    {
        return $this->estado;
    }

    /** @return array<string, mixed> */
    public function datos(): array
    {
        return $this->datos;
    }

    /**
     * Emite la respuesta. Si json_encode falla (UTF-8 inválido), el Front
     * Controller la convierte en 500 genérico: nunca se envía cuerpo corrupto.
     */
    public function enviar(): void
    {
        http_response_code($this->estado);
        header('Content-Type: ' . $this->tipoContenido);
        header('X-Content-Type-Options: nosniff');
        foreach ($this->encabezados as $nombre => $valor) {
            header("{$nombre}: {$valor}");
        }

        if ($this->cuerpoCrudo !== null) {
            echo $this->cuerpoCrudo;

            return;
        }

        $cuerpo = json_encode(
            $this->datos,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        echo $cuerpo;
    }
}
