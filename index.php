<?php

	define('DOCROOT', rtrim(dirname(__FILE__), '\\/'));
	define('DOMAIN', rtrim(rtrim($_SERVER['HTTP_HOST'], '\\/') . dirname($_SERVER['PHP_SELF']), '\\/'));

	require DOCROOT . '/symphony/lib/boot/bundle.php';
	require CORE . '/class.symphony.php';

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
			'launcher'	=> $launcher
		)
	);

	$launcher(
		isset($_GET['mode'])
			? $_GET['mode']
			: null
	);