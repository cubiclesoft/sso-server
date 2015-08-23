<?php
	// SSO Generic Login Module for Terms of Service/Privacy Policy
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	if (!defined("SSO_FILE"))  exit();

	$g_sso_login_modules["sso_tos"] = array(
		"name" => "Terms of Service/Privacy Policy",
		"desc" => "Adds a standard terms of service and/or privacy policy checkbox during registration."
	);

	class sso_login_module_sso_tos extends sso_login_ModuleBase
	{
		public function DefaultOrder()
		{
			return 200;
		}

		private function GetInfo()
		{
			global $sso_settings;

			$info = $sso_settings["sso_login"]["modules"]["sso_tos"];
			if (!isset($info["terms"]))  $info["terms"] = "";
			if (!isset($info["privacy"]))  $info["privacy"] = "";

			return $info;
		}

		public function ConfigSave()
		{
			global $sso_settings;

			$info = $this->GetInfo();
			$info["terms"] = trim($_REQUEST["sso_tos_terms"]);
			$info["privacy"] = trim($_REQUEST["sso_tos_privacy"]);

			$sso_settings["sso_login"]["modules"]["sso_tos"] = $info;
		}

		public function Config(&$contentopts)
		{
			$info = $this->GetInfo();
			$contentopts["fields"][] = array(
				"title" => "Terms Of Service",
				"type" => "textarea",
				"height" => "300px",
				"name" => "sso_tos_terms",
				"value" => BB_GetValue("sso_tos_terms", $info["terms"]),
				"desc" => "The URL of your terms of service or the document's text itself."
			);
			$contentopts["fields"][] = array(
				"title" => "Privacy Policy",
				"type" => "textarea",
				"height" => "300px",
				"name" => "sso_tos_privacy",
				"value" => BB_GetValue("sso_tos_privacy", $info["privacy"]),
				"desc" => "The URL of your privacy policy or the document's text itself."
			);
		}

		public function SignupCheck(&$result, $ajax, $admin)
		{
			if ($admin)  return;

			if (!$ajax)
			{
				$info = $this->GetInfo();
				if ($info["terms"] != "" || $info["privacy"] != "")
				{
					$field = SSO_FrontendFieldValue("sso_login_tos");
					if ($field != "yes")
					{
						$terms = Str::ReplaceNewlines("\n", trim($info["terms"]));
						$privacy = Str::ReplaceNewlines("\n", trim($info["privacy"]));
						if ($terms != "" && $privacy != "")  $result["errors"][] = BB_Translate("Agree to the Terms of Service and Privacy Policy to continue.");
						else if ($terms != "")  $result["errors"][] = BB_Translate("Agree to the Terms of Service to continue.");
						else  $result["errors"][] = BB_Translate("Agree to the Privacy Policy to continue.");
					}
				}
			}
		}

		public function GenerateSignup($admin)
		{
			if ($admin)  return false;

			$info = $this->GetInfo();
			if ($info["terms"] != "" || $info["privacy"] != "")
			{
				$terms = Str::ReplaceNewlines("\n", trim($info["terms"]));
				if ($terms != "")
				{
					if (strpos($terms, "\n") === false && (strtolower(substr($terms, 0, 7)) == "http://" || strtolower(substr($terms, 0, 8)) == "https://"))
					{
						$termsurl = "<a href=\"" . htmlspecialchars($terms) . "\" target=\"_blank\">" . htmlspecialchars(BB_Translate("Terms of Service")) . "</a>";
					}
					else
					{
						$termsurl = htmlspecialchars(BB_Translate("Terms of Service"));
?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Terms of Service")); ?></div>
				<div class="sso_main_formdata"><textarea class="sso_main_textarea"><?php echo htmlspecialchars($terms); ?></textarea></div>
			</div>
<?php
					}
				}

				$privacy = Str::ReplaceNewlines("\n", trim($info["privacy"]));
				if ($privacy != "")
				{
					if (strpos($privacy, "\n") === false && (strtolower(substr($privacy, 0, 7)) == "http://" || strtolower(substr($privacy, 0, 8)) == "https://"))
					{
						$privacyurl = "<a href=\"" . htmlspecialchars($privacy) . "\" target=\"_blank\">" . htmlspecialchars(BB_Translate("Privacy Policy")) . "</a>";
					}
					else
					{
						$privacyurl = htmlspecialchars(BB_Translate("Privacy Policy"));
?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Privacy Policy")); ?></div>
				<div class="sso_main_formdata"><textarea class="sso_main_textarea"><?php echo htmlspecialchars($privacy); ?></textarea></div>
			</div>
<?php
					}
				}

				if ($terms != "" && $privacy != "")  $display = BB_Translate("I agree to the %s and %s.", $termsurl, $privacyurl);
				else if ($terms != "")  $display = BB_Translate("I agree to the %s.", $termsurl);
				else  $display = BB_Translate("I agree to the %s.", $privacyurl);
?>
			<div class="sso_main_formitem">
				<div class="sso_main_formdata"><input class="sso_main_checkbox" type="checkbox" id="<?php echo SSO_FrontendField("sso_login_tos"); ?>" name="<?php echo SSO_FrontendField("sso_login_tos"); ?>" value="yes"<?php echo (SSO_FrontendFieldValue("sso_login_tos") == "yes" ? " checked" : ""); ?> /> <label for="<?php echo SSO_FrontendField("sso_login_tos"); ?>"><?php echo $display; ?></label></div>
			</div>
			<script type="text/javascript">
			jQuery('#<?php echo SSO_FrontendField("sso_login_tos"); ?>').parent().find('label a').click(function(e) {
				e.preventDefault();
				window.open(jQuery(this).attr('href'));
			});
			</script>
<?php
			}
		}
	}
?>