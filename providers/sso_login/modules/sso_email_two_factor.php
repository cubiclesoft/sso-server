<?php
	// SSO Generic Login Module for Two-Factor Authentication via E-mail.
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	if (!defined("SSO_FILE"))  exit();

	if (isset($sso_settings["sso_login"]["install_type"]) && ($sso_settings["sso_login"]["install_type"] == "email_username" || $sso_settings["sso_login"]["install_type"] == "email"))
	{
		$g_sso_login_modules["sso_email_two_factor"] = array(
			"name" => "E-mail Two-Factor Authentication",
			"desc" => "Adds two-factor authentication for e-mail."
		);
	}

	class sso_login_module_sso_email_two_factor extends sso_login_ModuleBase
	{
		public function DefaultOrder()
		{
			return 200;
		}

		private function GetInfo()
		{
			global $sso_settings;

			$info = $sso_settings["sso_login"]["modules"]["sso_email_two_factor"];
			if (!isset($info["email_from"]))  $info["email_from"] = "";
			if (!isset($info["email_subject"]))  $info["email_subject"] = "";
			if (!isset($info["email_msg"]))  $info["email_msg"] = "";
			if (!isset($info["email_msg_text"]))  $info["email_msg_text"] = "";
			if (!isset($info["window"]))  $info["window"] = 300;
			if (!isset($info["clock_drift"]))  $info["clock_drift"] = 60;

			return $info;
		}

		public function ConfigSave()
		{
			global $sso_settings;

			$info = $this->GetInfo();
			$info["email_from"] = $_REQUEST["sso_email_two_factor_email_from"];
			$info["email_subject"] = trim($_REQUEST["sso_email_two_factor_email_subject"]);
			$info["email_msg"] = $_REQUEST["sso_email_two_factor_email_msg"];
			$info["email_msg_text"] = SMTP::ConvertHTMLToText($_REQUEST["sso_email_two_factor_email_msg"]);
			$info["window"] = (int)$_REQUEST["sso_email_two_factor_window"];
			$info["clock_drift"] = (int)$_REQUEST["sso_email_two_factor_clock_drift"];

			if (stripos($info["email_msg"], "@TWOFACTOR@") === false)  BB_SetPageMessage("error", "The E-mail Two-Factor Authentication 'E-mail Message' field does not contain '@TWOFACTOR@'.");
			else if ($info["window"] < 30 || $info["window"] > 300)  BB_SetPageMessage("error", "The E-mail Two-Factor Authentication 'Window Size' field contains an invalid value.");
			else if ($info["clock_drift"] < 0 || $info["clock_drift"] > $info["window"])  BB_SetPageMessage("error", "The E-mail Two-Factor Authentication 'Window Size' field contains an invalid value.");

			$sso_settings["sso_login"]["modules"]["sso_email_two_factor"] = $info;
		}

		public function Config(&$contentopts)
		{
			$info = $this->GetInfo();
			$contentopts["fields"][] = array(
				"title" => "From Address",
				"type" => "text",
				"name" => "sso_email_two_factor_email_from",
				"value" => BB_GetValue("sso_email_two_factor_email_from", $info["email_from"]),
				"desc" => "The from address for the e-mail message to send to users with the two-factor authentication code.  Leave blank for the server default."
			);
			$contentopts["fields"][] = array(
				"title" => "Subject Line",
				"type" => "text",
				"name" => "sso_email_two_factor_email_subject",
				"value" => BB_GetValue("sso_email_two_factor_email_subject", $info["email_subject"]),
				"desc" => "The subject line for the e-mail message to send to users with their two-factor authentication code."
			);
			$contentopts["fields"][] = array(
				"title" => "HTML Message",
				"type" => "textarea",
				"height" => "300px",
				"name" => "sso_email_two_factor_email_msg",
				"value" => BB_GetValue("sso_email_two_factor_email_msg", $info["email_msg"]),
				"desc" => "The HTML e-mail message to send to users with their two-factor authentication code.  @USERNAME@, @EMAIL@, and @TWOFACTOR@ are special strings that will be replaced with user and system generated values.  @TWOFACTOR@ is required."
			);
			$contentopts["fields"][] = array(
				"title" => "Window Size",
				"type" => "text",
				"name" => "sso_email_two_factor_window",
				"value" => BB_GetValue("sso_email_two_factor_window", $info["window"]),
				"desc" => "The length of time, in seconds, each authentication code is valid for.  Valid range is 30 to 300.  Default is 300."
			);
			$contentopts["fields"][] = array(
				"title" => "Clock Drift",
				"type" => "text",
				"name" => "sso_email_two_factor_clock_drift",
				"value" => BB_GetValue("sso_email_two_factor_clock_drift", $info["clock_drift"]),
				"desc" => "The amount of clock drift, in seconds, to allow for each authentication code.  Valid range is 0 to the window size.  Default is 60."
			);
		}

		public function TwoFactorCheck(&$result, $userinfo)
		{
			if ($userinfo !== false && $userinfo["two_factor_method"] == "sso_email_two_factor")
			{
				$info = $this->GetInfo();
				$code = SSO_FrontendFieldValue("two_factor_code", "");
				$twofactor = sso_login::GetTimeBasedOTP($userinfo["two_factor_key"], time() / $info["window"]);
				$twofactor2 = sso_login::GetTimeBasedOTP($userinfo["two_factor_key"], (time() - $info["clock_drift"]) / $info["window"]);
				$twofactor3 = sso_login::GetTimeBasedOTP($userinfo["two_factor_key"], (time() + $info["clock_drift"]) / $info["window"]);
				if ($code !== $twofactor && $code !== $twofactor2 && $code !== $twofactor3)  $result["errors"][] = BB_Translate("Invalid two-factor authentication code.");
			}
		}

		public function GetTwoFactorName()
		{
			return BB_Translate("E-mail");
		}

		public function SendTwoFactorCode(&$result, $userrow, $userinfo)
		{
			// Send the two-factor authentication e-mail.
			$info = $this->GetInfo();
			$fromaddr = BB_PostTranslate($info["email_from"] != "" ? $info["email_from"] : SSO_SMTP_FROM);
			$subject = BB_Translate($info["email_subject"]);
			$twofactor = sso_login::GetTimeBasedOTP($userinfo["two_factor_key"], time() / $info["window"]);
			$htmlmsg = str_ireplace(array("@USERNAME@", "@EMAIL@", "@TWOFACTOR@"), array(htmlspecialchars($userrow->username), htmlspecialchars($userrow->email), htmlspecialchars($twofactor)), BB_PostTranslate($info["email_msg"]));
			$textmsg = str_ireplace(array("@USERNAME@", "@EMAIL@", "@TWOFACTOR@"), array($userrow->username, $userrow->email, $twofactor), BB_PostTranslate($info["email_msg_text"]));

			$result2 = SSO_SendEmail($fromaddr, $userrow->email, $subject, $htmlmsg, $textmsg);
			if (!$result2["success"])  $result["errors"][] = BB_Translate("Login exists but a fatal error occurred.  Fatal error:  Unable to send two-factor authentication e-mail.  %s", $result["error"]);
		}
	}
?>