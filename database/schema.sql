-- =====================================================================
-- Sistema de Tickets de Soporte TI — Esquema (MySQL 8.0+, InnoDB)
-- Uso:  mysql -u root -p < database/schema.sql   (luego seed.sql)
-- Los CHECK se aplican desde MySQL 8.0.16 (en MariaDB 10.2+ también).
-- Zona horaria: las fechas se guardan en hora local de Colombia
-- (America/Bogota, UTC-05:00, sin horario de verano). La aplicación
-- debe ejecutar SET time_zone = '-05:00' al conectar.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS soporte_ti
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE soporte_ti;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS ticket_historial;
DROP TABLE IF EXISTS tickets;
DROP TABLE IF EXISTS consecutivos_ticket;
DROP TABLE IF EXISTS tecnicos;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS categorias;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Catálogo de categorías (no se queman en el HTML)
-- ---------------------------------------------------------------------
CREATE TABLE categorias (
  id      TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre  VARCHAR(50)      NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Usuarios solicitantes (internos de la empresa)
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo  VARCHAR(20)  NOT NULL,
  nombre  VARCHAR(120) NOT NULL,
  cargo   VARCHAR(100) NOT NULL,
  area    VARCHAR(100) NOT NULL,
  activo  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_codigo (codigo),
  KEY idx_usuarios_nombre (nombre),
  CONSTRAINT chk_usuarios_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Técnicos de soporte
-- ---------------------------------------------------------------------
CREATE TABLE tecnicos (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre  VARCHAR(120) NOT NULL,
  activo  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_tecnicos_activo (activo),
  CONSTRAINT chk_tecnicos_activo CHECK (activo IN (0, 1))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Consecutivo anual del número de ticket (TK-AAAA-NNNNNN)
-- Se incrementa dentro de una transacción con:
--   SELECT ultimo FROM consecutivos_ticket WHERE anio = ? FOR UPDATE
-- Esto serializa a quienes crean tickets al mismo tiempo.
-- ---------------------------------------------------------------------
CREATE TABLE consecutivos_ticket (
  anio    SMALLINT UNSIGNED NOT NULL,
  ultimo  INT UNSIGNED      NOT NULL DEFAULT 0,
  PRIMARY KEY (anio)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tickets
-- ---------------------------------------------------------------------
CREATE TABLE tickets (
  id             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  numero         VARCHAR(14)      NOT NULL,            -- TK-2026-000042
  usuario_id     INT UNSIGNED     NOT NULL,
  categoria_id   TINYINT UNSIGNED NOT NULL,
  descripcion    VARCHAR(1000)    NOT NULL,
  prioridad      ENUM('Alta','Media','Baja')            NOT NULL,
  tecnico_id     INT UNSIGNED     NOT NULL,
  estado         ENUM('ABIERTO','EN CURSO','CERRADO')   NOT NULL DEFAULT 'ABIERTO',
  solucion       TEXT             NULL,                -- obligatoria al cerrar
  creado_en      DATETIME         NOT NULL,
  vence_en       DATETIME         NOT NULL,            -- fecha límite SLA
  actualizado_en DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,
  cerrado_en     DATETIME         NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tickets_numero (numero),

  -- Índices para los filtros y el orden de la tabla
  KEY idx_tickets_estado      (estado),
  KEY idx_tickets_prioridad   (prioridad),
  KEY idx_tickets_tecnico_est (tecnico_id, estado),    -- carga activa por técnico
  KEY idx_tickets_usuario     (usuario_id),
  KEY idx_tickets_categoria   (categoria_id),
  KEY idx_tickets_creado      (creado_en),
  KEY idx_tickets_estado_vence (estado, vence_en),     -- conteo de vencidos

  CONSTRAINT fk_tickets_usuario   FOREIGN KEY (usuario_id)   REFERENCES usuarios   (id),
  CONSTRAINT fk_tickets_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id),
  CONSTRAINT fk_tickets_tecnico   FOREIGN KEY (tecnico_id)   REFERENCES tecnicos   (id),

  CONSTRAINT chk_tickets_descripcion
    CHECK (CHAR_LENGTH(descripcion) BETWEEN 20 AND 1000),
  CONSTRAINT chk_tickets_vence
    CHECK (vence_en > creado_en),
  CONSTRAINT chk_tickets_cierre
    CHECK (estado <> 'CERRADO' OR (solucion IS NOT NULL AND cerrado_en IS NOT NULL))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Historial de cambios (creación, estado, reasignación)
-- ---------------------------------------------------------------------
CREATE TABLE ticket_historial (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id           BIGINT UNSIGNED NOT NULL,
  tipo_cambio         ENUM('CREACION','ESTADO','REASIGNACION') NOT NULL,
  estado_anterior     ENUM('ABIERTO','EN CURSO','CERRADO')     NULL,
  estado_nuevo        ENUM('ABIERTO','EN CURSO','CERRADO')     NOT NULL,
  tecnico_anterior_id INT UNSIGNED    NULL,
  tecnico_nuevo_id    INT UNSIGNED    NULL,
  observacion         VARCHAR(1000)   NULL,
  creado_en           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_historial_ticket (ticket_id, creado_en),

  CONSTRAINT fk_historial_ticket
    FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE,
  CONSTRAINT fk_historial_tecnico_ant
    FOREIGN KEY (tecnico_anterior_id) REFERENCES tecnicos (id),
  CONSTRAINT fk_historial_tecnico_nuevo
    FOREIGN KEY (tecnico_nuevo_id) REFERENCES tecnicos (id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Catálogo fijo (parte del esquema, no de los datos de prueba)
-- ---------------------------------------------------------------------
INSERT INTO categorias (nombre) VALUES ('Hardware'), ('Software'), ('Red'), ('Otro');
