-- phpMyAdmin SQL Dump
-- version 4.8.4
-- https://www.phpmyadmin.net/
--									 

--
-- Base de données :  `velov`
--
CREATE DATABASE IF NOT EXISTS `velov` DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;
USE `velov`;

-- --------------------------------------------------------

--
-- Structure de la table `velov_activ_station_stat`
--

DROP TABLE IF EXISTS `velov_activ_station_stat`;
CREATE TABLE `velov_activ_station_stat` (
  `date` date NOT NULL,
  `heure` int(11) NOT NULL,
  `nbStationUpdatedInThisHour` int(11) NOT NULL,
  `nbStationUpdatedLAst3Hour` int(11) DEFAULT NULL,
  `nbStationUpdatedLAst6Hour` int(11) DEFAULT NULL,
  `nbStationAtThisDate` int(11) DEFAULT NULL,
  `nbrvelovExit` int(11) DEFAULT NULL,
  `nbrEvelovExit` int(11) DEFAULT NULL,
  `networkNbBike` int(11) DEFAULT NULL,
  `networkNbBikeOverflow` int(11) DEFAULT NULL,
  `networkEstimatedNbBike` int(11) DEFAULT NULL,
  `networkEstimatedNbBikeOverflow` int(11) DEFAULT NULL,
  `networkNbEBike` int(11) DEFAULT NULL,
  `networkNbEBikeOverflow` int(11) DEFAULT NULL,
  `networkEstimatedNbEBike` int(11) DEFAULT NULL,
  `networkEstimatedNbEBikeOverflow` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- --------------------------------------------------------

--
-- Structure de la table `velov_api_sanitize`
--

DROP TABLE IF EXISTS `velov_api_sanitize`;
CREATE TABLE `velov_api_sanitize` (
  `JsonDate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `JsonMD5` varchar(32) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- --------------------------------------------------------

--
-- Structure de la table `velov_network`
--

DROP TABLE IF EXISTS `velov_network`;
CREATE TABLE `velov_network` (
  `id` int(11) NOT NULL,
  `network_key` varchar(50) NOT NULL,
  `Current_Value` varchar(50)  NOT NULL,
  `Min_Value` varchar(50) NOT NULL,
  `Max_Value` varchar(50) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


INSERT INTO velov_network (id, network_key, Current_Value, Min_Value, Max_Value) VALUES(1, 'LastUpdate', '2010-01-01 00:00:00', '2010-01-01 00:00:00', '2010-01-01 00:00:00');
INSERT INTO velov_network (id, network_key, Current_Value, Min_Value, Max_Value) VALUES(2, 'operative_station_nbr', '0', '0', '0');
INSERT INTO velov_network (id, network_key, Current_Value, Min_Value, Max_Value) VALUES(3, 'velov_nbr', '0', '0', '0');
INSERT INTO velov_network (id, network_key, Current_Value, Min_Value, Max_Value) VALUES(4, 'evelov_nbr', '0', '0', '0');
INSERT INTO velov_network (id, network_key, Current_Value, Min_Value, Max_Value) VALUES(5, 'inactive_station_nbr', '0', '0', '0');
INSERT INTO velov_network (id, network_key, Current_Value, Min_Value, Max_Value) VALUES(6, 'velov_nbr_overflow', '0', '0', '0');
INSERT INTO velov_network (id, network_key, Current_Value, Min_Value, Max_Value) VALUES(7, 'evelov_nbr_overflow', '0', '0', '0');
INSERT INTO velov_network (id, network_key, Current_Value, Min_Value, Max_Value) VALUES(8, 'evelov_nbr2', '0', '0', '0');
INSERT INTO velov_network (id, network_key, Current_Value, Min_Value, Max_Value) VALUES(9, 'velov_nbr2', '0', '0', '0');
INSERT INTO `velov_network` (`id`, `network_key`, `Current_Value`, `Min_Value`, `Max_Value`) VALUES(10, 'nbrvelovUtilises', '0', '0', '0');
INSERT INTO `velov_network` (`id`, `network_key`, `Current_Value`, `Min_Value`, `Max_Value`) VALUES(11, 'nbrEvelovUtilises', '0', '0', '0');


-- --------------------------------------------------------

--
-- Structure de la table `velov_station`
--

DROP TABLE IF EXISTS `velov_station`;
CREATE TABLE `velov_station` (
  `id` int(11) NOT NULL,
  `stationName` varchar(255) NOT NULL,
  `stationCode` varchar(10)  NOT NULL COMMENT 'code station api veli sans les 0 devant',
  `stationState` varchar(50) NOT NULL,
  `stationLat` double(24,15) NOT NULL,
  `stationLon` double(24,15) NOT NULL,
  `stationAdress` varchar(300) DEFAULT NULL COMMENT 'depuis api google à partir de lat/lon',
  `stationKioskState` varchar(3)  DEFAULT NULL,
  `stationNbEDock` int(11) NOT NULL COMMENT 'nombre de diapason (E ou pas)',
  `stationNbBike` int(11) NOT NULL,
  `stationNbEBike` int(11) NOT NULL,
  `nbFreeDock` int(11) NOT NULL,
  `nbFreeEDock` int(11) NOT NULL,
  `stationNbBikeOverflow` int(11) NOT NULL,
  `stationNbEBikeOverflow` int(11) NOT NULL,
  `stationLastChange` timestamp NOT NULL COMMENT 'date du dernier changement de la station',
  `stationLastExit` datetime DEFAULT NULL COMMENT 'date du dernier retrait',
  `stationInsertedInDb` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `stationOperativeDate` datetime DEFAULT NULL,
  `stationLastView` datetime DEFAULT NULL COMMENT 'date de dernière collecte des infos de la station',
  `stationLastComeBack` datetime DEFAULT NULL,
  `stationLastChangeAtComeBack` datetime DEFAULT NULL,
  `stationAvgHourBetweenExit` float(5,1) DEFAULT NULL,
  `stationAvgHourBetweenComeBack` float(5,1) DEFAULT NULL,
  `stationSignaleHS` tinyint(1) NOT NULL DEFAULT '0',
  `stationSignaleHSDate` datetime DEFAULT NULL,
  `stationSignaleHSCount` int(11) NOT NULL DEFAULT '0',
  `stationSignaledElectrified` int(1) NOT NULL DEFAULT '2' COMMENT '0:non - 1-oui - 2:unknown',
  `stationSignaledElectrifiedDate` datetime DEFAULT NULL,
  `stationHidden` tinyint(1) NOT NULL DEFAULT '0',
  `stationLocationHasChanged` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

-- --------------------------------------------------------

--
-- Structure de la table `velov_station_min_velov`
--

DROP TABLE IF EXISTS `velov_station_min_velov`;
CREATE TABLE `velov_station_min_velov` (
  `stationCode` varchar(10) COLLATE latin1_general_ci NOT NULL,
  `stationStatDate` date NOT NULL,
  `stationvelovMinvelov` int(11) NOT NULL COMMENT 'velov + vae',
  `stationvelovMinEvelov` int(11) DEFAULT NULL COMMENT 'vae',
  `stationvelovMaxvelov` int(11) NOT NULL DEFAULT '0' COMMENT 'velov + vae',
  `stationvelovMinvelovOverflow` int(11) DEFAULT NULL COMMENT 'velov + vae',
  `stationvelovMinEvelovOverflow` int(11) DEFAULT NULL COMMENT 'vae',
  `stationvelovMaxvelovOverflow` int(11) DEFAULT NULL COMMENT 'velov + vae',
  `stationvelovExit` int(11) DEFAULT '0',
  `stationEvelovExit` int(11) DEFAULT NULL,
  `updateDate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin
PARTITION BY RANGE COLUMNS(stationStatDate)
(
PARTITION p0 VALUES LESS THAN ('2018-12-31') ENGINE=InnoDB,
PARTITION p1 VALUES LESS THAN (MAXVALUE) ENGINE=InnoDB
);

-- --------------------------------------------------------

--
-- Structure de la table `velov_station_status`
--

DROP TABLE IF EXISTS `velov_station_status`;
CREATE TABLE IF NOT EXISTS `velov_station_status` (
  `id` int(11) NOT NULL,
  `stationCode` varchar(10) COLLATE utf8_bin NOT NULL COMMENT 'code station api veli sans les 0 devant',
  `stationState` varchar(50) COLLATE utf8_bin NOT NULL,
  `stationStatusDate` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Index pour les tables exportées
--

--
-- Index pour la table `velov_activ_station_stat`
--
ALTER TABLE `velov_activ_station_stat`
  ADD PRIMARY KEY (`date`,`heure`);

--
-- Index pour la table `velov_api_sanitize`
--
ALTER TABLE `velov_api_sanitize`
  ADD PRIMARY KEY (`JsonDate`),
  ADD KEY `JsonMD5` (`JsonMD5`);

--
-- Index pour la table `velov_network`
--
ALTER TABLE `velov_network`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key_index` (`network_key`);

--
-- Index pour la table `velov_station`
--
ALTER TABLE `velov_station`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stationCode` (`stationCode`);

--
-- Index pour la table `velov_station_min_velov`
--
ALTER TABLE `velov_station_min_velov`
  ADD PRIMARY KEY (`stationCode`,`stationStatDate`);

--
-- Index pour la table `velov_station_status`
--
ALTER TABLE `velov_station_status`
  ADD PRIMARY KEY (`id`,`stationStatusDate`),
  ADD KEY `id` (`id`,`stationState`);
  
--
-- AUTO_INCREMENT pour les tables exportées
--

--
-- AUTO_INCREMENT pour la table `velov_network`
--
ALTER TABLE `velov_network`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `velov_station`
--
ALTER TABLE `velov_station`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;