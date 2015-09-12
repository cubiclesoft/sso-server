<?php
	// Single Sign-On Server
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	if (file_exists("config.php"))  exit();

	require_once "support/debug.php";
	require_once "support/str_basics.php";
	require_once "support/page_basics.php";
	require_once "support/sso_functions.php";
	require_once "support/utf8.php";
	require_once "support/smtp.php";
	require_once "support/pop3.php";
	require_once "support/random.php";
	require_once "support/csdb/db.php";

	SetDebugLevel();
	Str::ProcessAllInput();

	// Only allow secure database products to be used except on localhost by default.
	// Can be overridden with an installation hook BUT please know exactly what you are doing before doing so.
	$allowinsecuredatabases = ($_SERVER["REMOTE_ADDR"] == "127.0.0.1" || $_SERVER["REMOTE_ADDR"] == "::1");

	// Allow developers to inject code here.  For example, IP address restriction logic or a SSO bypass.
	if (file_exists("install_hook.php"))  require_once "install_hook.php";

	if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "checklist")
	{
?>
	<table align="center">
		<tr class="head"><th>Test</th><th>Passed?</th></tr>
		<tr class="row">
			<td>PHP 5.4.x or later</td>
			<td align="right">
<?php
		if ((double)phpversion() < 5.4)  echo "<span class=\"error\">No</span><br /><br />The server is running PHP " . phpversion() . ".  The installation may succeed but the rest of the Single Sign-On Server will be broken.  You will be unable to use this product.  Running outdated versions of PHP poses a serious website security risk.  Please contact your system administrator to upgrade your PHP installation.";
		else  echo "<span class=\"success\">Yes</span>";
?>
			</td>
		</tr>
		<tr class="row altrow">
			<td>PHP 'safe_mode' off</td>
			<td align="right">
<?php
		if (ini_get('safe_mode'))  echo "<span class=\"error\">No</span><br /><br />PHP is running with 'safe_mode' enabled.  You will probably get additional failures below relating to file/directory creation.  This setting is generally accepted as a poor security solution that doesn't work and is deprecated.  Please turn it off.  If you are getting errors below, can't change this setting, and the fixes below aren't working, you may need to contact your hosting service provider.";
		else  echo "<span class=\"success\">Yes</span>";
?>
			</td>
		</tr>
		<tr class="row">
			<td>Able to create files in ./</td>
			<td align="right">
<?php
		if (file_put_contents("test.dat", "a") === false)  echo "<span class=\"error\">No</span><br /><br />chmod 777 on the directory may fix the problem.";
		else if (!unlink("test.dat"))  echo "<span class=\"error\">No</span><br /><br />Unable to delete test file.  chmod 777 on the directory may fix the problem.";
		else  echo "<span class=\"success\">Yes</span>";
?>
			</td>
		</tr>
		<tr class="row altrow">
			<td>Able to create directories in ./</td>
			<td align="right">
<?php
		if (!mkdir("test"))  echo "<span class=\"error\">No</span><br /><br />chmod 777 on the directory may fix the problem.";
		else if (!rmdir("test"))  echo "<span class=\"error\">No</span><br /><br />Unable to delete test directory.  chmod 777 on the parent directory may fix the problem.";
		else  echo "<span class=\"success\">Yes</span>";
?>
			</td>
		</tr>
		<tr class="row">
			<td>No './index.html'</td>
			<td align="right">
<?php
		if (file_exists("index.html"))  echo "<span class=\"error\">No</span><br /><br />Depending on server settings, 'index.html' may interfere with the proper operation of Single Sign-On Server.";
		else  echo "<span class=\"success\">Yes</span>";
?>
			</td>
		</tr>
		<tr class="row altrow">
			<td>Endpoint './endpoint.php' exists</td>
			<td align="right">
<?php
		if (!file_exists("endpoint.php"))  echo "<span class=\"error\">No</span><br /><br />'endpoint.php' does not exist on the server.  Installation will fail.";
		else  echo "<span class=\"success\">Yes</span>";
?>
			</td>
		</tr>
		<tr class="row">
			<td>Admin './admin.php' exists</td>
			<td align="right">
<?php
		if (!file_exists("admin.php"))  echo "<span class=\"error\">No</span><br /><br />'admin.php' does not exist on the server.  Installation will fail.";
		else  echo "<span class=\"success\">Yes</span>";
?>
			</td>
		</tr>
		<tr class="row">
			<td>No './settings.php'</td>
			<td align="right">
<?php
		if (file_exists("settings.php"))  echo "<span class=\"error\">No</span><br /><br />'settings.php' will be overwritten upon install.";
		else  echo "<span class=\"success\">Yes</span>";
?>
			</td>
		</tr>
		<tr class="row altrow">
			<td>$_SERVER["REQUEST_URI"] supported</td>
			<td align="right">
<?php
		if (!isset($_SERVER["REQUEST_URI"]))  echo "<span class=\"error\">No</span><br /><br />Server does not support this feature.  The installation may fail and the site might not work.";
		else  echo "<span class=\"success\">Yes</span>";
?>
			</td>
		</tr>
		<tr class="row">
			<td>PHP 'register_globals' off</td>
			<td align="right">
<?php
		if (ini_get('register_globals'))  echo "<span class=\"error\">No</span><br /><br />PHP is running with 'register_globals' enabled.  This setting is generally accepted as a major security risk and is deprecated.  Please turn it off by editing the php.ini file for your site - you may need to contact your hosting provider to accomplish this task.";
		else  echo "<span class=\"success\">Yes</span>";
?>
			</td>
		</tr>
		<tr class="row altrow">
			<td>PHP 'magic_quotes_gpc' off</td>
			<td align="right">
<?php
		if (get_magic_quotes_gpc())  echo "<span class=\"error\">No</span><br /><br />PHP is running with 'magic_quotes_gpc' enabled.  This setting is generally accepted as a security risk AND causes all sorts of non-security-related problems.  It is also deprecated.  Please turn it off by editing the php.ini file for your site - you may need to contact your hosting provider to accomplish this task.";
		else  echo "<span class=\"success\">Yes</span>";
?>
			</td>
		</tr>
		<tr class="row">
			<td>PHP 'magic_quotes_sybase' off</td>
			<td align="right">
<?php
		if (ini_get('magic_quotes_sybase'))  echo "<span class=\"error\">No</span><br /><br />PHP is running with 'magic_quotes_sybase' enabled.  This setting is generally accepted as a security risk AND causes all sorts of non-security-related problems.  It is also deprecated.  Please turn it off by editing the php.ini file for your site - you may need to contact your hosting provider to accomplish this task.";
		else  echo "<span class=\"success\">Yes</span>";
?>
			</td>
		</tr>
		<tr class="row altrow">
			<td>Installation over SSL</td>
			<td align="right">
<?php
		if (!BB_IsSSLRequest())  echo "<span class=\"error\">No</span><br /><br />While Single Sign-On Server will install and run without using HTTPS/SSL, think about the implications of network sniffing access tokens, who will have access to the system, and what they can do in the system.  SSL certificates can be obtained for free.  Proceed only if this major security risk is acceptable.";
		else  echo "<span class=\"success\">Yes</span>";
?>
			</td>
		</tr>
		<tr class="row">
			<td>Crypto-safe CSPRNG available</td>
			<td align="right">
<?php
		try
		{
			$rng = new CSPRNG(true);
			echo "<span class=\"success\">Yes</span>";
		}
		catch (Exception $e)
		{
			echo "<span class=\"error\">No</span><br /><br />Installation will fail.  Please ask your system administrator to install a supported PHP extension (e.g. OpenSSL, Mcrypt).";
		}
?>
			</td>
		</tr>
		<tr class="head">
			<th>Supported PHP functions/classes</th>
			<th>&nbsp;</th>
		</tr>
<?php
		$functions = array(
			"fsockopen" => "Web/e-mail automation/validation functions",
			"json_encode" => "JSON encoding support functions",
			"mcrypt_module_open" => "Mcrypt cryptographic support functions",
			"openssl_open" => "OpenSSL extension support",
		);

		$x = 0;
		foreach ($functions as $function => $info)
		{
			echo "<tr class=\"row" . ($x % 2 ? " altrow" : "") . "\"><td>" . htmlspecialchars($function) . "</td><td align=\"right\">" . (function_exists($function) ? "<span class=\"success\">Yes</span>" : "<span class=\"error\">No</span><br /><br />Single Sign-On Server will be unable to use " . $info . ".  The installation might succeed but the product will not function at all or have terrible performance.") . "</td></tr>\n";
			$x++;
		}

		$classes = array(
			"PDO" => "PDO database class",
		);

		foreach ($classes as $class => $info)
		{
			echo "<tr class=\"row" . ($x % 2 ? " altrow" : "") . "\"><td>" . htmlspecialchars($class) . "</td><td align=\"right\">" . (class_exists($class) ? "<span class=\"success\">Yes</span>" : "<span class=\"error\">No</span><br /><br />Single Sign-On Server will be unable to use " . $info . ".  The installation might succeed but the product will not function at all or have terrible performance.") . "</td></tr>\n";
			$x++;
		}
?>
	</table>
<?php
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "dbtest")
	{
		$databases = SSO_GetSupportedDatabases();
		$type = (string)$_REQUEST["type"];
		if (!isset($databases[$type]))
		{
			echo "<span class=\"error\">Please select a database server.</span>";

			exit();
		}

		if ($_REQUEST["dsn"] == "")
		{
			$rng = new CSPRNG(true);

			$dsn = $databases[$type]["default_dsn"];
			$dsn = str_replace("@RANDOM@", $rng->GenerateString(), $dsn);
			$dsn = str_replace("@PATH@", str_replace("\\", "/", dirname(__FILE__)), $dsn);

			$_REQUEST["dsn"] = $dsn;
		}

		require_once "support/csdb/db_" . $type . ".php";

		$classname = "CSDB_" . $type;

		try
		{
			$db = new $classname();
			$db->SetDebug(true);
			$db->Connect($type . ":" . $_REQUEST["dsn"], ($databases[$type]["login"] ? $_REQUEST["user"] : false), ($databases[$type]["login"] ? $_REQUEST["pass"] : false));
			echo "<span class=\"success\">Successfully connected to the server.</span><br /><b>Running " . htmlspecialchars($db->GetDisplayName() . " " . $db->GetVersion()) . "</b>";
			unset($db);
		}
		catch (Exception $e)
		{
			echo "<span class=\"error\">Database connection attempt failed.</span><br />" . htmlspecialchars($e->getMessage());
		}

		if ($databases[$type]["replication"] && $_REQUEST["master_dsn"] != "")
		{
			try
			{
				$db = new $classname();
				$db->SetDebug(true);
				$db->Connect($type . ":" . $_REQUEST["master_dsn"], ($databases[$type]["login"] ? $_REQUEST["master_user"] : false), ($databases[$type]["login"] ? $_REQUEST["master_pass"] : false));
				echo "<span class=\"success\">Successfully connected to the replication master server.</span><br /><b>Running " . htmlspecialchars($db->GetDisplayName() . " " . $db->GetVersion()) . "</b>";
				unset($db);
			}
			catch (Exception $e)
			{
				echo "<span class=\"error\">Replication master connection attempt failed.</span><br />" . htmlspecialchars($e->getMessage());
			}
		}
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "emailtest")
	{
		$smtpserver = explode(":", trim($_REQUEST["smtpserver"]));
		if (count($smtpserver) == 2)
		{
			$smtpport = $smtpserver[1];
			$smtpserver = $smtpserver[0];
		}
		else
		{
			$smtpserver = trim($_REQUEST["smtpserver"]);
			$smtpport = 25;
		}

		$pop3server = explode(":", trim($_REQUEST["pop3server"]));
		if (count($pop3server) == 2)
		{
			$pop3port = $pop3server[1];
			$pop3server = $pop3server[0];
		}
		else
		{
			$pop3server = trim($_REQUEST["pop3server"]);
			$pop3port = 110;
		}

		$message = <<<EOF
<html><body>Hello,<br>
<br>
This is the Single Sign-On (SSO) Server installer test e-mail.<br>
<br>
Thank you for using SSO Server.
</body></html>
EOF;

		$headers = SMTP::GetUserAgent("Thunderbird");
		$smtpsent = false;
		if ($smtpserver != "" && $_REQUEST["from"] != "" && $_REQUEST["to"] != "")
		{
			$smtpoptions = array(
				"headers" => $headers,
				"textmessage" => strip_tags($message),
				"htmlmessage" => $message,
				"server" => $smtpserver,
				"port" => $smtpport,
				"secure" => ($smtpport == 465),
				"username" => $_REQUEST["user"],
				"password" => $_REQUEST["pass"]
			);

			$result = SMTP::SendEmail($_REQUEST["from"], $_REQUEST["to"], "[SSO Server] Installer Test Message", $smtpoptions);
			if (!$result["success"])
			{
				echo "<span class=\"error\">Failed to connect to the SMTP server.  " . htmlspecialchars($result["error"]) . (isset($result["info"]) ? " (" . htmlspecialchars($result["info"]) . ")" : "") . "</span><br />";
			}
			else
			{
				echo "<span class=\"success\">Successfully connected to the SMTP server and sent the test message.</span><br />";
				$smtpsent = true;
			}
		}
		else if ($smtpserver == "")  echo "<span class=\"error\">SMTP:  'SMTP Server' field not filled in.</span><br />";
		else if ($_REQUEST["from"] == "")  echo "<span class=\"error\">SMTP:  'Default E-mail Address' field not filled in.</span><br />";
		else if ($_REQUEST["to"] == "")  echo "<span class=\"error\">SMTP:  'Send To' field not filled in.</span><br />";

		$pop3valid = false;
		if ($pop3server != "" && $_REQUEST["user"] != "" && $_REQUEST["pass"] != "")
		{
			$pop3options = array(
				"server" => $pop3server,
				"port" => $pop3port,
				"secure" => ($pop3port == 995)
			);

			$temppop3 = new POP3;
			$result = $temppop3->Connect($_REQUEST["user"], $_REQUEST["pass"], $pop3options);
			if (!$result["success"])
			{
				echo "<span class=\"error\">Failed to connect to the POP3 server.  " . htmlspecialchars($result["error"]) . (isset($result["info"]) ? " (" . htmlspecialchars($result["info"]) . ")" : "") . "</span><br />";
			}
			else
			{
				echo "<span class=\"success\">Successfully connected to the POP3 server.</span><br />";
				$temppop3->Disconnect();
				$pop3valid = true;
			}
		}
		else if ($pop3server == "")  echo "<span class=\"error\">POP3:  'POP3 Server' field not filled in.</span><br />";
		else if ($_REQUEST["user"] == "")  echo "<span class=\"error\">POP3:  'Username' field not filled in.</span><br />";
		else if ($_REQUEST["pass"] == "")  echo "<span class=\"error\">POP3:  'Password' field not filled in.</span><br />";

		// Test SMTP again if POP3 succeeded.
		if ($pop3valid && !$smtpsent)
		{
			if ($smtpserver != "" && $_REQUEST["from"] != "" && $_REQUEST["to"] != "")
			{
				$result = SMTP::SendEmail($_REQUEST["from"], $_REQUEST["to"], "[SSO Server] Installer Test Message", $smtpoptions);
				if (!$result["success"])
				{
					echo "<span class=\"error\">Failed to connect to the SMTP server.  " . htmlspecialchars($result["error"]) . (isset($result["info"]) ? " (" . htmlspecialchars($result["info"]) . ")" : "") . "</span><br />";
				}
				else
				{
					echo "<span class=\"success\">Successfully connected to the SMTP server.</span><br />";
					$smtpsent = true;
				}
			}
		}
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "install")
	{
		function InstallError($message)
		{
			echo "<span class=\"error\">" . $message . "  Click 'Prev' below to go back and correct the problem.</span>";
			echo "<script type=\"text/javascript\">InstallFailed();</script>";

			exit();
		}

		function InstallWarning($message)
		{
			echo "<span class=\"warning\">" . $message . "</span><br />";
			flush();
		}

		function InstallSuccess($message)
		{
			echo "<span class=\"success\">" . $message . "</span><br />";
			flush();
		}

		// Set up page-level calculation variables.
		define("SSO_ROOT_PATH", str_replace("\\", "/", dirname(__FILE__)));

		$url = dirname(BB_GetRequestURLBase());
		if (substr($url, -1) == "/")  $url = substr($url, 0, -1);
		define("SSO_ROOT_URL", $url);

		$url = dirname(BB_GetFullRequestURLBase());
		if (substr($url, -1) != "/")  $url .= "/";
		define("SSO_LOGIN_URL", $url);

		define("SSO_SUPPORT_PATH", "support");
		define("SSO_PROVIDER_PATH", "providers");

		// Generate random seeds.
		$rng = new CSPRNG(true);
		$sso_rng = $rng;
		for ($x = 0; $x < 14; $x++)
		{
			$seed = $rng->GenerateToken(128);
			if ($seed === false)  InstallError("Seed generation failed.");

			define("SSO_BASE_RAND_SEED" . ($x ? $x + 1 : ""), $seed);
		}

		define("SSO_USE_LESS_SAFE_STORAGE", $_REQUEST["sso_use_less_safe_storage"] == "yes");

		// Connect to the database server.
		$databases = SSO_GetSupportedDatabases();
		$dbtype = (string)$_REQUEST["db_select"];
		if (!isset($databases[$dbtype]))  InstallError("Please select a database server.");

		if ($_REQUEST["db_dsn"] == "")
		{
			$dsn = $databases[$dbtype]["default_dsn"];
			$dsn = str_replace("@RANDOM@", $rng->GenerateString(), $dsn);
			$dsn = str_replace("@PATH@", str_replace("\\", "/", dirname(__FILE__)), $dsn);

			$_REQUEST["db_dsn"] = $dsn;
		}

		require_once "support/csdb/db_" . $dbtype . ".php";

		$dbclassname = "CSDB_" . $dbtype;

		try
		{
			$db = new $dbclassname($dbtype . ":" . $_REQUEST["db_dsn"], ($databases[$dbtype]["login"] ? $_REQUEST["db_user"] : false), ($databases[$dbtype]["login"] ? $_REQUEST["db_pass"] : false));
			if ($_REQUEST["db_master_dsn"] != "")  $db->SetMaster($dbtype . ":" . $_REQUEST["db_master_dsn"], ($databases[$dbtype]["login"] ? $_REQUEST["db_master_user"] : false), ($databases[$dbtype]["login"] ? $_REQUEST["db_master_pass"] : false));
		}
		catch (Exception $e)
		{
			InstallError("Database connection failed.  " . htmlspecialchars($e->getMessage()));
		}
		try
		{
			InstallSuccess("Successfully connected to the database server.  Running " . htmlspecialchars($db->GetDisplayName() . " " . $db->GetVersion()));
		}
		catch (Exception $e)
		{
			InstallError("Database connection succeeded but unable to get server version.  " . htmlspecialchars($e->getMessage()));
		}

		// Create/Use the SSO database.
		try
		{
			$db->Query("USE", $_REQUEST["db_name"]);
		}
		catch (Exception $e)
		{
			try
			{
				$db->Query("CREATE DATABASE", array($_REQUEST["db_name"], "CHARACTER SET" => "utf8", "COLLATE" => "utf8_general_ci"));
				$db->Query("USE", $_REQUEST["db_name"]);
			}
			catch (Exception $e)
			{
				InstallError("Unable to create/use database '" . htmlspecialchars($_REQUEST["db_name"]) . "'.  " . htmlspecialchars($e->getMessage()));
			}
		}
		InstallSuccess("Successfully created and selected database '" . htmlspecialchars($_REQUEST["db_name"]) . "'.");

		// Create SSO database tables.
		$dbprefix = $_REQUEST["db_sso_prefix"];
		$sso_db_apikeys = $dbprefix . "apikeys";
		$sso_db_fields = $dbprefix . "fields";
		$sso_db_users = $dbprefix . "users";
		$sso_db_user_tags = $dbprefix . "user_tags";
		$sso_db_user_sessions = $dbprefix . "user_sessions";
		$sso_db_temp_sessions = $dbprefix . "temp_sessions";
		$sso_db_tags = $dbprefix . "tags";
		$sso_db_ipcache = $dbprefix . "ipcache";
		try
		{
			$apikeysfound = $db->TableExists($sso_db_apikeys);
			$fieldsfound = $db->TableExists($sso_db_fields);
			$usersfound = $db->TableExists($sso_db_users);
			$usertagsfound = $db->TableExists($sso_db_user_tags);
			$usersessionsfound = $db->TableExists($sso_db_user_sessions);
			$tempsessionsfound = $db->TableExists($sso_db_temp_sessions);
			$tagsfound = $db->TableExists($sso_db_tags);
			$ipcachefound = $db->TableExists($sso_db_ipcache);
		}
		catch (Exception $e)
		{
			InstallError("Unable to determine the existence of a database table.  " . htmlspecialchars($e->getMessage()));
		}
		if (!$apikeysfound)
		{
			try
			{
				$db->Query("CREATE TABLE", array($sso_db_apikeys, array(
					"id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true, "PRIMARY KEY" => true, "AUTO INCREMENT" => true),
					"user_id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true),
					"namespace" => array("STRING", 1, 20, "NOT NULL" => true),
					"apikey" => array("STRING", 1, 64, "NOT NULL" => true),
					"created" => array("DATETIME", "NOT NULL" => true),
					"info" => array("STRING", 3, "NOT NULL" => true),
				),
				array(
					array("KEY", array("user_id"), "NAME" => $sso_db_apikeys . "_user_id"),
					array("KEY", array("namespace"), "NAME" => $sso_db_apikeys . "_namespace"),
				)));
			}
			catch (Exception $e)
			{
				InstallError("Unable to create the database table '" . htmlspecialchars($sso_db_apikeys) . "'.  " . htmlspecialchars($e->getMessage()));
			}
		}
		if (!$fieldsfound)
		{
			try
			{
				$db->Query("CREATE TABLE", array($sso_db_fields, array(
					"id" => array("INTEGER", 4, "UNSIGNED" => true, "NOT NULL" => true, "PRIMARY KEY" => true, "AUTO INCREMENT" => true),
					"field_name" => array("STRING", 1, 50, "NOT NULL" => true),
					"field_desc" => array("STRING", 1, 255, "NOT NULL" => true),
					"field_hash" => array("STRING", 1, 32, "NOT NULL" => true),
					"encrypted" => array("INTEGER", 1, "NOT NULL" => true),
					"enabled" => array("INTEGER", 1, "NOT NULL" => true),
					"created" => array("DATETIME", "NOT NULL" => true),
				),
				array(
					array("UNIQUE", array("field_name"), "NAME" => $sso_db_fields . "_field_name"),
				)));
			}
			catch (Exception $e)
			{
				InstallError("Unable to create the database table '" . htmlspecialchars($sso_db_fields) . "'.  " . htmlspecialchars($e->getMessage()));
			}
		}
		if (!$usersfound)
		{
			try
			{
				$db->Query("CREATE TABLE", array($sso_db_users, array(
					"id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true, "PRIMARY KEY" => true, "AUTO INCREMENT" => true),
					"provider_name" => array("STRING", 1, 50, "NOT NULL" => true),
					"provider_id" => array("STRING", 1, 255, "NOT NULL" => true),
					"session_extra" => array("STRING", 1, 64, "NOT NULL" => true),
					"version" => array("INTEGER", 4, "NOT NULL" => true),
					"lastipaddr" => array("STRING", 1, 50, "NOT NULL" => true),
					"lastactivated" => array("DATETIME", "NOT NULL" => true),
					"info" => array("STRING", 3, "NOT NULL" => true),
					"info2" => array("STRING", 3, "NOT NULL" => true),
				),
				array(
					array("UNIQUE", array("provider_name", "provider_id"), "NAME" => $sso_db_users . "_provider"),
				)));
			}
			catch (Exception $e)
			{
				InstallError("Unable to create the database table '" . htmlspecialchars($sso_db_users) . "'.  " . htmlspecialchars($e->getMessage()));
			}
		}
		if (!$usertagsfound)
		{
			try
			{
				$db->Query("CREATE TABLE", array($sso_db_user_tags, array(
					"user_id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true),
					"tag_id" => array("INTEGER", 4, "NOT NULL" => true),
					"issuer_id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true),
					"reason" => array("STRING", 1, 100, "NOT NULL" => true),
					"created" => array("DATETIME", "NOT NULL" => true),
				),
				array(
					array("PRIMARY", array("user_id", "tag_id"), "NAME" => $sso_db_user_tags . "_usertag"),
				)));
			}
			catch (Exception $e)
			{
				InstallError("Unable to create the database table '" . htmlspecialchars($sso_db_user_tags) . "'.  " . htmlspecialchars($e->getMessage()));
			}
		}
		if (!$usersessionsfound)
		{
			try
			{
				$db->Query("CREATE TABLE", array($sso_db_user_sessions, array(
					"id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true, "PRIMARY KEY" => true, "AUTO INCREMENT" => true),
					"user_id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true),
					"apikey_id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true),
					"updated" => array("DATETIME", "NOT NULL" => true),
					"created" => array("DATETIME", "NOT NULL" => true),
					"session_id" => array("STRING", 1, 64, "NOT NULL" => true),
					"info" => array("STRING", 3, "NOT NULL" => true),
				),
				array(
					array("KEY", array("user_id", "updated"), "NAME" => $sso_db_user_sessions . "_user_id"),
					array("KEY", array("apikey_id", "updated"), "NAME" => $sso_db_user_sessions . "_apikey_id"),
				)));
			}
			catch (Exception $e)
			{
				InstallError("Unable to create the database table '" . htmlspecialchars($sso_db_user_sessions) . "'.  " . htmlspecialchars($e->getMessage()));
			}
		}
		if (!$tempsessionsfound)
		{
			try
			{
				$db->Query("CREATE TABLE", array($sso_db_temp_sessions, array(
					"id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true, "PRIMARY KEY" => true, "AUTO INCREMENT" => true),
					"apikey_id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true),
					"updated" => array("DATETIME", "NOT NULL" => true),
					"created" => array("DATETIME", "NOT NULL" => true),
					"heartbeat" => array("INTEGER", 4, "UNSIGNED" => true, "NOT NULL" => true),
					"session_id" => array("STRING", 1, 64, "NOT NULL" => true),
					"ipaddr" => array("STRING", 1, 50, "NOT NULL" => true),
					"info" => array("STRING", 3, "NOT NULL" => true),
					"recoverinfo" => array("STRING", 3, "NOT NULL" => true),
				),
				array(
					array("KEY", array("apikey_id", "updated"), "NAME" => $sso_db_temp_sessions . "_apikey_id"),
				)));
			}
			catch (Exception $e)
			{
				InstallError("Unable to create the database table '" . htmlspecialchars($sso_db_temp_sessions) . "'.  " . htmlspecialchars($e->getMessage()));
			}
		}
		if (!$tagsfound)
		{
			try
			{
				$db->Query("CREATE TABLE", array($sso_db_tags, array(
					"id" => array("INTEGER", 4, "UNSIGNED" => true, "NOT NULL" => true, "PRIMARY KEY" => true, "AUTO INCREMENT" => true),
					"tag_name" => array("STRING", 1, 50, "NOT NULL" => true),
					"tag_desc" => array("STRING", 1, 255, "NOT NULL" => true),
					"enabled" => array("INTEGER", 1, "NOT NULL" => true),
					"created" => array("DATETIME", "NOT NULL" => true),
				),
				array(
					array("UNIQUE", array("tag_name"), "NAME" => $sso_db_tags . "_tag_name"),
				)));
			}
			catch (Exception $e)
			{
				InstallError("Unable to create the database table '" . htmlspecialchars($sso_db_tags) . "'.  " . htmlspecialchars($e->getMessage()));
			}

			try
			{
				$db->Query("INSERT", array($sso_db_tags, array(
					"tag_name" => $_REQUEST["sso_site_admin"],
					"tag_desc" => "",
					"enabled" => 1,
					"created" => CSDB::ConvertToDBTime(time()),
				)));
			}
			catch (Exception $e)
			{
				InstallError("Unable to insert '" . htmlspecialchars($_REQUEST["sso_site_admin"]) . "' into database table '" . htmlspecialchars($sso_db_tags) . "'.  " . htmlspecialchars($e->getMessage()));
			}

			try
			{
				$db->Query("INSERT", array($sso_db_tags, array(
					"tag_name" => $_REQUEST["sso_admin"],
					"tag_desc" => "",
					"enabled" => 1,
					"created" => CSDB::ConvertToDBTime(time()),
				)));
			}
			catch (Exception $e)
			{
				InstallError("Unable to insert '" . htmlspecialchars($_REQUEST["sso_admin"]) . "' into database table '" . htmlspecialchars($sso_db_tags) . "'.  " . htmlspecialchars($e->getMessage()));
			}

			try
			{
				$db->Query("INSERT", array($sso_db_tags, array(
					"tag_name" => $_REQUEST["sso_locked"],
					"tag_desc" => "",
					"enabled" => 1,
					"created" => CSDB::ConvertToDBTime(time()),
				)));
			}
			catch (Exception $e)
			{
				InstallError("Unable to insert '" . htmlspecialchars($_REQUEST["sso_locked"]) . "' into database table '" . htmlspecialchars($sso_db_tags) . "'.  " . htmlspecialchars($e->getMessage()));
			}
		}
		if (!$ipcachefound)
		{
			try
			{
				$db->Query("CREATE TABLE", array($sso_db_ipcache, array(
					"id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true, "PRIMARY KEY" => true, "AUTO INCREMENT" => true),
					"ipaddr" => array("STRING", 1, 50, "NOT NULL" => true),
					"created" => array("DATETIME", "NOT NULL" => true),
					"info" => array("STRING", 3, "NOT NULL" => true),
				),
				array(
					array("UNIQUE", array("ipaddr"), "NAME" => $sso_db_ipcache . "_ipaddr"),
					array("KEY", array("created"), "NAME" => $sso_db_ipcache . "_created"),
				)));
			}
			catch (Exception $e)
			{
				InstallError("Unable to create the database table '" . htmlspecialchars($sso_db_ipcache) . "'.  " . htmlspecialchars($e->getMessage()));
			}
		}
		InstallSuccess("Successfully created database tables and data in '" . htmlspecialchars($_REQUEST["db_name"]) . "'.");

		$sso_fields = array();
		$sso_settings = array();
		if (!SSO_SaveSettings())  InstallError("Unable to install the settings file.");
		SSO_LoadSettings();
		if (!SSO_SaveSettings())  InstallError("Unable to save the default settings file.");
		InstallSuccess("Successfully set up the settings file.");

		// Extract SMTP/POP3 settings.
		$smtpserver = explode(":", trim($_REQUEST["smtp_server"]));
		if (count($smtpserver) == 2)
		{
			$smtpport = $smtpserver[1];
			$smtpserver = $smtpserver[0];
		}
		else
		{
			$smtpserver = trim($_REQUEST["smtp_server"]);
			$smtpport = 25;
		}

		$pop3server = explode(":", trim($_REQUEST["pop3_server"]));
		if (count($pop3server) == 2)
		{
			$pop3port = $pop3server[1];
			$pop3server = $pop3server[0];
		}
		else
		{
			$pop3server = trim($_REQUEST["pop3_server"]);
			$pop3port = 110;
		}

		// Optionally move critical files to final locations.  Some security through obscurity here.
		if ($_REQUEST["sso_endpoint_sto"] > 0)
		{
			$srcfile = SSO_ROOT_PATH . "/endpoint.php";
			$endpointdir = "endpoint_" . $rng->GenerateString();
			$destfile = SSO_ROOT_PATH . "/" . $endpointdir;
			if (!@mkdir($destfile, 0755))  InstallError("Unable to create endpoint directory.");
			$destfile .= "/endpoint.php";
			if (!@rename($srcfile, $destfile))  InstallError("Unable to move 'endpoint.php' to endpoint directory.");
			$url = dirname(BB_GetFullRequestURLBase());
			if (substr($url, -1) != "/")  $url .= "/";
			$url .= $endpointdir . "/endpoint.php";
			define("SSO_ENDPOINT_URL", $url);
			InstallSuccess("Successfully created a randomly named directory and moved 'endpoint.php' into it.");
		}
		else
		{
			$url = dirname(BB_GetFullRequestURLBase());
			if (substr($url, -1) != "/")  $url .= "/";
			$url .= "endpoint.php";
			define("SSO_ENDPOINT_URL", $url);
		}

		if ($_REQUEST["sso_admin_sto"] > 0)
		{
			$srcfile = SSO_ROOT_PATH . "/admin.php";
			$admindir = "admin_" . $rng->GenerateString();
			$destfile = SSO_ROOT_PATH . "/" . $admindir;
			if (!@mkdir($destfile, 0755))  InstallError("Unable to create endpoint directory.");
			$destfile .= "/admin.php";
			if (!@rename($srcfile, $destfile))  InstallError("Unable to move 'admin.php' to admin directory.");
			$adminurl = dirname(BB_GetFullRequestURLBase());
			if (substr($adminurl, -1) != "/")  $adminurl .= "/";
			$adminurl .= $admindir . "/admin.php";
			InstallSuccess("Successfully created a randomly named directory and moved 'admin.php' into it.");
		}
		else
		{
			$adminurl = dirname(BB_GetFullRequestURLBase());
			if (substr($adminurl, -1) != "/")  $adminurl .= "/";
			$adminurl .= "admin.php";
		}

		// Set up the main configuration file.
		$data = "<" . "?php\n";
		$data .= "\tdefine(\"SSO_HTTP_SERVER\", \"\");\n";
		$data .= "\tdefine(\"SSO_HTTPS_SERVER\", \"\");\n";
		$data .= "\tdefine(\"SSO_USE_HTTPS\", " . var_export(BB_IsSSLRequest(), true) . ");\n";
		$data .= "\tdefine(\"SSO_ROOT_PATH\", " . var_export(SSO_ROOT_PATH, true) . ");\n";
		$data .= "\tdefine(\"SSO_ROOT_URL\", " . var_export(SSO_ROOT_URL, true) . ");\n";
		$data .= "\tdefine(\"SSO_LOGIN_URL\", " . var_export(SSO_LOGIN_URL, true) . ");\n";
		$data .= "\tdefine(\"SSO_ENDPOINT_URL\", " . var_export(SSO_ENDPOINT_URL, true) . ");\n";
		$data .= "\tdefine(\"SSO_SUPPORT_PATH\", " . var_export(SSO_SUPPORT_PATH, true) . ");\n";
		$data .= "\tdefine(\"SSO_PROVIDER_PATH\", " . var_export(SSO_PROVIDER_PATH, true) . ");\n";
		$data .= "\tdefine(\"SSO_LANG_PATH\", \"lang\");\n";
		$data .= "\tdefine(\"SSO_DEFAULT_LANG\", " . var_export($_REQUEST["sso_default_lang"], true) . ");\n";
		$data .= "\tdefine(\"SSO_ADMIN_LANG\", " . var_export($_REQUEST["sso_admin_lang"], true) . ");\n";
		$data .= "\tdefine(\"SSO_PROXY_X_FORWARDED_FOR\", " . var_export($_REQUEST["sso_proxy_x_forwarded_for"], true) . ");\n";
		$data .= "\tdefine(\"SSO_PROXY_CLIENT_IP\", " . var_export($_REQUEST["sso_proxy_client_ip"], true) . ");\n";
		$data .= "\tdefine(\"SSO_BASE_RAND_SEED\", " . var_export(SSO_BASE_RAND_SEED, true) . ");\n";
		$data .= "\tdefine(\"SSO_BASE_RAND_SEED2\", " . var_export(SSO_BASE_RAND_SEED2, true) . ");\n";
		$data .= "\tdefine(\"SSO_BASE_RAND_SEED3\", " . var_export(SSO_BASE_RAND_SEED3, true) . ");\n";
		$data .= "\tdefine(\"SSO_BASE_RAND_SEED4\", " . var_export(SSO_BASE_RAND_SEED4, true) . ");\n";
		$data .= "\tdefine(\"SSO_BASE_RAND_SEED5\", " . var_export(SSO_BASE_RAND_SEED5, true) . ");\n";
		$data .= "\tdefine(\"SSO_BASE_RAND_SEED6\", " . var_export(SSO_BASE_RAND_SEED6, true) . ");\n";
		$data .= "\tdefine(\"SSO_BASE_RAND_SEED7\", " . var_export(SSO_BASE_RAND_SEED7, true) . ");\n";
		$data .= "\tdefine(\"SSO_BASE_RAND_SEED8\", " . var_export(SSO_BASE_RAND_SEED8, true) . ");\n";
		$data .= "\tdefine(\"SSO_BASE_RAND_SEED9\", " . var_export(SSO_BASE_RAND_SEED9, true) . ");\n";
		$data .= "\tdefine(\"SSO_BASE_RAND_SEED10\", " . var_export(SSO_BASE_RAND_SEED10, true) . ");\n";
		$data .= "\tdefine(\"SSO_BASE_RAND_SEED11\", " . var_export(SSO_BASE_RAND_SEED11, true) . ");\n";
		$data .= "\tdefine(\"SSO_BASE_RAND_SEED12\", " . var_export(SSO_BASE_RAND_SEED12, true) . ");\n";
		$data .= "\tdefine(\"SSO_BASE_RAND_SEED13\", " . var_export(SSO_BASE_RAND_SEED13, true) . ");\n";
		$data .= "\tdefine(\"SSO_BASE_RAND_SEED14\", " . var_export(SSO_BASE_RAND_SEED14, true) . ");\n";
		$data .= "\tdefine(\"SSO_HEARTBEAT_LIMIT\", 30);\n";
		$data .= "\tdefine(\"SSO_STO_ENDPOINT\", " . var_export($_REQUEST["sso_endpoint_sto"] > 0, true) . ");\n";
		$data .= "\tdefine(\"SSO_STO_ADMIN\", " . var_export($_REQUEST["sso_admin_sto"] > 0, true) . ");\n";
		$data .= "\tdefine(\"SSO_USING_CRON\", " . var_export($_REQUEST["sso_using_cron"] > 0, true) . ");\n";
		$data .= "\tdefine(\"SSO_PRIMARY_CIPHER\", " . var_export($_REQUEST["sso_primary_cipher"], true) . ");\n";
		$data .= "\tdefine(\"SSO_DUAL_ENCRYPT\", " . var_export($_REQUEST["sso_dual_encrypt"] > 0, true) . ");\n";
		$data .= "\tdefine(\"SSO_USE_LESS_SAFE_STORAGE\", " . var_export($_REQUEST["sso_use_less_safe_storage"] == "yes", true) . ");\n";
		$data .= "\n";
		$data .= "\tdefine(\"SSO_DB_TYPE\", " . var_export($_REQUEST["db_select"], true) . ");\n";
		$data .= "\tdefine(\"SSO_DB_DSN\", " . var_export($_REQUEST["db_dsn"], true) . ");\n";
		$data .= "\tdefine(\"SSO_DB_USER\", " . var_export($_REQUEST["db_user"], true) . ");\n";
		$data .= "\tdefine(\"SSO_DB_PASS\", " . var_export($_REQUEST["db_pass"], true) . ");\n";
		$data .= "\tdefine(\"SSO_DB_MASTER_DSN\", " . var_export($_REQUEST["db_master_dsn"], true) . ");\n";
		$data .= "\tdefine(\"SSO_DB_MASTER_USER\", " . var_export($_REQUEST["db_master_user"], true) . ");\n";
		$data .= "\tdefine(\"SSO_DB_MASTER_PASS\", " . var_export($_REQUEST["db_master_pass"], true) . ");\n";
		$data .= "\tdefine(\"SSO_DB_NAME\", " . var_export($_REQUEST["db_name"], true) . ");\n";
		$data .= "\tdefine(\"SSO_DB_PREFIX\", " . var_export($dbprefix, true) . ");\n";
		$data .= "\n";
		$data .= "\tdefine(\"SSO_SMTP_SERVER\", " . var_export($smtpserver, true) . ");\n";
		$data .= "\tdefine(\"SSO_SMTP_PORT\", " . var_export($smtpport, true) . ");\n";
		$data .= "\tdefine(\"SSO_POP3_SERVER\", " . var_export($pop3server, true) . ");\n";
		$data .= "\tdefine(\"SSO_POP3_PORT\", " . var_export($pop3port, true) . ");\n";
		$data .= "\tdefine(\"SSO_SMTPPOP3_USER\", " . var_export($_REQUEST["smtppop3_user"], true) . ");\n";
		$data .= "\tdefine(\"SSO_SMTPPOP3_PASS\", " . var_export($_REQUEST["smtppop3_pass"], true) . ");\n";
		$data .= "\tdefine(\"SSO_SMTP_FROM\", " . var_export($_REQUEST["smtp_from"], true) . ");\n";
		$data .= "\n";
		$data .= "\tdefine(\"SSO_SITE_ADMIN_TAG\", " . var_export($_REQUEST["sso_site_admin"], true) . ");\n";
		$data .= "\tdefine(\"SSO_ADMIN_TAG\", " . var_export($_REQUEST["sso_admin"], true) . ");\n";
		$data .= "\tdefine(\"SSO_LOCKED_TAG\", " . var_export($_REQUEST["sso_locked"], true) . ");\n";
		$data .= "?" . ">";
		if (file_put_contents("config.php", $data) === false)  InstallError("Unable to create the configuration file.");
		if ($_REQUEST["sso_endpoint_sto"] > 0 && file_put_contents(SSO_ROOT_PATH . "/" . $endpointdir . "/config.php", $data) === false)  InstallError("Unable to create the configuration file clone in the endpoint subdirectory.");
		if ($_REQUEST["sso_admin_sto"] > 0 && file_put_contents(SSO_ROOT_PATH . "/" . $admindir . "/config.php", $data) === false)  InstallError("Unable to create the configuration file clone in the admin subdirectory.");
		InstallSuccess("Successfully created the configuration file.");

		InstallSuccess("The installation completed successfully.");

?>
		<br />
		Next:  <a href="<?php echo htmlspecialchars($adminurl); ?>">Start using Single-Sign On</a><br />
		(Read the installation instructions or the above link might not work.)<br />
<?php
	}
	else
	{
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>Single Sign-On Server Installer</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel="stylesheet" href="support/install.css" type="text/css" />
<script type="text/javascript" src="support/jquery-1.11.0.min.js"></script>

<script type="text/javascript">
function Page(curr, next)
{
	$('#page' + curr).hide();
	$('#page' + next).fadeIn('normal');

	return false;
}
</script>

</head>
<body>
<noscript><span class="error">Er...  You need Javascript enabled to install Single Sign-On (SSO) Server.</span></noscript>
<form id="installform" method="post" enctype="multipart/form-data" action="install.php" accept-charset="utf-8">
<input type="hidden" name="action" value="install" />
<div id="main">
	<div id="page1" class="box">
		<h1>Single Sign-On Server Installer</h1>
		<h3>Welcome to the Single Sign-On Server installer.</h3>
		<div class="boxmain">
			If you are looking to implement a centralized account management and login system for one or more domains,
			bring disparate login systems together under a unified system, and easily manage all aspects of a user account,
			then this is most likely what you are looking for:<br /><br />

			<div class="indent">
				A self-contained, centralized account management server that can sit on any domain with tools
				to easily manage user fields and access permissions, with multiple signup and sign in options,
				and easy-to-use client functions to sign in and extract information from the server in a
				secure manner.  Or more simply put:  Do you need a login system that rocks?
			</div>
			<br />

			If that sounds like you, Single Sign-On (SSO) is the answer.  Just click "Next" below to get started.
		</div>

		<div class="boxbuttons">
			<a href="#" onclick="return Page(1, 2);">Next &raquo;</a>
		</div>
	</div>

	<div id="page2" class="box" style="display: none;">
		<h1>Single Sign-On Server Requirements</h1>
		<h3>The Single Sign-On Server system requirements.</h3>
		<div class="boxmain">
			In order to use Single Sign-On (SSO) Server, you will need to meet these logistical requirements:<br />
			<ul>
				<li>Someone who knows PHP (a PHP programmer)</li>
				<li>Someone who knows design (a web designer)</li>
			</ul>

			You will also need to meet these technical requirements (most of these are auto-detected by this installation wizard):<br />
			<ul>
				<li><a href="http://www.php.net/" target="_blank">PHP 5.4.x or later</a> (preferably the latest)</li>
				<li><a href="http://barebonescms.com/documentation/csdb/" target="_blank">A CSDB-compliant database</a></li>
			</ul>

		</div>

		<div class="boxbuttons">
			<a href="#" onclick="return Page(2, 1);">&laquo; Prev</a> | <a href="#" onclick="return Page(2, 3);">Next &raquo;</a>
		</div>
	</div>

	<div id="page3" class="box" style="display: none;">
		<h1>Single Sign-On Server Checklist</h1>
		<h3>The Single Sign-On Server compatability checklist.</h3>
		<div class="boxmain">
			Before beginning the installation, you should check to make sure that the server meets or exceeds
			the basic technical requirements.  Below is the checklist for compatability with Single Sign-On (SSO) Server.<br /><br />

			<div id="checklist"></div>
			<br />

			<script type="text/javascript">
			function RefreshChecklist()
			{
				$('#checklist').load('install.php', { 'action' : 'checklist' });

				return false;
			}

			RefreshChecklist();
			</script>

			<a href="#" onclick="return RefreshChecklist();">Refresh the checklist</a><br /><br />

			NOTE:  You are allowed to install Single Sign-On (SSO) Server even if you don't meet the requirements above.  Just don't complain if your
			installation or this installer does not work.  Each web server is different - there is no way to satisfy all servers
			without a ton of code.  Besides, you may be able to get away with some missing things for some websites.
		</div>

		<div class="boxbuttons">
			<a href="#" onclick="return Page(3, 2);">&laquo; Prev</a> | <a href="#" onclick="return Page(3, 4);">Next &raquo;</a>
		</div>
	</div>

	<div id="page4" class="box" style="display: none;">
		<h1>Database Setup</h1>
		<h3>Set up database options.</h3>
		<div class="boxmain">
			Single Sign-On (SSO) Server requires a database.<br /><br />

			<div class="formfields">
				<div class="formitem">
					<div class="formitemtitle">Database Server</div>
					<select id="db_select" name="db_select">
<?php
		$login = array();
		$replication = array();
		$databases = SSO_GetSupportedDatabases();
		foreach ($databases as $database => $info)
		{
			if ($info["production"] || $allowinsecuredatabases)
			{
				require_once "support/csdb/db_" . $database . ".php";

				try
				{
					$classname = "CSDB_" . $database;
					$db = new $classname();
					if ($db->IsAvailable() !== false)
					{
						echo "<option value=\"" . htmlspecialchars($database) . "\">" . htmlspecialchars($db->GetDisplayName()) . (!$info["production"] ? " [NOT for production use]" : "") . "</option>";
						$login[$database] = $info["login"];
						$replication[$database] = $info["replication"];
					}
				}
				catch (Exception $e)
				{
				}
			}
		}
?>
					</select>
					<div class="formitemdesc">Select a database server.  If the list is empty, please contact your hosting provider and ask them to enable a <a href="http://barebonescms.com/documentation/csdb/" target="_blank">supported PDO database driver</a>.</div>
				</div>
				<div class="db_selected" style="display: none;">
				<div class="formitem">
					<div class="formitemtitle">DSN Options</div>
					<input class="text" id="db_dsn" type="text" name="db_dsn" value="" />
					<div class="formitemdesc">The initial connection string to connect to the database server.  Options are driver specific.  Leave blank for the default.  Usually takes the form of:  host=ipaddr_or_hostname[;port=portnum] (e.g. host=localhost;port=3306)</div>
				</div>
				<div class="formitem db_login">
					<div class="formitemtitle">Username</div>
					<input class="text" id="db_user" type="text" name="db_user" />
					<div class="formitemdesc">The username to use to log into the database server.</div>
				</div>
				<div class="formitem db_login">
					<div class="formitemtitle">Password</div>
					<input class="text" id="db_pass" type="password" name="db_pass" />
					<div class="formitemdesc">The password to use to log into the database server.</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">Database</div>
					<input class="text" id="db_name" type="text" name="db_name" value="sso" />
					<div class="formitemdesc">The database to select after logging into the database server.</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">Table Prefix</div>
					<input class="text" id="db_sso_prefix" type="text" name="db_sso_prefix" value="sso_" />
					<div class="formitemdesc">The prefix to use for table names in the selected database.</div>
				</div>
				<div class="formitem db_replication">
					<div class="formitemtitle">Replication Master - DSN Options</div>
					<input class="text" id="db_master_dsn" type="text" name="db_master_dsn" value="" />
					<div class="formitemdesc">The connection string to connect to the master database server.  Do NOT use if you aren't using database replication!  Options are driver specific.  Usually takes the form of:  host=ipaddr_or_hostname[;port=portnum] (e.g. host=somehost;port=3306)</div>
				</div>
				<div class="formitem db_replication db_login">
					<div class="formitemtitle">Replication Master - Username</div>
					<input class="text" id="db_master_user" type="text" name="db_master_user" />
					<div class="formitemdesc">The username to use to log into the replication master database server.</div>
				</div>
				<div class="formitem db_replication db_login">
					<div class="formitemtitle">Replication Master - Password</div>
					<input class="text" id="db_master_pass" type="password" name="db_master_pass" />
					<div class="formitemdesc">The password to use to log into the replication master database server.</div>
				</div>
				</div>
			</div>
			<br />

			<div id="dbtestwrap" class="testresult">
				<div id="dbtest"></div>
			</div>
			<br />

			<script type="text/javascript">
			function RefreshDBTest()
			{
				$('#dbtestwrap').fadeIn('slow');
				$('#dbtest').load('install.php', { 'action' : 'dbtest', 'type' : $('#db_select').val(), 'dsn' : $('#db_dsn').val(), 'user' : $('#db_user').val(), 'pass' : $('#db_pass').val(), 'master_dsn' : $('#db_master_dsn').val(), 'master_user' : $('#db_master_user').val(), 'master_pass' : $('#db_master_pass').val() });

				return false;
			}

			var db_login = <?php echo json_encode($login); ?>;
			var db_replication = <?php echo json_encode($replication); ?>;

			function UpdateDBFields()
			{
				var val = $('#db_select').val();

				if (val == '')  $('.db_selected').hide();
				else
				{
					$('.db_selected').show();

					if (db_replication[val])  $('.db_replication').show();
					else  $('.db_replication').hide();

					if (db_login[val])  $('.db_login').show();
					else  $('.db_login').hide();
				}
			}

			$(function() {
				$('#db_select').change(UpdateDBFields).keyup(UpdateDBFields);
				UpdateDBFields();
			});
			</script>

			<a href="#" onclick="return RefreshDBTest();" class="db_selected" style="display: none;">Test the database settings</a><br /><br />

			Setting up a database is required for SSO information storage.
		</div>

		<div class="boxbuttons">
			<a href="#" onclick="return Page(4, 3);">&laquo; Prev</a> | <a href="#" onclick="return Page(4, 5);" class="db_selected" style="display: none;">Next &raquo;</a>
		</div>
	</div>

	<div id="page5" class="box" style="display: none;">
		<h1>Mail Setup</h1>
		<h3>Set up mail options.</h3>
		<div class="boxmain">
			Single Sign-On (SSO) Server itself does not utilize e-mail directly.  However, the SSO Generic Login provider and
			other optional features may send e-mail.<br /><br />

			<div class="formfields">
				<div class="formitem">
					<div class="formitemtitle">SMTP Server</div>
					<input class="text" id="smtp_server" type="text" name="smtp_server" value="localhost" />
					<div class="formitemdesc">The location of the SMTP server.  Can include a port (e.g. 'localhost:25').</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">POP3 Server</div>
					<input class="text" id="pop3_server" type="text" name="pop3_server" />
					<div class="formitemdesc">Some SMTP servers require <a href="http://en.wikipedia.org/wiki/POP_before_SMTP" target="_blank">POP3 before SMTP</a> is allowed to send e-mail.  Can also be used for accessing incoming e-mail programmatically via a widget.  Can include a port (e.g. 'localhost:110').</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">Default Username</div>
					<input class="text" id="smtppop3_user" type="text" name="smtppop3_user" />
					<div class="formitemdesc">The default username to use to log into the SMTP/POP3 server.</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">Default Password</div>
					<input class="text" id="smtppop3_pass" type="password" name="smtppop3_pass" />
					<div class="formitemdesc">The default password to use to log into the SMTP/POP3 server.</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">Default E-mail Address</div>
					<input class="text" id="smtp_from" type="text" name="smtp_from" />
					<div class="formitemdesc">The default e-mail address to send messages from.  Also supports the following format:  "Full Name" &lt;name@domain.com&gt;</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">Send To</div>
					<input class="text" id="smtp_to" type="text" name="smtp_to" />
					<div class="formitemdesc">The e-mail address to send a test message to when testing these settings.  NOTE:  Some mail servers block messages where 'from' and 'to' are the same.</div>
				</div>
			</div>
			<br />

			<div id="emailtestwrap" class="testresult">
				<div id="emailtest"></div>
			</div>
			<br />

			<script type="text/javascript">
			function RefreshEmailTest()
			{
				$('#emailtestwrap').fadeIn('slow');
				$('#emailtest').load('install.php', { 'action' : 'emailtest', 'smtpserver' : $('#smtp_server').val(), 'pop3server' : $('#pop3_server').val(), 'user' : $('#smtppop3_user').val(), 'pass' : $('#smtppop3_pass').val(), 'from' : $('#smtp_from').val(), 'to' : $('#smtp_to').val() });

				return false;
			}
			</script>

			<a href="#" onclick="return RefreshEmailTest();">Test the e-mail settings</a><br /><br />

			Setting up SMTP/POP3 is optional.  You can configure/change these options later by editing the generated 'config.php' file.
		</div>

		<div class="boxbuttons">
			<a href="#" onclick="return Page(5, 4);">&laquo; Prev</a> | <a href="#" onclick="return Page(5, 6);">Next &raquo;</a>
		</div>
	</div>

	<div id="page6" class="box" style="display: none;">
		<h1>Single Sign-On Server Setup</h1>
		<h3>Set up Single Sign-On (SSO) Server options.</h3>
		<div class="boxmain">
			Set up the Single Sign-On (SSO) Server base options.  Some of these can't be changed easily but the defaults are usually good enough.<br /><br />

			<div class="formfields">
				<div class="formitem">
					<div class="formitemtitle">Trusted 'X-Forwarded-For' Proxies</div>
					<input class="text" id="sso_proxy_x_forwarded_for" type="text" name="sso_proxy_x_forwarded_for" value="" />
					<div class="formitemdesc">A semi-colon separated list of IP addresses of trusted proxy servers that put the remote address into a 'X-Forwarded-For' HTTP header.</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">Trusted 'Client-IP' Proxies</div>
					<input class="text" id="sso_proxy_client_ip" type="text" name="sso_proxy_client_ip" value="" />
					<div class="formitemdesc">A semi-colon separated list of IP addresses of trusted proxy servers that put the remote address into a 'Client-IP' HTTP header.</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">SSO Site Admin Tag</div>
					<input class="text" id="sso_site_admin" type="text" name="sso_site_admin" value="sso_site_admin" />
					<div class="formitemdesc">The tag to use for users with the SSO site admin privilege.  Can do anything.  Only a limited number of users should have this tag.</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">SSO Admin Tag</div>
					<input class="text" id="sso_admin" type="text" name="sso_admin" value="sso_admin" />
					<div class="formitemdesc">The tag to use for users with the SSO admin privilege.  Slightly reduced access.</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">SSO Locked Account Tag</div>
					<input class="text" id="sso_locked" type="text" name="sso_locked" value="sso_locked" />
					<div class="formitemdesc">The tag to use for a locked user account.</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">Endpoint Security Through Obscurity (STO)</div>
					<select id="sso_endpoint_sto" name="sso_endpoint_sto">
						<option value="1">Yes</option>
						<option value="0">No</option>
					</select>
					<div class="formitemdesc">Move the SSO API endpoint into a randomly named subdirectory.  Difficult to guess but known to any environment that uses a SSO API key.</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">Admin Security Through Obscurity (STO)</div>
					<select id="sso_admin_sto" name="sso_admin_sto">
						<option value="1">Yes</option>
						<option value="0">No</option>
					</select>
					<div class="formitemdesc">Move the SSO admin interface into a randomly named subdirectory.  Difficult to guess but known to anyone who uses the SSO admin.</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">SSO Server Default Language</div>
					<input class="text" id="sso_default_lang" type="text" name="sso_default_lang" value="" />
					<div class="formitemdesc">The IANA language code of an installed language pack to use as the default language.</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">SSO Server Admin Language</div>
					<input class="text" id="sso_admin_lang" type="text" name="sso_admin_lang" value="" />
					<div class="formitemdesc">The IANA language code of an installed language pack to use as the language for the admin interface.</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">Primary Symmetric Cipher</div>
					<select id="sso_primary_cipher" name="sso_primary_cipher">
						<option value="blowfish">Blowfish</option>
						<option value="aes256">AES-256</option>
					</select>
					<div class="formitemdesc">The cipher to use for encrypted database storage.  The ordering of the ciphers is intentional.  Blowfish is preferred, having withstood two decades of cryptanalysis.</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">Use Dual Encryption</div>
					<select id="sso_dual_encrypt" name="sso_dual_encrypt">
						<option value="1">Yes</option>
						<option value="0">No</option>
					</select>
					<div class="formitemdesc">Two keys and two IVs are used to encrypt data twice with the same cipher as per <a href="http://cubicspot.blogspot.com/2013/02/extending-block-size-of-any-symmetric.html" target="_blank">this post</a>.</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">Use Less Safe Storage</div>
					<select id="sso_use_less_safe_storage" name="sso_use_less_safe_storage">
						<option value="no">No</option>
						<option value="yes">Yes</option>
					</select>
					<div class="formitemdesc">Enabling this allows setting files to be edited manually should the need arise.</div>
				</div>
				<div class="formitem">
					<div class="formitemtitle">Use CRON For Cleanup</div>
					<select id="sso_using_cron" name="sso_using_cron">
						<option value="0">No</option>
						<option value="1">Yes</option>
					</select>
					<div class="formitemdesc">For servers that have cron available to run 'cron.php' on a schedule.  Reduces the load on busy servers.</div>
				</div>
			</div>
		</div>

		<div class="boxbuttons">
			<a href="#" onclick="return Page(6, 5);">&laquo; Prev</a> | <a href="#" onclick="return Page(6, 7);">Next &raquo;</a>
		</div>
	</div>

	<div id="page7" class="box" style="display: none;">
		<h1>Ready To Install</h1>
		<h3>Ready to install Single Sign-On Server.</h3>
		<div class="boxmain">
			Single Sign-On Server is ready to install.  Click the link below to complete the installation process.
			Upon successful completion, 'install.php' (this installer) will be disabled.
			NOTE:  Be patient during the installation process.  It takes 5 to 30 seconds to complete.<br /><br />

			<div id="installwrap" class="testresult">
				<div id="install"></div>
			</div>
			<br />

			<script type="text/javascript">
			function Install()
			{
				$('#installlink').hide();
				$('.boxbuttons').hide();
				$('#installwrap').fadeIn('slow');
				$('#install').load('install.php', $('#installform').serialize() + '&rnd_' + Math.floor(Math.random() * 1000000));

				return false;
			}

			function InstallFailed()
			{
				$('#installlink').fadeIn('slow');
				$('.boxbuttons').fadeIn('slow');
			}
			</script>

			<a id="installlink" href="#" onclick="return Install();">Install Single Sign-On Server</a><br /><br />
		</div>

		<div class="boxbuttons">
			<a href="#" onclick="return Page(7, 6);">&laquo; Prev</a>
		</div>
	</div>

</div>
</form>
</body>
</html>
<?php
	}
?>