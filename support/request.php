<?php
	// CubicleSoft web request helper/processing functions.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	class Request
	{
		protected static $hostcache;

		protected static function ProcessSingleInput($data)
		{
			foreach ($data as $key => $val)
			{
				if (is_string($val))  $_REQUEST[$key] = trim($val);
				else if (is_array($val))
				{
					$_REQUEST[$key] = array();
					foreach ($val as $key2 => $val2)  $_REQUEST[$key][$key2] = (is_string($val2) ? trim($val2) : $val2);
				}
				else  $_REQUEST[$key] = $val;
			}
		}

		// Cleans up all PHP input issues so that $_REQUEST may be used as expected.
		public static function Normalize()
		{
			self::ProcessSingleInput($_COOKIE);
			self::ProcessSingleInput($_GET);
			self::ProcessSingleInput($_POST);
		}

		public static function IsSSL()
		{
			return ((isset($_SERVER["HTTPS"]) && ($_SERVER["HTTPS"] == "on" || $_SERVER["HTTPS"] == "1")) || (isset($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] == "443") || (isset($_SERVER["REQUEST_URI"]) && str_replace("\\", "/", strtolower(substr($_SERVER["REQUEST_URI"], 0, 8))) == "https://"));
		}

		// Returns 'http[s]://www.something.com[:port]' based on the current page request.
		public static function GetHost($protocol = "")
		{
			$protocol = strtolower($protocol);
			$ssl = ($protocol == "https" || ($protocol == "" && self::IsSSL()));
			if ($protocol == "")  $type = "def";
			else if ($ssl)  $type = "https";
			else  $type = "http";

			if (!isset(self::$hostcache))  self::$hostcache = array();
			if (isset(self::$hostcache[$type]))  return self::$hostcache[$type];

			$url = "http" . ($ssl ? "s" : "") . "://";

			$str = (isset($_SERVER["REQUEST_URI"]) ? str_replace("\\", "/", $_SERVER["REQUEST_URI"]) : "/");
			$pos = strpos($str, "?");
			if ($pos !== false)  $str = substr($str, 0, $pos);
			$str2 = strtolower($str);
			if (substr($str2, 0, 7) == "http://")
			{
				$pos = strpos($str, "/", 7);
				if ($pos === false)  $str = "";
				else  $str = substr($str, 7, $pos);
			}
			else if (substr($str2, 0, 8) == "https://")
			{
				$pos = strpos($str, "/", 8);
				if ($pos === false)  $str = "";
				else  $str = substr($str, 8, $pos);
			}
			else  $str = "";

			if ($str != "")  $host = $str;
			else if (isset($_SERVER["HTTP_HOST"]))  $host = $_SERVER["HTTP_HOST"];
			else  $host = $_SERVER["SERVER_NAME"] . ":" . (int)$_SERVER["SERVER_PORT"];

			$pos = strpos($host, ":");
			if ($pos === false)  $port = 0;
			else
			{
				$port = (int)substr($host, $pos + 1);
				$host = substr($host, 0, $pos);
			}
			if ($port < 1 || $port > 65535)  $port = ($ssl ? 443 : 80);
			$url .= preg_replace('/[^a-z0-9.\-]/', "", strtolower($host));
			if ($protocol == "" && ((!$ssl && $port != 80) || ($ssl && $port != 443)))  $url .= ":" . $port;
			else if ($protocol == "http" && !$ssl && $port != 80)  $url .= ":" . $port;
			else if ($protocol == "https" && $ssl && $port != 443)  $url .= ":" . $port;

			self::$hostcache[$type] = $url;

			return $url;
		}

		public static function GetURLBase()
		{
			$str = (isset($_SERVER["REQUEST_URI"]) ? str_replace("\\", "/", $_SERVER["REQUEST_URI"]) : "/");
			$pos = strpos($str, "?");
			if ($pos !== false)  $str = substr($str, 0, $pos);
			if (strncasecmp($str, "http://", 7) == 0 || strncasecmp($str, "https://", 8) == 0)
			{
				$pos = strpos($str, "/", 8);
				if ($pos === false)  $str = "/";
				else  $str = substr($str, $pos);
			}

			return $str;
		}

		public static function GetFullURLBase($protocol = "")
		{
			return self::GetHost($protocol) . self::GetURLBase();
		}

		public static function PrependHost($url, $protocol = "")
		{
			// Handle protocol-only.
			if (strncmp($url, "//", 2) == 0)
			{
				$host = self::GetHost($protocol);
				$pos = strpos($host, ":");
				if ($pos === false)  return $url;

				return substr($host, 0, $pos + 1) . $url;
			}

			if (strpos($url, ":") !== false)  return $url;

			// Handle relative paths.
			if ($url === "" || $url[0] !== "/")  return rtrim(self::GetFullURLBase($protocol), "/") . "/" . $url;

			// Handle absolute paths.
			$host = self::GetHost($protocol);

			return $host . $url;
		}
	}
?>