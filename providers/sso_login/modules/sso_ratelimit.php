<?php
	// SSO Generic Login Module for Rate Limiting
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	if (!defined("SSO_FILE"))  exit();

	$g_sso_login_modules["sso_ratelimit"] = array(
		"name" => "Rate Limiting",
		"desc" => "Prevents attacks and general abuse of the login and registration system."
	);

	class sso_login_module_sso_ratelimit extends sso_login_ModuleBase
	{
		private function GetInfo()
		{
			global $sso_settings;

			$info = $sso_settings["sso_login"]["modules"]["sso_ratelimit"];
			if (!isset($info["system_interval"]))  $info["system_interval"] = 24 * 60 * 60;
			if (!isset($info["system_requests"]))  $info["system_requests"] = 2800;
			if (!isset($info["login_interval"]))  $info["login_interval"] = 15 * 60;
			if (!isset($info["login_attempts"]))  $info["login_attempts"] = 20;
			if (!isset($info["two_factor_attempts"]))  $info["two_factor_attempts"] = 3;
			if (!isset($info["register_interval"]))  $info["register_interval"] = 8 * 60 * 60;
			if (!isset($info["register_num"]))  $info["register_num"] = 1;

			return $info;
		}

		public function ConfigSave()
		{
			global $sso_settings;

			$info = $this->GetInfo();
			$info["system_interval"] = (int)$_REQUEST["sso_ratelimit_system_interval"];
			$info["system_requests"] = (int)$_REQUEST["sso_ratelimit_system_requests"];
			$info["login_interval"] = (int)$_REQUEST["sso_ratelimit_login_interval"];
			$info["login_attempts"] = (int)$_REQUEST["sso_ratelimit_login_attempts"];
			$info["two_factor_attempts"] = (int)$_REQUEST["sso_ratelimit_two_factor_attempts"];
			$info["register_interval"] = (int)$_REQUEST["sso_ratelimit_register_interval"];
			$info["register_num"] = (int)$_REQUEST["sso_ratelimit_register_num"];

			if ($info["system_interval"] < 1)  BB_SetPageMessage("error", "The 'Total Requests Interval' field contains an invalid value.");
			else if ($info["system_requests"] < 1)  BB_SetPageMessage("error", "The 'Total Requests Per Interval' field contains an invalid value.");
			else if ($info["login_interval"] < 1)  BB_SetPageMessage("error", "The 'Login/Recovery Attempts Interval' field contains an invalid value.");
			else if ($info["login_attempts"] < 1)  BB_SetPageMessage("error", "The 'Login/Recovery Attempts Per Interval' field contains an invalid value.");
			else if ($info["two_factor_attempts"] < 1)  BB_SetPageMessage("error", "The 'Two-Factor Authentication Per Login Attempt' field contains an invalid value.");
			else if ($info["register_interval"] < 1)  BB_SetPageMessage("error", "The 'Registrations Interval' field contains an invalid value.");
			else if ($info["register_num"] < 1)  BB_SetPageMessage("error", "The 'Registrations Per Interval' field contains an invalid value.");

			$sso_settings["sso_login"]["modules"]["sso_ratelimit"] = $info;
		}

		public function Config(&$contentopts)
		{
			$info = $this->GetInfo();
			$contentopts["fields"][] = array(
				"title" => "Total Requests Interval",
				"type" => "text",
				"name" => "sso_ratelimit_system_interval",
				"value" => BB_GetValue("sso_ratelimit_system_interval", $info["system_interval"]),
				"desc" => "The interval, in seconds, over which requests to the Generic Login provider will be measured.  Default is 86400 (1 day)."
			);
			$contentopts["fields"][] = array(
				"title" => "Total Requests Per Interval",
				"type" => "text",
				"name" => "sso_ratelimit_system_requests",
				"value" => BB_GetValue("sso_ratelimit_system_requests", $info["system_requests"]),
				"desc" => "The number of requests to the Generic Login provider that may be made within the specified interval above from a single IP address.  This includes AJAX callbacks.  Default is 2880 (one request every 30 seconds)."
			);
			$contentopts["fields"][] = array(
				"title" => "Login/Recovery Attempts Interval",
				"type" => "text",
				"name" => "sso_ratelimit_login_interval",
				"value" => BB_GetValue("sso_ratelimit_login_interval", $info["login_interval"]),
				"desc" => "The interval, in seconds, over which failed login and recovery attempts will be measured.  Default is 900 (15 minutes)."
			);
			$contentopts["fields"][] = array(
				"title" => "Login/Recovery Attempts Per Interval",
				"type" => "text",
				"name" => "sso_ratelimit_login_attempts",
				"value" => BB_GetValue("sso_ratelimit_login_attempts", $info["login_attempts"]),
				"desc" => "The number of failed login and recovery attempts that may be made within the specified interval above from a single IP address.  Default is 20 (slightly more than one attempt per minute)."
			);
			$contentopts["fields"][] = array(
				"title" => "Two-Factor Authentication Per Login Attempt",
				"type" => "text",
				"name" => "sso_ratelimit_two_factor_attempts",
				"value" => BB_GetValue("sso_ratelimit_two_factor_attempts", $info["two_factor_attempts"]),
				"desc" => "The number of failed two-factor authentication attempts that may be made before the user is required to sign in again.  Default is 3."
			);
			$contentopts["fields"][] = array(
				"title" => "Registrations Interval",
				"type" => "text",
				"name" => "sso_ratelimit_register_interval",
				"value" => BB_GetValue("sso_ratelimit_register_interval", $info["register_interval"]),
				"desc" => "The interval, in seconds, over which new registrations will be measured.  Default is 28800 (8 hours)."
			);
			$contentopts["fields"][] = array(
				"title" => "Registrations Per Interval",
				"type" => "text",
				"name" => "sso_ratelimit_register_num",
				"value" => BB_GetValue("sso_ratelimit_register_num", $info["register_num"]),
				"desc" => "The number of new registrations that may be made within the specified interval above from a single IP address.  Default is 1 (up to 3 registrations per day)."
			);
		}

		public function AddIPCacheInfo($displayname)
		{
			global $info, $contentopts;

			if (isset($info["sso_login_modules"]) && isset($info["sso_login_modules"]["sso_ratelimit"]))
			{
				$info2 = $this->GetInfo();
				$num = $info["sso_login_modules"]["sso_ratelimit"]["sysreq"];
				$contentopts["fields"][] = array(
					"title" => BB_Translate("%s - Rate Limit - System Requests", $displayname),
					"type" => "custom",
					"value" => BB_Translate("%d system request" . ($num == 1 ? "" : "s") . " since %s.  Limit %d.", $num, BB_FormatTimestamp("M j, Y @ g:i A", CSDB::ConvertFromDBTime($info["sso_login_modules"]["sso_ratelimit"]["ts"])), $info2["system_requests"]),
				);
				$num = $info["sso_login_modules"]["sso_ratelimit"]["logins"];
				$contentopts["fields"][] = array(
					"title" => BB_Translate("%s - Rate Limit - Login/Recovery Attempts", $displayname),
					"type" => "custom",
					"value" => BB_Translate("%d login/recovery attempt" . ($num == 1 ? "" : "s") . " since %s.  Limit %d.", $num, BB_FormatTimestamp("M j, Y @ g:i A", CSDB::ConvertFromDBTime($info["sso_login_modules"]["sso_ratelimit"]["ts2"])), $info2["login_attempts"]),
				);
				$num = $info["sso_login_modules"]["sso_ratelimit"]["register"];
				$contentopts["fields"][] = array(
					"title" => BB_Translate("%s - Rate Limit - Registrations", $displayname),
					"type" => "custom",
					"value" => BB_Translate("%d registration" . ($num == 1 ? "" : "s") . " since %s.  Limit %d.", $num, BB_FormatTimestamp("M j, Y @ g:i A", CSDB::ConvertFromDBTime($info["sso_login_modules"]["sso_ratelimit"]["ts3"])), $info2["register_num"]),
				);
			}
			else
			{
				$contentopts["fields"][] = array(
					"title" => BB_Translate("%s - Rate Limiting Information", $displayname),
					"type" => "custom",
					"value" => "<i>" . htmlspecialchars(BB_Translate("Undefined (No information found)")) . "</i>"
				);
			}
		}

		private function UpdateIPAddrInfo($incsysreq, $inclogins, $incregister)
		{
			global $sso_ipaddr_info;

			$info = $this->GetInfo();
			if (isset($sso_ipaddr_info["sso_login_modules"]["sso_ratelimit"]))  $result = $sso_ipaddr_info["sso_login_modules"]["sso_ratelimit"];
			else
			{
				$result = array(
					"ts" => CSDB::ConvertToDBTime(time()),
					"sysreq" => 0,
					"ts2" => CSDB::ConvertToDBTime(time()),
					"logins" => 0,
					"ts3" => CSDB::ConvertToDBTime(time()),
					"register" => 0
				);
			}

			// Check expirations and reset if necessary.
			if (CSDB::ConvertFromDBTime($result["ts"]) < time() - $info["system_interval"])
			{
				$result["ts"] = CSDB::ConvertToDBTime(time());
				$result["sysreq"] = 0;
			}
			if (CSDB::ConvertFromDBTime($result["ts2"]) < time() - $info["login_interval"])
			{
				$result["ts2"] = CSDB::ConvertToDBTime(time());
				$result["logins"] = 0;
			}
			if (CSDB::ConvertFromDBTime($result["ts3"]) < time() - $info["register_interval"])
			{
				$result["ts3"] = CSDB::ConvertToDBTime(time());
				$result["register"] = 0;
			}

			// Increment requested.
			if ($incsysreq && $result["sysreq"] < $info["system_requests"])  $result["sysreq"]++;
			if ($inclogins && $result["logins"] < $info["login_attempts"])  $result["logins"]++;
			if ($incregister && $result["register"] < $info["register_num"])  $result["register"]++;

			$sso_ipaddr_info["sso_login_modules"]["sso_ratelimit"] = $result;

			// Save the information.
			SSO_SaveIPAddrInfo();
		}

		public function TwoFactorCheck(&$result, $userinfo)
		{
			global $sso_ipaddr_info;

			if ($userinfo === false)
			{
				$this->UpdateIPAddrInfo(true, false, false);

				$info = $this->GetInfo();
				if ($sso_ipaddr_info["sso_login_modules"]["sso_ratelimit"]["sysreq"] >= $info["system_requests"])  $result["errors"][] = BB_Translate("Request rate limit exceeded.");
			}
		}

		public function TwoFactorFailed(&$result, $userinfo)
		{
			global $sso_session_info, $sso_target_url;

			$info = $this->GetInfo();
			if (!isset($sso_session_info["sso_login_two_factor"]["sso_ratelimit"]))  $sso_session_info["sso_login_two_factor"]["sso_ratelimit"] = 0;
			$sso_session_info["sso_login_two_factor"]["sso_ratelimit"]++;

			if ($sso_session_info["sso_login_two_factor"]["sso_ratelimit"] < $info["two_factor_attempts"])  SSO_SaveSessionInfo();
			else
			{
				unset($sso_session_info["sso_login_two_factor"]);
				SSO_SaveSessionInfo();

				header("Location: " . BB_GetRequestHost() . $sso_target_url . "&sso_msg=two_factor_auth_expired");
				exit();
			}
		}

		public function SignupCheck(&$result, $ajax, $admin)
		{
			global $sso_ipaddr_info;

			if ($admin)  return;

			$this->UpdateIPAddrInfo(true, false, false);

			$info = $this->GetInfo();
			if ($sso_ipaddr_info["sso_login_modules"]["sso_ratelimit"]["sysreq"] >= $info["system_requests"])  $result["errors"][] = BB_Translate("Request rate limit exceeded.");
			else if (!$ajax && $sso_ipaddr_info["sso_login_modules"]["sso_ratelimit"]["register"] >= $info["register_num"])  $result["errors"][] = BB_Translate("Request rate limit exceeded.");
		}

		public function SignupDone($userid, $admin)
		{
			if ($admin)  return;

			$this->UpdateIPAddrInfo(false, false, true);
		}

		public function GenerateSignup($admin)
		{
			if ($admin)  return false;

			$this->UpdateIPAddrInfo(true, false, false);
		}

		public function VerifyCheck(&$result)
		{
			global $sso_ipaddr_info;

			$this->UpdateIPAddrInfo(true, false, false);

			$info = $this->GetInfo();
			if ($sso_ipaddr_info["sso_login_modules"]["sso_ratelimit"]["sysreq"] >= $info["system_requests"])  $result["errors"][] = BB_Translate("Request rate limit exceeded.");
		}

		public function LoginCheck(&$result, $userinfo, $recoveryallowed)
		{
			global $sso_ipaddr_info;

			if ($userinfo === false)
			{
				$this->UpdateIPAddrInfo(true, false, false);

				$info = $this->GetInfo();
				if ($sso_ipaddr_info["sso_login_modules"]["sso_ratelimit"]["sysreq"] >= $info["system_requests"])  $result["errors"][] = BB_Translate("Request rate limit exceeded.");
				else if ($sso_ipaddr_info["sso_login_modules"]["sso_ratelimit"]["logins"] >= $info["login_attempts"])  $result["errors"][] = BB_Translate("Request rate limit exceeded.");
			}
		}

		public function GenerateLogin($messages)
		{
			$this->UpdateIPAddrInfo(true, ($messages !== false && count($messages["errors"]) > 0), false);
		}

		public function RecoveryCheck(&$result, $userinfo)
		{
			$this->LoginCheck($result, $userinfo, false);
		}

		public function GenerateRecovery($messages)
		{
			$this->GenerateLogin($messages);
		}

		public function RecoveryCheck2(&$result, $userinfo)
		{
			$this->LoginCheck($result, $userinfo, false);
		}

		public function GenerateRecovery2($messages)
		{
			$this->GenerateLogin($messages);
		}

		public function UpdateInfoCheck(&$result, $userinfo, $ajax)
		{
			$this->VerifyCheck($result);
		}

		public function GenerateUpdateInfo($userrow, $userinfo)
		{
			$this->GenerateSignup(false);
		}
	}
?>