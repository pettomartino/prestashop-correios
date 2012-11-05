<?php

	// Init
	$sql = array();
	$sql[] = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'correios_rate_service_code`;';	
	$sql[] = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'correios_cache`;';
	$sql[] = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'correios_cache_test`;';
	$sql[] = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'correios_rate_config`;';
	$sql[] = 'DROP TABLE IF EXISTS `'._DB_PREFIX_.'correios_rate_config_service`;';

?>
