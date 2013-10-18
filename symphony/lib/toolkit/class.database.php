<?php

	Class Database {

		public $conn = null;

		/**
		 * Sets the current `$_log` to be an empty array
		 *
		 * @var array
		 */
		public $log = array();

		/**
		 * The number of queries this class has executed, defaults to 0.
		 *
		 * @var integer
		 */
		private $_query_count = 0;

		/**
		 * Whether query caching is enabled or not. By default this set
		 * to true which will use SQL_CACHE to cache the results of queries
		 *
		 * @var boolean
		 */
		private $_cache = true;

		private $_prefix = 'sym_';

		/**
		 * Sets query caching to true, this will prepend all READ_OPERATION
		 * queries with SQL_CACHE. Symphony be default enables caching. It
		 * can be turned off by setting the query_cache parameter to 'off' in the
		 * Symphony config file.
		 *
		 * @link http://dev.mysql.com/doc/refman/5.1/en/query-cache.html
		 */
		public function enableCaching(){
			$this->_cache = true;
		}

		/**
		 * Sets query caching to false, this will prepend all READ_OPERATION
		 * queries will SQL_NO_CACHE.
		 */
		public function disableCaching(){
			$this->_cache = false;
		}

		/**
		 * Returns boolean if query caching is enabled or not
		 *
		 * @return boolean
		 */
		public function isCachingEnabled(){
			return $this->_cache;
		}

		/**
		 * Returns the number of queries that has been executed
		 *
		 * @return integer
		 */
		public function queryCount(){
			return $this->_query_count;
		}

		/**
		 * Symphony uses a prefix for all it's database tables so it can live peacefully
		 * on the same database as other applications. By default this is sym_, but it
		 * can be changed when Symphony is installed.
		 *
		 * @param string $prefix
		 *  The table prefix for Symphony, by default this is sym_
		 */
		public function setPrefix($prefix){
			$this->_prefix = $prefix;
		}

		public function flush(){
			$this->_result = null;
			$this->_lastResult = array();
			$this->_lastQuery = null;
			$this->_lastQueryHash = null;
		}

		public function __construct($dsn = null, $username = null, $password = null, array $options = array()) {
			return $this->connect($dsn, $username, $password, $options);
		}

		public function connect($dsn = null, $username = null, $password = null, array $options = array()) {
			try {
				$this->conn = new PDO($dsn, $username, $password, $options);
				$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			}
			catch (PDOException $ex) {
				$this->error($ex);

				return false;
			}

			return true;
		}

		public function determineQueryType($query){
			return (preg_match('/^(set|create|insert|replace|alter|delete|update|optimize|truncate|drop)/i', $query) ? MySQL::__WRITE_OPERATION__ : MySQL::__READ_OPERATION__);
		}

		public function query($query, $type = "OBJECT", $params = array()) {
			if(empty($query)) return false;

			$start = precision_timer();
			$query = trim($query);
			$query_type = $this->determineQueryType($query);
			$query_hash = md5($query.$start);

			if($this->_prefix != 'tbl_'){
				$query = preg_replace('/tbl_(\S+?)([\s\.,]|$)/', $this->_prefix .'\\1\\2', $query);
			}

			// TYPE is deprecated since MySQL 4.0.18, ENGINE is preferred
			if($query_type == MySQL::__WRITE_OPERATION__) {
				$query = preg_replace('/TYPE=(MyISAM|InnoDB)/i', 'ENGINE=$1', $query);
			}
			else if($query_type == MySQL::__READ_OPERATION__ && !preg_match('/^SELECT\s+SQL(_NO)?_CACHE/i', $query)){
				if($this->isCachingEnabled()) {
					$query = preg_replace('/^SELECT\s+/i', 'SELECT SQL_CACHE ', $query);
				}
				else {
					$query = preg_replace('/^SELECT\s+/i', 'SELECT SQL_NO_CACHE ', $query);
				}
			}

			$this->flush();
			$this->_lastQuery = $query;
			$this->_lastQueryHash = $query_hash;

			try {
				$this->_result = $this->conn->prepare($query);
				$this->_result->execute();
				$this->_query_count++;
			}
			catch (PDOException $ex) {
				$this->error($ex);
			}

			if($this->conn->errorCode() != '00000'){
				$this->error();
			}
			else if($this->_result instanceof PDOStatement && $query_type == MySQL::__READ_OPERATION__) {
				$this->_lastQuery = $this->_result->queryString;

				if($type == "ASSOC") {
					if(isset($params['offset'])) {
						while ($row = $this->_result->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_ABS, $params['offset'])) {
							$this->_lastResult = $row;
						}
					}
					else {
						while ($row = $this->_result->fetch(PDO::FETCH_ASSOC)) {
							$this->_lastResult[] = $row;
						}
					}
				}
				else {
					while ($row = $this->_result->fetchObject()) {
						$this->_lastResult[] = $row;
					}
				}
			}
			$this->_result->closeCursor();
			$stop = precision_timer('stop', $start);

			/**
			 * After a query has successfully executed, that is it was considered
			 * valid SQL, this delegate will provide the query, the query_hash and
			 * the execution time of the query.
			 *
			 * Note that this function only starts logging once the ExtensionManager
			 * is available, which means it will not fire for the first couple of
			 * queries that set the character set.
			 *
			 * @since Symphony 2.3
			 * @delegate PostQueryExecution
			 * @param string $context
			 * '/frontend/' or '/backend/'
			 * @param string $query
			 *  The query that has just been executed
			 * @param string $query_hash
			 *  The hash used by Symphony to uniquely identify this query
			 * @param float $execution_time
			 *  The time that it took to run `$query`
			 */
			if(Symphony::ExtensionManager() instanceof ExtensionManager) {
				Symphony::ExtensionManager()->notifyMembers('PostQueryExecution', class_exists('Administration') ? '/backend/' : '/frontend/', array(
					'query' => $query,
					'query_hash' => $query_hash,
					'execution_time' => $stop
				));

				// If the ExceptionHandler is enabled, then the user is authenticated
				// or we have a serious issue, so log the query.
				if(GenericExceptionHandler::$enabled) {
					$this->_log[$query_hash] = array(
						'query' => $query,
						'query_hash' => $query_hash,
						'execution_time' => $stop
					);
				}
			}

			// Symphony isn't ready yet. Log internally
			else {
				$this->_log[$query_hash] = array(
					'query' => $query,
					'query_hash' => $query_hash,
					'execution_time' => $stop
				);
			}

			return true;
		}

		/**
		 * Returns the last insert ID from the previous query. This is
		 * the value from an auto_increment field.
		 *
		 * @return integer
		 *  The last interested row's ID
		 */
		public function getInsertID(){
			return $this->conn->lastInsertId();
		}

		public function fetch($query = null, $index_by_column = null, $params = array()){
			if(!is_null($query)) {
				$this->query($query, 'ASSOC', $params);
			}
			else if(is_null($this->_lastResult) || $this->_lastResult === false) {
				return array();
			}

			$result = $this->_lastResult;

			if(!is_null($index_by_column) && isset($result[0][$index_by_column])){
				$n = array();

				foreach($result as $ii) {
					$n[$ii[$index_by_column]] = $ii;
				}

				$result = $n;
			}

			return $result;
		}

		private function error(Exception $ex) {
			$msg = $ex->getMessage();
			$errornum = $ex->getCode();

			/**
			 * After a query execution has failed this delegate will provide the query,
			 * query hash, error message and the error number.
			 *
			 * Note that this function only starts logging once the `ExtensionManager`
			 * is available, which means it will not fire for the first couple of
			 * queries that set the character set.
			 *
			 * @since Symphony 2.3
			 * @delegate QueryExecutionError
			 * @param string $context
			 * '/frontend/' or '/backend/'
			 * @param string $query
			 *  The query that has just been executed
			 * @param string $query_hash
			 *  The hash used by Symphony to uniquely identify this query
			 * @param string $msg
			 *  The error message provided by MySQL which includes information on why the execution failed
			 * @param integer $num
			 *  The error number that corresponds with the MySQL error message
			 */
			if(Symphony::ExtensionManager() instanceof ExtensionManager) {
				Symphony::ExtensionManager()->notifyMembers('QueryExecutionError', class_exists('Administration') ? '/backend/' : '/frontend/', array(
					'query' => $this->_lastQuery,
					'query_hash' => $this->_lastQueryHash,
					'msg' => $msg,
					'num' => $errornum
				));
			}

			throw $ex;
		}


	}