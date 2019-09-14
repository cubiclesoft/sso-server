<?php
	// CubicleSoft command-line functions.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	class CLI
	{
		public static function ParseCommandLine($options, $args = false)
		{
			if (!isset($options["shortmap"]))  $options["shortmap"] = array();
			if (!isset($options["rules"]))  $options["rules"] = array();
			if (!isset($options["allow_opts_after_param"]))  $options["allow_opts_after_param"] = true;

			// Clean up shortmap and rules.
			foreach ($options["shortmap"] as $key => $val)
			{
				if (!isset($options["rules"][$val]))  unset($options["shortmap"][$key]);
			}
			foreach ($options["rules"] as $key => $val)
			{
				if (!isset($val["arg"]))  $options["rules"][$key]["arg"] = false;
				if (!isset($val["multiple"]))  $options["rules"][$key]["multiple"] = false;
			}

			if ($args === false)  $args = $_SERVER["argv"];
			else if (is_string($args))
			{
				$args2 = $args;
				$args = array();
				$inside = false;
				$currarg = "";
				$y = strlen($args2);
				for ($x = 0; $x < $y; $x++)
				{
					$currchr = substr($args2, $x, 1);

					if ($inside === false && $currchr == " " && $currarg != "")
					{
						$args[] = $currarg;
						$currarg = "";
					}
					else if ($currchr == "\"" || $currchr == "'")
					{
						if ($inside === false)  $inside = $currchr;
						else if ($inside === $currchr)  $inside = false;
						else  $currarg .= $currchr;
					}
					else if ($currchr == "\\" && $x < $y - 1)
					{
						$x++;
						$currarg .= substr($args2, $x, 1);
					}
					else if ($inside !== false || $currchr != " ")
					{
						$currarg .= $currchr;
					}
				}

				if ($currarg != "")  $args[] = $currarg;
			}

			$result = array("success" => true, "file" => array_shift($args), "opts" => array(), "params" => array());

			// Look over shortmap to determine if options exist that are one byte (flags) and don't have arguments.
			$chrs = array();
			foreach ($options["shortmap"] as $key => $val)
			{
				if (isset($options["rules"][$val]) && !$options["rules"][$val]["arg"])  $chrs[$key] = true;
			}

			$allowopt = true;
			$y = count($args);
			for ($x = 0; $x < $y; $x++)
			{
				$arg = $args[$x];

				// Attempt to process an option.
				$opt = false;
				$optval = false;
				if ($allowopt && substr($arg, 0, 1) == "-")
				{
					$pos = strpos($arg, "=");
					if ($pos === false)  $pos = strlen($arg);
					else  $optval = substr($arg, $pos + 1);
					$arg2 = substr($arg, 1, $pos - 1);

					if (isset($options["rules"][$arg2]))  $opt = $arg2;
					else if (isset($options["shortmap"][$arg2]))  $opt = $options["shortmap"][$arg2];
					else if ($x == 0)
					{
						// Attempt to process as a set of flags.
						$y2 = strlen($arg2);
						if ($y2 > 0)
						{
							for ($x2 = 0; $x2 < $y2; $x2++)
							{
								$currchr = substr($arg2, $x2, 1);

								if (!isset($chrs[$currchr]))  break;
							}

							if ($x2 == $y2)
							{
								for ($x2 = 0; $x2 < $y2; $x2++)
								{
									$opt = $options["shortmap"][substr($arg2, $x2, 1)];

									if (!$options["rules"][$opt]["multiple"])  $result["opts"][$opt] = true;
									else
									{
										if (!isset($result["opts"][$opt]))  $result["opts"][$opt] = 0;
										$result["opts"][$opt]++;
									}
								}

								continue;
							}
						}
					}
				}

				if ($opt === false)
				{
					// Is a parameter.
					if (substr($arg, 0, 1) === "\"" || substr($arg, 0, 1) === "'")  $arg = substr($arg, 1);
					if (substr($arg, -1) === "\"" || substr($arg, -1) === "'")  $arg = substr($arg, 0, -1);

					$result["params"][] = $arg;

					if (!$options["allow_opts_after_param"])  $allowopt = false;
				}
				else if (!$options["rules"][$opt]["arg"])
				{
					// Is a flag by itself.
					if (!$options["rules"][$opt]["multiple"])  $result["opts"][$opt] = true;
					else
					{
						if (!isset($result["opts"][$opt]))  $result["opts"][$opt] = 0;
						$result["opts"][$opt]++;
					}
				}
				else
				{
					// Is an option.
					if ($optval === false)
					{
						$x++;
						if ($x == $y)  break;
						$optval = $args[$x];
					}

					if (substr($optval, 0, 1) === "\"" || substr($optval, 0, 1) === "'")  $optval = substr($optval, 1);
					if (substr($optval, -1) === "\"" || substr($optval, -1) === "'")  $optval = substr($optval, 0, -1);

					if (!$options["rules"][$opt]["multiple"])  $result["opts"][$opt] = $optval;
					else
					{
						if (!isset($result["opts"][$opt]))  $result["opts"][$opt] = array();
						$result["opts"][$opt][] = $optval;
					}
				}
			}

			return $result;
		}

		public static function CanGetUserInputWithArgs(&$args, $prefix)
		{
			return (($prefix !== false && isset($args["opts"][$prefix]) && is_array($args["opts"][$prefix]) && count($args["opts"][$prefix])) || count($args["params"]));
		}

		// Gets a line of input from the user.  If the user supplies all information via the command-line, this could be entirely automated.
		public static function GetUserInputWithArgs(&$args, $prefix, $question, $default, $noparamsoutput = "", $suppressoutput = false, $callback = false, $callbackopts = false)
		{
			if (!self::CanGetUserInputWithArgs($args, $prefix) && $noparamsoutput != "")
			{
				echo "\n" . rtrim($noparamsoutput) . "\n\n";

				$suppressoutput = false;
				$noparamsoutput = "";
			}

			do
			{
				$prompt = ($suppressoutput ? "" : $question . ($default !== false ? " [" . $default . "]" : "") . ":  ");

				if ($prefix !== false && isset($args["opts"][$prefix]) && is_array($args["opts"][$prefix]) && count($args["opts"][$prefix]))
				{
					$line = array_shift($args["opts"][$prefix]);
					if ($line === "")  $line = $default;
					if (!$suppressoutput)  echo $prompt . $line . "\n";
				}
				else if (count($args["params"]))
				{
					$line = array_shift($args["params"]);
					if ($line === "")  $line = $default;
					if (!$suppressoutput)  echo $prompt . $line . "\n";
				}
				else if (strtoupper(substr(php_uname("s"), 0, 3)) != "WIN" && function_exists("readline") && function_exists("readline_add_history"))
				{
					$line = readline($prompt);
					if ($line === false)  exit();

					$line = trim($line);
					if ($line === "")  $line = $default;
					if ($line !== false && $line !== "")  readline_add_history($line);
				}
				else
				{
					echo $prompt;
					fflush(STDOUT);
					$line = fgets(STDIN);
					if ($line === false || ($line === "" && feof(STDIN)))  exit();

					$line = trim($line);
					if ($line === "")  $line = $default;
				}

				if ($line === false || (is_callable($callback) && !call_user_func_array($callback, array($line, &$callbackopts))))
				{
					if ($line !== false)  $line = false;
					else  echo "Please enter a value.\n";

					if (!self::CanGetUserInputWithArgs($args, $prefix) && $noparamsoutput != "")
					{
						echo "\n" . $noparamsoutput . "\n";

						$noparamsoutput = "";
					}

					$suppressoutput = false;
				}
			} while ($line === false);

			return $line;
		}

		// Obtains a valid line of input.
		public static function GetLimitedUserInputWithArgs(&$args, $prefix, $question, $default, $allowedoptionsprefix, $allowedoptions, $loop = true, $suppressoutput = false, $multipleuntil = false)
		{
			$noparamsoutput = $allowedoptionsprefix . "\n\n";
			$size = 0;
			foreach ($allowedoptions as $key => $val)
			{
				if ($size < strlen($key))  $size = strlen($key);
			}

			foreach ($allowedoptions as $key => $val)
			{
				$newtab = str_repeat(" ", 2 + $size + 3);
				$noparamsoutput .= "  " . $key . ":" . str_repeat(" ", $size - strlen($key)) . "  " . str_replace("\n\t", "\n" . $newtab, $val) . "\n";
			}

			$noparamsoutput .= "\n";

			if ($default === false && count($allowedoptions) == 1)
			{
				reset($allowedoptions);
				$default = key($allowedoptions);
			}

			$results = array();
			do
			{
				$displayed = (!count($args["params"]));
				$result = self::GetUserInputWithArgs($args, $prefix, $question, $default, $noparamsoutput, $suppressoutput);
				if (is_array($multipleuntil) && $multipleuntil["exit"] === $result)  break;
				$result2 = false;
				if (!count($allowedoptions))  break;
				foreach ($allowedoptions as $key => $val)
				{
					if (!strcasecmp($key, $result) || !strcasecmp($val, $result))  $result2 = $key;
				}
				if ($loop)
				{
					if ($result2 === false)
					{
						echo "Please select an option from the list.\n";

						$suppressoutput = false;
					}
					else if (is_array($multipleuntil))
					{
						$results[$result2] = $result2;

						$question = $multipleuntil["nextquestion"];
						$default = $multipleuntil["nextdefault"];
					}
				}

				if ($displayed)  $noparamsoutput = "";
			} while ($loop && ($result2 === false || is_array($multipleuntil)));

			return (is_array($multipleuntil) ? $results : $result2);
		}

		// Obtains Yes/No style input.
		public static function GetYesNoUserInputWithArgs(&$args, $prefix, $question, $default, $noparamsoutput = "", $suppressoutput = false)
		{
			$default = (substr(strtoupper(trim($default)), 0, 1) === "Y" ? "Y" : "N");

			$result = self::GetUserInputWithArgs($args, $prefix, $question, $default, $noparamsoutput, $suppressoutput);
			$result = (substr(strtoupper(trim($result)), 0, 1) === "Y");

			return $result;
		}

		public static function GetHexDump($data)
		{
			$result = "";

			$x = 0;
			$y = strlen($data);
			if ($y <= 256)  $padwidth = 2;
			else if ($y <= 65536)  $padwidth = 4;
			else if ($y <= 16777216)  $padwidth = 6;
			else  $padwidth = 8;

			$pad = str_repeat(" ", $padwidth);

			$data2 = str_split(strtoupper(bin2hex($data)), 32);
			foreach ($data2 as $line)
			{
				$result .= sprintf("%0" . $padwidth . "X", $x) . " | ";

				$line = str_split($line, 2);
				array_splice($line, 8, 0, "");
				$result .= implode(" ", $line) . "\n";

				$result .= $pad . " |";
				$y2 = $x + 16;
				for ($x2 = 0; $x2 < 16 && $x < $y; $x2++)
				{
					$result .= " ";
					if ($x2 === 8)  $result .= " ";

					$tempchr = ord($data{$x});
					if ($tempchr === 0x09)  $result .= "\\t";
					else if ($tempchr === 0x0D)  $result .= "\\r";
					else if ($tempchr === 0x0A)  $result .= "\\n";
					else if ($tempchr === 0x00)  $result .= "\\0";
					else if ($tempchr < 32 || $tempchr > 126)  $result .= "  ";
					else  $result .= " " . $data{$x};

					$x++;
				}

				$result .= "\n";
			}

			return $result;
		}

		// Outputs a JSON array (useful for captured output).
		public static function DisplayResult($result, $exit = true)
		{
			if (is_array($result))  echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
			else  echo $result . "\n";

			if ($exit)  exit();
		}

		// Useful for reparsing remaining parameters as new arguments.
		public static function ReinitArgs(&$args, $newargs)
		{
			// Process the parameters.
			$options = array(
				"shortmap" => array(
					"?" => "help"
				),
				"rules" => array(
				)
			);

			foreach ($newargs as $arg)  $options["rules"][$arg] = array("arg" => true, "multiple" => true);
			$options["rules"]["help"] = array("arg" => false);

			$args = self::ParseCommandLine($options, array_merge(array(""), $args["params"]));

			if (isset($args["opts"]["help"]))  self::DisplayResult(array("success" => true, "options" => array_keys($options["rules"])));
		}

		// Tracks messages for a command-line interface app.
		private static $messages = array();

		public static function LogMessage($msg, $data = null)
		{
			if (isset($data))  $msg .= "\n\t" . trim(str_replace("\n", "\n\t", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));

			self::$messages[] = $msg;

			fwrite(STDERR, $msg . "\n");
		}

		public static function DisplayError($msg, $result = false, $exit = true)
		{
			self::LogMessage(($exit ? "[Error] " : "") . $msg);

			if ($result !== false && is_array($result) && isset($result["error"]) && isset($result["errorcode"]))  self::LogMessage("[Error] " . $result["error"] . " (" . $result["errorcode"] . ")", (isset($result["info"]) ? $result["info"] : null));

			if ($exit)  exit();
		}

		public static function GetLogMessages($filters = array())
		{
			if (is_string($filters))  $filters = array($filters);

			$result = array();
			foreach (self::$messages as $message)
			{
				$found = (!count($filters));
				foreach ($filters as $filter)
				{
					if (preg_match($filter, $message))  $found = true;
				}

				if ($found)  $result[] = $message;
			}

			return $result;
		}

		public static function ResetLogMessages()
		{
			self::$messages = array();
		}


		private static $timerinfo = array();

		public static function StartTimer()
		{
			$ts = microtime(true);

			self::$timerinfo = array(
				"start" => $ts,
				"diff" => $ts
			);
		}

		public static function UpdateTimer()
		{
			$ts = microtime(true);
			$diff = $ts - self::$timerinfo["diff"];
			self::$timerinfo["diff"] = $ts;

			$result = array(
				"success" => true,
				"diff" => sprintf("%.2f", $diff),
				"total" => sprintf("%.2f", $ts - self::$timerinfo["start"])
			);

			return $result;
		}
	}
?>