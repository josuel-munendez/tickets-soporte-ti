<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\TicketService;

/**
 * Endpoints GET de tickets (§3: Controller delgado — cero SQL, cero lógica
 * de negocio; solo traduce Request → Service → Response del contrato §5).
 */
final class TicketController
{
    public function __construct(private readonly TicketService $tickets)
    {
    }

    /** GET /api/tickets — lista paginada con filtros de la query string. */
    public function listar(Request $peticion): Response
    {
        $resultado = $this->tickets->listar([
            'estado' => $peticion->parametro('estado'),
            'prioridad' => $peticion->parametro('prioridad'),
            'tecnico' => $peticion->parametro('tecnico'),
            'q' => $peticion->parametro('q'),
            'page' => $peticion->parametro('page'),
            'per_page' => $peticion->parametro('per_page'),
        ]);

        return Response::json($resultado);
    }

    /** POST /api/tickets — crea un ticket y responde 201 con el recurso creado (§5). */
    public function crear(Request $peticion): Response
    {
        return Response::json($this->tickets->crear($peticion->json()), 201);
    }

    /** GET /api/tickets/{numero} — detalle con historial completo. */
    public function detalle(Request $peticion, array $parametros): Response
    {
        return Response::json($this->tickets->detalle($parametros['numero'] ?? ''));
    }

    /** GET /api/tickets/resumen — métricas del tablero superior. */
    public function resumen(Request $peticion): Response
    {
        return Response::json($this->tickets->resumen());
    }
}
