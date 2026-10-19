<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\UsuarioService;

/** GET /api/usuarios/{codigo} — datos del solicitante para la captura (§5, §7). */
final class UsuarioController
{
    public function __construct(private readonly UsuarioService $usuarios)
    {
    }

    public function buscar(Request $peticion, array $parametros): Response
    {
        return Response::json($this->usuarios->buscarParaRegistro($parametros['codigo'] ?? ''));
    }
}
