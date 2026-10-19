-- =====================================================================
-- Sistema de Tickets de Soporte TI — Datos semilla (MySQL 8.0+)
-- Uso:  mysql -u root -p < database/schema.sql
--       mysql -u root -p < database/seed.sql
--
-- Contenido: 10 usuarios (1 inactivo), 4 técnicos (1 inactivo),
-- 4 categorías y 25 tickets (12 activos + 13 cerrados) con su historial.
--
-- Decisiones:
--  * Las fechas son relativas a NOW() para que siempre haya tickets
--    VENCIDOS y POR VENCER al momento de la demostración.
--  * Carga activa (ABIERTO + EN CURSO) respeta el tope de 5:
--    técnico 1 = 5, técnico 2 = 4, técnico 3 = 3, técnico 4 (inactivo) = 0.
--  * El número TK-AAAA-NNNNNN usa el año actual; el consecutivo del año
--    queda en 25 para que el siguiente ticket sea el 26.
--  * Se puede ejecutar varias veces: limpia las tablas antes de cargar.
-- =====================================================================

USE soporte_ti;
SET NAMES utf8mb4;
SET time_zone = '-05:00';                 -- America/Bogota (sin horario de verano)
SET @ahora = NOW();
SET @anio  = YEAR(@ahora);

-- ---------------------------------------------------------------------
-- Limpieza (el catálogo de categorías se conserva)
-- ---------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE ticket_historial;
TRUNCATE TABLE tickets;
TRUNCATE TABLE consecutivos_ticket;
TRUNCATE TABLE usuarios;
TRUNCATE TABLE tecnicos;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Categorías (idempotente; el esquema ya las crea en este mismo orden)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO categorias (nombre) VALUES ('Hardware'), ('Software'), ('Red'), ('Otro');

-- ---------------------------------------------------------------------
-- Usuarios solicitantes (U010 está inactivo)
-- ---------------------------------------------------------------------
INSERT INTO usuarios (id, codigo, nombre, cargo, area, activo) VALUES
  (1,  'U001', 'Ana María Torres',     'Analista de Ventas',          'Comercial',      1),
  (2,  'U002', 'Jorge Enrique Salazar','Ejecutivo de Cuenta',         'Comercial',      1),
  (3,  'U003', 'Paola Andrea Mejía',   'Contadora',                   'Contabilidad',   1),
  (4,  'U004', 'Luis Fernando Rojas',  'Auxiliar de Compras',         'Compras',        1),
  (5,  'U005', 'Diana Carolina Pardo', 'Coordinadora de RR. HH.',     'Talento Humano', 1),
  (6,  'U006', 'Camilo Andrés Vega',   'Jefe de Logística',           'Logística',      1),
  (7,  'U007', 'Natalia Ospina',       'Auxiliar de Bodega',          'Logística',      1),
  (8,  'U008', 'Ricardo Herrera',      'Analista de Facturación',     'Facturación',    1),
  (9,  'U009', 'Sandra Milena Cruz',   'Asistente Administrativa',    'Administración', 1),
  (10, 'U010', 'Mauricio Beltrán',     'Asesor de Atención al Cliente','Servicio',      0);

-- ---------------------------------------------------------------------
-- Técnicos (el 4 está inactivo: solo aparece en tickets ya cerrados)
-- ---------------------------------------------------------------------
INSERT INTO tecnicos (id, nombre, activo) VALUES
  (1, 'Carlos Ramírez', 1),
  (2, 'Laura Gómez',    1),
  (3, 'Andrés Pérez',   1),
  (4, 'Marta Díaz',     0);

-- ---------------------------------------------------------------------
-- Tickets: se definen en una tabla temporal con tiempos relativos y luego
-- se calculan número, fecha límite SLA (Alta 4 h, Media 24 h, Baja 72 h)
-- y fechas de cierre con una sola regla, igual que lo hará la aplicación.
--   min_atras  = minutos desde la creación hasta ahora
--   min_cierre = minutos desde la creación hasta el cierre (solo CERRADO)
-- ---------------------------------------------------------------------
CREATE TEMPORARY TABLE seed_tickets (
  id          INT UNSIGNED     NOT NULL,
  usuario_id  INT UNSIGNED     NOT NULL,
  categoria_id TINYINT UNSIGNED NOT NULL,
  descripcion VARCHAR(1000)    NOT NULL,
  prioridad   VARCHAR(5)       NOT NULL,
  tecnico_id  INT UNSIGNED     NOT NULL,
  estado      VARCHAR(8)       NOT NULL,
  solucion    TEXT             NULL,
  min_atras   INT              NOT NULL,
  min_cierre  INT              NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

INSERT INTO seed_tickets
  (id, usuario_id, categoria_id, descripcion, prioridad, tecnico_id, estado, solucion, min_atras, min_cierre)
VALUES
  -- ===== ACTIVOS: técnico 1 (5 tickets, tope) =====
  (1,  1, 3, 'La VPN corporativa se desconecta cada pocos minutos y no permite acceder al ERP',            'Alta',  1, 'EN CURSO', NULL,  360, NULL),  -- VENCIDO
  (2,  2, 1, 'El equipo no enciende después de un corte de energía en la oficina de ventas',               'Alta',  1, 'ABIERTO',  NULL,  210, NULL),  -- POR VENCER
  (3,  3, 2, 'Outlook no sincroniza el correo institucional y muestra error de credenciales',              'Media', 1, 'EN CURSO', NULL, 1800, NULL),  -- VENCIDO
  (4,  4, 4, 'Solicitud de instalación de un segundo monitor en el puesto de contabilidad',                'Baja',  1, 'ABIERTO',  NULL,  300, NULL),  -- EN TIEMPO
  (5,  5, 2, 'No se puede iniciar sesión en la plataforma de ventas Rsales desde el portátil',             'Media', 1, 'ABIERTO',  NULL, 1140, NULL),  -- POR VENCER
  -- ===== ACTIVOS: técnico 2 (4 tickets) =====
  (6,  6, 3, 'Sin conexión a internet en el segundo piso, el punto de red no responde',                    'Alta',  2, 'EN CURSO', NULL,   60, NULL),  -- EN TIEMPO
  (7,  7, 1, 'La impresora del área de bodega atasca el papel y deja líneas en las hojas',                 'Baja',  2, 'EN CURSO', NULL, 4800, NULL),  -- VENCIDO
  (8,  8, 2, 'Instalar y configurar Microsoft Teams en el equipo nuevo de recursos humanos',               'Media', 2, 'ABIERTO',  NULL,  120, NULL),  -- EN TIEMPO
  (9,  9, 4, 'Actualizar el inventario de activos del área administrativa con los equipos nuevos',         'Baja',  2, 'ABIERTO',  NULL, 3360, NULL),  -- POR VENCER
  -- ===== ACTIVOS: técnico 3 (3 tickets) =====
  (10, 1, 1, 'La pantalla del portátil parpadea y se apaga durante las videollamadas',                     'Alta',  3, 'ABIERTO',  NULL,  300, NULL),  -- VENCIDO
  (11, 3, 2, 'Error al emitir facturas en la plataforma de facturación electrónica Dispapeles',            'Media', 3, 'EN CURSO', NULL,  600, NULL),  -- EN TIEMPO
  (12, 5, 3, 'Wifi inestable en la sala de juntas durante las reuniones de la tarde',                      'Baja',  3, 'EN CURSO', NULL, 1200, NULL),  -- EN TIEMPO
  -- ===== CERRADOS: técnico 4 (inactivo; los 13 y 14 fueron reasignados desde el técnico 1) =====
  (13, 10, 1, 'Cambio de teclado y mouse defectuosos en el puesto de atención al cliente',                 'Baja',  4, 'CERRADO', 'Se reemplazaron teclado y mouse por unidades nuevas del inventario',                14400,  300),
  (14, 2,  2, 'Instalación de Office 365 y configuración de OneDrive en equipo nuevo',                     'Media', 4, 'CERRADO', 'Se instaló Office 365, se configuró OneDrive y se validó el acceso con el usuario', 12960,  180),
  (15, 4,  3, 'Cable de red dañado en el puesto de compras, sin conectividad',                             'Alta',  4, 'CERRADO', 'Se cambió el cable de red y se verificó la conexión a la red corporativa',          11520,  150),
  -- ===== CERRADOS: técnico 1 =====
  (16, 6,  2, 'Restablecer contraseña de la cuenta de correo y de Teams',                                  'Baja',  1, 'CERRADO', 'Se restableció la contraseña y se confirmó el acceso a correo y Teams',             10080,  240),
  (17, 7,  1, 'Mantenimiento preventivo y limpieza de equipos del área de logística',                      'Media', 1, 'CERRADO', 'Se realizó limpieza interna, cambio de pasta térmica y revisión de periféricos',     8640, 1200),
  (18, 8,  4, 'Solicitud de acceso a carpeta compartida de SharePoint para el nuevo analista',             'Baja',  1, 'CERRADO', 'Se otorgaron permisos de lectura y escritura en la carpeta de SharePoint',           7200,  420),
  -- ===== CERRADOS: técnico 2 (20 y 21 cerraron fuera del SLA) =====
  (19, 9,  3, 'Revisión de alertas de acceso sospechoso en el firewall Fortigate',                         'Alta',  2, 'CERRADO', 'Se revisaron los registros, se bloqueó la IP de origen y se documentó el incidente', 5760,  200),
  (20, 1,  2, 'La aplicación Megaservice se cierra sola al abrir los reportes mensuales',                  'Media', 2, 'CERRADO', 'Se reinstaló la aplicación y se actualizó el cliente a la última versión',           4320, 1600),
  (21, 3,  1, 'Disco duro lento en el portátil de gerencia, tarda varios minutos en iniciar',              'Alta',  2, 'CERRADO', 'Se reemplazó el disco por uno de estado sólido y se restauró la imagen del equipo',  2880,  360),
  (22, 5,  4, 'Capacitación al personal nuevo sobre el uso de Teams y OneDrive',                           'Baja',  2, 'CERRADO', 'Se dictó la capacitación de dos horas y se entregó el manual de usuario',            2160,  600),
  -- ===== CERRADOS: técnico 3 =====
  (23, 2,  3, 'Cambio de configuración del switch para habilitar un nuevo punto de red',                   'Media', 3, 'CERRADO', 'Se configuró el puerto del switch en la VLAN correcta y se probó el punto',          1500,  400),
  (24, 4,  2, 'Error de certificado al ingresar a la aplicación Orange desde el navegador',                'Alta',  3, 'CERRADO', 'Se instaló el certificado raíz corporativo y se limpió la caché del navegador',      1000,  180),
  (25, 6,  1, 'Configuración de equipo de cómputo nuevo para el área comercial',                           'Baja',  3, 'CERRADO', 'Se instaló el sistema, el software corporativo y se entregó el equipo al usuario',    700,  300);

INSERT INTO tickets
  (id, numero, usuario_id, categoria_id, descripcion, prioridad, tecnico_id, estado, solucion,
   creado_en, vence_en, actualizado_en, cerrado_en)
SELECT
  x.id,
  CONCAT('TK-', @anio, '-', LPAD(x.id, 6, '0')),
  x.usuario_id, x.categoria_id, x.descripcion, x.prioridad, x.tecnico_id, x.estado, x.solucion,
  x.creado,
  DATE_ADD(x.creado, INTERVAL CASE x.prioridad WHEN 'Alta' THEN 4 WHEN 'Media' THEN 24 ELSE 72 END HOUR),
  CASE x.estado
    WHEN 'CERRADO'  THEN DATE_ADD(x.creado, INTERVAL x.min_cierre MINUTE)
    WHEN 'EN CURSO' THEN DATE_ADD(x.creado, INTERVAL 10 MINUTE)
    ELSE x.creado
  END,
  IF(x.estado = 'CERRADO', DATE_ADD(x.creado, INTERVAL x.min_cierre MINUTE), NULL)
FROM (
  SELECT s.*, DATE_SUB(@ahora, INTERVAL s.min_atras MINUTE) AS creado
  FROM seed_tickets s
) x;

DROP TEMPORARY TABLE seed_tickets;

-- ---------------------------------------------------------------------
-- Historial
-- ---------------------------------------------------------------------
-- 1) Creación de todos los tickets (siempre nacen ABIERTO)
INSERT INTO ticket_historial
  (ticket_id, tipo_cambio, estado_anterior, estado_nuevo, tecnico_anterior_id, tecnico_nuevo_id, observacion, creado_en)
SELECT t.id, 'CREACION', NULL, 'ABIERTO', NULL, t.tecnico_id, 'Ticket registrado', t.creado_en
FROM tickets t;

-- 2) Reasignación de los tickets 13 y 14 al técnico 4 (antes los tenía el técnico 1)
INSERT INTO ticket_historial
  (ticket_id, tipo_cambio, estado_anterior, estado_nuevo, tecnico_anterior_id, tecnico_nuevo_id, observacion, creado_en)
SELECT t.id, 'REASIGNACION', 'ABIERTO', 'ABIERTO', 1, t.tecnico_id,
       'Reasignado por balanceo de carga del equipo', DATE_ADD(t.creado_en, INTERVAL 5 MINUTE)
FROM tickets t
WHERE t.id IN (13, 14);

-- 3) ABIERTO -> EN CURSO (tickets en curso y cerrados)
INSERT INTO ticket_historial
  (ticket_id, tipo_cambio, estado_anterior, estado_nuevo, tecnico_anterior_id, tecnico_nuevo_id, observacion, creado_en)
SELECT t.id, 'ESTADO', 'ABIERTO', 'EN CURSO', NULL, NULL,
       'El técnico inicia la atención del ticket', DATE_ADD(t.creado_en, INTERVAL 10 MINUTE)
FROM tickets t
WHERE t.estado IN ('EN CURSO', 'CERRADO');

-- 4) EN CURSO -> CERRADO (la observación es la solución aplicada)
INSERT INTO ticket_historial
  (ticket_id, tipo_cambio, estado_anterior, estado_nuevo, tecnico_anterior_id, tecnico_nuevo_id, observacion, creado_en)
SELECT t.id, 'ESTADO', 'EN CURSO', 'CERRADO', NULL, NULL, t.solucion, t.cerrado_en
FROM tickets t
WHERE t.estado = 'CERRADO';

-- ---------------------------------------------------------------------
-- Consecutivo del año: el próximo ticket será el 26
-- ---------------------------------------------------------------------
INSERT INTO consecutivos_ticket (anio, ultimo) VALUES (@anio, 25)
  ON DUPLICATE KEY UPDATE ultimo = 25;

-- ---------------------------------------------------------------------
-- Verificación rápida (opcional): debe dar 25 tickets, 12 activos,
-- carga máxima 5 por técnico y al menos 4 vencidos.
-- ---------------------------------------------------------------------
-- SELECT estado, COUNT(*) FROM tickets GROUP BY estado;
-- SELECT tecnico_id, COUNT(*) AS carga FROM tickets WHERE estado <> 'CERRADO' GROUP BY tecnico_id;
-- SELECT COUNT(*) AS vencidos FROM tickets WHERE estado <> 'CERRADO' AND vence_en < NOW();
