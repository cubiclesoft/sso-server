<?php
	// CubicleSoft PostgreSQL database interface.
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	if (!class_exists("CSDB"))  exit();

	class CSDB_pgsql_lite extends CSDB
	{
		protected $lastid;

		public function IsAvailable()
		{
			return (class_exists("PDO") && in_array("pgsql", PDO::getAvailableDrivers()) ? "pgsql" : false);
		}

		public function GetDisplayName()
		{
			return CSDB::DB_Translate("PostgreSQL (via PDO, Lite version)");
		}

		public function Connect($dsn, $username = false, $password = false, $options = array())
		{
			$this->lastid = 0;

			parent::Connect($dsn, $username, $password, $options);

			// Set Unicode support.
			$this->Query("SET", "client_encoding TO 'UTF-8'");
		}

		public function GetInsertID($name = null)
		{
			return $this->lastid;
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
						"PRECOLUMN" => array("DISTINCT" => "bool", "SUBQUERIES" => true),
						"FROM" => array("SUBQUERIES" => true),
						"WHERE" => array("SUBQUERIES" => true),
						"GROUP BY" => true,
						"HAVING" => true,
						"ORDER BY" => true,
						"LIMIT" => " OFFSET "
					);

					return $this->ProcessSELECT($master, $sql, $opts, $queryinfo, $args, $subquery, $supported);
				}
				case "INSERT":
				{
					$supported = array(
						"PREINTO" => array(),
						"POSTVALUES" => array("RETURNING" => "key_identifier"),
						"SELECT" => true,
					);

					// To get the last insert ID via GetInsertID(), the field that contains a 'serial' (auto increment) field must be specified.
					if (isset($queryinfo["AUTO INCREMENT"]))  $queryinfo["RETURNING"] = $queryinfo["AUTO INCREMENT"];

					$this->lastid = 0;
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
					$sql = "SET " . $queryinfo;

					return array("success" => true);
				}
				case "USE":
				{
					// Fake multiple databases with PostgreSQL schemas.
					// http://www.postgresql.org/docs/7.3/static/ddl-schemas.html
					$sql = "SET search_path TO " . ($queryinfo != "" ? $this->QuoteIdentifier($queryinfo) . "," : "") . "\"\$user\",public";

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

		protected function RunStatementFilter(&$stmt, &$filteropts)
		{
			if ($filteropts["mode"] == "INSERT")
			{
				// Force the last ID value to be extracted for INSERT queries.
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
					if ($row !== false)
					{
						$key = $filteropts["queryinfo"]["AUTO INCREMENT"];
						$this->lastid = $row->$key;
					}

					break;
				}
			}

			if (!$fetchnext)  parent::RunRowFilter($row, $filteropts, $fetchnext);
		}
	}
?>