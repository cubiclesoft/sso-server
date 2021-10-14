<?php
	// CubicleSoft MySQL/Maria DB lightweight database interface.
	// (C) 2021 CubicleSoft.  All Rights Reserved.

	if (!class_exists("CSDB", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/db.php";

	class CSDB_mysql_lite extends CSDB
	{
		public function IsAvailable()
		{
			return (class_exists("PDO") && in_array("mysql", PDO::getAvailableDrivers()) ? "mysql" : false);
		}

		public function GetDisplayName()
		{
			return CSDB::DB_Translate("MySQL/Maria DB (via PDO, Lite version)");
		}

		public function Connect($dsn, $username = false, $password = false, $options = array())
		{
			parent::Connect($dsn, $username, $password, $options);

			// Set Unicode support.
			$this->Query("SET", "NAMES 'utf8mb4'");
		}

		public function GetInsertID($name = null)
		{
			return $this->GetOne("SELECT", array("LAST_INSERT_ID()"));
		}

		public function QuoteIdentifier($str)
		{
			return "`" . str_replace(array("`", "?"), array("``", ""), $str) . "`";
		}

		protected function GenerateSQL(&$master, &$sql, &$opts, $cmd, $queryinfo, $args, $subquery)
		{
			switch ($cmd)
			{
				case "SELECT":
				{
					$supported = array(
						"PRECOLUMN" => array("DISTINCT" => "bool", "HIGH_PRIORITY" => "bool", "SUBQUERIES" => true),
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
						"PREINTO" => array("LOW_PRIORITY" => "bool", "DELAYED" => "bool", "HIGH_PRIORITY" => "bool", "IGNORE" => "bool"),
						"SELECT" => true,
						"BULKINSERT" => true
					);

					return $this->ProcessINSERT($master, $sql, $opts, $queryinfo, $args, $subquery, $supported);
				}
				case "UPDATE":
				{
					$supported = array(
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
						"PREFROM" => array("LOW_PRIORITY" => "bool", "QUICK" => "bool", "IGNORE" => "bool"),
						"WHERE" => array("SUBQUERIES" => true),
						"ORDER BY" => true,
						"LIMIT" => ", "
					);

					return $this->ProcessDELETE($master, $sql, $opts, $queryinfo, $args, $subquery, $supported);
				}
				case "SET":
				{
					$sql = "SET " . $queryinfo;

					return array("success" => true);
				}
				case "USE":
				{
					$sql = "USE " . $this->QuoteIdentifier($queryinfo);

					return array("success" => true);
				}
				case "TRUNCATE TABLE":
				{
					$master = true;

					$sql = "TRUNCATE TABLE " . $this->QuoteIdentifier($queryinfo[0]);

					return array("success" => true);
				}
			}

			return array("success" => false, "error" => CSDB::DB_Translate("Unknown query command '%s'.", $cmd), "errorcode" => "unknown_query_command");
		}
	}
?>