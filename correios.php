<?php


class Correios extends Module
{
	public  $id_carrier;

	private $_html = '';
	private $_postErrors = array();
	private $_webserviceError = '';
	private $_rateServiceGroupList = array();
	private $_fieldsList = array();
	private $_pickupTypeList = array();
	private $_packagingTypeList = array();
	private $_dimensionUnit = '';
	private $_weightUnit = '';
	private $_dimensionUnitList = array('CM' => 'CM', 'IN' => 'IN', 'CMS' => 'CM', 'INC' => 'IN');
	private $_weightUnitList = array('KG' => 'KGS', 'KGS' => 'KGS', 'LBS' => 'LBS', 'LB' => 'LBS');
	private $_moduleName = 'correios';
	
	/*
	** Construct Method
	**
	*/

	public function __construct()
	{
		global $cookie;

		$this->name = 'correios';
		$this->tab = 'shipping_logistics';
		$this->version = '0.12';
		$this->author = 'Petto Martio';

		parent::__construct ();

		$this->displayName = $this->l('Correios Carrier');
		$this->description = $this->l('Modified by Fabrice Menoyot. Offer your customers, different delivery methods with Brazilian Correios');

		if (self::isInstalled($this->name))
		{
			// Loading Var
			$warning = array();
			$this->loadingVar();

			// Check Configuration Values
			foreach ($this->_fieldsList as $keyConfiguration => $name)
				if (!Configuration::get($keyConfiguration) && !empty($name))
					$warning[] = '\''.$name.'\' ';

			// Checking Unit
			$this->_dimensionUnit = $this->_dimensionUnitList[strtoupper(Configuration::get('PS_DIMENSION_UNIT'))];
			$this->_weightUnit = $this->_weightUnitList[strtoupper(Configuration::get('PS_WEIGHT_UNIT'))];
			if (!$this->_weightUnit || !$this->_weightUnitList[$this->_weightUnit])
				$warning[] = $this->l('\'Weight Unit (LB or KG).\'').' ';
			if (!$this->_dimensionUnit || !$this->_dimensionUnitList[$this->_dimensionUnit])
				$warning[] = $this->l('\'Dimension Unit (CM or IN).\'').' ';

			// Generate Warnings
			if (count($warning))
				$this->warning .= implode(' , ',$warning).$this->l('must be configured to use this module correctly.').' ';
		}
	}

	public function loadingVar()
	{
		// Loading Fields List
		$this->_fieldsList = array(
			'CORREIOS_CARRIER_POSTAL_CODE' => '',
			'CORREIOS_CARRIER_DISPLAY_ALL' => ''
			
		);
	}

	/*
	** Install / Uninstall Methods
	**
	*/

	public function install()
	{
		global $cookie;

		// Install SQL
		include(dirname(__FILE__).'/config.php');
		
		include(dirname(__FILE__).'/sql-install.php');
		
		foreach ($sql as $s) {
			
			// Insert Free Shipping Service in database
			if ($s  == "INSERT INTO `"._DB_PREFIX_."correios_rate_service_code` (`id_carrier`, `code`, `service`, `active`) VALUES ('0', '99999', 'Sem Frete', '0');") {
				if (_INSTALL_FRETE_INCLUIDO) {
						if (!Db::getInstance()->Execute($s))
						return false;
				}
			
			} else {
					
				if (!Db::getInstance()->Execute($s))
					return false;
			}
		}
		// Cleaning procedures to ensure superb installation!
		//=========================================================
		if (_CLEAN_SQL_BEFORE_INSTALLATION) {
			include(dirname(__FILE__).'/sql-install-clean-sql.php');
			foreach ($sql2 as $s2)
				if (!Db::getInstance()->Execute($s2))
					return false;
				
		}
		if (_CLEAN_SQL_CART_ALSO) {
			$s3 = 'DELETE FROM `'._DB_PREFIX_.'cart` WHERE `id_cart` != "";';
			if (!Db::getInstance()->Execute($s3))
						return false;
						
			$s4 = 'DELETE FROM `'._DB_PREFIX_.'cart_product` WHERE `id_cart` != "";';
			if (!Db::getInstance()->Execute($s4))
						return false;
		}
		//=========================================================
		// Install Module
		if (!parent::install() OR !$this->registerHook('updateCarrier'))
			return false;

		return true;
	}

	public function uninstall()
	{
		global $cookie;

		// Uninstall Carriers
		Db::getInstance()->autoExecute(_DB_PREFIX_.'carrier', array('deleted' => 1), 'UPDATE', '`external_module_name` = \'correioscarrier\' OR `id_carrier` IN (SELECT DISTINCT(`id_carrier`) FROM `'._DB_PREFIX_.'correios_rate_service_code`)');

		// Uninstall Config
		foreach ($this->_fieldsList as $keyConfiguration => $name)
			if (!Configuration::deleteByName($keyConfiguration))
				return false;

		// Uninstall SQL
		include(dirname(__FILE__).'/sql-uninstall.php');
		foreach ($sql as $s)
			if (!Db::getInstance()->Execute($s))
				return false;

		// Uninstall Module
		if (!parent::uninstall() OR !$this->unregisterHook('updateCarrier'))
			return false;

		return true;
	}

	public function installCarriers()
	{
		// Unactive all Correios Carriers
		Db::getInstance()->autoExecute(_DB_PREFIX_.'correios_rate_service_code', array('active' => 0), 'UPDATE');

		// Get all services availables
		$rateServiceList = Db::getInstance()->ExecuteS('SELECT * FROM `'._DB_PREFIX_.'correios_rate_service_code');
		foreach ($rateServiceList as $rateService)
			if (!$rateService['id_carrier'])
			{
				$config = array(
					'name' => $rateService['service'],
					'id_tax_rules_group' => 0,
					'active' => true,
					'deleted' => 0,
					'shipping_handling' => false,
					'range_behavior' => 0,
					'delay' => array('fr' => $rateService['service'], 'en' => $rateService['service'], 'br' => $rateService['service'], 'pt-br' => $rateService['service']),
					'id_zone' => 1,
					'is_module' => true,
					'shipping_external' => true,
					'external_module_name' => $this->_moduleName,
					'carrier_code' => $rateService['code'],
					'need_range' => true
				);
				
				$id_carrier = $this->installExternalCarrier($config);
				Db::getInstance()->autoExecute(_DB_PREFIX_.'correios_rate_service_code', array('id_carrier' => (int)($id_carrier), 'id_carrier_history' => (int)($id_carrier)), 'UPDATE', '`id_correios_rate_service_code` = '.(int)($rateService['id_correios_rate_service_code']));
			}
	}
	
	public static function installExternalCarrier($config)
	{
		$carrier = new Carrier();
		$carrier->name = $config['name'];
		$carrier->id_tax_rules_group = $config['id_tax_rules_group'];
		$carrier->id_zone = $config['id_zone'];
		$carrier->active = $config['active'];
		$carrier->deleted = $config['deleted'];	
		$carrier->delay = $config['delay'];
		$carrier->shipping_handling = $config['shipping_handling'];
		$carrier->range_behavior = $config['range_behavior'];
		$carrier->is_module = $config['is_module'];
		$carrier->shipping_external = $config['shipping_external'];
		$carrier->external_module_name = $config['external_module_name'];
		$carrier->need_range = $config['need_range'];
		// temp_code is used only as a reference to the carrier below. This is not stored in the carrier table!
		$carrier->temp_code = $config['carrier_code'];

		$languages = Language::getLanguages(true);
		foreach ($languages as $language)
		{
			if ($language['iso_code'] == 'fr')
				$carrier->delay[(int)$language['id_lang']] = $config['delay'][$language['iso_code']];
			if ($language['iso_code'] == 'en')
				$carrier->delay[(int)$language['id_lang']] = $config['delay'][$language['iso_code']];
			if ($language['iso_code'] == 'br')
				$carrier->delay[(int)$language['id_lang']] = $config['delay'][$language['iso_code']];
			if ($language['iso_code'] == 'pt-br')
				$carrier->delay[(int)$language['id_lang']] = $config['delay'][$language['iso_code']];
		}

		if ($carrier->add())
		{
			$groups = Group::getGroups(true);
			foreach ($groups as $group)
				Db::getInstance()->autoExecute(_DB_PREFIX_.'carrier_group', array('id_carrier' => (int)($carrier->id), 'id_group' => (int)($group['id_group'])), 'INSERT');

			$rangePrice = new RangePrice();
			$rangePrice->id_carrier = $carrier->id;
			$rangePrice->delimiter1 = '0';
			
			$rangePrice->delimiter2 = '10000';
			$rangePrice->add();

			$rangeWeight = new RangeWeight();
			$rangeWeight->id_carrier = $carrier->id;
			$rangeWeight->delimiter1 = '0';
			$rangeWeight->delimiter2 = '10000';
			if ($carrier->temp_code == "99999") {
				$rangeWeight->delimiter2 = '0.001';	
			}
			$rangeWeight->add();

			$zones = Zone::getZones(true);
			foreach ($zones as $zone)
			{
				Db::getInstance()->autoExecute(_DB_PREFIX_.'carrier_zone', array('id_carrier' => (int)($carrier->id), 'id_zone' => (int)($zone['id_zone'])), 'INSERT');
				Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_.'delivery', array('id_carrier' => (int)($carrier->id), 'id_range_price' => (int)($rangePrice->id), 'id_range_weight' => NULL, 'id_zone' => (int)($zone['id_zone']), 'price' => '0'), 'INSERT');
				Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_.'delivery', array('id_carrier' => (int)($carrier->id), 'id_range_price' => NULL, 'id_range_weight' => (int)($rangeWeight->id), 'id_zone' => (int)($zone['id_zone']), 'price' => '0'), 'INSERT');
			}
			// Copy Logos
			$imgCarrier = "";
			$imgCarrier = "/logos/" . $carrier->temp_code . ".jpg";
			if (!copy(dirname(__FILE__). $imgCarrier, _PS_SHIP_IMG_DIR_.'/'.(int)$carrier->id.'.jpg'))
				return false;
			// For some reason, this is the one that seems to be required:
			if (!copy(dirname(__FILE__). $imgCarrier, _PS_TMP_IMG_DIR_ .'/carrier_mini_'.(int)$carrier->id.'.jpg'))
				return false;
			
			// Return ID Carrier
			return (int)($carrier->id);
		}
		return false;
	}


	/*
	** Global Form Config Methods
	**
	*/

	public function getContent()
	{
		$this->_html .= '<h2>' . $this->l('Correios Carrier').'</h2>';
		if (!empty($_POST) && Tools::isSubmit('submitSave'))
		{
			
			$this->_postValidation();
			if (!sizeof($this->_postErrors)){
				$this->_postProcess();
			} else
				foreach ($this->_postErrors AS $err)
					$this->_html .= '<div class="alert error"><img src="'._PS_IMG_.'admin/forbbiden.gif" alt="nok" />&nbsp;'.$err.'</div>';
		}
		$this->_displayForm();
		return $this->_html;
	}

	private function _displayForm()
	{
		$this->_html .= '<fieldset>
		<legend><img src="'.$this->_path.'logo.gif" alt="" /> '.$this->l('Correios Module Status').'</legend>';

		$alert = array();
		if (!Configuration::get('CORREIOS_CARRIER_POSTAL_CODE'))
			$alert['rateServiceGroup'] = 1;
		if (Db::getInstance()->getValue('SELECT * FROM `'._DB_PREFIX_.'correios_rate_service_code`'/* WHERE `active` = 1'*/) < 1)
			$alert['deliveryServices'] = 1;
		if (!$this->webserviceTest())
			$alert['webserviceTest'] = 1;

		if (!count($alert))
			$this->_html .= '<img src="'._PS_IMG_.'admin/module_install.png" /><strong>'.$this->l('Correios Carrier is configured and online!').'</strong>';
		else
		{
			$this->_html .= '<img src="'._PS_IMG_.'admin/warn2.png" /><strong>'.$this->l('Correios Carrier is not configured yet, please:').'</strong>';
			$this->_html .= '<br />'.(isset($alert['rateServiceGroup']) ? '<img src="'._PS_IMG_.'admin/warn2.png" />' : '<img src="'._PS_IMG_.'admin/module_install.png" />').' 1) '.$this->l('Fill the "General Settings" form.');
			$this->_html .= '<br />'.(isset($alert['webserviceTest']) ? '<img src="'._PS_IMG_.'admin/warn2.png" />' : '<img src="'._PS_IMG_.'admin/module_install.png" />').' 2) '.$this->l('Webservice test connection').($this->_webserviceError ? ' : '.$this->_webserviceError : '');
			$this->_html .= '<br />'.(isset($alert['deliveryServices']) ? '<img src="'._PS_IMG_.'admin/warn2.png" />' : '<img src="'._PS_IMG_.'admin/module_install.png" />').' 3) '.$this->l('Select your available delivery service (you must validate your rate service group first).');
		}

		if (!is_callable('curl_exec'))
			$this->_html .= '<br /><br />'.$this->l('cURL Extension is not enabled, Correios module can work without cURL but it would be better to enable it.');

		$this->_html .= '</fieldset><div class="clear">&nbsp;</div>';
		$this->_html .= $this->_displayFormConfig();
	}

	private function _displayFormConfig()
	{
		$template_variables = array(
			'REQUEST_URI' => $_SERVER['REQUEST_URI'],
			'myCheckBox' => Tools::getValue('correios_carrier_display_all', Configuration::get('CORREIOS_CARRIER_DISPLAY_ALL')), 
			'zip_code' => Tools::getValue('correios_carrier_postal_code', Configuration::get('CORREIOS_CARRIER_POSTAL_CODE')),
			'tab_selected' => isset($_GET['id_tab']),
		);
		$this->smarty->assign($template_variables);

		return $this->display(__FILE__, 'config/form-general.tpl');
		
	}

	public function hookShoppingCart($params)
	{
		if (!$this->isModuleAvailable())
			return;


		return $this->smarty->fetch(_FILE__,'teste.tpl');
	}

	private function _postValidation()
	{
		if (Tools::getValue('section') == 'general')
  		$this->_postValidationGeneral();
	}

	private function _postProcess()
	{

		if (Tools::getValue('section') == 'general') {
			$this->_postProcessGeneral();
		}
      		
	}
	

	

	

	private function _postValidationGeneral()
	{
		// Check configuration values
		if (Tools::getValue('correios_carrier_postal_code') == NULL)
			$this->_postErrors[]  = $this->l('Your Zip / Postal code is not specified');

		// Check correios webservice availibity
		if (!$this->_postErrors)
		{
			// Install Carriers (if not)
			$this->installCarriers();

			// If no errors appear, the carrier is being activated, else, the carrier is being deactivated
			if (!$this->_postErrors)
			{
				// Get available services
				$serviceSelected = Tools::getValue('service');

				// Active available carrier
				if ($serviceSelected)
					foreach ($serviceSelected as $ss)
					{
						$id_carrier = Db::getInstance()->getValue('SELECT `id_carrier` FROM `'._DB_PREFIX_.'correios_rate_service_code` WHERE `id_correios_rate_service_code` = '.(int)($ss));
						Db::getInstance()->autoExecute(_DB_PREFIX_.'correios_rate_service_code', array('active' => 1), 'UPDATE', '`id_correios_rate_service_code` = '.(int)($ss));
					}
			}
			// All new configurations values are saved to be sure to test webservices with it
			Configuration::updateValue('CORREIOS_CARRIER_POSTAL_CODE', Tools::getValue('correios_carrier_postal_code'));
			Configuration::updateValue('CORREIOS_CARRIER_DISPLAY_ALL', Tools::getValue('correios_carrier_display_all'));
			
			if (!$this->webserviceTest())
				$this->_postErrors[]  = $this->l('Prestashop could not connect to Correios webservices').' :<br />'.($this->_webserviceError ? $this->_webserviceError : $this->l('No error description found'));
		}
	}

	private function _postProcessGeneral()
	{
		// Saving new configurations
		if (Configuration::updateValue('CORREIOS_CARRIER_POSTAL_CODE', Tools::getValue('correios_carrier_postal_code')))
			$this->_html .= $this->displayConfirmation($this->l('Settings updated'));
		else
			$this->_html .= $this->displayErrors($this->l('Settings failed'));
	}

	public function hookupdateCarrier($params)
	{
		if ((int)($params['id_carrier']) != (int)($params['carrier']->id))
		{
			$serviceSelected = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'correios_rate_service_code` WHERE `id_carrier` = '.(int)$params['id_carrier']);
			$update = array('id_carrier' => (int)($params['carrier']->id), 'id_carrier_history' => pSQL($serviceSelected['id_carrier_history'].'|'.(int)($params['carrier']->id)));
			Db::getInstance()->autoExecute(_DB_PREFIX_.'correios_rate_service_code', $update, 'UPDATE', '`id_carrier` = '.(int)$params['id_carrier']);
		}
	}

	public function displayInfoByCart()
	{
		// QUE SERA ISSO??
	}

	/*
	** Front Methods
	**
	*/
	public function getCookieCurrencyRate($id_currency_origin)
	{
		global $cookie;
		$conversionRate = 1;
		if ($cookie->id_currency != $id_currency_origin)
		{
			$currencyOrigin = new Currency((int)$id_currency_origin);
			$conversionRate /= $currencyOrigin->conversion_rate;
			$currencySelect = new Currency((int)$cookie->id_currency);
			$conversionRate *= $currencySelect->conversion_rate;
		}
		return $conversionRate;
	}
	
	public function getOrderShippingCostHash($wsParams)
	{
		$paramHash = '';
		$productHash = '';
		foreach ($wsParams['products'] as $product)
		{
			if (!empty($productHash))
				$productHash .= '|';
			$productHash .= $product['id_product'].':'.$product['id_product_attribute'].':'.$product['cart_quantity'];
		}
		foreach ($wsParams as $k => $v)
			if ($k != 'products')
			$paramHash .= '/'.$v;
		return md5($productHash.$paramHash);
	}
	
	public function getOrderShippingCostCache($wsParams)
	{
		// Get Cache
		$row = Db::getInstance()->getRow("
		SELECT * FROM `"._DB_PREFIX_."correios_cache`
		WHERE `id_cart` = ".(int)($wsParams['id_cart'])."
		AND `id_carrier` = ".(int)($this->id_carrier)."
		AND `hash` = '".pSQL($wsParams['hash'])."'");
		if ($row['id_currency'])
		{
			// Check Currency Rate And Calcul
			$conversionRate = $this->getCookieCurrencyRate($row['id_currency']);
			$row['total_charges'] = $row['total_charges'] * $conversionRate;

			// Return Cache
			return $row;
		}
		
		return false;
	}
	
	public function saveOrderShippingCostCache($wsParams, $wscost)
	{
		global $cookie;
		$is_available = 1;
		if (!$wscost)
			$is_available = 0;
		$date = date('Y-m-d H:i:s');
		$insert = array(
			'id_cart' => (int)($wsParams['id_cart']),
			'id_carrier' => (int)($this->id_carrier),
			'hash' => pSQL($wsParams['hash']),
			'id_currency' => (int)($cookie->id_currency),
			'total_charges' => pSQL($wscost),
			'is_available' => (int)($is_available),
			'date_add' => pSQL($date),
			'date_upd' => pSQL($date)
		);
		Db::getInstance()->autoExecute(_DB_PREFIX_.'correios_cache', $insert, 'INSERT');
	}
	
	public function loadShippingCostConfig($product)
	{
		// Init var
		$config = array();
	
		// Check if there is a specific product configuration
		if ($product['id_product'] > 0)
		{
			$productConfiguration = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'correios_rate_config` WHERE `id_product` = '.(int)($product['id_product']));
			if ($productConfiguration['id_correios_rate_config'])
			{
				$servicesConfiguration = Db::getInstance()->ExecuteS('
				SELECT urcs.*, ursc.`id_carrier`
				FROM `'._DB_PREFIX_.'correios_rate_config_service` urcs
				LEFT JOIN `'._DB_PREFIX_.'correios_rate_service_code` ursc ON (ursc.`id_correios_rate_service_code` = urcs.`id_correios_rate_service_code`)
				WHERE `id_correios_rate_config` = '.(int)($productConfiguration['id_correios_rate_config']));
				foreach ($servicesConfiguration as $service)
					$productConfiguration['services'][$service['id_correios_rate_service_code']] = $service;
				return $productConfiguration;
			}
		}

		// Check if there is a specific category configuration
		if ($product['id_category_default'] > 0)
		{
			$categoryConfiguration = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'correios_rate_config` WHERE `id_category` = '.(int)($product['id_category_default']));
			if ($categoryConfiguration['id_correios_rate_config'])
			{
				$servicesConfiguration = Db::getInstance()->ExecuteS('
				SELECT urcs.*, ursc.`id_carrier`
				FROM `'._DB_PREFIX_.'correios_rate_config_service` urcs
				LEFT JOIN `'._DB_PREFIX_.'correios_rate_service_code` ursc ON (ursc.`id_correios_rate_service_code` = urcs.`id_correios_rate_service_code`)
				WHERE `id_correios_rate_config` = '.(int)($categoryConfiguration['id_correios_rate_config']));
				foreach ($servicesConfiguration as $service)
					$categoryConfiguration['services'][$service['id_correios_rate_service_code']] = $service;
				return $categoryConfiguration;
			}
		}

		// Return general config
		$config['packaging_type_code'] = Configuration::get('CORREIOS_CARRIER_PACKAGING_TYPE');
		$servicesConfiguration = Db::getInstance()->ExecuteS('SELECT * FROM `'._DB_PREFIX_.'correios_rate_service_code` WHERE `active` = 1');
		foreach ($servicesConfiguration as $service)
		{
			$config['services'][$service['id_correios_rate_service_code']] = $service;
	  }
		return $config;
	}

	public function getWebserviceShippingCost($wsParams)
	{

		
		// Init var
		$cost = 0;
		// Getting shipping cost for each product
		foreach ($wsParams['products'] as $product)
		{
			// Load specific configuration
			$config = $this->loadShippingCostConfig($product);
			
			// Get service in adequation with carrier and check if available
			$serviceSelected = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'correios_rate_service_code` WHERE `id_carrier` = '.(int)($this->id_carrier));

			$TotalWeight = 0;
			// Load param product
			$wsParams['nCdServico'] = $serviceSelected['code'];
			for ($qty = 0; $qty < $product['quantity']; $qty++)     
		      {
						$wsParams['package_list'][] = array(
							'width' => ($product['width'] ? $product['width'] : 1),
							'height' => ($product['height'] ? $product['height'] : 1),
							'depth' => ($product['depth'] ? $product['depth'] : 1),
							'weight' => ($product['weight'] ? $product['weight'] : 1),
							'packaging_type' => ($config['packaging_type_code'] ? $config['packaging_type_code'] : Configuration::get('CORREIOS_CARRIER_PACKAGING_TYPE')),
						);
						$TotalWeight += $product['weight'];
		      }

			// If Additional charges
			if (isset($config['id_currency']) && isset($config['additionnal_charges']))
			{
				$conversionRate = 1;
				$conversionRate = $this->getCookieCurrencyRate((int)($config['id_currency']));
				$cost += ($config['additionnal_charges'] * $conversionRate);
			}
		} 

	
	$myCheckBox = Configuration::get('CORREIOS_CARRIER_DISPLAY_ALL');
	if ($myCheckBox != "on") { 
		if ($TotalWeight == 0){
				return false;
		}
	}	
    if ($TotalWeight < 0.3)
		if ($myCheckBox != "on") { 
			$TotalWeight = 0.3;
		} else {
			$TotalWeight = 0;
		}
		$wsParams['nVlPeso'] = $TotalWeight;
		

		// If webservice return a cost, we add it, else, we return the original shipping cost
		if ($wsParams['nCdServico'] == "99999") {
			return $cost . "|" . $TotalWeight;
		} else {

			$result = $this->getCorreiosShippingCost($wsParams);
			if ($result['connect'] && $result['cost'] > 0)
				// We return the cost and the total wieght as we need the total weight in the following function!
				$thisCost = ($cost + $result['cost']);
				return $thisCost . "|" . $TotalWeight;
			return false;
		}	
	}

	public function getOrderShippingCost($params, $shipping_cost)
	{
		
		return 0;
	}

	public function getPackageShippingCost($id_carrier = null, $use_tax = true, $default_country = null, $product_list = null)
	{
		return 10;
	}
	/*
public function getOrderShippingCost($params, $shipping_cost)
	{	
		return 0;
		
		// Init var
		$address = new Address($params->id_address_delivery);
		$recipient_country = Db::getInstance()->getRow('SELECT `iso_code` FROM `'._DB_PREFIX_.'country` WHERE `id_country` = '.(int)($address->id_country));
		$recipient_state = Db::getInstance()->getRow('SELECT `iso_code` FROM `'._DB_PREFIX_.'state` WHERE `id_state` = '.(int)($address->id_state));
		$shipper_country = Db::getInstance()->getRow('SELECT `iso_code` FROM `'._DB_PREFIX_.'country` WHERE `id_country` = '.(int)(Configuration::get('CORREIOS_CARRIER_COUNTRY')));
		$shipper_state = Db::getInstance()->getRow('SELECT `iso_code` FROM `'._DB_PREFIX_.'state` WHERE `id_state` = '.(int)(Configuration::get('CORREIOS_CARRIER_STATE')));
		$products = $params->getProducts();

		
		$context = Context::getContext();
		
		// Webservices Params
		$wsParams = array(
			'nCdEmpresa' => '',
			'sDsSenha' => '',
			'sCepOrigem' => Configuration::get('CORREIOS_CARRIER_POSTAL_CODE'),
			'sCepDestino' => $address->postcode ?: $context->cookie->postcode,
			'nVlPeso'  => cart::GetTotalWeight(),
			'nCdFormato' => '1',
			'nVlComprimento' => '25',
			'nVlAltura' => '20',
			'nVlLargura' => '25',
			'sCdMaoPropria' => 'N',
			'nVlValorDeclarado' => '0.00',
			'sCdAvisoRecebimento' => 'N',
			'nCdServico' => '41106',
			'nVlDiametro' => '0',
			'StrRetorno' => 'xml',
			'id_cart' => $params->id,
			'hash' => '',
			'products' => $params->getProducts()
			);
		$wsParams['hash'] = $this->getOrderShippingCostHash($wsParams);


		// Check cache
		// Disabled when Sem Frete installed
		include(dirname(__FILE__).'/config.php');
		if (_USE_CACHE_CORREIOS) {
				$cache = $this->getOrderShippingCostCache($wsParams);
				if ($cache['id_correios_cache'] > 0)
				{
					if ($cache['is_available'] == 0) {
						//return false;
					} else {
						if ($cache['total_charges'])
							return $cache['total_charges'];
					}
				}
		}
		// Get Webservices Cost and Cache it
		// Return cost and total weight
		$myResult = $this->getWebserviceShippingCost($wsParams);

		$myresultSplit = explode("|",$myResult);
		$wscost = (float)$myresultSplit[0];
		$TotalWeight = (float)$myresultSplit[1];


		// Get id of NO frete Carrier
		$serviceSelected = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'correios_rate_service_code` WHERE `code` = 99999');
		$freeShippingCarrier = $serviceSelected['id_carrier'];
		if (_USE_CACHE_CORREIOS) {
			$this->saveOrderShippingCostCache($wsParams, $wscost);
		}
		
		// Exibir todos os transportadores com Frete Grátis se e selecionada a opcao!	
		$myCheckBox = Configuration::get('CORREIOS_CARRIER_DISPLAY_ALL');
		if ($myCheckBox == "on") { 
			if ($TotalWeight == 0) {	
				if ($wscost >= 0)  {
					return $wscost + $shipping_cost;
				} else {
					return false;
				}
			} else {
				if ($wscost > 0)  {
					return $wscost + $shipping_cost;
				} else {
					return false;
				}
			}
		} else {	
			if (($TotalWeight == 0) && ($freeShippingCarrier == (int)($this->id_carrier))) {
				return $wscost + $shipping_cost;
			} else {
				if ($wscost > 0)  {
					return $wscost + $shipping_cost;
				} else {
					return false;
				}
			}
		}
		//==========================================================
	}
	*/

	public function getOrderShippingCostExternal($params)
	{
		return 0;
		//return $this->getOrderShippingCost($params, 23);
	}

	/*
	** Webservices Methods
	**
	*/

	public function parseXML($valTab)
	{
		$resultTab = array(
		  'Valor' => str_replace(',','.',$valTab->cServico->Valor),
		  'Erro' => $valTab->cServico->Erro,
			'MsgErro' => $valTab->cServico->MsgErro,
			'PrazoEntrega' => $valTab->cServico->PrazoEntrega
		);
		return $resultTab;
	}

	public function recurseTab(&$resultTab, $levelTab, $index, $value)
	{
		if (isset($levelTab[$index]))
			$this->recurseTab($resultTab[$levelTab[$index]], $levelTab, $index + 1, $value);
		else
			$resultTab = $value;
	}

	public function webserviceTest($service = '')
	{
		// Example Params for testing
//$url = "http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx?nCdEmpresa=&sDsSenha=&sCepOrigem=$cep_origem&sCepDestino=$cep_destino&nVlPeso=$peso&nCdFormato=1&
//nlComprimento=25&nVlAltura=20&nVlLargura=25&sCdMaoPropria=n&nVlValorDeclarado=0&sCdAvisoRecebimento=n&nCdServico=$cod_servico&nVlDiametro=0&StrRetorno=xml";
       
		$wsParams = array(
		  'nCdEmpresa' => '',
		  'sDsSenha' => '',
			'sCepOrigem' => Configuration::get('CORREIOS_CARRIER_POSTAL_CODE'),
      'sCepDestino' => Configuration::get('CORREIOS_CARRIER_POSTAL_CODE'),
      'nVlPeso'  => '1',
      'nCdFormato' => '1',
      'nVlComprimento' => '25',
      'nVlAltura' => '20',
      'nVlLargura' => '25',
      'sCdMaoPropria' => 'N',
      'nVlValorDeclarado' => '0.00',
      'sCdAvisoRecebimento' => 'N',
			'nCdServico' => '41106',
			'nVlDiametro' => '0',
      'StrRetorno' => 'xml',
      'id_cart' => '0',
      'hash' => '',
			'package_list' => array(
				array('width' => 10, 'height' => 3, 'depth' => 10, 'weight' => 2.0, 'packaging_type' => Configuration::get('CORREIOS_CARRIER_PACKAGING_TYPE')),
				array('width' => 3, 'height' => 3, 'depth' => 3, 'weight' => 1.0, 'packaging_type' => Configuration::get('CORREIOS_CARRIER_PACKAGING_TYPE')),
			),
		);

		// Unit or Large Test
		if (!empty($service))
			$servicesList = array(array('code' => $service));
		else
			$servicesList = Db::getInstance()->ExecuteS('SELECT `code` FROM `'._DB_PREFIX_.'correios_rate_service_code');	

		// Testing Service
		foreach ($servicesList as $service)
		{
			// Sending Request
			$wsParams['service'] = $service['code'];
			$resultTab = Db::getInstance()->getValue('SELECT `result` FROM `'._DB_PREFIX_.'correios_cache_test` WHERE `hash` = \''.pSQL(md5($this->getURL($wsParams))).'\'');
			if ($resultTab)
				$resultTab = json_decode($resultTab, True);
			else
				$resultTab = $this->sendRequest($wsParams);
			// Return results
			if (isset($resultTab['Erro']))
			{
				Db::getInstance()->autoExecute(_DB_PREFIX_.'correios_cache_test', array('hash' => pSQL(md5($this->getURL($wsParams))), 'result' => pSQL(json_encode($resultTab)), 'date_add' => pSQL(date('Y-m-d H:i:s')), 'date_upd' => pSQL(date('Y-m-d H:i:s'))), 'INSERT');
				return true;
			}

			if (isset($resultTab['MsgErro']))
				$this->_webserviceError = $this->l('Error').' '.$resultTab['Erro'].' : '.$resultTab['MsgErro'];
			else
			{ 
				$this->_webserviceError = $this->l('Correios Webservice seems to be down, please wait a few minutes and try again');
				return false;
			}
		}

		return false;
	}

	public function getCorreiosShippingCost($wsParams)
	{
		// Check Arguments
		if (!$wsParams)
			return array('connect' => false, 'cost' => 0);

		// Sending Request
		

		$resultTab = $this->sendRequest($wsParams);
		
		// Check currency
		$conversionRate = 1.00;
		if (isset($resultTab['RATINGSERVICESELECTIONRESPONSE']['RATEDSHIPMENT']['TOTALCHARGES']['CURRENCYCODE']))
		{
			$id_currency_return = Db::getInstance()->getValue('SELECT `id_currency` FROM `'._DB_PREFIX_.'currency` WHERE `iso_code` = \''.pSQL($resultTab['RATINGSERVICESELECTIONRESPONSE']['RATEDSHIPMENT']['TOTALCHARGES']['CURRENCYCODE']).'\'');
			$conversionRate = $this->getCookieCurrencyRate($id_currency_return);
		} 

		// Return results
		if (isset ($resultTab['Valor']) && $resultTab['Erro'] == '0')
			return array('connect' => true, 'cost' => $resultTab['Valor'] * $conversionRate);

		if (isset($resultTab['Erro']) && $resultTab['Erro'] != '0')
			$this->_webserviceError = $resultTab['Erro']['MsgErro'];
		else
			$this->_webserviceError = $this->l('Correios Webservice seems to be down, please wait a few minutes and try again');

		return array('connect' => false, 'cost' => 0);
	}

	public function sendRequest($wsParams)
	{
		// POST Request
		$errno = $errstr = $result = '';
		$url = $this->getURL($wsParams);
    $xml = simplexml_load_file($url);

    if (!$xml)
  	{
	   	$this->_webserviceError = $this->l('Could not connect to correios.com.br');
			return false;
		}

		// Parsing XML
		$resultTab = $this->parseXML($xml);
		return $resultTab;
	}

	public function getURL($wsParams = array())
	{

		$search = array(
		  '[[nCdEmpresa]]',
		  '[[sDsSenha]]',
			'[[sCepOrigem]]',
      '[[sCepDestino]]',
      '[[nVlPeso]]',
      '[[nCdFormato]]',
      '[[nVlComprimento]]',
      '[[nVlAltura]]',
      '[[nVlLargura]]',
      '[[sCdMaoPropria]]',
      '[[nVlValorDeclarado]]',
      '[[sCdAvisoRecebimento]]',
			'[[nCdServico]]',
			'[[nVlDiametro]]',
      '[[StrRetorno]]',
		);

		$replace = array(
			$wsParams['nCdEmpresa'],
			$wsParams['sDsSenha'],
			$wsParams['sCepOrigem'],
			$wsParams['sCepDestino'],
			$wsParams['nVlPeso'],
			$wsParams['nCdFormato'],
			$wsParams['nVlComprimento'],
			$wsParams['nVlAltura'],
			$wsParams['nVlLargura'],
			$wsParams['sCdMaoPropria'],
			$wsParams['nVlValorDeclarado'],
			$wsParams['sCdAvisoRecebimento'],
			$wsParams['nCdServico'],
			$wsParams['nVlDiametro'],
			$wsParams['StrRetorno']
		);

    $urlTemplate = 'http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx?nCdEmpresa=[[nCdEmpresa]]&sDsSenha=[[sDsSenha]]&sCepOrigem=[[sCepOrigem]]&sCepDestino=[[sCepDestino]]&nVlPeso=[[nVlPeso]]&nCdFormato=[[nCdFormato]]&nVlComprimento=[[nVlComprimento]]&nVlAltura=[[nVlAltura]]&nVlLargura=[[nVlLargura]]&sCdMaoPropria=[[sCdMaoPropria]]&nVlValorDeclarado=[[nVlValorDeclarado]]&sCdAvisoRecebimento=[[sCdAvisoRecebimento]]&nCdServico=[[nCdServico]]&nVlDiametro=[[nVlDiametro]]&StrRetorno=[[StrRetorno]]'; 
		$url = str_replace($search, $replace, $urlTemplate);
		return $url;
	} 
}
?>

