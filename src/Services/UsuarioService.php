<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NoEncontradoException;
use App\Repositories\UsuarioRepository;

/**
 * Consulta de usuarios solicitantes (§3). Para el formulario de registro la
 * SPA necesita confirmar que el código existe y el usuario está activo (RN-02).
 */
final class UsuarioService
{
    public function __construct(private readonly UsuarioRepository $usuarios)
    {
    }

    /**
     * Devuelve los datos básicos del solicitante o 404 si no existe o está
     * inactivo (decisión D4, spec fase 3): el formulario deshabilita el
     * registro ante cualquier 404; la validación fuerte se reitera en el POST.
     *
     * @return array<string, string>
     */
    public function buscarParaRegistro(string $codigo): array
    {
        $usuario = $this->usuarios->buscarPorCodigo($codigo);
        if ($usuario === null || (int) $usuario['activo'] !== 1) {
            throw new NoEncontradoException('Usuario no encontrado o inactivo.');
        }

        return [
            'codigo' => $usuario['codigo'],
            'nombre' => $usuario['nombre'],
            'cargo' => $usuario['cargo'],
            'area' => $usuario['area'],
        ];
    }
}
