<?php
declare(strict_types=1);

/**
 * Front Controller: único punto de entrada de la API (AGENTS.md §3).
 * Carga configuración, construye el router, aplica el middleware de API key
 * y captura TODA excepción devolviendo el formato único de error JSON (§5).
 * Jamás expone trazas PHP o SQL al cliente (§4.4).
 */

use App\Controllers\CategoriaController;
use App\Controllers\TecnicoController;
use App\Controllers\TicketController;
use App\Controllers\UsuarioController;
use App\Exceptions\ApiException;
use App\Http\Middlewares\ApiKeyMiddleware;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Repositories\CategoriaRepository;
use App\Repositories\TecnicoRepository;
use App\Repositories\TicketRepository;
use App\Repositories\UsuarioRepository;
use App\Services\CategoriaService;
use App\Services\TecnicoService;
use App\Services\TicketService;
use App\Services\UsuarioService;
use App\Support\Config;
use App\Support\Database;

// En el servidor de desarrollo, los archivos estáticos (assets) se sirven solos;
// index.php atiende la SPA (para inyectar la API key sin versionarla) y la API.
if (PHP_SAPI === 'cli-server') {
    $rutaSolicitada = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $esRutaSpa = is_string($rutaSolicitada) && ($rutaSolicitada === '/' || $rutaSolicitada === '/app.html');
    if (!$esRutaSpa && is_string($rutaSolicitada) && !str_contains($rutaSolicitada, '..')) {
        $archivoEstatico = __DIR__ . $rutaSolicitada;
        if (is_file($archivoEstatico)) {
            return false;
        }
    }
}

// display_errors = 0: ninguna respuesta puede traer HTML o PHP crudo (§4)
ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('America/Bogota'); // §2: todas las fechas en hora local de Colombia

$raiz = dirname(__DIR__);

$autoload = $raiz . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    // Fallback PSR-4 (decisión D1, spec fase 1): si Composer no estuvo disponible,
    // se registran las clases App\* desde src/ sin tocar cada archivo con require.
    spl_autoload_register(static function (string $clase) use ($raiz): void {
        $prefijo = 'App\\';
        if (!str_starts_with($clase, $prefijo)) {
            return;
        }
        $ruta = $raiz . '/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
        if (is_file($ruta)) {
            require $ruta;
        }
    });
}

// Log interno: la traza completa queda en storage/logs/ (git ignoraría los .log)
// y el cliente solo ve el 500 genérico (decisión D4, spec fase 1).
$registrarError = static function (Throwable $error) use ($raiz): void {
    $mensaje = sprintf(
        "[%s] %s: %s en %s:%d%s%s",
        date('c'),
        $error::class,
        $error->getMessage(),
        $error->getFile(),
        $error->getLine(),
        PHP_EOL,
        $error->getTraceAsString(),
    );
    $directorio = $raiz . '/storage/logs';
    if (is_dir($directorio) || @mkdir($directorio, 0775, true)) {
        error_log($mensaje . PHP_EOL, 3, $directorio . '/error_' . date('Y-m-d') . '.log');
    } else {
        error_log($mensaje); // fallback: log del servidor web
    }
};

try {
    Config::cargar($raiz . '/config/config.php');

    // SPA (§7): se sirve app.html inyectando la API key desde config (secreto NO versionado).
    // Si se abre app.html como archivo estático (Apache/nginx), el marcador queda y la SPA
    // pide la clave por pantalla (ver assets/js/api.js).
    if (PHP_SAPI === 'cli-server') {
        $rutaSolicitada = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if ($rutaSolicitada === '/' || $rutaSolicitada === '/app.html') {
            $plantilla = file_get_contents(__DIR__ . '/app.html');
            echo str_replace('__CLAVE_API__', htmlspecialchars(Config::apiKey(), ENT_QUOTES), (string) $plantilla);
            return;
        }
    }

    // Inyección manual de dependencias (§10 D): PDO → Repos → Services → Controllers.
    // Si la BD no responde, PDOException cae al catch global ⇒ 500 sin trazas.
    $database = new Database(Config::obtener('db'));
    $conexion = $database->conexion();
    $ticketRepository = new TicketRepository($conexion);
    $tecnicoRepository = new TecnicoRepository($conexion);
    $usuarioRepository = new UsuarioRepository($conexion);
    $categoriaRepository = new CategoriaRepository($conexion);

    $ticketService = new TicketService(
        $ticketRepository,
        $tecnicoRepository,
        $usuarioRepository,
        $categoriaRepository,
        $database,
    );
    $ticketController = new TicketController($ticketService);
    $usuarioController = new UsuarioController(new UsuarioService($usuarioRepository));
    $tecnicoController = new TecnicoController(new TecnicoService($tecnicoRepository));
    $categoriaController = new CategoriaController(new CategoriaService($categoriaRepository));

    $router = new Router();
    $router->usarMiddleware(new ApiKeyMiddleware());

    // Ruta de salud (no es de negocio): permite probar el esqueleto sin BD (CA-4.4)
    $router->agregar('GET', '/api/ping', static fn (): Response => Response::json(['status' => 'ok']));

    $router->agregar('GET', '/api/tickets', [$ticketController, 'listar']);
    $router->agregar('GET', '/api/tickets/export.csv', [$ticketController, 'exportarCsv']);
    $router->agregar('POST', '/api/tickets', [$ticketController, 'crear']);
    $router->agregar('GET', '/api/tickets/{numero}', [$ticketController, 'detalle']);
    $router->agregar('PATCH', '/api/tickets/{numero}/estado', [$ticketController, 'cambiarEstado']);
    $router->agregar('PATCH', '/api/tickets/{numero}/tecnico', [$ticketController, 'reasignarTecnico']);
    $router->agregar('GET', '/api/resumen', [$ticketController, 'resumen']);
    $router->agregar('GET', '/api/usuarios/{codigo}', [$usuarioController, 'buscar']);
    $router->agregar('GET', '/api/tecnicos', [$tecnicoController, 'listar']);
    $router->agregar('GET', '/api/categorias', [$categoriaController, 'listar']);

    $router->resolver(Request::desdeGlobals())->enviar();
} catch (ApiException $e) {
    // Errores con significado HTTP: conservan su código y su código de negocio (§5)
    Response::error($e->codigo(), $e->getMessage(), $e->estado(), $e->fields())->enviar();
} catch (Throwable $e) {
    // Inesperado (config faltante, BD caída, fallo de json_encode...): 500 genérico (§4.4)
    $registrarError($e);
    Response::error('ERROR_INTERNO', 'Error interno del servidor. Contacte al administrador.', 500)->enviar();
}
