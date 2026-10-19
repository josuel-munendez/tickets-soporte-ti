<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\CategoriaRepository;
use App\Repositories\TecnicoRepository;
use App\Repositories\TicketRepository;
use App\Repositories\UsuarioRepository;
use App\Services\TicketService;
use App\Support\Config;
use App\Support\Database;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Base de las pruebas de integración (RN-01/05/06/09).
 *
 * Se conecta a la MISMA base de datos del desarrollo usando config/config.php
 * y limpia todo lo que crea. Si no hay configuración o BD disponible, las
 * pruebas se marcan como omitidas (skipped) en lugar de fallar, para que la
 * suite unitaria siga siendo ejecutable en un entorno sin MySQL.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected PDO $pdo;
    protected TicketService $tickets;
    protected Database $database;

    /** Números de ticket creados por la prueba, para borrarlos al final. */
    private array $numerosCreados = [];

    protected function setUp(): void
    {
        $raiz = dirname(__DIR__, 2);
        $archivo = $raiz . '/config/config.php';
        if (!is_file($archivo)) {
            self::markTestSkipped('No hay config/config.php: se omiten las pruebas de integración.');
        }

        Config::cargar($archivo);

        $database = new Database(Config::obtener('db'));
        try {
            $pdo = $database->conexion();
        } catch (PDOException $e) {
            self::markTestSkipped('No se pudo conectar a MySQL: ' . $e->getMessage());
        }

        $this->pdo = $pdo;
        $this->database = $database;
        $this->tickets = new TicketService(
            new TicketRepository($pdo),
            new TecnicoRepository($pdo),
            new UsuarioRepository($pdo),
            new CategoriaRepository($pdo),
            $database,
        );

        $this->reconstruirConsecutivo();
    }

    protected function tearDown(): void
    {
        if (!isset($this->pdo)) {
            return;
        }

        // Limpieza: borra lo creado por la prueba (historial primero por la FK).
        foreach ($this->numerosCreados as $numero) {
            $id = $this->pdo->prepare('SELECT id FROM tickets WHERE numero = :n');
            $id->execute([':n' => $numero]);
            $fila = $id->fetch();
            if ($fila === false) {
                continue;
            }
            $this->pdo->prepare('DELETE FROM ticket_historial WHERE ticket_id = :id')
                ->execute([':id' => $fila['id']]);
            $this->pdo->prepare('DELETE FROM tickets WHERE id = :id')
                ->execute([':id' => $fila['id']]);
        }
    }

    /** Registra un número para que tearDown lo borre. */
    protected function recordarNumero(string $numero): void
    {
        $this->numerosCreados[] = $numero;
    }

    /** @return array<string, mixed> Fila cruda del ticket. */
    protected function filaTicket(string $numero): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tickets WHERE numero = :n');
        $stmt->execute([':n' => $numero]);
        $fila = $stmt->fetch();
        self::assertIsArray($fila, "No existe el ticket {$numero}");

        return $fila;
    }

    /**
     * Deja el consecutivo del año apuntando al máximo real de tickets, para que
     * la creación de prueba use el siguiente número disponible sin chocar.
     */
    private function reconstruirConsecutivo(): void
    {
        $anio = (int) date('Y');
        $max = $this->pdo->prepare(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(numero, 9) AS UNSIGNED)), 0) AS maximo
             FROM tickets WHERE numero LIKE :patron",
        );
        $max->execute([':patron' => "TK-{$anio}-%"]);
        $maximo = (int) $max->fetch()['maximo'];

        $this->pdo->prepare(
            'INSERT INTO consecutivos_ticket (anio, ultimo) VALUES (:anio, :ultimo)
             ON DUPLICATE KEY UPDATE ultimo = GREATEST(ultimo, :ultimo2)',
        )->execute([':anio' => $anio, ':ultimo' => $maximo, ':ultimo2' => $maximo]);
    }
}
