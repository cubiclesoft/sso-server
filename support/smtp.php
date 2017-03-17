<?php
	// CubicleSoft PHP SMTP e-mail functions.
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	// Load dependencies.
	if (!class_exists("UTF8", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/utf8.php";
	if (!class_exists("IPAddr", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/ipaddr.php";

	class SMTP
	{
		public static $dnsttlcache = array();
		private static $depths = array(), $purifier = false, $html = false;

		// Reduce dependencies.  Duplicates code though.
		private static function FilenameSafe($filename)
		{
			return preg_replace('/[_]+/', "_", preg_replace('/[^A-Za-z0-9_.\-]/', "_", $filename));
		}

		private static function ReplaceNewlines($replacewith, $data)
		{
			$data = str_replace("\r\n", "\n", $data);
			$data = str_replace("\r", "\n", $data);
			$data = str_replace("\n", $replacewith, $data);

			return $data;
		}

		// RFC1341 is a hacky workaround to allow 8-bit over 7-bit transport.
		// Also known as "Quoted Printable".
		public static function ConvertToRFC1341($data, $restrictmore = false)
		{
			$data2 = "";

			// Ranges are limited so that EBCDIC transport works.
			// Also, PHP's mail() function doesn't deal well with lines that start with '.'.
			// http://us2.php.net/manual/en/function.mail.php
			$y = strlen($data);
			for ($x = 0; $x < $y; $x++)
			{
				$currchr = ord($data[$x]);
				if ($currchr == 9 || $currchr == 32 || ($currchr >= 37 && $currchr <= 45) || ($currchr >= 47 && $currchr <= 60) || $currchr == 62 || $currchr == 63 || ($currchr >= 65 && $currchr <= 90) || $currchr == 95 || ($currchr >= 97 && $currchr <= 122))
				{
					if (!$restrictmore)  $data2 .= $data[$x];
					else if (($currchr >= 48 && $currchr <= 57) || ($currchr >= 65 && $currchr <= 90) || ($currchr >= 97 && $currchr <= 122))  $data2 .= sprintf("=%02X", $currchr);
					else  $data2 .= $data[$x];
				}
				else if ($currchr == 13 && $x + 1 < $y && ord($data[$x + 1]) == 10)
				{
					$data2 .= "\r\n";
					$x++;
				}
				else
				{
					$data2 .= sprintf("=%02X", $currchr);
				}
			}

			// Break the string on 75 character boundaries and add '=' character.
			$data2 = explode("\r\n", $data2);
			$result = "";
			foreach ($data2 as $currline)
			{
				$x2 = 0;
				$y2 = strlen($currline);
				while ($x2 + 75 < $y2)
				{
					if ($currline[$x2 + 74] == '=')
					{
						$result .= substr($currline, $x2, 74);
						$x2 += 74;
					}
					else if ($currline[$x2 + 73] == '=')
					{
						$result .= substr($currline, $x2, 73);
						$x2 += 73;
					}
					else
					{
						$result .= substr($currline, $x2, 75);
						$x2 += 75;
					}
					$result .= "=\r\n";
				}

				if ($x2 < $y2)  $result .= substr($currline, $x2, $y2 - $x2);
				$result .= "\r\n";
			}

			return $result;
		}

		public static function ConvertEmailMessageToRFC1341($data, $restrictmore = false)
		{
			$data = self::ReplaceNewlines("\r\n", $data);

			return self::ConvertToRFC1341($data, $restrictmore);
		}

		// RFC1342 is a hacky workaround to encode headers in e-mails.
		public static function ConvertToRFC1342($data, $lang = "UTF-8", $encodeb64 = true)
		{
			$result = "";

			// An individual RFC1342-compliant string can only be 75 characters long, 6 must be markers,
			// one must be the encoding method, and at least one must be data (adjusted to 4 required
			// spaces to simplify processing).
			if (strlen($lang) > 75 - 6 - 1 - 4)  return $result;

			$lang = strtoupper($lang);
			if ($lang != "ISO-8859-1" && $lang != "US-ASCII")  $encodeb64 = true;

			$maxdatalength = 75 - 6 - strlen($lang) - 1;
			if ($encodeb64)
			{
				$maxdatalength = $maxdatalength * 3 / 4;
				$y = strlen($data);
				if ($lang == "UTF-8")
				{
					$x = 0;
					$pos = 0;
					$size = 0;
					while (UTF8::NextChrPos($data, $y, $pos, $size))
					{
						if ($pos + $size - $x > $maxdatalength)
						{
							if ($x)  $result .= " ";
							$result .= "=?" . $lang . "?B?" . base64_encode(substr($data, $x, $pos - $x)) . "?=";
							$x = $pos;
						}
					}
				}
				else
				{
					for ($x = 0; $x + $maxdatalength < $y; $x += $maxdatalength)
					{
						if ($x)  $result .= " ";
						$result .= "=?" . $lang . "?B?" . base64_encode(substr($data, $x, $maxdatalength)) . "?=";
					}
				}

				if ($x < $y)
				{
					if ($x)  $result .= " ";
					$result .= "=?" . $lang . "?B?" . base64_encode(substr($data, $x, $y - $x)) . "?=";
				}
			}
			else
			{
				// Quoted printable.
				$maxdatalength = $maxdatalength / 3;
				$y = strlen($data);
				for ($x = 0; $x + $maxdatalength < $y; $x += $maxdatalength)
				{
					if ($x)  $result .= " ";
					$result .= "=?" . $lang . "?Q?" . str_replace(" ", "_", self::ConvertToRFC1341(substr($data, $x, $maxdatalength), true)) . "?=";
				}
				if ($x < $y)
				{
					if ($x)  $result .= " ";
					$result .= "=?" . $lang . "?Q?" . str_replace(" ", "_", self::ConvertToRFC1341(substr($data, $x, $y - $x), true)) . "?=";
				}
			}

			return $result;
		}

		private static function SMTP_Translate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}

		// Takes a potentially invalid e-mail address and attempts to make it valid.
		public static function MakeValidEmailAddress($email, $options = array())
		{
			$email = str_replace("\t", " ", $email);
			$email = str_replace("\r", " ", $email);
			$email = str_replace("\n", " ", $email);
			$email = trim($email);

			// Reverse parse out the initial domain/IP address part of the e-mail address.
			$domain = "";
			$state = "domend";
			$cfwsdepth = 0;
			while ($email != "" && $state != "")
			{
				$prevchr = substr($email, -2, 1);
				$lastchr = substr($email, -1);

				switch ($state)
				{
					case "domend":
					{
						if ($lastchr == ")")
						{
							$laststate = "domain";
							$state = "cfws";
						}
						else if ($lastchr == "]" || $lastchr == "}")
						{
							$domain .= "]";
							$email = trim(substr($email, 0, -1));
							$state = "ipaddr";
						}
						else
						{
							$state = "domain";
						}

						break;
					}
					case "cfws":
					{
						if ($prevchr == "\\")  $email = trim(substr($email, 0, -2));
						else if ($lastchr == ")")
						{
							$email = trim(substr($email, 0, -1));
							$depth++;
						}
						else if ($lastchr == "(")
						{
							$email = trim(substr($email, 0, -1));
							$depth--;
							if (!$depth && substr($email, -1) != ")")  $state = $laststate;
						}
						else  $email = trim(substr($email, 0, -1));

						break;
					}
					case "ipaddr":
					{
						if ($lastchr == "[" || $lastchr == "{" || $lastchr == "@")
						{
							$domain .= "[";
							$state = "@";

							if ($lastchr == "@")  break;
						}
						else if ($lastchr == "," || $lastchr == ".")  $domain .= ".";
						else if ($lastchr == ";" || $lastchr == ":")  $domain .= ":";
						else if (preg_match('/[A-Za-z0-9]/', $lastchr))  $domain .= $lastchr;

						$email = trim(substr($email, 0, -1));

						break;
					}
					case "domain":
					{
						if ($lastchr == "@")
						{
							$state = "@";

							break;
						}
						else if ($lastchr == ")")
						{
							$state = "cfws";
							$laststate = "@";

							break;
						}
						else if ($lastchr == "," || $lastchr == ".")  $domain .= ".";
						else if (preg_match('/[A-Za-z0-9-]/', $lastchr))  $domain .= $lastchr;

						$email = trim(substr($email, 0, -1));

						break;
					}
					case "@":
					{
						if ($lastchr == "@")  $state = "";

						$email = trim(substr($email, 0, -1));

						break;
					}
				}
			}
			$domain = strrev($domain);
			$parts = explode(".", $domain);
			foreach ($parts as $num => $part)  $parts[$num] = str_replace(" ", "-", trim(str_replace("-", " ", $part)));
			$domain = implode(".", $parts);

			// Forward parse out the local part of the e-mail address.
			// Remove CFWS (comments, folding whitespace).
			while (substr($email, 0, 1) == "(")
			{
				while ($email != "")
				{
					$currchr = substr($email, 0, 1);
					if ($currchr == "\\")  $email = trim(substr($email, 2));
					else if ($currchr == "(")
					{
						$depth++;
						$email = trim(substr($email, 1));
					}
					else if ($currchr == ")")
					{
						$email = trim(substr($email, 1));
						$depth--;
						if (!$depth && substr($email, 0, 1) != "(")  break;
					}
				}
			}

			// Process quoted/unquoted string.
			$local = "";
			if (substr($email, 0, 1) == "\"")
			{
				$email = substr($email, 1);
				while ($email != "")
				{
					$currchr = substr($email, 0, 1);
					$nextchr = substr($email, 1, 1);

					if ($currchr == "\\")
					{
						if ($nextchr == "\\" || $nextchr == "\"")
						{
							$local .= substr($email, 0, 2);
							$email = substr($email, 2);
						}
						else if (ord($nextchr) >= 33 && ord($nextchr) <= 126)
						{
							$local .= substr($email, 1, 1);
							$email = substr($email, 2);
						}
					}
					else if ($currchr == "\"")  break;
					else if (ord($currchr) >= 33 && ord($nextchr) <= 126)
					{
						$local .= substr($email, 0, 1);
						$email = substr($email, 1);
					}
					else  $email = substr($email, 1);
				}

				if (substr($local, -1) != "\"")  $local .= "\"";
			}
			else
			{
				while ($email != "")
				{
					$currchr = substr($email, 0, 1);

					if (preg_match("/[A-Za-z0-9]/", $currchr) || $currchr == "!" || $currchr == "#" || $currchr == "\$" || $currchr == "%" || $currchr == "&" || $currchr == "'" || $currchr == "*" || $currchr == "+" || $currchr == "-" || $currchr == "/" || $currchr == "=" || $currchr == "?" || $currchr == "^" || $currchr == "_" || $currchr == "`"  || $currchr == "{" || $currchr == "|" || $currchr == "}" || $currchr == "~" || $currchr == ".")
					{
						$local .= $currchr;
						$email = substr($email, 1);
					}
					else  break;
				}

				$local = preg_replace('/[.]+/', ".", $local);
				if (substr($local, 0, 1) == ".")  $local = substr($local, 1);
				if (substr($local, -1) == ".")  $local = substr($local, 0, -1);
			}
			while (substr($local, -2) == "\\\"")  $local = substr($local, 0, -2) . "\"";
			if ($local == "\"" || $local == "\"\"")  $local = "";

			// Analyze the domain/IP part and fix any issues.
			$domain = preg_replace('/[.]+/', ".", $domain);
			if (substr($domain, -1) == "]")
			{
				if (substr($domain, 0, 1) != "[")  $domain = "[" . $domain;

				// Process the IP address.
				if (strtolower(substr($domain, 0, 6)) == "[ipv6:")  $ipaddr = IPAddr::NormalizeIP(substr($domain, 6, -1));
				else  $ipaddr = IPAddr::NormalizeIP(substr($domain, 1, -1));

				if ($ipaddr["ipv4"] != "")  $domain = "[" . $ipaddr["ipv4"] . "]";
				else  $domain = "[IPv6:" . $ipaddr["ipv6"] . "]";
			}
			else
			{
				// Process the domain.
				if (substr($domain, 0, 1) == ".")  $domain = substr($domain, 1);
				if (substr($domain, -1) == ".")  $domain = substr($domain, 0, -1);
				$domain = explode(".", $domain);
				foreach ($domain as $num => $part)
				{
					if (substr($part, 0, 1) == "-")  $part = substr($part, 1);
					if (substr($part, -1) == "-")  $part = substr($part, 0, -1);
					if (strlen($part) > 63)  $part = substr($part, 0, 63);

					$domain[$num] = $part;
				}

				$domain = implode(".", $domain);
			}

			// Validate the final lengths.
			$y = strlen($local);
			$y2 = strlen($domain);
			$email = $local . "@" . $domain;
			if (!$y)  return array("success" => false, "error" => self::SMTP_Translate("Missing local part of e-mail address."), "errorcode" => "missing_local_part", "info" => $email);
			if (!$y2)  return array("success" => false, "error" => self::SMTP_Translate("Missing domain part of e-mail address."), "errorcode" => "missing_domain_part", "info" => $email);
			if ($y > 64 || $y2 > 253 || $y + $y2 + 1 > 253)  return array("success" => false, "error" => self::SMTP_Translate("E-mail address is too long."), "errorcode" => "email_too_long", "info" => $email);

			// Process results.
			if (substr($domain, 0, 1) == "[" && substr($domain, -1) == "]")  $result = array("success" => true, "email" => $email, "lookup" => false, "type" => "IP");
			else if (isset($options["usedns"]) && $options["usedns"] === false)  $result = array("success" => true, "email" => $email, "lookup" => false, "type" => "Domain", "domain" => $domain);
			else if ((!isset($options["usednsttlcache"]) || $options["usednsttlcache"] === true) && isset(self::$dnsttlcache[$domain]) && self::$dnsttlcache[$domain] >= time())  $result = array("success" => true, "email" => $email, "lookup" => false, "type" => "CachedDNS", "domain" => $domain);
			else
			{
				// Check for a mail server based on a DNS lookup.
				$result = self::GetDNSRecord($domain, array("MX", "A"), (isset($options["nameservers"]) ? $options["nameservers"] : array("8.8.8.8", "8.8.4.4")), (!isset($options["usednsttlcache"]) || $options["usednsttlcache"] === true));
				if ($result["success"])  $result = array("success" => true, "email" => $email, "lookup" => true, "type" => $result["type"], "domain" => $domain, "records" => $result["records"]);
			}

			return $result;
		}

		public static function UpdateDNSTTLCache()
		{
			$ts = time();
			foreach (self::$dnsttlcache as $domain => $ts2)
			{
				if ($ts2 > $ts)  unset(self::$dnsttlcache[$domain]);
			}
		}

		public static function GetDNSRecord($domain, $types = array("MX", "A"), $nameservers = array("8.8.8.8", "8.8.4.4"), $cache = true)
		{
			// Check for a mail server based on a DNS lookup.
			if (!class_exists("Net_DNS2_Resolver", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/Net/DNS2.php";

			$resolver = new Net_DNS2_Resolver(array("nameservers" => $nameservers));
			try
			{
				foreach ($types as $type)
				{
					$response = $resolver->query($domain, $type);
					if ($response && count($response->answer))
					{
						if ($cache)
						{
							$minttl = -1;
							foreach ($response->answer as $answer)
							{
								if ($minttl < 0 || ($answer->ttl > 0 && $answer->ttl < $minttl))  $minttl = $answer->ttl;
							}

							self::$dnsttlcache[$domain] = time() + $minttl;
						}

						return array("success" => true, "type" => $type, "records" => $response);
					}
				}

				return array("success" => false, "error" => self::SMTP_Translate("Invalid domain name or missing DNS record."), "errorcode" => "invalid_domain_or_missing_record", "info" => $domain);
			}
			catch (Exception $e)
			{
				return array("success" => false, "error" => self::SMTP_Translate("Invalid domain name.  Internal exception occurred."), "errorcode" => "dns_library_exception", "info" => self::SMTP_Translate("%s (%s).", $e->getMessage(), $domain));
			}
		}

		public static function EmailAddressesToNamesAndEmail(&$destnames, &$destaddrs, $emailaddrs, $removenames = false, $options = array())
		{
			$destnames = array();
			$destaddrs = array();

			$data = str_replace("\t", " ", $emailaddrs);
			$data = str_replace("\r", " ", $data);
			$data = str_replace("\n", " ", $data);
			$data = trim($data);

			// Parse e-mail addresses out of the string with a state engine.
			// Parsed in reverse because that is easier than trying to figure out if each address
			// starts with a name OR a quoted string for the local part of the e-mail address.
			// The e-mail address parsing in this state engine is intentionally incomplete.
			// The goal is to identify '"name" <emailaddr>, name <emailaddr>, emailaddr' variations.
			$found = false;
			while ($data != "")
			{
				$name = "";
				$email = "";
				$state = "addrend";
				$cfwsdepth = 0;
				$inbracket = false;

				while ($data != "" && $state != "")
				{
					$prevchr = substr($data, -2, 1);
					$lastchr = substr($data, -1);

					switch ($state)
					{
						case "addrend":
						{
							if ($lastchr == ">")
							{
								$data = trim(substr($data, 0, -1));
								$inbracket = true;
								$state = "domend";
							}
							else if ($lastchr == "," || $lastchr == ";")
							{
								$data = trim(substr($data, 0, -1));
							}
							else  $state = "domend";

							break;
						}
						case "domend":
						{
							if ($lastchr == ")")
							{
								$laststate = "domain";
								$state = "cfws";
							}
							else if ($lastchr == "]" || $lastchr == "}")
							{
								$email .= "]";
								$data = trim(substr($data, 0, -1));
								$state = "ipaddr";
							}
							else
							{
								$state = "domain";
							}

							break;
						}
						case "cfws":
						{
							if ($prevchr == "\\")  $data = trim(substr($data, 0, -2));
							else if ($lastchr == ")")
							{
								$data = trim(substr($data, 0, -1));
								$depth++;
							}
							else if ($lastchr == "(")
							{
								$data = trim(substr($data, 0, -1));
								$depth--;
								if (!$depth && substr($data, -1) != ")")  $state = $laststate;
							}
							else  $data = trim(substr($data, 0, -1));

							break;
						}
						case "ipaddr":
						{
							if ($lastchr == "[" || $lastchr == "{" || $lastchr == "@")
							{
								$email .= "[";
								$state = "@";

								if ($lastchr == "@")  break;
							}
							else if ($lastchr == "," || $lastchr == ".")  $email .= ".";
							else if ($lastchr == ";" || $lastchr == ":")  $email .= ":";
							else if (preg_match('/[A-Za-z0-9]/', $lastchr))  $email .= $lastchr;

							$data = trim(substr($data, 0, -1));

							break;
						}
						case "domain":
						{
							if ($lastchr == "@")
							{
								$state = "@";

								break;
							}
							else if ($lastchr == ")")
							{
								$state = "cfws";
								$laststate = "@";

								break;
							}
							else if ($lastchr == "," || $lastchr == ".")  $email .= ".";
							else if (preg_match('/[A-Za-z0-9-]/', $lastchr))  $email .= $lastchr;

							$data = trim(substr($data, 0, -1));

							break;
						}
						case "@":
						{
							if ($lastchr == "@")
							{
								$email .= "@";
								$state = "localend";
							}

							$data = trim(substr($data, 0, -1));

							break;
						}
						case "localend":
						{
							if ($lastchr == ")")
							{
								$state = "cfws";
								$laststate = "localend";
							}
							else if ($lastchr == "\"")
							{
								$email .= "\"";
								$data = substr($data, 0, -1);
								$state = "quotedlocal";
							}
							else  $state = "local";

							break;
						}
						case "quotedlocal":
						{
							if ($prevchr == "\\")
							{
								$email .= $lastchar . $prevchr;
								$data = substr($data, 0, -2);
							}
							else if ($lastchr == "\"")
							{
								$email .= $lastchar;
								$data = trim(substr($data, 0, -1));
								$state = "localstart";
							}
							else
							{
								$email .= $lastchar;
								$data = substr($data, 0, -1);
							}

							break;
						}
						case "local":
						{
							if (preg_match("/[A-Za-z0-9]/", $lastchr) || $lastchr == "!" || $lastchr == "#" || $lastchr == "\$" || $lastchr == "%" || $lastchr == "&" || $lastchr == "'" || $lastchr == "*" || $lastchr == "+" || $lastchr == "-" || $lastchr == "/" || $lastchr == "=" || $lastchr == "?" || $lastchr == "^" || $lastchr == "_" || $lastchr == "`"  || $lastchr == "{" || $lastchr == "|" || $lastchr == "}" || $lastchr == "~" || $lastchr == ".")
							{
								$email .= $lastchr;
								$data = substr($data, 0, -1);
							}
							else if ($lastchr == ")")
							{
								$state = "cfws";
								$laststate = "localstart";
							}
							else if ($inbracket)
							{
								if ($lastchr == "<")  $state = "localstart";
								else  $data = substr($data, 0, -1);
							}
							else if ($lastchr == " " || $lastchr == "," || $lastchr == ";")  $state = "localstart";
							else  $data = substr($data, 0, -1);

							break;
						}
						case "localstart":
						{
							if ($inbracket)
							{
								if ($lastchr == "<")  $state = "nameend";

								$data = trim(substr($data, 0, -1));
							}
							else if ($lastchr == "," || $lastchr == ";")  $state = "";
							else  $data = trim(substr($data, 0, -1));

							break;
						}
						case "nameend":
						{
							if ($lastchr == "\"")
							{
								$data = substr($data, 0, -1);
								$state = "quotedname";
							}
							else  $state = "name";

							break;
						}
						case "quotedname":
						{
							if ($prevchr == "\\")
							{
								$name .= $lastchar . $prevchr;
								$data = substr($data, 0, -2);
							}
							else if ($lastchr == "\"")
							{
								$data = trim(substr($data, 0, -1));
								$state = "";
							}
							else
							{
								$name .= $lastchr;
								$data = substr($data, 0, -1);
							}

							break;
						}
						case "name":
						{
							if ($lastchr == "," || $lastchr == ";")  $state = "";
							else
							{
								$name .= $lastchr;
								$data = substr($data, 0, -1);
							}

							break;
						}
					}
				}

				$email = self::MakeValidEmailAddress(strrev($email), $options);
				if ($email["success"])
				{
					if ($removenames)  $name = "";
					$name = trim(strrev($name));
					if (substr($name, 0, 1) == "\"")  $name = trim(substr($name, 1));
					$name = str_replace("\\\\", "\\", $name);
					$name = str_replace("\\\"", "\"", $name);

					$destnames[] = $name;
					$destaddrs[] = $email["email"];

					$found = true;
				}

				$data = trim($data);
			}

			$destnames = array_reverse($destnames);
			$destaddrs = array_reverse($destaddrs);

			return $found;
		}

		// Takes in a comma-separated list of e-mail addresses and returns appropriate e-mail headers.
		public static function EmailAddressesToEmailHeaders($emailaddrs, $headername, $multiple = true, $removenames = false, $options = array())
		{
			$result = "";

			$tempnames = array();
			$tempaddrs = array();
			self::EmailAddressesToNamesAndEmail($tempnames, $tempaddrs, $emailaddrs, $removenames, $options);

			$y = count($tempnames);
			for ($x = 0; $x < $y && ($multiple || $result == ""); $x++)
			{
				$name = $tempnames[$x];
				$emailaddr = $tempaddrs[$x];

				if ($name != "" && !UTF8::IsASCII($name))  $name = self::ConvertToRFC1342($name) . " ";
				else if ($name != "")  $name = '"' . $name . '" ';
				if ($result != "")  $result .= ",\r\n ";
				if ($name != "")  $result .= $name . '<' . $emailaddr . '>';
				else  $result .= $emailaddr;
			}

			if ($result != "" && $headername != "")  $result = $headername . ": " . $result . "\r\n";

			return $result;
		}

		public static function GetUserAgent($type)
		{
			if ($type == "Thunderbird")  return "User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:24.0) Gecko/20100101 Thunderbird/24.0\r\n";
			else if ($type == "Thunderbird2")  return "X-Mailer: Thunderbird 2.0.0.16 (Windows/20080708)\r\n";
			else if ($type == "OutlookExpress")  return "X-Mailer: Microsoft Outlook Express 6.00.2900.3198\r\nX-MimeOLE: Produced By Microsoft MimeOLE V6.00.2900.3198\r\n";
			else if ($type == "Exchange")  return "X-Mailer: Produced By Microsoft Exchange V6.0.6619.12\r\n";
			else if ($type == "OfficeOutlook")  return "X-Mailer: Microsoft Office Outlook 12.0\r\n";

			return "";
		}

		public static function GetTimeLeft($start, $limit)
		{
			if ($limit === false)  return false;

			$difftime = microtime(true) - $start;
			if ($difftime >= $limit)  return 0;

			return $limit - $difftime;
		}

		private static function ProcessRateLimit($size, $start, $limit, $async)
		{
			$difftime = microtime(true) - $start;
			if ($difftime > 0.0)
			{
				if ($size / $difftime > $limit)
				{
					// Sleeping for some amount of time will equalize the rate.
					// So, solve this for $x:  $size / ($x + $difftime) = $limit
					$amount = ($size - ($limit * $difftime)) / $limit;

					if ($async)  return microtime(true) + $amount;
					else  usleep($amount);
				}
			}

			return -1.0;
		}

		private static function StreamTimedOut($fp)
		{
			if (!function_exists("stream_get_meta_data"))  return false;

			$info = stream_get_meta_data($fp);

			return $info["timed_out"];
		}

		// Reads one or more lines in.
		private static function ProcessState__ReadLine(&$state)
		{
			while (strpos($state["data"], "\n") === false)
			{
				$data2 = @fgets($state["fp"], 116000);
				if ($data2 === false || $data2 === "")
				{
					if ($state["async"])  return array("success" => false, "error" => self::SMTP_Translate("Non-blocking read returned no data."), "errorcode" => "no_data");
					else if ($data2 === false)  return array("success" => false, "error" => self::SMTP_Translate("Underlying stream encountered a read error."), "errorcode" => "stream_read_error");
				}
				if ($data2 === false || strpos($data2, "\n") === false)
				{
					if (feof($state["fp"]))  return array("success" => false, "error" => self::SMTP_Translate("Remote peer disconnected."), "errorcode" => "peer_disconnected");
					if (self::StreamTimedOut($state["fp"]))  return array("success" => false, "error" => self::SMTP_Translate("Underlying stream timed out."), "errorcode" => "stream_timeout_exceeded");
				}
				if ($state["timeout"] !== false && self::GetTimeLeft($state["startts"], $state["timeout"]) == 0)  return array("success" => false, "error" => self::SMTP_Translate("SMTP timeout exceeded."), "errorcode" => "timeout_exceeded");

				$state["result"]["rawrecvsize"] += strlen($data2);
				$state["data"] .= $data2;

				if (isset($state["options"]["recvratelimit"]))  $state["waituntil"] = self::ProcessRateLimit($state["rawsize"], $state["recvstart"], $state["options"]["recvratelimit"], $state["async"]);

				if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("rawrecv", $data2, &$state["options"]["debug_callback_opts"]));
				else if ($state["debug"])  $state["result"]["rawrecv"] .= $data2;
			}

			return array("success" => true);
		}

		// Writes data out.
		private static function ProcessState__WriteData(&$state)
		{
			if ($state["data"] !== "")
			{
				$result = @fwrite($state["fp"], $state["data"]);
				if ($result === false || feof($state["fp"]))  return array("success" => false, "error" => self::SMTP_Translate("A fwrite() failure occurred.  Most likely cause:  Connection failure."), "errorcode" => "fwrite_failed");
				if ($state["timeout"] !== false && self::GetTimeLeft($state["startts"], $state["timeout"]) == 0)  return array("success" => false, "error" => self::SMTP_Translate("SMTP timeout exceeded."), "errorcode" => "timeout_exceeded");

				$data2 = substr($state["data"], 0, $result);
				$state["data"] = (string)substr($state["data"], $result);

				$state["result"]["rawsendsize"] += $result;

				if (isset($state["options"]["sendratelimit"]))
				{
					$state["waituntil"] = self::ProcessRateLimit($state["result"]["rawsendsize"], $state["result"]["connected"], $state["options"]["sendratelimit"], $state["async"]);
					if (microtime(true) < $state["waituntil"])  return array("success" => false, "error" => self::SMTP_Translate("Rate limit for non-blocking connection has not been reached."), "errorcode" => "no_data");
				}

				if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("rawsend", $data2, &$state["options"]["debug_callback_opts"]));
				else if ($state["debug"])  $state["result"]["rawsend"] .= $data2;
			}

			return array("success" => true);
		}

		public static function ForceClose(&$state)
		{
			if ($state["fp"] !== false)
			{
				@fclose($state["fp"]);
				$state["fp"] = false;
			}

			if (isset($state["currentfile"]) && $state["currentfile"] !== false)
			{
				if ($state["currentfile"]["fp"] !== false)  @fclose($state["currentfile"]["fp"]);
				$state["currentfile"] = false;
			}
		}

		private static function CleanupErrorState(&$state, $result)
		{
			if (!$result["success"] && $result["errorcode"] !== "no_data")
			{
				self::ForceClose($state);

				$state["error"] = $result;
			}

			return $result;
		}

		private static function InitSMTPRequest(&$state, $command, $expectedcode, $nextstate, $expectederror)
		{
			$state["data"] = $command . "\r\n";
			$state["state"] = "send_request";
			$state["expectedcode"] = $expectedcode;
			$state["nextstate"] = $nextstate;
			$state["expectederror"] = $expectederror;
		}

		public static function WantRead(&$state)
		{
			return ($state["state"] === "get_response");
		}

		public static function WantWrite(&$state)
		{
			return !self::WantRead($state);
		}

		public static function ProcessState(&$state)
		{
			if (isset($state["error"]))  return $state["error"];

			if ($state["timeout"] !== false && self::GetTimeLeft($state["startts"], $state["timeout"]) == 0)  return self::CleanupErrorState($state, array("success" => false, "error" => self::SMTP_Translate("SMTP timeout exceeded."), "errorcode" => "timeout_exceeded"));
			if (microtime(true) < $state["waituntil"])  return array("success" => false, "error" => self::SMTP_Translate("Rate limit for non-blocking connection has not been reached."), "errorcode" => "no_data");

			while ($state["state"] !== "done")
			{
				switch ($state["state"])
				{
					case "connecting":
					{
						if (function_exists("stream_select") && $state["async"])
						{
							$readfp = NULL;
							$writefp = array($state["fp"]);
							$exceptfp = NULL;
							$result = @stream_select($readfp, $writefp, $exceptfp, 0);
							if ($result === false)  return self::CleanupErrorState($state, array("success" => false, "error" => self::SMTP_Translate("A stream_select() failure occurred.  Most likely cause:  Connection failure."), "errorcode" => "stream_select_failed"));

							if (!count($writefp))  return array("success" => false, "error" => self::SMTP_Translate("Connection not established yet."), "errorcode" => "no_data");
						}

						// Handle peer certificate retrieval.
						if (function_exists("stream_context_get_options"))
						{
							$contextopts = stream_context_get_options($state["fp"]);
							if ($state["secure"] && isset($state["options"]["sslopts"]) && is_array($state["options"]["sslopts"]) && isset($contextopts["ssl"]["peer_certificate"]))
							{
								if (isset($state["options"]["debug_callback"]) && is_callable($state["options"]["debug_callback"]))  call_user_func_array($state["options"]["debug_callback"], array("peercert", @openssl_x509_parse($contextopts["ssl"]["peer_certificate"]), &$state["options"]["debug_callback_opts"]));
							}
						}

						// Deal with failed connections that hang applications.
						if (isset($state["options"]["streamtimeout"]) && $state["options"]["streamtimeout"] !== false && function_exists("stream_set_timeout"))  @stream_set_timeout($state["fp"], $state["options"]["streamtimeout"]);

						$state["result"]["connected"] = microtime(true);

						$state["data"] = "";
						$state["code"] = 0;
						$state["expectedcode"] = 220;
						$state["expectederror"] = self::SMTP_Translate("Expected a 220 response from the SMTP server upon connecting.");
						$state["response"] = "";
						$state["state"] = "get_response";
						$state["nextstate"] = "helo_ehlo";

						break;
					}
					case "send_request":
					{
						// Send the request to the server.
						$result = self::ProcessState__WriteData($state);
						if (!$result["success"])  return self::CleanupErrorState($state, $result);

						$state["code"] = 0;
						$state["response"] = "";

						// Handle QUIT differently.
						$state["state"] = ($state["nextstate"] === "done" ? "done" : "get_response");

						break;
					}
					case "get_response":
					{
						$result = self::ProcessState__ReadLine($state);
						if (!$result["success"])  return self::CleanupErrorState($state, $result);

						$currline = $state["data"];
						$state["data"] = "";
						if (strlen($currline) >= 4)
						{
							$state["response"] .= substr($currline, 4);
							$state["code"] = (int)substr($currline, 0, 3);
							if (substr($currline, 3, 1) == " ")
							{
								if ($state["expectedcode"] > 0 && $state["code"] !== $state["expectedcode"])  return self::CleanupErrorState($state, array("success" => false, "error" => $state["expectederror"], "errorcode" => "invalid_response", "info" => $state["code"] . " " . $state["response"]));

								$state["response"] = self::ReplaceNewlines("\r\n", $state["response"]);

								$state["state"] = $state["nextstate"];
							}
						}

						break;
					}
					case "helo_ehlo":
					{
						// Send EHLO or HELO depending on server support.
						$hostname = (isset($state["options"]["hostname"]) ? $state["options"]["hostname"] : "[" . trim(isset($_SERVER["SERVER_ADDR"]) && $_SERVER["SERVER_ADDR"] != "127.0.0.1" ? $_SERVER["SERVER_ADDR"] : "192.168.0.101") . "]");
						$state["size_supported"] = 0;
						if (strpos($state["response"], " ESMTP") !== false)
						{
							self::InitSMTPRequest($state, "EHLO " . $hostname, 250, "esmtp_extensions", self::SMTP_Translate("Expected a 250 response from the SMTP server upon EHLO."));
						}
						else
						{
							self::InitSMTPRequest($state, "HELO " . $hostname, 250, "mail_from", self::SMTP_Translate("Expected a 250 response from the SMTP server upon HELO."));
						}

						break;
					}
					case "esmtp_extensions":
					{
						// Process supported ESMTP extensions.
						$auth = "";
						$smtpdata = explode("\r\n", $state["response"]);
						$y = count($smtpdata);
						for ($x = 1; $x < $y; $x++)
						{
							if (strtoupper(substr($smtpdata[$x], 0, 4)) == "AUTH" && ($smtpdata[$x][4] == ' ' || $smtpdata[$x][4] == '='))  $auth = strtoupper(substr($smtpdata[$x], 5));
							if (strtoupper(substr($smtpdata[$x], 0, 4)) == "SIZE" && ($smtpdata[$x][4] == ' ' || $smtpdata[$x][4] == '='))  $state["size_supported"] = (int)substr($smtpdata[$x], 5);
						}

						$state["state"] = "mail_from";

						// Process login (if any and supported).
						if (strpos($auth, "LOGIN") !== false)
						{
							$state["username"] = (isset($state["options"]["username"]) ? (string)$state["options"]["username"] : "");
							$state["password"] = (isset($state["options"]["password"]) ? (string)$state["options"]["password"] : "");
							if ($state["username"] !== "" || $state["password"] !== "")
							{
								self::InitSMTPRequest($state, "AUTH LOGIN", 334, "auth_login_username", self::SMTP_Translate("Expected a 334 response from the SMTP server upon AUTH LOGIN."));
							}
						}

						break;
					}
					case "auth_login_username":
					{
						self::InitSMTPRequest($state, base64_encode($state["username"]), 334, "auth_login_password", self::SMTP_Translate("Expected a 334 response from the SMTP server upon AUTH LOGIN username."));

						break;
					}
					case "auth_login_password":
					{
						self::InitSMTPRequest($state, base64_encode($state["password"]), 235, "mail_from", self::SMTP_Translate("Expected a 235 response from the SMTP server upon AUTH LOGIN password."));

						break;
					}
					case "mail_from":
					{
						self::InitSMTPRequest($state, "MAIL FROM:<" . $state["fromaddrs"][0] . ">" . ($state["size_supported"] ? " SIZE=" . strlen($state["message"]) : ""), 250, "rcpt_to", self::SMTP_Translate("Expected a 250 response from the SMTP server upon MAIL FROM."));

						break;
					}
					case "rcpt_to":
					{
						$addr = array_shift($state["toaddrs"]);
						self::InitSMTPRequest($state, "RCPT TO:<" . $addr . ">", 250, (count($state["toaddrs"]) ? "rcpt_to" : "data"), self::SMTP_Translate("Expected a 250 response from the SMTP server upon RCPT TO."));

						break;
					}
					case "data":
					{
						self::InitSMTPRequest($state, "DATA", 354, "send_message", self::SMTP_Translate("Expected a 354 response from the SMTP server upon DATA."));

						break;
					}
					case "send_message":
					{
						self::InitSMTPRequest($state, $state["message"] . "\r\n.", 250, "quit", self::SMTP_Translate("Expected a 250 response from the SMTP server upon sending the e-mail."));

						break;
					}
					case "quit":
					{
						self::InitSMTPRequest($state, "QUIT", 0, "done", "");

						break;
					}
				}
			}

			$state["result"]["endts"] = microtime(true);

			fclose($state["fp"]);

			return $state["result"];
		}

		private static function SMTP_RandomHexString($length)
		{
			$lookup = "0123456789ABCDEF";
			$result = "";

			while ($length)
			{
				$result .= $lookup[mt_rand(0, 15)];

				$length--;
			}

			return $result;
		}

		private static function ProcessSSLOptions(&$options, $key, $host)
		{
			if (isset($options[$key]["auto_cainfo"]))
			{
				unset($options[$key]["auto_cainfo"]);

				$cainfo = ini_get("curl.cainfo");
				if ($cainfo !== false && strlen($cainfo) > 0)  $options[$key]["cafile"] = $cainfo;
				else if (file_exists(str_replace("\\", "/", dirname(__FILE__)) . "/cacert.pem"))  $options[$key]["cafile"] = str_replace("\\", "/", dirname(__FILE__)) . "/cacert.pem";
			}

			if (isset($options[$key]["auto_cn_match"]))
			{
				unset($options[$key]["auto_cn_match"]);

				$options[$key]["CN_match"] = $host;
			}

			if (isset($options[$key]["auto_sni"]))
			{
				unset($options[$key]["auto_sni"]);

				$options[$key]["SNI_enabled"] = true;
				$options[$key]["SNI_server_name"] = $host;
			}
		}

		// Sends an e-mail by directly connecting to a SMTP server using PHP sockets.  Much more powerful than calling mail().
		public static function SendSMTPEmail($toaddr, $fromaddr, $message, $options = array())
		{
			$startts = microtime(true);
			$timeout = (isset($options["timeout"]) ? $options["timeout"] : false);

			if (!function_exists("stream_socket_client") && !function_exists("fsockopen"))  return array("success" => false, "error" => self::SMTP_Translate("The functions 'stream_socket_client' and 'fsockopen' do not exist."), "errorcode" => "function_check");

			$temptonames = array();
			$temptoaddrs = array();
			$tempfromnames = array();
			$tempfromaddrs = array();
			if (!self::EmailAddressesToNamesAndEmail($temptonames, $temptoaddrs, $toaddr, true, $options))  return array("success" => false, "error" => self::SMTP_Translate("Invalid 'To' e-mail address(es)."), "errorcode" => "invalid_to_address", "info" => $toaddr);
			if (!self::EmailAddressesToNamesAndEmail($tempfromnames, $tempfromaddrs, $fromaddr, true, $options))  return array("success" => false, "error" => self::SMTP_Translate("Invalid 'From' e-mail address."), "errorcode" => "invalid_from_address", "info" => $fromaddr);

			$server = (isset($options["server"]) ? $options["server"] : "localhost");
			$secure = (isset($options["secure"]) ? $options["secure"] : false);
			$port = (isset($options["port"]) ? (int)$options["port"] : -1);
			if ($port < 0 || $port > 65535)  $port = ($secure ? 465 : 25);
			$debug = (isset($options["debug"]) ? $options["debug"] : false);

			$headers = "Message-ID: <" . self::SMTP_RandomHexString(8) . "." . self::SMTP_RandomHexString(7) . "@" . substr($tempfromaddrs[0], strrpos($tempfromaddrs[0], "@") + 1) . ">\r\n";
			$headers .= "Date: " . date("D, d M Y H:i:s O") . "\r\n";

			$message = $headers . $message;
			$message = self::ReplaceNewlines("\r\n", $message);
			$message = str_replace("\r\n.\r\n", "\r\n..\r\n", $message);

			// Set up the final output array.
			$result = array("success" => true, "rawsendsize" => 0, "rawrecvsize" => 0, "startts" => $startts);
			$debug = (isset($options["debug"]) && $options["debug"]);
			if ($debug)
			{
				$result["rawsend"] = "";
				$result["rawrecv"] = "";
			}

			if ($timeout !== false && self::GetTimeLeft($startts, $timeout) == 0)  return array("success" => false, "error" => self::SMTP_Translate("SMTP timeout exceeded."), "errorcode" => "timeout_exceeded");

			// Connect to the target server.
			$hostname = (isset($options["hostname"]) ? $options["hostname"] : "[" . trim(isset($_SERVER["SERVER_ADDR"]) && $_SERVER["SERVER_ADDR"] != "127.0.0.1" ? $_SERVER["SERVER_ADDR"] : "192.168.0.101") . "]");
			$errornum = 0;
			$errorstr = "";
			if (isset($options["fp"]) && is_resource($options["fp"]))
			{
				$fp = $options["fp"];
				unset($options["fp"]);
			}
			else
			{
				if (!isset($options["connecttimeout"]))  $options["connecttimeout"] = 10;
				$timeleft = self::GetTimeLeft($startts, $timeout);
				if ($timeleft !== false)  $options["connecttimeout"] = min($options["connecttimeout"], $timeleft);
				if (!function_exists("stream_socket_client"))  $fp = @fsockopen(($secure ? "tls://" : "") . $server, $port, $errornum, $errorstr, $options["connecttimeout"]);
				else
				{
					$context = @stream_context_create();
					if (isset($options["source_ip"]))  $context["socket"] = array("bindto" => $options["source_ip"] . ":0");
					if ($secure && isset($options["sslopts"]) && is_array($options["sslopts"]))
					{
						self::ProcessSSLOptions($options, "sslopts", $server);
						foreach ($options["sslopts"] as $key => $val)  @stream_context_set_option($context, "ssl", $key, $val);
					}
					$fp = @stream_socket_client(($secure ? "tls://" : "") . $server . ":" . $port, $errornum, $errorstr, $options["connecttimeout"], STREAM_CLIENT_CONNECT | (isset($options["async"]) && $options["async"] ? STREAM_CLIENT_ASYNC_CONNECT : 0), $context);
				}

				if ($fp === false)  return array("success" => false, "error" => self::SMTP_Translate("Unable to establish a SMTP connection to '%s'.", ($secure ? "tls://" : "") . $server . ":" . $port), "errorcode" => "connection_failure", "info" => $errorstr . " (" . $errornum . ")");
			}

			if (function_exists("stream_set_blocking"))  @stream_set_blocking($fp, (isset($options["async"]) && $options["async"] ? 0 : 1));

			// Initialize the connection request state array.
			$state = array(
				"fp" => $fp,
				"async" => (isset($options["async"]) ? $options["async"] : false),
				"debug" => $debug,
				"startts" => $startts,
				"timeout" => $timeout,
				"waituntil" => -1.0,
				"data" => "",
				"code" => 0,
				"expectedcode" => 0,
				"expectederror" => "",
				"response" => "",
				"fromaddrs" => $tempfromaddrs,
				"toaddrs" => $temptoaddrs,
				"message" => $message,
				"secure" => $secure,

				"state" => "connecting",

				"options" => $options,
				"result" => $result
			);

			// Return the state for async calls.  Caller must call ProcessState().
			if ($state["async"])  return array("success" => true, "state" => $state);

			// Run through all of the valid states and return the result.
			return self::ProcessState($state);
		}

		// Has to be public so that TagFilter can successfully call.
		public static function SMTP_HTMLTagFilter($stack, &$content, $open, $tagname, &$attrs, $options)
		{
			$content = str_replace(array("&nbsp;", "&#160;", "\xC2\xA0"), array(" ", " ", " "), $content);
			$content = str_replace("&amp;", "&", $content);
			$content = str_replace("&quot;", "\"", $content);

			if ($tagname === "head")  return array("keep_tag" => false, "keep_interior" => false);
			if ($tagname === "style")  return array("keep_tag" => false, "keep_interior" => false);
			if ($tagname === "script")  return array("keep_tag" => false, "keep_interior" => false);
			if ($tagname === "a" && (!isset($attrs["href"]) || trim($attrs["href"]) === ""))  return array("keep_tag" => false, "keep_interior" => false);
			if ($tagname === "/a" && $stack[0]["keep_interior"])
			{
				if ($stack[0]["attrs"]["href"] === trim($content))  $content = " [ " . trim($content) . " ] ";
				else if (trim($content) !== "")  $content = " " . trim($content) . " (" . trim($stack[0]["attrs"]["href"]) . ") ";
			}
			if ($tagname === "img")
			{
				if (!isset($attrs["src"]))  $attrs["src"] = "";

				if (isset($attrs["alt"]) && trim($attrs["alt"]) !== "" && trim($attrs["alt"]) !== $attrs["src"])  $content .= trim($attrs["alt"]) . "\n\n";
			}

			if ($tagname === "table" || $tagname === "blockquote" || $tagname === "ul")  self::$depths[] = $tagname;
			if ($tagname === "ol")  self::$depths[] = 1;

			if (trim($content) !== "")
			{
				if ($tagname === "/tr")  $content = ltrim($content) . "\n\n";
				if ($tagname === "/th")  $content = "*" . ltrim($content) . "*\n";
				if ($tagname === "/td")  $content = ltrim($content) . "\n";
				if ($tagname === "/div")  $content = ltrim($content) . "\n";
				if ($tagname === "/li")  $content = "\n" . (count(self::$depths) && is_int(self::$depths[count(self::$depths) - 1]) ? sprintf("%d. ", self::$depths[count(self::$depths) - 1]++) : "- ") . ltrim($content) . "\n";
				if ($tagname === "br")  $content .= "\n";
				if ($tagname === "/h1" || $tagname === "/h2" || $tagname === "/h3")  $content = "*" . trim($content) . "*\n\n";
				if ($tagname === "/h4" || $tagname === "/h5" || $tagname === "/h6")  $content = "*" . trim($content) . "*\n";
				if ($tagname === "/i" || $tagname === "/em")  $content = " _" . trim($content) . "_ ";
				if ($tagname === "/b" || $tagname === "/strong")  $content = " *" . trim($content) . "* ";
				if ($tagname === "/p")  $content = "\n\n" . trim($content) . "\n\n";
				if ($tagname === "/blockquote")  $content = "------------------------\n" . trim($content) . "\n------------------------\n";
				if ($tagname === "/ul" || $tagname === "/ol" || $tagname === "/table" || $tagname === "/blockquote")
				{
					// Indent the lines of content varying amounts depending on final depth.
					$prefix = "";
					if ($tagname === "/table")  $prefix .= "\xFF\xFF";
					if ($tagname === "/ul" || $tagname === "/ol")  $prefix .= "\xFF\xFF" . (count(self::$depths) > 1 ? "\xFF\xFF" : "");
					if ($tagname === "/blockquote")  $prefix .= "\xFF\xFF\xFF\xFF";

					$lines = explode("\n", $content);
					foreach ($lines as $num => $line)
					{
						if (trim($line) !== "")
						{
							if ($line{0} !== "\xFF" && (($tagname === "/ul" && $line{0} !== "-") || ($tagname === "/ol" && !(int)$line{0})))  $prefix2 = "\xFF\xFF";
							else  $prefix2 = "";

							$lines[$num] = $prefix . $prefix2 . trim($line);
						}
					}
					$content = "\n\n" . implode("\n", $lines) . "\n\n";
				}
				if ($tagname === "/pre")  $content = "\n\n" . $content . "\n\n";
			}

			if ($tagname === "/table" || $tagname === "/blockquote" || $tagname === "/ul" || $tagname === "/ol")  array_pop(self::$depths);

			return array("keep_tag" => false);
		}

		// Has to be public so that TagFilter can successfully call.
		public static function SMTP_HTMLContentFilter($stack, $result, &$content, $options)
		{
			if (TagFilter::GetParentPos($stack, "pre") === false)
			{
				$content = preg_replace('/\s{2,}/', "  ", str_replace(array("\r\n", "\n", "\r", "\t"), " ", $content));
				if ($result !== "" && substr($result, -1) === "\n")  $content = trim($content);
			}
		}

		public static function ConvertHTMLToText($data)
		{
			self::$depths = array();

			// Load TagFilter.
			if (!class_exists("TagFilter", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/tag_filter.php";

			$data = UTF8::MakeValid($data);

			$options = TagFilter::GetHTMLOptions();
			$options["tag_callback"] = "SMTP::SMTP_HTMLTagFilter";
			$options["content_callback"] = "SMTP::SMTP_HTMLContentFilter";

			$data = TagFilter::Run($data, $options);

			$data = str_replace("\xFF", " ", $data);

			$data = UTF8::MakeValid($data);

			return $data;
		}

		private static function MIME_RandomString($length)
		{
			$lookup = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
			$result = "";

			while ($length)
			{
				$result .= $lookup[mt_rand(0, 61)];

				$length--;
			}

			return $result;
		}

		// Implements the correct MultiAsyncHelper responses for SMTP.
		public static function SendEmailAsync__Handler($mode, &$data, $key, &$info)
		{
			switch ($mode)
			{
				case "init":
				{
					if ($info["init"])  $data = $info["keep"];
					else
					{
						$info["result"] = self::SendEmail($info["fromaddr"], $info["toaddr"], $info["subject"], $info["options"]);
						if (!$info["result"]["success"])
						{
							$info["keep"] = false;

							if (is_callable($info["callback"]))  call_user_func_array($info["callback"], array($key, $info["url"], $info["result"]));
						}
						else
						{
							$info["state"] = $info["result"]["state"];

							// Move to the live queue.
							$data = true;
						}
					}

					break;
				}
				case "update":
				case "read":
				case "write":
				{
					if ($info["keep"])
					{
						$info["result"] = self::ProcessState($info["state"]);
						if ($info["result"]["success"] || $info["result"]["errorcode"] !== "no_data")  $info["keep"] = false;

						if (is_callable($info["callback"]))  call_user_func_array($info["callback"], array($key, $info["url"], $info["result"]));

						if ($mode === "update")  $data = $info["keep"];
					}

					break;
				}
				case "readfps":
				{
					if ($info["state"] !== false && self::WantRead($info["state"]))  $data[$key] = $info["state"]["fp"];

					break;
				}
				case "writefps":
				{
					if ($info["state"] !== false && self::WantWrite($info["state"]))  $data[$key] = $info["state"]["fp"];

					break;
				}
				case "cleanup":
				{
					// When true, caller is removing.  Otherwise, detaching from the queue.
					if ($data === true)
					{
						if (isset($info["state"]))
						{
							if ($info["state"] !== false)  self::ForceClose($info["state"]);

							unset($info["state"]);
						}

						$info["keep"] = false;
					}

					break;
				}
			}
		}

		public static function SendEmailAsync($helper, $key, $callback, $fromaddr, $toaddr, $subject, $options = array())
		{
			$options["async"] = true;

			// DNS lookups are synchronous.  Disable this until it is possible to deal with them.
			$options["usedns"] = false;

			$info = array(
				"init" => false,
				"keep" => true,
				"callback" => $callback,
				"fromaddr" => $fromaddr,
				"toaddr" => $toaddr,
				"subject" => $subject,
				"options" => $options,
				"result" => false
			);

			$helper->Set($key, $info, array(__CLASS__, "SendEmailAsync__Handler"));

			return array("success" => true);
		}

		public static function SendEmail($fromaddr, $toaddr, $subject, $options = array())
		{
			$subject = str_replace("\r", " ", $subject);
			$subject = str_replace("\n", " ", $subject);
			if (!UTF8::IsASCII($subject))  $subject = self::ConvertToRFC1342($subject);

			$replytoaddr = (isset($options["replytoaddr"]) ? $options["replytoaddr"] : "");
			$ccaddr = (isset($options["ccaddr"]) ? $options["ccaddr"] : "");
			$bccaddr = (isset($options["bccaddr"]) ? $options["bccaddr"] : "");
			$headers = (isset($options["headers"]) ? $options["headers"] : "");
			$textmessage = (isset($options["textmessage"]) ? $options["textmessage"] : "");
			$htmlmessage = (isset($options["htmlmessage"]) ? $options["htmlmessage"] : "");
			$attachments = (isset($options["attachments"]) ? $options["attachments"] : array());

			$messagetoaddr = self::EmailAddressesToEmailHeaders($toaddr, "To", true, false, $options);
			$replytoaddr = self::EmailAddressesToEmailHeaders($replytoaddr, "Reply-To", false, false, $options);
			if ($replytoaddr == "")  $replytoaddr = self::EmailAddressesToEmailHeaders($fromaddr, "Reply-To", false, false, $options);
			$messagefromaddr = self::EmailAddressesToEmailHeaders($fromaddr, "From", false, false, $options);
			if ($messagefromaddr == "" && $replytoaddr == "")  return array("success" => false, "error" => self::SMTP_Translate("From address is invalid."), "errorcode" => "invalid_from_address", "info" => $fromaddr);
			if ($ccaddr != "")  $toaddr .= ", " . $ccaddr;
			$ccaddr = self::EmailAddressesToEmailHeaders($ccaddr, "Cc", true, false, $options);
			if ($bccaddr != "")  $toaddr .= ", " . $bccaddr;
			$bccaddr = self::EmailAddressesToEmailHeaders($bccaddr, "Bcc", true, false, $options);

			if ($htmlmessage == "" && !count($attachments))
			{
				// Plain-text e-mail.
				$destheaders = "";
				$destheaders .= $messagefromaddr;
				if ($headers != "")  $destheaders .= $headers;
				$destheaders .= "MIME-Version: 1.0\r\n";
				if (!isset($options["usemail"]) || !$options["usemail"])  $destheaders .= $messagetoaddr;
				if ($replytoaddr != "")  $destheaders .= $replytoaddr;
				if ($ccaddr != "")  $destheaders .= $ccaddr;
				if ($bccaddr != "")  $destheaders .= $bccaddr;
				if (!isset($options["usemail"]) || !$options["usemail"])  $destheaders .= "Subject: " . $subject . "\r\n";
				$destheaders .= "Content-Type: text/plain; charset=UTF-8\r\n";
				$destheaders .= "Content-Transfer-Encoding: quoted-printable\r\n";

				$message = self::ConvertEmailMessageToRFC1341($textmessage);
			}
			else
			{
				// MIME e-mail (HTML, text, attachments).
				$mimeboundary = "--------" . self::MIME_RandomString(25);
				$destheaders = "";
				$destheaders .= $messagefromaddr;
				if ($headers != "")  $destheaders .= $headers;
				$destheaders .= "MIME-Version: 1.0\r\n";
				if (!isset($options["usemail"]) || !$options["usemail"])  $destheaders .= $messagetoaddr;
				if ($replytoaddr != "")  $destheaders .= $replytoaddr;
				if ($ccaddr != "")  $destheaders .= $ccaddr;
				if ($bccaddr != "")  $destheaders .= $bccaddr;
				if (!isset($options["usemail"]) || !$options["usemail"])  $destheaders .= "Subject: " . $subject . "\r\n";
				if (count($attachments) && isset($options["inlineattachments"]) && $options["inlineattachments"])  $destheaders .= "Content-Type: multipart/related; boundary=\"" . $mimeboundary . "\"; type=\"multipart/alternative\"\r\n";
				else if (count($attachments))  $destheaders .= "Content-Type: multipart/mixed; boundary=\"" . $mimeboundary . "\"\r\n";
				else if ($textmessage != "" && $htmlmessage != "")  $destheaders .= "Content-Type: multipart/alternative; boundary=\"" . $mimeboundary . "\"\r\n";
				else  $mimeboundary = "";

				if ($mimeboundary != "")  $mimecontent = "This is a multi-part message in MIME format.\r\n";
				else  $mimecontent = "";

				if ($textmessage == "" || $htmlmessage == "" || !count($attachments))  $mimeboundary2 = $mimeboundary;
				else
				{
					$mimeboundary2 = "--------" . self::MIME_RandomString(25);
					$mimecontent .= "--" . $mimeboundary . "\r\n";
					$mimecontent .= "Content-Type: multipart/alternative; boundary=\"" . $mimeboundary2 . "\"\r\n";
					$mimecontent .= "\r\n";
				}

				if ($textmessage != "")
				{
					if ($mimeboundary2 != "")
					{
						$mimecontent .= "--" . $mimeboundary2 . "\r\n";
						$mimecontent .= "Content-Type: text/plain; charset=UTF-8\r\n";
						$mimecontent .= "Content-Transfer-Encoding: quoted-printable\r\n";
						$mimecontent .= "\r\n";
					}
					else
					{
						$destheaders .= "Content-Type: text/plain; charset=UTF-8\r\n";
						$destheaders .= "Content-Transfer-Encoding: quoted-printable\r\n";
					}
					$message = self::ConvertEmailMessageToRFC1341($textmessage);
					$mimecontent .= $message;
					$mimecontent .= "\r\n";
				}

				if ($htmlmessage != "")
				{
					if ($mimeboundary2 != "")
					{
						$mimecontent .= "--" . $mimeboundary2 . "\r\n";
						$mimecontent .= "Content-Type: text/html; charset=UTF-8\r\n";
						$mimecontent .= "Content-Transfer-Encoding: quoted-printable\r\n";
						$mimecontent .= "\r\n";
					}
					else
					{
						$destheaders .= "Content-Type: text/html; charset=UTF-8\r\n";
						$destheaders .= "Content-Transfer-Encoding: quoted-printable\r\n";
					}
					$message = self::ConvertEmailMessageToRFC1341($htmlmessage);
					$mimecontent .= $message;
					$mimecontent .= "\r\n";
				}

				if ($mimeboundary2 != "" && $mimeboundary != $mimeboundary2)  $mimecontent .= "--" . $mimeboundary2 . "--\r\n";

				// Process the attachments.
				$y = count($attachments);
				for ($x = 0; $x < $y; $x++)
				{
					$mimecontent .= "--" . $mimeboundary . "\r\n";
					$type = str_replace("\r", "", $attachments[$x]["type"]);
					$type = str_replace("\n", "", $type);
					$type = UTF8::ConvertToASCII($type);
					if (!isset($attachments[$x]["name"]))  $name = "";
					else
					{
						$name = str_replace("\r", "", $attachments[$x]["name"]);
						$name = str_replace("\n", "", $name);
						$name = self::FilenameSafe($name);
					}

					if (!isset($attachments[$x]["location"]))  $location = "";
					else
					{
						$location = str_replace("\r", "", $attachments[$x]["location"]);
						$location = str_replace("\n", "", $location);
						$location = UTF8::ConvertToASCII($location);
					}

					if (!isset($attachments[$x]["cid"]))  $cid = "";
					else
					{
						$cid = str_replace("\r", "", $attachments[$x]["cid"]);
						$cid = str_replace("\n", "", $cid);
						$cid = UTF8::ConvertToASCII($cid);
					}
					$mimecontent .= "Content-Type: " . $type . ($name != "" ? "; name=\"" . $name . "\"" : "") . "\r\n";
					if ($cid != "")  $mimecontent .= "Content-ID: <" . $cid . ">\r\n";
					if ($location != "")  $mimecontent .= "Content-Location: " . $location . "\r\n";
					$mimecontent .= "Content-Transfer-Encoding: base64\r\n";
					if ($name != "")  $mimecontent .= "Content-Disposition: inline; filename=\"" . $name . "\"\r\n";
					$mimecontent .= "\r\n";
					$mimecontent .= chunk_split(base64_encode($attachments[$x]["data"]));
					$mimecontent .= "\r\n";
				}

				if ($mimeboundary != "")  $mimecontent .= "--" . $mimeboundary . "--\r\n";
				$message = $mimecontent;
			}

			if (isset($options["returnresults"]) && $options["returnresults"])  return array("success" => true, "toaddr" => $toaddr, "fromaddr" => $fromaddr, "headers" => $destheaders, "subject" => $subject, "message" => $message);
			else if (isset($options["usemail"]) && $options["usemail"])
			{
				$result = mail($toaddr, $subject, self::ReplaceNewlines("\n", $message), $destheaders);
				if (!$result)  return array("success" => false, "error" => self::SMTP_Translate("PHP mail() call failed."), "errorcode" => "mail_call_failed");

				return array("success" => true);
			}
			else
			{
				return self::SendSMTPEmail($toaddr, $fromaddr, $destheaders . "\r\n" . $message, $options);
			}
		}
	}
?>