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

	class CSDB_oci extends CSDB
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

// To use CLOBs, try:  PDO::ATTR_STRINGIFY_FETCHES => true
// Source:  https://github.com/yiisoft/yii2/issues/3167

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
			return str_replace(array("\"", "?"), array("\"\"", ""), $str);
		}

		// This function is used to get the last inserted sequence value by table name.
		//
		// Uses automatically geneerated sequences as part of the Oracle 12c IDENTITY
		// column.  This is not available in 11g and older Oracle databases.
		// See the ProcessColumnDefinition() function for more detail.
		private function GetOracleInsertID($tableName)
		{
			// Query the "all_tab_columns" for the oracle IDENTITY column and identify the sequence.
			$seqName = $this->GetOne("SELECT", array(
				"data_default",
				"FROM" => "all_tab_columns",
				"WHERE" => "identity_column = 'YES' AND table_name = ?"
			), strtoupper($tableName));

			// The previous query returned "nextval" with the sequence name.
			// However, we need the current sequence value.
			$seqName = str_replace(".nextval", ".CURRVAL", $seqName);

			// This grabs the current value from the sequence identified above.
			$retVal = $this->GetOne("SELECT", array(
				$seqName,
				"FROM" => "DUAL"
			));

			// Return the current sequence value.
			return $retVal;
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
						// Haven't figured out the LIMIT problem yet
						// TODO: Figure out how to use Oracle's ROWNUM where clause functionalitty
						// instead of the LIMIT function
// Probably best to fake this on versions before Oracle 12c.
// Oracle 12c and later supports this:  OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY;
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
					// SCHEMA is already selected with user.
					// $sql = "SELECT 1 FROM DUAL";

					return array("success" => false, "errorcode" => "skip_sql_query");
				}
				case "CREATE DATABASE":
				{
					// Fake creating a databse which isn't needed with oracle.

					return array("success" => false, "errorcode" => "skip_sql_query");
				}
				case "DROP DATABASE":
				{
					// Fake dropping a database which isn't needed with oracle.

					return array("success" => false, "errorcode" => "skip_sql_query");
				}

				case "CREATE TABLE":
				{
// A random PostgreSQL reference...is this comment relevant to Oracle/OCI?
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
					// Not a perfect query.  Will show a list of owners (users), which in oracle are considered schemas
					$sql = "SELECT DISTINCT owner AS name FROM all_tables WHERE owner NOT IN ('MDSYS', 'SYSTEM', 'XDB', 'SYS')";
					if (isset($queryinfo[0]) && $queryinfo[0] == "")  unset($queryinfo[0]);

					return array("success" => true, "filter_opts" => array("mode" => "SHOW DATABASES", "queryinfo" => $queryinfo));
				}
				case "SHOW TABLES":
				{
					$sql = "SELECT " . (isset($queryinfo["FULL"]) && $queryinfo["FULL"] ? "*" : "table_name AS name") . " FROM all_tables" . (isset($queryinfo["LIKE"]) ? " WHERE table_name LIKE ?" : "") . " ORDER BY table_name";
					if (isset($queryinfo[0]) && $queryinfo[0] == "")  unset($queryinfo[0]);
					if (isset($queryinfo["LIKE"]))  $opts[] = $queryinfo["LIKE"];

					return array("success" => true, "filter_opts" => array("mode" => "SHOW TABLES", "queryinfo" => $queryinfo));
				}
				case "SHOW CREATE DATABASE":
				{
					// Not a perfect query.  Will show a list of owners (users), which in oracle are considered schemas
					$sql = "SELECT DISTINCT owner AS name FROM all_tables WHERE owner NOT IN ('MDSYS', 'SYSTEM', 'XDB', 'SYS') AND owner = ?";
					$opts[] = $queryinfo[0];

					return array("success" => true, "filter_opts" => array("mode" => "SHOW CREATE DATABASE"));
				}
				case "SHOW CREATE TABLE":
				{
					$sql = "SELECT DISTINCT owner AS table_schema, table_name, temporary AS table_type FROM all_tables WHERE owner = ? AND table_name = ?";
					$opts[] = ($this->currdb !== false ? $this->currdb : "public");
					$opts[] = $queryinfo[0];

					return array("success" => true, "filter_opts" => array("mode" => "SHOW CREATE TABLE", "hints" => (isset($queryinfo["EXPORT HINTS"]) ? $queryinfo["EXPORT HINTS"] : array())));
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
					/*
					TODO: Update the column types for Oracle
					if ($row !== false)
					{
						$cols = array();
						$result2 = $this->Query("SELECT", array(
							"*",
							"FROM" => "all_tab_columns",
							"WHERE" => " table_name = ?",
							"ORDER BY" => "column_id"
						), $row->table_name);
						while ($row2 = $result2->NextRow())
						{
							if (isset($filteropts["hints"]) && isset($filteropts["hints"][$row2->column_name]))  $col = $filteropts["hints"][$row2->column_name];
							else
							{
								$row2->data_type = str_replace(array(" with timezone", "without timezone", "[]"), "", $row2->data_type);

								switch (strtoupper($type))
								{
									case "BIT":
									{
										$opts2 = array("INTEGER");
										if (!count($typeopts) || $typeopts[0] <= 8)  $opts2[] = 1;
										else if ($typeopts[0] <= 16)  $opts2[] = 2;
										else if ($typeopts[0] <= 24)  $opts2[] = 3;
										else if ($typeopts[0] <= 32)  $opts2[] = 4;
										else  $opts2[] = 8;

										break;
									}
									case "TINYINT":  $opts2 = array("INTEGER", 1);  $extras = array("UNSIGNED" => "keep", "ZEROFILL" => "ignore");  break;
									case "SMALLINT":  $opts2 = array("INTEGER", 2);  $extras = array("UNSIGNED" => "keep", "ZEROFILL" => "ignore");  break;
									case "MEDIUMINT":  $opts2 = array("INTEGER", 3);  $extras = array("UNSIGNED" => "keep", "ZEROFILL" => "ignore");  break;
									case "INT":  case "INTEGER":  $opts2 = array("INTEGER", 4);  $extras = array("UNSIGNED" => "keep", "ZEROFILL" => "ignore");  break;
									case "BIGINT":  $opts2 = array("INTEGER", 8);  $extras = array("UNSIGNED" => "keep", "ZEROFILL" => "ignore");  break;

									case "DOUBLE":  case "REAL":  $opts2 = array("FLOAT", 8);  $extras = array("UNSIGNED" => "ignore", "ZEROFILL" => "ignore");  break;
									case "FLOAT":  $opts2 = array("FLOAT", 4);  $extras = array("UNSIGNED" => "ignore", "ZEROFILL" => "ignore");  break;
									case "DECIMAL":  case "NUMERIC":  $opts2[] = array("DECIMAL", (count($typeopts) ? $typeopts[0] : 10), (count($typeopts) > 1 ? $typeopts[1] : 0));  $extras = array("UNSIGNED" => "ignore", "ZEROFILL" => "ignore");  break;

									case "DATE":  $opts2 = array("DATE");  break;
									case "TIME":  $opts2 = array("DATE");  break;
									case "DATETIME":  case "TIMESTAMP":  $opts2 = array("DATETIME");  break;
									case "YEAR":  $opts2 = array("INTEGER", 4);  break;

									case "CHAR":  $opts2 = array("STRING", 1, (count($typeopts) ? $typeopts[0] : 255), "FIXED" => true);  $extras = array("CHARACTER SET" => "ignore_with_opt", "COLLATE" => "ignore_with_opt");  break;
									case "VARCHAR":  $opts2 = array("STRING", ($typeopts[0] > 255 ? 2 : 1), $typeopts[0]);  $extras = array("CHARACTER SET" => "ignore_with_opt", "COLLATE" => "ignore_with_opt");  break;
									case "TINYTEXT":  $opts2 = array("STRING", 1, 255);  $extras = array("BINARY" => "ignore", "CHARACTER SET" => "ignore_with_opt", "COLLATE" => "ignore_with_opt");  break;
									case "TEXT":  $opts2 = array("STRING", 2);  $extras = array("BINARY" => "ignore", "CHARACTER SET" => "ignore_with_opt", "COLLATE" => "ignore_with_opt");  break;
									case "MEDIUMTEXT":  $opts2 = array("STRING", 3);  $extras = array("BINARY" => "ignore", "CHARACTER SET" => "ignore_with_opt", "COLLATE" => "ignore_with_opt");  break;
									case "LONGTEXT":  $opts2 = array("STRING", 4);  $extras = array("BINARY" => "ignore", "CHARACTER SET" => "ignore_with_opt", "COLLATE" => "ignore_with_opt");  break;
									case "ENUM":  $opts2 = array("STRING", 1, 255);  $extras = array("CHARACTER SET" => "ignore_with_opt", "COLLATE" => "ignore_with_opt");  break;
									case "SET":  $opts2 = array("STRING", 2);  $extras = array("CHARACTER SET" => "ignore_with_opt", "COLLATE" => "ignore_with_opt");  break;

									case "BINARY":  $opts2 = array("BINARY", 1, (count($typeopts) ? $typeopts[0] : 255), "FIXED" => true);  break;
									case "VARBINARY":  $opts2 = array("BINARY", ($typeopts[0] > 255 ? 2 : 1), $typeopts[0]);  break;
									case "TINYBLOB":  $opts2 = array("BINARY", 1, 255);  break;
									case "BLOB":  $opts2 = array("BINARY", 2);  break;
									case "MEDIUMBLOB":  $opts2 = array("BINARY", 3);  break;
									case "LONGBLOB":  $opts2 = array("BINARY", 4);  break;

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
					*/
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

					// THIS WILL ONLY WORK WITH ORACLE 12c and later!
					//
					// The IDENTITY column type is not available in 11g and
					// prior databases.  It may be possible to make sequences
					// and default values work, but all accepted methods for
					// auto increment columns in Oracle use TRIGGERS. I couldn't
					// figure out a way to create a trigger with a native SQL
					// statmenet.  It appears that Trigger creation requires PL/SQL
					if (isset($info["AUTO INCREMENT"]) && $info["AUTO INCREMENT"])
					{
						if ($varbytes < 4)  $sql .= " NUMBER(10,0) GENERATED BY DEFAULT ON NULL AS IDENTITY";
						else  $sql .= " NUMBER(19,0) GENERATED BY DEFAULT ON NULL AS IDENTITY";
					}
					else
					{
						if ($varbytes == 1)  $sql .= " NUMBER(3,0)";
						else if ($varbytes == 2)  $sql .= " NUMBER(5,0)";
						else if ($varbytes == 3)  $sql .= " NUMBER(7,0)";
						else if ($varbytes == 4)  $sql .= " NUMBER(10,0)";
						else  $sql .= " NUMBER(19,0)";
					}

					break;
				}
				case "FLOAT":
				{
					$varbytes = (isset($info[1]) ? (int)$info[1] : 4);
					if ($varbytes <= 4)  $sql .= " FLOAT";
					else  $sql .= " FLOAT(24)";

					break;
				}
				case "DECIMAL":
				{
					$sql .= " FLOAT(24)";

					break;
				}
				case "STRING":
				{
					$varbytes = $info[1];
					if ($varbytes == 1)
					{
						if (isset($info["FIXED"]) && $info["FIXED"])  $sql .= " NCHAR2(" . $info[2] . ")";
						else  $sql .= " NVARCHAR2(" . $info[2] . ")";

						// Used NVARCHAR2 so that double-byte characters could be used for internationalization
					}
					else if ($varbytes == 2)  $sql .= " VARCHAR2(4000)";
					else if ($varbytes == 3)  $sql .= " VARCHAR2(4000)";
					else  $sql .= " VARCHAR2(4000)";

					// Used VARCHAR2(4000) instead of CLOB because PHP was not reading CLOB data correctly.
					// data was being returned as a stream and not a text value.  It may be possible to
					// use CLOB functionality, however it will need more investigation.
// See comment in Connect() for a possible fix.
// If that fails, this might be able to be handled by using a RunRowFilter() for SELECT queries.  Convert the column to a string.

					// Could not use NVARCHAR because the maximum number of characters is 2000, not 4000
					// This was not long enough to support the "info" and "info2" columns in SSO

					break;
				}
				case "BINARY": $sql .= " BLOB";
				case "DATE":  $sql .= " DATE";  break;
				case "TIME":  $sql .= " TIMESTAMP";  break;
				case "DATETIME":  $sql .= " TIMESTAMP";  break;
				case "BOOLEAN":  $sql .= " BOOLEAN";  break;
				default:  return array("success" => false, "error" => CSDB::DB_Translate("Unknown column type '%s'.", $type), "errorcode" => "unknown_column_type");
			}

			if (isset($info["NOT NULL"]) && $info["NOT NULL"] && $type != "STRING")  $sql .= " NOT NULL";
			if (isset($info["DEFAULT"]))  $sql .= " DEFAULT " . $this->Quote($info["DEFAULT"]);
			if (isset($info["PRIMARY KEY"]) && $info["PRIMARY KEY"])  $sql .= " PRIMARY KEY";
			if (isset($info["UNIQUE KEY"]) && $info["UNIQUE KEY"])  $sql .= " UNIQUE";

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
					// Oracle CREATE TABLE doesn't support regular KEY indexes, but ADD INDEX does.

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
					// Oracle CREATE TABLE doesn't support regular FULLTEXT indexes, but ADD INDEX does.

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