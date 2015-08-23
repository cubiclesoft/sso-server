<?php
	// CubicleSoft SQLite database interface.
	// (C) 2013 CubicleSoft.  All Rights Reserved.

	if (!class_exists("CSDB"))  exit();

	class CSDB_sqlite extends CSDB
	{
		protected $dbprefix;

		public function IsAvailable()
		{
			return (class_exists("PDO") && in_array("sqlite", PDO::getAvailableDrivers()) ? "sqlite" : false);
		}

		public function GetDisplayName()
		{
			return CSDB::DB_Translate("SQLite (via PDO)");
		}

		public function Connect($dsn, $username = false, $password = false, $options = array())
		{
			$this->dbprefix = "";

			parent::Connect($dsn, $username, $password, $options);
		}

		public function GetVersion()
		{
			return $this->GetOne("SELECT", array("SQLITE_VERSION()"));
		}

		public function GetInsertID($name = null)
		{
			return $this->GetOne("SELECT", array("LAST_INSERT_ROWID()"));
		}

		public function TableExists($name)
		{
			return ($this->GetOne("SHOW TABLES", array("LIKE" => (strpos($name, "__") === false ? $this->dbprefix : "") . $name)) === false ? false : true);
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
						"SELECT" => true
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
				case "CREATE DATABASE":
				{
					return array("success" => false, "errorcode" => "skip_sql_query");
				}
				case "USE":
				{
					$this->dbprefix = $this->GetDBPrefix($queryinfo);

					return array("success" => false, "errorcode" => "skip_sql_query");
				}
				case "DROP DATABASE":
				{
					$master = true;

					$result = $this->Query("SHOW TABLES", array("FROM" => $queryinfo));
					while ($row = $result->NextRow())
					{
						$this->Query("DROP TABLE", array($row->name, "FROM" => $queryinfo));
					}

					return array("success" => false, "errorcode" => "skip_sql_query");
				}
				case "CREATE TABLE":
				{
					$supported = array(
						"DBPREFIX" => $this->dbprefix,
						"TEMPORARY" => "CREATE TEMPORARY TABLE",
						"AS_SELECT" => true,
						"PROCESSKEYS" => true,
						"POSTCREATE" => array()
					);

					$result = $this->ProcessCREATE_TABLE($master, $sql, $opts, $queryinfo, $args, $subquery, $supported);
					if (!$result["success"])  return $result;

					// Handle named keys.
					$sql = array($sql);
					$opts = array($opts);
					if (isset($queryinfo[2]) && is_array($queryinfo[2]))
					{
						foreach ($queryinfo[2] as $info)
						{
							if (strtoupper($info[0]) == "KEY" && isset($info["NAME"]))
							{
								$sql2 = "";
								$opts2 = array();
								$result = $this->GenerateSQL($master, $sql2, $opts2, "ADD INDEX", array($queryinfo[0], $info), array(), false);
								if (!$result["success"])  return $result;

								$sql[] = $sql2;
								$opts[] = $opts2;
							}
						}
					}

					return $result;
				}
				case "ADD COLUMN":
				{
					$master = true;

					$result = $this->ProcessColumnDefinition($queryinfo[2]);
					if (!$result["success"])  return $result;

					$sql = "ALTER TABLE " . $this->QuoteIdentifier($queryinfo[0]) . " ADD COLUMN " . $this->QuoteIdentifier($queryinfo[1]) . " " . $result["sql"];

					return array("success" => true);
				}
				case "PRAGMA":
				{
					// PRAGMA support exists solely for SQLite DROP COLUMN.
					$master = true;

					$sql = "PRAGMA " . $queryinfo;

					return array("success" => true);
				}
				case "DROP COLUMN":
				{
					// Has to be emulated by creating a temporary table without the column.
					// Triggers, views, and foreign keys will probably be lost.  If you care, then submit a patch.
					$master = true;

					$row = $this->GetRow("SHOW CREATE TABLE", array($queryinfo[0]));
					if (!$row)  return array("success" => false, "error" => CSDB::DB_Translate("An error occurred while getting existing table information."), "errorcode" => "show_create_table_error");

					if (!isset($row->opts[1][$queryinfo[1]]))  return array("success" => false, "errorcode" => "skip_sql_query");

					unset($row->opts[1][$queryinfo[1]]);

					if (!count($row->opts[1]))  return array("success" => false, "error" => CSDB::DB_Translate("Table contains only one column."), "errorcode" => "drop_column_error");

					$opts2 = $row->opts;
					$opts2[0] = "csdb__dropcol";
					$opts2["TEMPORARY"] = true;

					$cols = array_keys($opts2[1]);
					$this->Query("CREATE TABLE", $opts2);
					$this->Query("INSERT", array("csdb__dropcol", $cols, "SELECT" => array(array($cols, "?"), $queryinfo[0])));
					$this->Query("DROP TABLE", array($queryinfo[0]));
					$this->Query("CREATE TABLE", $row->opts);
					$this->Query("INSERT", array($queryinfo[0], $cols, "SELECT" => array(array($cols, "?"), "csdb__dropcol")));
					$this->Query("DROP TABLE", array("csdb__dropcol"));

					return array("success" => false, "errorcode" => "skip_sql_query");
				}
				case "ADD INDEX":
				{
					$master = true;

					$keyinfo = $queryinfo[1];
					$type = strtoupper($keyinfo[0]);
					foreach ($keyinfo[1] as $num => $field)  $keyinfo[1][$num] = $this->QuoteIdentifier($field);

					if (!isset($keyinfo["NAME"]))  return array("success" => false, "errorcode" => "skip_sql_query");

					if ($type == "PRIMARY" || $type == "UNIQUE")  $sql = "CREATE UNIQUE INDEX ";
					else if ($type == "FOREIGN")  return array("success" => false, "errorcode" => "skip_sql_query");
					else  $sql = "CREATE INDEX ";

					$sql .= $this->QuoteIdentifier($this->dbprefix . $queryinfo[0] . "__" . $keyinfo["NAME"]) . " ON " . $this->QuoteIdentifier($this->dbprefix . $queryinfo[0]) . " (" . implode(", ", $keyinfo[1]) . ")";

					return array("success" => true);
				}
				case "DROP INDEX":
				{
					$master = true;

					if (!isset($queryinfo[2]))  return array("success" => false, "errorcode" => "skip_sql_query");

					$sql = "DROP INDEX " . $this->QuoteIdentifier($this->dbprefix . $queryinfo[2]);

					return array("success" => true);
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
				case "DROP TABLE":
				{
					$master = true;

					$dbprefix = (isset($queryinfo["FROM"]) ? $this->GetDBPrefix($queryinfo["FROM"]) : $this->dbprefix);
					$sql = "DROP TABLE " . $this->QuoteIdentifier($dbprefix . $queryinfo[0]);

					return array("success" => true);
				}
				case "SHOW DATABASES":
				{
					// Swiped from:  http://www.sqlite.org/faq.html#q7
					$sql = "SELECT name FROM (SELECT * FROM sqlite_master  UNION ALL  SELECT * FROM sqlite_temp_master) WHERE type = 'table' ORDER BY name";
					if (isset($queryinfo[0]))  $queryinfo[0] = $this->GetDBPrefix($queryinfo[0]);

					return array("success" => true, "filter_opts" => array("mode" => "SHOW DATABASES", "queryinfo" => $queryinfo, "names" => array()));
				}
				case "SHOW TABLES":
				{
					// Swiped from:  http://www.sqlite.org/faq.html#q7
					$sql = "SELECT " . (isset($queryinfo["FULL"]) && $queryinfo["FULL"] ? "*" : "name") . " FROM (SELECT *, 'normal' AS tbl_type FROM sqlite_master  UNION ALL  SELECT *, 'temp' AS tbl_type FROM sqlite_temp_master) WHERE type = 'table'" . (isset($queryinfo["LIKE"]) ? " AND name LIKE ?" : "") . " ORDER BY name";
					if (isset($queryinfo[0]) && $queryinfo[0] == "")  unset($queryinfo[0]);
					if (isset($queryinfo["LIKE"]))  $opts[] = $queryinfo["LIKE"];

					return array("success" => true, "filter_opts" => array("mode" => "SHOW TABLES", "queryinfo" => $queryinfo));
				}
				case "SHOW CREATE DATABASE":
				{
					$sql = "SELECT name FROM (SELECT * FROM sqlite_master  UNION ALL  SELECT * FROM sqlite_temp_master) WHERE type = 'table' ORDER BY name";
					$queryinfo[0] = (string)@substr((isset($queryinfo[0]) ? $this->GetDBPrefix($queryinfo[0]) : $this->dbprefix), 0, -2);

					return array("success" => true, "filter_opts" => array("mode" => "SHOW CREATE DATABASE", "output" => false, "queryinfo" => $queryinfo));
				}
				case "SHOW CREATE TABLE":
				{
					$sql = "SELECT tbl_type, sql FROM (SELECT *, 'normal' AS tbl_type FROM sqlite_master  UNION ALL  SELECT *, 'temp' AS tbl_type FROM sqlite_temp_master) WHERE type = 'table' AND name = ?";

					$opts = array($this->dbprefix . $queryinfo[0]);

					return array("success" => true, "filter_opts" => array("mode" => "SHOW CREATE TABLE", "output" => false, "queryinfo" => $queryinfo, "hints" => (isset($queryinfo["EXPORT HINTS"]) ? $queryinfo["EXPORT HINTS"] : array())));
				}
			}

			return array("success" => false, "error" => CSDB::DB_Translate("Unknown query command '%s'.", $cmd), "errorcode" => "unknown_query_command");
		}

		public function RunRowFilter(&$row, &$filteropts, &$fetchnext)
		{
			switch ($filteropts["mode"])
			{
				case "SHOW DATABASES":
				{
					if ($row !== false)
					{
						$name = $row->name;
						$pos = strpos($name, "__");
						if ($pos === false)  $name = "";
						else  $name = substr($name, 0, $pos);

						if (isset($filteropts["names"][$name]))  $fetchnext = true;
						else
						{
							$filteropts["names"][$name] = true;
							$row->name = $name;
						}
					}

					break;
				}
				case "SHOW TABLES":
				{
					if ($row !== false)
					{
						$dbprefix = (isset($filteropts["queryinfo"]["FROM"]) ? $this->GetDBPrefix($filteropts["queryinfo"]["FROM"]) : $this->dbprefix);
						$name = $row->name;

						if ($dbprefix == "" && strpos($name, "__") !== false)  $fetchnext = true;
						else if ($dbprefix != "" && strtolower(substr($name, 0, strlen($dbprefix))) !== strtolower($dbprefix))  $fetchnext = true;
						else
						{
							$row->name = substr($name, strlen($dbprefix));
							$row->tbl_name = $row->name;
						}
					}

					break;
				}
				case "SHOW CREATE DATABASE":
				{
					if ($row !== false)
					{
						$name = $row->name;
						$pos = strpos($name, "__");
						if ($pos === false)  $name = "";
						else  $name = substr($name, 0, $pos);

						if ($filteropts["output"] || $name == "" || $name !== $filteropts["queryinfo"][0])  $fetchnext = true;
						else
						{
							$row->name = $name;
							$row->cmd = "CREATE DATABASE";
							$row->opts = array($name);

							$filteropts["output"] = true;
						}
					}

					break;
				}
				case "SHOW CREATE TABLE":
				{
					if ($row !== false)
					{
						if ($filteropts["output"])  $fetchnext = true;
						else
						{
							$dbprefix = ($row->tbl_type == "temp" ? "temp." : "");

							// Process columns according to:  http://sqlite.org/datatype3.html
							$cols = array();
							$result2 = $this->Query("PRAGMA", $dbprefix . "table_info(" . $this->QuoteIdentifier($this->dbprefix . $filteropts["queryinfo"][0]) . ")");
							while ($row2 = $result2->NextRow())
							{
								if (isset($filteropts["hints"]) && isset($filteropts["hints"][$row2->name]))  $col = $filteropts["hints"][$row2->name];
								else
								{
									if (stripos($row2->type, "INT") !== false)  $col = array("INTEGER", 8);
									else if (stripos($row2->type, "CHAR") !== false || stripos($row2->type, "CLOB") !== false || stripos($row2->type, "TEXT") !== false)
									{
										$col = array("STRING", 4);

										$pos = strpos($row2->type, "(");
										if ($pos !== false)
										{
											$pos2 = strpos($row2->type, ")", $pos);
											if ($pos2 !== false)
											{
												$num = (int)substr($row2->type, $pos + 1, $pos2 - $pos - 1);
												if ($num > 0 && $num < 256)  $col = array("STRING", 1, $num);
											}
										}
									}
									else if (stripos($row2->type, "BLOB") !== false || $row2->type === "")  $col = array("BINARY", 4);
									else if (stripos($row2->type, "REAL") !== false || stripos($row2->type, "FLOA") !== false || stripos($row2->type, "DOUB") !== false)  $col = array("FLOAT", 8);
									else
									{
										$col = array("NUMERIC");
										$pos = strpos($row2->type, "(");
										if ($pos !== false)
										{
											$pos2 = strpos($row2->type, ")", $pos);
											if ($pos2 !== false)
											{
												$nums = explode(",", substr($row2->type, $pos + 1, $pos2 - $pos - 1));
												$num = (int)trim($nums[0]);
												if ($num > 0)  $col[] = $num;
												if (count($nums) > 1)
												{
													$num = (int)trim($nums[1]);
													if ($num > 0)  $col[] = $num;
												}
											}
										}
									}

									if ($row2->notnull > 0)  $col["NOT NULL"] = true;
									if (isset($row2->dflt_value))  $col["DEFAULT"] = $row2->dflt_value;
									if ($row2->pk > 0)
									{
										$col["PRIMARY KEY"] = true;
										if ($col[0] == "INTEGER")  $col["AUTO INCREMENT"] = true;
									}
								}

								$cols[$row2->name] = $col;
							}

							// Process indexes.
							$keys = array();
							$result2 = $this->Query("PRAGMA", $dbprefix . "index_list(" . $this->QuoteIdentifier($this->dbprefix . $filteropts["queryinfo"][0]) . ")");
							while ($row2 = $result2->NextRow())
							{
								$cols2 = array();
								$result3 = $this->Query("PRAGMA", $dbprefix . "index_info(" . $this->QuoteIdentifier($row2->name) . ")");
								while ($row3 = $result3->NextRow())
								{
									$cols2[] = $row3->name;
								}

								if (substr($row2->name, 0, strlen($this->dbprefix . $filteropts["queryinfo"][0]) + 2) == $this->dbprefix . $filteropts["queryinfo"][0] . "__")  $row2->name = substr($row2->name, strlen($this->dbprefix . $filteropts["queryinfo"][0]) + 2);

								$keys[] = array(($row2->unique > 0 ? "UNIQUE" : "KEY"), $cols2, "NAME" => $row2->name);
							}

							// Process foreign keys?  It would be nice to see some output of foreign_key_list().

							$row->cmd = "CREATE TABLE";
							$row->opts = array($filteropts["queryinfo"][0], $cols);
							if (count($keys))  $row->opts[] = $keys;
							if ($row->tbl_type == "temp")  $row->opts["TEMPORARY"] = true;
							$row->opts["CHARACTER SET"] = "utf8";

							$filteropts["output"] = true;
						}
					}

					break;
				}
			}

			if (!$fetchnext)  parent::RunRowFilter($row, $filteropts, $fetchnext);
		}

		protected function ProcessColumnDefinition($info)
		{
			// SQLite is seriously limited here with just four data types.
			$sql = "";
			$type = strtoupper($info[0]);
			switch ($type)
			{
				case "INTEGER":  $sql .= " INTEGER";  break;
				case "FLOAT":  $sql .= " REAL";  break;
				case "DECIMAL":
				{
					$sql .= " NUMERIC";
					if (isset($info[1]))  $sql .= "(" . $info[1] . (isset($info[2]) ? ", " . $info[2] : "") . ")";

					break;
				}
				case "STRING":
				{
					$varbytes = $info[1];
					if ($varbytes == 1)  $sql .= " TEXT(" . $info[2] . ")";
					else  $sql .= " TEXT";

					break;
				}
				case "BINARY":  $sql .= " BLOB";  break;
				case "DATE":  $sql .= " TEXT";  break;
				case "TIME":  $sql .= " TEXT";  break;
				case "DATETIME":  $sql .= " TEXT";  break;
				case "BOOLEAN":  $sql .= " INTEGER";  break;
				default:  return array("success" => false, "error" => CSDB::DB_Translate("Unknown column type '%s'.", $type), "errorcode" => "unknown_column_type");
			}

			if (isset($info["NOT NULL"]) && $info["NOT NULL"])  $sql .= " NOT NULL";
			if (isset($info["DEFAULT"]))  $sql .= " DEFAULT " . $this->Quote($info["DEFAULT"]);
			if (isset($info["PRIMARY KEY"]) && $info["PRIMARY KEY"])
			{
				$sql .= " PRIMARY KEY";
				if (isset($info["AUTO INCREMENT"]) && $info["AUTO INCREMENT"])  $sql .= " AUTOINCREMENT";
			}
			if (isset($info["UNIQUE KEY"]) && $info["UNIQUE KEY"])  $sql .= " UNIQUE";
			if (isset($info["REFERENCES"]))  $sql .= " REFERENCES " . $this->ProcessReferenceDefinition($info["REFERENCES"]);

			return array("success" => true, "sql" => $sql);
		}

		protected function ProcessKeyDefinition($info)
		{
			$sql = "";
			if (isset($info["CONSTRAINT"]))  $sql .= "CONSTRAINT " . $info["CONSTRAINT"] . " ";
			$type = strtoupper($info[0]);
			foreach ($info[1] as $num => $field)  $info[1][$num] = $this->QuoteIdentifier($field);
			switch ($type)
			{
				case "PRIMARY":
				{
					$sql .= "PRIMARY KEY";
					$sql .= " (" . implode(", ", $info[1]) . ")";

					break;
				}
				case "KEY":
				{
					// SQLite CREATE TABLE doesn't support regular KEY indexes, but ALTER TABLE does.

					break;
				}
				case "UNIQUE":
				{
					$sql .= "UNIQUE";
					$sql .= " (" . implode(", ", $info[1]) . ")";

					break;
				}
				case "FULLTEXT":
				{
					// SQLite doesn't support FULLTEXT indexes.
					break;
				}
				case "FOREIGN":
				{
					$sql .= "FOREIGN KEY";
					$sql .= " (" . implode(", ", $info[1]) . ")";
					$sql .= " REFERENCES " . $this->ProcessReferenceDefinition($info[2]);

					break;
				}
				default:  return array("success" => false, "error" => CSDB::DB_Translate("Unknown key type '%s'.", $type), "errorcode" => "unknown_key_type");;
			}

			return array("success" => true, "sql" => $sql);
		}

		private function GetDBPrefix($str)
		{
			$str = preg_replace('/\s+/', "_", trim(str_replace("_", " ", $str)));

			return ($str != "" ? $str . "__" : "");
		}
	}
?>