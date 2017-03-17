<?php
	// CubicleSoft PHP IP Address functions.
	// (C) 2016 CubicleSoft.  All Rights Reserved.

	class IPAddr
	{
		static function IsHostname($str)
		{
			$str = strtolower(str_replace(" ", "", $str));

			$pos = strpos($str, ":");
			if ($pos === false)
			{
				if (strpos($str, ".") === false || preg_match('/[^0-9.]/', $str))  return true;
			}
			else
			{
				$pos2 = strrpos($str, ":");
				if ($pos2 !== false && $pos === $pos2)  return true;

				if (preg_match('/[^0-9a-f:.]/', $str))  return true;
			}

			return false;
		}

		static function NormalizeIP($ipaddr)
		{
			$ipv4addr = "";
			$ipv6addr = "";

			// Generate IPv6 address.
			$ipaddr = strtolower(trim($ipaddr));
			if (strpos($ipaddr, ":") === false)  $ipaddr = "::ffff:" . $ipaddr;
			$ipaddr = explode(":", $ipaddr);
			if (count($ipaddr) < 3)  $ipaddr = array("", "", "0");
			$ipaddr2 = array();
			$foundpos = false;
			foreach ($ipaddr as $num => $segment)
			{
				$segment = trim($segment);
				if ($segment != "")  $ipaddr2[] = $segment;
				else if ($foundpos === false && count($ipaddr) > $num + 1 && $ipaddr[$num + 1] != "")
				{
					$foundpos = count($ipaddr2);
					$ipaddr2[] = "0000";
				}
			}
			// Convert ::ffff:123.123.123.123 format.
			if (strpos($ipaddr2[count($ipaddr2) - 1], ".") !== false)
			{
				$x = count($ipaddr2) - 1;
				if ($ipaddr2[count($ipaddr2) - 2] != "ffff")  $ipaddr2[$x] = "0";
				else
				{
					$ipaddr = explode(".", $ipaddr2[$x]);
					if (count($ipaddr) != 4)  $ipaddr2[$x] = "0";
					else
					{
						$ipaddr2[$x] = str_pad(strtolower(dechex($ipaddr[0])), 2, "0", STR_PAD_LEFT) . str_pad(strtolower(dechex($ipaddr[1])), 2, "0", STR_PAD_LEFT);
						$ipaddr2[] = str_pad(strtolower(dechex($ipaddr[2])), 2, "0", STR_PAD_LEFT) . str_pad(strtolower(dechex($ipaddr[3])), 2, "0", STR_PAD_LEFT);
					}
				}
			}
			$ipaddr = array_slice($ipaddr2, 0, 8);
			if ($foundpos !== false && count($ipaddr) < 8)  array_splice($ipaddr, $foundpos, 0, array_fill(0, 8 - count($ipaddr), "0000"));
			foreach ($ipaddr as $num => $segment)
			{
				$ipaddr[$num] = substr(str_pad(strtolower(dechex(hexdec($segment))), 4, "0", STR_PAD_LEFT), -4);
			}
			$ipv6addr = implode(":", $ipaddr);

			// Extract IPv4 address.
			if (substr($ipv6addr, 0, 30) == "0000:0000:0000:0000:0000:ffff:")  $ipv4addr = hexdec(substr($ipv6addr, 30, 2)) . "." . hexdec(substr($ipv6addr, 32, 2)) . "." . hexdec(substr($ipv6addr, 35, 2)) . "." . hexdec(substr($ipv6addr, 37, 2));

			// Make a short IPv6 address.
			$shortipv6 = $ipv6addr;
			$pattern = "0000:0000:0000:0000:0000:0000:0000";
			do
			{
				$shortipv6 = str_replace($pattern, ":", $shortipv6);
				$pattern = substr($pattern, 5);
			} while (strlen($shortipv6) == 39 && $pattern != "");
			$shortipv6 = explode(":", $shortipv6);
			foreach ($shortipv6 as $num => $segment)
			{
				if ($segment != "")  $shortipv6[$num] = strtolower(dechex(hexdec($segment)));
			}
			$shortipv6 = implode(":", $shortipv6);

			return array("ipv6" => $ipv6addr, "shortipv6" => $shortipv6, "ipv4" => $ipv4addr);
		}

		static function GetRemoteIP($proxies = array())
		{
			$ipaddr = self::NormalizeIP(isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "127.0.0.1");

			// Check for trusted proxies.  Stop at first untrusted IP in the chain.
			if (isset($proxies[$ipaddr["ipv6"]]) || ($ipaddr["ipv4"] != "" && isset($proxies[$ipaddr["ipv4"]])))
			{
				$xforward = (isset($_SERVER["HTTP_X_FORWARDED_FOR"]) ? explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"]) : array());
				$clientip = (isset($_SERVER["HTTP_CLIENT_IP"]) ? explode(",", $_SERVER["HTTP_CLIENT_IP"]) : array());

				do
				{
					$found = false;

					if (isset($proxies[$ipaddr["ipv6"]]))  $header = $proxies[$ipaddr["ipv6"]];
					else  $header = $proxies[$ipaddr["ipv4"]];

					$header = strtolower($header);
					if ($header == "xforward" && count($xforward) > 0)
					{
						$ipaddr = self::NormalizeIP(array_pop($xforward));
						$found = true;
					}
					else if ($header == "clientip" && count($clientip) > 0)
					{
						$ipaddr = self::NormalizeIP(array_pop($clientip));
						$found = true;
					}
				} while ($found && (isset($proxies[$ipaddr["ipv6"]]) || ($ipaddr["ipv4"] != "" && isset($proxies[$ipaddr["ipv4"]]))));
			}

			return $ipaddr;
		}

		static function IsMatch($pattern, $ipaddr)
		{
			if (is_string($ipaddr))  $ipaddr = self::NormalizeIP($ipaddr);

			if (strpos($pattern, ":") !== false)
			{
				// Pattern is IPv6.
				$pattern = explode(":", strtolower($pattern));
				$ipaddr = explode(":", $ipaddr["ipv6"]);
				if (count($pattern) != 8 || count($ipaddr) != 8)  return false;
				foreach ($pattern as $num => $segment)
				{
					$found = false;
					$pieces = explode(",", $segment);
					foreach ($pieces as $piece)
					{
						$piece = trim($piece);
						$piece = explode(".", $piece);
						if (count($piece) == 1)
						{
							$piece = $piece[0];

							if ($piece == "*")  $found = true;
							else if (strpos($piece, "-") !== false)
							{
								$range = explode("-", $piece);
								$range[0] = hexdec($range[0]);
								$range[1] = hexdec($range[1]);
								$val = hexdec($ipaddr[$num]);
								if ($range[0] > $range[1])  $range[0] = $range[1];
								if ($val >= $range[0] && $val <= $range[1])  $found = true;
							}
							else if ($piece === $ipaddr[$num])  $found = true;
						}
						else if (count($piece) == 2)
						{
							// Special IPv4-like notation.
							$found2 = false;
							$found3 = false;
							$val = hexdec(substr($ipaddr[$num], 0, 2));
							$val2 = hexdec(substr($ipaddr[$num], 2, 2));

							if ($piece[0] == "*")  $found2 = true;
							else if (strpos($piece[0], "-") !== false)
							{
								$range = explode("-", $piece[0]);
								if ($range[0] > $range[1])  $range[0] = $range[1];
								if ($val >= $range[0] && $val <= $range[1])  $found2 = true;
							}
							else if ($piece[0] == $val)  $found2 = true;

							if ($piece[1] == "*")  $found3 = true;
							else if (strpos($piece[1], "-") !== false)
							{
								$range = explode("-", $piece[1]);
								if ($range[0] > $range[1])  $range[0] = $range[1];
								if ($val >= $range[0] && $val <= $range[1])  $found3 = true;
							}
							else if ($piece[1] == $val2)  $found3 = true;

							if ($found2 && $found3)  $found = true;
						}

						if ($found)  break;
					}

					if (!$found)  return false;
				}
			}
			else
			{
				// Pattern is IPv4.
				$pattern = explode(".", strtolower($pattern));
				$ipaddr = explode(".", $ipaddr["ipv4"]);
				if (count($pattern) != 4 || count($ipaddr) != 4)  return false;
				foreach ($pattern as $num => $segment)
				{
					$found = false;
					$pieces = explode(",", $segment);
					foreach ($pieces as $piece)
					{
						$piece = trim($piece);

						if ($piece == "*")  $found = true;
						else if (strpos($piece, "-") !== false)
						{
							$range = explode("-", $piece);
							if ($range[0] > $range[1])  $range[0] = $range[1];
							if ($ipaddr[$num] >= $range[0] && $ipaddr[$num] <= $range[1])  $found = true;
						}
						else if ($piece == $ipaddr[$num])  $found = true;

						if ($found)  break;
					}

					if (!$found)  return false;
				}
			}

			return true;
		}
	}
?>