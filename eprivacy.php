<?php

/**
 * @package plugin System - EU e-Privacy Directive
 * @copyright (C) 2010-2011 RicheyWeb - www.richeyweb.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * System - EU e-Privacy Directive Copyright (c) 2011 Michael Richey.
 * System - EU e-Privacy Directive is licensed under the http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 */
// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');

/**
 * ePrivacy system plugin
 */
class plgSystemePrivacy extends JPlugin {

	public $_cookieACL;
	public $_defaultACL;
	public $_prep;
	public $_eprivacy;
	public $_clear;
	public $_country;
	public $_display;
	public $_displayed;
	public $_displaytype;
	public $_config;
	public $_exit;
	public $_eu;
	public $_app;
	public $_doc;

//	public function onAfterInitialise() {
	public function onAfterRoute() {
		$this->_pluginDefaults();
		if ($this->_exitEarly(true) || $this->_isGuest())
		{
			return;
		}
		$app = JFactory::getApplication();

		// guests who have accepted
		if ($app->getUserState('plg_system_eprivacy', false))
		{
			$this->_groupadded = true;
			$this->_display = false;
			$this->_eprivacy = true;
			$this->_addViewLevel();
			return;
		}

		// guests who have already accepted and have a cookie
		if ($this->_hasLongTermCookie())
			return;


		// are they in a country where eprivacy is required?
		if ($this->params->get('geoplugin', false))
		{
			$this->_useGeoPlugin();
		}
		else
		{
			$app->setUserState('plg_system_eprivacy_non_eu', false);
		}

		if (!$this->_eprivacy)
		{
			$this->_cleanHeaders();
		}
		return true;
	}

	public function _pluginDefaults() {
		$userconfig = JComponentHelper::getParams('com_users');
		$this->_cookieACL = (integer) $this->params->get('cookieACL', $userconfig->get('guest_usergroup', 1));
		$this->_defaultACL = (integer) $userconfig->get('guest_usergroup', 1);
		$this->_groupadded = false;
		$this->_prep = false;
		$this->_eprivacy = false;
		$this->_clear = array();
		$this->_country = false;
		$this->_display = true;
		$this->_displayed = false;
		$this->_displaytype = $this->params->get('displaytype', 'message');
		$this->_exit = false;
		$this->_eu = array(
			/* special cases - we run these just to be safe */
			'Anonymous Proxy', 'Satellite Provider',
			/* member states */
			'Austria', 'Belgium', 'Bulgaria', 'Cyprus', 'Czech Republic', 'Denmark', 'Estonia', 'Finland', 'France', 'Germany',
			'Greece', 'Hungary', 'Ireland', 'Italy', 'Latvia', 'Lithuania', 'Luxembourg', 'Malta', 'Netherlands', 'Poland',
			'Portugal', 'Romania', 'Slovakia', 'Slovenia', 'Spain', 'Sweden', 'United Kingdom',
			/* overseas member state territories */
			'Virgin Islands, British'/* United Kingdom */,
			'French Guiana', 'Guadeloupe', 'Martinique', 'Reunion'/* France */
		);
	}

	public function onBeforeCompileHead() {
		$this->_pagePrepJS($this->_displaytype, $this->_display);
		if ($this->_exitEarly())
		{
			return true;
		}
		$this->_requestAccept();
		if (!$this->_eprivacy)
			$this->_cleanHeaders();
		return true;
	}

	public function onBeforeRender() {
		if ($this->_exitEarly())
			return true;
		// because JAT3 is lame!
		$this->onBeforeCompileHead();
	}

	public function onAfterRender() {
		if ($this->_exitEarly())
			return true;
		if (!$this->_eprivacy)
			$this->_cleanHeaders();
		return true;
	}

	private function _cleanHeaders() {
		header_remove('Set-Cookie');
		$app = JFactory::getApplication();
		if (isset($_SERVER['HTTP_COOKIE']))
		{
			$config = JFactory::getConfig();
			$cookiedomains = (array) $this->params->get('cookiedomains', array());
			$cookies = explode(';', $_SERVER['HTTP_COOKIE']);
			foreach ($cookies as $cookie)
			{
				if(strlen(trim($cookie))) {
					$this->_killCookie($cookie, $cookiedomains, $app, $config);
				}
//				$parts = explode('=', $cookie);
//				$name = trim($parts[0]);
//				$app->input->cookie->set($name, '', time() - 1000);
//				$app->input->cookie->set($name, '', time() - 1000, $config->get('cookie_path', '/'));
//				$app->input->cookie->set($name, '', time() - 1000, $config->get('cookie_path', '/'), $config->get('cookie_domain', filter_input(INPUT_SERVER, 'HTTP_HOST')));
//				if (!count($cookiedomains))
//				{
//					continue;
//				}
//				foreach ($cookiedomains as $o)
//				{
//					$app->input->cookie->set($name, '', time() - 1000, $config->get('cookie_path', '/'), $o->domain);
//				}
			}
		}
	}
	
	private function _killCookie($cookie,$cookiedomains,$app,$config) {
		$parts = explode('=', $cookie);
		$name = trim($parts[0]);
		$app->input->cookie->set($name, '', time() - 86400);
		$app->input->cookie->set($name, '', -time() - 86400, $config->get('cookie_path','/'), $config->get('cookie_domain',filter_input(INPUT_SERVER,'HTTP_HOST')));
		if(count($cookiedomains)) {
			foreach($cookiedomains as $o) {
				$app->input->cookie->set($name, '', time() - 86400, $config->get('cookie_path','/'),$o->domain);
			}
		}		
	}

	private function _requestAccept() {
		if (JFactory::getUser()->id)
			return true;
		$app = JFactory::getApplication();
		switch ($this->params->get('displaytype', 'message'))
		{
			case 'message':
				if ($this->_display && !$this->_displayed)
				{
					$this->_displayed = true;
					$msg = $this->_setMessage();
					$app->enqueueMessage($msg, $this->params->get('messagetype', 'message'));
				}
				break;
			default:
				break;
		}
	}

	private function _pagePrepJS($type, $autoopen = true) {
		if (JFactory::getApplication()->isAdmin() || $this->_prep)
		{
			return;
		}
		$doc = JFactory::getDocument();
		$config = JFactory::getConfig();
		$min = $config->get('debug', false) ? '':'.min';
		JHtml::_('jquery.framework', true, true);
		$scriptoptions = version_compare(JVERSION, '3.7.0','lt')?'text/javascript':array('version'=>'auto');
		$doc->addScript(JURI::root(true) . '/media/plg_system_eprivacy/js/eprivacy.class' . $min . '.js', $scriptoptions);
		$this->loadLanguage('plg_system_eprivacy');
		$options = array('displaytype' => $type, 'autoopen' => in_array($autoopen, array('modal', 'confirm')), 'accepted' => ($this->_eprivacy ? true : false), 'root'=>JURI::root(true));
		$cookie_domain = $config->get('cookie_domain','');
		$cookie_path = $config->get('cookie_path','');
		$options['cookie'] = array(
				'domain'=>(strlen(trim($cookie_domain))>0)?$cookie_domain:'.'.filter_input(INPUT_SERVER,'HTTP_HOST'),
				'path'=>strlen($cookie_path)?$cookie_path:null,
		);
		if ($this->_config['geopluginjs'] === true)
		{
			$options['geopluginjs'] = true;
			$options['country'] = $this->_country;
			$doc->addScript('http://www.geoplugin.net/javascript.gp');
		}
		if (in_array($type, array('message', 'confirm', 'module', 'modal', 'ribbon')))
		{
			$this->_getCSS('module');
			$this->_jsStrings($type);
		}
		switch ($type)
		{
			case 'message':
			case 'confirm':
			case 'module':
				break;
			case 'modal':
				$agreebutton = '<button class="plg_system_eprivacy_agreed btn btn-success">' . JText::_('PLG_SYS_EPRIVACY_AGREE') . '</button>';
				$declinebutton = '<button class="plg_system_eprivacy_declined btn btn-danger">' . JText::_('PLG_SYS_EPRIVACY_DECLINE') . '</button>';
				$modaloptions = array(
					'title' => JText::_('PLG_SYS_EPRIVACY_MESSAGE_TITLE'),
					'backdrop' => 'static',
					'keyboard' => false,
					'closeButton' => false,
					'footer' => $agreebutton . $declinebutton
				);
				$modalbody = '<p>' . JText::_('PLG_SYS_EPRIVACY_MESSAGE') . '</p>';
				$modallinks = array();
				if (strlen($this->params->get('policyurl', '')))
				{
					$modallinks[] = '<a href="' . $this->params->get('policyurl', '') . '" target="' . $this->params->get('policytarget', '_blank') . '">' . JText::_('PLG_SYS_EPRIVACY_POLICYTEXT') . '</a>';
				}
				if ($this->params->get('lawlink', 1))
				{
					$modallinks[] = '<a href="' . $this->_getLawLink() . '" target="_BLANK">' . JText::_('PLG_SYS_EPRIVACY_LAWLINK_TEXT') . '</a>';
				}
				if (count($modallinks))
				{
					$modalbody.='<ul><li>' . implode('</li><li>', $modallinks) . '</li></ul>';
				}
				$options['modalmarkup'] = JHtml::_('bootstrap.renderModal', 'eprivacyModal', $modaloptions, $modalbody);
				break;
			case 'ribbon':
				$this->_getCSS('ribbon', $min);
				$options['policyurl'] = $this->params->get('policyurl', '');
				$options['policytarget'] = $this->params->get('policytarget', '_blank');
				$options['agreeclass'] = $this->params->get('ribbonagreeclass','');
				$options['declineclass'] = $this->params->get('ribbondeclineclass','');
				if ($this->params->get('lawlink', 1))
				{
					$url = $this->_getLawLink();
				}
				else
				{
					$url = '';
				}
				$options['lawlink'] = $url;
				break;
			case 'cookieblocker';
				break;
		}
		if ($type === 'cookieblocker')
		{
			$doc->addStyleDeclaration("\n#plg_system_eprivacy { width:0px;height:0px;clear:none; BEHAVIOR: url(#default#userdata); }\n");
		}
		$doc->addScriptOptions('plg_system_eprivacy', $options);
		$this->_prep = true;
	}

	private function _getLawLink() {
		$lang = explode('-', JFactory::getLanguage()->getTag());
		$langtag = strtoupper($lang[0]);
		$linklang = 'EN';
		if (in_array($langtag, array('BG', 'ES', 'CS', 'DA', 'DE', 'ET', 'EL', 'EN', 'FR', 'GA', 'IT', 'LV', 'LT', 'HU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SL', 'FI', 'SV')))
		{
			$linklang = $langtag;
		}
		$url = 'http://eur-lex.europa.eu/LexUriServ/LexUriServ.do?uri=CELEX:32002L0058:' . $linklang . ':NOT';
		return $url;
	}

	private function _setMessage() {
		$msg = '<div class="plg_system_eprivacy_message">';
		$msg.= '<h2>' . JText::_('PLG_SYS_EPRIVACY_MESSAGE_TITLE') . '</h2>';
		$msg.= '<p>' . JText::_('PLG_SYS_EPRIVACY_MESSAGE') . '</p>';

		if (strlen(trim($this->params->get('policyurl', ''))))
		{
			$msg.= '<p><a href="' . trim($this->params->get('policyurl', '')) . '" target="' . $this->params->get('policytarget', '_blank') . '">' . JText::_('PLG_SYS_EPRIVACY_POLICYTEXT') . '</a></p>';
		}
		if ($this->params->get('lawlink', 1))
		{
			$msg.= '<p><a href="' . $this->_getLawLink() . '" onclick="window.open(this.href);return false;">' . JText::_('PLG_SYS_EPRIVACY_LAWLINK_TEXT') . '</a></p>';
		}

		$msg.= '<button class="plg_system_eprivacy_agreed">' . JText::_('PLG_SYS_EPRIVACY_AGREE') . '</button>';
		$msg.= '<button class="plg_system_eprivacy_declined">' . JText::_('PLG_SYS_EPRIVACY_DECLINE') . '</button>';
		$msg.= '<div id="plg_system_eprivacy"></div>';
		$msg.= '</div>';
		$msg.= '<div class="plg_system_eprivacy_declined">';
		$msg.= JText::_('PLG_SYS_EPRIVACY_DECLINED');
		$msg.= '<button class="plg_system_eprivacy_reconsider">' . JText::_('PLG_SYS_EPRIVACY_RECONSIDER') . '</button>';
		$msg.= '</div>';
		return $msg;
	}

	private function _useGeoPlugin() {
		require_once(JPATH_ROOT . '/plugins/system/eprivacy/geoplugin.class.php');
		if (function_exists('curl_init') || ini_get('allow_url_fopen'))
		{
			$geoplugin = new geoPlugin();
			$geoplugin->locate();
			if (!in_array(trim($geoplugin->countryName), $this->_eu))
			{
				$this->_eprivacy = true;
				$this->_display = false;
				$this->_addViewLevel();
				JFactory::getApplication()->setUserState('plg_system_eprivacy', true);
				JFactory::getApplication()->setUserState('plg_system_eprivacy_non_eu', true);
			}
			else
			{
				JFactory::getApplication()->setUserState('plg_system_eprivacy_non_eu', false);
				$this->_country = trim($geoplugin->countryName);
				$this->_eprivacy = false;
				$this->_display = true;
			}
		}
		else
		{
			$this->_eprivacy = false;
			$this->_country = 'Geoplugin JS: Country Not Available to PHP';
			$this->_config = array('geopluginjs' => true);
		}
	}

	private function _jsStrings($type) {
		$strings = array(
			'message' => array('CONFIRMUNACCEPT'),
			'module' => array('CONFIRMUNACCEPT'),
			'modal' => array('MESSAGE_TITLE', 'MESSAGE', 'POLICYTEXT', 'LAWLINK_TEXT', 'AGREE', 'DECLINE', 'CONFIRMUNACCEPT'),
			'confirm' => array('MESSAGE', 'JSMESSAGE', 'CONFIRMUNACCEPT'),
			'ribbon' => array('MESSAGE', 'POLICYTEXT', 'LAWLINK_TEXT', 'AGREE', 'DECLINE', 'CONFIRMUNACCEPT')
		);
		foreach ($strings[$type] as $string)
		{
			JText::script('PLG_SYS_EPRIVACY_' . $string);
		}
	}

	private function _exitEarly($initialise = false) {
		if ($this->_exit)
		{
			return true;
		}
		$app = JFactory::getApplication();
		// plugin should only run in the front-end
		if ($app->isAdmin())
		{
			$this->_exit = true;
			return true;
		}

		// shouldn't run in raw output
		if ($app->input->get('format', '', 'cmd') == 'raw')
		{
			$this->_exit = true;
			return true;
		}
		
		// don't interfere with ajax calls
		if ($app->input->getCmd('option', false) === 'com_ajax')
		{
			$this->_exit = true;
			return true;
		}
		
		// plugin should only run in HTML pages
		$doc = JFactory::getDocument();
		if (!$initialise)
		{
			if ($doc->getType() != 'html')
			{
				$this->_exit = true;
				return true;
			}
		}

		return false;
	}

	private function _isGuest() {
		$user = JFactory::getUser();
		if (!$user->guest)
		{
			$this->_exit = true;
			$this->_addViewLevel();
			$this->_display = false;
			$this->_eprivacy = true;
			return true;
		}
		return false;
	}

	private function _hasLongTermCookie() {
		$app = JFactory::getApplication();
		if ($this->params->get('longtermcookie', false))
		{
			$accepted = $app->input->cookie->get('plg_system_eprivacy', false);
			if ($accepted)
			{
				$config = JFactory::getConfig();
				$this->_addViewLevel();
				$this->_eprivacy = true;
				$this->_display = false;
				$cookie_path = strlen($config->get('cookie_path')) ? $config->get('cookie_path') : '/';
				$cookie_domain = strlen($config->get('cookie_domain')) ? $config->get('cookie_domain') : $_SERVER['HTTP_HOST'];
				$app->input->cookie->set('plg_system_eprivacy', $accepted, time() + 60 * 60 * 24 * (int) $this->params->get('longtermcookieduration', 30), $cookie_path, $cookie_domain);
				return true;
			}
		}
		return false;
	}

	private function _reflectJUser($remove = false) {
		$user = JFactory::getUser();
		$JAccessReflection = new ReflectionClass('JUser');
		$_authLevels = $JAccessReflection->getProperty('_authLevels');
		$_authLevels->setAccessible(true);
		$groups = $_authLevels->getValue($user);
		switch ($remove)
		{
			case 'remove':
				$key = array_search($this->_cookieACL, $groups);
				if ($key)
				{
					unset($groups[$key]);
					$this->_groupadded = false;
				}
				break;
			default:
				if (!array_search($this->_cookieACL, $groups))
				{
					$groups[] = $this->_cookieACL;
					$this->_groupadded = true;
				}
				break;
		}
		$_authLevels->setValue($user, $groups);
	}

	private function _addViewLevel($remove = false) {
		if (!class_exists('ReflectionClass', false) || !method_exists('ReflectionProperty', 'setAccessible'))
			return;
		if ($this->_defaultACL == $this->_cookieACL)
			return;
		$this->_reflectJUser($remove);
	}

	private function _getCSS($type, $min = '.min') {
		$doc = JFactory::getDocument();
		switch ($type)
		{
			case 'ribbon':
				if ($this->params->get('useribboncss', 1))
				{
					$doc->addStyleSheet(JURI::root(true) . '/media/plg_system_eprivacy/css/ribbon' . $min . '.css', array('version' => 'auto'));
					$doc->addStyleDeclaration($this->params->get('ribboncss'));
				}
				break;
			case 'module':
				if ($this->params->get('usemodulecss', 1))
				{
					$doc->addStyleDeclaration($this->params->get('modulecss'));
				}
				break;
			default:
				break;
		}
	}

}
