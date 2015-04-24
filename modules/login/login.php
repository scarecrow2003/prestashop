<?php
if (!defined('_PS_VERSION_'))
  exit;
 
class Login extends Module
{
	public function __construct()
	{
		$this->name = 'login';
		$this->tab = 'login';
		$this->version = '1.0.0';
		$this->author = 'Zhihua';
		$this->need_instance = 0;
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_); 
		$this->bootstrap = true;
	 
		parent::__construct();
	 
		$this->displayName = $this->l('Login module');
		$this->description = $this->l('3-party Login module.');
	 
		$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
	}
  
	public function install()
	{
	  if (Shop::isFeatureActive())
		Shop::setContext(Shop::CONTEXT_ALL);
	 
	  if (!parent::install() ||
		!$this->registerHook('leftColumn') ||
		!$this->registerHook('header')
	  )
		return false;
	 
	  return true;
	}
	
	public function uninstall()
	{
	  if (!parent::uninstall() ||
		!Configuration::deleteByName('LOGIN_QQ_ID') ||
		!Configuration::deleteByName('LOGIN_QQ_SECRET') ||
		!Configuration::deleteByName('LOGIN_WEIBO_ID') ||
		!Configuration::deleteByName('LOGIN_WEIBO_SECRET') ||
		!Configuration::deleteByName('LOGIN_ALI_ID') ||
		!Configuration::deleteByName('LOGIN_ALI_SECRET')
	  )
		return false;
	 
	  return true;
	}
	
	public function getContent()
	{
		$output = null;
	 
		if (Tools::isSubmit('submit'.$this->name))
		{
			$qq_id = strval(Tools::getValue('LOGIN_QQ_ID'));
			$qq_secret = strval(Tools::getValue('LOGIN_QQ_SECRET'));
			$weibo_id = strval(Tools::getValue('LOGIN_WEIBO_ID'));
			$weibo_secret = strval(Tools::getValue('LOGIN_WEIBO_SECRET'));
			$ali_id = strval(Tools::getValue('LOGIN_ALI_ID'));
			$ali_secret = strval(Tools::getValue('LOGIN_ALI_SECRET'));
			if (!$qq_id || empty($qq_id) || !Validate::isGenericName($qq_id) ||
				!$qq_secret || empty($qq_secret) || !Validate::isGenericName($qq_secret) ||
				!$weibo_id || empty($weibo_id) || !Validate::isGenericName($weibo_id) ||
				!$weibo_secret || empty($weibo_secret) || !Validate::isGenericName($weibo_secret) ||
				!$ali_id || empty($ali_id) || !Validate::isGenericName($ali_id) ||
				!$ali_secret || empty($ali_secret) || !Validate::isGenericName($ali_secret)) {
				$output .= $this->displayError($this->l('Invalid Configuration value'));
			}
			else
			{
				Configuration::updateValue('LOGIN_QQ_ID', $qq_id);
				Configuration::updateValue('LOGIN_QQ_SECRET', $qq_secret);
				Configuration::updateValue('LOGIN_WEIBO_ID', $weibo_id);
				Configuration::updateValue('LOGIN_WEIBO_SECRET', $weibo_secret);
				Configuration::updateValue('LOGIN_ALI_ID', $ali_id);
				Configuration::updateValue('LOGIN_ALI_SECRET', $ali_secret);
				$output .= $this->displayConfirmation($this->l('Settings updated'));
			}
		}
		return $output.$this->displayForm();
	}
	
	public function displayForm()
	{
		// Get default language
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');
		 
		// Init Fields form array
		$fields_form[0]['form'] = array(
			'legend' => array(
				'title' => $this->l('QQ setting')
			),
			'input' => array(
				array(
					'type' => 'text',
					'label' => $this->l('QQ client_id'),
					'name' => 'LOGIN_QQ_ID',
					'size' => 20,
					'required' => true
				),
				array(
					'type' => 'text',
					'label' => $this->l('QQ client_secret'),
					'name' => 'LOGIN_QQ_SECRET',
					'size' => 20,
					'required' => true
				)
			),
			'submit' => array(
				'title' => $this->l('Save'),
				'class' => 'button'
			)
		);
		
		$fields_form[1]['form'] = array(
			'legend' => array(
				'title' => $this->l('Weibo setting')
			),
			'input' => array(
				array(
					'type' => 'text',
					'label' => $this->l('Weibo client_id'),
					'name' => 'LOGIN_WEIBO_ID',
					'size' => 20,
					'required' => true
				),
				array(
					'type' => 'text',
					'label' => $this->l('Weibo client_secret'),
					'name' => 'LOGIN_WEIBO_SECRET',
					'size' => 20,
					'required' => true
				)
			),
			'submit' => array(
				'title' => $this->l('Save'),
				'class' => 'button'
			)
		);
		
		$fields_form[2]['form'] = array(
			'legend' => array(
				'title' => $this->l('Alipay setting')
			),
			'input' => array(
				array(
					'type' => 'text',
					'label' => $this->l('Alipay client_id'),
					'name' => 'LOGIN_ALI_ID',
					'size' => 20,
					'required' => true
				),
				array(
					'type' => 'text',
					'label' => $this->l('Alipay client_secret'),
					'name' => 'LOGIN_ALI_SECRET',
					'size' => 20,
					'required' => true
				)
			),
			'submit' => array(
				'title' => $this->l('Save'),
				'class' => 'button'
			)
		);
		 
		$helper = new HelperForm();
		 
		// Module, token and currentIndex
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
		 
		// Language
		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;
		 
		// Title and toolbar
		$helper->title = $this->displayName;
		$helper->show_toolbar = true;        // false -> remove toolbar
		$helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
		$helper->submit_action = 'submit'.$this->name;
		$helper->toolbar_btn = array(
			'save' =>
			array(
				'desc' => $this->l('Save'),
				'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
				'&token='.Tools::getAdminTokenLite('AdminModules'),
			),
			'back' => array(
				'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
				'desc' => $this->l('Back to list')
			)
		);
		 
		// Load current value
		$helper->fields_value['LOGIN_QQ_ID'] = Configuration::get('LOGIN_QQ_ID');
		$helper->fields_value['LOGIN_QQ_SECRET'] = Configuration::get('LOGIN_QQ_SECRET');
		$helper->fields_value['LOGIN_WEIBO_ID'] = Configuration::get('LOGIN_WEIBO_ID');
		$helper->fields_value['LOGIN_WEIBO_SECRET'] = Configuration::get('LOGIN_WEIBO_SECRET');
		$helper->fields_value['LOGIN_ALI_ID'] = Configuration::get('LOGIN_ALI_ID');
		$helper->fields_value['LOGIN_ALI_SECRET'] = Configuration::get('LOGIN_ALI_SECRET');
		 
		return $helper->generateForm($fields_form);
	}
	
	public function hookDisplayLeftColumn($params)
	{
	  $this->context->smarty->assign(
		  array(
			  'qq_id' => Configuration::get('LOGIN_QQ_ID'),
			  'my_module_link' => $this->context->link->getModuleLink('login', 'display')
		  )
	  );
	  return $this->display(__FILE__, 'login.tpl');
	}
	   
	public function hookDisplayRightColumn($params)
	{
	  return $this->hookDisplayLeftColumn($params);
	}
	   
	public function hookDisplayHeader()
	{
	  $this->context->controller->addCSS($this->_path.'css/login.css', 'all');
	}
}