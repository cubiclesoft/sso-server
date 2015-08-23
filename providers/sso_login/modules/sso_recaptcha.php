<?php
	// SSO Generic Login Module for reCAPTCHA support
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	if (!defined("SSO_FILE"))  exit();

	$g_sso_login_modules["sso_recaptcha"] = array(
		"name" => "reCAPTCHA",
		"desc" => "Adds reCAPTCHA support for registration and/or logins."
	);

	class sso_login_module_sso_recaptcha extends sso_login_ModuleBase
	{
		public function DefaultOrder()
		{
			return 150;
		}

		private function GetInfo()
		{
			global $sso_settings;

			$info = $sso_settings["sso_login"]["modules"]["sso_recaptcha"];
			if (!isset($info["publickey"]))  $info["publickey"] = "";
			if (!isset($info["privatekey"]))  $info["privatekey"] = "";
			if (!isset($info["theme2"]))  $info["theme2"] = "light";
			if (!isset($info["register"]))  $info["register"] = true;
			if (!isset($info["login_interval"]))  $info["login_interval"] = 15 * 60;
			if (!isset($info["login_attempts"]))  $info["login_attempts"] = 3;
			if (!isset($info["remember"]))  $info["remember"] = true;

			return $info;
		}

		public function ConfigSave()
		{
			global $sso_settings;

			$info = $this->GetInfo();
			$info["publickey"] = trim($_REQUEST["sso_recaptcha_publickey"]);
			$info["privatekey"] = trim($_REQUEST["sso_recaptcha_privatekey"]);
			$info["register"] = ($_REQUEST["sso_recaptcha_register"] > 0);
			$info["theme2"] = ($_REQUEST["sso_recaptcha_theme"] === "dark" ? "dark" : "light");
			$info["login_interval"] = (int)$_REQUEST["sso_recaptcha_login_interval"];
			$info["login_attempts"] = (int)$_REQUEST["sso_recaptcha_login_attempts"];
			$info["remember"] = ($_REQUEST["sso_recaptcha_remember"] > 0);

			if ($info["publickey"] == "")  BB_SetPageMessage("info", "The 'reCAPTCHA Public/Site Key' field is empty.");
			else if ($info["privatekey"] == "")  BB_SetPageMessage("info", "The 'reCAPTCHA Private/Secret Key' field is empty.");

			if ($info["login_interval"] < 1)  BB_SetPageMessage("error", "The 'reCAPTCHA Login/Recovery Attempts Interval' field contains an invalid value.");
			else if ($info["login_attempts"] < 1)  BB_SetPageMessage("error", "The 'reCAPTCHA Login/Recovery Attempts Per Interval' field contains an invalid value.");

			$sso_settings["sso_login"]["modules"]["sso_recaptcha"] = $info;
		}

		public function Config(&$contentopts)
		{
			$info = $this->GetInfo();
			$contentopts["fields"][] = array(
				"title" => "reCAPTCHA Public/Site Key",
				"type" => "text",
				"name" => "sso_recaptcha_publickey",
				"value" => BB_GetValue("sso_recaptcha_publickey", $info["publickey"]),
				"htmldesc" => "You get a public/site key when you <a href=\"https://www.google.com/recaptcha/admin/list\" target=\"_blank\">sign up for the reCAPTCHA service</a>.  reCAPTCHA will not work without a public/site key!"
			);
			$contentopts["fields"][] = array(
				"title" => "reCAPTCHA Private/Secret Key",
				"type" => "text",
				"name" => "sso_recaptcha_privatekey",
				"value" => BB_GetValue("sso_recaptcha_privatekey", $info["privatekey"]),
				"htmldesc" => "You get a private/secret key when you <a href=\"https://www.google.com/recaptcha/admin/list\" target=\"_blank\">sign up for the reCAPTCHA service</a>.  reCAPTCHA will not work without a private/secret key!"
			);
			$contentopts["fields"][] = array(
				"title" => "reCAPTCHA Theme",
				"type" => "select",
				"name" => "sso_recaptcha_theme",
				"options" => array("light" => "Light", "dark" => "Dark"),
				"select" => BB_GetValue("sso_recaptcha_theme", $info["theme2"]),
				"desc" => "Select the theme to use.  The default theme works well with most web designs."
			);
			$contentopts["fields"][] = array(
				"title" => "Registration reCAPTCHA?",
				"type" => "select",
				"name" => "sso_recaptcha_register",
				"options" => array(1 => "Yes", 0 => "No"),
				"select" => BB_GetValue("sso_recaptcha_register", (string)(int)$info["register"]),
				"desc" => "Require reCAPTCHA entry during registration."
			);
			$contentopts["fields"][] = array(
				"title" => "reCAPTCHA Login/Recovery Attempts Interval",
				"type" => "text",
				"name" => "sso_recaptcha_login_interval",
				"value" => BB_GetValue("sso_recaptcha_login_interval", $info["login_interval"]),
				"desc" => "The interval, in seconds, over which failed login and recovery attempts will be measured.  Default is 900 (15 minutes)."
			);
			$contentopts["fields"][] = array(
				"title" => "reCAPTCHA Login/Recovery Attempts Per Interval",
				"type" => "text",
				"name" => "sso_recaptcha_login_attempts",
				"value" => BB_GetValue("sso_recaptcha_login_attempts", $info["login_attempts"]),
				"desc" => "The number of failed login and recovery attempts that may be made within the specified interval above from a single IP address before reCAPTCHA is required.  Default is 3."
			);
			$contentopts["fields"][] = array(
				"title" => "Remember Correct reCAPTCHAs?",
				"type" => "select",
				"name" => "sso_recaptcha_remember",
				"options" => array(1 => "Yes", 0 => "No"),
				"select" => BB_GetValue("sso_recaptcha_remember", (string)(int)$info["remember"]),
				"desc" => "Remembers a correct reCAPTCHA entry on a per-session basis.  Once the CAPTCHA is solved, it won't be displayed to the user again for that session."
			);
		}

		public function AddIPCacheInfo($displayname)
		{
			global $info, $contentopts;

			if (isset($info["sso_login_modules"]) && isset($info["sso_login_modules"]["sso_recaptcha"]))
			{
				$info2 = $this->GetInfo();
				$num = $info["sso_login_modules"]["sso_recaptcha"]["logins"];
				$contentopts["fields"][] = array(
					"title" => BB_Translate("%s - reCAPTCHA - Login/Recovery Attempts", $displayname),
					"type" => "custom",
					"value" => BB_Translate("%d login/recovery attempt" . ($num == 1 ? "" : "s") . " since %s.  Limit %d before showing reCAPTCHA.", $num, BB_FormatTimestamp("M j, Y @ g:i A", CSDB::ConvertFromDBTime($info["sso_login_modules"]["sso_ratelimit"]["ts"])), $info2["login_attempts"]),
				);
			}
			else
			{
				$contentopts["fields"][] = array(
					"title" => BB_Translate("%s - reCAPTCHA Information", $displayname),
					"type" => "custom",
					"value" => "<i>" . htmlspecialchars(BB_Translate("Undefined (No information found)")) . "</i>"
				);
			}
		}

		private function ProcessVerification(&$result, $info)
		{
			global $sso_ipaddr, $sso_session_info;

			if ($info["publickey"] != "" && $info["privatekey"] != "" && (!$info["remember"] || !isset($sso_session_info["sso_recaptcha_passed"]) || !$sso_session_info["sso_recaptcha_passed"]))
			{
				if (!isset($_REQUEST["g-recaptcha-response"]))  $result["errors"][] = BB_Translate("Human Verification information is missing.");
				else
				{
					require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/http.php";
					require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/web_browser.php";

					$url = "https://www.google.com/recaptcha/api/siteverify?secret=" . urlencode($info["privatekey"]) . "&response=" . urlencode($_REQUEST["g-recaptcha-response"]) . "&remoteip=" . urlencode($sso_ipaddr["ipv6"]);

					$web = new WebBrowser();
					$result2 = $web->Process($url);

					if (!$result2["success"])  $result["errors"][] = BB_Translate("Human Verification failed.  Error retrieving response from remote service.", $result2["error"]);
					else if ($result2["response"]["code"] != 200)  $result["errors"][] = BB_Translate("Human Verification failed.  The remote service responded with:  %s", $result2["response"]["code"] . " " . $result2["response"]["meaning"]);
					else
					{
						$data = @json_decode($result2["body"], true);
						if ($data === false)  $result["errors"][] = BB_Translate("Human Verification failed.  Unable to decode the response from the remote service.");
						else if (!isset($data["success"]))  $result["errors"][] = BB_Translate("Incorrect Human Verification entered.  Try again.  (Code:  %s)", BB_Translate($data["error"]));
						else if ($info["remember"])
						{
							$sso_session_info["sso_recaptcha_passed"] = true;

							if (!SSO_SaveSessionInfo())
							{
								$result["errors"][] = BB_Translate("Unable to save session information.");

								return;
							}
						}
					}
				}
			}
		}

		public function SignupCheck(&$result, $ajax, $admin)
		{
			if ($admin)  return;

			if (!$ajax)
			{
				$info = $this->GetInfo();
				if ($info["register"])  $this->ProcessVerification($result, $info);
			}
		}

		private function DisplayreCAPTCHA($info)
		{
			global $sso_session_info;

			if ($info["publickey"] != "" && $info["privatekey"] != "" && (!$info["remember"] || !isset($sso_session_info["sso_recaptcha_passed"]) || !$sso_session_info["sso_recaptcha_passed"]))
			{
?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Human Verification")); ?></div>
				<script src="https://www.google.com/recaptcha/api.js"></script>
				<div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($info["publickey"]); ?>" data-theme="<?php echo htmlspecialchars($info["theme2"]); ?>"></div>
				<noscript><div class="sso_main_formdesc"><?php echo htmlspecialchars(BB_Translate("You must enable Javascript to use this page.")); ?></div></noscript>
			</div>
<?php
			}
		}

		public function GenerateSignup($admin)
		{
			if ($admin)  return false;

			$info = $this->GetInfo();
			if ($info["register"])  $this->DisplayreCAPTCHA($info);
		}

		private function UpdateIPAddrInfo($inclogins)
		{
			global $sso_ipaddr_info;

			$info = $this->GetInfo();
			if (isset($sso_ipaddr_info["sso_login_modules"]["sso_recaptcha"]))  $result = $sso_ipaddr_info["sso_login_modules"]["sso_recaptcha"];
			else
			{
				$result = array(
					"ts" => CSDB::ConvertToDBTime(time()),
					"logins" => 0
				);
			}

			// Check expirations and reset if necessary.
			if (CSDB::ConvertFromDBTime($result["ts"]) < time() - $info["login_interval"])
			{
				$result["ts"] = CSDB::ConvertToDBTime(time());
				$result["logins"] = 0;
			}

			// Increment requested.
			if ($inclogins && $result["logins"] < $info["login_attempts"])  $result["logins"]++;

			$sso_ipaddr_info["sso_login_modules"]["sso_recaptcha"] = $result;

			// Save the information.
			SSO_SaveIPAddrInfo();
		}

		public function LoginCheck(&$result, $userinfo, $recoveryallowed)
		{
			global $sso_ipaddr_info;

			if ($userinfo === false)
			{
				$this->UpdateIPAddrInfo(false);

				$info = $this->GetInfo();
				if ($sso_ipaddr_info["sso_login_modules"]["sso_recaptcha"]["logins"] >= $info["login_attempts"])
				{
					$this->ProcessVerification($result, $info);
				}
			}
		}

		public function GenerateLogin($messages)
		{
			global $sso_ipaddr_info;

			$this->UpdateIPAddrInfo($messages !== false && count($messages["errors"]) > 0);

			$info = $this->GetInfo();
			if ($sso_ipaddr_info["sso_login_modules"]["sso_recaptcha"]["logins"] >= $info["login_attempts"])
			{
				$this->DisplayreCAPTCHA($info);
			}
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
	}
?>