<?php

	/**
	 * @package core
	 */

	/**
	 * The Administration class is an instance of Symphony that controls
	 * all backend pages. These pages are HTMLPages are usually generated
	 * using XMLElement before being rendered as HTML. These pages do not
	 * use XSLT. The Administration is only accessible by logged in Authors
	 */
	require_once TOOLKIT . '/class.htmlpage.php';
	require_once TOOLKIT . '/class.ajaxpage.php';

	class DevKit extends Symphony {
		/**
		 * The path of the current page, ie. '/blueprints/sections/'
		 * @var string
		 */
		private $_currentPage  = null;

		/**
		 * The class representation of the current Symphony backend page,
		 * which is a subclass of the `HTMLPage` class. Symphony uses a convention
		 * of prefixing backend page classes with 'content'. ie. 'contentBlueprintsSections'
		 * @var HTMLPage
		 */
		public $Page;

		/**
		 * This function returns an instance of the Administration
		 * class. It is the only way to create a new Administration, as
		 * it implements the Singleton interface
		 *
		 * @return DevKit
		 */
		public static function instance() {
			if (!(self::$_instance instanceof DevKit)) {
				self::$_instance = new DevKit;
			}

			return self::$_instance;
		}

		public static function buildQueryString($params = array()) {
			parse_str($_SERVER['QUERY_STRING'], $query);

			$query = array_diff_key($query, array(
				'symphony-page'	=> null
			));

			$query = array_merge($query, $params);

			return '?' . http_build_query($query, '&');
		}

		/**
		 * Returns the current Page path, excluding the domain and Symphony path.
		 *
		 * @return string
		 *  The path of the current page, ie. '/blueprints/sections/'
		 */
		public function getCurrentPageURL() {
			return $this->_currentPage;
		}

		/**
		 * Overrides the Symphony isLoggedIn function to allow Authors
		 * to become logged into the backend when `$_REQUEST['auth-token']`
		 * is present. This logs an Author in using the loginFromToken function.
		 * A token may be 6 or 8 characters in length in the backend. A 6 character token
		 * is used for forget password requests, whereas the 8 character token is used to login
		 * an Author into the page
		 *
		 * @see core.Symphony#loginFromToken()
		 * @return boolean
		 */
		public function isLoggedIn(){
			if (isset($_REQUEST['auth-token']) && $_REQUEST['auth-token'] && in_array(strlen($_REQUEST['auth-token']), array(6, 8))) {
				return $this->loginFromToken($_REQUEST['auth-token']);
			}

			return Symphony::initialiseLogin();
		}

		/**
		 * Called by index.php, this function is responsible for rendering the current
		 * page on the Frontend. Two delegates are fired, AdminPagePreGenerate and
		 * AdminPagePostGenerate.
		 *
		 * @param HTMLPage $page
		 * @return string
		 *  The HTML of the page to return.
		 */
		public function display(HTMLPage $page) {
			$this->Page = $page;
			$output = $this->Page->generate();

			return $output;
		}
	}
