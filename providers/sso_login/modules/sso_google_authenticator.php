<?php
	// SSO Generic Login Module for Two-Factor Authentication via Google Authenticator.
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	if (!defined("SSO_FILE"))  exit();

	$g_sso_login_modules["sso_google_authenticator"] = array(
		"name" => "Google Authenticator",
		"desc" => "Adds two-factor authentication that is compatible with Google Authenticator (IETF RFC 6238)."
	);

	class sso_login_module_sso_google_authenticator extends sso_login_ModuleBase
	{
		public function DefaultOrder()
		{
			return 30;
		}

		private function GetInfo()
		{
			global $sso_settings;

			$info = $sso_settings["sso_login"]["modules"]["sso_google_authenticator"];
			if (!isset($info["generate_qr_codes"]))  $info["generate_qr_codes"] = true;
			if (!isset($info["clock_drift"]))  $info["clock_drift"] = 5;

			return $info;
		}

		public function ConfigSave()
		{
			global $sso_settings;

			$info = $this->GetInfo();
			$info["generate_qr_codes"] = ($_REQUEST["sso_google_authenticator_generate_qr_codes"] > 0);
			$info["clock_drift"] = (int)$_REQUEST["sso_google_authenticator_clock_drift"];

			if ($info["clock_drift"] < 0 || $info["clock_drift"] > 30)  BB_SetPageMessage("error", "The Google Authenticator 'Clock Drift' field contains an invalid value.");

			$sso_settings["sso_login"]["modules"]["sso_google_authenticator"] = $info;
		}

		public function Config(&$contentopts)
		{
			$info = $this->GetInfo();
			$contentopts["fields"][] = array(
				"title" => "Generate QR Codes",
				"type" => "select",
				"name" => "sso_google_authenticator_generate_qr_codes",
				"options" => array(1 => "Yes", 0 => "No"),
				"select" => BB_GetValue("sso_google_authenticator_generate_qr_codes", (string)(int)$info["generate_qr_codes"]),
				"desc" => "Displays a Google Authenticator compatible QR code to the user during sign up and account recovery."
			);
			$contentopts["fields"][] = array(
				"title" => "Clock Drift",
				"type" => "text",
				"name" => "sso_google_authenticator_clock_drift",
				"value" => BB_GetValue("sso_google_authenticator_clock_drift", (string)(int)$info["clock_drift"]),
				"desc" => "The amount of clock drift, in seconds, to allow for each authentication code.  Range is 0 to 30.  Default is 5."
			);
		}

		public function TwoFactorCheck(&$result, $userinfo)
		{
			if ($userinfo !== false && $userinfo["two_factor_method"] == "sso_google_authenticator")
			{
				$info = $this->GetInfo();
				$code = SSO_FrontendFieldValue("two_factor_code", "");
				$twofactor = sso_login::GetTimeBasedOTP($userinfo["two_factor_key"], time() / 30);
				$twofactor2 = sso_login::GetTimeBasedOTP($userinfo["two_factor_key"], (time() - $info["clock_drift"]) / 30);
				$twofactor3 = sso_login::GetTimeBasedOTP($userinfo["two_factor_key"], (time() + $info["clock_drift"]) / 30);
				if ($code !== $twofactor && $code !== $twofactor2 && $code !== $twofactor3)  $result["errors"][] = BB_Translate("Invalid two-factor authentication code.");
			}
		}

		private function SignupUpdateCheck(&$result, $update, $userrow)
		{
			global $sso_target_url, $sso_session_info;

			// Generate the QR code.
			$info = $this->GetInfo();
			if ($info["generate_qr_codes"])
			{
				if (isset($_REQUEST["sso_google_authenticator_qr_u"]) && isset($_REQUEST["sso_google_authenticator_qr_h"]) && isset($_REQUEST["sso_google_authenticator_qr_k"]))
				{
					require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/phpqrcode.php";

					$url = "otpauth://totp/" . urlencode($_REQUEST["sso_google_authenticator_qr_u"]) . "@" . urlencode($_REQUEST["sso_google_authenticator_qr_h"]) . "?secret=" . $_REQUEST["sso_google_authenticator_qr_k"];

					QRcode::png($url, false, QR_ECLEVEL_Q, 4);
				}
				else
				{
					if ($update)  $username = SSO_FrontendFieldValue("update_username", ($userrow !== false ? $userrow->username : ""));
					else  $username = SSO_FrontendFieldValue("username", "");
					if ($username == "")
					{
						if ($update)  $email = SSO_FrontendFieldValue("update_email", ($userrow !== false ? $userrow->email : ""));
						else  $email = SSO_FrontendFieldValue("email", "");
						if ($email != "")
						{
							$pos = strpos($email, "@");
							if ($pos !== false)  $username = substr($email, 0, $pos);
						}
					}

					require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/http.php";

					$host = BB_GetRequestHost();
					$result2 = HTTP::ExtractURL($host);
					$host = $result2["host"];

					$key = (isset($sso_session_info["sso_login_two_factor_key"]) ? $sso_session_info["sso_login_two_factor_key"] : "");

					if ($username != "" && $host != "" && $key != "")
					{
						$url = $sso_target_url . "&sso_login_action=" . ($update ? "update_info&sso_v=" . urlencode($_REQUEST["sso_v"]) : "signup_check") . "&sso_ajax=1&sso_google_authenticator_qr_u=" . urlencode($username) . "&sso_google_authenticator_qr_h=" . urlencode($host) . "&sso_google_authenticator_qr_k=" . urlencode($key);

?>
<script type="text/javascript">
jQuery('.sso_google_authenticator_qrcode').html('<div class="sso_main_formitem"><div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Google Authenticator QR Code")); ?></div><div class="sso_main_formdata"><div class="sso_main_static"><img src="<?php echo htmlspecialchars($url); ?>" alt="<?php echo htmlspecialchars(BB_Translate("Google Authenticator QR Code")); ?>" /></div></div>');
</script>
<?php
					}
					else if (SSO_FrontendFieldValue($update ? "update_username" : "username") !== false || SSO_FrontendFieldValue($update ? "update_email" : "email") !== false)
					{
?>
<script type="text/javascript">
jQuery('.sso_google_authenticator_qrcode').html('');
</script>
<?php
					}
				}
			}
		}

		public function SignupCheck(&$result, $ajax, $admin)
		{
			if ($ajax)  $this->SignupUpdateCheck($result, false, false);
		}

		private function DisplaySignup($userrow, $userinfo, $admin)
		{
			global $sso_target_url, $sso_session_info;

			$info = $this->GetInfo();
			if ($admin)
			{
				$result = array(
					array(
						"title" => "Google Authenticator Key",
						"type" => "static",
						"value" => $_REQUEST["two_factor_key"],
						"desc" => "The manual key to use with Google Authenticator and compatible applications."
					)
				);

				return $result;
			}
			else
			{
?>
			<div class="sso_google_authenticator_wrap">
				<div class="sso_main_formitem">
					<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Google Authenticator Key")); ?></div>
					<div class="sso_main_formdata"><div class="sso_main_static"><?php echo htmlspecialchars($sso_session_info["sso_login_two_factor_key"]); ?></div></div>
				</div>
<?php
				if ($info["generate_qr_codes"])
				{
?>
				<div class="sso_google_authenticator_qrcode"></div>
<?php
					$result = array();
					$this->SignupUpdateCheck($result, ($userinfo !== false), $userrow);
				}
?>
			</div>
			<script type="text/javascript">
			function SSO_GoogleAuthenticator_Update()
			{
				if (jQuery('.sso_login_changehook_two_factor').val() == 'sso_google_authenticator')  jQuery('.sso_google_authenticator_wrap').show();
				else  jQuery('.sso_google_authenticator_wrap').hide();
			}

			jQuery(function() {
				jQuery('.sso_login_changehook_two_factor').change(SSO_GoogleAuthenticator_Update);
				SSO_GoogleAuthenticator_Update();
			});
			</script>
<?php
			}
		}

		public function GenerateSignup($admin)
		{
			return $this->DisplaySignup(false, false, $admin);
		}

		public function GetTwoFactorName()
		{
			return BB_Translate("Google Authenticator");
		}

		public function UpdateInfoCheck(&$result, $userinfo, $ajax)
		{
			if ($ajax && $userinfo !== false)  $this->SignupUpdateCheck($result, true, false);
		}

		public function GenerateUpdateInfo($userrow, $userinfo)
		{
			$this->DisplaySignup($userrow, $userinfo, false);
		}
	}
?>