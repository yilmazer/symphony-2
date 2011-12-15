<?php

	/**
	 * @package core
	 */

	class Console {
		static public $current;
		static public $root;
		static public $stack;
		static public $input;
		static public $output;
		static public $template;
		static public function init($id = null) {
			if (isset(self::$root) === false) {
				self::$root = new ConsoleStack();
				self::$current = self::$root;
			}
		}

		static public function enable() {
			self::$root->enableDataCollection();
		}

		static public function log($message) {
			self::$current->log($message);
		}

		static public function registerInput($data) {
			self::$input = $data;
		}

		static public function registerOutput($data) {
			self::$output = $data;
		}

		static public function registerTemplate($data) {
			self::$template = $data;
		}

		static public function store() {
			
		}

		static public function time($message = null) {
			if (is_null($message) === false) {
				self::$stack[] = self::$current;
				self::$current->{$message}->begin();
			}

			else {
				self::$current->begin();
			}

			return self::$current;
		}

		static public function timeEnd() {
			self::$current->end();

			return self::$current;
		}
	}

	class ConsoleStack {
		protected $enabled;
		protected $children;
		protected $start_time;
		protected $end_time;

		public function __construct($enabled = false) {
			$this->enabled = $enabled;
		}

		public function __get($message) {
			if ($this->enabled === false) return $this;

			if (isset($this->children) === false) {
				$this->children = array();
			}

			$this->children[] = new ConsoleMessage($message);
			$this->children[] = $child = new ConsoleStack(true);

			Console::$stack[] = $this;
			Console::$current = $child;

			return $child;
		}

		public function enableDataCollection() {
			$this->enabled = true;
		}

		public function log($message) {
			if ($this->enabled === false) return $this;

			if (isset($this->children) === false) {
				$this->children = array();
			}

			$this->children[] = new ConsoleMessage($message);
		}

		public function begin() {
			if ($this->enabled === false) return $this;

			$this->start_time = microtime(true);

			return $this;
		}

		public function end() {
			if ($this->enabled === false) return $this;

			$this->end_time = microtime(true);

			if (Console::$stack) {
				Console::$current = array_pop(Console::$stack);
			}

			return $this;
		}
	}

	class ConsoleMessage {
		protected $message;

		public function __construct($message) {
			$this->message = $message;
		}
	}