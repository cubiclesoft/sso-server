<?php
	// CubicleSoft generic database base class.
	// (C) 2015 CubicleSoft.  All Rights Reserved.

	class CSDB
	{
		protected $numqueries, $totaltime, $dbobj, $origdbobj, $debug, $transaction, $currdb;
		private $available_status;
		private $mdsn, $musername, $mpassword, $moptions;

		public static function ConvertToDBDate($ts, $gmt = true)
		{
			return ($gmt ? gmdate("Y-m-d", $ts) : date("Y-m-d", $ts)) . " 00:00:00";
		}

		public static function ConvertToDBTime($ts, $gmt = true)
		{
			return ($gmt ? gmdate("Y-m-d H:i:s", $ts) : date("Y-m-d H:i:s", $ts));
		}

		public static function ConvertFromDBTime($field, $gmt = true)
		{
			$field = array_pad(explode(" ", preg_replace('/\s+/', " ", preg_replace('/[^0-9]/', " ", $field))), 6, 0);
			$year = (int)$field[0];
			$month = (int)$field[1];
			$day = (int)$field[2];
			$hour = (int)$field[3];
			$min = (int)$field[4];
			$sec = (int)$field[5];

			return ($gmt ? gmmktime($hour, $min, $sec, $month, $day, $year) : mktime($hour, $min, $sec, $month, $day, $year));
		}

		public function IsAvailable()
		{
			return false;
		}

		public function GetDisplayName()
		{
			return "";
		}

		public function __construct($dsn = "", $username = "", $password = "")
		{
			$this->numqueries = 0;
			$this->totaltime = 0;
			$this->dbobj = false;
			$this->origdbobj = false;
			$this->debug = false;
			$this->transaction = 0;
			$this->mdsn = false;
			$this->currdb = false;
			$this->available_status = $this->IsAvailable();

			if ($dsn != "")  $this->Connect($dsn, $username, $password);
		}

		public function __destruct()
		{
			$this->Disconnect();
		}

		public function SetDebug($debug)
		{
			$this->debug = $debug;
		}

		protected function AssertPDOAvailable($checkdb)
		{
			if (!is_string($this->available_status))
			{
				throw new Exception(CSDB::DB_Translate("The driver is not available."));
				exit();
			}

			if ($checkdb && $this->dbobj === false)
			{
				throw new Exception(CSDB::DB_Translate("Not connected to a database."));
				exit();
			}
		}

		public function BeginTransaction()
		{
			$this->AssertPDOAvailable(true);

			if (!$this->transaction)
			{
				$this->dbobj->beginTransaction();

				if (is_resource($this->debug))  fwrite($this->debug, "BEGIN transaction.\n----------\n");
			}

			$this->transaction++;
		}

		public function Commit()
		{
			$this->AssertPDOAvailable(true);

			if ($this->transaction)
			{
				$this->transaction--;
				if (!$this->transaction)
				{
					$this->dbobj->commit();

					if (is_resource($this->debug))  fwrite($this->debug, "COMMIT transaction.\n----------\n");
				}
			}
		}

		public function Rollback()
		{
			$this->AssertPDOAvailable(true);

			if ($this->transaction)
			{
				$this->transaction = 0;
				$this->dbobj->rollBack();

				if (is_resource($this->debug))  fwrite($this->debug, "ROLLBACK transaction.\n----------\n");
			}
		}

		public function Connect($dsn, $username = false, $password = false, $options = array())
		{
			$this->AssertPDOAvailable(false);

			$this->origdbobj = $this->dbobj;
			$this->dbobj = false;
			$this->mdsn = false;

			$startts = microtime(true);

			if (is_array($dsn))
			{
				foreach ($dsn as $key => $val)  $dsn[$key] = $key . "=" . $val;
				$dsn = $this->available_status . ":" . implode(";", $dsn);
			}

			$pos = strpos($dsn, ":");
			$driver = substr($dsn, 0, $pos);
			if ($driver !== $this->available_status)
			{
				throw new Exception(CSDB::DB_Translate("The driver '%s' is invalid.  Must be '%s'.", htmlspecialchars($driver), htmlspecialchars($this->available_status)));
				exit();
			}

			try
			{
				if ($password !== false)  $this->dbobj = new PDO($dsn, $username, $password);
				else if ($username !== false)  $this->dbobj = new PDO($dsn, $username);
				else  $this->dbobj = new PDO($dsn);
			}
			catch (Exception $e)
			{
				if (is_resource($this->debug))  fwrite($this->debug, "The connection to the database server failed.  " . $e->getMessage() . "\n----------\n");

				if (is_resource($this->debug) || $this->debug)  throw new Exception(CSDB::DB_Translate("The connection to the database server failed.  %s", $e->getMessage()));
				else  throw new Exception(CSDB::DB_Translate("The connection to the database server failed."));
				exit();
			}

			if (is_resource($this->debug))  fwrite($this->debug, "Connected to '" . $dsn . "'.\n----------\n");

			$this->totaltime += (microtime(true) - $startts);

			return true;
		}

		public function SetMaster($dsn, $username = false, $password = false, $options = array())
		{
			$this->mdsn = $dsn;
			$this->musername = $username;
			$this->mpassword = $password;
			$this->moptions = $options;
		}

		public function Disconnect()
		{
			$startts = microtime(true);

			while ($this->transaction)  $this->Commit();

			if ($this->dbobj !== false)
			{
				unset($this->dbobj);
				$this->dbobj = false;
			}
			if ($this->origdbobj !== false)
			{
				unset($this->origdbobj);
				$this->origdbobj = false;
			}

			$this->totaltime += (microtime(true) - $startts);

			if (is_resource($this->debug))  fwrite($this->debug, "Disconnected from database.\n\nTotal query time:  " . sprintf("%.03f", $this->totaltime) . " seconds\n----------\n");

			return true;
		}

		public function Query()
		{
			$params = func_get_args();
			return $this->InternalQuery($params);
		}

		public function GetRow()
		{
			$params = func_get_args();
			$dbresult = $this->InternalQuery($params);
			if ($dbresult === false)  return false;
			$row = $dbresult->NextRow();
			unset($dbresult);

			return $row;
		}

		public function GetRowArray()
		{
			$params = func_get_args();
			$dbresult = $this->InternalQuery($params);
			if ($dbresult === false)  return false;
			$row = $dbresult->NextRow(PDO::FETCH_BOTH);
			unset($dbresult);

			return $row;
		}

		public function GetCol()
		{
			$result = array();

			$params = func_get_args();
			$dbresult = $this->InternalQuery($params);
			if ($dbresult === false)  return false;
			while ($row = $dbresult->NextRow(PDO::FETCH_NUM))
			{
				$result[] = $row[0];
			}

			return $result;
		}

		public function GetOne()
		{
			$params = func_get_args();
			$dbresult = $this->InternalQuery($params);
			if ($dbresult === false)  return false;
			$row = $dbresult->NextRow(PDO::FETCH_NUM);
			unset($dbresult);
			if ($row === false)  return false;

			return $row[0];
		}

		public function GetVersion()
		{
			return "";
		}

		public function GetInsertID($name = null)
		{
			return $this->dbobj->lastInsertId($name);
		}

		public function TableExists($name)
		{
			return false;
		}

		public function LargeResults($enable)
		{
		}

		public function NumQueries()
		{
			return $this->numqueries;
		}

		// Execution time with microsecond precision.
		public function ExecutionTime()
		{
			return $this->totaltime;
		}

		private function InternalQuery($params)
		{
			$startts = microtime(true);

			$cmd = array_shift($params);
			if ($cmd !== false)  $cmd = strtoupper($cmd);
			$queryinfo = array_shift($params);
			if (count($params) == 1 && is_array($params[0]))  $params = $params[0];

			if ($cmd === false)
			{
				$master = true;
				$sqls = array((string)$queryinfo);
				$opts = array($params);
				$filteropts = false;
			}
			else
			{
				$master = false;
				$sqls = "";
				$opts = array();
				$result = $this->GenerateSQL($master, $sqls, $opts, $cmd, $queryinfo, $params, false);
				$filteropts = (isset($result["filter_opts"]) ? $result["filter_opts"] : false);
				if (!$result["success"])
				{
					if ($result["errorcode"] == "skip_sql_query")  return new CSDB_PDO_Statement($this, false, $filteropts);

					if (is_resource($this->debug))  fwrite($this->debug, "Error generating '" . $cmd . "' SQL query.  " . $result["error"] . " (" . $result["errorcode"] . ")\n\n" . (is_string($sqls) ? $sqls : var_export($sqls, true)) . "\n----------\n");

					throw new Exception(CSDB::DB_Translate("Error generating '%s' SQL query.  %s (%s)", $cmd, $result["error"], $result["errorcode"]));
					exit();
				}

				if ($cmd == "USE")  $this->currdb = $queryinfo;
			}

			// Switch to master database.
			if ($master && $this->mdsn !== false)
			{
				$numcommit = $this->transaction;
				while ($this->transaction)  $this->Commit();

				if (!$this->Connect($this->mdsn, $this->musername, $this->mpassword, $this->moptions))
				{
					throw new Exception(CSDB::DB_Translate("The connection to the master database failed."));
					exit();
				}

				if (is_resource($this->debug))  fwrite($this->debug, "Connection to master database succeeded.\n----------\n");

				if ($this->currdb !== false)  $this->Query("USE", $this->currdb);

				while ($this->transaction < $numcommit)  $this->BeginTransaction();
			}

			$prepareopts = (isset($result["prepare_opts"]) ? $result["prepare_opts"] : array());

			if (is_string($sqls))
			{
				$sqls = array($sqls);
				$opts = array($opts);
			}
			foreach ($sqls as $num => $sql)
			{
				$opts2 = $opts[$num];

				$result = $this->dbobj->prepare($sql, $prepareopts);
				if ($result === false)
				{
					$info = $this->dbobj->errorInfo();

					if (is_resource($this->debug))  fwrite($this->debug, $info[0] . " " . $info[2] . " (" . $info[1] . ").\nError preparing SQL query:\n\n" . $sql . "\n\n" . var_export($opts2, true) . "\n----------\n");

					if (is_resource($this->debug) || $this->debug)  throw new Exception(CSDB::DB_Translate("%s %s (%s).  Error preparing SQL query:  %s  %s", $info[0], $info[2], $info[1], $sql, var_export($opts2, true)));
					else  throw new Exception(CSDB::DB_Translate("Error preparing SQL query.  %s %s (%s)", $info[0], $info[2], $info[1]));
					exit();
				}
				if (!$result->execute($opts2))
				{
					$info = $result->errorInfo();

					if (is_resource($this->debug))  fwrite($this->debug, $info[0] . " " . $info[2] . " (" . $info[1] . ").\nError executing SQL query:\n\n" . $sql . "\n\n" . var_export($opts2, true) . "\n----------\n");

					if (is_resource($this->debug) || $this->debug)  throw new Exception(CSDB::DB_Translate("%s %s (%s).  Error executing SQL query:  %s  %s", $info[0], $info[2], $info[1], $sql, var_export($opts2, true)));
					else  throw new Exception(CSDB::DB_Translate("Error executing SQL query.  %s %s (%s)", $info[0], $info[2], $info[1]));
					exit();
				}

				$this->numqueries++;

				$diff = (microtime(true) - $startts);
				$this->totaltime += $diff;

				if (is_resource($this->debug))
				{
					ob_start();
					debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
					$info = ob_get_contents();
					ob_end_clean();
					fwrite($this->debug, "Query #" . $this->numqueries . "\n\n" . $sql . "\n\n" . var_export($opts2, true) . "\n\n" . $info . "\nCommand:  " . $cmd . "\n\n" . sprintf("%.03f", $diff) . " seconds (" . sprintf("%.03f", $this->totaltime) . " total)\n----------\n");
				}

				if ($filteropts !== false)  $this->RunStatementFilter($result, $filteropts);
			}

			return new CSDB_PDO_Statement($this, $result, $filteropts);
		}

		public function Quote($str, $type = PDO::PARAM_STR)
		{
			if (is_bool($str) && !$str)  return "NULL";

			return $this->dbobj->quote((string)$str, $type);
		}

		public function QuoteIdentifier($str)
		{
			return "";
		}

		protected function ProcessSubqueries(&$result, &$master, $subqueries)
		{
			foreach ($subqueries as $num => $subquery)
			{
				$sql2 = "";
				$opts2 = array();
				$queryinfo2 = array_shift($subquery);
				if (count($subquery) == 1 && is_array($subquery[0]))  $subquery = $subquery[0];
				$result = $this->GenerateSQL($master, $sql2, $opts2, "SELECT", $queryinfo2, $subquery, true);
				if (!$result["success"])  return $result;

				$result = str_replace("{" . $num . "}", "(" . $sql2 . ")", $result);
			}

			return array("success" => true);
		}

		// Derived classes implement this function.
		protected function GenerateSQL(&$master, &$sql, &$opts, $cmd, $queryinfo, $args, $subquery)
		{
			return array("success" => false, "error" => CSDB::DB_Translate("The base class GenerateSQL() was called."), "errorcode" => "wrong_class_used");
		}

		protected function RunStatementFilter(&$stmt, &$filteropts)
		{
		}

		// Can't be a 'protected' function since this is called from CSDB_PDO_Statement.
		public function RunRowFilter(&$row, &$filteropts, &$fetchnext)
		{
			if ($row === false)  return;

			switch ($filteropts["mode"])
			{
				case "SHOW DATABASES":
				case "SHOW TABLES":
				{
					if (isset($row->name) && isset($filteropts["queryinfo"]) && isset($filteropts["queryinfo"][0]))
					{
						if (stripos($row->name, $filteropts["queryinfo"][0]) === false)  $fetchnext = true;
					}

					break;
				}
				case "SELECT EXPORT":
				{
					if ($row !== false)
					{
						$opts = array($filteropts["table"], array());
						foreach ($row as $key => $val)
						{
							$opts[1][$key] = $val;
							unset($row->$key);
						}

						$row->cmd = "INSERT";
						$row->opts = $opts;
					}

					break;
				}
			}
		}

		protected function ProcessColumnDefinition($info)
		{
			return array("success" => false, "error" => CSDB::DB_Translate("The base class ProcessColumnDefinition() was called."), "errorcode" => "wrong_class_used");
		}

		protected function ProcessKeyDefinition($info)
		{
			return array("success" => false, "error" => CSDB::DB_Translate("The base class ProcessKeyDefinition() was called."), "errorcode" => "wrong_class_used");
		}

		// Not intended to be overridden, just accessible to the parent.
		protected function ProcessSELECT(&$master, &$sql, &$opts, $queryinfo, $args, $subquery, $supported)
		{
			$sql = "SELECT";
			foreach ($supported["PRECOLUMN"] as $key => $mode)
			{
				if ($key != "SUBQUERIES" && isset($queryinfo[$key]))
				{
					if ($mode == "bool" && $queryinfo[$key])  $sql .= " " . $key;
				}
			}

			if (is_array($queryinfo[0]))
			{
				foreach ($queryinfo[0] as $num => $col)  $queryinfo[0][$num] = $this->QuoteIdentifier($col);
				$queryinfo[0] = implode(", ", $queryinfo[0]);
			}

			if ($supported["PRECOLUMN"]["SUBQUERIES"] && isset($queryinfo["SUBQUERIES"]))
			{
				$result = $this->ProcessSubqueries($queryinfo[0], $master, $queryinfo["SUBQUERIES"]);
				if (!$result["success"])  return $result;
			}

			$sql .= " " . $queryinfo[0];

			if (isset($queryinfo["FROM"]))  $queryinfo[1] = $queryinfo["FROM"];

			if (isset($queryinfo[1]))
			{
				$prefix = (isset($supported["DBPREFIX"]) ? $supported["DBPREFIX"] : "");

				$pos = strpos($queryinfo[1], "?");
				while ($pos !== false)
				{
					$queryinfo[1] = substr($queryinfo[1], 0, $pos) . $this->QuoteIdentifier($prefix . array_shift($args)) . substr($queryinfo[1], $pos + 1);

					$pos = strpos($queryinfo[1], "?");
				}

				if ($supported["FROM"]["SUBQUERIES"] && isset($queryinfo["SUBQUERIES"]))
				{
					$result = $this->ProcessSubqueries($queryinfo[1], $master, $queryinfo["SUBQUERIES"]);
					if (!$result["success"])  return $result;
				}

				$sql .= " FROM " . $queryinfo[1];
			}

			if (isset($queryinfo["WHERE"]))
			{
				if ($supported["WHERE"]["SUBQUERIES"] && isset($queryinfo["SUBQUERIES"]))
				{
					$result = $this->ProcessSubqueries($queryinfo["WHERE"], $master, $queryinfo["SUBQUERIES"]);
					if (!$result["success"])  return $result;
				}

				$sql .= " WHERE " . $queryinfo["WHERE"];
			}

			if (isset($supported["GROUP BY"]) && $supported["GROUP BY"] && isset($queryinfo["GROUP BY"]))  $sql .= " GROUP BY " . $queryinfo["GROUP BY"];
			if (isset($supported["HAVING"]) && $supported["HAVING"] && isset($queryinfo["HAVING"]))  $sql .= " HAVING " . $queryinfo["HAVING"];
			if (isset($supported["ORDER BY"]) && $supported["ORDER BY"] && isset($queryinfo["ORDER BY"]))  $sql .= " ORDER BY " . $queryinfo["ORDER BY"];
			if (isset($supported["LIMIT"]) && $supported["LIMIT"] !== false && !$subquery && isset($queryinfo["LIMIT"]))
			{
				if (is_array($queryinfo["LIMIT"]))  $queryinfo["LIMIT"] = implode($supported["LIMIT"], $queryinfo["LIMIT"]);
				$sql .= " LIMIT " . $queryinfo["LIMIT"];
			}

			$opts = $args;

			if (isset($queryinfo["EXPORT ROWS"]))  return array("success" => true, "filter_opts" => array("mode" => "SELECT EXPORT", "table" => $queryinfo["EXPORT ROWS"]));

			return array("success" => true);
		}

		protected function ProcessINSERT(&$master, &$sql, &$opts, $queryinfo, $args, $subquery, $supported)
		{
			$master = true;

			$sql = "INSERT";
			foreach ($supported["PREINTO"] as $key => $mode)
			{
				if (isset($queryinfo[$key]))
				{
					if ($mode == "bool" && $queryinfo[$key])  $sql .= " " . $key;
				}
			}
			$prefix = (isset($supported["DBPREFIX"]) ? $supported["DBPREFIX"] : "");
			$sql .= " INTO " . $this->QuoteIdentifier($prefix . $queryinfo[0]);

			if (isset($queryinfo["FROM"]))  $queryinfo[1] = $queryinfo["FROM"];

			if (isset($queryinfo["SELECT"]))
			{
				if (!$supported["SELECT"])  return array("success" => false, CSDB::DB_Translate("INSERT INTO SELECT not supported."), "insert_select_unsupported");

				if (isset($queryinfo[1]) && is_array($queryinfo[1]) && count($queryinfo[1]))
				{
					$keys = array();
					foreach ($queryinfo[1] as $key)  $keys[] = $this->QuoteIdentifier($key);
					$sql .= " (" . implode(", ", $keys) . ")";
				}

				$sql2 = "";
				$opts2 = array();
				$queryinfo2 = array_shift($queryinfo["SELECT"]);
				if (count($queryinfo["SELECT"]) == 1 && is_array($queryinfo["SELECT"][0]))  $queryinfo["SELECT"] = $queryinfo["SELECT"][0];
				$result = $this->GenerateSQL($master, $sql2, $opts2, "SELECT", $queryinfo2, $queryinfo["SELECT"], false);
				if (!$result["success"])  return $result;
				$sql .= " " . $sql2;
			}
			else if (isset($queryinfo[1]) && is_array($queryinfo[1]) && count($queryinfo[1]))
			{
				$keys = array();
				$vals = array();
				foreach ($queryinfo[1] as $key => $val)
				{
					$keys[] = $this->QuoteIdentifier($key);
					$vals[] = "?";
					$args[] = $val;
				}

				// Avoid this if possible.
				if (isset($queryinfo[2]))
				{
					foreach ($queryinfo[2] as $key => $val)
					{
						$keys[] = $this->QuoteIdentifier($key);
						$vals[] = $val;
					}
				}
				$sql .= " (" . implode(", ", $keys) . ") VALUES ";
				$origsql = $sql;
				$sql .= "(" . implode(", ", $vals) . ")";

				// Handle bulk inserts.
				if (isset($queryinfo[3]) && isset($queryinfo[4]))
				{
					$bulkinsert = (isset($supported["BULKINSERT"]) && $supported["BULKINSERT"]);
					$bulkinsertlimit = (isset($supported["BULKINSERTLIMIT"]) ? $supported["BULKINSERTLIMIT"] : false);
					$sql = array($sql);
					$args = array($args);
					$lastpos = 0;
					for ($x = 3; isset($queryinfo[$x]) && isset($queryinfo[$x + 1]); $x += 2)
					{
						if (!$bulkinsert || ($bulkinsertlimit !== false && count($args[$lastpos]) + count($queryinfo[$x]) + count($queryinfo[$x + 1]) >= $bulkinsertlimit))
						{
							$sql[] = $origsql;
							$args[] = array();
							$lastpos++;
						}
						else
						{
							$sql[$lastpos] .= ", ";
						}

						$vals = array();
						foreach ($queryinfo[$x] as $key => $val)
						{
							$vals[] = "?";
							$args[$lastpos][] = $val;
						}

						// Avoid this if possible.
						foreach ($queryinfo[$x + 1] as $key => $val)  $vals[] = $val;

						$sql[$lastpos] .= "(" . implode(", ", $vals) . ")";
					}
				}

				if (isset($supported["POSTVALUES"]) && !isset($queryinfo[3]))
				{
					foreach ($supported["POSTVALUES"] as $key => $mode)
					{
						if (isset($queryinfo[$key]))
						{
							if ($mode == "key_identifier" && isset($queryinfo[$key]))  $sql .= " " . $key . " " . $this->QuoteIdentifier($queryinfo[$key]);
						}
					}
				}
			}
			else  return array("success" => false, "error" => CSDB::DB_Translate("INSERT command is missing required option or parameter."), "errorcode" => "missing_option_or_parameter");

			$opts = $args;

			return array("success" => true);
		}

		protected function ProcessUPDATE(&$master, &$sql, &$opts, $queryinfo, $args, $subquery, $supported)
		{
			$master = true;

			$sql = "UPDATE";
			foreach ($supported["PRETABLE"] as $key => $mode)
			{
				if (isset($queryinfo[$key]))
				{
					if ($mode == "bool" && $queryinfo[$key])  $sql .= " " . $key;
				}
			}
			$prefix = (isset($supported["DBPREFIX"]) ? $supported["DBPREFIX"] : "");
			$sql .= " " . $this->QuoteIdentifier($prefix . $queryinfo[0]);

			$set = array();
			$vals = array();
			foreach ($queryinfo[1] as $key => $val)
			{
				$set[] = $this->QuoteIdentifier($key) . " = " . (is_bool($val) ? ($val ? "DEFAULT" : "NULL") : "?");
				if (!is_bool($val))  $vals[] = $val;
			}
			$args = array_merge($vals, $args);

			// Avoid this if possible.
			if (isset($queryinfo[2]))
			{
				foreach ($queryinfo[2] as $key => $val)
				{
					$set[] = $this->QuoteIdentifier($key) . " = " . $val;
				}
			}

			$sql .= " SET " . implode(", ", $set);

			if (isset($queryinfo["WHERE"]))
			{
				if ($supported["WHERE"]["SUBQUERIES"] && isset($queryinfo["SUBQUERIES"]))
				{
					$result = $this->ProcessSubqueries($queryinfo["WHERE"], $master, $queryinfo["SUBQUERIES"]);
					if (!$result["success"])  return $result;
				}

				$sql .= " WHERE " . $queryinfo["WHERE"];
			}
			else
			{
				// Attempt to detect accidental 'WHERE = ...' clauses.
				foreach ($queryinfo as $key => $val)
				{
					if (is_int($key) && is_string($val) && strtoupper(substr($val, 0, 5)) === "WHERE")  return array("success" => false, "error" => CSDB::DB_Translate("UPDATE command appears to have a WHERE in a value instead of a key.  Query blocked to avoid an unintentional change to the entire table.  Did you write 'WHERE something = ...' instead of 'WHERE' => 'something = ...'?"), "errorcode" => "query_blocked_where_clause");
				}
			}

			if (isset($supported["ORDER BY"]) && $supported["ORDER BY"] && isset($queryinfo["ORDER BY"]))  $sql .= " ORDER BY " . $queryinfo["ORDER BY"];
			if (isset($supported["LIMIT"]) && $supported["LIMIT"] !== false && !$subquery && isset($queryinfo["LIMIT"]))
			{
				if (is_array($queryinfo["LIMIT"]))  $queryinfo["LIMIT"] = implode($supported["LIMIT"], $queryinfo["LIMIT"]);
				$sql .= " LIMIT " . $queryinfo["LIMIT"];
			}

			$opts = $args;

			return array("success" => true);
		}

		protected function ProcessDELETE(&$master, &$sql, &$opts, $queryinfo, $args, $subquery, $supported)
		{
			$master = true;

			$sql = "DELETE";
			foreach ($supported["PREFROM"] as $key => $mode)
			{
				if (isset($queryinfo[$key]))
				{
					if ($mode == "bool" && $queryinfo[$key])  $sql .= " " . $key;
				}
			}
			$prefix = (isset($supported["DBPREFIX"]) ? $supported["DBPREFIX"] : "");
			$sql .= " FROM " . $this->QuoteIdentifier($prefix . $queryinfo[0]);

			if (isset($queryinfo["WHERE"]))
			{
				if ($supported["WHERE"]["SUBQUERIES"] && isset($queryinfo["SUBQUERIES"]))
				{
					$result = $this->ProcessSubqueries($queryinfo["WHERE"], $master, $queryinfo["SUBQUERIES"]);
					if (!$result["success"])  return $result;
				}

				$sql .= " WHERE " . $queryinfo["WHERE"];
			}
			else
			{
				// Attempt to detect accidental 'WHERE = ...' clauses.
				foreach ($queryinfo as $key => $val)
				{
					if (is_int($key) && is_string($val) && strtoupper(substr($val, 0, 5)) === "WHERE")  return array("success" => false, "error" => CSDB::DB_Translate("DELETE command appears to have a WHERE in a value instead of a key.  Query blocked to avoid an unintentional deletion of all records in the entire table.  Did you write 'WHERE something = ...' instead of 'WHERE' => 'something = ...'?"), "errorcode" => "query_blocked_where_clause");
				}
			}

			if (isset($supported["ORDER BY"]) && $supported["ORDER BY"] && isset($queryinfo["ORDER BY"]))  $sql .= " ORDER BY " . $queryinfo["ORDER BY"];
			if (isset($supported["LIMIT"]) && $supported["LIMIT"] !== false && !$subquery && isset($queryinfo["LIMIT"]))
			{
				if (is_array($queryinfo["LIMIT"]))  $queryinfo["LIMIT"] = implode($supported["LIMIT"], $queryinfo["LIMIT"]);
				$sql .= " LIMIT " . $queryinfo["LIMIT"];
			}

			$opts = $args;

			return array("success" => true);
		}

		protected function ProcessCREATE_TABLE(&$master, &$sql, &$opts, $queryinfo, $args, $subquery, $supported)
		{
			$master = true;

			if (isset($supported["TEMPORARY"]) && isset($queryinfo["TEMPORARY"]) && $queryinfo["TEMPORARY"])  $cmd = $supported["TEMPORARY"];
			else  $cmd = "CREATE TABLE";
			$prefix = (isset($supported["DBPREFIX"]) ? $supported["DBPREFIX"] : "");
			$sql = $cmd . " " . $this->QuoteIdentifier($prefix . $queryinfo[0]);

			if (isset($queryinfo["SELECT"]))
			{
				if (!isset($supported["AS_SELECT"]) || !$supported["AS_SELECT"])  return array("success" => false, CSDB::DB_Translate("CREATE TABLE AS SELECT not supported."), "create_table_select_unsupported");

				$sql2 = "";
				$opts2 = array();
				$queryinfo2 = array_shift($queryinfo["SELECT"]);
				if (count($queryinfo["SELECT"]) == 1 && is_array($queryinfo["SELECT"][0]))  $queryinfo["SELECT"] = $queryinfo["SELECT"][0];
				$result = $this->GenerateSQL($master, $sql2, $opts2, "SELECT", $queryinfo2, $queryinfo["SELECT"], false);
				if (!$result["success"])  return $result;

				if (isset($supported["PRE_AS"]))
				{
					foreach ($supported["PRE_AS"] as $key => $mode)
					{
						if (isset($queryinfo[$key]))
						{
							if ($mode == "bool" && $queryinfo[$key])  $sql .= " " . $key;
						}
					}
				}

				$sql .= " AS " . $sql2;
			}
			else
			{
				$sql2 = array();
				foreach ($queryinfo[1] as $key => $info)
				{
					$sql3 = $this->QuoteIdentifier($key);
					$result = $this->ProcessColumnDefinition($info);
					if (!$result["success"])  return $result;

					$sql2[] = $sql3 . $result["sql"];
				}

				if (isset($supported["PROCESSKEYS"]) && $supported["PROCESSKEYS"] && isset($queryinfo[2]) && is_array($queryinfo[2]))
				{
					foreach ($queryinfo[2] as $info)
					{
						$result = $this->ProcessKeyDefinition($info);
						if (!$result["success"])  return $result;

						if ($result["sql"] != "")  $sql2[] = $result["sql"];
					}
				}

				$sql .= " (\n";
				if (count($sql2))  $sql .= "\t" . implode(",\n\t", $sql2) . "\n";
				$sql .= ")";
				foreach ($supported["POSTCREATE"] as $key => $mode)
				{
					if (isset($queryinfo[$key]))
					{
						if ($mode == "bool" && $queryinfo[$key])  $sql .= " " . $key;
						else if ($mode == "string")  $sql .= " " . $key . " " . $queryinfo[$key];
					}
				}
			}

			$opts = $args;

			return array("success" => true);
		}

		protected function ProcessReferenceDefinition($info)
		{
			foreach ($info[1] as $num => $colname)  $info[1][$num] = $this->QuoteIdentifier($colname);
			$sql = $this->QuoteIdentifier($info[0]) . " (" . implode(", ", $info[1]) . ")";
			if (isset($info["MATCH FULL"]) && $info["MATCH FULL"])  $sql .= " MATCH FULL";
			else if (isset($info["MATCH PARTIAL"]) && $info["MATCH PARTIAL"])  $sql .= " MATCH PARTIAL";
			else if (isset($info["MATCH SIMPLE"]) && $info["MATCH SIMPLE"])  $sql .= " MATCH SIMPLE";
			if (isset($info["ON DELETE"]))  $sql .= " ON DELETE " . $info["ON DELETE"];
			if (isset($info["ON UPDATE"]))  $sql .= " ON UPDATE " . $info["ON UPDATE"];

			return $sql;
		}

		protected static function DB_Translate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}
	}

	class CSDB_PDO_Statement
	{
		private $db, $stmt, $filteropts;

		function __construct($db, $stmt, $filteropts)
		{
			$this->db = $db;
			$this->stmt = $stmt;
			$this->filteropts = $filteropts;
		}

		function __destruct()
		{
			$this->Free();
		}

		function Free()
		{
			if ($this->stmt === false)  return false;

			$this->stmt = false;

			return true;
		}

		function NextRow($fetchtype = PDO::FETCH_OBJ)
		{
			if ($this->stmt === false && $this->filteropts === false)  return false;

			do
			{
				$fetchnext = false;
				$result = ($this->stmt !== false ? $this->stmt->fetch($this->filteropts === false ? $fetchtype : PDO::FETCH_OBJ) : false);
				if ($this->filteropts !== false)  $this->db->RunRowFilter($result, $this->filteropts, $fetchnext);
			} while ($result !== false && $fetchnext);

			if ($result === false)  $this->Free();
			else if ($this->filteropts !== false && $fetchtype != PDO::FETCH_OBJ)
			{
				$result2 = array();
				foreach ($result as $key => $val)
				{
					if ($fetchtype == PDO::FETCH_NUM || $fetchtype == PDO::FETCH_BOTH)  $result2[] = $val;
					if ($fetchtype == PDO::FETCH_ASSOC || $fetchtype == PDO::FETCH_BOTH)  $result2[$key] = $val;
				}

				$result = $result2;
			}

			return $result;
		}
	}
?>