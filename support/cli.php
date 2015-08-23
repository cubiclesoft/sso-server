<?php
	// CubicleSoft command-line functions.
	// (C) 2013 CubicleSoft.  All Rights Reserved.

	function ParseCommandLine($options, $args = false)
	{
		if (!isset($options["shortmap"]))  $options["shortmap"] = array();
		if (!isset($options["rules"]))  $options["rules"] = array();

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
					$inside = ($inside === false ? $currchr : false);
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

		$y = count($args);
		for ($x = 0; $x < $y; $x++)
		{
			$arg = $args[$x];

			// Attempt to process an option.
			$opt = false;
			$optval = false;
			if (substr($arg, 0, 1) == "-")
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
?>