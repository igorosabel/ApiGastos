/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;

CREATE TABLE `expense` (
  `id` INT(11) NOT NULL COMMENT 'Id único para cada gasto',
  `id_user` INT(11) NOT NULL COMMENT 'Id del usuario que crea el gasto',
  `id_type` INT(11) NULL COMMENT 'Id del tipo de gasto (nulo para varios)',
  `concept` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Concepto del gasto',
  `amount` FLOAT NOT NULL DEFAULT '0' COMMENT 'Importe del gasto',
  `created_at` DATETIME NOT NULL COMMENT 'Fecha de creación del registro',
  `updated_at` DATETIME NULL COMMENT 'Fecha de última modificación del registro',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `unit` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Id único para cada unidad',
  `id_user` INT(11) NOT NULL COMMENT 'Id del usuario origen',
  `related_to` INT(11) NOT NULL COMMENT 'Id del usuario con el que se relaciona',
  `status` INT(11) NOT NULL DEFAULT '0' COMMENT 'Estado de la solicitud 0 pendiente 1 aceptada',
  `created_at` DATETIME NOT NULL COMMENT 'Fecha de creación del registro',
  `updated_at` DATETIME NULL COMMENT 'Fecha de última modificación del registro',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `type` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Id único para cada tipo de gasto',
  `concept` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre para el tipo de gasto',
  `created_at` DATETIME NOT NULL COMMENT 'Fecha de creación del registro',
  `updated_at` DATETIME NULL COMMENT 'Fecha de última modificación del registro',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `user` (
  `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Id único para cada usuario',
  `name` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nombre del usuario',
  `email` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Email del usuario',
  `pass` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Contraseña cifrada del usuario',
  `created_at` DATETIME NOT NULL COMMENT 'Fecha de creación del registro',
  `updated_at` DATETIME NULL COMMENT 'Fecha de última modificación del registro',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `expense`
  ADD KEY `fk_expense_user_idx` (`id_user`),
  ADD CONSTRAINT `fk_expense_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;


ALTER TABLE `unit`
  ADD KEY `fk_unit_user_idx` (`id_user`),
  ADD KEY `fk_unit_user_idx` (`related_to`),
  ADD CONSTRAINT `fk_unit_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_unit_user` FOREIGN KEY (`related_to`) REFERENCES `user` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;


/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
