-- Gastos API - Esquema inicial v1.1
-- Objetivo: MariaDB 12.3.x / Osumi Framework 9.12.1
-- Ejecutar sobre una base de datos VACÍA con utf8mb4 y motor InnoDB.
-- Los identificadores son INT signed (no BIGINT / UNSIGNED).
-- Los tipos/status utilizan VARCHAR + CHECK (no ENUM).
-- Las restricciones entre espacios también deben validarse desde la API.

CREATE TABLE `user` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(254) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `space` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `type` VARCHAR(10) NOT NULL,
  `owner_id` INT NOT NULL,
  -- Solo se rellena en espacios personales; UNIQUE permite múltiples NULL.
  `personal_owner_id` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_space_personal_owner` (`personal_owner_id`),
  KEY `idx_space_owner` (`owner_id`),
  CONSTRAINT `fk_space_owner` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_space_personal_owner` FOREIGN KEY (`personal_owner_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_space_type` CHECK (`type` IN ('personal','shared')),
  CONSTRAINT `ck_space_personal_owner` CHECK (
    (`type` = 'personal' AND `personal_owner_id` IS NOT NULL AND `owner_id` = `personal_owner_id`)
    OR (`type` = 'shared' AND `personal_owner_id` IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `space_member` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_space` INT NOT NULL,
  `id_user` INT NOT NULL,
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_space_member` (`id_space`,`id_user`),
  KEY `idx_member_user` (`id_user`,`id_space`),
  CONSTRAINT `fk_member_space` FOREIGN KEY (`id_space`) REFERENCES `space` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_member_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `space_invitation` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_space` INT NOT NULL,
  `email` VARCHAR(254) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `invited_by` INT NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `accepted_at` DATETIME NULL,
  `revoked_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invitation_token` (`token_hash`),
  KEY `idx_invitation_space_email` (`id_space`,`email`),
  KEY `idx_invitation_expires` (`expires_at`),
  CONSTRAINT `fk_invitation_space` FOREIGN KEY (`id_space`) REFERENCES `space` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invitation_inviter` FOREIGN KEY (`invited_by`) REFERENCES `user` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_invitation_resolution` CHECK (`accepted_at` IS NULL OR `revoked_at` IS NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `category` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_space` INT NOT NULL,
  `id_parent` INT NULL,
  `name` VARCHAR(100) NOT NULL,
  `default_type` VARCHAR(7) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_category_space_id` (`id_space`,`id`),
  KEY `idx_category_parent` (`id_space`,`id_parent`),
  CONSTRAINT `fk_category_space` FOREIGN KEY (`id_space`) REFERENCES `space` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_category_parent_same_space` FOREIGN KEY (`id_space`,`id_parent`)
    REFERENCES `category` (`id_space`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_category_type` CHECK (`default_type` IN ('expense','income'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `financial_transaction` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_space` INT NOT NULL,
  `id_user` INT NOT NULL,
  `id_category` INT NOT NULL,
  `type` VARCHAR(7) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `concept` VARCHAR(255) NULL,
  `date` DATE NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transaction_space_id` (`id_space`,`id`),
  KEY `idx_transaction_space_date` (`id_space`,`date`),
  KEY `idx_transaction_category_date` (`id_space`,`id_category`,`date`),
  KEY `idx_transaction_user` (`id_user`),
  CONSTRAINT `fk_transaction_space` FOREIGN KEY (`id_space`) REFERENCES `space` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_transaction_member` FOREIGN KEY (`id_space`,`id_user`)
    REFERENCES `space_member` (`id_space`,`id_user`) ON DELETE RESTRICT,
  CONSTRAINT `fk_transaction_category` FOREIGN KEY (`id_space`,`id_category`)
    REFERENCES `category` (`id_space`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_transaction_type` CHECK (`type` IN ('expense','income')),
  CONSTRAINT `ck_transaction_amount` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `recurring_transaction` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_space` INT NOT NULL,
  `id_category` INT NOT NULL,
  `type` VARCHAR(7) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `concept` VARCHAR(255) NOT NULL,
  `day_of_month` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_recurring_space_id` (`id_space`,`id`),
  KEY `idx_recurring_space_active` (`id_space`,`active`),
  KEY `idx_recurring_category` (`id_space`,`id_category`),
  CONSTRAINT `fk_recurring_space` FOREIGN KEY (`id_space`) REFERENCES `space` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_recurring_category` FOREIGN KEY (`id_space`,`id_category`)
    REFERENCES `category` (`id_space`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_recurring_type` CHECK (`type` IN ('expense','income')),
  CONSTRAINT `ck_recurring_amount` CHECK (`amount` > 0),
  CONSTRAINT `ck_recurring_day` CHECK (`day_of_month` BETWEEN 1 AND 31),
  CONSTRAINT `ck_recurring_dates` CHECK (`end_date` IS NULL OR `end_date` >= `start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `recurring_occurrence` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `id_space` INT NOT NULL,
  `id_recurring_transaction` INT NOT NULL,
  `period` DATE NOT NULL,
  `scheduled_date` DATE NOT NULL,
  `status` VARCHAR(9) NOT NULL DEFAULT 'pending',
  -- Instantánea para que cambios futuros en la plantilla no afecten al pendiente.
  `id_category` INT NOT NULL,
  `type` VARCHAR(7) NOT NULL,
  `concept` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `id_transaction` INT NULL,
  `resolved_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_occurrence_period` (`id_recurring_transaction`,`period`),
  UNIQUE KEY `uq_occurrence_transaction` (`id_transaction`),
  KEY `idx_occurrence_space_status_date` (`id_space`,`status`,`scheduled_date`),
  KEY `idx_occurrence_category` (`id_space`,`id_category`),
  CONSTRAINT `fk_occurrence_recurring` FOREIGN KEY (`id_space`,`id_recurring_transaction`)
    REFERENCES `recurring_transaction` (`id_space`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_occurrence_category` FOREIGN KEY (`id_space`,`id_category`)
    REFERENCES `category` (`id_space`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_occurrence_transaction` FOREIGN KEY (`id_space`,`id_transaction`)
    REFERENCES `financial_transaction` (`id_space`,`id`) ON DELETE RESTRICT,
  CONSTRAINT `ck_occurrence_status` CHECK (`status` IN ('pending','confirmed','skipped')),
  CONSTRAINT `ck_occurrence_type` CHECK (`type` IN ('expense','income')),
  CONSTRAINT `ck_occurrence_amount` CHECK (`amount` > 0),
  CONSTRAINT `ck_occurrence_period` CHECK (DAYOFMONTH(`period`) = 1),
  CONSTRAINT `ck_occurrence_resolution` CHECK (
    (`status` = 'pending' AND `id_transaction` IS NULL AND `resolved_at` IS NULL)
    OR (`status` = 'confirmed' AND `id_transaction` IS NOT NULL AND `resolved_at` IS NOT NULL)
    OR (`status` = 'skipped' AND `id_transaction` IS NULL AND `resolved_at` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reglas que NO puede garantizar este DDL por sí solo:
-- 1. El propietario debe figurar como miembro de su espacio.
-- 2. Cada nuevo usuario debe recibir su espacio personal y su membresía, de forma atómica.
-- 3. Los espacios personales no se invitan/transfieren/abandonan.
-- 4. La API debe impedir que una categoría sea su propio padre y cualquier
--    ciclo indirecto. MariaDB no admite CHECK sobre una columna AUTO_INCREMENT.
-- 5. Al confirmar una ocurrencia, crear la transacción y actualizar la ocurrencia
--    en una operación ACID con bloqueo de fila y verificación de estado.
-- 6. Para ocurrencias nuevas: nunca antes del mes de creación de la plantilla,
--    ni antes de su start_date; conservar fecha mínima en el servicio.
-- 7. Los cambios en una plantilla no deben reescribir mensualidades confirmadas.
-- 8. Usuarios y espacios con historial no deben borrarse físicamente sin una
--    política explícita de conservación / anonimización.
-- 9. Validar que la transacción asociada a una ocurrencia coincide con el tipo,
--    espacio y categoría esperados, además de la FK compuesta de espacio.
-- 10. La revisión de modelos generados es obligatoria: el generador OFW puede
--     no soportar todos los detalles de FKs compuestas y restricciones CHECK.
