<?php
	// SSO Generic Login Module for Two-Factor Authentication via Twilio-compatible reverse SMS.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	if (!defined("SSO_FILE"))
	{
		// Process incoming webhook requests.
		define("SSO_FILE", 1);
		define("SSO_MODE", "sso_two_factor");

		require_once "../../../config.php";
		require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/str_basics.php";
		require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/sso_functions.php";
		require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/random.php";
		require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/sdk_twilio.php";

		Str::ProcessAllInput();

		// Initialize the global CSPRNG instance.
		$sso_rng = new CSPRNG();

		// Timing attack defense.
		SSO_RandomSleep();

		// Load in $sso_settings and initialize it.
		SSO_LoadSettings();

		// Get the Account SID from the settings.
		if (!isset($sso_settings["sso_login"]["modules"]["sso_twilio_two_factor"]))  exit();
		$info = $sso_settings["sso_login"]["modules"]["sso_twilio_two_factor"];
		if (!isset($info["account_sid"]) || $info["account_sid"] === "")  exit();

		$twilio = new TwilioSDK();
		$twilio->SetAccessInfo($info["account_sid"], $info["account_token"]);

		// Secure the request.
		$twilio->ValidateWebhookRequest($info["account_token"] !== "");

		// Don't send a response message.
		$twilio->StartXMLResponse();
		$twilio->EndXMLResponse();

		if (!isset($_REQUEST["From"]) || !isset($_REQUEST["Body"]) || $_REQUEST["Body"] === "")  exit();

		$phone = preg_replace('/[^0-9]/', "", $_REQUEST["From"]);

		// Write the message body to disk.
		$dir = sys_get_temp_dir();
		$dir = str_replace("\\", "/", $dir);
		if (substr($dir, -1) !== "/")  $dir .= "/";
		$filename = $dir . "sso_login_rev_sms_2fa_" . $phone . ".dat";

		file_put_contents($filename, $_REQUEST["Body"]);

		exit();
	}

	$g_sso_login_modules["sso_twilio_two_factor"] = array(
		"name" => "Reverse SMS Two-Factor Authentication",
		"desc" => "Adds reverse SMS two-factor authentication for Twilio webhook-compatible services (SignalWire, Twilio, etc)."
	);

	class sso_login_module_sso_twilio_two_factor extends sso_login_ModuleBase
	{
		public function DefaultOrder()
		{
			return 35;
		}

		private function GetInfo()
		{
			global $sso_settings;

			$info = $sso_settings["sso_login"]["modules"]["sso_twilio_two_factor"];
			if (!isset($info["account_sid"]))  $info["account_sid"] = "";
			if (!isset($info["account_token"]))  $info["account_token"] = "";
			if (!isset($info["phone"]))  $info["phone"] = "";

			return $info;
		}

		public function ConfigSave()
		{
			global $sso_settings;

			$info = $this->GetInfo();
			$info["account_sid"] = $_REQUEST["sso_twilio_two_factor_account_sid"];
			$info["account_token"] = $_REQUEST["sso_twilio_two_factor_account_token"];
			$info["phone"] = $_REQUEST["sso_twilio_two_factor_phone"];

			$sso_settings["sso_login"]["modules"]["sso_twilio_two_factor"] = $info;
		}

		public function Config(&$contentopts)
		{
			$info = $this->GetInfo();
			$contentopts["fields"][] = array(
				"title" => "SMS Webhook URL",
				"type" => "static",
				"value" => SSO_LOGIN_URL . SSO_PROVIDER_PATH . "/sso_login/modules/sso_twilio_two_factor.php",
				"desc" => "The URL to set for the SMS webhook in the Twilio phone number settings."
			);
			$contentopts["fields"][] = array(
				"title" => "Account SID",
				"type" => "text",
				"name" => "sso_twilio_two_factor_account_sid",
				"value" => BB_GetValue("sso_twilio_two_factor_account_sid", $info["account_sid"]),
				"desc" => "The account SID.  Used for webhook validation."
			);
			$contentopts["fields"][] = array(
				"title" => "Account Token",
				"type" => "text",
				"name" => "sso_twilio_two_factor_account_token",
				"value" => BB_GetValue("sso_twilio_two_factor_account_token", $info["account_token"]),
				"desc" => "The account token/secret.  Used for optional webhook signature validation."
			);
			$contentopts["fields"][] = array(
				"title" => "Phone Number",
				"type" => "text",
				"name" => "sso_twilio_two_factor_phone",
				"value" => BB_GetValue("sso_twilio_two_factor_phone", $info["phone"]),
				"desc" => "The Twilio SMS phone number to display to the user."
			);
		}

		public function TwoFactorCheck(&$result, $userinfo)
		{
			global $sso_session_info;

			if ($userinfo !== false && $userinfo["two_factor_method"] == "sso_twilio_two_factor")
			{
				$phone = preg_replace('/[^0-9]/', "", $userinfo["sso_twilio_two_factor_phone"]);

				// Load message body from disk.
				$dir = sys_get_temp_dir();
				$dir = str_replace("\\", "/", $dir);
				if (substr($dir, -1) !== "/")  $dir .= "/";
				$filename = $dir . "sso_login_rev_sms_2fa_" . $phone . ".dat";

				$maxts = time() + 15;
				do
				{
					$data = @file_get_contents($filename);
					if ($data === $sso_session_info["two_factor_code"])
					{
						@unlink($filename);

						return;
					}
					else if ($data !== false && filemtime($filename) < time() - 30)
					{
						$result["errors"][] = BB_Translate("Invalid two-factor authentication code.");

						return;
					}

					usleep(250000);
				} while (time() < $maxts);

				$result["errors"][] = BB_Translate("Two-factor authentication code not received.");
			}
		}

		private function SignupUpdateCheck(&$result, $ajax, $update, $admin)
		{
			$method = ($admin ? BB_GetValue("two_factor_method", false) : SSO_FrontendFieldValue($update ? "update_two_factor_method" : "two_factor_method"));
			if (!$ajax && $method !== "sso_twilio_two_factor")  return;

			$field = ($admin ? BB_GetValue("sso_login_twilio_two_factor_phone", false) : SSO_FrontendFieldValue($update ? "sso_login_twilio_two_factor_phone_update" : "sso_login_twilio_two_factor_phone"));
			if (!$ajax || $field !== false)
			{
				if ($field !== false && strlen(preg_replace('/[^0-9]/', "", $field)) > 14)  $result["errors"][] = BB_Translate("Phone number is too long.");
				else if ($field !== false && strlen(preg_replace('/[^0-9]/', "", $field)) < 11)  $result["warnings"][] = BB_Translate("Phone number might not be valid.  Did you include country code + area code?");
				else if (!$ajax && ($field === false || trim($field) == ""))  $result["errors"][] = BB_Translate("Enter a phone number for SMS two-factor authentication.");
				else  $result["success"] = BB_Translate("Phone number looks okay.");
			}
		}

		public function SignupCheck(&$result, $ajax, $admin)
		{
			$this->SignupUpdateCheck($result, $ajax, false, $admin);
		}

		private function DisplaySignup($userrow, $userinfo, $admin)
		{
			global $sso_target_url, $sso_session_info;

			$info = $this->GetInfo();
			if ($admin)
			{
				$result = array(
					array(
						"title" => "SMS Two Factor Phone",
						"type" => "text",
						"name" => "sso_login_twilio_two_factor_phone",
						"value" => ($userinfo !== false ? $userinfo["sso_twilio_two_factor_phone"] : ""),
						"desc" => "The phone number to use for SMS two-factor authentication."
					)
				);

				return $result;
			}
			else
			{
?>
			<div class="sso_twilio_two_factor_wrap">
				<div class="sso_main_formitem">
					<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Your Phone Number")); ?></div>
					<div class="sso_main_formdata"><input class="sso_main_text sso_login_changehook" type="text" name="<?php echo SSO_FrontendField($userinfo === false ? "sso_login_twilio_two_factor_phone" : "sso_login_twilio_two_factor_phone_update"); ?>" value="<?php echo htmlspecialchars(SSO_FrontendFieldValue(($userinfo === false ? "sso_login_twilio_two_factor_phone" : "sso_login_twilio_two_factor_phone_update"), "")); ?>" /></div>
				</div>
			</div>
			<script type="text/javascript">
			function SSO_TwilioTwoFactor_Update()
			{
				if (jQuery('.sso_login_changehook_two_factor').val() == 'sso_twilio_two_factor')  jQuery('.sso_twilio_two_factor_wrap').show();
				else  jQuery('.sso_twilio_two_factor_wrap').hide();
			}

			jQuery(function() {
				jQuery('.sso_login_changehook_two_factor').change(SSO_TwilioTwoFactor_Update);
				SSO_TwilioTwoFactor_Update();
			});
			</script>
<?php
			}
		}

		public function GenerateSignup($admin)
		{
			return $this->DisplaySignup(false, false, $admin);
		}

		private function SignupUpdateAddInfo(&$userinfo, $update, $admin)
		{
			$info = $this->GetInfo();
			if ($info["account_sid"] != "")
			{
				if ($admin)  $userinfo["sso_twilio_two_factor_phone"] = $_REQUEST["sso_login_twilio_two_factor_phone"];
				else  $userinfo["sso_twilio_two_factor_phone"] = SSO_FrontendFieldValue(($update ? "sso_login_twilio_two_factor_phone_update" : "sso_login_twilio_two_factor_phone"), "");
			}
		}

		public function SignupAddInfo(&$userinfo, $admin)
		{
			$this->SignupUpdateAddInfo($userinfo, false, $admin);
		}

		public function GetTwoFactorName()
		{
			return BB_Translate("SMS");
		}

		public function UpdateInfoCheck(&$result, $userinfo, $ajax)
		{
			if ($userinfo !== false)  $this->SignupUpdateCheck($result, $ajax, true, false);
		}

		public function UpdateAddInfo(&$userinfo)
		{
			$this->SignupUpdateAddInfo($userinfo, true, false);
		}

		public function GenerateUpdateInfo($userrow, $userinfo)
		{
			$this->DisplaySignup($userrow, $userinfo, false);
		}

		public function GenerateTwoFactorField($userinfo)
		{
			global $sso_rng, $sso_session_info;

			$info = $this->GetInfo();
			if ($info["account_sid"] != "")
			{
				if (!isset($sso_session_info["two_factor_code"]))
				{
					$chrmap = "0123456789";
					$sso_session_info["two_factor_code"] = "";
					for ($x = 0; $x < 6; $x++)  $sso_session_info["two_factor_code"] .= $chrmap[$sso_rng->GetInt(0, 9)];

					SSO_SaveSessionInfo();
				}

?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Send Two-Factor Authentication Code")); ?></div>
				<div class="sso_main_formdesc"><?php echo htmlspecialchars(BB_Translate("Send %s to %s via SMS from your registered phone and then use the 'Sign in' button below.", $sso_session_info["two_factor_code"], $info["phone"])); ?></div>
			</div>
<?php
			}

			return true;
		}
	}
?>