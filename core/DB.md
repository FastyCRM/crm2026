CREATE DATABASE IF NOT EXISTS crm2026
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE crm2026;

-- =========================================================
-- USERS: логин по телефону, восстановление по email
-- ui_theme индивидуально
-- =========================================================
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(32) NOT NULL UNIQUE,          -- хранить в формате "только цифры"
  pass_hash VARCHAR(255) NOT NULL,
  name VARCHAR(190) NULL,
  status ENUM('active','blocked') NOT NULL DEFAULT 'active',
  ui_theme ENUM('light','dark','color') NOT NULL DEFAULT 'dark',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- роли
CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL UNIQUE,           -- admin/manager/user
  name VARCHAR(190) NOT NULL,
  sort INT NOT NULL DEFAULT 100
) ENGINE=InnoDB;

-- связи user <-> role
CREATE TABLE IF NOT EXISTS user_roles (
  user_id INT UNSIGNED NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- remember sessions
CREATE TABLE IF NOT EXISTS auth_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  selector CHAR(24) NOT NULL UNIQUE,
  validator_hash CHAR(64) NOT NULL,
  ip VARBINARY(16) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  revoked_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_as_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_expires (user_id, expires_at)
) ENGINE=InnoDB;

-- анти-брут: ключ "phone:ip"
CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  key_str VARCHAR(190) NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  last_try_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lock_until TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uq_key (key_str)
) ENGINE=InnoDB;

-- список модулей (инфо/меню). Доступ всё равно берём из settings.php
CREATE TABLE IF NOT EXISTS modules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  icon VARCHAR(64) NULL,
  sort INT NOT NULL DEFAULT 100,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  menu TINYINT(1) NOT NULL DEFAULT 1,
  roles JSON NULL,
  has_settings TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- восстановление пароля по email
CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  used_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_expires (user_id, expires_at)
) ENGINE=InnoDB;