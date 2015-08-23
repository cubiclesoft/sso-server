<?php
	// SSO Server Cron interface.
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	define("SSO_FILE", 1);

	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	// Temporary root.
	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/config.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/debug.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/cli.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/str_basics.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/page_basics.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/sso_functions.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/random.php";

	$sso_start = microtime(true);

	SetDebugLevel();

	// Process the command-line options.
	$options = array(
		"shortmap" => array(
			"d" => "debug",
			"v" => "verbose",
			"?" => "help"
		),
		"rules" => array(
			"debug" => array("arg" => false),
			"verbose" => array("arg" => false),
			"help" => array("arg" => false)
		)
	);
	$sso_args = ParseCommandLine($options);

	if (count($sso_args["params"]) != 0 || isset($sso_args["opts"]["help"]))
	{
		echo "SSO Server Cron Interface\n";
		echo "Purpose:  Run cleanup operations that need to be executed periodically.\n";
		echo "\n";
		echo "Syntax:  " . $args["file"] . " [options]\n";
		echo "Options:\n";
		echo "\t-d   Output debugging information.\n";
		echo "\t-v   Verbose output.\n";
		echo "\t-?   This help documentation.\n";

		exit();
	}

	$sso_debug = isset($sso_args["opts"]["debug"]);
	$sso_verbose = isset($sso_args["opts"]["verbose"]);

	// Initialize language settings.
	BB_InitLangmap(SSO_ROOT_PATH . "/" . SSO_LANG_PATH . "/", SSO_DEFAULT_LANG);
	BB_SetLanguage(SSO_ROOT_PATH . "/" . SSO_LANG_PATH . "/", SSO_ADMIN_LANG);

	// Initialize the global CSPRNG instance.
	$sso_rng = new CSPRNG();

	// Connect to the database and generate database globals.
	SSO_DBConnect(true);

	// Load in fields without admin select.
	SSO_LoadFields(false);

	// Load in $sso_settings and initialize it.
	SSO_LoadSettings();

	// Get system clock drift.
	$sso_clockdrift = (isset($sso_settings[""]["clock_drift"]) ? $sso_settings[""]["clock_drift"] : 300);

	// Allow developers to inject code here.
	if (file_exists(SSO_ROOT_PATH . "/cron_hook.php"))  require_once SSO_ROOT_PATH . "/cron_hook.php";

	// Run cleanup queries.
	try
	{
		$sso_db->Query("DELETE", array($sso_db_temp_sessions, "WHERE" => "updated < ?"), CSDB::ConvertToDBTime(time() - 60 * 60));
		$sso_db->Query("DELETE", array($sso_db_temp_sessions, "WHERE" => "heartbeat = ? AND updated < ?"), SSO_HEARTBEAT_LIMIT, CSDB::ConvertToDBTime(time() - $sso_clockdrift));
		$sso_db->Query("DELETE", array($sso_db_user_sessions, "WHERE" => "updated < ?"), CSDB::ConvertToDBTime(time() - $sso_clockdrift));
		$sso_db->Query("DELETE", array($sso_db_ipcache, "WHERE" => "created < ?"), CSDB::ConvertToDBTime(time() - 24 * 60 * 60 * $sso_settings[""]["iprestrict"]["ip_cache_len"]));
	}
	catch (Exception $e)
	{
		echo "Database query error.  " . $e->getMessage() . "\n\n";
	}

	if ($sso_verbose)
	{
		echo "Time taken:  " . number_format(microtime(true) - $sso_start, 2) . " sec\n";
		if (function_exists("memory_get_peak_usage"))  echo "Maximum RAM used:  " . number_format(memory_get_peak_usage(), 0) . "\n";

		echo "Done.\n";
	}
?>