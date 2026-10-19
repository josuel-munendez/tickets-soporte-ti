<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\CategoriaService;

/** GET /api/categorias — catálogo desde BD para el formulario (§5, §7). */
final class CategoriaController
{
    public function __construct(private readonly CategoriaService $categorias)
    {
    }

    public function listar(Request $peticion): Response
    {
        return Response::json(['data' => $this->categorias->listar()]);
    }
}
