<?php
	// SSO server endpoint.
	// (C) 2015 CubicleSoft.  All Rights Reserved.

	define("SSO_FILE", 1);
	define("SSO_MODE", "endpoint");

	require_once "config.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/str_basics.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/sso_functions.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/blowfish.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/aes.php";
	if (!ExtendedAES::IsMcryptAvailable())  require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/phpseclib/AES.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/random.php";

	Str::ProcessAllInput();

	// Don't proceed any further if this is an acciental re-upload of this file to the root path.
	if (SSO_STO_ENDPOINT && SSO_ROOT_PATH == str_replace("\\", "/", dirname(__FILE__)))  exit();

	// Initialize the global CSPRNG instance.
	$sso_rng = new CSPRNG();

	// Timing attack defense.
	$sso_skipsleep = false;

	// Calculate the remote IP address.
	$sso_ipaddr = SSO_GetRemoteIP();

	// Start out with plain-text responses until the data packet is decrypted.
	$sso_encrypted = false;

	function SSO_EndpointOutput($result)
	{
		global $sso_encrypted, $sso_apikey_info, $sso_data, $sso_skipsleep;

		if (!$sso_skipsleep)  SSO_RandomSleep();

		$result = @json_encode($result);
		if ($sso_encrypted)
		{
			if ($sso_apikey_info["keyinfo"]["mode"] === "aes256")  $result = ExtendedAES::CreateDataPacket($result, $sso_apikey_info["keyinfo"]["key"], $sso_apikey_info["keyinfo"]["opts"]);
			else  $result = Blowfish::CreateDataPacket($result, $sso_apikey_info["keyinfo"]["key"], $sso_apikey_info["keyinfo"]["opts"]);

			$result = base64_encode($result);
		}

		echo $result;
		exit();
	}

	function SSO_EndpointError($msg, $info = "")
	{
		global $sso_skipsleep;

		$sso_skipsleep = false;

		$result = array(
			"success" => false,
			"error" => $msg
		);
		if ($info != "")  $result["info"] = $info;

		SSO_EndpointOutput($result);
	}

	if (SSO_USE_HTTPS && !SSO_IsSSLRequest())  SSO_EndpointError("SSO Server is configured to only accept HTTPS (SSL) requests.");

	// Make sure the client version matches the server.
	if (!isset($_REQUEST["ver"]))  SSO_EndpointError("Version not specified.  Please use an official SSO client.");
	if ($_REQUEST["ver"] != "3.0")  SSO_EndpointError("Client API version does not match server API version.  Please use a compatible SSO client.", "3.0");

	// Handle expected information.
	if (!isset($_REQUEST["apikey"]))  SSO_EndpointError("API key not specified.  Please use an official SSO client.");

	// Break up the API key.
	$apikey = explode("-", $_REQUEST["apikey"]);
	if (count($apikey) != 2)  SSO_EndpointError("Invalid API key specified.");

	if (!isset($_REQUEST["action"]))  SSO_EndpointError("Action not specified.  Please use an official SSO client.");

	if (!isset($_REQUEST["data"]))  SSO_EndpointError("Data packet not specified.  Please use an official SSO client.");

	try
	{
		// Connect to the database and generate database globals.
		SSO_DBConnect(false);
	}
	catch (Exception $e)
	{
		SSO_EndpointError("Database connect failed.", $e->getMessage());
	}

	// Load in fields without admin select.
	SSO_LoadFields(false);

	// Load in $sso_settings and initialize it.
	SSO_LoadSettings();

	// Simply bail with a generic message if a SQL query fails.
	try
	{
		// Load the API key information.
		$sso_apirow = $sso_db->GetRow("SELECT", array(
			"*",
			"FROM" => "?",
			"WHERE" => "id = ? AND apikey = ?",
		), $sso_db_apikeys, $apikey[1], $apikey[0]);

		if ($sso_apirow === false)  SSO_EndpointError("Invalid API key specified.");
		$sso_apikey_info = unserialize($sso_apirow->info);
		if (!isset($sso_apikey_info["type"]))  $sso_apikey_info["type"] = "normal";

		// Check the IP address against API key patterns.
		if (!SSO_IsIPAllowed($sso_apikey_info))  SSO_EndpointError("Invalid API key IP address.");

		// Decrypt the data packet using the secret key.
		$sso_data = @base64_decode(str_replace(array("-", "_"), array("+", "/"), $_REQUEST["data"]));
		if ($sso_data === false)  SSO_EndpointError("Unable to decode data packet.");

		$sso_apikey_info["keyinfo"] = array("mode" => "", "key" => "", "opts" => array("mode" => "CBC"));
		if (strpos($sso_apikey_info["key"], ":") === false)
		{
			$sso_apikey_info["keyinfo"]["mode"] = "blowfish";
			$sso_apikey_info["keyinfo"]["key"] = pack("H*", substr($sso_apikey_info["key"], 0, -16));
			$sso_apikey_info["keyinfo"]["opts"]["iv"] = pack("H*", substr($sso_apikey_info["key"], -16));
		}
		else
		{
			$info = explode(":", $sso_apikey_info["key"]);
			if (count($info) < 3)  return array("success" => false, "error" => SSO_Translate("Invalid secret key."));

			$sso_apikey_info["keyinfo"]["mode"] = $info[0];
			$sso_apikey_info["keyinfo"]["key"] = pack("H*", $info[1]);
			$sso_apikey_info["keyinfo"]["opts"]["iv"] = pack("H*", $info[2]);

			if (count($info) >= 5)
			{
				$sso_apikey_info["keyinfo"]["opts"]["key2"] = pack("H*", $info[3]);
				$sso_apikey_info["keyinfo"]["opts"]["iv2"] = pack("H*", $info[4]);
			}

			unset($info);
		}
		$sso_apikey_info["keyinfo"]["opts"]["prefix"] = pack("H*", $sso_rng->GenerateToken());

		if ($sso_apikey_info["keyinfo"]["mode"] === "aes256")  $sso_data = ExtendedAES::ExtractDataPacket($sso_data, $sso_apikey_info["keyinfo"]["key"], $sso_apikey_info["keyinfo"]["opts"]);
		else  $sso_data = Blowfish::ExtractDataPacket($sso_data, $sso_apikey_info["keyinfo"]["key"], $sso_apikey_info["keyinfo"]["opts"]);

		if ($sso_data === false)  SSO_EndpointError("Unable to decrypt data packet.");

		$sso_data = @json_decode($sso_data, true);
		if ($sso_data === false)  SSO_EndpointError("Unable to extract data packet.");

		$sso_encrypted = true;

		// Check the data packet against submitted data.
		if (!isset($sso_data["ts"]) || !isset($sso_data["apikey"]) || $_REQUEST["apikey"] !== $sso_data["apikey"] || !isset($sso_data["action"]) || $_REQUEST["action"] !== $sso_data["action"] || !isset($sso_data["ver"]) || $_REQUEST["ver"] !== $sso_data["ver"])  SSO_EndpointError("Bad data packet.  Please use an official SSO client.");

		// Determine system clock drift.
		$sso_clockdrift = (isset($sso_settings[""]["clock_drift"]) ? $sso_settings[""]["clock_drift"] : 300);
		if (isset($sso_apikey_info["clock_drift"]) && $sso_apikey_info["clock_drift"] > 0)  $sso_clockdrift = $sso_apikey_info["clock_drift"];

		// Check the timestamp of the packet.  The default allows for 5 minutes of clock drift.
		$ts = time();
		$ts2 = CSDB::ConvertFromDBTime($sso_data["ts"]);
		if ($ts - $sso_clockdrift > $ts2 || $ts + $sso_clockdrift < $ts2)  SSO_EndpointError("Invalid data packet timestamp.");

		// Execute any endpoint hook that might exist.  Useful for creating obfuscated virtual endpoint URLs with .htaccess for creating external API keys.
		if (file_exists("endpoint_hook.php"))  require_once "endpoint_hook.php";

		// Clean up old sessions and IP address cache entries.
		if (!SSO_USING_CRON && $_REQUEST["action"] != "test" && !mt_rand(0, 9))
		{
			$sso_db->Query("DELETE", array($sso_db_temp_sessions, "WHERE" => "updated < ?"), CSDB::ConvertToDBTime(time() - 60 * 60));
			$sso_db->Query("DELETE", array($sso_db_temp_sessions, "WHERE" => "heartbeat = ? AND updated < ?"), SSO_HEARTBEAT_LIMIT, CSDB::ConvertToDBTime(time() - $sso_clockdrift));
			$sso_db->Query("DELETE", array($sso_db_user_sessions, "WHERE" => "updated < ?"), CSDB::ConvertToDBTime(time() - $sso_clockdrift));
			$sso_db->Query("DELETE", array($sso_db_ipcache, "WHERE" => "created < ?"), CSDB::ConvertToDBTime(time() - 24 * 60 * 60 * $sso_settings[""]["iprestrict"]["ip_cache_len"]));
		}

		// Process the action.
		if ($sso_data["action"] == "test")
		{
			$result = array(
				"success" => true
			);

			SSO_EndpointOutput($result);
		}
		else if ($sso_data["action"] == "canautologin")
		{
			if ($sso_apikey_info["type"] != "normal")  SSO_EndpointError("Invalid API key type.");

			if (!isset($sso_data["ns"]) || $sso_data["ns"] == "")  SSO_EndpointError("Namespace information not sent or not specified.");
			if (!isset($sso_settings[""]["expose_namespaces"]) || $sso_settings[""]["expose_namespaces"] < 1 || !isset($sso_settings[""]["namespacekey4"]))  SSO_EndpointError("Namespace exposure support is disabled at the server level.");

			$namespaces = SSO_LoadNamespaces(false, $sso_data["ns"]);

			if (!isset($namespaces[$sso_apirow->namespace]))  return false;

			$session_id = $namespaces[$sso_apirow->namespace];

			$sessionrow = $sso_db->GetRow("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "id = ? AND updated > ?",
			), $sso_db_user_sessions, $session_id, CSDB::ConvertToDBTime(time() - 5 * 60));

			if ($sessionrow === false)  SSO_EndpointError("Namespace referenced session is invalid or has expired.");

			// Namespace sign in is only allowed if the session is fully validated and the user is from the same IP address.
			$session_info = unserialize($sessionrow->info);
			if (!isset($session_info["validated"]))  SSO_EndpointError("Namespace referenced session is not validated.");
			if (!isset($session_info["ipaddr"]) || !isset($_REQUEST["ipaddr"]) || $session_info["ipaddr"] != $_REQUEST["ipaddr"])  SSO_EndpointError("Namespace referenced session is from an unspecified or different IP address.");

			$result = array(
				"success" => true
			);

			SSO_EndpointOutput($result);
		}
		else if ($sso_data["action"] == "initlogin")
		{
			if ($sso_apikey_info["type"] != "normal")  SSO_EndpointError("Invalid API key type.");

			// Create a new session.
			$sid = $sso_rng->GenerateString();
			$recoverid = $sso_rng->GenerateString();
			$info = array(
				"url" => base64_encode(isset($sso_data["url"]) ? $sso_data["url"] : ""),
				"files" => (bool)(int)(isset($sso_data["files"]) ? $sso_data["files"] : 0),
				"initmsg" => base64_encode(isset($sso_data["initmsg"]) ? $sso_data["initmsg"] : ""),
				"rid" => $recoverid,
				"appurl" => base64_encode(isset($sso_data["appurl"]) ? $sso_data["appurl"] : ""),
			);
			if ($info["url"] == "")  SSO_EndpointError("Return URL not specified.");

			$sso_db->Query("INSERT", array($sso_db_temp_sessions, array(
				"apikey_id" => $sso_apirow->id,
				"updated" => CSDB::ConvertToDBTime(time()),
				"created" => CSDB::ConvertToDBTime(time()),
				"heartbeat" => SSO_HEARTBEAT_LIMIT,
				"session_id" => $sid,
				"ipaddr" => (isset($_REQUEST["ipaddr"]) ? $_REQUEST["ipaddr"] : "APIKEY: " . $sso_ipaddr["ipv6"]),
				"info" => serialize($info),
				"recoverinfo" => base64_encode(isset($sso_data["info"]) ? $sso_data["info"] : ""),
			), "AUTO INCREMENT" => "id"));

			$id = $sso_db->GetInsertID();

			$url = SSO_LOGIN_URL . "?sso_id=" . urlencode($sid . "-" . $id) . "&lang=" . urlencode(isset($sso_data["lang"]) ? $sso_data["lang"] : "");
			if (isset($sso_data["extra"]))
			{
				foreach ($sso_data["extra"] as $key => $val)  $url .= "&" . urlencode($key) . "=" . urlencode($val);
			}

			$result = array(
				"success" => true,
				"url" => $url,
				"rid" => $recoverid
			);

			SSO_EndpointOutput($result);
		}
		else if ($sso_data["action"] == "setlogin")
		{
			if ($sso_apikey_info["type"] != "remote")  SSO_EndpointError("Invalid API key type.");

			// Load the user account.
			if (!isset($sso_data["sso_id"]))  SSO_EndpointError("Session ID expected.");
			if (!isset($sso_data["token"]))  SSO_EndpointError("Token expected.");
			if (!isset($sso_data["user_id"]) || $sso_data["user_id"] == "")  SSO_EndpointError("User ID expected.");
			if (!isset($sso_data["updateinfo"]))  SSO_EndpointError("Field map expected.");

			$sso_sessionrow = $sso_db->GetRow("SELECT", array(
				array("id", "apikey_id", "updated", "created", "heartbeat", "session_id", "info"),
				"FROM" => "?",
				"WHERE" => "id = ?",
			), $sso_db_temp_sessions, $sso_data["sso_id"]);

			if ($sso_sessionrow === false)  SSO_EndpointError("The session ID is invalid.  Most likely cause:  Expired.");

			$sso_session_info = unserialize($sso_sessionrow->info);
			if (isset($sso_session_info["setlogin_result"]))  SSO_EndpointError("The session ID is invalid.  Most likely cause:  Set login already called.");
			if (!isset($sso_session_info["setlogin_info"]))  SSO_EndpointError("The session ID is invalid.  Most likely cause:  Not remote enabled.");
			if ($sso_session_info["setlogin_info"]["apikey_id"] !== $sso_apirow->id)  SSO_EndpointError("The session ID is invalid.  Most likely cause:  Not remote enabled for this API key.");
			if (CSDB::ConvertFromDBTime($sso_session_info["setlogin_info"]["expires"]) < time())  SSO_EndpointError("The session ID is invalid.  Most likely cause:  Verification token expired.");
			if ($sso_session_info["setlogin_info"]["token"] !== $sso_data["token"])  SSO_EndpointError("The verification token is invalid.");

			// Check for a valid provider.
			$providers = SSO_GetProviderList();
			$sso_providers = array();
			if (!in_array($sso_session_info["setlogin_info"]["provider"], $providers))  SSO_EndpointError("The session ID maps to an invalid provider.");

			// Calculate API key field mapping.
			$protectedfields = array();
			$updateinfo = ($sso_data["updateinfo"] != "" ? @json_decode($sso_data["updateinfo"], true) : false);
			if (!is_array($updateinfo))  $updateinfo = false;
			foreach ($sso_apikey_info["field_map"] as $key => $info)
			{
				if ($info["perms"] == "rw")
				{
					if ($updateinfo !== false && isset($updateinfo[$info["name"]]))  $protectedfields[$key] = $updateinfo[$info["name"]];
				}
			}

			$sso_session_info["setlogin_result"] = array(
				"user_id" => $sso_data["user_id"],
				"protected_fields" => $protectedfields
			);
			if (!SSO_SaveSessionInfo())  SSO_EndpointError("Unable to save session information.");

			$url = $sso_session_info["setlogin_info"]["redirect_url"];
			$url .= (strpos($url, "?") === false ? "?" : "&") . "sso_setlogin_secret=" . urlencode($sso_session_info["setlogin_info"]["secret"]);

			$result = array(
				"success" => true,
				"url" => $url
			);

			SSO_EndpointOutput($result);
		}
		else if ($sso_data["action"] == "getlogin")
		{
			if ($sso_apikey_info["type"] != "normal")  SSO_EndpointError("Invalid API key type.");

			// Load the user account.
			if (!isset($sso_data["sso_id"]))  SSO_EndpointError("Session ID expected.");
			if (!isset($sso_data["expires"]))  SSO_EndpointError("Expiration expected.");
			if ((int)$sso_data["expires"] < $sso_clockdrift)  $sso_data["expires"] = $sso_clockdrift;

			// When 'sso_id2' is specified, the 'sso_id' is a token.
			// This approach allows the real session ID to never be seen by the user/browser.
			if (isset($sso_data["sso_id2"]))
			{
				$sso_session_id2 = explode("-", $sso_data["sso_id2"]);
				if (count($sso_session_id2) == 2)
				{
					$sso_sessionrow2 = $sso_db->GetRow("SELECT", array(
						"*",
						"FROM" => "?",
						"WHERE" => "id = ? AND apikey_id = ? AND session_id = ?",
					), $sso_db_temp_sessions, $sso_session_id2[1], $sso_apirow->id, $sso_session_id2[0]);

					if ($sso_sessionrow2 !== false)
					{
						$sso_session_info2 = unserialize($sso_sessionrow2->info);

						if (isset($sso_session_info2["new_id"]) && isset($sso_session_info2["new_id2"]) && $sso_data["sso_id"] == $sso_session_info2["new_id2"])
						{
							$sso_data["sso_id"] = $sso_session_info2["new_id"];
						}
					}
				}
			}

			$sso_session_id = explode("-", $sso_data["sso_id"]);
			if (count($sso_session_id) != 2)  SSO_EndpointError("Invalid session ID specified.");

			$sso_sessionrow = $sso_db->GetRow("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "id = ? AND apikey_id = ? AND session_id = ? AND updated > ?",
			), $sso_db_user_sessions, $sso_session_id[1], $sso_apirow->id, $sso_session_id[0], CSDB::ConvertToDBTime(time() - $sso_clockdrift));

			if ($sso_sessionrow === false)  SSO_EndpointError("The session ID is invalid.  Most likely cause:  Expired.");

			$sso_session_info = unserialize($sso_sessionrow->info);
			if (!$sso_session_info["validated"])  SSO_EndpointError("The session ID is not validated.");

			$sso_userrow = $sso_db->GetRow("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "id = ?",
			), $sso_db_users, $sso_sessionrow->user_id);

			if ($sso_userrow === false)  SSO_EndpointError("The session ID maps to an invalid user.");
			$sso_user_info = SSO_LoadDecryptedUserInfo($sso_userrow);

			$sso_user_tags = array();

			$result = $sso_db->Query("SELECT", array(
				"t.tag_name",
				"FROM" => "? AS ut, ? AS t",
				"WHERE" => "ut.tag_id = t.id AND ut.user_id = ? AND t.enabled = 1",
			), $sso_db_user_tags, $sso_db_tags, $sso_userrow->id);

			while ($row = $result->NextRow())
			{
				$sso_user_tags[$row->tag_name] = true;
			}

			if (isset($sso_user_tags[SSO_LOCKED_TAG]))  SSO_EndpointError("The session ID maps to an invalid user.");
			$siteadmin = isset($sso_user_tags[SSO_SITE_ADMIN_TAG]);
			unset($sso_user_tags[SSO_SITE_ADMIN_TAG]);

			// Load the provider and get the protected field list.
			$providers = SSO_GetProviderList();
			$sso_providers = array();
			if (!in_array($sso_userrow->provider_name, $providers))  SSO_EndpointError("The session ID maps to an invalid provider.");

			$sso_provider = $sso_userrow->provider_name;
			if (!isset($sso_settings[$sso_provider]))  $sso_settings[$sso_provider] = array();

			if (file_exists(SSO_ROOT_PATH . "/" . SSO_PROVIDER_PATH . "/" . $sso_provider . "/index.php"))  require_once SSO_ROOT_PATH . "/" . SSO_PROVIDER_PATH . "/" . $sso_provider . "/index.php";
			if (!class_exists($sso_provider))  SSO_EndpointError("The session ID maps to an invalid provider.");

			$sso_providers[$sso_provider] = new $sso_provider;
			$sso_providers[$sso_provider]->Init();
			$protectedfields = $sso_providers[$sso_provider]->GetProtectedFields();

			// Calculate API key field mapping.
			$fieldmap = array();
			$writable = array();
			$userupdated = false;
			$updateinfo = (isset($sso_data["updateinfo"]) && $sso_data["updateinfo"] != "" ? @json_decode($sso_data["updateinfo"], true) : false);
			if (!is_array($updateinfo))  $updateinfo = false;
			foreach ($sso_apikey_info["field_map"] as $key => $info)
			{
				if ($info["perms"] == "rw" && !isset($protectedfields[$info["name"]]))
				{
					$writable[$info["name"]] = true;
					if ($updateinfo !== false && isset($updateinfo[$info["name"]]))
					{
						$sso_user_info[$key] = $updateinfo[$info["name"]];

						$userupdated = true;
					}
				}

				$fieldmap[$info["name"]] = (isset($sso_user_info[$key]) ? $sso_user_info[$key] : "");
			}

			// Calculate API key tag mapping.
			$tagmap = array();
			foreach ($sso_apikey_info["tag_map"] as $key => $val)
			{
				if (isset($sso_user_tags[$key]))  $tagmap[$val] = true;
			}

			if (isset($sso_data["delete_old"]) && $sso_data["delete_old"] != 0)  $result = array("success" => false);
			else
			{
				$result = array(
					"success" => true,
					"sso_id" => $sso_data["sso_id"],
					"id" => $sso_userrow->id,
					"extra" => sha1($sso_data["apikey"] . ":" . $sso_userrow->session_extra),
					"field_map" => $fieldmap,
					"writable" => $writable,
					"tag_map" => $tagmap,
					"admin" => $siteadmin
				);
			}

			// Append recovery session information.  Ignore errors.
			if (isset($sso_data["sso_id2"]) && isset($sso_data["rid"]) && $sso_data["rid"] != "")
			{
				if (count($sso_session_id2) == 2 && $sso_sessionrow2 !== false)
				{
					if (isset($sso_session_info2["new_id"]) && isset($sso_session_info2["rid"]) && $sso_session_info2["new_id"] == $sso_data["sso_id"] && $sso_session_info2["rid"] == $sso_data["rid"])
					{
						if (!isset($sso_data["delete_old"]) || $sso_data["delete_old"] == 0)  $result["rinfo"] = base64_decode($sso_sessionrow2->recoverinfo);
						else
						{
							$sso_db->Query("DELETE", array($sso_db_temp_sessions, "WHERE" => "id = ?"), $sso_sessionrow2->id);

							$result = array("success" => true);
						}
					}
				}
			}

			if (!$result["success"])  SSO_EndpointError("Unable to delete the old session.");

			// Update the user session.
			if (!isset($sso_data["delete_old"]) || $sso_data["delete_old"] == 0)
			{
				$sso_db->Query("UPDATE", array($sso_db_user_sessions, array(
					"updated" => CSDB::ConvertToDBTime(time() + (int)$sso_data["expires"]),
				), "WHERE" => "id = ?"), $sso_sessionrow->id);
			}

			// Update the user account.
			if ($userupdated)
			{
				$info2 = $sso_user_info;
				$info3 = SSO_CreateEncryptedUserInfo($info2);

				$sso_db->Query("UPDATE", array($sso_db_users, array(
					"info" => serialize($info2),
					"info2" => $info3,
				), "WHERE" => "id = ?"), $sso_userrow->id);
			}

			SSO_EndpointOutput($result);
		}
		else if ($sso_data["action"] == "logout")
		{
			if ($sso_apikey_info["type"] != "normal")  SSO_EndpointError("Invalid API key type.");

			// Remove the session and all sessions in the same namespace.
			if (!isset($sso_data["sso_id"]))  SSO_EndpointError("Session ID expected.");

			$sso_session_id = explode("-", $sso_data["sso_id"]);
			if (count($sso_session_id) != 2)  SSO_EndpointError("Invalid session ID specified.");

			// Extract the current namespace.
			$row = $sso_db->GetRow("SELECT", array(
				"us.user_id, a.namespace",
				"FROM" => "? AS us, ? AS a",
				"WHERE" => "us.apikey_id = a.id AND us.id = ? AND us.apikey_id = ? AND us.session_id = ?",
			), $sso_db_user_sessions, $sso_db_apikeys, $sso_session_id[1], $sso_apirow->id, $sso_session_id[0]);

			if ($row !== false)
			{
				// Get all session IDs for the user that are in the same namespace.
				$ids = $sso_db->GetCol("SELECT", array(
					"us.id",
					"FROM" => "? AS us, ? AS a",
					"WHERE" => "us.apikey_id = a.id AND us.user_id = ? AND a.namespace = ?",
				), $sso_db_user_sessions, $sso_db_apikeys, $row->user_id, $row->namespace);

				// Delete all of the IDs.
				$sso_db->Query("DELETE", array($sso_db_user_sessions, "WHERE" => "id IN ('" . implode("','", $ids) . "')"));
			}

			// Delete the specific session (probably redundant).
			$sso_db->Query("DELETE", array($sso_db_user_sessions, "WHERE" => "id = ? AND apikey_id = ? AND session_id = ?"), $sso_session_id[1], $sso_apirow->id, $sso_session_id[0]);

			$result = array(
				"success" => true
			);

			SSO_EndpointOutput($result);
		}
		else if ($sso_apikey_info["type"] == "custom" && function_exists("EndpointHook_CustomHandler") && EndpointHook_CustomHandler())
		{
			// Do nothing.
		}
		else
		{
			SSO_EndpointError("Invalid action specified.  Please use an official SSO client/server combination.");
		}
	}
	catch (Exception $e)
	{
		SSO_EndpointError("Database query error.", $e->getMessage());
	}
?>