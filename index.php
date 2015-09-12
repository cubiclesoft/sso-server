<?php
	// SSO Server frontend.
	// (C) 2015 CubicleSoft.  All Rights Reserved.

	define("SSO_FILE", 1);
	define("SSO_MODE", "frontend");

	require_once "config.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/str_basics.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/page_basics.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/sso_functions.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/blowfish.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/aes.php";
	if (!ExtendedAES::IsMcryptAvailable())  require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/phpseclib/AES.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/random.php";

	Str::ProcessAllInput();

	// Initialize the global CSPRNG instance.
	$sso_rng = new CSPRNG();

	// Timing attack defense.
	SSO_RandomSleep();

	// Calculate the remote IP address.
	$sso_ipaddr = SSO_GetRemoteIP();

	// Initialize language settings.
	BB_InitLangmap(SSO_ROOT_PATH . "/" . SSO_LANG_PATH . "/", SSO_DEFAULT_LANG);
	if (isset($_REQUEST["lang"]) && $_REQUEST["lang"] == "")  unset($_REQUEST["lang"]);
	if (isset($_REQUEST["lang"]))  BB_SetLanguage(SSO_ROOT_PATH . "/" . SSO_LANG_PATH . "/", $_REQUEST["lang"]);

	function SSO_DisplayError($msg, $htmlmsg = "")
	{
		global $sso_header, $sso_footer;

		if (isset($_REQUEST["sso_ajax"]))  echo htmlspecialchars(BB_Translate($msg)) . $htmlmsg;
		else
		{
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
		}

		exit();
	}

	function SSO_OutputHeartbeat()
	{
		global $sso_db, $sso_db_temp_sessions, $sso_sessionrow, $sso_session_info, $sso_indexphp;

		if ($sso_session_info["initmsg"] != "" || $sso_session_info["files"])
		{
			$sso_session_info["initmsg"] = "";
			$sso_session_info["files"] = 0;
			SSO_SaveSessionInfo();
		}

		if ($sso_sessionrow->heartbeat > 0)
		{
			$sso_db->Query("UPDATE", array($sso_db_temp_sessions, array(
				"updated" => CSDB::ConvertToDBTime(time()),
			), array(
				"heartbeat" => "heartbeat - 1",
			), "WHERE" => "id = ? AND heartbeat > 0"), $sso_sessionrow->id);
		}
?>
<script type="text/javascript">
if (typeof(window.jQuery) == 'undefined')
{
	document.write('<' + 'script type="text/javascript" src="<?php echo htmlspecialchars(SSO_ROOT_URL . "/" . SSO_SUPPORT_PATH . "/jquery-1.11.0.min.js"); ?>" /' + '><' + '/script' + '>');
	document.write('<' + 'script type="text/javascript"' + '>jQuery.noConflict();<' + '/script' + '>');
}
</script>
<script type="text/javascript">
function SSO_Heartbeat() {
	jQuery('#sso_heartbeat').load('<?php echo SSO_ROOT_URL . "/" . $sso_indexphp; ?>', { 'sso_ajax' : 1, 'sso_id' : '<?php echo htmlspecialchars(BB_JSSafe($_REQUEST["sso_id"])); ?>', 'sso_action' : 'sso_heartbeat' });
}

jQuery(function() {
	setInterval(SSO_Heartbeat, 3300000);
});
</script>
<div id="sso_heartbeat" style="display: none;"></div>
<?php
	}

	function SSO_FrontendField($name)
	{
		global $sso_session_id, $sso_apikey;

		return "sso_" . hash_hmac("md5", $sso_session_id[0] . ":" . $name, SSO_BASE_RAND_SEED2 . ":" . $sso_apikey);
	}

	function SSO_FrontendFieldValue($name, $default = false)
	{
		$name = SSO_FrontendField($name);

		return (isset($_REQUEST[$name]) ? UTF8::MakeValid($_REQUEST[$name]) : $default);
	}

	// The system expects UTF-8 inputs.  Attempt to force it here.
	header("Content-Type: text/html; charset=UTF-8");

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

	// Connect to the database and generate database globals.
	try
	{
		SSO_DBConnect(false);
	}
	catch (Exception $e)
	{
		SSO_DisplayError("Unable to connect to the database.");
	}

	// Load in fields without admin select.
	SSO_LoadFields(false);

	// Load in $sso_settings and initialize it.
	SSO_LoadSettings();

	if (SSO_USE_HTTPS && !BB_IsSSLRequest())  SSO_DisplayError("SSL expected.  Most likely cause:  Bad server configuration.");

	if (!isset($_REQUEST["sso_id"]) && isset($_COOKIE["sso_server_id"]))  $_REQUEST["sso_id"] = $_COOKIE["sso_server_id"];
	if (!isset($_REQUEST["sso_id"]))  SSO_DisplayError("Session ID expected.  Most likely causes:  Pressing the back button, clicking a URL that launched a new web browser, using a non-offical client, or a bad or incorrectly configured web proxy.  If you clicked a URL in an e-mail, it opened a new web browser, and you got this error, then try this solution:  Copy the URL and paste it into the address bar of the other web browser.  Sorry for the inconvenience, but this behavior helps keep your account secure from hackers.");

	// Migrate 'sso_id' to a cookie.
	if (!isset($_COOKIE["sso_server_id"]) || $_COOKIE["sso_server_id"] != $_REQUEST["sso_id"])
	{
		SetCookieFixDomain("sso_server_id", $_REQUEST["sso_id"], 0, "", "", SSO_IsSSLRequest(), true);
	}

	// Remove 'sso_id' from browser URL to reduce URL sharing vulnerabilities.
	if (isset($_GET["sso_id"]) && isset($_SERVER["QUERY_STRING"]))
	{
		$url = BB_GetFullRequestURLBase();
		$qstr = explode("&", $_SERVER["QUERY_STRING"]);
		foreach ($qstr as $num => $opt)
		{
			if (substr($opt, 0, 7) == "sso_id=")  unset($qstr[$num]);
		}
		$qstr = implode("&", $qstr);
		if ($qstr != "")  $url .= "?" . $qstr;

		header("Location: " . $url);
		exit();
	}

	$sso_session_id = explode("-", $_REQUEST["sso_id"]);
	if (count($sso_session_id) != 2)  SSO_DisplayError("Invalid session ID specified.");

	try
	{
		// Load/Create IP address information.
		$row = $sso_db->GetRow("SELECT", array(
			"*",
			"FROM" => "?",
			"WHERE" => "ipaddr = ?",
		), $sso_db_ipcache, $sso_ipaddr["ipv6"]);

		if ($row !== false)
		{
			$sso_ipaddr_id = $row->id;
			$sso_ipaddr_info = @unserialize($row->info);
			if ($sso_ipaddr_info === false)  $sso_ipaddr_info = array();
		}
		else
		{
			$sso_db->Query("INSERT", array($sso_db_ipcache, array(
				"ipaddr" => $sso_ipaddr["ipv6"],
				"created" => CSDB::ConvertToDBTime(time()),
				"info" => serialize(array()),
			), "AUTO INCREMENT" => "id"));

			$sso_ipaddr_id = $sso_db->GetInsertID();
			$sso_ipaddr_info = array();
		}
	}
	catch (Exception $e)
	{
		SSO_DisplayError("A database error has occurred.  Most likely cause:  Bad SQL query.");
	}

	// Load providers.
	$providers = SSO_GetProviderList();
	$sso_providers = array();
	foreach ($providers as $sso_provider)
	{
		if (!isset($sso_settings[$sso_provider]))  $sso_settings[$sso_provider] = array();

		require_once SSO_ROOT_PATH . "/" . SSO_PROVIDER_PATH . "/" . $sso_provider . "/index.php";
		if (class_exists($sso_provider))
		{
			$sso_providers[$sso_provider] = new $sso_provider;
			$sso_providers[$sso_provider]->Init();
			if (!$sso_providers[$sso_provider]->IsEnabled())  unset($sso_providers[$sso_provider]);
		}
	}
	if (!count($sso_providers))
	{
		if (!isset($sso_settings[""]["no_providers_msg"]))  $sso_settings[""]["no_providers_msg"] = "";
		$message = $sso_settings[""]["no_providers_msg"];

		$details = array();

		if (isset($sso_ipaddr_info["spaminfo"]))
		{
			foreach ($sso_ipaddr_info["spaminfo"] as $provider => $spaminfo)
			{
				if ($spaminfo["spammer"])
				{
					foreach ($spaminfo["reasons"] as $num => $reason)
					{
						$details[$reason] = $reason;
					}
				}
			}
		}

		$message = str_replace("@BLOCKDETAILS@", (count($details) ? "<p><b>" . htmlspecialchars(BB_Translate("Additional Details")) . "</b></p><p>" . htmlspecialchars(BB_Translate("Your IP Address:  %s", ($sso_ipaddr["ipv4"] != "" ? $sso_ipaddr["ipv4"] : $sso_ipaddr["shortipv6"]))) . "</p><p>" . str_replace("\n", "<br />", htmlspecialchars(implode("\n", $details))) . "</p>" : "<p><i>" . htmlspecialchars(BB_Translate("No additional details are available.")) . "</i></p>"), $message);

		SSO_DisplayError("This system does not have any active providers.  Either no providers have been configured or your current location has been blocked.", $message);
	}
	if (isset($_REQUEST["sso_provider"]) && !isset($sso_providers[$_REQUEST["sso_provider"]]))  unset($_REQUEST["sso_provider"]);
	if (count($sso_providers) == 1)
	{
		$_REQUEST["sso_provider"] = array_keys($sso_providers);
		$_REQUEST["sso_provider"] = $_REQUEST["sso_provider"][0];
	}

	try
	{
		$sso_sessionrow = $sso_db->GetRow("SELECT", array(
			array("id", "apikey_id", "updated", "created", "heartbeat", "session_id", "info"),
			"FROM" => "?",
			"WHERE" => "id = ? AND session_id = ?",
		), $sso_db_temp_sessions, $sso_session_id[1], $sso_session_id[0]);

		if ($sso_sessionrow === false)  SSO_DisplayError("The session ID is invalid.  Most likely cause:  Expired.");

		$sso_session_info = unserialize($sso_sessionrow->info);

		$sso_apirow = $sso_db->GetRow("SELECT", array(
			"*",
			"FROM" => "?",
			"WHERE" => "id = ?",
		), $sso_db_apikeys, $sso_sessionrow->apikey_id);

		if ($sso_apirow === false)  SSO_DisplayError("The session ID is invalid.  Most likely cause:  Client API key revoked.");
		$sso_apikey = $sso_apirow->apikey;
		$sso_apikey_info = unserialize($sso_apirow->info);

		if (function_exists("FrontendHook_PreHeaderMessage"))  FrontendHook_PreHeaderMessage();

		// Display an incoming message to the user if they ever see a user interface.
		if ($sso_session_info["initmsg"] != "" || $sso_session_info["files"])
		{
			$sso_header .= "\n<div class=\"sso_server_message_wrap\">";

			$initmsg = base64_decode($sso_session_info["initmsg"]);
			if ($initmsg == "insufficient_permissions")  $sso_header .= "<div class=\"sso_server_error\">" . htmlspecialchars(BB_Translate("Your account has insufficient permissions to access that resource.")) . "</div>";
			else if ($initmsg != "")  $sso_header .= "<div class=\"sso_server_error\">" . htmlspecialchars(BB_Translate($initmsg)) . "</div>";

			if ($sso_session_info["files"])  $sso_header .= "<div class=\"sso_server_warning\">" . BB_Translate("Uploaded files will have to be uploaded again.") . "</div>";

			$sso_header .= "</div>\n";
		}

		// Pick up the user's application and store it in a cookie in case they click the back button.
		if (!isset($_COOKIE["sso_server_lastapp"]) || $_COOKIE["sso_server_lastapp"] != $sso_session_info["appurl"])
		{
			SetCookieFixDomain("sso_server_lastapp", $sso_session_info["appurl"], 0, "", "", SSO_IsSSLRequest(), true);
		}

		$sso_indexphp = (isset($sso_settings[""]["hide_index"]) && $sso_settings[""]["hide_index"] ? "" : "index.php");

		// XSS/XSRF defense for same-origin iframes.
		if (!isset($_REQUEST["sso_action"]) || $_REQUEST["sso_action"] != "sso_iframe_error")
		{
			ob_start();
?>
<script type="text/javascript">
try
{
	if (window.self !== window.top)  window.location.href = '<?php echo BB_JSSafe(BB_GetRequestHost() . SSO_ROOT_URL . "/" . $sso_indexphp . "?sso_action=sso_iframe_error" . (isset($_REQUEST["lang"]) ? "&lang=" . urlencode($_REQUEST["lang"]) : "")); ?>';
	if (window.self !== window.parent)  window.location.href = '<?php echo BB_JSSafe(BB_GetRequestHost() . SSO_ROOT_URL . "/" . $sso_indexphp . "?sso_action=sso_iframe_error" . (isset($_REQUEST["lang"]) ? "&lang=" . urlencode($_REQUEST["lang"]) : "")); ?>';
}
catch (ex)
{
}
</script>
<?php
			$sso_header .= ob_get_contents();
			ob_end_clean();
		}

		if (function_exists("FrontendHook_PostHeaderMessage"))  FrontendHook_PostHeaderMessage();

		if (isset($_REQUEST["sso_action"]) && $_REQUEST["sso_action"] == "sso_heartbeat")
		{
			$sso_db->Query("UPDATE", array($sso_db_temp_sessions, array(
				"updated" => CSDB::ConvertToDBTime(time()),
			), array(
				"heartbeat" => "heartbeat - 1",
			), "WHERE" => "id = ? AND heartbeat > 0"), $sso_sessionrow->id);

			echo "OK";
		}
		else if (isset($_REQUEST["sso_action"]) && $_REQUEST["sso_action"] == "sso_iframe_error")
		{
			SSO_DisplayError("You have been redirected to this page in order to prevent your web browser from giving away your sign in information to an untrusted third party.  Please contact this web server's admin about this issue as it is possible that this web server has been compromised.  Most likely cause:  An SSO server page was loaded via an embedded iframe.");
		}
		else if (isset($_REQUEST["sso_action"]) && $_REQUEST["sso_action"] == "sso_redirect")
		{
			if (!isset($_COOKIE["sso_server_er"]) || !isset($_COOKIE["sso_server_ern"]) || $_COOKIE["sso_server_ern"] !== md5(SSO_FrontendField("external_redirect") . ":" . base64_decode($_COOKIE["sso_server_er"])))  SSO_DisplayError("Valid redirect expected.  Most likely cause:  Invalid cookies.");

			header("Location: " . base64_decode($_COOKIE["sso_server_er"]));

			SetCookieFixDomain("sso_server_er", "", 0, "", "", SSO_IsSSLRequest(), true);
			SetCookieFixDomain("sso_server_ern", "", 0, "", "", SSO_IsSSLRequest(), true);

			if (isset($_REQUEST["sso_final"]) && $_REQUEST["sso_final"] > 0)
			{
				// Delete the temporary session cookies.
				SetCookieFixDomain("sso_server_id", "", 1, "", "", SSO_IsSSLRequest(), true);
				SetCookieFixDomain("sso_server_id2", "", 1, "", "", SSO_IsSSLRequest(), true);
			}
		}
		else if (isset($_REQUEST["sso_action"]) && $_REQUEST["sso_action"] == "sso_validate")
		{
			// Load the user account.
			if (!isset($_COOKIE["sso_server_id2"]))  SSO_DisplayError("New session ID expected.  Most likely cause:  Cookies are disabled or bad provider.");

			$sso_session_id2 = explode("-", $_COOKIE["sso_server_id2"]);
			if (count($sso_session_id2) != 2)  SSO_DisplayError("Invalid session ID specified.");

			if (!isset($sso_session_info["new_id"]) || $sso_session_info["new_id"] !== $_COOKIE["sso_server_id2"])  SSO_DisplayError("The new session ID maps to a different session.  Most likely cause:  Bad provider.");

			$sso_sessionrow2 = $sso_db->GetRow("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "id = ? AND session_id = ?",
			), $sso_db_user_sessions, $sso_session_id2[1], $sso_session_id2[0]);

			if ($sso_sessionrow2 === false)  SSO_DisplayError("The new session ID is invalid.  Most likely cause:  Expired.");

			$sso_session_info2 = unserialize($sso_sessionrow2->info);
			if ($sso_session_info2["validated"])  SSO_DisplayError("The new session ID is already validated.");
			$sso_automate = $sso_session_info2["automate"];

			$sso_userrow = $sso_db->GetRow("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "id = ?",
			), $sso_db_users, $sso_sessionrow2->user_id);

			if ($sso_userrow === false)  SSO_DisplayError("The new session ID maps to an invalid user.  Most likely cause:  Internal error.");

			$sso_provider = $sso_userrow->provider_name;
			if (!isset($sso_providers[$sso_provider]))  SSO_DisplayError("The session ID maps to an invalid provider.");

			$sso_protectedfields = $sso_providers[$sso_provider]->GetProtectedFields();

			$sso_user_info = SSO_LoadDecryptedUserInfo($sso_userrow);

			// Load API key field mapping.
			$sso_missingfields = array();
			foreach ($sso_apikey_info["field_map"] as $key => $info)
			{
				if (!isset($sso_user_info[$key]) && (!isset($sso_protectedfields[$key]) || !$sso_protectedfields[$key]))  $sso_missingfields[$key] = $key;
			}

			$sso_target_url = SSO_ROOT_URL . "/" . $sso_indexphp . "?sso_action=sso_validate" . (isset($_REQUEST["lang"]) ? "&lang=" . urlencode($_REQUEST["lang"]) : "");

			// A developer can optionally hook into the SSO server here.
			// The "version" of the user account is checked and the user updates their account prior to continuing.
			if (file_exists(SSO_ROOT_PATH . "/index_hook.php"))  require_once SSO_ROOT_PATH . "/index_hook.php";
			else
			{
				SSO_ValidateUser();

				SSO_DisplayError("Unable to validate the new session.  Most likely cause:  Internal error.");
			}
		}
		else if (isset($sso_session_info["new_id"]))
		{
			// Redirect to validation.
			header("Location: " . BB_GetRequestHost() . SSO_ROOT_URL . "/" . $sso_indexphp . "?sso_action=sso_validate" . (isset($_REQUEST["lang"]) ? "&lang=" . urlencode($_REQUEST["lang"]) : ""));
			exit();
		}
		else if (isset($_REQUEST["sso_provider"]) && isset($sso_providers[$_REQUEST["sso_provider"]]))
		{
			SSO_ActivateImpersonationUser();
			SSO_ActivateNamespaceUser();

			$sso_provider = $_REQUEST["sso_provider"];
			$sso_selectors_url = SSO_ROOT_URL . "/" . $sso_indexphp . "" . (isset($_REQUEST["lang"]) ? "?lang=" . urlencode($_REQUEST["lang"]) : "");
			$sso_target_url = SSO_ROOT_URL . "/" . $sso_indexphp . "?sso_provider=" . urlencode($sso_provider) . (isset($_REQUEST["lang"]) ? "&lang=" . urlencode($_REQUEST["lang"]) : "");

			ob_start();
			$sso_providers[$sso_provider]->ProcessFrontend();
			$data = ob_get_contents();
			ob_end_clean();

			// Allow a frontend hook function to modify the output of providers.
			// Useful for changing class names for projects like Twitter Bootstrap.
			if (function_exists("FrontendHook_ProcessOutput"))  $data = FrontendHook_ProcessOutput($data);

			echo $data;
		}
		else
		{
			SSO_ActivateImpersonationUser();
			SSO_ActivateNamespaceUser();

			echo $sso_header;

			SSO_OutputHeartbeat();
?>
<div class="sso_selector_wrap">
<div class="sso_selector_wrap_inner">
	<div class="sso_selector_header"><?php echo htmlspecialchars(BB_Translate("Select Sign In Method")); ?></div>
	<div class="sso_selectors">
<?php
			$outputmap = array();
			foreach ($sso_providers as $sso_provider => &$instance)
			{
				$sso_target_url = SSO_ROOT_URL . "/" . $sso_indexphp . "?sso_provider=" . urlencode($sso_provider) . (isset($_REQUEST["lang"]) ? "&lang=" . urlencode($_REQUEST["lang"]) : "");

				ob_start();
				$sso_providers[$sso_provider]->GenerateSelector();
				$order = (isset($sso_settings[""]["order"][$sso_provider]) ? $sso_settings[""]["order"][$sso_provider] : $instance->DefaultOrder());
				SSO_AddSortedOutput($outputmap, $order, $sso_provider, ob_get_contents());
				ob_end_clean();
			}

			SSO_DisplaySortedOutput($outputmap);
?>
	</div>
</div>
</div>
<?php

			echo $sso_footer;
		}
	}
	catch (Exception $e)
	{
		SSO_DisplayError("A database error has occurred.  Most likely cause:  Bad SQL query.");
	}
?>