<?php
declare(strict_types=1);

namespace App\Support;

use PDO;
use Throwable;

/**
 * Única fábrica de conexiones PDO del proyecto (AGENTS.md §3, §4.2).
 * Atributos estrictos, UTF8MB4 y zona horaria America/Bogota (§2): las fechas
 * de MySQL y de PHP quedan iguales en hora local de Colombia (UTC-05:00).
 * Los Repositorios reciben el PDO por inyección (SOLID, §10 D).
 */
final class Database
{
    private ?PDO $conexion = null;

    /** @param array<string, mixed> $configDb Clave 'db' de la configuración. */
    public function __construct(private readonly array $configDb)
    {
    }

    public function conexion(): PDO
    {
        if ($this->conexion === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                (string) ($this->configDb['host'] ?? '127.0.0.1'),
                (int) ($this->configDb['puerto'] ?? 3306),
                (string) ($this->configDb['nombre'] ?? ''),
                (string) ($this->configDb['charset'] ?? 'utf8mb4'),
            );

            $this->conexion = new PDO(
                $dsn,
                (string) ($this->configDb['usuario'] ?? ''),
                (string) ($this->configDb['contrasena'] ?? ''),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            );

            // Hora de la BD = hora de la aplicación (§2); NOW() y date() coinciden
            $this->conexion->exec("SET time_zone = '-05:00'");
        }

        return $this->conexion;
    }

    /**
     * Único camino transaccional del proyecto: lo usan los Services (§3).
     * COMMIT si el callable termina bien; ROLLBACK y relanza si falla,
     * para que nunca queden medias transacciones (ej. ticket sin historial).
     */
    public function transaction(callable $fn): mixed
    {
        $pdo = $this->conexion();
        $pdo->beginTransaction();
        try {
            $resultado = $fn($pdo);
            $pdo->commit();

            return $resultado;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
