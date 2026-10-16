-- SOLO para pruebas locales. Contiene datos y credenciales ficticias.
CREATE DATABASE IF NOT EXISTS db_callcenter_demo
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_callcenter_demo;

CREATE TABLE usuarios (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol VARCHAR(40) NOT NULL DEFAULT 'demo'
) ENGINE=InnoDB;

CREATE TABLE operadores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  razon_social VARCHAR(180) NOT NULL UNIQUE,
  nombre_comercial VARCHAR(180) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE pnn_datos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  numeracion_inicial CHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  numeracion_final CHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  empresa VARCHAR(180) NOT NULL,
  poblacion VARCHAR(180) NOT NULL,
  estado VARCHAR(120) NOT NULL,
  modalidad VARCHAR(20) NOT NULL,
  INDEX idx_pnn_rango (numeracion_inicial, numeracion_final),
  CONSTRAINT chk_pnn_rango CHECK (numeracion_inicial <= numeracion_final)
) ENGINE=InnoDB;

INSERT INTO usuarios (usuario, password_hash, rol) VALUES
('demo', '$2y$12$SdVRhWaL2qjLoFIuxIF8Ce5nBT428..VLVYQj6kOpQTjoyAFjRzqu', 'demo');

INSERT INTO operadores (razon_social, nombre_comercial) VALUES
('OPERADOR DEMO', 'Operador de demostración');

-- Rangos deliberadamente ficticios; no corresponden a números asignados.
INSERT INTO pnn_datos
  (numeracion_inicial, numeracion_final, empresa, poblacion, estado, modalidad)
VALUES
  ('0000000000', '0000000999', 'OPERADOR DEMO', 'Localidad ficticia', 'Estado de ejemplo', 'MPP');
