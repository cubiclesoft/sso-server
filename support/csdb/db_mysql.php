<?php
	// CubicleSoft MySQL/Maria DB database interface.
	// (C) 2015 CubicleSoft.  All Rights Reserved.

	if (!class_exists("CSDB"))  exit();

	class CSDB_mysql extends CSDB
	{
		public function IsAvailable()
		{
			return (class_exists("PDO") && in_array("mysql", PDO::getAvailableDrivers()) ? "mysql" : false);
		}

		public function GetDisplayName()
		{
			return CSDB::DB_Translate("MySQL/Maria DB (via PDO)");
		}

		public function Connect($dsn, $username = false, $password = false, $options = array())
		{
			parent::Connect($dsn, $username, $password, $options);

			// Set Unicode support.
			$this->Query("SET", "NAMES 'utf8'");
		}

		public function GetVersion()
		{
			return $this->GetOne("SELECT", array("VERSION()"));
		}

		public function GetInsertID($name = null)
		{
			return $this->GetOne("SELECT", array("LAST_INSERT_ID()"));
		}

		public function TableExists($name)
		{
			return ($this->GetOne("SHOW TABLES", array("LIKE" => $name)) === false ? false : true);
		}

		public function LargeResults($enable)
		{
			$this->dbobj->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, (!(bool)$enable));
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
				case "CREATE DATABASE":
				{
					$master = true;

					$sql = "CREATE DATABASE " . $this->QuoteIdentifier($queryinfo[0]);
					$sql .= " CHARACTER SET " . (isset($queryinfo["CHARACTER SET"]) ? $queryinfo["CHARACTER SET"] : "utf8");
					if (isset($queryinfo["COLLATE"]))  $sql .= " COLLATE " . $queryinfo["COLLATE"];

					return array("success" => true);
				}
				case "DROP DATABASE":
				{
					$master = true;

					$sql = "DROP DATABASE " . $this->QuoteIdentifier($queryinfo[0]);

					return array("success" => true);
				}
				case "CREATE TABLE":
				{
					$supported = array(
						"TEMPORARY" => "CREATE TEMPORARY TABLE",
						"AS_SELECT" => true,
						"PRE_AS" => array("REPLACE" => "bool", "IGNORE" => "bool"),
						"PROCESSKEYS" => true,
						"POSTCREATE" => array("CHARACTER SET" => "string", "COLLATE" => "string")
					);
					if (!isset($queryinfo["CHARACTER SET"]))  $queryinfo["CHARACTER SET"] = "utf8";

					return $this->ProcessCREATE_TABLE($master, $sql, $opts, $queryinfo, $args, $subquery, $supported);
				}
				case "ADD COLUMN":
				{
					$master = true;

					$result = $this->ProcessColumnDefinition($queryinfo[2]);
					if (!$result["success"])  return $result;

					$sql = "ALTER TABLE " . $this->QuoteIdentifier($queryinfo[0]) . " ADD COLUMN " . $this->QuoteIdentifier($queryinfo[1]) . " " . $result["sql"];
					if (isset($queryinfo["FIRST"]) && $queryinfo["FIRST"])  $sql .= " FIRST";
					else if (isset($queryinfo["AFTER"]))  $sql .= " AFTER " . $this->QuoteIdentifier($queryinfo["AFTER"]);

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

					$result = $this->ProcessKeyDefinition($queryinfo[1]);
					if (!$result["success"])  return $result;

					$sql = "ALTER TABLE " . $this->QuoteIdentifier($queryinfo[0]) . " ADD " . $result["sql"];

					return array("success" => true);
				}
				case "DROP INDEX":
				{
					$master = true;

					$type = strtoupper($queryinfo[1]);
					$sql = "ALTER TABLE " . $this->QuoteIdentifier($queryinfo[0]) . " DROP ";
					if ($type == "PRIMARY")  $sql .= "PRIMARY KEY";
					else if ($type == "FOREIGN")  $sql .= "FOREIGN KEY " . $this->QuoteIdentifier($queryinfo[2]);
					else  $sql .= "KEY " . $this->QuoteIdentifier($queryinfo[2]);

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

					if (isset($queryinfo["TEMPORARY"]) && $queryinfo["TEMPORARY"])  $cmd = "DROP TEMPORARY TABLE";
					$sql = $cmd . " " . $this->QuoteIdentifier($queryinfo[0]);
					if (isset($queryinfo["RESTRICT"]) && $queryinfo["RESTRICT"])  $sql .= " RESTRICT";
					else if (isset($queryinfo["CASCADE"]) && $queryinfo["CASCADE"])  $sql .= " CASCADE";

					return array("success" => true);
				}
				case "SHOW DATABASES":
				{
					$sql = $cmd;
					if (isset($queryinfo[0]) && $queryinfo[0] == "")  unset($queryinfo[0]);

					return array("success" => true, "filter_opts" => array("mode" => "SHOW DATABASES", "queryinfo" => $queryinfo));
				}
				case "SHOW TABLES":
				{
					if (isset($queryinfo["FULL"]) && $queryinfo["FULL"])  $cmd = "SHOW FULL TABLES";
					$sql = $cmd;
					if (isset($queryinfo["FROM"]))  $sql .= " FROM " . $this->QuoteIdentifier($queryinfo["FROM"]);
					if (isset($queryinfo[0]) && $queryinfo[0] == "")  unset($queryinfo[0]);
					if (isset($queryinfo["LIKE"]))
					{
						$sql .= " LIKE ?";
						$opts[] = $queryinfo["LIKE"];
					}

					return array("success" => true, "filter_opts" => array("mode" => "SHOW TABLES", "queryinfo" => $queryinfo));
				}
				case "SHOW CREATE DATABASE":
				{
					$sql = "SHOW CREATE DATABASE " . $this->QuoteIdentifier($queryinfo[0]);

					return array("success" => true, "filter_opts" => array("mode" => "SHOW CREATE DATABASE"));
				}
				case "SHOW CREATE TABLE":
				{
					$sql = "SHOW CREATE TABLE " . $this->QuoteIdentifier($queryinfo[0]);

					return array("success" => true, "filter_opts" => array("mode" => "SHOW CREATE TABLE", "hints" => (isset($queryinfo["EXPORT HINTS"]) ? $queryinfo["EXPORT HINTS"] : array())));
				}
				case "BULK IMPORT MODE":
				{
					$master = true;

					if ($queryinfo)
					{
						$sql = array(
							"SET autocommit=0",
							"SET unique_checks=0",
							"SET foreign_key_checks=0",
						);

						$opts = array(
							array(),
							array(),
							array(),
						);
					}
					else
					{
						$sql = array(
							"SET autocommit=1",
							"SET unique_checks=1",
							"SET foreign_key_checks=1",
						);

						$opts = array(
							array(),
							array(),
							array(),
						);
					}

					return array("success" => true);
				}
			}

			return array("success" => false, "error" => CSDB::DB_Translate("Unknown query command '%s'.", $cmd), "errorcode" => "unknown_query_command");
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
					if ($varbytes == 1)  $sql .= " TINYINT";
					else if ($varbytes == 2)  $sql .= " SMALLINT";
					else if ($varbytes == 3)  $sql .= " MEDIUMINT";
					else if ($varbytes == 4)  $sql .= " INT";
					else  $sql .= " BIGINT";

					if (isset($info["UNSIGNED"]) && $info["UNSIGNED"])  $sql .= " UNSIGNED";

					break;
				}
				case "FLOAT":
				{
					$varbytes = (isset($info[1]) ? (int)$info[1] : 4);
					if ($varbytes <= 4)  $sql .= " FLOAT";
					else  $sql .= " DOUBLE";

					break;
				}
				case "DECIMAL":
				{
					$sql .= " DECIMAL";
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
					else if ($varbytes == 2)  $sql .= " TEXT";
					else if ($varbytes == 3)  $sql .= " MEDIUMTEXT";
					else  $sql .= " LONGTEXT";

					break;
				}
				case "BINARY":
				{
					$varbytes = $info[1];
					if ($varbytes == 1)
					{
						if (isset($info["FIXED"]) && $info["FIXED"])  $sql .= " BINARY(" . $info[2] . ")";
						else  $sql .= " VARBINARY(" . $info[2] . ")";
					}
					else if ($varbytes == 2)  $sql .= " BLOB";
					else if ($varbytes == 3)  $sql .= " MEDIUMBLOB";
					else  $sql .= " LONGBLOB";

					break;
				}
				case "DATE":  $sql .= " DATE";  break;
				case "TIME":  $sql .= " TIME";  break;
				case "DATETIME":  $sql .= " DATETIME";  break;
				case "BOOLEAN":  $sql .= " TINYINT";  break;
				default:  return array("success" => false, "error" => CSDB::DB_Translate("Unknown column type '%s'.", $type), "errorcode" => "unknown_column_type");
			}

			if (isset($info["NOT NULL"]) && $info["NOT NULL"])  $sql .= " NOT NULL";
			if (isset($info["DEFAULT"]))  $sql .= " DEFAULT " . $this->Quote($info["DEFAULT"]);
			if (isset($info["PRIMARY KEY"]) && $info["PRIMARY KEY"])  $sql .= " PRIMARY KEY";
			if (isset($info["UNIQUE KEY"]) && $info["UNIQUE KEY"])  $sql .= " UNIQUE KEY";
			if (isset($info["AUTO INCREMENT"]) && $info["AUTO INCREMENT"])  $sql .= " AUTO_INCREMENT";
			if (isset($info["COMMENT"]))  $sql .= " COMMENT " . $this->Quote($info["COMMENT"]);
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
					$sql .= "KEY";
					if (isset($info["NAME"]))  $sql .= " " . $this->QuoteIdentifier($info["NAME"]);
					$sql .= " (" . implode(", ", $info[1]) . ")";
					if (isset($info["OPTION"]))  $sql .= " " . $info["OPTION"];

					break;
				}
				case "UNIQUE":
				{
					if (isset($info["CONSTRAINT"]))  $sql .= "CONSTRAINT " . $info["CONSTRAINT"] . " ";
					$sql .= "UNIQUE KEY";
					if (isset($info["NAME"]))  $sql .= " " . $this->QuoteIdentifier($info["NAME"]);
					$sql .= " (" . implode(", ", $info[1]) . ")";
					if (isset($info["OPTION"]))  $sql .= " " . $info["OPTION"];

					break;
				}
				case "FULLTEXT":
				{
					$sql .= "FULLTEXT";
					if (isset($info["NAME"]))  $sql .= " " . $this->QuoteIdentifier($info["NAME"]);
					$sql .= " (" . implode(", ", $info[1]) . ")";
					if (isset($info["OPTION"]))  $sql .= " " . $info["OPTION"];

					break;
				}
				case "FOREIGN":
				{
					if (isset($info["CONSTRAINT"]))  $sql .= "CONSTRAINT " . $info["CONSTRAINT"] . " ";
					$sql .= "FOREIGN KEY";
					if (isset($info["NAME"]))  $sql .= " " . $this->QuoteIdentifier($info["NAME"]);
					$sql .= " (" . implode(", ", $info[1]) . ")";
					$sql .= " REFERENCES " . $this->ProcessReferenceDefinition($info[2]);

					break;
				}
				default:  return array("success" => false, "error" => CSDB::DB_Translate("Unknown key type '%s'.", $type), "errorcode" => "unknown_key_type");;
			}

			return array("success" => true, "sql" => $sql);
		}

		// This is unnecessarily complex because MySQL/Maria DB has weird bugs:
		//   http://bugs.mysql.com/bug.php?id=20001
		public function RunRowFilter(&$row, &$filteropts, &$fetchnext)
		{
			switch ($filteropts["mode"])
			{
				case "SHOW DATABASES":
				{
					if ($row !== false)
					{
						$row->name = $row->Database;
						unset($row->Database);
					}

					break;
				}
				case "SHOW TABLES":
				{
					if ($row !== false)
					{
						foreach ($row as $key => $val)
						{
							if (substr($key, 0, 10) == "Tables_in_")
							{
								$row->name = $val;
								unset($row->$key);
							}
						}
					}

					break;
				}
				case "SHOW CREATE DATABASE":
				{
					if ($row !== false)
					{
						$opts = array($row->Database);
						$str = $row->{"Create Database"};
						$pos = strpos($str, "/*!40100");
						if ($pos !== false)
						{
							$pos2 = strpos($str, "*/", $pos);
							if ($pos2 !== false)
							{
								$str = " " . trim(substr($str, $pos + 8, $pos2 - $pos - 8)) . " ";

								$pos = strpos($str, " CHARACTER SET ");
								if ($pos !== false)
								{
									$pos += 15;
									$pos2 = strpos($str, " ", $pos);
									if ($pos2 !== false)  $opts["CHARACTER SET"] = substr($str, $pos, $pos2 - $pos);
								}

								$pos = strpos($str, " COLLATE ");
								if ($pos !== false)
								{
									$pos += 9;
									$pos2 = strpos($str, " ", $pos);
									if ($pos2 !== false)  $opts["COLLATE"] = substr($str, $pos, $pos2 - $pos);
								}
							}
						}

						$row->cmd = "CREATE DATABASE";
						$row->opts = $opts;
					}

					break;
				}
				case "SHOW CREATE TABLE":
				{
					if ($row !== false)
					{
						$opts = array($row->Table, array(), array());
						$str = $row->{"Create Table"};
						if (strtoupper(substr($str, 0, 23)) == "CREATE TEMPORARY TABLE ")  $opts["TEMPORARY"] = true;
						$pos = stripos($str, " TABLE ");
						if ($pos !== false)
						{
							$str = substr($str, $pos + 7);
							$ident = true;
							$id = $this->ExtractIdentifier($str, $ident);
							$str = trim($str);
							if ($str{0} == "(")
							{
								$str = trim(substr($str, 1));

								// Process columns and keys.
								$colextras = array(
									"NOT NULL", "NULL", "DEFAULT", "AUTO_INCREMENT",
									"UNIQUE KEY", "UNIQUE", "PRIMARY KEY", "PRIMARY",
									"COMMENT", "REFERENCES"
								);
								$keytypes = array(
									"CONSTRAINT" => true, "INDEX" => true, "KEY" => true, "FULLTEXT" => true, "SPATIAL" => true, "PRIMARY" => true, "UNIQUE" => true, "FOREIGN" => true, "CHECK" => true
								);
								$keyextras = array(
									"PRIMARY KEY", "INDEX", "KEY", "UNIQUE INDEX", "UNIQUE KEY", "UNIQUE",
									"FULLTEXT INDEX", "FULLTEXT KEY", "FULLTEXT", "SPATIAL INDEX", "SPATIAL KEY", "SPATIAL",
									"FOREIGN KEY", "CHECK"
								);
								while ($str != "" && $str{0} != ")")
								{
									$id = $this->ExtractIdentifier($str, $ident);
									if ($ident || !isset($keytypes[strtoupper($id)]))
									{
										// Column.  Extract data type and options/values.
										$pos = strpos($str, "(");
										$pos2 = strpos($str, " ");
										if ($pos === false)  $pos = strlen($str);
										if ($pos2 === false)  $pos2 = strlen($str);
										if ($pos < $pos2)
										{
											$type = substr($str, 0, $pos);
											$str = trim(substr($str, $pos + 1));
											$typeopts = array();
											while ($str != "" && $str{0} != ")")
											{
												$typeopts[] = $this->ExtractAndUnescapeValue($str);

												if ($str != "" && $str{0} == ",")  $str = trim(substr($str, 1));
											}

											$str = trim(substr($str, 1));
										}
										else
										{
											$type = substr($str, 0, $pos2);
											$str = trim(substr($str, $pos2 + 1));
											$typeopts = array();
										}

										// Process data type.  Stop processing on an unknown column type.
										$extras = array();
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

												$opts2["UNSIGNED"] = true;

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
											case "TIME":  $opts2 = array("TIME");  break;
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

										do
										{
											$found = false;
											foreach ($extras as $extra => $rule)
											{
												if (strtoupper(substr($str, 0, strlen($extra))) == $extra)
												{
													$str = trim(substr($str, strlen($extra)));

													if ($rule == "keep")  $opts2[$extra] = true;
													else if ($rule == "ignore_with_opt")  $this->ExtractIdentifier($str, $ident);

													$found = true;
												}
											}
										} while ($found);

										while ($str != "" && $str{0} != "," && $str{0} != ")")
										{
											foreach ($colextras as $extra)
											{
												if (strtoupper(substr($str, 0, strlen($extra))) == $extra)
												{
													$str = trim(substr($str, strlen($extra)));

													switch ($extra)
													{
														case "NOT NULL":  $opts2["NOT NULL"] = true;  break;
														case "NULL":  break;
														case "DEFAULT":  $opts2["DEFAULT"] = $this->ExtractAndUnescapeValue($str);  break;
														case "AUTO_INCREMENT":  $opts2["AUTO INCREMENT"] = true;  break;
														case "UNIQUE KEY":  case "UNIQUE":  $opts2["UNIQUE KEY"] = true;  break;
														case "PRIMARY KEY":  case "PRIMARY":  $opts2["PRIMARY KEY"] = true;  break;
														case "COMMENT":  $opts2["COMMENT"] = $this->ExtractAndUnescapeValue($str);  break;
														case "REFERENCES":  $opts2["REFERENCES"] = $this->ExtractReferencesInfo($str);  break;
													}
												}
											}
										}

										if (isset($filteropts["hints"]) && isset($filteropts["hints"][$id]))  $opts2 = $filteropts["hints"][$id];

										$opts[1][$id] = $opts2;
									}
									else
									{
										// Key or constraint.
										$opts2 = array();
										if (strtoupper($id) == "CONSTRAINT")
										{
											$id = $this->ExtractIdentifier($str, $ident);
											$id2 = strtoupper($id);
											if ($ident || !isset($keytypes[strtoupper($id)]))
											{
												$opts2["CONSTRAINT"] = $id;
												$id = $this->ExtractIdentifier($str, $ident);
											}
										}

										$str = $id . " " . $str;

										foreach ($keyextras as $extra)
										{
											if (strtoupper(substr($str, 0, strlen($extra))) == $extra)
											{
												$str = trim(substr($str, strlen($extra)));

												switch ($extra)
												{
													case "PRIMARY KEY":
													{
														$opts2[] = "PRIMARY";
														while ($str != "" && $str{0} != "(")
														{
															$id = $this->ExtractIdentifier($str, $ident);
															if (!$ident && strtoupper($id) == "USING")  $opts2["USING"] = $this->ExtractIdentifier($str, $ident);
														}
														$opts2[] = $this->ExtractMultipleIdentifiers($str);

														break;
													}
													case "INDEX":
													case "KEY":
													{
														$opts2[] = "KEY";
														while ($str != "" && $str{0} != "(")
														{
															$id = $this->ExtractIdentifier($str, $ident);
															if (!$ident && strtoupper($id) == "USING")  $opts2["USING"] = $this->ExtractIdentifier($str, $ident);
															else  $opts2["NAME"] = $id;
														}
														$opts2[] = $this->ExtractMultipleIdentifiers($str);

														break;
													}
													case "UNIQUE INDEX":
													case "UNIQUE KEY":
													case "UNIQUE":
													{
														$opts2[] = "UNIQUE";
														while ($str != "" && $str{0} != "(")
														{
															$id = $this->ExtractIdentifier($str, $ident);
															if (!$ident && strtoupper($id) == "USING")  $opts2["USING"] = $this->ExtractIdentifier($str, $ident);
															else  $opts2["NAME"] = $id;
														}
														$opts2[] = $this->ExtractMultipleIdentifiers($str);

														break;
													}
													case "FULLTEXT INDEX":
													case "FULLTEXT KEY":
													case "FULLTEXT":
													{
														$opts2[] = "FULLTEXT";
														while ($str != "" && $str{0} != "(")  $opts2["NAME"] = $this->ExtractIdentifier($str, $ident);
														$opts2[] = $this->ExtractMultipleIdentifiers($str);

														break;
													}
													case "SPATIAL INDEX":
													case "SPATIAL KEY":
													case "SPATIAL":
													{
														// GIS is not portable.
														while ($str != "" && $str{0} != "(")  $this->ExtractIdentifier($str, $ident);
														$this->ExtractMultipleIdentifiers($str);

														break;
													}
													case "FOREIGN KEY":
													{
														$opts2[] = "FOREIGN";
														while ($str != "" && $str{0} != "(")  $opts2["NAME"] = $this->ExtractIdentifier($str, $ident);
														$opts2[] = $this->ExtractMultipleIdentifiers($str);
														$opts2[] = $this->ExtractReferencesInfo($str);

														break;
													}
													case "CHECK":
													{
														$pos = strpos($str, ")");
														$str = substr($str, $pos + 1);

														break;
													}
												}
											}
										}

										while ($str != "" && $str{0} != "," && $str{0} != ")")
										{
											$id = $this->ExtractIdentifier($str, $ident);
											if (!$ident && strtoupper($id) == "USING")  $opts2["USING"] = $this->ExtractIdentifier($str, $ident);
										}

										if (isset($opts2[0]))  $opts[2][] = $opts2;
									}

									if ($str != "" && $str{0} == ",")  $str = trim(substr($str, 1));

									$str = trim($str);
								}

								// Process the last line.
								$str = trim(substr($str, 1));
								$extras = array(
									"ENGINE", "TYPE", "AUTO_INCREMENT", "AVG_ROW_LENGTH", "DEFAULT CHARACTER SET", "CHARACTER SET",
									"DEFAULT CHARSET", "CHARSET", "CHECKSUM", "DEFAULT COLLATE", "COLLATE",
									"COMMENT", "CONNECTION", "DATA DIRECTORY", "DELAY_KEY_WRITE", "INDEX DIRECTORY",
									"INSERT_METHOD", "MAX_ROWS", "MIN_ROWS", "PACK_KEYS", "PASSWORD", "ROW_FORMAT",
								);
								do
								{
									$found = false;

									foreach ($extras as $extra)
									{
										if (strtoupper(substr($str, 0, strlen($extra))) == $extra)
										{
											$str = trim(substr($str, strlen($extra)));
											if ($str{0} == "=")  $str = trim(substr($str, 1));

											switch ($extra)
											{
												case "ENGINE":  case "TYPE":  $opts["ENGINE"] = $this->ExtractAndUnescapeValue($str);  break;
												case "DEFAULT CHARACTER SET":
												case "CHARACTER SET":
												case "DEFAULT CHARSET":
												case "CHARSET":
												{
													$opts["CHARACTER SET"] = $this->ExtractAndUnescapeValue($str);

													break;
												}
												case "DEFAULT COLLATE":
												case "COLLATE":
												{
													$opts["COLLATE"] = $this->ExtractAndUnescapeValue($str);

													break;
												}
												case "AUTO_INCREMENT":
												case "AVG_ROW_LENGTH":
												case "CHECKSUM":
												case "COMMENT":
												case "CONNECTION":
												case "DATA DIRECTORY":
												case "DELAY_KEY_WRITE":
												case "INDEX DIRECTORY":
												case "INSERT_METHOD":
												case "MAX_ROWS":
												case "MIN_ROWS":
												case "PACK_KEYS":
												case "PASSWORD":
												case "ROW_FORMAT":
												{
													$this->ExtractAndUnescapeValue($str);

													break;
												}
											}

											$found = true;
										}
									}
								} while ($found);
							}
						}

						$row->cmd = "CREATE TABLE";
						$row->opts = $opts;
					}

					break;
				}
			}

			parent::RunRowFilter($row, $filteropts, $fetchnext);
		}

		private function ExtractIdentifier(&$str, &$ident)
		{
			$str = trim($str);
			if ($str{0} == "`")
			{
				$str = substr($str, 1);
				$pos = strpos($str, "`");
				while ($pos !== false && $str{$pos + 1} == "`")  $pos = strpos($str, "`", $pos + 2);
				if ($pos === false)  $pos = strlen($str);
				$result = str_replace("``", "`", substr($str, 0, $pos));
				$str = (string)@substr($str, $pos + 1);

				$ident = true;
			}
			else
			{
				if (!preg_match('/[^A-Za-z0-9_]/', $str, $matches, PREG_OFFSET_CAPTURE))  $matches = array(array($str, strlen($str)));

				$result = substr($str, 0, $matches[0][1]);
				$str = substr($str, $matches[0][1]);

				$ident = false;
			}

			$str = trim($str);

			return $result;
		}

		private function ExtractAndUnescapeValue(&$str)
		{
			$result = "";
			$str = trim($str);
			if ($str{0} == "'")
			{
				$str = substr($str, 1);
				while ($str != "" && ($str{0} != "'" || (strlen($str) > 1 && $str{1} == "'")))
				{
					if ($str{0} == "'" || $str{0} == "\\")  $str = substr($str, 1);
					$result .= $str{0};

					$str = substr($str, 1);
				}

				$str = substr($str, 1);
			}
			else if ($str{0} == "\"")
			{
				$str = substr($str, 1);
				while ($str != "" && ($str{0} != "\"" || (strlen($str) > 1 && $str{1} != "\"")))
				{
					if ($str{0} == "\"" || $str{0} == "\\")  $str = substr($str, 1);
					$result .= $str{0};

					$str = substr($str, 1);
				}
			}
			else
			{
				if (!preg_match('/[^A-Za-z0-9_]/', $str, $matches, PREG_OFFSET_CAPTURE))  $matches = array(array($str, strlen($str)));

				$result = substr($str, 0, $matches[0][1]);
				$str = substr($str, $matches[0][1]);
				if (strtoupper($result) === "NULL")  $result = false;
			}

			$str = trim($str);

			return $result;
		}

		// Assumes the 'REFERENCES' keyword has already been removed from the string.
		private function ExtractReferencesInfo(&$str)
		{
			$ident = false;
			$result = array($this->ExtractIdentifier($str, $ident), $this->ExtractMultipleIdentifiers($str));

			$extras = array(
				"MATCH FULL", "MATCH PARTIAL", "MATCH SIMPLE",
				"ON DELETE RESTRICT", "ON DELETE CASCADE", "ON DELETE SET NULL", "ON DELETE NO ACTION",
				"ON UPDATE RESTRICT", "ON UPDATE CASCADE", "ON UPDATE SET NULL", "ON UPDATE NO ACTION",
			);
			do
			{
				$found = false;

				foreach ($extras as $extra)
				{
					if (strtoupper(substr($str, 0, strlen($extra))) == $extra)
					{
						$str = trim(substr($str, strlen($extra)));

						switch ($extra)
						{
							case "MATCH FULL":  $result["MATCH FULL"] = true;  break;
							case "MATCH PARTIAL":  $result["MATCH PARTIAL"] = true;  break;
							case "MATCH SIMPLE":  $result["MATCH SIMPLE"] = true;  break;
							case "ON DELETE RESTRICT":  $result["ON DELETE"] = "RESTRICT";  break;
							case "ON DELETE CASCADE":  $result["ON DELETE"] = "CASCADE";  break;
							case "ON DELETE SET NULL":  $result["ON DELETE"] = "SET NULL";  break;
							case "ON DELETE NO ACTION":  $result["ON DELETE"] = "NO ACTION";  break;
							case "ON UPDATE RESTRICT":  $result["ON UPDATE"] = "RESTRICT";  break;
							case "ON UPDATE CASCADE":  $result["ON UPDATE"] = "CASCADE";  break;
							case "ON UPDATE SET NULL":  $result["ON UPDATE"] = "SET NULL";  break;
							case "ON UPDATE NO ACTION":  $result["ON UPDATE"] = "NO ACTION";  break;
						}

						$found = true;
					}
				}
			} while ($found);

			return $result;
		}

		private function ExtractMultipleIdentifiers(&$str)
		{
			$result = array();
			if ($str{0} == "(")
			{
				$ident = false;
				do
				{
					$str = substr($str, 1);
					$result[] = $this->ExtractIdentifier($str, $ident);
				} while ($str{0} == ",");

				if ($str{0} == ")")  $str = trim(substr($str, 1));
			}

			return $result;
		}
	}
?>