<?php
/**
 * Plantilla de configuración versionada (AGENTS.md §4.6).
 * Copia este archivo a config/config.php y coloca los valores reales.
 * config/config.php está en .gitignore: NUNCA se versiona.
 */
declare(strict_types=1);

return [
    'db' => [
        'host' => '127.0.0.1',
        'puerto' => 3306,
        'nombre' => 'soporte_ti',
        'usuario' => 'root',
        'contrasena' => '',              // placeholder: la contraseña real solo vive en config.php
        'charset' => 'utf8mb4',
    ],
    'api_key' => 'tu_clave_secreta_aqui', // placeholder: se compara con hash_equals() en el middleware
    'zona_horaria' => 'America/Bogota',
];
