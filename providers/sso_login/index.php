<?php
	// SSO Generic Login Provider
	// (C) 2015 CubicleSoft.  All Rights Reserved.

	if (!defined("SSO_FILE"))  exit();

	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/Base2n.php";

	class sso_login_ModuleBase
	{
		public function DefaultOrder()
		{
			return false;
		}

		public function ConfigSave()
		{
		}

		public function Config(&$contentopts)
		{
		}

		public function CustomConfig()
		{
		}

		public function CheckEditUserFields(&$userinfo)
		{
		}

		public function AddEditUserFields(&$contentopts, &$userinfo)
		{
		}

		public function IsAllowed()
		{
			return true;
		}

		public function AddProtectedFields(&$result)
		{
		}

		public function AddIPCacheInfo($displayname)
		{
		}

		public function ModifyEmail($userinfo, &$htmlmsg, &$textmsg)
		{
		}

		public function TwoFactorCheck(&$result, $userinfo)
		{
		}

		public function TwoFactorFailed(&$result, $userinfo)
		{
		}

		public function SignupCheck(&$result, $ajax, $admin)
		{
		}

		public function SignupAddInfo(&$userinfo, $admin)
		{
		}

		public function SignupDone($userid, $admin)
		{
		}

		public function GetTwoFactorName()
		{
			return false;
		}

		public function GenerateSignup($admin)
		{
		}

		public function VerifyCheck(&$result)
		{
		}

		public function InitMessages(&$result)
		{
		}

		public function LoginCheck(&$result, $userinfo, $recoveryallowed)
		{
		}

		public function SendTwoFactorCode(&$result, $userrow, $userinfo)
		{
		}

		public function LoginAddMap(&$mapinfo, $userrow, &$userinfo, $admin)
		{
		}

		public function GenerateLogin($messages)
		{
		}

		public function IsRecoveryAllowed($allowoptional)
		{
			return false;
		}

		public function AddRecoveryMethod($method)
		{
		}

		public function RecoveryCheck(&$result, $userinfo)
		{
		}

		public function RecoveryDone(&$result, $method, $userrow, $userinfo)
		{
		}

		public function GenerateRecovery($messages)
		{
		}

		public function RecoveryCheck2(&$result, $userinfo)
		{
		}

		public function GenerateRecovery2($messages)
		{
		}

		public function UpdateInfoCheck(&$result, $userinfo, $ajax)
		{
		}

		public function UpdateAddInfo(&$userinfo)
		{
		}

		public function UpdateInfoDone($userid)
		{
		}

		public function GenerateUpdateInfo($userrow, $userinfo)
		{
		}

		public function CustomFrontend()
		{
		}
	}

	// Load Modules.
	$g_sso_login_modules = array();
	$g_dirpath = SSO_ROOT_PATH . "/" . SSO_PROVIDER_PATH . "/sso_login/modules";
	$g_dirlist = SSO_GetDirectoryList($g_dirpath);
	foreach ($g_dirlist["dirs"] as $g_dir)
	{
		if (file_exists($g_dirpath . "/" . $g_dir . "/index.php"))  require_once $g_dirpath . "/" . $g_dir . "/index.php";
	}
	foreach ($g_dirlist["files"] as $g_file)
	{
		if (Str::ExtractFileExtension($g_file) == "php")  require_once $g_dirpath . "/" . $g_file;
	}

	class sso_login extends SSO_ProviderBase
	{
		public function Init()
		{
			global $sso_settings, $g_sso_login_modules;

			if (!isset($sso_settings["sso_login"]["installed"]))  $sso_settings["sso_login"]["installed"] = false;
			if (!isset($sso_settings["sso_login"]["enabled"]))  $sso_settings["sso_login"]["enabled"] = false;
			if (!isset($sso_settings["sso_login"]["install_type"]))  $sso_settings["sso_login"]["install_type"] = "";
			if (!isset($sso_settings["sso_login"]["open_reg"]))  $sso_settings["sso_login"]["open_reg"] = true;
			if (!isset($sso_settings["sso_login"]["change_username"]))  $sso_settings["sso_login"]["change_username"] = false;
			if (!isset($sso_settings["sso_login"]["change_email"]))  $sso_settings["sso_login"]["change_email"] = true;
			if (!isset($sso_settings["sso_login"]["require_two_factor"]))  $sso_settings["sso_login"]["require_two_factor"] = false;
			if (!isset($sso_settings["sso_login"]["two_factor_order"]))  $sso_settings["sso_login"]["two_factor_order"] = 25;
			if (!isset($sso_settings["sso_login"]["username_minlen"]))  $sso_settings["sso_login"]["username_minlen"] = 4;
			if (!isset($sso_settings["sso_login"]["username_blacklist"]))  $sso_settings["sso_login"]["username_blacklist"] = "";
			if (!isset($sso_settings["sso_login"]["email_verify_from"]))  $sso_settings["sso_login"]["email_verify_from"] = "";
			if (!isset($sso_settings["sso_login"]["email_verify_subject"]))  $sso_settings["sso_login"]["email_verify_subject"] = "";
			if (!isset($sso_settings["sso_login"]["email_verify_msg"]))  $sso_settings["sso_login"]["email_verify_msg"] = "";
			if (!isset($sso_settings["sso_login"]["email_verify_msg_text"]))  $sso_settings["sso_login"]["email_verify_msg_text"] = "";
			if (!isset($sso_settings["sso_login"]["email_recover_from"]))  $sso_settings["sso_login"]["email_recover_from"] = "";
			if (!isset($sso_settings["sso_login"]["email_recover_subject"]))  $sso_settings["sso_login"]["email_recover_subject"] = "";
			if (!isset($sso_settings["sso_login"]["email_recover_msg"]))  $sso_settings["sso_login"]["email_recover_msg"] = "";
			if (!isset($sso_settings["sso_login"]["email_recover_msg_text"]))  $sso_settings["sso_login"]["email_recover_msg_text"] = "";
			if (!isset($sso_settings["sso_login"]["email_session"]))  $sso_settings["sso_login"]["email_session"] = "verify";
			if (!isset($sso_settings["sso_login"]["email_bad_domains"]))  $sso_settings["sso_login"]["email_bad_domains"] = "";
			if (!isset($sso_settings["sso_login"]["password_minlen"]))  $sso_settings["sso_login"]["password_minlen"] = 8;
			if (!isset($sso_settings["sso_login"]["password_mode"]))  $sso_settings["sso_login"]["password_mode"] = (function_exists("password_hash") ? "password_hash_bcrypt" : "blowfish");
			if (!isset($sso_settings["sso_login"]["password_mintime"]))  $sso_settings["sso_login"]["password_mintime"] = 250;
			if (!isset($sso_settings["sso_login"]["password_minrounds"]))  $sso_settings["sso_login"]["password_minrounds"] = self::CalculateOptimalHashRounds($sso_settings["sso_login"]["password_mode"], $sso_settings["sso_login"]["password_mintime"]);
			if (!isset($sso_settings["sso_login"]["modules"]))  $sso_settings["sso_login"]["modules"] = array();
			if (!isset($sso_settings["sso_login"]["map_username"]) || !SSO_IsField($sso_settings["sso_login"]["map_username"]))  $sso_settings["sso_login"]["map_username"] = "";
			if (!isset($sso_settings["sso_login"]["map_email"]) || !SSO_IsField($sso_settings["sso_login"]["map_email"]))  $sso_settings["sso_login"]["map_email"] = "";
			if (!isset($sso_settings["sso_login"]["iprestrict"]))  $sso_settings["sso_login"]["iprestrict"] = SSO_InitIPFields();

			foreach ($g_sso_login_modules as $key => $info)
			{
				if (!isset($sso_settings["sso_login"]["modules"][$key]))  $sso_settings["sso_login"]["modules"][$key] = array("_a" => false);
			}
		}

		public function DisplayName()
		{
			return BB_Translate("Generic Login");
		}

		public function DefaultOrder()
		{
			return 50;
		}

		private function CanActivateUser()
		{
			global $sso_settings, $sso_site_admin;

			if (!$sso_site_admin)  return false;
			if (($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username") && $sso_settings["sso_login"]["map_username"] == "")  return false;
			if (($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email") && $sso_settings["sso_login"]["map_email"] == "")  return false;

			return true;
		}

		public function MenuOpts()
		{
			global $sso_site_admin, $sso_settings;

			$result = array(
				"name" => "Generic Login"
			);

			if ($sso_site_admin)
			{
				if ($sso_settings["sso_login"]["enabled"])
				{
					$result["items"] = array();

					if ($this->CanActivateUser())  $result["items"]["Create User"] = SSO_CreateConfigURL("createuser");
					$result["items"]["Configure"] = SSO_CreateConfigURL("config");
					$result["items"]["Disable"] = SSO_CreateConfigURL("disable");
				}
				else if ($sso_settings["sso_login"]["installed"])
				{
					$result["items"] = array(
						"Enable" => SSO_CreateConfigURL("enable")
					);
				}
				else
				{
					$result["items"] = array(
						"Install" => SSO_CreateConfigURL("install")
					);
				}
			}
			else if ($sso_settings["sso_login"]["enabled"])
			{
				$result["items"] = array(
					"Find User" => SSO_CreateConfigURL("finduser")
				);
			}

			return $result;
		}

		public function Config()
		{
			global $sso_rng, $sso_db, $sso_db_users, $sso_site_admin, $sso_settings, $sso_menuopts, $sso_select_fields, $g_sso_login_modules;

			$sso_db_sso_login_users = SSO_DB_PREFIX . "p_sso_login_users";

			if ($sso_site_admin && $sso_settings["sso_login"]["enabled"] && $_REQUEST["action2"] == "config")
			{
				if (isset($_REQUEST["configsave"]))
				{
					if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username")
					{
						$_REQUEST["username_blacklist"] = trim($_REQUEST["username_blacklist"]);
						$_REQUEST["username_minlen"] = (int)$_REQUEST["username_minlen"];

						if ($_REQUEST["username_minlen"] < 1)  BB_SetPageMessage("error", "The 'Minimum Username Length' field contains an invalid value.");
					}

					if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")
					{
						$_REQUEST["email_verify_msg"] = trim($_REQUEST["email_verify_msg"]);
						$_REQUEST["email_recover_msg"] = trim($_REQUEST["email_recover_msg"]);

						if ($_REQUEST["email_verify_msg"] != "" && stripos($_REQUEST["email_verify_msg"], "@VERIFY@") === false)  BB_SetPageMessage("error", "The 'Verify E-mail Message' field does not contain '@VERIFY@'.");
						else if ($_REQUEST["email_recover_msg"] != "" && stripos($_REQUEST["email_recover_msg"], "@VERIFY@") === false)  BB_SetPageMessage("error", "The 'Recovery E-mail Message' field does not contain '@VERIFY@'.");

						define("CS_TRANSLATE_FUNC", "BB_Translate");
						require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/smtp.php";

						if ($_REQUEST["email_verify_from"] != "")
						{
							$email = SMTP::MakeValidEmailAddress($_REQUEST["email_verify_from"]);
							if (!$email["success"])  BB_SetPageMessage("error", BB_Translate("The e-mail address '%s' is invalid.  %s", $_REQUEST["email_verify_from"], $email["error"]));
							else
							{
								if ($email["email"] != trim($_REQUEST["email_verify_from"]))  BB_SetPageMessage("info", BB_Translate("Invalid e-mail address.  Perhaps you meant '%s' instead?", $email["email"]));

								$_REQUEST["email_verify_from"] = $email["email"];
							}
						}

						if ($_REQUEST["email_recover_from"] != "")
						{
							$email = SMTP::MakeValidEmailAddress($_REQUEST["email_recover_from"]);
							if (!$email["success"])  BB_SetPageMessage("error", BB_Translate("The e-mail address '%s' is invalid.  %s", $_REQUEST["email_recover_from"], $email["error"]));
							else
							{
								if ($email["email"] != trim($_REQUEST["email_recover_from"]))  BB_SetPageMessage("info", BB_Translate("Invalid e-mail address.  Perhaps you meant '%s' instead?", $email["email"]));

								$_REQUEST["email_recover_from"] = $email["email"];
							}
						}
					}

					$_REQUEST["two_factor_order"] = (int)$_REQUEST["two_factor_order"];
					$_REQUEST["password_minlen"] = (int)$_REQUEST["password_minlen"];
					$_REQUEST["password_mintime"] = (int)$_REQUEST["password_mintime"];
					if ($_REQUEST["two_factor_order"] < 0)  BB_SetPageMessage("error", "The 'Two-Factor Authentication Display Order' field contains an invalid value.");
					else if ($_REQUEST["password_minlen"] < 0)  BB_SetPageMessage("error", "The 'Minimum Password Length' field contains an invalid value.");
					else if ($_REQUEST["password_mintime"] < 50)  BB_SetPageMessage("error", "The 'Minimum Password Time' field contains an invalid value.  Must be at least 50 milliseconds.");
					else if ($_REQUEST["password_mintime"] > 5000)  BB_SetPageMessage("error", "The 'Minimum Password Time' field contains an invalid value.  Must be less than 5000 milliseconds (5 seconds).");

					foreach ($g_sso_login_modules as $key => $info)
					{
						if ($_REQUEST[$key . "__a"] < 1)  $sso_settings["sso_login"]["modules"][$key]["_a"] = false;

						if ($sso_settings["sso_login"]["modules"][$key]["_a"])
						{
							$module = "sso_login_module_" . $key;
							$instance = new $module;

							if ($instance->DefaultOrder() !== false)
							{
								if ((int)$_REQUEST[$key . "__s"] < 0)  BB_SetPageMessage("error", BB_Translate("The '%s Module Display Order' field contains an invalid value.", $info["name"]));
								else  $sso_settings["sso_login"]["modules"][$key]["_s"] = $_REQUEST[$key . "__s"];
							}

							$instance->ConfigSave();
						}

						$sso_settings["sso_login"]["modules"][$key]["_a"] = ($_REQUEST[$key . "__a"] > 0);
					}

					$sso_settings["sso_login"]["iprestrict"] = SSO_ProcessIPFields();

					if (BB_GetPageMessageType() != "error")
					{
						if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username")
						{
							$sso_settings["sso_login"]["map_username"] = (SSO_IsField($_REQUEST["map_username"]) ? $_REQUEST["map_username"] : "");
							$sso_settings["sso_login"]["username_minlen"] = $_REQUEST["username_minlen"];
							$sso_settings["sso_login"]["username_blacklist"] = $_REQUEST["username_blacklist"];
							$sso_settings["sso_login"]["change_username"] = ($_REQUEST["change_username"] > 0);
						}

						if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")
						{
							$sso_settings["sso_login"]["map_email"] = (SSO_IsField($_REQUEST["map_email"]) ? $_REQUEST["map_email"] : "");
							$sso_settings["sso_login"]["email_verify_from"] = $_REQUEST["email_verify_from"];
							$sso_settings["sso_login"]["email_verify_subject"] = trim($_REQUEST["email_verify_subject"]);
							$sso_settings["sso_login"]["email_verify_msg"] = $_REQUEST["email_verify_msg"];
							$sso_settings["sso_login"]["email_verify_msg_text"] = SMTP::ConvertHTMLToText($_REQUEST["email_verify_msg"]);
							$sso_settings["sso_login"]["email_recover_from"] = $_REQUEST["email_recover_from"];
							$sso_settings["sso_login"]["email_recover_subject"] = trim($_REQUEST["email_recover_subject"]);
							$sso_settings["sso_login"]["email_recover_msg"] = $_REQUEST["email_recover_msg"];
							$sso_settings["sso_login"]["email_recover_msg_text"] = SMTP::ConvertHTMLToText($_REQUEST["email_recover_msg"]);
							$sso_settings["sso_login"]["email_session"] = ($_REQUEST["email_session"] == "none" || $_REQUEST["email_session"] == "all" ? $_REQUEST["email_session"] : "verify");
							$sso_settings["sso_login"]["email_bad_domains"] = $_REQUEST["email_bad_domains"];
							$sso_settings["sso_login"]["change_email"] = ($_REQUEST["change_email"] > 0);
						}

						$sso_settings["sso_login"]["require_two_factor"] = ($_REQUEST["require_two_factor"] > 0);
						$sso_settings["sso_login"]["two_factor_order"] = $_REQUEST["two_factor_order"];
						$sso_settings["sso_login"]["password_minlen"] = $_REQUEST["password_minlen"];

						$modetimechanged = ($sso_settings["sso_login"]["password_mode"] != $_REQUEST["password_mode"] || $sso_settings["sso_login"]["password_mintime"] != $_REQUEST["password_mintime"]);
						$sso_settings["sso_login"]["password_mode"] = $_REQUEST["password_mode"];
						$sso_settings["sso_login"]["password_mintime"] = $_REQUEST["password_mintime"];
						if ($modetimechanged)  $sso_settings["sso_login"]["password_minrounds"] = self::CalculateOptimalHashRounds($sso_settings["sso_login"]["password_mode"], $sso_settings["sso_login"]["password_mintime"]);

						$sso_settings["sso_login"]["open_reg"] = ($_REQUEST["open_reg"] > 0);

						if (!SSO_SaveSettings())  BB_SetPageMessage("error", "Unable to save settings.");
						else if (BB_GetPageMessageType() == "info")  SSO_ConfigRedirect("config", array(), "info", $_REQUEST["bb_msg"] . "  " . BB_Translate("Successfully updated the %s provider configuration.", $this->DisplayName()));
						else  SSO_ConfigRedirect("config", array(), "success", BB_Translate("Successfully updated the %s provider configuration.", $this->DisplayName()));
					}
				}

				$contentopts = array(
					"desc" => BB_Translate("Configure the %s provider.", $this->DisplayName()),
					"nonce" => "action",
					"hidden" => array(
						"action" => "config",
						"provider" => "sso_login",
						"action2" => "config",
						"configsave" => "1"
					),
					"fields" => array(),
					"submit" => "Save",
					"focus" => true
				);

				if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username")
				{
					$contentopts["fields"][] = array(
						"title" => "Username Settings",
						"type" => "accordion"
					);
					$contentopts["fields"][] = array(
						"title" => "Map Username",
						"type" => "select",
						"name" => "map_username",
						"options" => $sso_select_fields,
						"select" => BB_GetValue("map_username", (string)$sso_settings["sso_login"]["map_username"]),
						"desc" => "The field in the SSO system to map the username to."
					);
					$contentopts["fields"][] = array(
						"title" => "Minimum Username Length",
						"type" => "text",
						"name" => "username_minlen",
						"value" => BB_GetValue("username_minlen", $sso_settings["sso_login"]["username_minlen"]),
						"desc" => "The minimum number of characters a username must have."
					);
					$contentopts["fields"][] = array(
						"title" => "Username Blacklist",
						"type" => "textarea",
						"height" => "300px",
						"name" => "username_blacklist",
						"value" => BB_GetValue("username_blacklist", $sso_settings["sso_login"]["username_blacklist"]),
						"desc" => "A blacklist of words that a username may not contain.  One per line."
					);
					$contentopts["fields"][] = array(
						"title" => "Allow Username Changes",
						"type" => "select",
						"name" => "change_username",
						"options" => array(1 => "Yes", 0 => "No"),
						"select" => BB_GetValue("change_username", (string)(int)$sso_settings["sso_login"]["change_username"]),
						"desc" => "Users may change their usernames."
					);
					$contentopts["fields"][] = "endaccordion";
				}

				if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")
				{
					$contentopts["fields"][] = array(
						"title" => "E-mail Settings",
						"type" => "accordion"
					);
					$contentopts["fields"][] = array(
						"title" => "Map E-mail Address",
						"type" => "select",
						"name" => "map_email",
						"options" => $sso_select_fields,
						"select" => BB_GetValue("map_email", (string)$sso_settings["sso_login"]["map_email"]),
						"desc" => "The field in the SSO system to map the e-mail address to."
					);
					$contentopts["fields"][] = array(
						"title" => "Verification E-mail - From Address",
						"type" => "text",
						"name" => "email_verify_from",
						"value" => BB_GetValue("email_verify_from", $sso_settings["sso_login"]["email_verify_from"]),
						"desc" => "The from address for the e-mail message to send to new registrants.  Leave blank for the server default."
					);
					$contentopts["fields"][] = array(
						"title" => "Verification E-mail - Subject Line",
						"type" => "text",
						"name" => "email_verify_subject",
						"value" => BB_GetValue("email_verify_subject", $sso_settings["sso_login"]["email_verify_subject"]),
						"desc" => "The subject line for the e-mail message to send to new registrants."
					);
					$contentopts["fields"][] = array(
						"title" => "Verification E-mail - HTML Message",
						"type" => "textarea",
						"height" => "300px",
						"name" => "email_verify_msg",
						"value" => BB_GetValue("email_verify_msg", $sso_settings["sso_login"]["email_verify_msg"]),
						"desc" => "The HTML e-mail message to send to new registrants.  @USERNAME@, @EMAIL@, and @VERIFY@ are special strings that will be replaced with user and system generated values.  @VERIFY@ is required."
					);
					$contentopts["fields"][] = array(
						"title" => "Recovery E-mail - From Address",
						"type" => "text",
						"name" => "email_recover_from",
						"value" => BB_GetValue("email_recover_from", $sso_settings["sso_login"]["email_recover_from"]),
						"desc" => "The from address for the e-mail message to send to users recovering access to their account.  Leave blank for the server default."
					);
					$contentopts["fields"][] = array(
						"title" => "Recovery E-mail - Subject Line",
						"type" => "text",
						"name" => "email_recover_subject",
						"value" => BB_GetValue("email_recover_subject", $sso_settings["sso_login"]["email_recover_subject"]),
						"desc" => "The subject line for the e-mail message to send to users recovering access to their account."
					);
					$contentopts["fields"][] = array(
						"title" => "Recovery E-mail - HTML Message",
						"type" => "textarea",
						"height" => "300px",
						"name" => "email_recover_msg",
						"value" => BB_GetValue("email_recover_msg", $sso_settings["sso_login"]["email_recover_msg"]),
						"desc" => "The HTML e-mail message to send to users recovering access to their account.  @USERNAME@, @EMAIL@, and @VERIFY@ are special strings that will be replaced with user and system generated values.  @VERIFY@ is required."
					);
					$contentopts["fields"][] = array(
						"title" => "Verification/Recovery E-mail - Send Session ID",
						"type" => "select",
						"name" => "email_session",
						"options" => array("none" => "Never", "verify" => "Verification e-mail only", "all" => "Verification and recovery e-mails"),
						"select" => BB_GetValue("email_session", $sso_settings["sso_login"]["email_session"]),
						"desc" => "Send the session ID as part of the URL in an e-mail.  When the session ID isn't sent, the same browser session must be used with the URL or an error message will appear.  Sending the session ID for recovery e-mails is not recommended."
					);
					$contentopts["fields"][] = array(
						"title" => "E-mail Domain Blacklist",
						"type" => "textarea",
						"height" => "300px",
						"name" => "email_bad_domains",
						"value" => BB_GetValue("email_bad_domains", $sso_settings["sso_login"]["email_bad_domains"]),
						"desc" => "A blacklist of e-mail address domains that are not allowed to create accounts.  One per line."
					);
					$contentopts["fields"][] = array(
						"title" => "Allow E-mail Address Changes",
						"type" => "select",
						"name" => "change_email",
						"options" => array(1 => "Yes", 0 => "No"),
						"select" => BB_GetValue("change_email", (string)(int)$sso_settings["sso_login"]["change_email"]),
						"desc" => "Users may change their e-mail addresses."
					);
					$contentopts["fields"][] = "endaccordion";
				}

				$contentopts["fields"][] = array(
					"title" => "Other Settings",
					"type" => "accordion"
				);
				$contentopts["fields"][] = array(
					"title" => "Require Two-Factor Authentication",
					"type" => "select",
					"name" => "require_two_factor",
					"options" => array(1 => "Yes", 0 => "No"),
					"select" => BB_GetValue("require_two_factor", (string)(int)$sso_settings["sso_login"]["require_two_factor"]),
					"desc" => "Users have to select and sign in with a two-factor authentication method.  Existing users will have to use account recovery to set up two-factor authentication."
				);
				$contentopts["fields"][] = array(
					"title" => "Two-Factor Authentication Display Order",
					"type" => "text",
					"name" => "two_factor_order",
					"value" => BB_GetValue("two_factor_order", $sso_settings["sso_login"]["two_factor_order"]),
					"desc" => "The display order to use for the two-factor authentication dropdown."
				);

				$contentopts["fields"][] = array(
					"title" => "Minimum Password Length",
					"type" => "text",
					"name" => "password_minlen",
					"value" => BB_GetValue("password_minlen", $sso_settings["sso_login"]["password_minlen"]),
					"desc" => "The minimum number of characters a password must have."
				);
				$options = array();
				if (function_exists("password_hash"))  $options["password_hash_bcrypt"] = "password_hash() - Native PHP Bcrypt hashing";
				$options["blowfish"] = "Blowfish::Hash() - A Bcrypt-like hash";
				$contentopts["fields"][] = array(
					"title" => "Password Hashing Mode",
					"type" => "select",
					"name" => "password_mode",
					"options" => $options,
					"select" => BB_GetValue("password_mode", $sso_settings["sso_login"]["password_mode"]),
					"desc" => "The password hashing mode to use.  Note that changing the hashing mode will force all users to change their passwords.  If account recovery is not possible, users will be unable to access their accounts."
				);
				$contentopts["fields"][] = array(
					"title" => "Minimum Password Time",
					"type" => "text",
					"name" => "password_mintime",
					"value" => BB_GetValue("password_mintime", $sso_settings["sso_login"]["password_mintime"]),
					"desc" => "The minimum amount of time, in milliseconds, required to spend to initially hash a password."
				);
				$contentopts["fields"][] = array(
					"title" => "Minimum Password Rounds",
					"type" => "static",
					"value" => $sso_settings["sso_login"]["password_minrounds"],
					"desc" => "The minimum number of rounds required to hash a password.  Automatically calculated." . ($sso_settings["sso_login"]["password_minrounds"] < 128 ? "  WARNING:  Due to the low number of minimum rounds, stored passwords will not be as secure as they should be.  Please select a different password hashing mode and/or increase the minimum hashing time." : "")
				);

				$contentopts["fields"][] = array(
					"title" => "Open Registration",
					"type" => "select",
					"name" => "open_reg",
					"options" => array(1 => "Yes", 0 => "No"),
					"select" => BB_GetValue("open_reg", (string)(int)$sso_settings["sso_login"]["open_reg"]),
					"desc" => "Users may register for new accounts."
				);
				$contentopts["fields"][] = "endaccordion";

				$contentopts["fields"][] = "split";

				foreach ($g_sso_login_modules as $key => $info)
				{
					$contentopts["fields"][] = array(
						"title" => BB_Translate(($sso_settings["sso_login"]["modules"][$key]["_a"] ? "%s Module *" : "%s Module"), $info["name"]),
						"type" => "accordion"
					);
					$contentopts["fields"][] = array(
						"title" => BB_Translate("%s Module Enabled?", $info["name"]),
						"type" => "select",
						"name" => $key . "__a",
						"options" => array(1 => "Yes", 0 => "No"),
						"select" => BB_GetValue($key . "__a", (string)(int)$sso_settings["sso_login"]["modules"][$key]["_a"]),
						"desc" => BB_Translate("Enables the %s module.  %s", $info["name"], $info["desc"])
					);

					if ($sso_settings["sso_login"]["modules"][$key]["_a"])
					{
						$module = "sso_login_module_" . $key;
						$instance = new $module;

						if ($instance->DefaultOrder() !== false)
						{
							$contentopts["fields"][] = array(
								"title" => BB_Translate("%s Module Display Order", $info["name"]),
								"type" => "text",
								"name" => $key . "__s",
								"value" => BB_GetValue($key . "__s", (string)(int)(isset($sso_settings["sso_login"]["modules"][$key]["_s"]) ? $sso_settings["sso_login"]["modules"][$key]["_s"] : $instance->DefaultOrder())),
								"desc" => BB_Translate("The display order to use for the %s module.", $info["name"])
							);
						}

						$instance->Config($contentopts);
					}
					$contentopts["fields"][] = "endaccordion";
				}

				SSO_AppendIPFields($contentopts, $sso_settings["sso_login"]["iprestrict"]);

				BB_GeneratePage(BB_Translate("Configure %s", $this->DisplayName()), $sso_menuopts, $contentopts);
			}
			else if ($sso_site_admin && $sso_settings["sso_login"]["enabled"] && $_REQUEST["action2"] == "disable")
			{
				$sso_settings["sso_login"]["enabled"] = false;

				if (!SSO_SaveSettings())  BB_RedirectPage("error", "Unable to save settings.");
				else  BB_RedirectPage("success", BB_Translate("Successfully disabled the %s provider.", $this->DisplayName()));
			}
			else if ($sso_site_admin && !$sso_settings["sso_login"]["enabled"] && $_REQUEST["action2"] == "enable")
			{
				$sso_settings["sso_login"]["enabled"] = true;

				if (!SSO_SaveSettings())  BB_RedirectPage("error", "Unable to save settings.");
				else  BB_RedirectPage("success", BB_Translate("Successfully enabled the %s provider.", $this->DisplayName()));
			}
			else if ($sso_site_admin && !$sso_settings["sso_login"]["installed"] && $_REQUEST["action2"] == "install")
			{
				if (isset($_REQUEST["type"]))
				{
					if ($sso_db->TableExists($sso_db_sso_login_users))  BB_SetPageMessage("error", "The database table '" . $sso_db_sso_login_users . "' already exists.");
					if ($_REQUEST["type"] != "email_username" && $_REQUEST["type"] != "email" && $_REQUEST["type"] != "username")  BB_SetPageMessage("error", "Please select a valid 'Registration Key'.");

					if (BB_GetPageMessageType() != "error")
					{
						try
						{
							if ($_REQUEST["type"] == "email_username")
							{
								$sso_db->Query("CREATE TABLE", array($sso_db_sso_login_users, array(
									"id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true, "PRIMARY KEY" => true, "AUTO INCREMENT" => true),
									"username" => array("STRING", 1, 75, "NOT NULL" => true),
									"email" => array("STRING", 1, 255, "NOT NULL" => true),
									"verified" => array("INTEGER", 1, "NOT NULL" => true),
									"created" => array("DATETIME", "NOT NULL" => true),
									"info" => array("STRING", 3, "NOT NULL" => true),
								),
								array(
									array("UNIQUE", array("username"), "NAME" => $sso_db_sso_login_users . "_username"),
									array("UNIQUE", array("email"), "NAME" => $sso_db_sso_login_users . "_email"),
								)));
							}
							else if ($_REQUEST["type"] == "email")
							{
								$sso_db->Query("CREATE TABLE", array($sso_db_sso_login_users, array(
									"id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true, "PRIMARY KEY" => true, "AUTO INCREMENT" => true),
									"email" => array("STRING", 1, 255, "NOT NULL" => true),
									"verified" => array("INTEGER", 1, "NOT NULL" => true),
									"created" => array("DATETIME", "NOT NULL" => true),
									"info" => array("STRING", 3, "NOT NULL" => true),
								),
								array(
									array("UNIQUE", array("email"), "NAME" => $sso_db_sso_login_users . "_email"),
								)));
							}
							else if ($_REQUEST["type"] == "username")
							{
								$sso_db->Query("CREATE TABLE", array($sso_db_sso_login_users, array(
									"id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true, "PRIMARY KEY" => true, "AUTO INCREMENT" => true),
									"username" => array("STRING", 1, 75, "NOT NULL" => true),
									"created" => array("DATETIME", "NOT NULL" => true),
									"info" => array("STRING", 3, "NOT NULL" => true),
								),
								array(
									array("UNIQUE", array("username"), "NAME" => $sso_db_sso_login_users . "_username"),
								)));
							}

							$sso_settings["sso_login"]["installed"] = true;
							$sso_settings["sso_login"]["enabled"] = true;
							$sso_settings["sso_login"]["install_type"] = $_REQUEST["type"];

							if (!SSO_SaveSettings())  BB_SetPageMessage("error", "Unable to save settings.");
							else  SSO_ConfigRedirect("config", array(), "success", BB_Translate("Successfully installed the %s provider.", $this->DisplayName()));
						}
						catch (Exception $e)
						{
							BB_SetPageMessage("error", "Unable to create the database table '" . htmlspecialchars($sso_db_sso_login_users) . "'.  " . $e->getMessage());
						}
					}
				}

				$contentopts = array(
					"desc" => BB_Translate("Install the %s provider.", $this->DisplayName()),
					"nonce" => "action",
					"hidden" => array(
						"action" => "config",
						"provider" => "sso_login",
						"action2" => "install"
					),
					"fields" => array(
						array(
							"title" => "Registration Key(s)",
							"type" => "select",
							"name" => "type",
							"options" => array("email_username" => "E-mail Address and Username", "email" => "E-mail Address only", "username" => "Username only"),
							"select" => BB_GetValue("type", ""),
							"desc" => "The unique fields to require for a registration system entry.  This can't be changed after installing.  The default is highly recommended."
						),
					),
					"submit" => "Install",
					"focus" => true
				);

				BB_GeneratePage(BB_Translate("Install %s", $this->DisplayName()), $sso_menuopts, $contentopts);
			}
			else if ($sso_settings["sso_login"]["enabled"] && $_REQUEST["action2"] == "activateuser" && $this->CanActivateUser())
			{
				if (!isset($_REQUEST["id"]))  SSO_ConfigRedirect("finduser", array(), "error", "User ID not specified.");

				$userrow = $sso_db->GetRow("SELECT", array(
					"*",
					"FROM" => "?",
					"WHERE" => "id = ?",
				), $sso_db_sso_login_users, $_REQUEST["id"]);

				if (!$userrow)  SSO_ConfigRedirect("finduser", array(), "error", "User not found.");
				if (!isset($userrow->email))  $userrow->email = "";
				if (!isset($userrow->username))  $userrow->username = "";
				if (!isset($userrow->verified))  $userrow->verified = 1;
				$userinfo = SSO_DecryptDBData($userrow->info);

				// Activate the user.
				$mapinfo = array();
				if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")  $mapinfo[$sso_settings["sso_login"]["map_email"]] = $userrow->email;
				if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username")  $mapinfo[$sso_settings["sso_login"]["map_username"]] = $userrow->username;

				// Initialize active modules.
				$this->activemodules = array();
				foreach ($g_sso_login_modules as $key => $info)
				{
					if ($sso_settings["sso_login"]["modules"][$key]["_a"])
					{
						$module = "sso_login_module_" . $key;
						$instance = new $module;
						$instance->LoginAddMap($mapinfo, $userrow, $userinfo, true);
					}
				}

				SSO_ActivateUser($userrow->id, $userinfo["extra"], $mapinfo, CSDB::ConvertFromDBTime($userrow->created), false, false);

				SSO_ConfigRedirect("edituser", array("id" => $userrow->id), "success", "Successfully activated the user.");
			}
			else if ($sso_settings["sso_login"]["enabled"] && $_REQUEST["action2"] == "edituser")
			{
				if (!isset($_REQUEST["id"]))  SSO_ConfigRedirect("finduser", array(), "error", "User ID not specified.");

				$row = $sso_db->GetRow("SELECT", array(
					"*",
					"FROM" => "?",
					"WHERE" => "id = ?",
				), $sso_db_sso_login_users, $_REQUEST["id"]);

				if (!$row)  SSO_ConfigRedirect("finduser", array(), "error", "User not found.");
				if (!isset($row->email))  $row->email = "";
				if (!isset($row->username))  $row->username = "";
				if (!isset($row->verified))  $row->verified = 1;
				$userinfo = SSO_DecryptDBData($row->info);

				// Initialize active modules.
				$this->activemodules = array();
				foreach ($g_sso_login_modules as $key => $info)
				{
					if ($sso_settings["sso_login"]["modules"][$key]["_a"])
					{
						$module = "sso_login_module_" . $key;
						$this->activemodules[$key] = new $module;
					}
				}

				if (isset($_REQUEST["reset_password"]))
				{
					$username = $row->username;
					$email = $row->email;

					if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username")
					{
						if ($_REQUEST["username"] == "")  BB_SetPageMessage("error", "Please specify a username.");
						else if ($_REQUEST["username"] != $row->username && $sso_db->GetOne("SELECT", array("COUNT(*)", "FROM" => "?", "WHERE" => "username = ?"), $sso_db_sso_login_users, $_REQUEST["username"]))  BB_SetPageMessage("error", "Username is already in use.");
						else  $username = $_REQUEST["username"];
					}
					if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")
					{
						if ($_REQUEST["email"] == "")  BB_SetPageMessage("error", "Please specify an e-mail address.");
						else if ($_REQUEST["email"] != $row->email && $sso_db->GetOne("SELECT", array("COUNT(*)", "FROM" => "?", "WHERE" => "email = ?"), $sso_db_sso_login_users, $_REQUEST["email"]))  BB_SetPageMessage("error", "E-mail Address is already in use.");
						else  $email = $_REQUEST["email"];
					}

					foreach ($g_sso_login_modules as $key => $info)
					{
						if ($sso_settings["sso_login"]["modules"][$key]["_a"])
						{
							$module = "sso_login_module_" . $key;
							$instance = new $module;
							$instance->CheckEditUserFields($userinfo);
						}
					}

					if (BB_GetPageMessageType() != "error" && $_REQUEST["reset_password"] > 0)
					{
						if ($_REQUEST["reset_password"] == 1)
						{
							$phrase = "";
							for ($x = 0; $x < 4; $x++)  $phrase .= " " . SSO_GetRandomWord();
							$phrase = preg_replace('/\s+/', " ", trim($phrase));

							$salt = $sso_rng->GenerateString();
							$data = $username . ":" . $email . ":" . $salt . ":" . $phrase;
							$passwordinfo = self::HashPasswordInfo($data, $sso_settings["sso_login"]["password_mode"], $sso_settings["sso_login"]["password_minrounds"]);
							if (!$passwordinfo["success"])  BB_SetPageMessage("error", "Unexpected cryptography error.");
							else
							{
								$userinfo["salt"] = $salt;
								$userinfo["rounds"] = (int)$passwordinfo["rounds"];
								$userinfo["password"] = bin2hex($passwordinfo["hash"]);

								BB_SetPageMessage("info", BB_Translate("Password has been changed to '%s'.", $phrase));
							}
						}
						else if ($this->IsRecoveryAllowed(false) && $_REQUEST["reset_password"] == 2)
						{
							$userinfo["rounds"] = 0;
							$userinfo["password"] = "";
						}
					}

					if (BB_GetPageMessageType() != "error")
					{
						try
						{
							$userinfo2 = SSO_EncryptDBData($userinfo);

							if ($sso_settings["sso_login"]["install_type"] == "email_username")
							{
								$sso_db->Query("UPDATE", array($sso_db_sso_login_users, array(
									"username" => $_REQUEST["username"],
									"email" => $_REQUEST["email"],
									"verified" => ((int)$_REQUEST["verified"] > 0 ? 1 : 0),
									"info" => $userinfo2,
								), "WHERE" => "id = ?"), $row->id);
							}
							else if ($sso_settings["sso_login"]["install_type"] == "email")
							{
								$sso_db->Query("UPDATE", array($sso_db_sso_login_users, array(
									"email" => $_REQUEST["email"],
									"verified" => ((int)$_REQUEST["verified"] > 0 ? 1 : 0),
									"info" => $userinfo2,
								), "WHERE" => "id = ?"), $row->id);
							}
							else if ($sso_settings["sso_login"]["install_type"] == "username")
							{
								$sso_db->Query("UPDATE", array($sso_db_sso_login_users, array(
									"username" => $_REQUEST["username"],
									"info" => $userinfo2,
								), "WHERE" => "id = ?"), $row->id);
							}

							if (BB_GetPageMessageType() == "info")  SSO_ConfigRedirect("edituser", array("id" => $row->id), "info", $_REQUEST["bb_msg"] . "  Successfully updated the user.");
							else  SSO_ConfigRedirect("edituser", array("id" => $row->id), "success", "Successfully updated the user.");
						}
						catch (Exception $e)
						{
							BB_SetPageMessage("error", "Database query error.");
						}
					}
				}

				$desc = "<br />";

				$row2 = $sso_db->GetRow("SELECT", array(
					"*",
					"FROM" => "?",
					"WHERE" => "provider_name = 'sso_login' AND provider_id = ?",
				), $sso_db_users, $row->id);

				if ($row2)  $desc .= "<a href=\"" . BB_GetRequestURLBase() . "?action=edituser&id=" . $row2->id . "&sec_t=" . BB_CreateSecurityToken("edituser") . "\">Edit SSO Server Info</a>";
				else if ($this->CanActivateUser())  $desc .= SSO_CreateConfigLink("Activate User", "activateuser", array("id" => $row->id), "Are you sure you want to activate this user?");

				$contentopts = array(
					"desc" => BB_Translate("Edit the %s user.", $this->DisplayName()),
					"htmldesc" => $desc,
					"nonce" => "action",
					"hidden" => array(
						"action" => "config",
						"provider" => "sso_login",
						"action2" => "edituser",
						"id" => $row->id
					),
					"fields" => array(
						array(
							"title" => "ID",
							"type" => "static",
							"value" => $row->id
						),
					),
					"submit" => "Save",
					"focus" => true
				);

				if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username")
				{
					$contentopts["fields"][] = array(
						"title" => "Username",
						"type" => "text",
						"name" => "username",
						"value" => BB_GetValue("username", $row->username)
					);
				}
				if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")
				{
					$contentopts["fields"][] = array(
						"title" => "E-mail Address",
						"type" => "text",
						"name" => "email",
						"value" => BB_GetValue("email", $row->email)
					);

					$contentopts["fields"][] = array(
						"title" => "Verified",
						"type" => "select",
						"name" => "verified",
						"options" => array("1" => "Yes", "0" => "No"),
						"select" => BB_GetValue("verified", (string)$row->verified)
					);
				}

				$contentopts["fields"][] = array(
					"title" => "Password Hash Rounds",
					"type" => "static",
					"value" => number_Format($userinfo["rounds"], 0)
				);

				$options = array("0" => "No", "1" => "Now - Generate a random password");
				if ($this->IsRecoveryAllowed(false))  $options["2"] = "Next Login - User must use account recovery to set a password";
				$contentopts["fields"][] = array(
					"title" => "Reset Password?",
					"type" => "select",
					"name" => "reset_password",
					"options" => $options,
					"select" => BB_GetValue("reset_password", "0")
				);

				foreach ($g_sso_login_modules as $key => $info)
				{
					if ($sso_settings["sso_login"]["modules"][$key]["_a"])
					{
						$module = "sso_login_module_" . $key;
						$instance = new $module;
						$instance->AddEditUserFields($contentopts, $userinfo);
					}
				}

				BB_GeneratePage(BB_Translate("Edit %s User", $this->DisplayName()), $sso_menuopts, $contentopts);
			}
			else if ($sso_settings["sso_login"]["enabled"] && $_REQUEST["action2"] == "createuser" && $this->CanActivateUser())
			{
				// Initialize active modules.
				$this->activemodules = array();
				foreach ($g_sso_login_modules as $key => $info)
				{
					if ($sso_settings["sso_login"]["modules"][$key]["_a"])
					{
						$module = "sso_login_module_" . $key;
						$this->activemodules[$key] = new $module;
					}
				}

				if (isset($_REQUEST["set_password"]))
				{
					$messages = $this->SignupUpdateCheck(false, false, false, true);
					if (count($messages["errors"]))  BB_SetPageMessage("error", implode("  ", array_merge($messages["errors"], $messages["warnings"])));
					else
					{
						// Create the account.
						$username = BB_GetValue("username", "");
						$email = BB_GetValue("email", "");
						$verified = true;
						if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")
						{
							$result = SMTP::MakeValidEmailAddress($email);
							$email = $result["email"];
						}

						$userinfo = array();
						$phrase = "";
						for ($x = 0; $x < 4; $x++)  $phrase .= " " . SSO_GetRandomWord();
						$phrase = preg_replace('/\s+/', " ", trim($phrase));

						$salt = $sso_rng->GenerateString();
						$data = $username . ":" . $email . ":" . $salt . ":" . $phrase;
						$userinfo["extra"] = $sso_rng->GenerateString();
						if ($_REQUEST["set_password"] == 1)
						{
							$passwordinfo = self::HashPasswordInfo($data, $sso_settings["sso_login"]["password_mode"], $sso_settings["sso_login"]["password_minrounds"]);
							if (!$passwordinfo["success"])  BB_SetPageMessage("error", "Unexpected cryptography error.");
							else
							{
								$userinfo["salt"] = $salt;
								$userinfo["rounds"] = (int)$passwordinfo["rounds"];
								$userinfo["password"] = bin2hex($passwordinfo["hash"]);

								BB_SetPageMessage("info", BB_Translate("Initial password has been set to '%s'.", $phrase));
							}
						}
						else if ($this->IsRecoveryAllowed(false) && $_REQUEST["set_password"] == 2)
						{
							$userinfo["salt"] = "";
							$userinfo["rounds"] = 0;
							$userinfo["password"] = "";
						}
						else  BB_SetPageMessage("error", "Invalid Set Password option.");

						$userinfo["two_factor_key"] = $_REQUEST["two_factor_key"];
						$userinfo["two_factor_method"] = (isset($_REQUEST["two_factor_method"]) ? $_REQUEST["two_factor_method"] : "");

						if (BB_GetPageMessageType() != "error")
						{
							foreach ($this->activemodules as &$instance)
							{
								$instance->SignupAddInfo($userinfo, true);
							}
							$userinfo2 = SSO_EncryptDBData($userinfo);

							try
							{
								if ($sso_settings["sso_login"]["install_type"] == "email_username")
								{
									$sso_db->Query("INSERT", array($sso_db_sso_login_users, array(
										"username" => $username,
										"email" => $email,
										"verified" => (int)$verified,
										"created" => CSDB::ConvertToDBTime(time()),
										"info" => $userinfo2,
									), "AUTO INCREMENT" => "id"));
								}
								else if ($sso_settings["sso_login"]["install_type"] == "email")
								{
									$sso_db->Query("INSERT", array($sso_db_sso_login_users, array(
										"email" => $email,
										"verified" => (int)$verified,
										"created" => CSDB::ConvertToDBTime(time()),
										"info" => $userinfo2,
									), "AUTO INCREMENT" => "id"));
								}
								else if ($sso_settings["sso_login"]["install_type"] == "username")
								{
									$sso_db->Query("INSERT", array($sso_db_sso_login_users, array(
										"username" => $username,
										"created" => CSDB::ConvertToDBTime(time()),
										"info" => $userinfo2,
									), "AUTO INCREMENT" => "id"));
								}
								else  BB_SetPageMessage("error", "Fatal error:  Login system is broken.");

								if (BB_GetPageMessageType() != "error")
								{
									$userid = $sso_db->GetInsertID();

									$userrow = $sso_db->GetRow("SELECT", array(
										"*",
										"FROM" => "?",
										"WHERE" => "id = ?",
									), $sso_db_sso_login_users, $userid);
								}
							}
							catch (Exception $e)
							{
								BB_SetPageMessage("error", "Database query error.");
							}

							if (BB_GetPageMessageType() != "error")
							{
								foreach ($this->activemodules as &$instance)
								{
									$instance->SignupDone($userid, true);
								}

								// Activate the user.
								if (isset($_REQUEST["activate"]) && $_REQUEST["activate"] == "yes")
								{
									$mapinfo = array();
									if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")  $mapinfo[$sso_settings["sso_login"]["map_email"]] = $userrow->email;
									if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username")  $mapinfo[$sso_settings["sso_login"]["map_username"]] = $userrow->username;

									foreach ($this->activemodules as &$instance)
									{
										$instance->LoginAddMap($mapinfo, $userrow, $userinfo, true);
									}

									SSO_ActivateUser($userrow->id, $userinfo["extra"], $mapinfo, CSDB::ConvertFromDBTime($userrow->created), false, false);
								}

								if (BB_GetPageMessageType() == "info")  SSO_ConfigRedirect("edituser", array("id" => $userid), "info", $_REQUEST["bb_msg"] . "  Successfully created the user.");
								else  SSO_ConfigRedirect("edituser", array("id" => $userid), "success", "Successfully created the user.");
							}
						}
					}
				}

				$_REQUEST["two_factor_key"] = BB_GetValue("two_factor_key", self::GenerateOTPKey(10));

				$contentopts = array(
					"desc" => BB_Translate("Create a new user in the %s provider.", $this->DisplayName()),
					"nonce" => "action",
					"hidden" => array(
						"action" => "config",
						"provider" => "sso_login",
						"action2" => "createuser",
						"two_factor_key" => $_REQUEST["two_factor_key"]
					),
					"fields" => array(
					),
					"submit" => "Create",
					"focus" => true
				);

				if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")
				{
					$contentopts["fields"][] = array(
						"title" => "E-mail Address",
						"type" => "text",
						"name" => "email",
						"value" => BB_GetValue("email", ""),
						"desc" => "The e-mail address of the new user.  Must be valid and not already in use."
					);
				}
				if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username")
				{
					$contentopts["fields"][] = array(
						"title" => "Username",
						"type" => "text",
						"name" => "username",
						"value" => BB_GetValue("username", ""),
						"desc" => "The username of the new user.  Must be valid and not already in use."
					);
				}

				$options = array("1" => "Now - Generate a random password upon account creation");
				if ($this->IsRecoveryAllowed(false))  $options["2"] = "Next Login - User must use account recovery to set a password";
				$contentopts["fields"][] = array(
					"title" => "Set Password",
					"type" => "select",
					"name" => "set_password",
					"options" => $options,
					"select" => BB_GetValue("set_password", "1"),
					"desc" => "Sets an account password now or later."
				);

				// Two-factor authentication dropdown.
				$fieldmap = array();
				$options = array();
				foreach ($this->activemodules as $key => &$instance)
				{
					$name = $instance->GetTwoFactorName();
					if ($name !== false)  $options[$key] = $name;
				}
				if (!$sso_settings["sso_login"]["require_two_factor"] && count($options))  $options = array_merge(array("" => "None"), $options);
				if (count($options))
				{
					$fields = array(
						array(
							"title" => "Two-Factor Authentication Method",
							"type" => "select",
							"name" => "two_factor_method",
							"options" => $options,
							"select" => BB_GetValue("two_factor_method", ""),
							"desc" => "Sets the two-factor authentication method."
						)
					);

					$order = $sso_settings["sso_login"]["two_factor_order"];
					SSO_AddSortedOutput($fieldmap, $order, "two_factor", $fields);
				}

				// Other fields.
				foreach ($g_sso_login_modules as $key => $info)
				{
					if ($sso_settings["sso_login"]["modules"][$key]["_a"])
					{
						$module = "sso_login_module_" . $key;
						$instance = new $module;
						$fields = $instance->GenerateSignup(true);
						if (isset($fields) && is_array($fields))
						{
							$order = (isset($sso_settings["sso_login"]["modules"][$key]["_s"]) ? $sso_settings["sso_login"]["modules"][$key]["_s"] : $instance->DefaultOrder());
							SSO_AddSortedOutput($fieldmap, $order, $key, $fields);
						}
					}
				}

				ksort($fieldmap);
				foreach ($fieldmap as $fields)
				{
					foreach ($fields as $fields2)  $contentopts["fields"] = array_merge($contentopts["fields"], $fields2);
				}

				$contentopts["fields"][] = array(
					"title" => "Activate User",
					"type" => "checkbox",
					"name" => "activate",
					"value" => "yes",
					"check" => BB_GetValue("activate", "yes"),
					"display" => "Activate the user upon successful account creation"
				);

				BB_GeneratePage("Create User", $sso_menuopts, $contentopts);
			}
			else if ($sso_site_admin && $sso_settings["sso_login"]["enabled"] && $_REQUEST["action2"] == "module" && isset($_REQUEST["module"]) && isset($sso_settings["sso_login"]["modules"][$_REQUEST["module"]]) && $sso_settings["sso_login"]["modules"][$_REQUEST["module"]]["_a"])
			{
				$module = "sso_login_module_" . $_REQUEST["module"];
				$instance = new $module;
				$instance->CustomConfig();
			}
		}

		public function IsEnabled()
		{
			global $sso_settings, $g_sso_login_modules;

			if (!$sso_settings["sso_login"]["enabled"])  return false;

			if (($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username") && $sso_settings["sso_login"]["map_username"] == "")  return false;
			if (($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email") && $sso_settings["sso_login"]["map_email"] == "")  return false;

			if (!SSO_IsIPAllowed($sso_settings["sso_login"]["iprestrict"]) || SSO_IsSpammer($sso_settings["sso_login"]["iprestrict"]))  return false;

			foreach ($g_sso_login_modules as $key => $info)
			{
				if ($sso_settings["sso_login"]["modules"][$key]["_a"])
				{
					$module = "sso_login_module_" . $key;
					$instance = new $module;
					if (!$instance->IsAllowed())  return false;
				}
			}

			return true;
		}

		public function GetProtectedFields()
		{
			global $sso_settings, $g_sso_login_modules;

			$result = array();
			if (($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username") && $sso_settings["sso_login"]["map_username"] != "")  $result[$sso_settings["sso_login"]["map_username"]] = true;
			if (($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email") && $sso_settings["sso_login"]["map_email"] != "")  $result[$sso_settings["sso_login"]["map_email"]] = true;

			foreach ($g_sso_login_modules as $key => $info)
			{
				if ($sso_settings["sso_login"]["modules"][$key]["_a"])
				{
					$module = "sso_login_module_" . $key;
					$instance = new $module;
					$instance->AddProtectedFields($result);
				}
			}

			return $result;
		}

		public function AddIPCacheInfo()
		{
			global $sso_settings, $g_sso_login_modules;

			foreach ($g_sso_login_modules as $key => $info)
			{
				if ($sso_settings["sso_login"]["modules"][$key]["_a"])
				{
					$module = "sso_login_module_" . $key;
					$instance = new $module;
					$instance->AddIPCacheInfo($this->DisplayName());
				}
			}
		}

		public function FindUsers()
		{
			global $contentopts, $opts, $specialopts, $sso_settings, $sso_db, $sso_db_users;

			$sso_db_sso_login_users = SSO_DB_PREFIX . "p_sso_login_users";

			$sqlfrom = array("? AS slu LEFT OUTER JOIN ? AS u ON (u.provider_name = 'sso_login' AND slu.id = u.provider_id)");
			$sqlwhere = array();
			$sqlvars = array($sso_db_sso_login_users, $sso_db_users);

			if (isset($specialopts["id"]))
			{
				$sqlwhere[] = "slu.id = ?";
				$sqlvars[] = $specialopts["id"];
			}

			if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")
			{
				$sqlwhere2 = array();
				foreach ($opts as $opt)
				{
					$sqlwhere2[] = "slu.email LIKE ?";
					$sqlvars[] = "%" . $opt . "%";
				}

				if (count($sqlwhere2))  $sqlwhere[] = "(" . implode(" AND ", $sqlwhere2) . ")";
			}

			if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username")
			{
				$sqlwhere2 = array();
				foreach ($opts as $opt)
				{
					$sqlwhere2[] = "slu.username LIKE ?";
					$sqlvars[] = "%" . $opt . "%";
				}

				if (count($sqlwhere2))  $sqlwhere[] = "(" . implode(" AND ", $sqlwhere2) . ")";
			}

			$rows = array();
			if (count($sqlwhere) || !count($specialopts))
			{
				$sqlopts = array(
					"slu.*",
					"FROM" => implode(", ", $sqlfrom),
					"WHERE" => "u.provider_name IS NULL",
					"LIMIT" => "300"
				);
				if (count($sqlwhere))  $sqlopts["WHERE"] .= " AND (" . implode(" OR ", $sqlwhere) . ")";
				else  $sqlopts["ORDER BY"] = "slu.id DESC";
				$result = $sso_db->Query("SELECT", $sqlopts, $sqlvars);
				while ($row = $result->NextRow())
				{
					if ($sso_settings["sso_login"]["install_type"] == "email_username")  $rows[] = array(htmlspecialchars($row->id), htmlspecialchars($row->email), htmlspecialchars($row->username), SSO_CreateConfigLink(BB_Translate("Edit"), "edituser", array("id" => $row->id)));
					else if ($sso_settings["sso_login"]["install_type"] == "email")  $rows[] = array(htmlspecialchars($row->id), htmlspecialchars($row->email), SSO_CreateConfigLink(BB_Translate("Edit"), "edituser", array("id" => $row->id)));
					else if ($sso_settings["sso_login"]["install_type"] == "username")  $rows[] = array(htmlspecialchars($row->id), htmlspecialchars($row->username), SSO_CreateConfigLink(BB_Translate("Edit"), "edituser", array("id" => $row->id)));
				}
			}

			if (count($rows))
			{
				if ($sso_settings["sso_login"]["install_type"] == "email_username")  $cols = array("ID", "E-mail Address", "Username", "Options");
				else if ($sso_settings["sso_login"]["install_type"] == "email")  $cols = array("ID", "E-mail Address", "Options");
				else if ($sso_settings["sso_login"]["install_type"] == "username")  $cols = array("ID", "Username", "Options");

				$contentopts["fields"][] = array(
					"title" => BB_Translate("%s Results (Not Activated)", $this->DisplayName()),
					"type" => "table",
					"cols" => $cols,
					"rows" => $rows
				);
			}
		}

		public function GetEditUserLinks($id)
		{
			global $sso_settings;

			if (!$sso_settings["sso_login"]["enabled"])  return array();

			return array(SSO_CreateConfigLink(BB_Translate("Edit %s Info", $this->DisplayName()), "edituser", array("id" => $id)));
		}

		public function GenerateSelector()
		{
			global $sso_target_url;
?>
<div class="sso_selector">
	<a class="sso_login" href="<?php echo htmlspecialchars($sso_target_url); ?>"><?php echo htmlspecialchars($this->DisplayName()); ?></a>
</div>
<?php
		}

		public static function CalculateOptimalHashRounds($mode, $mintime)
		{
			$mintime /= 1000;
			$minrounds = 8;

			do
			{
				$minrounds *= 2;

				$ts = microtime(true);
				$result = self::HashPasswordInfo("correct-horse-battery-staple", $mode, $minrounds, 0);
				$ts = microtime(true) - $ts;
				if (!$result["success"])  return false;
			} while ($ts < $mintime);

			// Blowfish::Hash() does as many rounds as it can in the allotted time,
			// which will generally be more than a reasonable $minrounds setting.
			if ($mode === "blowfish")  $minrounds /= 2;

			return $minrounds;
		}

		public static function HashPasswordInfo($data, $mode, $minrounds, $mintime = false)
		{
			global $sso_settings;

			if ($mode == "password_hash_bcrypt" && function_exists("password_hash"))
			{
				// Calculate bits.
				$bits = 0;
				$x = 1;
				while ($x < $minrounds)
				{
					$bits++;
					$x *= 2;
				}

				$result = @password_hash($data, PASSWORD_BCRYPT, array("cost" => $bits));
				if ($result === false)  $result = array("success" => false, "error" => "Unable to hash the data.");
				else  $result = array("success" => true, "rounds" => $x, "hash" => $result);
			}
			else
			{
				$result = Blowfish::Hash($data, $minrounds, ($mintime !== false ? $mintime : $sso_settings["sso_login"]["password_mintime"]));
			}

			return $result;
		}

		public static function VerifyPasswordInfo($data, $hash, $numrounds)
		{
			global $sso_settings;

			if ($sso_settings["sso_login"]["password_mode"] == "password_hash_bcrypt" && function_exists("password_verify"))
			{
				$result = @password_verify($data, pack("H*", $hash));
				if ($result === false)  return false;
			}
			else
			{
				$result = Blowfish::Hash($data, $numrounds, 0);
				if (!$result["success"] || $hash !== bin2hex($result["hash"]))  return false;
			}

			return true;
		}

		private function OutputJS($ajaxurl = false)
		{
			global $sso_target_url;

?>
<script type="text/javascript">
SSO_Vars = {
	'checking' : '<?php echo BB_JSSafe(BB_Translate("Checking...")); ?>',
<?php
			if ($ajaxurl !== false)
			{
?>
	'ajaxurl' : '<?php echo BB_JSSafe($ajaxurl); ?>',
<?php
			}
?>
	'showpassword' : '<?php echo htmlspecialchars(BB_JSSafe(BB_Translate("Show password"))); ?>'
};
</script>
<script type="text/javascript" src="<?php echo htmlspecialchars(SSO_ROOT_URL . "/" . SSO_PROVIDER_PATH . "/sso_login/sso_login.js"); ?>"></script>
<?php
		}

		private function SignupUpdateCheck($ajax, $userrow, $userinfo, $admin)
		{
			global $sso_settings, $sso_db;

			$sso_db_sso_login_users = SSO_DB_PREFIX . "p_sso_login_users";

			$result = array("errors" => array(), "warnings" => array(), "success" => "");
			$field = ($admin ? BB_GetValue("email", false) : SSO_FrontendFieldValue($userrow === false ? "email" : "update_email"));
			if ((!$ajax || $field !== false) && ($userrow === false || $sso_settings["sso_login"]["change_email"]) && ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email"))
			{
				if ($field === false || trim($field) == "")  $result["errors"][] = BB_Translate("E-mail Address field is empty.");
				else
				{
					define("CS_TRANSLATE_FUNC", "BB_Translate");
					require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/smtp.php";
					$email = SMTP::MakeValidEmailAddress($field);
					if (!$email["success"])  $result["errors"][] = BB_Translate("Invalid e-mail address.  %s", $email["error"]);
					else
					{
						if ($email["email"] != trim($field))  $result["warnings"][] = BB_Translate("Invalid e-mail address.  Perhaps you meant '%s' instead?", $email["email"]);

						$domain = strtolower(substr($email["email"], strrpos($email["email"], "@") + 1));
						$y = strlen($domain);
						$baddomains = explode("\n", strtolower($sso_settings["sso_login"]["email_bad_domains"]));
						foreach ($baddomains as $baddomain)
						{
							$baddomain = trim($baddomain);
							if ($baddomain != "")
							{
								$y2 = strlen($baddomain);
								if ($domain == $baddomain || ($y < $y2 && substr($domain, $y - $y2 - 1, 1) == "." && substr($domain, $y - $y2) == $baddomain))
								{
									$result["errors"][] = BB_Translate("E-mail address is in a blacklisted domain.");

									break;
								}
							}
						}

						try
						{
							if (!count($result["errors"]) && ($userrow === false || $userrow->email != $email["email"]) && $sso_db->GetOne("SELECT", array(array("id"), "FROM" => "?", "WHERE" => "email = ?", "LIMIT" => "1"), $sso_db_sso_login_users, $email["email"]) !== false)
							{
								$result["errors"][] = BB_Translate("E-mail address is already in use.");
							}
						}
						catch (Exception $e)
						{
							$result["errors"][] = BB_Translate("Database query error.");
						}

						$result["success"] = BB_Translate("E-mail address looks okay.");
					}
				}
			}
			if ($ajax && count($result["errors"]))  return $result;

			$field = ($admin ? BB_GetValue("username", false) : SSO_FrontendFieldValue($userrow === false ? "username" : "update_username"));
			if ((!$ajax || $field !== false) && ($userrow === false || $sso_settings["sso_login"]["change_username"]) && ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username"))
			{
				if ($field === false || trim($field) == "")  $result["errors"][] = BB_Translate("Username field is empty.");
				else
				{
					if (UTF8::strlen($field) < $sso_settings["sso_login"]["username_minlen"])
					{
						$result["errors"][] = BB_Translate("Username must be at least %d characters.", $sso_settings["sso_login"]["username_minlen"]);
					}

					$blacklist = explode("\n", str_replace("\r", "\n", $sso_settings["sso_login"]["username_blacklist"]));
					foreach ($blacklist as $word)
					{
						$word = trim($word);
						if ($word != "" && stripos($field, $word) !== false)
						{
							$result["errors"][] = BB_Translate("Username contains a blocked word.");
							break;
						}
					}

					try
					{
						if (!count($result["errors"]) && ($userrow === false || $userrow->username != trim($field)) && $sso_db->GetOne("SELECT", array(array("id"), "FROM" => "?", "WHERE" => "username = ?", "LIMIT" => "1"), $sso_db_sso_login_users, trim($field)) !== false)
						{
							$result["errors"][] = BB_Translate("Username is already in use.");
						}
					}
					catch (Exception $e)
					{
						$result["errors"][] = BB_Translate("Database query error.");
					}

					$result["success"] = BB_Translate("Username is available.");
				}
			}
			if ($ajax && count($result["errors"]))  return $result;

			$field = ($admin ? false : SSO_FrontendFieldValue($userrow === false ? "createpass" : "update_pass"));
			if ((!$ajax || $field !== false) && !$admin)
			{
				if ($field === false || trim($field) == "")
				{
					if ($userrow === false)  $result["errors"][] = BB_Translate("Password field is empty.");
				}
				else
				{
					if (UTF8::strlen($field) < $sso_settings["sso_login"]["password_minlen"])
					{
						$result["errors"][] = BB_Translate("Password must be at least %d characters.", $sso_settings["sso_login"]["password_minlen"]);
					}

					$result["success"] = BB_Translate("Password looks okay.");
				}
			}
			if ($ajax && count($result["errors"]))  return $result;

			$field = ($admin ? BB_GetValue("two_factor_method", false) : SSO_FrontendFieldValue($userrow === false ? "two_factor_method" : "update_two_factor_method"));
			if (!$ajax && $sso_settings["sso_login"]["require_two_factor"])
			{
				if ($field === false || trim($field) == "")  $result["errors"][] = BB_Translate("A two-factor authentication method is required.");
			}

			foreach ($this->activemodules as &$instance)
			{
				if ($userinfo === false)  $instance->SignupCheck($result, $ajax, $admin);
				else  $instance->UpdateInfoCheck($result, $userinfo, $ajax);
				if ($ajax && count($result["errors"]))  return $result;
			}

			return $result;
		}

		private function IsRecoveryAllowed($allowoptional = true)
		{
			global $sso_settings;

			if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")
			{
				if ($sso_settings["sso_login"]["email_recover_msg"] != "")  return true;
			}

			foreach ($this->activemodules as &$instance)
			{
				if ($instance->IsRecoveryAllowed($allowoptional))  return true;
			}

			return false;
		}

		private function DisplayMessages($messages, $header = true)
		{
			if ($messages !== false)
			{
?>
	<div class="sso_main_messages_wrap">
<?php
				if ($header)
				{
?>
		<div class="sso_main_messages_header"><?php echo htmlspecialchars(BB_Translate(count($messages["errors"]) + count($messages["warnings"]) == 1 ? "Please correct the following problem:" : "Please correct the following problems:")); ?></div>
<?php
				}
?>
		<div class="sso_main_messages">
<?php
				foreach ($messages["errors"] as $message)
				{
?>
			<div class="sso_main_messageerror"><?php echo htmlspecialchars($message); ?></div>
<?php
				}

				foreach ($messages["warnings"] as $message)
				{
?>
			<div class="sso_main_messagewarning"><?php echo htmlspecialchars($message); ?></div>
<?php
				}

				if (!count($messages["errors"]) && !count($messages["warnings"]))
				{
?>
			<div class="sso_main_messageokay"><?php echo htmlspecialchars($messages["success"]); ?></div>
<?php
				}
?>
		</div>
	</div>
<?php
			}
		}

		private function SendVerificationEmail($userid, $userinfo, &$messages, $username, $email)
		{
			global $sso_rng, $sso_session_info, $sso_target_url, $sso_settings;

			$sso_session_info["sso_login_verify"] = array(
				"id" => $userid,
				"v" => $sso_rng->GenerateString()
			);

			if (!SSO_SaveSessionInfo())  $messages["errors"][] = BB_Translate("Unable to save session information.");
			else
			{
				$fromaddr = BB_PostTranslate($sso_settings["sso_login"]["email_verify_from"] != "" ? $sso_settings["sso_login"]["email_verify_from"] : SSO_SMTP_FROM);
				$subject = BB_Translate($sso_settings["sso_login"]["email_verify_subject"]);
				$verifyurl = BB_GetRequestHost() . $sso_target_url . ($sso_settings["sso_login"]["email_session"] != "none" ? "&sso_id=" . urlencode($_REQUEST["sso_id"]) : "") . "&sso_login_action=verify&sso_v=" . urlencode($sso_session_info["sso_login_verify"]["v"]);
				$htmlmsg = str_ireplace(array("@USERNAME@", "@EMAIL@", "@VERIFY@"), array(htmlspecialchars($username), htmlspecialchars($email), htmlspecialchars($verifyurl)), BB_PostTranslate($sso_settings["sso_login"]["email_verify_msg"]));
				$textmsg = str_ireplace(array("@USERNAME@", "@EMAIL@", "@VERIFY@"), array($username, $email, $verifyurl), BB_PostTranslate($sso_settings["sso_login"]["email_verify_msg_text"]));
				foreach ($this->activemodules as &$instance)
				{
					$instance->ModifyEmail($userinfo, $htmlmsg, $textmsg);
				}

				$result = SSO_SendEmail($fromaddr, $email, $subject, $htmlmsg, $textmsg);
				if (!$result["success"])  $messages["errors"][] = BB_Translate("Unable to send verification e-mail.  %s", $result["error"]);
				else  $messages["warnings"][] = BB_Translate("Account must be verified before it can be used.  Check your e-mail.");
			}
		}

		public function ProcessFrontend()
		{
			global $g_sso_login_modules, $sso_settings, $sso_rng, $sso_header, $sso_footer, $sso_target_url, $sso_db, $sso_ipaddr_info, $sso_session_info, $sso_providers;

			if (!isset($sso_ipaddr_info["sso_login_modules"]))  $sso_ipaddr_info["sso_login_modules"] = array();

			// Initialize active modules.
			$this->activemodules = array();
			foreach ($g_sso_login_modules as $key => $info)
			{
				if ($sso_settings["sso_login"]["modules"][$key]["_a"])
				{
					$module = "sso_login_module_" . $key;
					$this->activemodules[$key] = new $module;
				}
			}

			$sso_db_sso_login_users = SSO_DB_PREFIX . "p_sso_login_users";

			if (isset($_REQUEST["sso_login_action"]) && $_REQUEST["sso_login_action"] == "module" && isset($_REQUEST["sso_login_module"]) && isset($this->activemodules[$_REQUEST["sso_login_module"]]))
			{
				$this->activemodules[$_REQUEST["sso_login_module"]]->CustomFrontend();
			}
			else if (isset($_REQUEST["sso_login_action"]) && $_REQUEST["sso_login_action"] == "verify" && $sso_settings["sso_login"]["open_reg"])
			{
				$messages = array("errors" => array(), "warnings" => array(), "success" => "");
				foreach ($this->activemodules as &$instance)
				{
					$instance->VerifyCheck($messages);
				}

				if (!count($messages["errors"]))
				{
					if (!isset($_REQUEST["sso_v"]) || !isset($sso_session_info["sso_login_verify"]))  $messages["errors"][] = BB_Translate("Invalid URL.  Verification missing.");
					else if (trim($_REQUEST["sso_v"]) !== $sso_session_info["sso_login_verify"]["v"])  $messages["errors"][] = BB_Translate("Invalid verification string specified.");
					else
					{
						try
						{
							$sso_db->Query("UPDATE", array($sso_db_sso_login_users, array(
								"verified" => 1,
							), "WHERE" => "id = ?"), $sso_session_info["sso_login_verify"]["id"]);
						}
						catch (Exception $e)
						{
							$messages["errors"][] = BB_Translate("Verification failed.  Database query error.");
						}

						if (!count($messages["errors"]))
						{
							header("Location: " . BB_GetRequestHost() . $sso_target_url . "&sso_msg=verified");
							exit();
						}
					}
				}

				echo $sso_header;

				SSO_OutputHeartbeat();
?>
<div class="sso_main_wrap sso_login">
<div class="sso_main_wrap_inner">
<?php
				$this->DisplayMessages($messages, false);
?>
	<div class="sso_login_signin"><a href="<?php echo htmlspecialchars($sso_target_url); ?>"><?php echo htmlspecialchars(BB_Translate("Sign in")); ?></a></div>
</div>
</div>
<?php
				echo $sso_footer;
			}
			else if (isset($_REQUEST["sso_login_action"]) && $_REQUEST["sso_login_action"] == "signup_check" && $sso_settings["sso_login"]["open_reg"])
			{
				$result = $this->SignupUpdateCheck(true, false, false, false);
				foreach ($result["errors"] as $error)  echo "<div class=\"sso_main_formerror\">" . htmlspecialchars($error) . "</div>";
				foreach ($result["warnings"] as $warning)  echo "<div class=\"sso_main_formwarning\">" . htmlspecialchars($warning) . "</div>";
				if (!count($result["errors"]) && !count($result["warnings"]))
				{
					if ($result["success"] != "")  echo "<div class=\"sso_main_formokay\">" . htmlspecialchars($result["success"]) . "</div>";
					else if (isset($result["htmlsuccess"]) && $result["htmlsuccess"] != "")  echo "<div class=\"sso_main_formokay\">" . $result["htmlsuccess"] . "</div>";
				}
			}
			else if (isset($_REQUEST["sso_login_action"]) && $_REQUEST["sso_login_action"] == "signup" && $sso_settings["sso_login"]["open_reg"])
			{
				if (SSO_FrontendFieldValue("submit") === false)  $messages = false;
				else
				{
					$messages = $this->SignupUpdateCheck(false, false, false, false);
					if (!count($messages["errors"]))
					{
						// Create the account.
						$username = SSO_FrontendFieldValue("username", "");
						$email = SSO_FrontendFieldValue("email", "");
						$verified = true;
						if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")
						{
							$result = SMTP::MakeValidEmailAddress($email);
							$email = $result["email"];
							$verified = ($sso_settings["sso_login"]["email_verify_subject"] == "" || $sso_settings["sso_login"]["email_verify_msg"] == "");
						}
						$salt = $sso_rng->GenerateString();
						$data = $username . ":" . $email . ":" . $salt . ":" . SSO_FrontendFieldValue("createpass");
						$passwordinfo = self::HashPasswordInfo($data, $sso_settings["sso_login"]["password_mode"], $sso_settings["sso_login"]["password_minrounds"]);
						if (!$passwordinfo["success"])  $messages["errors"][] = BB_Translate("Unexpected cryptography error.");
						else
						{
							$userinfo = array();
							$userinfo["extra"] = $sso_rng->GenerateString();
							$userinfo["two_factor_key"] = $sso_session_info["sso_login_two_factor_key"];
							$userinfo["two_factor_method"] = SSO_FrontendFieldValue("two_factor_method", "");
							foreach ($this->activemodules as &$instance)
							{
								$instance->SignupAddInfo($userinfo, false);
							}
							$userinfo["salt"] = $salt;
							$userinfo["rounds"] = (int)$passwordinfo["rounds"];
							$userinfo["password"] = bin2hex($passwordinfo["hash"]);
							$userinfo2 = SSO_EncryptDBData($userinfo);

							try
							{
								if ($sso_settings["sso_login"]["install_type"] == "email_username")
								{
									$sso_db->Query("INSERT", array($sso_db_sso_login_users, array(
										"username" => $username,
										"email" => $email,
										"verified" => (int)$verified,
										"created" => CSDB::ConvertToDBTime(time()),
										"info" => $userinfo2,
									), "AUTO INCREMENT" => "id"));
								}
								else if ($sso_settings["sso_login"]["install_type"] == "email")
								{
									$sso_db->Query("INSERT", array($sso_db_sso_login_users, array(
										"email" => $email,
										"verified" => (int)$verified,
										"created" => CSDB::ConvertToDBTime(time()),
										"info" => $userinfo2,
									), "AUTO INCREMENT" => "id"));
								}
								else if ($sso_settings["sso_login"]["install_type"] == "username")
								{
									$sso_db->Query("INSERT", array($sso_db_sso_login_users, array(
										"username" => $username,
										"created" => CSDB::ConvertToDBTime(time()),
										"info" => $userinfo2,
									), "AUTO INCREMENT" => "id"));
								}
								else  $messages["errors"][] = BB_Translate("Fatal error:  Login system is broken.");

								// Send verification e-mail.
								if (!count($messages["errors"]))  $userid = $sso_db->GetInsertID();
								if (!count($messages["errors"]) && !$verified)  $this->SendVerificationEmail($userid, $userinfo, $messages, $username, $email);
							}
							catch (Exception $e)
							{
								$messages["errors"][] = BB_Translate("Database query error.");
							}

							if (!count($messages["errors"]))
							{
								foreach ($this->activemodules as &$instance)
								{
									$instance->SignupDone($userid, false);
								}

								header("Location: " . BB_GetRequestHost() . $sso_target_url . "&sso_msg=" . ($verified ? "verified" : "verify"));
								exit();
							}
						}
					}
				}

				echo $sso_header;

				SSO_OutputHeartbeat();
				$this->OutputJS($sso_target_url . "&sso_login_action=signup_check&sso_ajax=1");
?>
<div class="sso_main_wrap sso_login">
<div class="sso_main_wrap_inner">
<?php
				$this->DisplayMessages($messages);
?>
	<div class="sso_login_signin"><a href="<?php echo htmlspecialchars($sso_target_url); ?>"><?php echo htmlspecialchars(BB_Translate("Sign in")); ?></a></div>
	<div class="sso_main_form_wrap sso_login_signup_form">
		<div class="sso_main_form_header"><?php echo htmlspecialchars(BB_Translate("Sign up")); ?></div>
		<form class="sso_main_form" name="sso_login_form" method="post" accept-charset="UTF-8" enctype="multipart/form-data" action="<?php echo htmlspecialchars($sso_target_url . "&sso_login_action=signup"); ?>" autocomplete="off">
<?php
				if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")
				{
?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Your E-mail Address")); ?></div>
				<div class="sso_main_formdata"><input class="sso_main_text sso_login_changehook" type="text" name="<?php echo SSO_FrontendField("email"); ?>" value="<?php echo htmlspecialchars(SSO_FrontendFieldValue("email", "")); ?>" /></div>
			</div>
<?php
				}
				if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username")
				{
?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Choose Username")); ?></div>
				<div class="sso_main_formdata"><input class="sso_main_text sso_login_changehook" type="text" name="<?php echo SSO_FrontendField("username"); ?>" value="<?php echo htmlspecialchars(SSO_FrontendFieldValue("username", "")); ?>" /></div>
			</div>
<?php
				}
?>
			<script type="text/javascript">
			jQuery('input.sso_main_text:first').focus();
			</script>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Choose Password")); ?></div>
				<div class="sso_main_formdata"><input class="sso_main_text sso_login_changehook" type="password" name="<?php echo SSO_FrontendField("createpass"); ?>" value="<?php echo htmlspecialchars(SSO_FrontendFieldValue("createpass", "")); ?>" /></div>
			</div>
<?php
				$outputmap = array();

				// Two-factor authentication dropdown.
				$outputmap2 = array();
				$method = SSO_FrontendFieldValue("two_factor_method", "");
				foreach ($this->activemodules as $key => &$instance)
				{
					$name = $instance->GetTwoFactorName();
					if ($name !== false)
					{
						$order = (isset($sso_settings["sso_login"]["modules"][$key]["_s"]) ? $sso_settings["sso_login"]["modules"][$key]["_s"] : $instance->DefaultOrder());
						SSO_AddSortedOutput($outputmap2, $order, $key, "<option value=\"" . htmlspecialchars($key) . "\"" . ($method == $key ? " selected" : "") . ">" . htmlspecialchars($name) . "</option>");
					}
				}
				if (!$sso_settings["sso_login"]["require_two_factor"] && count($outputmap2))  SSO_AddSortedOutput($outputmap2, 0, "", "<option value=\"\"" . ($method == "" ? " selected" : "") . ">" . htmlspecialchars(BB_Translate("None")) . "</option>");
				if (count($outputmap2))
				{
					if (!isset($sso_session_info["sso_login_two_factor_key"]))
					{
						$sso_session_info["sso_login_two_factor_key"] = self::GenerateOTPKey(10);
						SSO_SaveSessionInfo();
					}

					ob_start();
?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Choose Two-Factor Authentication Method")); ?></div>
				<div class="sso_main_formdata"><select class="sso_main_dropdown sso_login_changehook_two_factor" name="<?php echo SSO_FrontendField("two_factor_method"); ?>">
					<?php SSO_DisplaySortedOutput($outputmap2); ?>
				</select></div>
				<div class="sso_main_formdesc"><?php echo htmlspecialchars(BB_Translate($sso_settings["sso_login"]["require_two_factor"] ? "Required.  Two-factor authentication vastly improves the security of your account." : "Optional.  Two-factor authentication vastly improves the security of your account.")); ?></div>
			</div>
<?php
					$order = $sso_settings["sso_login"]["two_factor_order"];
					SSO_AddSortedOutput($outputmap, $order, "two_factor", ob_get_contents());
					ob_end_clean();
				}

				// Add active module output.
				foreach ($this->activemodules as $key => &$instance)
				{
					ob_start();
					$instance->GenerateSignup(false);
					$order = (isset($sso_settings["sso_login"]["modules"][$key]["_s"]) ? $sso_settings["sso_login"]["modules"][$key]["_s"] : $instance->DefaultOrder());
					SSO_AddSortedOutput($outputmap, $order, $key, ob_get_contents());
					ob_end_clean();
				}

				SSO_DisplaySortedOutput($outputmap);
?>
			<div class="sso_main_formsubmit">
				<input type="submit" name="<?php echo SSO_FrontendField("submit"); ?>" value="<?php echo htmlspecialchars(BB_Translate("Sign up")); ?>" />
			</div>
		</form>
	</div>
</div>
</div>
<?php
				echo $sso_footer;
			}
			else if (isset($_REQUEST["sso_login_action"]) && $_REQUEST["sso_login_action"] == "update_info")
			{
				// Check the session and load the user account.
				$messages = array("errors" => array(), "warnings" => array(), "success" => "");
				foreach ($this->activemodules as &$instance)
				{
					$instance->UpdateInfoCheck($messages, false, false);
				}

				$userrow = false;
				if (!count($messages["errors"]))
				{
					if (!isset($_REQUEST["sso_v"]) || !isset($sso_session_info["sso_login_update"]))  $messages["errors"][] = BB_Translate("Invalid URL.  Verification missing.");
					else if (trim($_REQUEST["sso_v"]) !== $sso_session_info["sso_login_update"]["v"])  $messages["errors"][] = BB_Translate("Invalid verification string specified.");
					else if (!isset($sso_session_info["sso_login_update"]["expires"]) || CSDB::ConvertFromDBTime($sso_session_info["sso_login_update"]["expires"]) < time())  $messages["errors"][] = BB_Translate("Update information is expired or invalid.");
					else
					{
						try
						{
							$userrow = $sso_db->GetRow("SELECT", array(
								"*",
								"FROM" => "?",
								"WHERE" => "id = ?",
							), $sso_db_sso_login_users, $sso_session_info["sso_login_update"]["id"]);

							if ($userrow === false)  $messages["errors"][] = BB_Translate("Update information is expired or invalid.");
							else
							{
								if (!isset($userrow->username))  $userrow->username = "";
								if (!isset($userrow->email))  $userrow->email = "";
								if (!isset($userrow->verified))  $userrow->verified = 1;
							}
						}
						catch (Exception $e)
						{
							$messages["errors"][] = BB_Translate("User check failed.  Database query error.");
						}
					}
				}

				if (!count($messages["errors"]))
				{
					$userinfo = SSO_DecryptDBData($userrow->info);
					if ($userinfo === false)  $messages["errors"][] = BB_Translate("Error loading user information.");
				}

				if (isset($_REQUEST["sso_ajax"]))
				{
					if (!count($messages["errors"]))  $messages = $this->SignupUpdateCheck(true, $userrow, $userinfo, false);
					foreach ($messages["errors"] as $error)  echo "<div class=\"sso_main_formerror\">" . htmlspecialchars($error) . "</div>";
					foreach ($messages["warnings"] as $warning)  echo "<div class=\"sso_main_formwarning\">" . htmlspecialchars($warning) . "</div>";
					if (!count($messages["errors"]) && !count($messages["warnings"]))
					{
						if ($messages["success"] != "")  echo "<div class=\"sso_main_formokay\">" . htmlspecialchars($messages["success"]) . "</div>";
						else if ($messages["htmlsuccess"] != "")  echo "<div class=\"sso_main_formokay\">" . $messages["htmlsuccess"] . "</div>";
					}
				}
				else if (count($messages["errors"]))
				{
					echo $sso_header;

					SSO_OutputHeartbeat();
?>
<div class="sso_main_wrap sso_login">
<div class="sso_main_wrap_inner">
<?php
					$this->DisplayMessages($messages, false);
?>
	<div class="sso_login_signin"><a href="<?php echo htmlspecialchars($sso_target_url); ?>"><?php echo htmlspecialchars(BB_Translate("Sign in")); ?></a></div>
</div>
</div>
<?php
					echo $sso_footer;
				}
				else
				{
					$messagesheader = false;
					$messages = false;
					if (SSO_FrontendFieldValue("submit") === false)
					{
						if (isset($_REQUEST["sso_msg"]))
						{
							$messages = array("errors" => array(), "warnings" => array(), "success" => "");
							foreach ($this->activemodules as &$instance)
							{
								$instance->InitMessages($messages);
							}
						}
					}
					else
					{
						$messages = $this->SignupUpdateCheck(false, $userrow, $userinfo, false);
						if (!count($messages["errors"]))
						{
							// Update the account.
							if ($sso_settings["sso_login"]["change_username"] && ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username"))  $username = SSO_FrontendFieldValue("update_username", "");
							else  $username = $userrow->username;

							if ($sso_settings["sso_login"]["change_email"] && ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email"))
							{
								$email = SSO_FrontendFieldValue("update_email", "");
								$result = SMTP::MakeValidEmailAddress($email);
								$email = $result["email"];
								$verified = ($sso_settings["sso_login"]["email_verify_subject"] == "" || $sso_settings["sso_login"]["email_verify_msg"] == "" || $userrow->email == $email);
							}
							else
							{
								$email = $userrow->email;
								$verified = $userrow->verified;
							}

							if (SSO_FrontendFieldValue("update_pass", "") != "")
							{
								$salt = $sso_rng->GenerateString();
								$data = $username . ":" . $email . ":" . $salt . ":" . SSO_FrontendFieldValue("update_pass");
								$passwordinfo = self::HashPasswordInfo($data, $sso_settings["sso_login"]["password_mode"], $sso_settings["sso_login"]["password_minrounds"]);
								if (!$passwordinfo["success"])  $messages["errors"][] = BB_Translate("Unexpected cryptography error.");
								else
								{
									$numrounds = (int)$passwordinfo["rounds"];
									$password = bin2hex($passwordinfo["hash"]);
								}
							}
							else if ($username != $userrow->username || $email != $userrow->email)  $messages["errors"][] = BB_Translate("Please enter a new password.");
							else
							{
								$salt = $userinfo["salt"];
								$numrounds = $userinfo["rounds"];
								$password = $userinfo["password"];
							}

							if (SSO_FrontendFieldValue("reset_two_factor_key", "") == "yes")
							{
								$sso_session_info["sso_login_two_factor_key"] = self::GenerateOTPKey(10);
								SSO_SaveSessionInfo();

								$messages["errors"][] = BB_Translate("Two-factor authentication security key has been reset.");
							}

							if (!count($messages["errors"]))
							{
								$userinfo["two_factor_key"] = $sso_session_info["sso_login_two_factor_key"];
								$userinfo["two_factor_method"] = SSO_FrontendFieldValue("update_two_factor_method", "");
								foreach ($this->activemodules as &$instance)
								{
									$instance->UpdateAddInfo($userinfo);
								}
								$userinfo["salt"] = $salt;
								$userinfo["rounds"] = $numrounds;
								$userinfo["password"] = $password;
								$userinfo2 = SSO_EncryptDBData($userinfo);

								try
								{
									if ($sso_settings["sso_login"]["install_type"] == "email_username")
									{
										$sso_db->Query("UPDATE", array($sso_db_sso_login_users, array(
											"username" => $username,
											"email" => $email,
											"verified" => (int)$verified,
											"info" => $userinfo2,
										), "WHERE" => "id = ?"), $userrow->id);
									}
									else if ($sso_settings["sso_login"]["install_type"] == "email")
									{
										$sso_db->Query("UPDATE", array($sso_db_sso_login_users, array(
											"email" => $email,
											"verified" => (int)$verified,
											"info" => $userinfo2,
										), "WHERE" => "id = ?"), $userrow->id);
									}
									else if ($sso_settings["sso_login"]["install_type"] == "username")
									{
										$sso_db->Query("UPDATE", array($sso_db_sso_login_users, array(
											"username" => $username,
											"info" => $userinfo2,
										), "WHERE" => "id = ?"), $userrow->id);
									}
									else  $messages["errors"][] = BB_Translate("Fatal error:  Login system is broken.");

									// Send verification e-mail.
									$userid = $userrow->id;
									if (!count($messages["errors"]) && !$verified)  $this->SendVerificationEmail($userid, $userinfo, $messages, $username, $email);
								}
								catch (Exception $e)
								{
									$messages["errors"][] = BB_Translate("Database query error.");
								}

								if (!count($messages["errors"]))
								{
									foreach ($this->activemodules as &$instance)
									{
										$instance->UpdateInfoDone($userid);
									}

									header("Location: " . BB_GetRequestHost() . $sso_target_url . "&sso_msg=" . ($verified ? "updated" : "verify"));
									exit();
								}
							}
						}
					}

					echo $sso_header;

					SSO_OutputHeartbeat();
					$this->OutputJS($sso_target_url . "&sso_login_action=update_info&sso_v=" . urlencode($_REQUEST["sso_v"]) . "&sso_ajax=1");
?>
<div class="sso_main_wrap sso_login">
<div class="sso_main_wrap_inner">
<?php
					$this->DisplayMessages($messages);
?>
	<div class="sso_login_signin"><a href="<?php echo htmlspecialchars($sso_target_url); ?>"><?php echo htmlspecialchars(BB_Translate("Sign in")); ?></a></div>
	<div class="sso_main_form_wrap sso_login_updateinfo_form">
		<div class="sso_main_form_header"><?php echo htmlspecialchars(BB_Translate("Update Information")); ?></div>
		<form class="sso_main_form" name="sso_login_form" method="post" accept-charset="UTF-8" enctype="multipart/form-data" action="<?php echo htmlspecialchars($sso_target_url . "&sso_login_action=update_info&sso_v=" . urlencode($_REQUEST["sso_v"])); ?>" autocomplete="off">
<?php
				if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")
				{
?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Your E-mail Address")); ?></div>
				<div class="sso_main_formdata"><?php if ($sso_settings["sso_login"]["change_email"])  { ?><input class="sso_main_text sso_login_changehook" type="text" name="<?php echo SSO_FrontendField("update_email"); ?>" value="<?php echo htmlspecialchars(SSO_FrontendFieldValue("update_email", $userrow->email)); ?>" /><?php } else { ?><input type="hidden" name="<?php echo SSO_FrontendField("update_email"); ?>" value="<?php echo htmlspecialchars(SSO_FrontendFieldValue("update_email", $userrow->email)); ?>" /><div class="sso_main_static"><?php echo htmlspecialchars($userrow->email); ?></div><?php } ?></div>
			</div>
<?php
				}
				if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username")
				{
?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Your Username")); ?></div>
				<div class="sso_main_formdata"><?php if ($sso_settings["sso_login"]["change_username"])  { ?><input class="sso_main_text sso_login_changehook" type="text" name="<?php echo SSO_FrontendField("update_username"); ?>" value="<?php echo htmlspecialchars(SSO_FrontendFieldValue("update_username", $userrow->username)); ?>" /><?php } else { ?><input type="hidden" name="<?php echo SSO_FrontendField("update_username"); ?>" value="<?php echo htmlspecialchars(SSO_FrontendFieldValue("update_username", $userrow->username)); ?>" /><div class="sso_main_static"><?php echo htmlspecialchars($userrow->username); ?></div><?php } ?></div>
			</div>
<?php
				}
?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("New Password")); ?></div>
				<div class="sso_main_formdata"><input class="sso_main_text sso_login_changehook" type="password" name="<?php echo SSO_FrontendField("update_pass"); ?>" value="<?php echo htmlspecialchars(SSO_FrontendFieldValue("update_pass", "")); ?>" /></div>
				<div class="sso_main_formdesc"><?php echo htmlspecialchars(BB_Translate("Optional.  Will change the password for the account.")); ?></div>
			</div>
			<script type="text/javascript">
			jQuery('input.sso_main_text:first').focus();
			</script>
<?php
				$outputmap = array();

				// Two-factor authentication dropdown.
				$outputmap2 = array();
				$method = SSO_FrontendFieldValue("update_two_factor_method", (isset($updateinfo["two_factor_method"]) ? $updateinfo["two_factor_method"] : ""));
				foreach ($this->activemodules as $key => &$instance)
				{
					$name = $instance->GetTwoFactorName();
					if ($name !== false)
					{
						$order = (isset($sso_settings["sso_login"]["modules"][$key]["_s"]) ? $sso_settings["sso_login"]["modules"][$key]["_s"] : $instance->DefaultOrder());
						SSO_AddSortedOutput($outputmap2, $order, $key, "<option value=\"" . htmlspecialchars($key) . "\"" . ($method == $key ? " selected" : "") . ">" . htmlspecialchars($name) . "</option>");
					}
				}
				if (!$sso_settings["sso_login"]["require_two_factor"] && count($outputmap2))  SSO_AddSortedOutput($outputmap2, 0, "", "<option value=\"\"" . ($method == "" ? " selected" : "") . ">" . htmlspecialchars(BB_Translate("None")) . "</option>");
				if (count($outputmap2))
				{
					if (!isset($sso_session_info["sso_login_two_factor_key"]))
					{
						$sso_session_info["sso_login_two_factor_key"] = self::GenerateOTPKey(10);
						SSO_SaveSessionInfo();
					}

					ob_start();
?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Choose Two-Factor Authentication Method")); ?></div>
				<div class="sso_main_formdata"><select class="sso_main_dropdown sso_login_changehook_two_factor" name="<?php echo SSO_FrontendField("update_two_factor_method"); ?>">
					<?php SSO_DisplaySortedOutput($outputmap2); ?>
				</select></div>
				<div class="sso_main_formdesc"><?php echo htmlspecialchars(BB_Translate($sso_settings["sso_login"]["require_two_factor"] ? "Required.  Two-factor authentication vastly improves the security of your account." : "Optional.  Two-factor authentication vastly improves the security of your account.")); ?></div>
				<div class="sso_main_formtwofactorreset"><input id="sso_two_factor_reset" type="checkbox" name="<?php echo SSO_FrontendField("reset_two_factor_key"); ?>" value="yes"> <label for="sso_two_factor_reset"><?php echo htmlspecialchars(BB_Translate("Reset two-factor authentication security key")); ?></label></div>
			</div>
<?php
					$order = $sso_settings["sso_login"]["two_factor_order"];
					SSO_AddSortedOutput($outputmap, $order, "two_factor", ob_get_contents());
					ob_end_clean();
				}

				// Add active module output.
				foreach ($this->activemodules as $key => &$instance)
				{
					ob_start();
					$instance->GenerateUpdateInfo($userrow, $userinfo);
					$order = (isset($sso_settings["sso_login"]["modules"][$key]["_s"]) ? $sso_settings["sso_login"]["modules"][$key]["_s"] : $instance->DefaultOrder());
					SSO_AddSortedOutput($outputmap, $order, $key, ob_get_contents());
					ob_end_clean();
				}

				SSO_DisplaySortedOutput($outputmap);
?>
			<div class="sso_main_formsubmit">
				<input type="submit" name="<?php echo SSO_FrontendField("submit"); ?>" value="<?php echo htmlspecialchars(BB_Translate("Update")); ?>" />
			</div>
		</form>
	</div>
</div>
</div>
<?php

				}
			}
			else if (isset($_REQUEST["sso_login_action"]) && $_REQUEST["sso_login_action"] == "recover2" && isset($_REQUEST["sso_method"]) && $this->IsRecoveryAllowed())
			{
				// Load and validate the recovery options.
				$userrow = false;
				if (isset($sso_session_info["sso_login_recover"]) && isset($sso_session_info["sso_login_recover"]["id"]) && isset($sso_session_info["sso_login_recover"]["method"]) && $sso_session_info["sso_login_recover"]["method"] == $_REQUEST["sso_method"])
				{
					try
					{
						$userrow = $sso_db->GetRow("SELECT", array(
							"*",
							"FROM" => "?",
							"WHERE" => "id = ?",
						), $sso_db_sso_login_users, $sso_session_info["sso_login_recover"]["id"]);

						if ($userrow)
						{
							if (!isset($userrow->username))  $userrow->username = "";
							if (!isset($userrow->email))  $userrow->email = "";
							if (!isset($userrow->verified))  $userrow->verified = 1;
						}
					}
					catch (Exception $e)
					{
						header("Location: " . BB_GetRequestHost() . $sso_target_url . "&sso_login_action=recover&sso_msg=recovery_db_error");
						exit();
					}
				}

				if ($userrow === false)
				{
					header("Location: " . BB_GetRequestHost() . $sso_target_url . "&sso_login_action=recover&sso_msg=recovery_expired_invalid");
					exit();
				}

				$userinfo = SSO_DecryptDBData($userrow->info);

				if ($userinfo === false)
				{
					header("Location: " . BB_GetRequestHost() . $sso_target_url . "&sso_login_action=recover&sso_msg=recovery_db_user_error");
					exit();
				}

				$messagesheader = false;
				$messages = false;
				if (SSO_FrontendFieldValue("submit") === false)
				{
					if (isset($_REQUEST["sso_msg"]))
					{
						$messages = array("errors" => array(), "warnings" => array(), "success" => "");
						foreach ($this->activemodules as &$instance)
						{
							$instance->InitMessages($messages);
						}
					}
				}
				else
				{
					$messages = array("errors" => array(), "warnings" => array(), "success" => "");
					foreach ($this->activemodules as &$instance)
					{
						$instance->RecoveryCheck2($messages, false);
					}

					if (!count($messages["errors"]))
					{
						foreach ($this->activemodules as &$instance)
						{
							$instance->RecoveryCheck2($messages, $userinfo);
						}

						if (!count($messages["errors"]))
						{
							$sso_session_info["sso_login_update"] = array(
								"id" => $userrow->id,
								"v" => $sso_rng->GenerateString(),
								"expires" => CSDB::ConvertToDBTime(time() + 30 * 60)
							);
							$sso_session_info["sso_login_two_factor_key"] = (isset($userinfo["two_factor_key"]) && $userinfo["two_factor_key"] != "" ? $userinfo["two_factor_key"] : self::GenerateOTPKey(10));

							if (!SSO_SaveSessionInfo())  $result["errors"][] = BB_Translate("Recovery was successful but a fatal error occurred.  Fatal error:  Unable to save session information.");
							else
							{
								header("Location: " . BB_GetRequestHost() . $sso_target_url . "&sso_login_action=update_info&sso_v=" . urlencode($sso_session_info["sso_login_update"]["v"]));
								exit();
							}
						}
					}
				}

				echo $sso_header;

				SSO_OutputHeartbeat();
				$this->OutputJS();
?>
<div class="sso_main_wrap sso_login">
<div class="sso_main_wrap_inner">
<?php
				$this->DisplayMessages($messages, $messagesheader);
?>
	<div class="sso_login_signin"><a href="<?php echo htmlspecialchars($sso_target_url); ?>"><?php echo htmlspecialchars(BB_Translate("Sign in")); ?></a></div>
	<div class="sso_main_form_wrap sso_login_recover_form">
		<div class="sso_main_form_header"><?php echo htmlspecialchars(BB_Translate("Restore Access")); ?></div>
		<form class="sso_main_form" name="sso_login_form" method="post" accept-charset="UTF-8" enctype="multipart/form-data" action="<?php echo htmlspecialchars($sso_target_url . "&sso_login_action=recover2&sso_method=" . urlencode($_REQUEST["sso_method"])); ?>" autocomplete="off">
<?php
				$outputmap = array();
				foreach ($this->activemodules as $key => &$instance)
				{
					ob_start();
					$instance->GenerateRecovery2($messages);
					$order = (isset($sso_settings["sso_login"]["modules"][$key]["_s"]) ? $sso_settings["sso_login"]["modules"][$key]["_s"] : $instance->DefaultOrder());
					SSO_AddSortedOutput($outputmap, $order, $key, ob_get_contents());
					ob_end_clean();
				}

				SSO_DisplaySortedOutput($outputmap);
?>
			<script type="text/javascript">
			jQuery('input.sso_main_text:first').focus();
			</script>
			<div class="sso_main_formsubmit">
				<input type="submit" name="<?php echo SSO_FrontendField("submit"); ?>" value="<?php echo htmlspecialchars(BB_Translate("Next")); ?>" />
			</div>
		</form>
	</div>
</div>
</div>
<?php
				echo $sso_footer;
			}
			else if (isset($_REQUEST["sso_login_action"]) && $_REQUEST["sso_login_action"] == "recover" && $this->IsRecoveryAllowed())
			{
				$messagesheader = false;
				$messages = false;
				if (SSO_FrontendFieldValue("submit") === false)
				{
					if (isset($_REQUEST["sso_msg"]))
					{
						$messages = array("errors" => array(), "warnings" => array(), "success" => "");
						if ($_REQUEST["sso_msg"] == "recovery_db_error")  $messages["warnings"][] = BB_Translate("A database error occurred while attempting to load recovery information.");
						else if ($_REQUEST["sso_msg"] == "recovery_expired_invalid")  $messages["errors"][] = BB_Translate("Recovery information is expired or invalid.");
						else if ($_REQUEST["sso_msg"] == "recovery_db_user_error")  $messages["errors"][] = BB_Translate("User information in the database is corrupted.");
						else
						{
							foreach ($this->activemodules as &$instance)
							{
								$instance->InitMessages($messages);
							}
						}
					}
				}
				else
				{
					$messages = array("errors" => array(), "warnings" => array(), "success" => "");
					$user = SSO_FrontendFieldValue("user_recover");
					$method = SSO_FrontendFieldValue("recover_method");
					if ($user === false || $user == "" || $method === false || $method == "")  $messages["errors"][] = BB_Translate("Please fill in the fields.");
					else
					{
						foreach ($this->activemodules as &$instance)
						{
							$instance->RecoveryCheck($messages, false);
						}
						if (!count($messages["errors"]))
						{
							$userrow = false;
							if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")
							{
								try
								{
									$userrow = $sso_db->GetRow("SELECT", array(
										"*",
										"FROM" => "?",
										"WHERE" => "email = ?",
									), $sso_db_sso_login_users, $user);

									if ($userrow)
									{
										if (!isset($userrow->username))  $userrow->username = "";
									}
								}
								catch (Exception $e)
								{
									$messages["errors"][] = BB_Translate("User check failed.  Database query error.");
								}
							}
							else if ($sso_settings["sso_login"]["install_type"] == "username")
							{
								try
								{
									$userrow = $sso_db->GetRow("SELECT", array(
										"*",
										"FROM" => "?",
										"WHERE" => "username = ?",
									), $sso_db_sso_login_users, $user);

									if ($userrow)
									{
										if (!isset($userrow->email))  $userrow->email = "";
										if (!isset($userrow->verified))  $userrow->verified = 1;
									}
								}
								catch (Exception $e)
								{
									$messages["errors"][] = BB_Translate("User check failed.  Database query error.");
								}
							}
							else  $messages["errors"][] = BB_Translate("Login system is broken.");

							if ($userrow === false)  $messages["errors"][] = BB_Translate("Invalid login.");
							else
							{
								$userinfo = SSO_DecryptDBData($userrow->info);

								if ($userinfo === false)  $messages["errors"][] = BB_Translate("Error loading user information.");
								else
								{
									foreach ($this->activemodules as &$instance)
									{
										$instance->RecoveryCheck($messages, $userinfo);
									}
								}
							}

							if (!count($messages["errors"]))
							{
								if ($method == "email" && $userrow->email != "")
								{
									$sso_session_info["sso_login_update"] = array(
										"id" => $userrow->id,
										"v" => $sso_rng->GenerateString(),
										"expires" => CSDB::ConvertToDBTime(time() + 30 * 60)
									);
									$sso_session_info["sso_login_two_factor_key"] = (isset($userinfo["two_factor_key"]) && $userinfo["two_factor_key"] != "" ? $userinfo["two_factor_key"] : self::GenerateOTPKey(10));

									if (!SSO_SaveSessionInfo())  $messages["errors"][] = BB_Translate("Login exists but a fatal error occurred.  Fatal error:  Unable to save session information.");
									else
									{
										$fromaddr = BB_PostTranslate($sso_settings["sso_login"]["email_recover_from"] != "" ? $sso_settings["sso_login"]["email_recover_from"] : SSO_SMTP_FROM);
										$subject = BB_Translate($sso_settings["sso_login"]["email_recover_subject"]);
										$verifyurl = BB_GetRequestHost() . $sso_target_url . ($sso_settings["sso_login"]["email_session"] == "all" ? "&sso_id=" . urlencode($_REQUEST["sso_id"]) : "") . "&sso_login_action=update_info&sso_v=" . urlencode($sso_session_info["sso_login_update"]["v"]);
										$htmlmsg = str_ireplace(array("@USERNAME@", "@EMAIL@", "@VERIFY@"), array(htmlspecialchars($userrow->username), htmlspecialchars($userrow->email), htmlspecialchars($verifyurl)), BB_PostTranslate($sso_settings["sso_login"]["email_recover_msg"]));
										$textmsg = str_ireplace(array("@USERNAME@", "@EMAIL@", "@VERIFY@"), array($userrow->username, $userrow->email, $verifyurl), BB_PostTranslate($sso_settings["sso_login"]["email_recover_msg_text"]));
										foreach ($this->activemodules as &$instance)
										{
											$instance->ModifyEmail($userinfo, $htmlmsg, $textmsg);
										}

										$result = SSO_SendEmail($fromaddr, $userrow->email, $subject, $htmlmsg, $textmsg);
										if (!$result["success"])  $messages["errors"][] = BB_Translate("Login exists but a fatal error occurred.  Fatal error:  Unable to send verification e-mail.  %s", $result["error"]);
										else
										{
											foreach ($this->activemodules as &$instance)
											{
												$instance->RecoveryDone($messages, $method, $userrow, $userinfo);
											}

											if (!count($messages["errors"]))
											{
												header("Location: " . BB_GetRequestHost() . $sso_target_url . "&sso_msg=recovery_email_sent");
												exit();
											}
										}
									}
								}
								else
								{
									foreach ($this->activemodules as &$instance)
									{
										$instance->RecoveryDone($messages, $method, $userrow, $userinfo);
									}
								}
							}
						}
					}
				}

				echo $sso_header;

				SSO_OutputHeartbeat();
				$this->OutputJS();
?>
<div class="sso_main_wrap sso_login">
<div class="sso_main_wrap_inner">
<?php
				$this->DisplayMessages($messages, $messagesheader);
?>
	<div class="sso_login_signin"><a href="<?php echo htmlspecialchars($sso_target_url); ?>"><?php echo htmlspecialchars(BB_Translate("Sign in")); ?></a></div>
	<div class="sso_main_form_wrap sso_login_recover_form">
		<div class="sso_main_form_header"><?php echo htmlspecialchars(BB_Translate("Restore Access")); ?></div>
		<form class="sso_main_form" name="sso_login_form" method="post" accept-charset="UTF-8" enctype="multipart/form-data" action="<?php echo htmlspecialchars($sso_target_url . "&sso_login_action=recover"); ?>" autocomplete="off">
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php
				if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")  echo htmlspecialchars(BB_Translate("E-mail Address"));
				else if ($sso_settings["sso_login"]["install_type"] == "username")  echo htmlspecialchars(BB_Translate("Username"));
				else  echo htmlspecialchars(BB_Translate("Login system is broken."));
?></div>
				<div class="sso_main_formdata"><input class="sso_main_text" type="text" name="<?php echo SSO_FrontendField("user_recover"); ?>" /></div>
			</div>
			<script type="text/javascript">
			jQuery('input.sso_main_text:first').focus();
			</script>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Recovery Method")); ?></div>
				<div class="sso_main_formdata"><select class="sso_main_dropdown" name="<?php echo SSO_FrontendField("recover_method"); ?>">
<?php
				$method = SSO_FrontendFieldValue("recover_method", "");
				if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")  echo "<option value=\"email\"" . ($method == "email" ? " selected" : "") . ">" . htmlspecialchars(BB_Translate("E-mail")) . "</option>";
				foreach ($this->activemodules as &$instance)
				{
					$instance->AddRecoveryMethod($method);
				}
?>
				</select></div>
			</div>
<?php
				$outputmap = array();
				foreach ($this->activemodules as $key => &$instance)
				{
					ob_start();
					$instance->GenerateRecovery($messages);
					$order = (isset($sso_settings["sso_login"]["modules"][$key]["_s"]) ? $sso_settings["sso_login"]["modules"][$key]["_s"] : $instance->DefaultOrder());
					SSO_AddSortedOutput($outputmap, $order, $key, ob_get_contents());
					ob_end_clean();
				}

				SSO_DisplaySortedOutput($outputmap);
?>
			<div class="sso_main_formsubmit">
				<input type="submit" name="<?php echo SSO_FrontendField("submit"); ?>" value="<?php echo htmlspecialchars(BB_Translate("Next")); ?>" />
			</div>
		</form>
	</div>
</div>
</div>
<?php
				echo $sso_footer;
			}
			else if (isset($_REQUEST["sso_login_action"]) && $_REQUEST["sso_login_action"] == "two_factor")
			{
				// Check the session and load the user account.
				$messages = array("errors" => array(), "warnings" => array(), "success" => "");
				foreach ($this->activemodules as &$instance)
				{
					$instance->TwoFactorCheck($messages, false);
				}

				$userrow = false;
				if (!count($messages["errors"]))
				{
					if (!isset($_REQUEST["sso_v"]) || !isset($sso_session_info["sso_login_two_factor"]))  $messages["errors"][] = BB_Translate("Invalid URL.  Verification missing.");
					else if (trim($_REQUEST["sso_v"]) !== $sso_session_info["sso_login_two_factor"]["v"])  $messages["errors"][] = BB_Translate("Invalid verification string specified.");
					else if (!isset($sso_session_info["sso_login_two_factor"]["expires"]) || CSDB::ConvertFromDBTime($sso_session_info["sso_login_two_factor"]["expires"]) < time())  $messages["errors"][] = BB_Translate("Two-factor information is expired or invalid.");
					else
					{
						try
						{
							$userrow = $sso_db->GetRow("SELECT", array(
								"*",
								"FROM" => "?",
								"WHERE" => "id = ?",
							), $sso_db_sso_login_users, $sso_session_info["sso_login_two_factor"]["id"]);

							if ($userrow === false)  $messages["errors"][] = BB_Translate("Two-factor information is expired or invalid.");
							else
							{
								if (!isset($userrow->username))  $userrow->username = "";
								if (!isset($userrow->email))  $userrow->email = "";
								if (!isset($userrow->verified))  $userrow->verified = 1;
							}
						}
						catch (Exception $e)
						{
							$messages["errors"][] = BB_Translate("User check failed.  Database query error.");
						}
					}
				}

				$method = BB_Translate("Unknown/Invalid.");
				if (!count($messages["errors"]))
				{
					$userinfo = SSO_DecryptDBData($userrow->info);
					if ($userinfo === false)  $messages["errors"][] = BB_Translate("Error loading user information.");
					else
					{
						// Check the two-factor authentication method.
						$methods = array();
						foreach ($this->activemodules as $key => &$instance)
						{
							$name = $instance->GetTwoFactorName(false);
							if ($name !== false)  $methods[$key] = $name;
						}

						if (isset($userinfo["two_factor_method"]) && isset($methods[$userinfo["two_factor_method"]]))  $method = $methods[$userinfo["two_factor_method"]];
						else  $messages["errors"][] = BB_Translate("A valid two-factor authentication method for this account is not available.  Use account recovery to restore access to the account.");
					}
				}

				if (count($messages["errors"]))
				{
					echo $sso_header;

					SSO_OutputHeartbeat();
?>
<div class="sso_main_wrap sso_login">
<div class="sso_main_wrap_inner">
<?php
					$this->DisplayMessages($messages, false);
?>
	<div class="sso_login_signin"><a href="<?php echo htmlspecialchars($sso_target_url); ?>"><?php echo htmlspecialchars(BB_Translate("Sign in")); ?></a></div>
</div>
</div>
<?php
					echo $sso_footer;
				}
				else
				{
					$messagesheader = false;
					$messages = false;
					if (SSO_FrontendFieldValue("submit") === false)
					{
						if (isset($_REQUEST["sso_msg"]))
						{
							$messages = array("errors" => array(), "warnings" => array(), "success" => "");
							foreach ($this->activemodules as &$instance)
							{
								$instance->InitMessages($messages);
							}
						}
					}
					else
					{
						$messages = array("errors" => array(), "warnings" => array(), "success" => "");
						foreach ($this->activemodules as &$instance)
						{
							$instance->TwoFactorCheck($messages, $userinfo);
						}

						if (count($messages["errors"]))
						{
							foreach ($this->activemodules as &$instance)
							{
								$instance->TwoFactorFailed($messages, $userinfo);
							}
						}
						else
						{
							// Login with two-factor authentication succeeded.  Activate the user.
							$mapinfo = array();
							if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")  $mapinfo[$sso_settings["sso_login"]["map_email"]] = $userrow->email;
							if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username")  $mapinfo[$sso_settings["sso_login"]["map_username"]] = $userrow->username;

							$origuserinfo = $userinfo;
							foreach ($this->activemodules as &$instance)
							{
								$instance->LoginAddMap($mapinfo, $userrow, $userinfo, false);
							}

							// If a module updated $userinfo, then update the database.
							if (serialize($userinfo) !== serialize($origuserinfo))
							{
								$userinfo2 = SSO_EncryptDBData($userinfo);

								try
								{
									$sso_db->Query("UPDATE", array($sso_db_sso_login_users, array(
										"info" => $userinfo2,
									), "WHERE" => "id = ?"), $userrow->id);
								}
								catch (Exception $e)
								{
									$messages["errors"][] = BB_Translate("Database query error.");
								}
							}

							if (!count($messages["errors"]))
							{
								SSO_ActivateUser($userrow->id, $userinfo["extra"], $mapinfo, CSDB::ConvertFromDBTime($userrow->created));

								// Only falls through on account lockout or a fatal error.
								$messages["errors"][] = BB_Translate("User activation failed.");
							}
						}
					}

					echo $sso_header;

					SSO_OutputHeartbeat();
					$this->OutputJS();
?>
<div class="sso_main_wrap sso_login">
<div class="sso_main_wrap_inner">
<?php
					$this->DisplayMessages($messages, $messagesheader);
?>
	<div class="sso_login_signin"><a href="<?php echo htmlspecialchars($sso_target_url); ?>"><?php echo htmlspecialchars(BB_Translate("Sign in")); ?></a></div>
	<div class="sso_main_form_wrap sso_login_recover_form">
		<div class="sso_main_form_header"><?php echo htmlspecialchars(BB_Translate("Two-Factor Authentication")); ?></div>
		<form class="sso_main_form" name="sso_login_form" method="post" accept-charset="UTF-8" enctype="multipart/form-data" action="<?php echo htmlspecialchars($sso_target_url . "&sso_login_action=two_factor&sso_v=" . urlencode($_REQUEST["sso_v"])); ?>" autocomplete="off">
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Enter Two-Factor Authentication Code")); ?></div>
				<div class="sso_main_formdata"><input class="sso_main_text" type="text" name="<?php echo SSO_FrontendField("two_factor_code"); ?>" /></div>
				<div class="sso_main_formdesc"><?php echo htmlspecialchars(BB_Translate("From %s.", $method)); ?></div>
			</div>
			<script type="text/javascript">
			jQuery('input.sso_main_text:first').focus();
			</script>
			<div class="sso_main_formsubmit">
				<input type="submit" name="<?php echo SSO_FrontendField("submit"); ?>" value="<?php echo htmlspecialchars(BB_Translate("Sign in")); ?>" />
			</div>
		</form>
	</div>
</div>
</div>
<?php
					echo $sso_footer;
				}
			}
			else
			{
				$messagesheader = false;
				$messages = false;
				if (SSO_FrontendFieldValue("submit") === false)
				{
					if (isset($_REQUEST["sso_msg"]))
					{
						$messages = array("errors" => array(), "warnings" => array(), "success" => "");
						if ($_REQUEST["sso_msg"] == "verified")  $messages["success"] = BB_Translate("Your account is ready to use.");
						else if ($_REQUEST["sso_msg"] == "verify")  $messages["warnings"][] = BB_Translate("Account must be verified before it can be used.  Check your e-mail.");
						else if ($_REQUEST["sso_msg"] == "recovery_email_sent")  $messages["warnings"][] = BB_Translate("Account recovery URL sent.  Check your e-mail.");
						else if ($_REQUEST["sso_msg"] == "updated")  $messages["success"] = BB_Translate("Your account information has been updated and is ready to use.");
						else if ($_REQUEST["sso_msg"] == "two_factor_auth_expired")  $messages["errors"][] = BB_Translate("Two-factor authentication expired.  Sign in again.");
						else
						{
							foreach ($this->activemodules as &$instance)
							{
								$instance->InitMessages($messages);
							}
						}
					}
				}
				else
				{
					$messages = array("errors" => array(), "warnings" => array(), "success" => "");
					$user = SSO_FrontendFieldValue("user");
					$password = SSO_FrontendFieldValue("password");
					if ($user === false || $user == "" || $password === false || $password == "")  $messages["errors"][] = BB_Translate("Please fill in the fields.");
					else
					{
						$recoveryallowed = $this->IsRecoveryAllowed(false);
						foreach ($this->activemodules as &$instance)
						{
							$instance->LoginCheck($messages, false, $recoveryallowed);
						}
						if (!count($messages["errors"]))
						{
							$userrow = false;
							if (strpos($user, "@") !== false && ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email"))
							{
								try
								{
									$userrow = $sso_db->GetRow("SELECT", array(
										"*",
										"FROM" => "?",
										"WHERE" => "email = ?",
									), $sso_db_sso_login_users, $user);

									if ($userrow)
									{
										$userinfo = SSO_DecryptDBData($userrow->info);

										if ($userinfo === false)  $userrow = false;
										else
										{
											if (!isset($userrow->username))  $userrow->username = "";
											$data = $userrow->username . ":" . $userrow->email . ":" . $userinfo["salt"] . ":" . $password;
											if (!self::VerifyPasswordInfo($data, $userinfo["password"], $userinfo["rounds"]))  $userrow = false;
										}
									}
								}
								catch (Exception $e)
								{
									$messages["errors"][] = BB_Translate("Login failed.  Database query error.");
								}
							}

							if ($userrow === false && ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username"))
							{
								try
								{
									$userrow = $sso_db->GetRow("SELECT", array(
										"*",
										"FROM" => "?",
										"WHERE" => "username = ?",
									), $sso_db_sso_login_users, $user);

									if ($userrow)
									{
										$userinfo = SSO_DecryptDBData($userrow->info);

										if ($userinfo === false)  $userrow = false;
										else
										{
											if (!isset($userrow->email))  $userrow->email = "";
											if (!isset($userrow->verified))  $userrow->verified = 1;
											$data = $userrow->username . ":" . $userrow->email . ":" . $userinfo["salt"] . ":" . $password;
											if (!self::VerifyPasswordInfo($data, $userinfo["password"], $userinfo["rounds"]))  $userrow = false;
										}
									}
								}
								catch (Exception $e)
								{
									$messages["errors"][] = BB_Translate("Login failed.  Database query error.");
								}
							}

							if ($userrow === false)  $messages["errors"][] = BB_Translate("Invalid login.");
							else
							{
								// Make sure the password is stored securely.  If not, transparently update the hash information in the database.
								if ($userinfo["rounds"] < $sso_settings["sso_login"]["password_minrounds"])
								{
									$userinfo["salt"] = $sso_rng->GenerateString();
									$data = $userrow->username . ":" . $userrow->email . ":" . $userinfo["salt"] . ":" . $password;
									$passwordinfo = self::HashPasswordInfo($data, $sso_settings["sso_login"]["password_mode"], $sso_settings["sso_login"]["password_minrounds"]);
									if ($passwordinfo["success"])
									{
										$userinfo["rounds"] = (int)$passwordinfo["rounds"];
										$userinfo["password"] = bin2hex($passwordinfo["hash"]);

										$userinfo2 = SSO_EncryptDBData($userinfo);

										try
										{
											$sso_db->Query("UPDATE", array($sso_db_sso_login_users, array(
												"info" => $userinfo2,
											), "WHERE" => "id = ?"), $userrow->id);
										}
										catch (Exception $e)
										{
											$messages["errors"][] = BB_Translate("Database query error.");
										}
									}
								}

								foreach ($this->activemodules as &$instance)
								{
									$instance->LoginCheck($messages, $userinfo, $recoveryallowed);
								}
							}

							if (!count($messages["errors"]))
							{
								// Go to two-factor authentication page.
								$methods = array();
								foreach ($this->activemodules as $key => &$instance)
								{
									$name = $instance->GetTwoFactorName(false);
									if ($name !== false)  $methods[$key] = true;
								}

								// Resend the verification e-mail.
								if (!$userrow->verified)  $this->SendVerificationEmail($userrow->id, $userinfo, $messages, $userrow->username, $userrow->email);
								else if (!$recoveryallowed && SSO_FrontendFieldValue("update_info", "") == "yes")
								{
									$sso_session_info["sso_login_update"] = array(
										"id" => $userrow->id,
										"v" => $sso_rng->GenerateString(),
										"expires" => CSDB::ConvertToDBTime(time() + 30 * 60)
									);
									$sso_session_info["sso_login_two_factor_key"] = (isset($userinfo["two_factor_key"]) && $userinfo["two_factor_key"] != "" ? $userinfo["two_factor_key"] : self::GenerateOTPKey(10));

									if (!SSO_SaveSessionInfo())  $messages["errors"][] = BB_Translate("Login exists but a fatal error occurred.  Fatal error:  Unable to save session information.");
									else
									{
										header("Location: " . BB_GetRequestHost() . $sso_target_url . "&sso_login_action=update_info&sso_v=" . urlencode($sso_session_info["sso_login_update"]["v"]));
										exit();
									}
								}
								else if ($sso_settings["sso_login"]["require_two_factor"] || (isset($userinfo["two_factor_method"]) && $userinfo["two_factor_method"] != "" && (count($methods) || ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email"))))
								{
									if ($sso_settings["sso_login"]["require_two_factor"] && (!isset($userinfo["two_factor_method"]) || !isset($methods[$userinfo["two_factor_method"]])))
									{
										$messages["errors"][] = BB_Translate("A valid two-factor authentication method for this account is not available.  Use account recovery to restore access to the account.");
									}
									else
									{
										$sso_session_info["sso_login_two_factor"] = array(
											"id" => $userrow->id,
											"v" => $sso_rng->GenerateString(),
											"expires" => CSDB::ConvertToDBTime(time() + 5 * 60)
										);

										if (!SSO_SaveSessionInfo())  $messages["errors"][] = BB_Translate("Login exists but a fatal error occurred.  Fatal error:  Unable to save session information.");
										else
										{
											$this->activemodules[$userinfo["two_factor_method"]]->SendTwoFactorCode($messages, $userrow, $userinfo);

											if (!count($messages["errors"]))
											{
												header("Location: " . BB_GetRequestHost() . $sso_target_url . "&sso_login_action=two_factor&sso_v=" . urlencode($sso_session_info["sso_login_two_factor"]["v"]));
												exit();
											}
										}
									}
								}
								else
								{
									// Login succeeded.  Activate the user.
									$mapinfo = array();
									if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email")  $mapinfo[$sso_settings["sso_login"]["map_email"]] = $userrow->email;
									if ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "username")  $mapinfo[$sso_settings["sso_login"]["map_username"]] = $userrow->username;

									$origuserinfo = $userinfo;
									foreach ($this->activemodules as &$instance)
									{
										$instance->LoginAddMap($mapinfo, $userrow, $userinfo, false);
									}

									// If a module updated $userinfo, then update the database.
									if (serialize($userinfo) !== serialize($origuserinfo))
									{
										$userinfo2 = SSO_EncryptDBData($userinfo);

										try
										{
											$sso_db->Query("UPDATE", array($sso_db_sso_login_users, array(
												"info" => $userinfo2,
											), "WHERE" => "id = ?"), $userrow->id);
										}
										catch (Exception $e)
										{
											$messages["errors"][] = BB_Translate("Database query error.");
										}
									}

									if (!count($messages["errors"]))
									{
										SSO_ActivateUser($userrow->id, $userinfo["extra"], $mapinfo, CSDB::ConvertFromDBTime($userrow->created));

										// Only falls through on account lockout or a fatal error.
										$messages["errors"][] = BB_Translate("User activation failed.");
									}
								}
							}
						}
					}
				}

				echo $sso_header;

				SSO_OutputHeartbeat();
				$this->OutputJS();
?>
<div class="sso_main_wrap sso_login">
<div class="sso_main_wrap_inner">
<?php
				$this->DisplayMessages($messages, $messagesheader);

				if ($sso_settings["sso_login"]["open_reg"])
				{
?>
	<div class="sso_login_signup"><a href="<?php echo htmlspecialchars($sso_target_url . "&sso_login_action=signup"); ?>"><?php echo htmlspecialchars(BB_Translate("Sign up")); ?></a></div>
<?php
				}
?>
	<div class="sso_main_form_wrap sso_login_signin_form">
		<div class="sso_main_form_header"><?php echo htmlspecialchars(BB_Translate("Sign in")); ?></div>
		<form class="sso_main_form" name="sso_login_form" method="post" accept-charset="UTF-8" enctype="multipart/form-data" action="<?php echo htmlspecialchars($sso_target_url); ?>" autocomplete="off">
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php
				if ($sso_settings["sso_login"]["install_type"] == "email_username")  echo htmlspecialchars(BB_Translate("Username or E-mail Address"));
				else if ($sso_settings["sso_login"]["install_type"] == "username")  echo htmlspecialchars(BB_Translate("Username"));
				else if ($sso_settings["sso_login"]["install_type"] == "email")  echo htmlspecialchars(BB_Translate("E-mail Address"));
				else  echo htmlspecialchars(BB_Translate("Login system is broken."));
?></div>
				<div class="sso_main_formdata"><input class="sso_main_text" type="text" name="<?php echo SSO_FrontendField("user"); ?>" /></div>
			</div>
			<script type="text/javascript">
			jQuery('input.sso_main_text:first').focus();
			</script>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Password")); ?></div>
				<div class="sso_main_formdata"><input class="sso_main_text" type="password" name="<?php echo SSO_FrontendField("password"); ?>" /></div>
			</div>
<?php
				$outputmap = array();
				foreach ($this->activemodules as $key => &$instance)
				{
					ob_start();
					$instance->GenerateLogin($messages);
					$order = (isset($sso_settings["sso_login"]["modules"][$key]["_s"]) ? $sso_settings["sso_login"]["modules"][$key]["_s"] : $instance->DefaultOrder());
					SSO_AddSortedOutput($outputmap, $order, $key, ob_get_contents());
					ob_end_clean();
				}

				SSO_DisplaySortedOutput($outputmap);

				if (!$this->IsRecoveryAllowed(false))
				{
?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Update Information")); ?></div>
				<div class="sso_main_formdata"><input id="sso_norecovery_updateinfo" type="checkbox" name="<?php echo SSO_FrontendField("update_info"); ?>" value="yes"<?php if (SSO_FrontendFieldValue("update_info", "") == "yes")  echo " checked"; ?> /> <label for="sso_norecovery_updateinfo">Change account information upon successful sign in</label></div>
			</div>
<?php
				}
?>
			<div class="sso_main_formsubmit">
				<input type="submit" name="<?php echo SSO_FrontendField("submit"); ?>" value="<?php echo htmlspecialchars(BB_Translate("Sign in")); ?>" />
			</div>
		</form>
	</div>
<?php
				if ($this->IsRecoveryAllowed())
				{
?>
	<div class="sso_login_recover_changeinfo"><a href="<?php echo htmlspecialchars($sso_target_url . "&sso_login_action=recover"); ?>"><?php echo htmlspecialchars(BB_Translate("Can't access your account?")); ?></a></div>
<?php
				}
?>
</div>
</div>
<?php
				echo $sso_footer;
			}
		}

		public static function PackInt64($num)
		{
			$result = "";

			if (is_int(2147483648))  $floatlim = 9223372036854775808;
			else  $floatlim = 2147483648;

			if (is_float($num))
			{
				$num = floor($num);
				if ($num < (double)$floatlim)  $num = (int)$num;
			}

			while (is_float($num))
			{
				$byte = (int)fmod($num, 256);
				$result = chr($byte) . $result;

				$num = floor($num / 256);
				if (is_float($num) && $num < (double)$floatlim)  $num = (int)$num;
			}

			while ($num > 0)
			{
				$byte = $num & 0xFF;
				$result = chr($byte) . $result;
				$num = $num >> 8;
			}

			$result = str_pad($result, 8, "\x00", STR_PAD_LEFT);
			$result = substr($result, -8);

			return $result;
		}

		// Implements RFC6238 in Google Authenticator-compatible time-based one time pad (OTP) format.
		// Expects $key to be in base32 encoded format.
		public static function GetTimeBasedOTP($key, $time, $digits = 6, $algo = "sha1")
		{
			$base32 = new Base2n(5, "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567", FALSE, TRUE, FALSE);
			$key = $base32->decode($key);

			// Convert time to binary (64-bit).
			$data = self::PackInt64($time);

			// Pad to 8 bytes.
			$data = str_pad($data, 8, "\x00", STR_PAD_LEFT);

			// Get the HMAC.
			$data = hash_hmac($algo, $data, $key);

			// Extract part of the hash.
			$pos = 2 * hexdec(substr($data, -1));
			$data = hexdec(substr($data, $pos, 8)) & 0x7fffffff;

			// Reduce the result.
			$result = $data % (int)pow(10, (int)$digits);

			// Convert to zero-padded string.
			$result = str_pad($result, (int)$digits, "0", STR_PAD_LEFT);

			return $result;
		}

		public static function GenerateOTPKey($size)
		{
			global $sso_rng;

			$data = $sso_rng->GetBytes($size);
			$base32 = new Base2n(5, "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567", FALSE, TRUE, FALSE);
			$result = $base32->encode($data);

			return $result;
		}
	}
?>