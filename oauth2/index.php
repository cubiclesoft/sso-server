<?php
	// OAuth2 integration endpoint shim.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	define("SSO_FILE", 1);
	define("SSO_MODE", "endpoint");

	require_once "../config.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/str_basics.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/page_basics.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/sso_functions.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/aes.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/blowfish.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/random.php";

	Str::ProcessAllInput();

	// Initialize the global CSPRNG instance.
	$sso_rng = new CSPRNG();

	// Timing attack defense.
	SSO_RandomSleep();

	// Calculate the remote IP address.
	$sso_ipaddr = SSO_GetRemoteIP();

	function SSO_DisplayError($msg, $htmlmsg = "")
	{
		global $sso_header, $sso_footer;

		// Bad request.
		http_response_code(400);

		header("Content-Type: text/html; charset=UTF-8");

		echo $sso_header;
		echo "<div class=\"sso_server_message_wrap" . ($htmlmsg == "" ? " sso_server_message_wrap_nosplit" : "") . "\"><div class=\"sso_server_error\">" . htmlspecialchars(BB_Translate($msg)) . "</div></div>";
		echo $htmlmsg;

		if (isset($_COOKIE["sso_server_lastapp"]) && $_COOKIE["sso_server_lastapp"] !== "")
		{
			$url = @base64_decode($_COOKIE["sso_server_lastapp"]);
			if ($url !== false)
			{
				echo "<div class=\"sso_main_info\"><a href=\"" . htmlspecialchars($url) . "\">" . htmlspecialchars(BB_Translate("Return to the application")) . "</a></div>";
			}
		}

		echo $sso_footer;

		exit();
	}

	// Stop proxies and browsers from caching these pages.
	header("Cache-Control: no-cache, no-store, max-age=0");
	header("Pragma: no-cache");
	header("Expires: -1");

	// Store the header and footer into variables.
	ob_start();
	if (file_exists(SSO_ROOT_PATH . "/header.php"))  require_once SSO_ROOT_PATH . "/header.php";
	$sso_header = ob_get_contents();
	ob_end_clean();

	ob_start();
	if (file_exists(SSO_ROOT_PATH . "/footer.php"))  require_once SSO_ROOT_PATH . "/footer.php";
	$sso_footer = ob_get_contents();
	ob_end_clean();

	if (SSO_USE_HTTPS && !BB_IsSSLRequest())  SSO_DisplayError("SSL expected.  Most likely cause:  Bad server configuration.");

	// Connect to the database and generate database globals.
	try
	{
		SSO_DBConnect(false);
	}
	catch (Exception $e)
	{
		SSO_DisplayError("Unable to connect to the database.");
	}

	// Load in $sso_settings and initialize it.
	SSO_LoadSettings();

	// Determine system clock drift.
	$sso_clockdrift = (isset($sso_settings[""]["clock_drift"]) ? $sso_settings[""]["clock_drift"] : 300);

//$fp = fopen(SSO_ROOT_PATH . "/debug.txt", "ab");
//fwrite($fp, "REQUEST:\n" . var_export($_REQUEST, true) . "\n\n");
//fwrite($fp, "GET:\n" . var_export($_GET, true) . "\n\n");
//fwrite($fp, "POST:\n" . var_export($_POST, true) . "\n\n");
//fwrite($fp, "COOKIE:\n" . var_export($_COOKIE, true) . "\n\n");
//fwrite($fp, "SERVER:\n" . var_export($_SERVER, true) . "\n\n");
//fwrite($fp, "------------------------------------------------------------------------------------------\n\n");

	// Determine what mode to run this script in based on the inputs.
	try
	{
		// Set 'access_token' to be the Bearer token when the header exists.
		if (isset($_SERVER["HTTP_AUTHORIZATION"]) && is_string($_SERVER["HTTP_AUTHORIZATION"]) && strncasecmp($_SERVER["HTTP_AUTHORIZATION"], "Bearer ", 7) == 0)
		{
			$_REQUEST["access_token"] = trim(substr($_SERVER["HTTP_AUTHORIZATION"], 7));
		}
		else if (function_exists("apache_request_headers"))
		{
			// Apache doesn't pass non-Basic Authorization headers by default.
			$headers = apache_request_headers();
			if (isset($headers["Authorization"]) && is_string($headers["Authorization"]) && strncasecmp($headers["Authorization"], "Bearer ", 7) == 0)
			{
				$_REQUEST["access_token"] = trim(substr($headers["Authorization"], 7));
			}
		}

		if (isset($_REQUEST["access_token"]) && is_string($_REQUEST["access_token"]))
		{
			// Step 4:  Return mapped user information.
			$info = @json_decode(base64_decode($_REQUEST["access_token"]), true);
			if (!is_array($info) || !isset($info["client_id"]) || !is_string($info["client_id"]) || !isset($info["sso_id"]) || !is_string($info["sso_id"]))  SSO_DisplayError("Invalid OAuth2 request.  Expected a valid 'access_token'.");

			// Parse the API key.
			$apikey = explode("-", $info["client_id"]);
			if (count($apikey) != 2)  SSO_DisplayError("Invalid OAuth2 request.  Expected a valid 'access_token'.");

			// Load the API key information.
			$sso_apirow = $sso_db->GetRow("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "id = ? AND apikey = ?",
			), $sso_db_apikeys, $apikey[1], $apikey[0]);

			if ($sso_apirow === false)  SSO_DisplayError("Invalid OAuth2 request.  Expected a valid 'access_token'.");
			$sso_apikey_info = SSO_LoadAPIKeyInfo(unserialize($sso_apirow->info));

			if ($sso_apikey_info["type"] != "normal")  SSO_DisplayError("Invalid OAuth2 request.  The API key's Type is not valid.");

			// Adjust clock drift.
			if ($sso_apikey_info["clock_drift"] > 0)  $sso_clockdrift = $sso_apikey_info["clock_drift"];

			// Load the session information.
			$sso_session_id = explode("-", $info["sso_id"]);
			if (count($sso_session_id) != 2)  SSO_DisplayError("Invalid session ID specified.");

			$sso_sessionrow = $sso_db->GetRow("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "id = ? AND apikey_id = ? AND session_id = ? AND updated > ?",
			), $sso_db_user_sessions, $sso_session_id[1], $sso_apirow->id, $sso_session_id[0], CSDB::ConvertToDBTime(time() - $sso_clockdrift));

			if ($sso_sessionrow === false)  SSO_DisplayError("The session ID is invalid.  Most likely cause:  Expired.");

			$sso_session_info = unserialize($sso_sessionrow->info);
			if (!$sso_session_info["validated"])  SSO_DisplayError("The session ID is not validated.");

			// Load the user information.
			$sso_userrow = $sso_db->GetRow("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "id = ?",
			), $sso_db_users, $sso_sessionrow->user_id);

			if ($sso_userrow === false)  SSO_DisplayError("The session ID maps to an invalid user.");
			$sso_user_info = SSO_LoadDecryptedUserInfo($sso_userrow);

			// Verify that the account is not locked out.
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

			if (isset($sso_user_tags[SSO_LOCKED_TAG]))  SSO_DisplayError("The session ID maps to an invalid user.");

			// Generate the final output.
			$result = array("id" => 0);
			foreach ($sso_apikey_info["field_map"] as $key => $info)
			{
				$result[$info["name"]] = (isset($sso_user_info[$key]) ? $sso_user_info[$key] : "");
			}
			$result["id"] = $sso_userrow->id;

			// Apply static fields.
			$lines = explode("\n", $sso_apikey_info["static_field_map"]);
			foreach ($lines as $line)
			{
				$pos = strpos($line, "=");
				if ($pos !== false)  $result[trim(substr($line, 0, $pos))] = trim(substr($line, $pos + 1));
			}

			foreach ($sso_apikey_info["tag_map"] as $key => $val)
			{
				if (isset($sso_user_tags[$key]))  $result["tag:" . $val] = true;
			}

			// Send the client the API key mapped fields.
			header("Content-Type: application/json");

			echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
//fwrite($fp, "Returning:  " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
//fwrite($fp, "------------------------------------------------------------------------------------------\n\n");
		}
		else if (isset($_POST["client_secret"]) && is_string($_REQUEST["client_secret"]))
		{
			// Step 3:  Exchange the temporary session token for the real session token.
			if (!isset($_REQUEST["code"]) || !is_string($_REQUEST["code"]))  SSO_DisplayError("Invalid OAuth2 request.  Expected a 'code'.");
			if (!isset($_REQUEST["client_id"]) || !is_string($_REQUEST["client_id"]))  SSO_DisplayError("Invalid OAuth2 request.  Expected a 'client_id'.");
			if (!isset($_REQUEST["redirect_uri"]) || !is_string($_REQUEST["redirect_uri"]))  SSO_DisplayError("Invalid OAuth2 request.  Expected a 'redirect_uri'.");
			if (!isset($_REQUEST["grant_type"]) || $_REQUEST["grant_type"] !== "authorization_code")  SSO_DisplayError("Invalid OAuth2 request.  Expected a 'grant_type' of 'authorization_code'.");

			// Parse the API key.
			$apikey = explode("-", $_REQUEST["client_id"]);
			if (count($apikey) != 2)  SSO_DisplayError("Invalid OAuth2 request.  Expected a valid 'client_id'.");

			// Load the API key information.
			$sso_apirow = $sso_db->GetRow("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "id = ? AND apikey = ?",
			), $sso_db_apikeys, $apikey[1], $apikey[0]);

			if ($sso_apirow === false)  SSO_DisplayError("Invalid OAuth2 request.  Expected a valid 'client_id'.");
			$sso_apikey_info = SSO_LoadAPIKeyInfo(unserialize($sso_apirow->info));

			if ($sso_apikey_info["type"] != "normal")  SSO_DisplayError("Invalid OAuth2 request.  The API key's Type is not valid.");

			// Verify the client secret.
			if (hash_hmac((function_exists("hash_algos") && in_array("sha256", hash_algos()) ? "sha256" : "sha1"), $sso_apirow->apikey . "-" . $sso_apirow->id, $sso_apikey_info["key"]) !== $_REQUEST["client_secret"])  SSO_DisplayError("Invalid OAuth2 request.  The 'client_secret' is not valid.");

			// Adjust clock drift.
			if ($sso_apikey_info["clock_drift"] > 0)  $sso_clockdrift = $sso_apikey_info["clock_drift"];

			// Parse the code and load the temporary session information.
			$info = @json_decode(base64_decode($_REQUEST["code"]), true);
			if (!is_array($info) || !isset($info["sso_id"]) || !is_string($info["sso_id"]) || !isset($info["sso_id2"]) || !is_string($info["sso_id2"]))  SSO_DisplayError("Invalid OAuth2 request.  Expected a valid 'code'.");

			$sso_session_id2 = explode("-", $info["sso_id2"]);
			if (count($sso_session_id2) != 2)  SSO_DisplayError("Invalid 'code' specified.");

			$sso_sessionrow2 = $sso_db->GetRow("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "id = ? AND apikey_id = ? AND session_id = ?",
			), $sso_db_temp_sessions, $sso_session_id2[1], $sso_apirow->id, $sso_session_id2[0]);

			if ($sso_sessionrow2 === false)  SSO_DisplayError("Invalid OAuth2 'code' specified.  The code was possibly reused.");
			$sso_session_info2 = unserialize($sso_sessionrow2->info);
			if (!isset($sso_session_info2["new_id"]) || !isset($sso_session_info2["new_id2"]) || $info["sso_id"] !== $sso_session_info2["new_id2"])  SSO_DisplayError("Invalid OAuth2 'code' specified.");

			// Confirm the redirect URI as per the spec.
			$rinfo = @json_decode(base64_decode($sso_sessionrow2->recoverinfo), true);
			if (!is_array($rinfo) || !isset($rinfo["redirect_uri"]))  SSO_DisplayError("Session ID is missing redirect information.");

			if ($rinfo["redirect_uri"] !== $_REQUEST["redirect_uri"])  SSO_DisplayError("Invalid OAuth2 request.  The 'redirect_uri' does not match the original 'redirect_uri'.");

			// Load the real session.
			$sso_session_id = explode("-", $sso_session_info2["new_id"]);
			if (count($sso_session_id) != 2)  SSO_DisplayError("Invalid session ID specified.");

			$sso_sessionrow = $sso_db->GetRow("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "id = ? AND apikey_id = ? AND session_id = ? AND updated > ?",
			), $sso_db_user_sessions, $sso_session_id[1], $sso_apirow->id, $sso_session_id[0], CSDB::ConvertToDBTime(time() - $sso_clockdrift));

			if ($sso_sessionrow === false)  SSO_DisplayError("The session ID is invalid.  Most likely cause:  Expired.");

			$sso_session_info = unserialize($sso_sessionrow->info);
			if (!$sso_session_info["validated"])  SSO_DisplayError("The session ID is not validated.");

			// Delete the temporary session to prevent code reuse.
			$sso_db->Query("DELETE", array($sso_db_temp_sessions, "WHERE" => "id = ?"), $sso_sessionrow2->id);

			// Send the client an access token.
			header("Content-Type: application/json");

			$result = array(
				"access_token" => base64_encode(json_encode(array("client_id" => $_REQUEST["client_id"], "sso_id" => $sso_session_info2["new_id"]), JSON_UNESCAPED_SLASHES)),
				"expires_in" => 3600,
				"scope" => "https://sso-server/api-key",
				"token_type" => "Bearer"
			);

			echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
//fwrite($fp, "Returning:  " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
//fwrite($fp, "------------------------------------------------------------------------------------------\n\n");
		}
		else if (isset($_REQUEST["from_sso_server"]) && isset($_REQUEST["sso_id"]) && is_string($_REQUEST["sso_id"]) && isset($_REQUEST["sso_id2"]) && is_string($_REQUEST["sso_id2"]))
		{
			// Step 2:  Load the recovery info in order to redirect back to the application.
			$sso_session_id2 = explode("-", $_REQUEST["sso_id2"]);
			if (count($sso_session_id2) != 2)  SSO_DisplayError("Invalid session ID specified.");

			$sso_sessionrow2 = $sso_db->GetRow("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "id = ? AND session_id = ?",
			), $sso_db_temp_sessions, $sso_session_id2[1], $sso_session_id2[0]);

			if ($sso_sessionrow2 === false)  SSO_DisplayError("Invalid session ID specified.");
			$sso_session_info2 = unserialize($sso_sessionrow2->info);
			if (!isset($sso_session_info2["new_id"]) || !isset($sso_session_info2["new_id2"]) || $_REQUEST["sso_id"] !== $sso_session_info2["new_id2"])  SSO_DisplayError("Invalid session ID token specified.");

			$rinfo = @json_decode(base64_decode($sso_sessionrow2->recoverinfo), true);
			if (!is_array($rinfo) || !isset($rinfo["redirect_uri"]))  SSO_DisplayError("Session ID is missing redirect information.");

			$url = $rinfo["redirect_uri"];
			$url .= (strpos($url, "?") === false ? "?" : "&") . "code=" . urlencode(base64_encode(json_encode(array("sso_id" => $_REQUEST["sso_id"], "sso_id2" => $_REQUEST["sso_id2"]), JSON_UNESCAPED_SLASHES)));
			if (isset($rinfo["state"]))  $url .= "&state=" . urlencode($rinfo["state"]);

			header("Location: " . $url);
//fwrite($fp, "Redirecting to:  " . $url . "\n");
//fwrite($fp, "------------------------------------------------------------------------------------------\n\n");
		}
		else if (isset($_GET["client_id"]) && is_string($_REQUEST["client_id"]))
		{
			// Step 1:  Establish a new SSO session and redirect to the frontend.
			if (!isset($_REQUEST["response_type"]) || $_REQUEST["response_type"] !== "code")  SSO_DisplayError("Invalid OAuth2 request.  Expected a 'response_type' of 'code'.");
			if (!isset($_REQUEST["redirect_uri"]) || !is_string($_REQUEST["redirect_uri"]))  SSO_DisplayError("Invalid OAuth2 request.  Expected a 'redirect_uri'.");
			$_REQUEST["redirect_uri"] = str_replace(array("\r", "\n"), "", $_REQUEST["redirect_uri"]);
			if ($_REQUEST["redirect_uri"] == "")  SSO_DisplayError("Invalid OAuth2 request.  Expected a 'redirect_uri'.");

			// Parse the API key.
			$apikey = explode("-", $_REQUEST["client_id"]);
			if (count($apikey) != 2)  SSO_DisplayError("Invalid OAuth2 request.  Expected a valid 'client_id'.");

			// Load the API key information.
			$sso_apirow = $sso_db->GetRow("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "id = ? AND apikey = ?",
			), $sso_db_apikeys, $apikey[1], $apikey[0]);

			if ($sso_apirow === false)  SSO_DisplayError("Invalid OAuth2 request.  Expected a valid 'client_id'.");
			$sso_apikey_info = SSO_LoadAPIKeyInfo(unserialize($sso_apirow->info));

			if ($sso_apikey_info["type"] != "normal")  SSO_DisplayError("Invalid OAuth2 request.  The API key's Type is not valid.");

			// Check the redirect URI against the list of allowed redirect URIs.
			$redirects = explode("\n", $sso_apikey_info["oauth2_redirects"]);
			if (!in_array($_REQUEST["redirect_uri"], $redirects))  SSO_DisplayError("Invalid OAuth2 request.  The specified 'redirect_uri' is not in the whitelist of allowed redirects.");

			// Create a new session.
			$sid = $sso_rng->GenerateString();
			$recoverid = $sso_rng->GenerateString();
			$info = array(
				"url" => base64_encode(SSO_LOGIN_URL . "oauth2/"),
				"files" => false,
				"initmsg" => base64_encode(isset($_REQUEST["initmsg"]) ? $_REQUEST["initmsg"] : ""),
				"rid" => $recoverid,
				"appurl" => base64_encode(isset($_REQUEST["appurl"]) ? $_REQUEST["appurl"] : ""),
			);

			$rinfo = array(
				"redirect_uri" => $_REQUEST["redirect_uri"]
			);

			if (isset($_REQUEST["state"]) && is_string($_REQUEST["state"]))  $rinfo["state"] = $_REQUEST["state"];

			$rinfo = json_encode($rinfo, JSON_UNESCAPED_SLASHES);

			$sso_db->Query("INSERT", array($sso_db_temp_sessions, array(
				"apikey_id" => $sso_apirow->id,
				"updated" => CSDB::ConvertToDBTime(time()),
				"created" => CSDB::ConvertToDBTime(time()),
				"heartbeat" => SSO_HEARTBEAT_LIMIT,
				"session_id" => $sid,
				"ipaddr" => "OAuth2: " . $sso_ipaddr["ipv6"],
				"info" => serialize($info),
				"recoverinfo" => base64_encode($rinfo),
			), "AUTO INCREMENT" => "id"));

			$id = $sso_db->GetInsertID();

			$url = SSO_LOGIN_URL . "?sso_id=" . urlencode($sid . "-" . $id) . "&lang=" . urlencode(isset($_REQUEST["lang"]) ? $_REQUEST["lang"] : "");
			if (!isset($_REQUEST["use_namespaces"]) || $_REQUEST["use_namespaces"] == 0)  $url .= "&use_namespaces=0";

			header("Location: " . $url);
//fwrite($fp, "Redirecting to:  " . $url . "\n");
//fwrite($fp, "------------------------------------------------------------------------------------------\n\n");
		}
		else
		{
			// Slightly intentionally misleading display string.
			SSO_DisplayError("Unable to determine OAuth2 processing mode.  Expected 'access_token', 'client_id', or 'client_secret'.  See the OAuth2 specification for proper interaction with OAuth2.");
		}
	}
	catch (Exception $e)
	{
		SSO_DisplayError("Database query error.", $e->getMessage());
	}
?>