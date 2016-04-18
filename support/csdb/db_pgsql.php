<?php
	// CubicleSoft PostgreSQL database interface.
	// (C) 2015 CubicleSoft.  All Rights Reserved.

	if (!class_exists("CSDB"))  exit();

	class CSDB_pgsql extends CSDB
	{
		protected $lastid;

		public function IsAvailable()
		{
			return (class_exists("PDO") && in_array("pgsql", PDO::getAvailableDrivers()) ? "pgsql" : false);
		}

		public function GetDisplayName()
		{
			return CSDB::DB_Translate("PostgreSQL (via PDO)");
		}

		public function Connect($dsn, $username = false, $password = false, $options = array())
		{
			$this->lastid = 0;

			parent::Connect($dsn, $username, $password, $options);

			// Set Unicode support.
			$this->Query("SET", "client_encoding TO 'UTF-8'");
		}

		public function GetVersion()
		{
			return $this->GetOne("SELECT", array("VERSION()"));
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
						"BULKINSERT" => true
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
				case "CREATE DATABASE":
				{
					$master = true;

					$sql = "CREATE SCHEMA " . $this->QuoteIdentifier($queryinfo[0]);

					return array("success" => true);
				}
				case "DROP DATABASE":
				{
					$master = true;

					$sql = "DROP SCHEMA " . $this->QuoteIdentifier($queryinfo[0]);

					return array("success" => true);
				}
				case "CREATE TABLE":
				{
					// UTF-8 support has to be declared at the database (not schema or table) level.
					// CREATE DATABASE whatever WITH ENCODING 'UTF8';
					// See also:  http://stackoverflow.com/questions/9961795/
					$supported = array(
						"TEMPORARY" => "CREATE TEMPORARY TABLE",
						"AS_SELECT" => true,
						"PRE_AS" => array(),
						"PROCESSKEYS" => true,
						"POSTCREATE" => array()
					);

					$result = $this->ProcessCREATE_TABLE($master, $sql, $opts, $queryinfo, $args, $subquery, $supported);
					if (!$result["success"])  return $result;

					// Handle named keys and fulltext searches.
					$sql = array($sql);
					$opts = array($opts);
					if (isset($queryinfo[2]) && is_array($queryinfo[2]))
					{
						foreach ($queryinfo[2] as $info)
						{
							if (isset($info["NAME"]))
							{
								if (strtoupper($info[0]) == "KEY" || strtoupper($info[0]) == "FULLTEXT")
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
				case "DROP COLUMN":
				{
					$master = true;

					$sql = "ALTER TABLE " . $this->QuoteIdentifier($queryinfo[0]) . " DROP COLUMN " . $this->QuoteIdentifier($queryinfo[1]);

					return array("success" => true);
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

					$sql .= $this->QuoteIdentifier($keyinfo["NAME"]) . " ON " . $this->QuoteIdentifier($queryinfo[0]) . ($type == "FULLTEXT" ? " USING GIN" : "") . " (" . implode(", ", $keyinfo[1]) . ")";

					return array("success" => true);
				}
				case "DROP INDEX":
				{
					$master = true;

					if (!isset($queryinfo[2]))  return array("success" => false, "errorcode" => "skip_sql_query");

					$sql = "DROP INDEX " . $this->QuoteIdentifier($queryinfo[2]);

					return array("success" => true);
				}
				case "TRUNCATE TABLE":
				{
					$master = true;

					$sql = "TRUNCATE TABLE " . $this->QuoteIdentifier($queryinfo[0]);

					return array("success" => true);
				}
				case "DROP TABLE":
				{
					$master = true;

					$sql = "DROP TABLE " . $this->QuoteIdentifier($queryinfo[0]);
					if (isset($queryinfo["RESTRICT"]) && $queryinfo["RESTRICT"])  $sql .= " RESTRICT";
					else if (isset($queryinfo["CASCADE"]) && $queryinfo["CASCADE"])  $sql .= " CASCADE";

					return array("success" => true);
				}
				case "SHOW DATABASES":
				{
					// Not a perfect query.  Any schema starting with 'pg_' will be skipped.
					$sql = "SELECT schema_name AS name FROM information_schema.schemata WHERE schema_name NOT LIKE 'pg_%' AND schema_name <> 'information_schema'";
					if (isset($queryinfo[0]) && $queryinfo[0] == "")  unset($queryinfo[0]);

					return array("success" => true, "filter_opts" => array("mode" => "SHOW DATABASES", "queryinfo" => $queryinfo));
				}
				case "SHOW TABLES":
				{
					$sql = "SELECT " . (isset($queryinfo["FULL"]) && $queryinfo["FULL"] ? "*" : "table_name AS name") . " FROM information_schema.tables WHERE (table_schema = ? OR table_type = 'LOCAL TEMPORARY')" . (isset($queryinfo["LIKE"]) ? " AND table_name LIKE ?" : "") . " ORDER BY table_name";
					$opts[] = (isset($queryinfo["FROM"]) ? $queryinfo["FROM"] : ($this->currdb !== false ? $this->currdb : "public"));
					if (isset($queryinfo[0]) && $queryinfo[0] == "")  unset($queryinfo[0]);
					if (isset($queryinfo["LIKE"]))  $opts[] = $queryinfo["LIKE"];

					return array("success" => true, "filter_opts" => array("mode" => "SHOW TABLES", "queryinfo" => $queryinfo));
				}
				case "SHOW CREATE DATABASE":
				{
					// Not a perfect query.  Any schema starting with 'pg_' will be skipped.
					$sql = "SELECT schema_name AS name FROM information_schema.schemata WHERE schema_name NOT LIKE 'pg_%' AND schema_name <> 'information_schema' AND schema_name = ?";
					$opts[] = $queryinfo[0];

					return array("success" => true, "filter_opts" => array("mode" => "SHOW CREATE DATABASE"));
				}
				case "SHOW CREATE TABLE":
				{
					$sql = "SELECT table_schema, table_name, table_type FROM information_schema.tables WHERE (table_schema = ? OR table_type = 'LOCAL TEMPORARY') AND table_name = ?";
					$opts[] = ($this->currdb !== false ? $this->currdb : "public");
					$opts[] = $queryinfo[0];

					return array("success" => true, "filter_opts" => array("mode" => "SHOW CREATE TABLE", "hints" => (isset($queryinfo["EXPORT HINTS"]) ? $queryinfo["EXPORT HINTS"] : array())));
				}
				case "BULK IMPORT MODE":
				{
					$master = true;

					if ($queryinfo)  $sql = "SET synchronous_commit TO OFF";
					else  $sql = "SET synchronous_commit TO DEFAULT";

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
				case "SHOW CREATE DATABASE":
				{
					if ($row !== false)
					{
						$row->cmd = "CREATE DATABASE";
						$row->opts = array($row->name);
					}

					break;
				}
				case "SHOW CREATE TABLE":
				{
					if ($row !== false)
					{
						$cols = array();
						$result2 = $this->Query("SELECT", array(
							"*",
							"FROM" => "information_schema.columns",
							"WHERE" => "table_schema = ? AND table_name = ?",
							"ORDER BY" => "ordinal_position"
						), $row->table_schema, $row->table_name);
						while ($row2 = $result2->NextRow())
						{
							if (isset($filteropts["hints"]) && isset($filteropts["hints"][$row2->column_name]))  $col = $filteropts["hints"][$row2->column_name];
							else
							{
								$row2->data_type = str_replace(array(" with timezone", "without timezone", "[]"), "", $row2->data_type);

								switch ($row2->data_type)
								{
									case "integer":  $col = array("INTEGER", 4);  break;
									case "bigint":  $col = array("INTEGER", 8);  break;
									case "smallint":  $col = array("INTEGER", 2);  break;
									case "real":  $col = array("FLOAT", 4);  break;
									case "double precision":  $col = array("FLOAT", 8);  break;
									case "numeric":  $col = array("DECIMAL", $row2->numeric_precision, $row2->numeric_scale);  break;
									case "\"char\"":  $col = array("STRING", 1, $row2->character_maximum_length, "FIXED" => true);  break;
									case "character varying":  $col = array("STRING", 1, $row2->character_maximum_length);  break;
									case "text":  $col = array("STRING", 4);  break;
									case "bytea":  $col = array("BINARY", 4);  break;
									case "date":  $col = array("DATE");  break;
									case "time":  $col = array("TIME");  break;
									case "timestamp":  $col = array("DATETIME");  break;
									case "boolean":  $col = array("BOOLEAN");  break;

									default:  return;
								}

								if ($row2->is_nullable == "NO")  $col["NOT NULL"] = true;
								if (isset($row2->column_default))
								{
									if (strtolower(substr($row2->column_default, 0, 8)) == "nextval(" && strtolower(substr($row2->column_default, -11)) == "::regclass)")  $col["AUTO INCREMENT"] = true;
									else  $col["DEFAULT"] = $row2->column_default;
								}
							}

							$cols[$row2->column_name] = $col;
						}

						// Process indexes.
						$lastindex = 0;
						$keys = array();
						$result2 = $this->Query("SELECT", array(
							"c.oid, c.relname, a.attname, a.attnum, i.indisprimary, i.indisunique",
							"FROM" => "pg_index AS i, pg_class AS c, pg_attribute AS a",
							"WHERE" => "i.indexrelid = c.oid AND i.indexrelid = a.attrelid AND i.indrelid = " . $this->Quote($this->QuoteIdentifier($row->table_schema) . "." . $this->QuoteIdentifier($row->table_name)) . "::regclass",
							"ORDER BY" => "c.oid, a.attnum"
						));
						while ($row2 = $result2->NextRow())
						{
							if ($lastindex != $row2->oid)
							{
								if ($lastindex > 0)  $keys[] = $key;

								// FULLTEXT index extraction is missing.  Feel free to submit a patch.
								if ($row2->indisprimary)  $type = "PRIMARY";
								else if ($row2->indisunique)  $type = "UNIQUE";
								else  $type = "KEY";

								$key = array($type, array(), "NAME" => $row2->relname);
								$lastindex = $row2->oid;
							}

							$key[1][] = $row2->attname;
						}
						if ($lastindex > 0)  $keys[] = $key;

						// Process foreign keys?  It would be nice to see some examples.

						// Generate the final CREATE TABLE information.
						$row->cmd = "CREATE TABLE";
						$row->opts = array($row->table_name, $cols);
						if (count($keys))  $row->opts[] = $keys;
						if ($row->table_type == "LOCAL TEMPORARY")  $row->opts["TEMPORARY"] = true;
					}

					break;
				}
			}

			if (!$fetchnext)  parent::RunRowFilter($row, $filteropts, $fetchnext);
		}

		protected function ProcessColumnDefinition($info)
		{
			$sql = "";
			$type = strtoupper($info[0]);
			switch ($type)
			{
				case "INTEGER":
				{
					$varbytes = (isset($info[1]) && (int)$info[1] > 0 ? (int)$info[1] : 4);
					if (isset($info["AUTO INCREMENT"]) && $info["AUTO INCREMENT"])
					{
						if ($varbytes < 4)  $sql .= " SERIAL";
						else  $sql .= " BIGSERIAL";
					}
					else
					{
						if ($varbytes < 3)  $sql .= " SMALLINT";
						else if ($varbytes < 5)  $sql .= " INTEGER";
						else  $sql .= " BIGINT";

						// No UNSIGNED support in PostgreSQL.
					}

					break;
				}
				case "FLOAT":
				{
					$varbytes = (isset($info[1]) ? (int)$info[1] : 4);
					if ($varbytes <= 4)  $sql .= " REAL";
					else  $sql .= " DOUBLE PRECISION";

					break;
				}
				case "DECIMAL":
				{
					$sql .= " NUMERIC";
					if (isset($info[1]))  $sql .= "(" . $info[1] . (isset($info[2]) ? ", " . $info[2] : "") . ")";

					break;
				}
				case "STRING":
				{
					$varbytes = $info[1];
					if ($varbytes == 1)
					{
						if (isset($info["FIXED"]) && $info["FIXED"])  $sql .= " CHAR(" . $info[2] . ")";
						else  $sql .= " VARCHAR(" . $info[2] . ")";
					}
					else
					{
						$sql .= " TEXT";
					}

					break;
				}
				case "BINARY":  $sql .= " BYTEA";  break;
				case "DATE":  $sql .= " DATE";  break;
				case "TIME":  $sql .= " TIME";  break;
				case "DATETIME":  $sql .= " TIMESTAMP";  break;
				case "BOOLEAN":  $sql .= " BOOLEAN";  break;
				default:  return array("success" => false, "error" => CSDB::DB_Translate("Unknown column type '%s'.", $type), "errorcode" => "unknown_column_type");
			}

			if (isset($info["NOT NULL"]) && $info["NOT NULL"])  $sql .= " NOT NULL";
			if (isset($info["DEFAULT"]))  $sql .= " DEFAULT " . $this->Quote($info["DEFAULT"]);
			if (isset($info["PRIMARY KEY"]) && $info["PRIMARY KEY"])  $sql .= " PRIMARY KEY";
			if (isset($info["UNIQUE KEY"]) && $info["UNIQUE KEY"])  $sql .= " UNIQUE";
			if (isset($info["REFERENCES"]))  $sql .= " REFERENCES " . $this->ProcessReferenceDefinition($info["REFERENCES"]);

			return array("success" => true, "sql" => $sql);
		}

		protected function ProcessKeyDefinition($info)
		{
			$sql = "";
			$type = strtoupper($info[0]);
			foreach ($info[1] as $num => $field)  $info[1][$num] = $this->QuoteIdentifier($field);
			switch ($type)
			{
				case "PRIMARY":
				{
					if (isset($info["CONSTRAINT"]))  $sql .= "CONSTRAINT " . $info["CONSTRAINT"] . " ";
					$sql .= "PRIMARY KEY";
					$sql .= " (" . implode(", ", $info[1]) . ")";
					if (isset($info["OPTION"]))  $sql .= " " . $info["OPTION"];

					break;
				}
				case "KEY":
				{
					// PostgreSQL CREATE TABLE doesn't support regular KEY indexes, but ADD INDEX does.

					break;
				}
				case "UNIQUE":
				{
					if (isset($info["CONSTRAINT"]))  $sql .= "CONSTRAINT " . $info["CONSTRAINT"] . " ";
					$sql .= "UNIQUE";
					$sql .= " (" . implode(", ", $info[1]) . ")";
					if (isset($info["OPTION"]))  $sql .= " " . $info["OPTION"];

					break;
				}
				case "FULLTEXT":
				{
					// PostgreSQL CREATE TABLE doesn't support regular FULLTEXT indexes, but ADD INDEX does.

					break;
				}
				case "FOREIGN":
				{
					if (isset($info["CONSTRAINT"]))  $sql .= "CONSTRAINT " . $info["CONSTRAINT"] . " ";
					$sql .= "FOREIGN KEY";
					$sql .= " (" . implode(", ", $info[1]) . ")";
					$sql .= " REFERENCES " . $this->ProcessReferenceDefinition($info[2]);

					break;
				}
				default:  return array("success" => false, "error" => CSDB::DB_Translate("Unknown key type '%s'.", $type), "errorcode" => "unknown_key_type");;
			}

			return array("success" => true, "sql" => $sql);
		}
	}
?>