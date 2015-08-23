<?php
	// SSO Generic Login Module for Anti-Phishing
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	if (!defined("SSO_FILE"))  exit();

	$g_sso_login_modules["sso_antiphish"] = array(
		"name" => "Anti-Phishing",
		"desc" => "Prevents phishing attacks against the login system.  Adds @ANTIPHISH@ option to e-mails."
	);

	class sso_login_module_sso_antiphish extends sso_login_ModuleBase
	{
		public function DefaultOrder()
		{
			return 50;
		}

		private function GetInfo()
		{
			global $sso_settings;

			$info = $sso_settings["sso_login"]["modules"]["sso_antiphish"];
			if (!isset($info["suggest"]))  $info["suggest"] = false;
			if (!isset($info["cookiekey"]))  $info["cookiekey"] = "";
			if (!isset($info["cookieiv"]))  $info["cookieiv"] = "";
			if (!isset($info["cookiekey2"]))  $info["cookiekey2"] = "";
			if (!isset($info["cookieiv2"]))  $info["cookieiv2"] = "";

			return $info;
		}

		public function ConfigSave()
		{
			global $sso_settings, $sso_rng;

			$info = $this->GetInfo();
			$info["suggest"] = ($_REQUEST["sso_antiphish_suggest"] > 0);
			if ($_REQUEST["sso_antiphish_resetkey"] > 0)
			{
				$info["cookiekey"] = "";
				$info["cookieiv"] = "";
				$info["cookiekey2"] = "";
				$info["cookieiv2"] = "";
			}

			if ($info["cookiekey"] == "" || $info["cookieiv"] == "" || $info["cookiekey2"] == "" || $info["cookieiv2"] == "")
			{
				$info["cookiekey"] = $sso_rng->GenerateToken(56);
				$info["cookieiv"] = $sso_rng->GenerateToken(8);
				$info["cookiekey2"] = $sso_rng->GenerateToken(56);
				$info["cookieiv2"] = $sso_rng->GenerateToken(8);
			}

			$sso_settings["sso_login"]["modules"]["sso_antiphish"] = $info;
		}

		public function Config(&$contentopts)
		{
			$info = $this->GetInfo();
			$contentopts["fields"][] = array(
				"title" => "Suggest Registration Passphrase?",
				"type" => "select",
				"name" => "sso_antiphish_suggest",
				"options" => array(1 => "Yes", 0 => "No"),
				"select" => BB_GetValue("sso_antiphish_suggest", (string)(int)$info["suggest"]),
				"desc" => "Suggest an anti-phishing passphrase during registration."
			);
			$contentopts["fields"][] = array(
				"title" => "Reset Secret Key?",
				"type" => "select",
				"name" => "sso_antiphish_resetkey",
				"options" => array(1 => "Yes", 0 => "No"),
				"select" => BB_GetValue("sso_antiphish_resetkey", "0"),
				"desc" => "Resets the internal key and initialization vector used to encrypt the anti-phishing cookie.  Will cause all existing cookies to become invalid."
			);
		}

		public function CheckEditUserFields(&$userinfo)
		{
			if ($_REQUEST["sso_antiphish"] == "")  BB_SetPageMessage("error", "Please specify an Anti-Phishing Phrase.");
			else  $userinfo["sso_antiphish"] = $_REQUEST["sso_antiphish"];
		}

		public function AddEditUserFields(&$contentopts, &$userinfo)
		{
			$contentopts["fields"][] = array(
				"title" => "Anti-Phishing Phrase",
				"type" => "text",
				"name" => "sso_antiphish",
				"value" => BB_GetValue("sso_antiphish", (isset($userinfo["sso_antiphish"]) ? $userinfo["sso_antiphish"] : ""))
			);
		}

		public function ModifyEmail($userinfo, &$htmlmsg, &$textmsg)
		{
			$phrase = (isset($userinfo["sso_antiphish"]) ? $userinfo["sso_antiphish"] : "");
			$htmlmsg = str_ireplace("@ANTIPHISH@", htmlspecialchars($phrase), $htmlmsg);
			$textmsg = str_ireplace("@ANTIPHISH@", $phrase, $textmsg);
		}

		private function SignupUpdateCheck(&$result, $ajax, $update, $admin)
		{
			$field = ($admin ? BB_GetValue("sso_login_antiphish", false) : SSO_FrontendFieldValue($update ? "sso_login_antiphish_update" : "sso_login_antiphish"));
			if (!$ajax || $field !== false)
			{
				if ($field === false || trim($field) == "")  $result["errors"][] = BB_Translate($admin ? "Anti-phishing phrase field is empty.  Choose something that will be recognized right away if it is wrong or missing." : "Anti-phishing phrase field is empty.  Choose something you will recognize right away if it is wrong or missing.");
				else if (strpos($field, BB_Translate("[Your name]")) !== false)  $result["warnings"][] = BB_Translate("Anti-phishing phrase contains the string '[Your name]'.");
				else
				{
					$result["success"] = BB_Translate("Anti-phishing phrase looks okay.");
				}
			}
		}

		public function SignupCheck(&$result, $ajax, $admin)
		{
			$this->SignupUpdateCheck($result, $ajax, false, $admin);
		}

		private function DisplaySignup($userinfo, $admin)
		{
			$info = $this->GetInfo();
			if ($info["cookiekey"] != "" && $info["cookieiv"] != "" && $info["cookiekey2"] != "" && $info["cookieiv2"] != "")
			{
				if ($userinfo !== false && isset($userinfo["sso_antiphish"]))  $phrase = $userinfo["sso_antiphish"];
				else if (!$info["suggest"])  $phrase = "";
				else
				{
					$phrase = BB_Translate($admin ? "[User's name]" : "[Your name]");
					$phrase .= " " . SSO_GetRandomWord(false, array(BB_Translate("will"), BB_Translate("won't"), BB_Translate("may"), BB_Translate("might"), BB_Translate("could"), BB_Translate("couldn't")));
					$phrase .= " " . SSO_GetRandomWord(false, array(BB_Translate("eat"), BB_Translate("consume"), BB_Translate("beat"), BB_Translate("hurl"), BB_Translate("launch"), BB_Translate("punch")));
					$phrase .= " " . strtoupper(SSO_GetRandomWord()) . SSO_GetRandomWord(false, array(BB_Translate("."), BB_Translate("!")));
					$phrase = trim(preg_replace('/\s+/', " ", $phrase));
				}

				if ($admin)
				{
					$result = array(
						array(
							"title" => "Anti-Phishing Phrase",
							"type" => "text",
							"name" => "sso_login_antiphish",
							"value" => BB_GetValue("sso_login_antiphish", $phrase),
							"desc" => "Sets an anti-phishing phrase for the user."
						)
					);

					return $result;
				}
				else
				{
?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate($userinfo !== false && isset($userinfo["sso_antiphish"]) ? "Your Anti-Phishing Phrase" : "Choose Anti-Phishing Phrase")); ?></div>
				<div class="sso_main_formdata"><input class="sso_main_text sso_login_changehook" type="text" name="<?php echo SSO_FrontendField($userinfo === false ? "sso_login_antiphish" : "sso_login_antiphish_update"); ?>" value="<?php echo htmlspecialchars(SSO_FrontendFieldValue($userinfo === false ? "sso_login_antiphish" : "sso_login_antiphish_update", $phrase)); ?>" /></div>
			</div>
<?php
				}
			}
		}

		public function GenerateSignup($admin)
		{
			return $this->DisplaySignup(false, $admin);
		}

		private function SignupUpdateAddInfo(&$userinfo, $update, $admin)
		{
			global $sso_rng;

			$info = $this->GetInfo();
			if ($info["cookiekey"] != "" && $info["cookieiv"] != "" && $info["cookiekey2"] != "" && $info["cookieiv2"] != "")
			{
				if ($admin)  $userinfo["sso_antiphish"] = $_REQUEST["sso_login_antiphish"];
				else
				{
					$userinfo["sso_antiphish"] = SSO_FrontendFieldValue($update ? "sso_login_antiphish_update" : "sso_login_antiphish");

					// Set the anti-phishing cookie here.
					$data = base64_encode(Blowfish::CreateDataPacket($userinfo["sso_antiphish"], pack("H*", $info["cookiekey"]), array("prefix" => $sso_rng->GenerateString(), "mode" => "CBC", "iv" => pack("H*", $info["cookieiv"]), "key2" => pack("H*", $info["cookiekey2"]), "iv2" => pack("H*", $info["cookieiv2"]), "lightweight" => true)));
					SetCookieFixDomain("sso_l_ap", $data, time() + 365 * 24 * 60 * 60, "", "", BB_IsSSLRequest(), true);
				}
			}
		}

		public function SignupAddInfo(&$userinfo, $admin)
		{
			$this->SignupUpdateAddInfo($userinfo, false, $admin);
		}

		public function GenerateLogin($messages)
		{
			$info = $this->GetInfo();
			if ($info["cookiekey"] != "" && $info["cookieiv"] != "" && $info["cookiekey2"] != "" && $info["cookieiv2"] != "")
			{
				$phrase = "";
				if (isset($_COOKIE["sso_l_ap"]))
				{
					// Decrypt data.
					$phrase = @base64_decode($_COOKIE["sso_l_ap"]);
					if ($phrase !== false)  $phrase = Blowfish::ExtractDataPacket($phrase, pack("H*", $info["cookiekey"]), array("mode" => "CBC", "iv" => pack("H*", $info["cookieiv"]), "key2" => pack("H*", $info["cookiekey2"]), "iv2" => pack("H*", $info["cookieiv2"]), "lightweight" => true));
					if ($phrase === false)  $phrase = "";
				}
?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Anti-Phishing Phrase")); ?></div>
<?php
				if ($phrase != "")
				{
?>
				<div class="sso_main_formdesc"><?php echo htmlspecialchars($phrase); ?></div>
<?php
				}
				else
				{
?>
				<div class="sso_main_formresult"><div class="sso_main_formwarning"><?php echo htmlspecialchars(BB_Translate("No anti-phishing phrase found.")); ?></div></div>
<?php
				}
?>
			</div>
<?php
			}
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
			$this->DisplaySignup($userinfo, false);
		}

		public function LoginAddMap(&$mapinfo, $userrow, &$userinfo, $admin)
		{
			global $sso_rng;

			$info = $this->GetInfo();
			if ($info["cookiekey"] != "" && $info["cookieiv"] != "" && $info["cookiekey2"] != "" && $info["cookieiv2"] != "" && isset($userinfo["sso_antiphish"]))
			{
				// Set the anti-phishing cookie here.
				$data = base64_encode(Blowfish::CreateDataPacket($userinfo["sso_antiphish"], pack("H*", $info["cookiekey"]), array("prefix" => $sso_rng->GenerateString(), "mode" => "CBC", "iv" => pack("H*", $info["cookieiv"]), "key2" => pack("H*", $info["cookiekey2"]), "iv2" => pack("H*", $info["cookieiv2"]), "lightweight" => true)));
				SetCookieFixDomain("sso_l_ap", $data, time() + 365 * 24 * 60 * 60, "", "", BB_IsSSLRequest(), true);
			}
		}
	}
?>