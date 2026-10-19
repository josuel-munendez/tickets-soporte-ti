<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\TecnicoService;

/** GET /api/tecnicos — técnicos activos con su carga actual (§5, §7). */
final class TecnicoController
{
    public function __construct(private readonly TecnicoService $tecnicos)
    {
    }

    public function listar(Request $peticion): Response
    {
        return Response::json(['data' => $this->tecnicos->listarConCarga()]);
    }
}
