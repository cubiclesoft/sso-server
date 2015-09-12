<?php
	// SSO server support functions.
	// (C) 2015 CubicleSoft.  All Rights Reserved.

	class SSO_ProviderBase
	{
		public function Init()
		{
		}

		public function DisplayName()
		{
			return "";
		}

		public function DefaultOrder()
		{
			return 0;
		}

		public function MenuOpts()
		{
		}

		public function Config()
		{
		}

		public function IsEnabled()
		{
			return false;
		}

		public function GetProtectedFields()
		{
			return array();
		}

		public function FindUsers()
		{
		}

		public function GetEditUserLinks($id)
		{
			return array();
		}

		public function AddIPCacheInfo()
		{
		}

		public function GenerateSelector()
		{
		}

		public function ProcessFrontend()
		{
		}
	}

	function SSO_IsSSLRequest()
	{
		return ((isset($_SERVER["HTTPS"]) && ($_SERVER["HTTPS"] == "on" || $_SERVER["HTTPS"] == "1")) || (isset($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] == "443") || (str_replace("\\", "/", strtolower(substr($_SERVER["REQUEST_URI"], 0, 8))) == "https://"));
	}

	function SSO_GetRemoteIP()
	{
		require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/ipaddr.php";

		$proxies = array();
		$ipaddrs = explode(";", SSO_PROXY_X_FORWARDED_FOR);
		foreach ($ipaddrs as $ipaddr)
		{
			$ipaddr = trim($ipaddr);
			if ($ipaddr != "")  $proxies[$ipaddr] = "xforward";
		}
		$ipaddrs = explode(";", SSO_PROXY_CLIENT_IP);
		foreach ($ipaddrs as $ipaddr)
		{
			$ipaddr = trim($ipaddr);
			if ($ipaddr != "")  $proxies[$ipaddr] = "clientip";
		}

		return IPAddr::GetRemoteIP($proxies);
	}

	function SSO_GetSupportedDatabases()
	{
		$result = array(
			"mysql" => array("production" => true, "login" => true, "replication" => true, "default_dsn" => "host=localhost"),
			"pgsql" => array("production" => true, "login" => true, "replication" => true, "default_dsn" => "host=localhost"),
			"oci" => array("production" => true, "login" => true, "replication" => true, "default_dsn" => "dbname=//localhost/ORCL"),
			"sqlite" => array("production" => false, "login" => false, "replication" => false, "default_dsn" => "@PATH@/sqlite_@RANDOM@.db")
		);

		return $result;
	}

	function SSO_DBConnect($full)
	{
		global $sso_db, $sso_db_apikeys, $sso_db_fields, $sso_db_users, $sso_db_user_tags, $sso_db_user_sessions, $sso_db_temp_sessions, $sso_db_tags, $sso_db_ipcache;

		require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/utf8.php";

		// Update legacy database connections (SSO 3.1 and earlier).
		if (!defined("SSO_DB_TYPE"))
		{
			define("SSO_DB_TYPE", "mysql");
			$server = explode(":", SSO_MYSQL_SERVER);
			define("SSO_DB_DSN", "host=" . $server[0] . (count($server) > 1 ? ";port=" . $server[1] : ""));
			define("SSO_DB_USER", SSO_MYSQL_USER);
			define("SSO_DB_PASS", SSO_MYSQL_PASS);
			if (SSO_MYSQL_MASTER_SERVER == "")  define("SSO_DB_MASTER_DSN", "");
			else
			{
				$server = explode(":", SSO_MYSQL_MASTER_SERVER);
				define("SSO_DB_MASTER_DSN", "host=" . $server[0] . (count($server) > 1 ? ";port=" . $server[1] : ""));
			}
			define("SSO_DB_MASTER_USER", SSO_MYSQL_MASTER_USER);
			define("SSO_DB_MASTER_PASS", SSO_MYSQL_MASTER_PASS);
			define("SSO_DB_NAME", SSO_MYSQL_DB);
			define("SSO_DB_PREFIX", SSO_MYSQL_PREFIX);
		}

		// Connect to the database.
		require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/csdb/db.php";
		require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/csdb/db_" . SSO_DB_TYPE . ($full ? "" : "_lite") . ".php";

		$dbclassname = "CSDB_" . SSO_DB_TYPE . ($full ? "" : "_lite");
		$databases = SSO_GetSupportedDatabases();

		$sso_db = new $dbclassname();
		$sso_db->Connect(SSO_DB_TYPE . ":" . SSO_DB_DSN, ($databases[SSO_DB_TYPE]["login"] ? SSO_DB_USER : false), ($databases[SSO_DB_TYPE]["login"] ? SSO_DB_PASS : false));
		if ($databases[SSO_DB_TYPE]["replication"] && SSO_DB_MASTER_DSN != "")  $sso_db->SetMaster(SSO_DB_TYPE . ":" . SSO_DB_MASTER_DSN, ($databases[SSO_DB_TYPE]["login"] ? SSO_DB_MASTER_USER : false), ($databases[SSO_DB_TYPE]["login"] ? SSO_DB_MASTER_PASS : false));

		$sso_db->Query("USE", SSO_DB_NAME);

		$sso_db_apikeys = SSO_DB_PREFIX . "apikeys";
		$sso_db_fields = SSO_DB_PREFIX . "fields";
		$sso_db_users = SSO_DB_PREFIX . "users";
		$sso_db_user_tags = SSO_DB_PREFIX . "user_tags";
		$sso_db_user_sessions = SSO_DB_PREFIX . "user_sessions";
		$sso_db_temp_sessions = SSO_DB_PREFIX . "temp_sessions";
		$sso_db_tags = SSO_DB_PREFIX . "tags";
		$sso_db_ipcache = SSO_DB_PREFIX . "ipcache";
	}

	function SSO_LoadFields($loadselect)
	{
		global $sso_fields, $sso_select_fields, $sso_db, $sso_db_fields;

		$sso_fields = array();
		if ($loadselect)  $sso_select_fields = array("" => "");
		try
		{
			$result = $sso_db->Query("SELECT", array(
				array("field_name", "field_desc", "encrypted"),
				"FROM" => "?",
				"WHERE" => "enabled = 1",
				"ORDER BY" => "field_name"
			), $sso_db_fields);
			while ($row = $result->NextRow())
			{
				$sso_fields[$row->field_name] = $row->encrypted;
				if ($loadselect)  $sso_select_fields[$row->field_name] = $row->field_name . " - " . $row->field_desc;
			}
		}
		catch (Exception $e)
		{
			return false;
		}

		return true;
	}

	function SSO_LoadFieldSearchOrder()
	{
		global $sso_fields, $sso_settings;

		if (!isset($sso_settings[""]["search_order"]))
		{
			$sso_settings[""]["search_order"] = array(
				"id" => false,
				"provider_name" => false,
				"provider_id" => false,
				"version" => false,
				"lastipaddr" => false,
				"lastactivated" => true,
				"tag_id" => false,
			);
		}

		foreach ($sso_fields as $key => $encrypted)
		{
			if (!isset($sso_settings[""]["search_order"]["field_" . $key]))  $sso_settings[""]["search_order"]["field_" . $key] = true;
		}

		return true;
	}

	function SSO_GenerateNamespaceKeys()
	{
		global $sso_settings, $sso_rng;

		$sso_settings[""]["namespacekey"] = $sso_rng->GenerateToken(56);
		$sso_settings[""]["namespaceiv"] = $sso_rng->GenerateToken(8);
		$sso_settings[""]["namespacekey2"] = $sso_rng->GenerateToken(56);
		$sso_settings[""]["namespaceiv2"] = $sso_rng->GenerateToken(8);

		$sso_settings[""]["namespacekey3"] = $sso_rng->GenerateToken(56);
		$sso_settings[""]["namespaceiv3"] = $sso_rng->GenerateToken(8);
		$sso_settings[""]["namespacekey4"] = $sso_rng->GenerateToken(56);
		$sso_settings[""]["namespaceiv4"] = $sso_rng->GenerateToken(8);
	}

	function SSO_LoadSettings()
	{
		global $sso_settings;

		require_once SSO_ROOT_PATH . "/settings.php";

		if (!isset($sso_settings[""]))
		{
			$sso_settings[""] = array(
				"timezone" => date_default_timezone_get(),
				"clock_drift" => 300,
				"iprestrict" => array(
					"patterns" => "*:*:*:*:*:*:*:*",
					"sfs_ip_mincount" => 0,
					"sfs_ip_maxage" => 0,
					"sfs_api_key" => "",
					"dnsrbl_lists" => "",
					"dnsrbl_mincount" => 1,
					"geoip_lists" => "",
					"ip_cache_len" => 14
				),
				"no_providers_msg" => "",
				"expose_namespaces" => 0,
				"hide_index" => 0,
				"first_activated_map" => "",
				"created_map" => "",
				"order" => array(),
				"dbversion" => 3
			);

			SSO_GenerateNamespaceKeys();
		}

		date_default_timezone_set($sso_settings[""]["timezone"]);

		$geoip_opts = SSO_GetGeoIPOpts();
		foreach ($geoip_opts as $opt => $val)
		{
			if (!isset($sso_settings[""]["iprestrict"]["geoip_map_" . $opt]) || !SSO_IsField($sso_settings[""]["iprestrict"]["geoip_map_" . $opt]))  $sso_settings[""]["iprestrict"]["geoip_map_" . $opt] = "";
		}
	}

	function SSO_CreatePHPStorageData($data)
	{
		if (!defined("SSO_USE_LESS_SAFE_STORAGE") || !SSO_USE_LESS_SAFE_STORAGE)  return "unserialize(base64_decode(\"" . base64_encode(serialize($data)) . "\"))";

		ob_start();
		var_export($data);
		$data = ob_get_contents();
		ob_end_clean();

		return $data;
	}

	function SSO_SaveSettings()
	{
		global $sso_settings;

		$data = "<" . "?php\n\t\$sso_settings = " . SSO_CreatePHPStorageData($sso_settings) . ";\n?" . ">";
		if (file_put_contents(SSO_ROOT_PATH . "/settings.php", $data) === false)  return false;

		if (function_exists("opcache_invalidate"))  @opcache_invalidate(SSO_ROOT_PATH . "/settings.php", true);

		return true;
	}

	function SSO_GetProviderList()
	{
		$providers = array();
		$dir = opendir(SSO_ROOT_PATH . "/" . SSO_PROVIDER_PATH);
		if ($dir)
		{
			while (($file = readdir($dir)) !== false)
			{
				if (substr($file, 0, 1) != "." && is_dir(SSO_ROOT_PATH . "/" . SSO_PROVIDER_PATH . "/" . $file) && file_exists(SSO_ROOT_PATH . "/" . SSO_PROVIDER_PATH . "/" . $file . "/index.php"))  $providers[$file] = $file;
			}

			closedir($dir);
		}

		return $providers;
	}

	function SSO_GetDirectoryList($path)
	{
		if (substr($path, -1) == "/")  $path = substr($path, 0, -1);

		$result = array("dirs" => array(), "files" => array());

		if ($path == "")  $path = ".";
		if (!file_exists($path))  return false;
		if (is_file($path))
		{
			$result["files"][] = $path;

			return $result;
		}

		$dir = opendir($path);
		if ($dir)
		{
			while (($file = readdir($dir)) !== false)
			{
				if ($file != "." && $file != "..")
				{
					$result[(is_file($path . "/" . $file) ? "files" : "dirs")][] = $file;
				}
			}

			closedir($dir);
		}

		natcasesort($result["dirs"]);
		natcasesort($result["files"]);
		$result["dirs"] = array_values($result["dirs"]);
		$result["files"] = array_values($result["files"]);

		return $result;
	}

	function SSO_RandomSleep()
	{
		global $sso_rng;

		usleep($sso_rng->GetInt(0, 250000));
	}

	function SSO_AddSortedOutput(&$outputmap, $numkey, $strkey, $data)
	{
		if (!isset($outputmap[$numkey]))  $outputmap[$numkey] = array();
		$outputmap[$numkey][$strkey] = $data;
	}

	function SSO_DisplaySortedOutput($outputmap)
	{
		ksort($outputmap);
		foreach ($outputmap as $outputmap2)
		{
			ksort($outputmap2);
			foreach ($outputmap2 as $output)  echo $output;
		}
	}

	function SSO_IsField($name)
	{
		global $sso_fields;

		return isset($sso_fields[$name]);
	}

	function SSO_SaveIPAddrInfo()
	{
		global $sso_db, $sso_db_ipcache, $sso_ipaddr_id, $sso_ipaddr_info;

		try
		{
			$sso_db->Query("UPDATE", array($sso_db_ipcache, array(
				"info" => serialize($sso_ipaddr_info),
			), "WHERE" => "id = ?"), $sso_ipaddr_id);
		}
		catch (Exception $e)
		{
			return false;
		}

		return true;
	}

	function SSO_GetGeoIPOpts()
	{
		$result = array(
			"continent_code" => array("continent", "code"),
			"continent_name" => array("continent", "names", "en"),
			"country_code" => array("country", "iso_code"),
			"country_name" => array("country", "names", "en"),
			"city" => array("city", "names", "en"),
			"region" => array("subdivisions", 0, "names", "en"),
			"region_code" => array("subdivisions", 0, "iso_code"),
			"postal_code" => array("postal", "code"),
			"latitude" => array("location", "latitude"),
			"longitude" => array("location", "longitude"),
			"metro_code" => array("location", "metro_code"),
			"time_zone" => array("location", "time_zone")
		);

		return $result;
	}

	function SSO_InitIPFields()
	{
		$result = array(
			"patterns" => "*:*:*:*:*:*:*:*",
			"allchecks" => true,
			"sfs_ip_mincount" => 0,
			"sfs_ip_maxage" => 0,
			"dnsrbl_lists" => "",
			"dnsrbl_mincount" => 1,
			"geoip_lists" => ""
		);

		return $result;
	}

	function SSO_ProcessIPFields($full = false)
	{
		$result = array();
		$result["patterns"] = trim($_REQUEST["sso_ipaddr__patterns"]);

		if (!$full)  $result["allchecks"] = (bool)(int)$_REQUEST["sso_ipaddr__allchecks"];
		$result["dnsrbl_lists"] = trim($_REQUEST["sso_ipaddr__dnsrbl_lists"]);
		$result["dnsrbl_mincount"] = (int)$_REQUEST["sso_ipaddr__dnsrbl_mincount"];
		$result["geoip_lists"] = trim($_REQUEST["sso_ipaddr__geoip_lists"]);
		if ($full)
		{
			$geoip_opts = SSO_GetGeoIPOpts();
			foreach ($geoip_opts as $opt => $val)
			{
				$result["geoip_map_" . $opt] = (SSO_IsField($_REQUEST["sso_ipaddr__geoip_map_" . $opt]) ? $_REQUEST["sso_ipaddr__geoip_map_" . $opt] : "");
			}
			$result["ip_cache_len"] = (int)$_REQUEST["sso_ipaddr__ip_cache_len"];
		}

		if ($result["dnsrbl_mincount"] < 1)  BB_SetPageMessage("error", "The 'DNSRBL - Minimum Matches' field contains an invalid value.");
		else if ($full && $result["ip_cache_len"] < 1)  BB_SetPageMessage("error", "The 'IP Address Cache Length (Days)' field contains an invalid value.");

		return $result;
	}

	function SSO_AppendIPFields(&$contentopts, $info, $full = false)
	{
		global $sso_select_fields;

		$contentopts["fields"][] = "split";
		if (!$full)
		{
			$contentopts["fields"][] = array(
				"title" => "Whitelist/Blacklist Options",
				"type" => "accordion"
			);
		}
		$contentopts["fields"][] = array(
			"title" => "Whitelist IP Address Patterns",
			"type" => "textarea",
			"height" => "250px",
			"name" => "sso_ipaddr__patterns",
			"value" => BB_GetValue("sso_ipaddr__patterns", $info["patterns"]),
			"desc" => "A whitelist of IP address patterns that allows access to " . ($full ? "the SSO server" : "this provider") . ".  One pattern per line.  (e.g. '10.0.0-15,17.*')"
		);
		if (!$full)
		{
			$contentopts["fields"][] = array(
				"title" => "Check Blacklists",
				"type" => "select",
				"name" => "sso_ipaddr__allchecks",
				"options" => array(1 => "Yes", 0 => "No"),
				"select" => BB_GetValue("sso_ipaddr__allchecks", (string)(int)$info["allchecks"]),
				"desc" => "Check the blacklists below when a user selects this provider."
			);
		}
		$contentopts["fields"][] = array(
			"title" => "DNSRBL - DNS Reverse Blacklists",
			"type" => "textarea",
			"height" => "250px",
			"name" => "sso_ipaddr__dnsrbl_lists",
			"value" => BB_GetValue("sso_ipaddr__dnsrbl_lists", $info["dnsrbl_lists"]),
			"htmldesc" => "Enter one or more DNSRBL entries ([website_url_of_dnsrbl|]domain[|required_response|alternate_response|...]).  One entry per line."
		);
		$contentopts["fields"][] = array(
			"title" => "DNSRBL - Minimum Matches",
			"type" => "text",
			"name" => "sso_ipaddr__dnsrbl_mincount",
			"value" => BB_GetValue("sso_ipaddr__dnsrbl_mincount", $info["dnsrbl_mincount"]),
			"desc" => "The minimum number of blacklists an IP address has to be on in order to be denied access."
		);
		$contentopts["fields"][] = array(
			"title" => "GeoIP - IP Geolocation Blacklists",
			"type" => "textarea",
			"height" => "250px",
			"name" => "sso_ipaddr__geoip_lists",
			"value" => BB_GetValue("sso_ipaddr__geoip_lists", $info["geoip_lists"]),
			"htmldesc" => "Enter one or more geographic areas to blacklist with semi-colon separated key-value pairs (key=value[;key=value])." . ($full ? "  Valid keys can be found below." : "") . "  One entry per line.  Example:  'city=Austin;region=TX' would ban any IP address that evaluated to Austin, TX." . (file_exists(SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/GeoLite2-City.mmdb") || file_exists(SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/GeoIP2-City.mmdb") ? "" : "  IP address to location requires the GeoIP2 City or <a href=\"http://dev.maxmind.com/geoip/geoip2/geolite2/\" target=\"_blank\">GeoLite2 City</a> database.  This message will no longer appear when either database is correctly installed in the 'support' subdirectory.")
		);
		if ($full)
		{
			$geoip_opts = SSO_GetGeoIPOpts();
			foreach ($geoip_opts as $opt => $val)
			{
				$contentopts["fields"][] = array(
					"title" => "GeoIP - Map '" . $opt . "'",
					"type" => "select",
					"name" => "sso_ipaddr__geoip_map_" . $opt,
					"options" => $sso_select_fields,
					"select" => BB_GetValue("sso_ipaddr__geoip_map_" . $opt, (string)$info["geoip_map_" . $opt]),
					"desc" => "The field in the SSO system to map '" . $opt . "' to."
				);
			}
			$contentopts["fields"][] = array(
				"title" => "IP Address Cache Length (Days)",
				"type" => "text",
				"name" => "sso_ipaddr__ip_cache_len",
				"value" => BB_GetValue("sso_ipaddr__ip_cache_len", $info["ip_cache_len"]),
				"desc" => "The length of time, in days, the results of queries against spam databases and other information for an IP address are cached.  Used to avoid hitting query limits of most systems and improve performance."
			);
		}
		else
		{
			$contentopts["fields"][] = "endaccordion";
		}
	}

	function SSO_GetGeoIPInfo()
	{
		global $sso_ipaddr;

		require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/geoip/Reader.php";
		require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/geoip/Reader/Decoder.php";
		require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/geoip/Reader/InvalidDatabaseException.php";
		require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/geoip/Reader/Metadata.php";
		require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/geoip/Reader/Util.php";

		if (file_exists(SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/GeoIP2-City.mmdb"))  $reader = new \MaxMind\Db\Reader(SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/GeoIP2-City.mmdb");
		else if (file_exists(SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/GeoLite2-City.mmdb"))  $reader = new \MaxMind\Db\Reader(SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/GeoLite2-City.mmdb");
		else  return false;

		$result = array();
		$record = $reader->get($sso_ipaddr["ipv6"]);
		$geoip_opts = SSO_GetGeoIPOpts();
		foreach ($geoip_opts as $opt => $path)
		{
			$val = $record;
			do
			{
				$found = false;
				$key = array_shift($path);
				if (isset($val[$key]))
				{
					$val = $val[$key];
					$found = true;
				}
			} while ($found && count($path));

			$result[$opt] = ($found ? (string)$val : "");
		}

		return $result;
	}

	function SSO_IsSpammer($info)
	{
		global $sso_settings, $sso_ipaddr, $sso_ipaddr_info, $sso_provider;

		// Check for an existing entry.
		if (isset($sso_ipaddr_info["spaminfo"]) && isset($sso_ipaddr_info["spaminfo"][$sso_provider]))  return ($info["allchecks"] ? (isset($sso_ipaddr_info["spaminfo_cache"]["spammer"]) ? $sso_ipaddr_info["spaminfo_cache"]["spammer"] : $sso_ipaddr_info["spaminfo"][$sso_provider]["spammer"]) : false);

		// An extra cache so the various servers are only queried once for global data.
		$spamcache = (isset($sso_ipaddr_info["spaminfo_cache"]) ? $sso_ipaddr_info["spaminfo_cache"] : array());

		// Check DNSRBL entries.  Only IPv4 support for the moment.
		$spaminfo = array("reasons" => array());
		$spammer = false;
		if (!isset($spamcache["dnsrbl"]))  $spamcache["dnsrbl"] = array();
		if ($sso_ipaddr["ipv4"] != "")
		{
			$num = 0;
			$ipv4 = implode(".", array_reverse(explode(".", $sso_ipaddr["ipv4"])));
			$blacklists = explode("\n", str_replace("\r", "\n", $info["dnsrbl_lists"] . "\n" . $sso_settings[""]["iprestrict"]["dnsrbl_lists"]));
			foreach ($blacklists as $blacklist)
			{
				$pos = strpos($blacklist, "#");
				if ($pos !== false)  $blacklist = substr($blacklist, 0, $pos);
				$blacklist = trim($blacklist);
				if ($blacklist != "")
				{
					$blacklist = explode("|", $blacklist);
					$domain = trim(array_shift($blacklist));
					$url = $domain;
					if (substr($url, 0, 7) == "http://" || substr($domain, 0, 8) == "https://")  $domain = trim(array_shift($blacklist));
					if ($domain != "")
					{
						if (isset($spamcache["dnsrbl"][$domain]))  $mapips = $spamcache["dnsrbl"][$domain];
						else
						{
							$mapips = gethostbynamel(stripos($domain, "@IP@") === false ? $ipv4 . "." . $domain : str_replace("@IP@", $ipv4, $domain));
							$spamcache["dnsrbl"][$domain] = $mapips;
						}
						if ($mapips !== false && is_array($mapips))
						{
							if (!count($blacklist))
							{
								$spaminfo["reasons"][] = BB_Translate("IP address '%s' appears on the blacklist at '%s'.", $sso_ipaddr["ipv4"], $url);
								$found = true;
							}
							else
							{
								$found = false;
								while (count($blacklist) && !$found)
								{
									$match = trim(array_shift($blacklist));
									if (strpos($match, "&") !== false || strpos($match, "<") !== false || strpos($match, ">") !== false)
									{
										$match = explode(".", $match);
										if (count($match) == 4)
										{
											foreach ($mapips as $mapip)
											{
												$mapip = explode(".", $mapip);
												if (count($mapip) == 4)
												{
													for ($x = 0; $x < 4; $x++)
													{
														$chr = substr($match[$x], 0, 1);
														if ($chr == "&" && ((int)substr($match[$x], 1) & (int)$mapip[$x]) == 0)  break;
														else if ($chr == "<" && ((int)substr($match[$x], 1) <= (int)$mapip[$x]))  break;
														else if ($chr == ">" && ((int)substr($match[$x], 1) >= (int)$mapip[$x]))  break;
														else if ($chr != "&" && $chr != "<" && $chr != ">" && $chr != "" && $match[$x] != $mapip[$x])  break;
													}

													if ($x == 4)
													{
														$spaminfo["reasons"][] = BB_Translate("IP address '%s' appears on the blacklist at '%s' with return value '%s' matching pattern '%s'.", $sso_ipaddr["ipv4"], $url, implode(".", $mapip), implode(".", $match));
														$found = true;

														break;
													}
												}
											}
										}
									}
									else if (in_array($match, $mapips))
									{
										$spaminfo["reasons"][] = BB_Translate("IP address '%s' appears on the blacklist at '%s' with return value '%s'.", $sso_ipaddr["ipv4"], $url, $match);
										$found = true;
									}
								}
							}

							if ($found)  $num++;
						}
					}
				}
			}

			if ((int)$info["dnsrbl_mincount"] > 0 && $num >= (int)$info["dnsrbl_mincount"])  $spammer = true;
			else if ((int)$sso_settings[""]["iprestrict"]["dnsrbl_mincount"] > 0 && $num >= (int)$sso_settings[""]["iprestrict"]["dnsrbl_mincount"])  $spammer = true;
		}

		// Check geolocation blacklists.
		if (!isset($spamcache["geoip"]))  $spamcache["geoip"] = SSO_GetGeoIPInfo();
		if ($spamcache["geoip"] !== false)
		{
			$geoip_lists = explode("\n", str_replace("\r", "\n", $info["geoip_lists"] . "\n" . $sso_settings[""]["iprestrict"]["geoip_lists"]));
			foreach ($geoip_lists as $line)
			{
				$line = trim($line);
				if ($line != "")
				{
					$found = true;
					$entries = explode(";", $line);
					foreach ($entries as $entry)
					{
						$entry = explode("=", $entry);
						if (count($entry) != 2)
						{
							$found = false;
							break;
						}

						$key = trim($entry[0]);
						$val = trim($entry[1]);
						if (!isset($spamcache["geoip"][$key]) || $spamcache["geoip"][$key] != $val)
						{
							$found = false;
							break;
						}
					}

					if ($found)
					{
						$spaminfo["reasons"][] = BB_Translate("IP address '%s' matches geolocation '%s'.", $sso_ipaddr["ipv6"] . ($sso_ipaddr["ipv4"] != "" ? " (" . $sso_ipaddr["ipv4"] . ")" : ""), $line);
						$spammer = true;
					}
				}
			}
		}

		// Cache the results.
		$spaminfo["spammer"] = $spammer;
		if (!isset($sso_ipaddr_info["spaminfo"]))  $sso_ipaddr_info["spaminfo"] = array();
		$sso_ipaddr_info["spaminfo"][$sso_provider] = $spaminfo;
		$sso_ipaddr_info["spaminfo_cache"] = $spamcache;
		SSO_SaveIPAddrInfo();

		return ($info["allchecks"] ? $spammer : false);
	}

	function SSO_IsIPAllowed($info)
	{
		global $sso_settings, $sso_ipaddr;

		$allowed = false;
		$patterns = explode("\n", str_replace("\r", "\n", $sso_settings[""]["iprestrict"]["patterns"]));
		foreach ($patterns as $pattern)
		{
			$pattern = trim($pattern);
			if ($pattern != "" && IPAddr::IsMatch($pattern, $sso_ipaddr))  $allowed = true;
		}
		if (!$allowed)  return false;

		$allowed = false;
		$patterns = explode("\n", str_replace("\r", "\n", $info["patterns"]));
		foreach ($patterns as $pattern)
		{
			$pattern = trim($pattern);
			if ($pattern != "" && IPAddr::IsMatch($pattern, $sso_ipaddr))  $allowed = true;
		}

		return $allowed;
	}

	function SSO_GetRandomWord($randcapital = true, $words = array())
	{
		global $sso_randomwords, $sso_rng;

		if (!isset($sso_randomwords))
		{
			$sso_randomwords = array();
			$sso_randomwords["fp"] = fopen(SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/dictionary.txt", "rb");
			$sso_randomwords["size"] = filesize(SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/dictionary.txt");
		}
		if ($sso_randomwords["fp"] === false)  return "";

		if (count($words))
		{
			$num = $sso_rng->GetInt(0, count($words) - 1);
			$word = trim($words[$num]);
		}
		else
		{
			$pos = $sso_rng->GetInt(0, $sso_randomwords["size"]);
			fseek($sso_randomwords["fp"], $pos);
			fgets($sso_randomwords["fp"]);
			$word = trim(fgets($sso_randomwords["fp"]));
			if ($word == "")  $word = trim(fgets($sso_randomwords["fp"]));
		}

		if ($randcapital && $sso_rng->GetInt(0, 1))  $word = strtoupper(substr($word, 0, 1)) . substr($word, 1);

		return $word;
	}

	// Drop-in replacement for hash_hmac() on hosts where Hash is not available.
	// Only supports HMAC-MD5 and HMAC-SHA1.
	if (!function_exists("hash_hmac"))
	{
		function hash_hmac($algo, $data, $key, $raw_output = false)
		{
			$algo = strtolower($algo);
			$size = 64;
			$opad = str_repeat("\x5C", $size);
			$ipad = str_repeat("\x36", $size);

			if (strlen($key) > $size)  $key = $algo($key, true);
			$key = str_pad($key, $size, "\x00");

			$y = strlen($key) - 1;
			for ($x = 0; $x < $y; $x++)
			{
				$opad[$x] = $opad[$x] ^ $key[$x];
				$ipad[$x] = $ipad[$x] ^ $key[$x];
			}

			$result = $algo($opad . $algo($ipad . $data, true), $raw_output);

			return $result;
		}
	}

	function SSO_SaveSessionInfo()
	{
		global $sso_db, $sso_db_temp_sessions, $sso_sessionrow, $sso_session_info;

		try
		{
			$sso_db->Query("UPDATE", array($sso_db_temp_sessions, array(
				"info" => serialize($sso_session_info),
			), "WHERE" => "id = ?"), $sso_sessionrow->id);
		}
		catch (Exception $e)
		{
			return false;
		}

		return true;
	}

	function SSO_SendEmail($fromaddr, $toaddr, $subject, $htmlmsg, $textmsg)
	{
		if (!class_exists("SMTP"))
		{
			define("CS_TRANSLATE_FUNC", "BB_Translate");
			require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/smtp.php";
		}

		$headers = SMTP::GetUserAgent("Thunderbird");
		$smtpoptions = array(
			"headers" => $headers,
			"htmlmessage" => $htmlmsg,
			"textmessage" => $textmsg,
			"server" => SSO_SMTP_SERVER,
			"port" => SSO_SMTP_PORT,
			"secure" => (SSO_SMTP_PORT == 465),
			"username" => SSO_SMTPPOP3_USER,
			"password" => SSO_SMTPPOP3_PASS
		);

		$result = SMTP::SendEmail($fromaddr, $toaddr, $subject, $smtpoptions);
		if (!$result["success"] && SSO_POP3_SERVER != "")
		{
			// Try POP-before-SMTP.
			require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/pop3.php";

			$pop3options = array(
				"server" => SSO_POP3_SERVER,
				"port" => SSO_POP3_PORT,
				"secure" => (SSO_POP3_PORT == 995)
			);

			$temppop3 = new POP3;
			$result = $temppop3->Connect(SSO_SMTPPOP3_USER, SSO_SMTPPOP3_PASS, $pop3options);
			if ($result["success"])
			{
				$temppop3->Disconnect();
				$result = SMTP::SendEmail($fromaddr, $toaddr, $subject, $smtpoptions);
			}
		}

		return $result;
	}

	function SSO_EncryptDBData($data)
	{
		global $sso_rng;

		$mode = (defined("SSO_PRIMARY_CIPHER") && SSO_PRIMARY_CIPHER == "aes256" ? "aes256" : "blowfish");

		$data = serialize($data);

		$key = pack("H*", SSO_BASE_RAND_SEED4);
		$options = array("prefix" => $sso_rng->GenerateString(), "mode" => "CBC", "iv" => pack("H*", SSO_BASE_RAND_SEED3));
		if (!defined("SSO_DUAL_ENCRYPT") || SSO_DUAL_ENCRYPT)
		{
			$options["key2"] = pack("H*", SSO_BASE_RAND_SEED5);
			$options["iv2"] = pack("H*", SSO_BASE_RAND_SEED6);
		}

		if ($mode == "aes256")  $data = ExtendedAES::CreateDataPacket($data, $key, $options);
		else  $data = Blowfish::CreateDataPacket($data, $key, $options);

		$data = $mode . ":" . (!defined("SSO_DUAL_ENCRYPT") || SSO_DUAL_ENCRYPT ? "2" : "1") . ":" . base64_encode($data);

		return $data;
	}

	function SSO_DecryptDBData($data)
	{
		$data2 = explode(":", $data);
		if (count($data2) == 3)
		{
			$mode = ($data2[0] == "aes256" ? "aes256" : "blowfish");
			$dual = ((int)$data2[1] === 2);
			$data = $data2[2];
		}
		else
		{
			$mode = "blowfish";
			$dual = false;
		}

		$data = @base64_decode($data);

		if ($data !== false)
		{
			$key = pack("H*", SSO_BASE_RAND_SEED4);
			$options = array("mode" => "CBC", "iv" => pack("H*", SSO_BASE_RAND_SEED3));
			if ($dual)
			{
				$options["key2"] = pack("H*", SSO_BASE_RAND_SEED5);
				$options["iv2"] = pack("H*", SSO_BASE_RAND_SEED6);
			}

			if ($mode == "aes256")  $data = ExtendedAES::ExtractDataPacket($data, $key, $options);
			else  $data = Blowfish::ExtractDataPacket($data, $key, $options);
		}

		if ($data !== false)  $data = @unserialize($data);

		return $data;
	}

	function SSO_LoadDecryptedUserInfo($row)
	{
		$result = @unserialize($row->info);
		$info = SSO_DecryptDBData($row->info2);
		if ($info !== false)  $result = array_merge($result, $info);

		return $result;
	}

	function SSO_CreateEncryptedUserInfo(&$userinfo)
	{
		global $sso_fields;

		$result = $userinfo;
		$userinfo = array();
		foreach ($sso_fields as $key => $encrypted)
		{
			if (isset($result[$key]))
			{
				$key2 = UTF8::MakeValid($key);
				$val = UTF8::MakeValid($result[$key]);
				unset($result[$key]);

				if ($encrypted)  $result[$key2] = $val;
				else  $userinfo[$key2] = $val;
			}
		}

		return SSO_EncryptDBData($result);
	}

	function SSO_AddGeoIPMapFields(&$info)
	{
		global $sso_settings, $sso_ipaddr_info;

		$geoip_opts = SSO_GetGeoIPOpts();
		foreach ($geoip_opts as $opt => $val)
		{
			if ($sso_settings[""]["iprestrict"]["geoip_map_" . $opt] != "")
			{
				$info[$sso_settings[""]["iprestrict"]["geoip_map_" . $opt]] = (isset($sso_ipaddr_info["spaminfo_cache"]) && isset($sso_ipaddr_info["spaminfo_cache"]["geoip"]) && $sso_ipaddr_info["spaminfo_cache"]["geoip"] !== false && isset($sso_ipaddr_info["spaminfo_cache"]["geoip"][$opt]) ? $sso_ipaddr_info["spaminfo_cache"]["geoip"][$opt] : "");
			}
		}
	}

	function SSO_IsLockedUser($id)
	{
		global $sso_db, $sso_db_user_tags, $sso_db_tags;

		$result = $sso_db->GetOne("SELECT", array(
			"COUNT(*)",
			"FROM" => "? AS ut, ? AS t",
			"WHERE" => "ut.tag_id = t.id AND ut.user_id = ? AND t.tag_name = ?",
		), $sso_db_user_tags, $sso_db_tags, $id, SSO_LOCKED_TAG);

		return ($result > 0);
	}

	function SSO_ActivateUserSession($row, $automate)
	{
		global $sso_rng, $sso_db, $sso_db_user_sessions, $sso_sessionrow, $sso_session_info, $sso_apirow;

		try
		{
			// Create a user session.
			$sid = $sso_rng->GenerateString();

			$sso_db->Query("INSERT", array($sso_db_user_sessions, array(
				"user_id" => $row->id,
				"apikey_id" => $sso_sessionrow->apikey_id,
				"updated" => CSDB::ConvertToDBTime(time()),
				"created" => CSDB::ConvertToDBTime(time()),
				"session_id" => $sid,
				"info" => serialize(array("validated" => false, "automate" => $automate)),
			), "AUTO INCREMENT" => "id"));

			$id = $sso_db->GetInsertID();

			// Update the current session with the new information.
			$sso_session_info["new_id"] = $sid . "-" . $id;
			if (!SSO_SaveSessionInfo())  return false;

			// Set the second ID cookie.
			SetCookieFixDomain("sso_server_id2", $sso_session_info["new_id"], 0, "", "", SSO_IsSSLRequest(), true);

			// Redirect to validation.
			header("Location: " . BB_GetRequestHost() . SSO_ROOT_URL . "/index.php?sso_action=sso_validate" . (isset($_REQUEST["lang"]) ? "&lang=" . urlencode($_REQUEST["lang"]) : ""));
			exit();
		}
		catch (Exception $e)
		{
			// Don't do anything here.  Just catch the database exception and let the code fall through.
			// It should be nearly impossible to get here in the first place.
		}

		return false;
	}

	// Activate a user who is being impersonated with a valid impersonation token.
	function SSO_ActivateImpersonationUser()
	{
		global $sso_db, $sso_db_users, $sso_db_user_tags, $sso_db_tags, $sso_apikey_info, $sso_providers, $sso_provider, $sso_session_info;

		if (!isset($_REQUEST["sso_impersonate"]) || !is_string($_REQUEST["sso_impersonate"]))  return false;
		if (!isset($sso_apikey_info["impersonation"]) || !$sso_apikey_info["impersonation"])  return false;

		if ($sso_session_info["initmsg"] != "")
		{
			$initmsg = base64_decode($sso_session_info["initmsg"]);
			if ($initmsg == "insufficient_permissions")  return false;
		}

		$user_id = explode("-", $_REQUEST["sso_impersonate"]);
		if (count($user_id) != 2)  return false;

		try
		{
			$row = $sso_db->GetRow("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "id = ?",
			), $sso_db_users, $user_id[1]);

			if ($row === false)  return false;

			$userinfo = SSO_LoadDecryptedUserInfo($row);
			if (!isset($userinfo["sso__impersonation"]) || !$userinfo["sso__impersonation"])  return false;
			if ($userinfo["sso__impersonation_key"] != $user_id[0])  return false;

			if (!isset($sso_providers[$row->provider_name]))  return false;
			$sso_provider = $row->provider_name;

			// Check for the account locked tag.
			if (SSO_IsLockedUser($row->id))  return false;

			SSO_ActivateUserSession($row, (bool)(int)$userinfo["sso__impersonation_auto"]);
		}
		catch (Exception $e)
		{
			// Don't do anything here.  Just catch the database exception and let the code fall through.
			// It should be nearly impossible to get here in the first place.
		}

		return false;
	}

	function SSO_LoadNamespaces($real, $data = false)
	{
		global $sso_settings;

		if ($real)
		{
			if (isset($sso_settings[""]["namespacekey2"]) && isset($_COOKIE["sso_server_ns"]))
			{
				if ($data === false)  $data = $_COOKIE["sso_server_ns"];
				$result = @base64_decode($data);
				if ($result !== false)  $result = Blowfish::ExtractDataPacket($result, pack("H*", $sso_settings[""]["namespacekey"]), array("mode" => "CBC", "iv" => pack("H*", $sso_settings[""]["namespaceiv"]), "key2" => pack("H*", $sso_settings[""]["namespacekey2"]), "iv2" => pack("H*", $sso_settings[""]["namespaceiv2"]), "lightweight" => true));
				if ($result !== false)  $result = @unserialize($result);

				if ($result !== false)  return $result;
			}
		}
		else
		{
			if (isset($sso_settings[""]["namespacekey4"]) && isset($_COOKIE["sso_server_ns2"]))
			{
				if ($data === false)  $data = $_COOKIE["sso_server_ns2"];
				$result = @base64_decode($data);
				if ($result !== false)  $result = Blowfish::ExtractDataPacket($result, pack("H*", $sso_settings[""]["namespacekey3"]), array("mode" => "CBC", "iv" => pack("H*", $sso_settings[""]["namespaceiv3"]), "key2" => pack("H*", $sso_settings[""]["namespacekey4"]), "iv2" => pack("H*", $sso_settings[""]["namespaceiv4"]), "lightweight" => true));
				if ($result !== false)  $result = @unserialize($result);

				if ($result !== false)  return $result;
			}
		}

		return array();
	}

	// Automatically activate a user who is already activated within the same API key namespace.
	function SSO_ActivateNamespaceUser()
	{
		global $sso_db, $sso_db_users, $sso_db_user_tags, $sso_db_tags, $sso_db_user_sessions, $sso_providers, $sso_provider, $sso_ipaddr, $sso_namespaces, $sso_apirow, $sso_session_info, $sso_settings;

		$sso_namespaces = SSO_LoadNamespaces(true);

		if (!isset($sso_namespaces[$sso_apirow->namespace]))  return false;

		if ($sso_session_info["initmsg"] != "")
		{
			$initmsg = base64_decode($sso_session_info["initmsg"]);
			if ($initmsg == "insufficient_permissions")
			{
				unset($sso_namespaces[$sso_apirow->namespace]);
				SetCookieFixDomain("sso_server_ns", base64_encode(serialize($sso_namespaces)), 0, "", "", SSO_IsSSLRequest(), true);

				return false;
			}
		}

		$session_id = explode("-", $sso_namespaces[$sso_apirow->namespace]);
		if (count($session_id) != 2)  return false;

		try
		{
			$sessionrow = $sso_db->GetRow("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "id = ? AND session_id = ? AND updated > ?",
			), $sso_db_user_sessions, $session_id[1], $session_id[0], CSDB::ConvertToDBTime(time() - 5 * 60));

			if ($sessionrow === false)
			{
				unset($sso_namespaces[$sso_apirow->namespace]);
				SetCookieFixDomain("sso_server_ns", base64_encode(serialize($sso_namespaces)), 0, "", "", SSO_IsSSLRequest(), true);

				return false;
			}

			// Only allow namespace sign in if the session is fully validated and the user is from the same IP address.
			$session_info = unserialize($sessionrow->info);
			if (!isset($session_info["validated"]))  return false;
			if (!isset($session_info["ipaddr"]) || $session_info["ipaddr"] != $sso_ipaddr["ipv6"])  return false;

			$row = $sso_db->GetRow("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "id = ?",
			), $sso_db_users, $sessionrow->user_id);

			if ($row === false)  return false;

			if (!isset($sso_providers[$row->provider_name]))  return false;
			$sso_provider = $row->provider_name;

			// Check for the account locked tag.
			if (SSO_IsLockedUser($row->id))  return false;

			SSO_ActivateUserSession($row, true);
		}
		catch (Exception $e)
		{
			// Don't do anything here.  Just catch the database exception and let the code fall through.
			// It should be nearly impossible to get here in the first place.
		}

		return false;
	}

	function SSO_ActivateUser($id, $entropy, $info, $created = false, $automate = false, $activatesession = true)
	{
		global $sso_rng, $sso_db, $sso_db_users, $sso_db_user_tags, $sso_db_tags, $sso_provider, $sso_ipaddr, $sso_settings;

		try
		{
			// Create or update the user.
			$row = $sso_db->GetRow("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "provider_name = ? AND provider_id = ?",
			), $sso_db_users, $sso_provider, $id);

			if ($row)
			{
				// Check for the account locked tag.
				if (SSO_IsLockedUser($row->id))  return false;

				$info2 = SSO_LoadDecryptedUserInfo($row);
				SSO_AddGeoIPMapFields($info2);
				foreach ($info as $key => $val)  $info2[$key] = $val;
				$info3 = SSO_CreateEncryptedUserInfo($info2);

				$sso_db->Query("UPDATE", array($sso_db_users, array(
					"lastipaddr" => $sso_ipaddr["ipv6"],
					"lastactivated" => CSDB::ConvertToDBTime(time()),
					"info" => serialize($info2),
					"info2" => $info3,
				), "WHERE" => "id = ?"), $row->id);
			}
			else
			{
				$extra = $sso_rng->GenerateString(64);
				$info2 = array();
				SSO_AddGeoIPMapFields($info2);
				if (isset($sso_settings[""]["first_activated_map"]) && SSO_IsField($sso_settings[""]["first_activated_map"]))  $info2[$sso_settings[""]["first_activated_map"]] = CSDB::ConvertToDBTime(time());
				if (isset($sso_settings[""]["created_map"]) && SSO_IsField($sso_settings[""]["created_map"]))  $info2[$sso_settings[""]["created_map"]] = CSDB::ConvertToDBTime($created !== false ? $created : time());
				foreach ($info as $key => $val)  $info2[$key] = $val;
				$info3 = SSO_CreateEncryptedUserInfo($info2);

				$sso_db->Query("INSERT", array($sso_db_users, array(
					"provider_name" => $sso_provider,
					"provider_id" => $id,
					"session_extra" => $extra,
					"version" => 0,
					"lastipaddr" => $sso_ipaddr["ipv6"],
					"lastactivated" => CSDB::ConvertToDBTime(time()),
					"info" => serialize($info2),
					"info2" => $info3,
				)));

				$row = $sso_db->GetRow("SELECT", array(
					"*",
					"FROM" => "?",
					"WHERE" => "provider_name = ? AND provider_id = ?",
				), $sso_db_users, $sso_provider, $id);
			}

			if ($activatesession)  SSO_ActivateUserSession($row, $automate);
		}
		catch (Exception $e)
		{
			// Don't do anything here.  Just catch the database exception and let the code fall through.
			// It should be nearly impossible to get here in the first place.
		}

		return false;
	}

	function SSO_SetUserVersion($version)
	{
		global $sso_db, $sso_db_users, $sso_userrow, $sso_user_info;

		try
		{
			$info2 = $sso_user_info;
			$info3 = SSO_CreateEncryptedUserInfo($info2);

			$sso_db->Query("UPDATE", array($sso_db_users, array(
				"version" => $version,
				"info" => serialize($info2),
				"info2" => $info3,
			), "WHERE" => "id = ?"), $sso_userrow->id);

			return true;
		}
		catch (Exception $e)
		{
			// Don't do anything here.  Just catch the database exception and let the code fall through.
			// It should be nearly impossible to get here in the first place.
		}

		return false;
	}

	function SSO_ExternalRedirect($url, $final = false)
	{
		SetCookieFixDomain("sso_server_er", base64_encode($url), 0, "", "", SSO_IsSSLRequest(), true);
		SetCookieFixDomain("sso_server_ern", md5(SSO_FrontendField("external_redirect") . ":" . $url), 0, "", "", SSO_IsSSLRequest(), true);

		$url = BB_GetRequestHost() . SSO_ROOT_URL . "/index.php?sso_action=sso_redirect" . ($final ? "&sso_final=1" : "") . (isset($_REQUEST["lang"]) ? "&lang=" . urlencode($_REQUEST["lang"]) : "");
?>
<!DOCTYPE html>
<html>
<head>
<script type="text/javascript">
document.location.replace('<?php echo BB_JSSafe($url); ?>');
</script>
<title><?php echo BB_Translate("Redirecting..."); ?></title>
<meta http-equiv="refresh" content="3; URL=<?php echo htmlspecialchars($url); ?>" />
</head>
<body>
<div style="text-align: center;"><?php echo BB_Translate("Redirecting..."); ?></div>
</body>
</html>
<?php

		exit();
	}

	function SSO_ValidateUser()
	{
		global $sso_rng, $sso_db, $sso_db_user_sessions, $sso_db_temp_sessions, $sso_session_info, $sso_apirow, $sso_sessionrow, $sso_sessionrow2, $sso_ipaddr, $sso_settings;

		try
		{
			// Browser gets a token representing the new session in the temporary session.
			$sso_session_info["new_id2"] = $sso_rng->GenerateString();

			$sso_db->Query("UPDATE", array($sso_db_temp_sessions, array(
				"info" => serialize($sso_session_info),
			), "WHERE" => "id = ?"), $sso_sessionrow->id);

			// Validate the session.
			$sso_db->Query("UPDATE", array($sso_db_user_sessions, array(
				"updated" => CSDB::ConvertToDBTime(time()),
				"info" => serialize(array("validated" => true, "ipaddr" => $sso_ipaddr["ipv6"])),
			), "WHERE" => "id = ?"), $sso_sessionrow2->id);

			// Build the redirect.
			$redirect = str_replace(array("\r", "\n"), "", base64_decode($sso_session_info["url"]));
			$redirect .= (strpos($redirect, "?") === false ? "?" : "&") . "from_sso_server=1&sso_id=" . urlencode($sso_session_info["new_id2"]) . "&sso_id2=" . urlencode($_REQUEST["sso_id"]);

			// Set the namespace cookie.
			if (isset($sso_settings[""]["namespacekey2"]))
			{
				$namespaces = SSO_LoadNamespaces(true);

				$namespaces[$sso_apirow->namespace] = $_COOKIE["sso_server_id2"];
				$data = serialize($namespaces);
				$data = base64_encode(Blowfish::CreateDataPacket($data, pack("H*", $sso_settings[""]["namespacekey"]), array("prefix" => $sso_rng->GenerateString(), "mode" => "CBC", "iv" => pack("H*", $sso_settings[""]["namespaceiv"]), "key2" => pack("H*", $sso_settings[""]["namespacekey2"]), "iv2" => pack("H*", $sso_settings[""]["namespaceiv2"]), "lightweight" => true)));
				SetCookieFixDomain("sso_server_ns", $data, 0, "", "", SSO_IsSSLRequest(), true);
			}

			// Set the exposed namespace cookie if the option is enabled.
			if (isset($sso_settings[""]["expose_namespaces"]) && $sso_settings[""]["expose_namespaces"] && isset($sso_settings[""]["namespacekey4"]))
			{
				$namespaces = SSO_LoadNamespaces(false);

				$namespaces[$sso_apirow->namespace] = $sso_sessionrow2->id;
				$data = serialize($namespaces);
				$data = base64_encode(Blowfish::CreateDataPacket($data, pack("H*", $sso_settings[""]["namespacekey3"]), array("prefix" => $sso_rng->GenerateString(), "mode" => "CBC", "iv" => pack("H*", $sso_settings[""]["namespaceiv3"]), "key2" => pack("H*", $sso_settings[""]["namespacekey4"]), "iv2" => pack("H*", $sso_settings[""]["namespaceiv4"]), "lightweight" => true)));
				$host = str_replace(array("http://", "https://"), "", BB_GetRequestHost());
				SetCookieFixDomain("sso_server_ns2", $data, 0, "/", $host, false, true);
			}

			// Redirect back to the client.
			SSO_ExternalRedirect($redirect, true);
		}
		catch (Exception $e)
		{
			// Don't do anything here.  Just catch the database exception and let the code fall through.
			// It should be nearly impossible to get here in the first place.
		}

		return false;
	}
?>