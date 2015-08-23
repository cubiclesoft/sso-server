<?php
	// Single Sign-On Server
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	define("SSO_FILE", 1);

	require_once "config.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/debug.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/str_basics.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/page_basics.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/sso_functions.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/random.php";

	SetDebugLevel();
	Str::ProcessAllInput();

	// Initialize the global CSPRNG instance.
	$sso_rng = new CSPRNG();

	// Calculate the remote IP address.
	$sso_ipaddr = SSO_GetRemoteIP();

	// Allow developers to inject code here.  For example, IP address restriction logic or a SSO bypass.
	if (file_exists("upgrade_hook.php"))  require_once "upgrade_hook.php";

	// Initialize language settings.
	BB_InitLangmap(SSO_ROOT_PATH . "/" . SSO_LANG_PATH . "/", SSO_DEFAULT_LANG);
	if (isset($_REQUEST["lang"]) && $_REQUEST["lang"] == "")  unset($_REQUEST["lang"]);
	if (isset($_REQUEST["lang"]))  BB_SetLanguage(SSO_ROOT_PATH . "/" . SSO_LANG_PATH . "/", $_REQUEST["lang"]);

	// Connect to the database and generate database globals.
	try
	{
		SSO_DBConnect(true);
	}
	catch (Exception $e)
	{
		SSO_DisplayError("Unable to connect to the database.");
	}

	// Load in fields without admin select.
	SSO_LoadFields(false);

	// Load in $sso_settings and initialize it.
	SSO_LoadSettings();

	if (SSO_USE_HTTPS && !BB_IsSSLRequest())  UpgradeError("SSL expected.  Most likely cause:  Bad server configuration.");

	function DisplayMessage($str)
	{
		echo BB_Translate($str) . "<br />\n";
	}

	function UpgradeError($str)
	{
		echo BB_Translate($str) . "<br />\n";

		exit();
	}

	if (!isset($sso_settings[""]["dbversion"]))  $sso_settings[""]["dbversion"] = 1;
	if ($sso_settings[""]["dbversion"] == 3)  UpgradeError("You already have the latest database version.  Upgrade completed.  You should delete this file off of the server.");

	// Begin upgrade.
	DisplayMessage("Beginning upgrade to latest version.");

	if ($sso_settings[""]["dbversion"] == 1)
	{
		// Add the namespace column to the API keys table.
		DisplayMessage("Adding 'namespace' column.");
		try
		{
			$sso_db->Query("ADD COLUMN", array($sso_db_apikeys, "namespace", array("STRING", 1, 20, "NOT NULL" => true), "AFTER" => "user_id"));
		}
		catch (Exception $e)
		{
			UpgradeError("Unable to update the database table '" . htmlspecialchars($sso_db_apikeys) . "'.  " . htmlspecialchars($e->getMessage()));
		}

		// Add an index on the namespace column.
		DisplayMessage("Adding 'namespace' index.");
		try
		{
			$sso_db->Query("ADD INDEX", array($sso_db_apikeys, array("KEY", array("namespace"), "NAME" => "namespace")));
		}
		catch (Exception $e)
		{
			UpgradeError("Unable to update the database table '" . htmlspecialchars($sso_db_apikeys) . "'.  " . htmlspecialchars($e->getMessage()));
		}

		// Wipe all existing sessions.
		DisplayMessage("Resetting all sessions.");
		try
		{
			$sso_db->Query("TRUNCATE TABLE", array($sso_db_user_sessions));
			$sso_db->Query("TRUNCATE TABLE", array($sso_db_temp_sessions));
		}
		catch (Exception $e)
		{
			UpgradeError("Unable to wipe sessions.  " . htmlspecialchars($e->getMessage()));
		}

		$sso_settings[""]["dbversion"] = 2;

		// Save the settings so the database version is saved.
		SSO_SaveSettings();
	}

	if ($sso_settings[""]["dbversion"] == 2)
	{
		// Generate random seeds.
		if (!defined("SSO_BASE_RAND_SEED8"))
		{
			$rng = new CSPRNG(true);
			for ($x = 0; $x < 10; $x++)
			{
				$seed = $rng->GenerateToken(128);
				if ($seed === false)  UpgradeError("Seed generation failed.");

				define("SSO_BASE_RAND_SEED" . ($x + 5), $seed);
			}

			$data = file_get_contents("config.php");
			$data .= "<" . "?php\n";
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
			$data .= "\n";
			$data .= "\tdefine(\"SSO_PRIMARY_CIPHER\", \"blowfish\");\n";
			$data .= "\tdefine(\"SSO_DUAL_ENCRYPT\", true);\n";
			$data .= "?" . ">";
			if (file_put_contents("config.php", $data) === false)  UpgradeError("Unable to update the server configuration file.");
		}

		// Regenerate namespace keys.
		SSO_GenerateNamespaceKeys();

		$sso_settings[""]["dbversion"] = 3;
	}

	// Save the settings so the database version is saved.
	SSO_SaveSettings();

	// Upgrade is done.
	DisplayMessage("Upgrade completed successfully.  You should delete this file off of the server.");
?>