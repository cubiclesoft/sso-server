<?php
	// CubicleSoft Oracle/OCI database interface.
	// (C) 2015 Brian Nilson.  Released under the CubicleSoft dual MIT/LGPL license.
	//
	// Thank you for your contribution!
	//
	// Used with permission under the CubicleSoft dual MIT/LGPL license.
	// Some portions (C) 2015 CubicleSoft.  All Rights Reserved.

	// This is an early beta - use at your own risk!

	if (!class_exists("CSDB"))  exit();

	class CSDB_oci_lite extends CSDB
	{
		protected $lastid;

// This function isn't in use anywhere...
		public static function ConvertToOracleDate($ts, $gmt = true)
		{
			return ($gmt ? gmdate("Y-m-d", strtotime($ts)) : date("Y-m-d", strtotime($ts))) . " 00:00:00";
		}

// This function also isn't in use anywhere...
		public static function ConvertToOracleTime($ts, $gmt = true)
		{
			return ($gmt ? gmdate("Y-m-d H:i:s", strtotime($ts)) : date("Y-m-d H:i:s", strtotime($ts)));
		}

		public function IsAvailable()
		{
			return (class_exists("PDO") && in_array("oci", PDO::getAvailableDrivers()) ? "oci" : false);
		}

		public function GetDisplayName()
		{
			return CSDB::DB_Translate("Oracle (via PDO) - Early beta");
		}

		public function Connect($dsn, $username = false, $password = false, $options = array())
		{
			$this->lastid = 0;

			parent::Connect($dsn, $username, $password, $options);

			// Convert DB NULL values into empty strings for use in code.
			$this->dbobj->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_TO_STRING);

			// Converts all uppercase table names into lowercase table names.
			$this->dbobj->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);

			// Set Oracle session variables to use standard date formats.
			$this->Query("SET", "NLS_DATE_FORMAT='YYYY-MM-DD'");
			$this->Query("SET", "NLS_TIMESTAMP_FORMAT='YYYY-MM-DD HH24:MI:SS'");

			// Set Unicode support.
			// TODO: Figure out unicode support for Oracle
			//$this->Query("SET", "NLS_LANGUAGE='UTF8'");
		}

		public function GetVersion()
		{
			$tableExists = $this->TableExists("SSO_USER");

			return $this->GetOne("SELECT",array(
				"banner",
				"FROM" => "v\$version",
				"WHERE" => "banner LIKE 'Oracle%'"
			));
		}

		public function GetInsertID($name = null)
		{
			return $this->lastid;
		}

		public function TableExists($name)
		{
			return ($this->GetOne("SHOW TABLES", array("LIKE" => $name)) === false ? false : true);
		}

		public function QuoteIdentifier($str)
		{
			return "" . str_replace(array("\"", "?"), array("\"\"", ""), $str) . "";
		}

		// This function is used to get the last inserted sequence value by table name.
		//
		// Uses automatically geneerated sequences as part of the Oracle 12c IDENTITY
		// column.  This is not available in 11g and older Oracle databases.
		// See the ProcessColumnDefinition() function for more detail.
		private function GetOracleInsertID($tableName)
		{
			// Query the "all_tab_columns" for the oracle IDENTITY column and identify the sequence
			$seqName = $this->GetOne("SELECT", array(
				"data_default",
				"FROM" => "all_tab_columns",
				"WHERE" => "identity_column = 'YES' AND table_name = ?"
			), strtoupper($tableName));

			// The previous query returned "nextval" with the sequence name,
			// however we need the current sequence value
			$seqName = str_replace(".nextval", ".CURRVAL", $seqName);

			// This grabs the current value from the sequence identified above
			$retVal = $this->GetOne("SELECT", array(
				$seqName,
				"FROM" => "DUAL"
			));

			// Return the current sequence value
			return $retVal;
		}

		protected function GenerateSQL(&$master, &$sql, &$opts, $cmd, $queryinfo, $args, $subquery)
		{
			$mystr = "test";
			switch ($cmd)
			{
				case "SELECT":
				{
					$supported = array(
						"PRECOLUMN" => array("DISTINCT" => "bool", "SUBQUERIES" => true),
						"FROM" => array("SUBQUERIES" => true),
						"WHERE" => array("SUBQUERIES" => true),
						"GROUP BY" => true,
						"HAVING" => true,
						"ORDER BY" => true,
						// Haven't figured out the LIMIT problem yet
						// TODO: Figure out how to use Oracle's ROWNUM where clause functionalitty
						// instead of the LIMIT function
						//"LIMIT" => " OFFSET "
					);

					// Oracle does not support aliasing table names in the FROM clause.
					// However, alias' are supported in COLUMN names.
					// AS is used in the Oracle FROM clause to process nested queries,
					// but does not support alias'.
					$queryinfo["FROM"] = str_replace("? AS ", "? ", $queryinfo["FROM"]);

					return $this->ProcessSELECT($master, $sql, $opts, $queryinfo, $args, $subquery, $supported);
				}
				case "INSERT":
				{
					$supported = array(
						"PREINTO" => array(),
						"POSTVALUES" => array("RETURNING" => "key_identifier"),
						"SELECT" => true,
					);

					$result = $this->ProcessINSERT($master, $sql, $opts, $queryinfo, $args, $subquery, $supported);
					if ($result["success"] && isset($queryinfo["AUTO INCREMENT"]))  $result["filter_opts"] = array("mode" => "INSERT", "queryinfo" => $queryinfo);

					return $result;
				}
				case "UPDATE":
				{
					// No ORDER BY or LIMIT support.
					$supported = array(
						"PRETABLE" => array("ONLY" => "bool"),
						"WHERE" => array("SUBQUERIES" => true)
					);

					return $this->ProcessUPDATE($master, $sql, $opts, $queryinfo, $args, $subquery, $supported);
				}
				case "DELETE":
				{
					// No ORDER BY or LIMIT support.
					$supported = array(
						"PREFROM" => array("ONLY" => "bool"),
						"WHERE" => array("SUBQUERIES" => true)
					);

					return $this->ProcessDELETE($master, $sql, $opts, $queryinfo, $args, $subquery, $supported);
				}
				case "SET":
				{
					$sql = "ALTER SESSION SET " . $queryinfo;

					return array("success" => true);
				}

				case "USE":
				{
					// Fake multiple databases with Oracle schemas.
					// SCHEMA is already selected with user
					// $sql = "SELECT 1 FROM DUAL";

					return array("success" => false, "errorcode" => "skip_sql_query");
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

		protected function RunStatementFilter(&$stmt, &$filteropts)
		{
			if ($filteropts["mode"] == "INSERT")
			{
				// Force the last ID value to be extracted for INSERT queries.
				// Unable to find a way to get Oracle to return a row without
				// Using PL/SQL functions.
				$result = new CSDB_PDO_Statement($this, $stmt, $filteropts);
				$row = $result->NextRow();

				$stmt = false;
			}

			parent::RunStatementFilter($stmt, $filteropts);
		}

		public function RunRowFilter(&$row, &$filteropts, &$fetchnext)
		{
			switch ($filteropts["mode"])
			{
				case "INSERT":
				{
					// Use the private function provided above to get the Last Inserted ID
					$this->lastid = $this->GetOracleInsertID($filteropts["queryinfo"][0]);

					break;
				}
			}

			if (!$fetchnext)  parent::RunRowFilter($row, $filteropts, $fetchnext);
		}
	}
?>