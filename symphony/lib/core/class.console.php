<?php

	/**
	 * @package core
	 */

	require_once CORE . '/interface.singleton.php';

	class Console implements Singleton {
		protected static $_instance = null;

		public static function instance($id = null) {
			if (!(self::$_instance instanceof Console)) {
				self::$_instance = new Console($id);
			}

			return self::$_instance;
		}
	}