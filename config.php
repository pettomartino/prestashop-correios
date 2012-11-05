<?php


// If you want to make a clean install, resetting the following tables, set the following to ture, otherwise, set it to false.
// Use this option if the carriers and range_weight do not get defined properly!
//-------------------------
/* TABLES:
		carrier
		carrier_group
		carrier_lang
		carrier_zone
		delivery
		range_price
		range_weight
*/
define('_CLEAN_SQL_BEFORE_INSTALLATION', true);

// Use the option below if you get the "no default carrier" error after installing! 
// To avoid this error, you should enter your shop before installation and sign out as a client!!
//-------------------------
/* TABLES:
		cart
		cart_product
*/
define('_CLEAN_SQL_CART_ALSO', false);

// Install a carrier for Free Shipping when Total Weight = 0 kg. Set to false to skip installation and use your own solution.
define('_INSTALL_FRETE_INCLUIDO', true);

// Use the Caching system to get cost of shipping
define('_USE_CACHE_CORREIOS',false);

?>