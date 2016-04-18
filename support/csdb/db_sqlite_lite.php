<?php
	// CubicleSoft SQLite lightweight database interface.
	// (C) 2015 CubicleSoft.  All Rights Reserved.

	if (!class_exists("CSDB"))  exit();

	class CSDB_sqlite_lite extends CSDB
	{
		protected $dbprefix;

		public function IsAvailable()
		{
			return (class_exists("PDO") && in_array("sqlite", PDO::getAvailableDrivers()) ? "sqlite" : false);
		}

		public function GetDisplayName()
		{
			return CSDB::DB_Translate("SQLite (via PDO, Lite version)");
		}

		public function Connect($dsn, $username = false, $password = false, $options = array())
		{
			$this->dbprefix = "";

			parent::Connect($dsn, $username, $password, $options);
		}

		public function GetInsertID($name = null)
		{
			return $this->GetOne("SELECT", array("LAST_INSERT_ROWID()"));
		}

		public function QuoteIdentifier($str)
		{
			return "\"" . str_replace(array("\"", "?"), array("\"\"", ""), $str) . "\"";
		}

		protected function GenerateSQL(&$master, &$sql, &$opts, $cmd, $queryinfo, $args, $subquery)
		{
			switch ($cmd)
			{
				case "SELECT":
				{
					$supported = array(
						"DBPREFIX" => $this->dbprefix,
						"PRECOLUMN" => array("DISTINCT" => "bool", "SUBQUERIES" => true),
						"FROM" => array("SUBQUERIES" => true),
						"WHERE" => array("SUBQUERIES" => true),
						"GROUP BY" => true,
						"HAVING" => true,
						"ORDER BY" => true,
						"LIMIT" => ", "
					);

					return $this->ProcessSELECT($master, $sql, $opts, $queryinfo, $args, $subquery, $supported);
				}
				case "INSERT":
				{
					$supported = array(
						"DBPREFIX" => $this->dbprefix,
						"PREINTO" => array("LOW_PRIORITY" => "bool", "DELAYED" => "bool", "HIGH_PRIORITY" => "bool", "IGNORE" => "bool"),
						"SELECT" => true,
						"BULKINSERT" => true,
						"BULKINSERTLIMIT" => 900,
					);

					return $this->ProcessINSERT($master, $sql, $opts, $queryinfo, $args, $subquery, $supported);
				}
				case "UPDATE":
				{
					$supported = array(
						"DBPREFIX" => $this->dbprefix,
						"PRETABLE" => array("LOW_PRIORITY" => "bool", "IGNORE" => "bool"),
						"WHERE" => array("SUBQUERIES" => true),
						"ORDER BY" => true,
						"LIMIT" => ", "
					);

					return $this->ProcessUPDATE($master, $sql, $opts, $queryinfo, $args, $subquery, $supported);
				}
				case "DELETE":
				{
					$supported = array(
						"DBPREFIX" => $this->dbprefix,
						"PREFROM" => array("LOW_PRIORITY" => "bool", "QUICK" => "bool", "IGNORE" => "bool"),
						"WHERE" => array("SUBQUERIES" => true),
						"ORDER BY" => true,
						"LIMIT" => ", "
					);

					return $this->ProcessDELETE($master, $sql, $opts, $queryinfo, $args, $subquery, $supported);
				}
				case "SET":
				{
					return array("success" => false, "errorcode" => "skip_sql_query");
				}
				case "USE":
				{
					$this->dbprefix = $this->GetDBPrefix($queryinfo);

					return array("success" => false, "errorcode" => "skip_sql_query");
				}
				case "TRUNCATE TABLE":
				{
					$supported = array(
						"DBPREFIX" => $this->dbprefix,
						"PREFROM" => array()
					);

					$queryinfo = array($queryinfo[0]);

					return $this->ProcessDELETE($master, $sql, $opts, $queryinfo, $args, $subquery, $supported);
				}
			}

			return array("success" => false, "error" => CSDB::DB_Translate("Unknown query command '%s'.", $cmd), "errorcode" => "unknown_query_command");
		}

		private function GetDBPrefix($str)
		{
			$str = preg_replace('/\s+/', "_", trim(str_replace("_", " ", $str)));

			return ($str != "" ? $str . "__" : "");
		}
	}
?>