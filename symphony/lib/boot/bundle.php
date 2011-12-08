<?php

	/**
	 * @package boot
	 */

	if(!defined('PHP_VERSION_ID')){
		$version = PHP_VERSION;

		/**
		 * For versions of PHP below 5.2.7, the PHP_VERSION_ID constant, doesn't
		 * exist, so this will just mimic the functionality as described on the
		 * PHP documentation
		 *
		 * @link http://php.net/manual/en/function.phpversion.php
		 * @var integer
		 */
		define('PHP_VERSION_ID', ($version{0} * 10000 + $version{2} * 100 + $version{4}));
	}

	if (PHP_VERSION_ID >= 50300){
		error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
	}
	else{
		error_reporting(E_ALL & ~E_NOTICE);
	}

	ini_set('magic_quotes_runtime', 0);

	require_once(DOCROOT . '/symphony/lib/boot/func.utilities.php');
	require_once(DOCROOT . '/symphony/lib/boot/defines.php');

	if (!file_exists(CONFIG)) {

		if (file_exists(DOCROOT . '/install/index.php')) {
			header(sprintf('Location: %s/install/', URL));
			exit;
		}

		die('<h2>Error</h2><p>Could not locate Symphony configuration file. Please check <code>manifest/config.php</code> exists.</p>');
	}

	include(CONFIG);

	$launcher = function($mode) {
		if (strtolower($mode) == 'administration') {
			require_once CORE . "/class.administration.php";

			$renderer = Administration::instance();
		}

		else {
			require_once CORE . "/class.frontend.php";

			$renderer = Frontend::instance();
		}

		header('Expires: Mon, 12 Dec 1982 06:14:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-cache, must-revalidate, max-age=0');
		header('Pragma: no-cache');

		$output = $renderer->display(getCurrentPage());

		echo $output;
	};