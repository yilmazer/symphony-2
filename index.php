<?php

	define('DOCROOT', rtrim(dirname(__FILE__), '\\/'));
	define('DOMAIN', rtrim(rtrim($_SERVER['HTTP_HOST'], '\\/') . dirname($_SERVER['PHP_SELF']), '\\/'));

	require DOCROOT . '/symphony/lib/boot/bundle.php';
	require CORE . '/class.symphony.php';

	// These things need to be started early:
	Symphony::initialiseConfiguration();
	Symphony::initialiseDatabase();
	Symphony::initialiseExtensionManager();

	/**
	 * Overload the default Symphony launcher logic.
	 * @delegate ModifySymphonyLauncher
	 * @param string $context
	 * '/all/'
	 * @param string $launcher
	 *  The default launcher closure.
	 */
	Symphony::ExtensionManager()->notifyMembers(
		'ModifySymphonyLauncher', '/all/',
		array(
			'launcher'	=> &$launcher
		)
	);

	$launcher(
		isset($_GET['mode'])
			? $_GET['mode']
			: null
	);