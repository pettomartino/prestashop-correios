<?php

	// Init
	$sql = array();

	// Create Service Table in Database
	$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'correios_rate_service_code` (
			  `id_correios_rate_service_code` int(10) NOT NULL AUTO_INCREMENT,
			  `id_carrier` int(10) NOT NULL,
			  `id_carrier_history` text NOT NULL,
			  `code` varchar(16) NOT NULL,
			  `service` varchar(255) NOT NULL,
			  `active` tinyint(1) NOT NULL,
			  UNIQUE(`code`, `service`),
			  PRIMARY KEY  (`id_correios_rate_service_code`)
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

	// Insert Free Shipping Service in database	
	$sql[] = "INSERT INTO `"._DB_PREFIX_."correios_rate_service_code` (`id_carrier`, `code`, `service`, `active`) VALUES ('0', '99999', 'Sem Frete', '0');";
		
			
	// Insert Service in database
	$sql[] = "INSERT INTO `"._DB_PREFIX_."correios_rate_service_code` (`id_carrier`, `code`, `service`, `active`) VALUES
			('0', '41106', 'PAC', '0'),
			('0', '40010', 'SEDEX', '0'),
			('0', '40215', 'SEDEX 10', '0'),
			('0', '40290', 'SEDEX HOJE', '0'),
			('0', '40045', 'SEDEX A COBRAR', '0');";	

	// Create Cache Table in Database
	$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'correios_cache` (
			  `id_correios_cache` int(10) NOT NULL AUTO_INCREMENT,
			  `id_cart` int(10) NOT NULL,
			  `id_carrier` int(10) NOT NULL,
			  `hash` varchar(32) NOT NULL,
			  `id_currency` int(10) NOT NULL,
			  `total_charges` double(10,2) NOT NULL,
			  `is_available` tinyint(1) NOT NULL,
			  `date_add` datetime NOT NULL,
			  `date_upd` datetime NOT NULL,
			  PRIMARY KEY  (`id_correios_cache`)
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

	// Create Test Cache Table in Database
	$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'correios_cache_test` (
			`id_correios_cache_test` int(10) NOT NULL AUTO_INCREMENT,
			`hash` varchar(1024) NOT NULL,
			`result` text NOT NULL,
			`date_add` datetime NOT NULL,
			`date_upd` datetime NOT NULL,
			PRIMARY KEY  (`id_correios_cache_test`)
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

	// Create Config Table in Database
	$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'correios_rate_config` (
			`id_correios_rate_config` int(10) NOT NULL AUTO_INCREMENT,
			`id_product` int(10) NOT NULL,
			`id_category` int(10) NOT NULL,
			`id_currency` int(10) NOT NULL,
			`packaging_type_code` varchar(64) NOT NULL,
			`additionnal_charges` double(6,2) NOT NULL,
			`date_add` datetime NOT NULL,
			`date_upd` datetime NOT NULL,
			PRIMARY KEY  (`id_correios_rate_config`)
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

	// Create Config (Service) Table in Database
	$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'correios_rate_config_service` (
			`id_correios_rate_config_service` int(10) NOT NULL AUTO_INCREMENT,
			`id_correios_rate_service_code` int(10) NOT NULL,
			`id_correios_rate_config` int(10) NOT NULL,
			`date_add` datetime NOT NULL,
			`date_upd` datetime NOT NULL,
			PRIMARY KEY  (`id_correios_rate_config_service`)
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';
		

		

?>
