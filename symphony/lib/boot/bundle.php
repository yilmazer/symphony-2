<?php

	/**
	 * @package boot
	 */

	require_once DOCROOT . '/symphony/lib/boot/func.utilities.php';
	require_once DOCROOT . '/symphony/lib/boot/defines.php';
	require_once CORE . '/class.console.php';
	require_once CORE . '/class.symphony.php';

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

	// Set appropriate error reporting:
	error_reporting(
		PHP_VERSION_ID >= 50300
			? E_ALL & ~E_NOTICE & ~E_DEPRECATED
			: E_ALL & ~E_NOTICE
	);

	// Turn of old-style magic:
	ini_set('magic_quotes_runtime', false);

	// Create the Console, either with the X-Symphony-Console-Id or a new random ID:
	Console::instance(
		isset($_SERVER['HTTP_X_SYMPHONY_CONSOLE_ID'])
			? $_SERVER['HTTP_X_SYMPHONY_CONSOLE_ID']
			: md5(rand())
	);

	if (!file_exists(CONFIG)) {
		if (file_exists(DOCROOT . '/install/index.php')) {
			header(sprintf('Location: %s/install/', URL));
			exit;
		}

		die('<h2>Error</h2><p>Could not locate Symphony configuration file. Please check <code>manifest/config.php</code> exists.</p>');
	}

	// Load configuration file:
	include CONFIG;

	// These things need to be started early:
	Symphony::initialiseConfiguration();
	Symphony::initialiseDatabase();
	Symphony::initialiseExtensionManager();

	/**
	 * Overload the default Symphony launcher logic.
	 * @delegate ModifySymphonyLauncher
	 * @param string $context
	 * '/all/'
	 */
	Symphony::ExtensionManager()->notifyMembers(
		'ModifySymphonyLauncher', '/all/'
	);

	// Use default launcher:
	if (defined('SYMPHONY_LAUNCHER') === false) {
		define('SYMPHONY_LAUNCHER', 'symphony_launcher');
	}
