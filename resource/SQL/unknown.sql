-- --------------------------------------------------------
-- Hôte:                         127.0.0.1
-- Version du serveur:           9.1.0 - MySQL Community Server - GPL
-- SE du serveur:                Win64
-- HeidiSQL Version:             12.11.0.7065
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Listage de la structure de la base pour unknow
CREATE DATABASE IF NOT EXISTS `unknow` /*!40100 DEFAULT CHARACTER SET latin1 */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `unknow`;

-- Listage de la structure de table unknow. build_queue
CREATE TABLE IF NOT EXISTS `build_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `planet_id` int NOT NULL,
  `bkey` varchar(32) NOT NULL,
  `target_level` int NOT NULL,
  `ends_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bq_active` (`planet_id`,`ends_at`)
) ENGINE=MyISAM AUTO_INCREMENT=30 DEFAULT CHARSET=latin1;

-- Listage des données de la table unknow.build_queue : 0 rows
/*!40000 ALTER TABLE `build_queue` DISABLE KEYS */;
/*!40000 ALTER TABLE `build_queue` ENABLE KEYS */;

-- Listage de la structure de table unknow. planets
CREATE TABLE IF NOT EXISTS `planets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `name` varchar(50) NOT NULL DEFAULT 'Planète mère',
  `metal` bigint unsigned NOT NULL DEFAULT '0',
  `crystal` bigint unsigned NOT NULL DEFAULT '0',
  `hydrogen` int NOT NULL DEFAULT '0',
  `energy` int NOT NULL,
  `prod_metal_per_hour` int NOT NULL DEFAULT '100',
  `prod_crystal_per_hour` int NOT NULL DEFAULT '50',
  `prod_hydrogen_per_hour` int NOT NULL DEFAULT '0',
  `prod_energy_per_hour` int NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;

-- Listage des données de la table unknow.planets : 0 rows
/*!40000 ALTER TABLE `planets` DISABLE KEYS */;
INSERT INTO `planets` (`id`, `user_id`, `name`, `metal`, `crystal`, `hydrogen`, `energy`, `prod_metal_per_hour`, `prod_crystal_per_hour`, `prod_hydrogen_per_hour`, `prod_energy_per_hour`, `last_update`, `created_at`) VALUES
	(3, 3, 'Planète mère', 59, 39, 29, 45, 115, 58, 30, 18, '2025-09-04 19:26:56', '2025-09-04 18:18:46');
/*!40000 ALTER TABLE `planets` ENABLE KEYS */;

-- Listage de la structure de table unknow. planet_buildings
CREATE TABLE IF NOT EXISTS `planet_buildings` (
  `planet_id` int NOT NULL,
  `bkey` varchar(32) NOT NULL,
  `level` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`planet_id`,`bkey`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Listage des données de la table unknow.planet_buildings : 0 rows
/*!40000 ALTER TABLE `planet_buildings` DISABLE KEYS */;
INSERT INTO `planet_buildings` (`planet_id`, `bkey`, `level`) VALUES
	(3, 'crystal_mine', 2),
	(3, 'hydrogen_plant', 1),
	(3, 'metal_mine', 2),
	(3, 'solar_plant', 1);
/*!40000 ALTER TABLE `planet_buildings` ENABLE KEYS */;

-- Listage de la structure de table unknow. users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `username` varchar(30) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;

-- Listage des données de la table unknow.users : 0 rows
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`id`, `email`, `username`, `password`, `created_at`) VALUES
	(3, 'xdarkcaim@gmail.com', NULL, '$2y$12$tbHUF0htXVGxpgcr28.og.eBUrN8d99TQU6ZZ6vPrUqwpfNG9QVgC', '2025-09-04 18:18:40');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
