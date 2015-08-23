<?php
	// SSO Generic Login Module for Account Recovery via Free E-mail to SMS Gateways.
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	if (!defined("SSO_FILE"))  exit();

	$g_sso_login_modules["sso_sms_recovery"] = array(
		"name" => "Account Recovery via Free SMS",
		"desc" => "Adds an additional, optional account recovery mechanism for mobile phone users that have carriers who provide a free e-mail to SMS gateway."
	);

	class sso_login_module_sso_sms_recovery extends sso_login_ModuleBase
	{
		public function DefaultOrder()
		{
			return 100;
		}

		private function GetInfo()
		{
			global $sso_settings;

			$info = $sso_settings["sso_login"]["modules"]["sso_sms_recovery"];
			if (!isset($info["first"]))  $info["first"] = "";
			if (!isset($info["from"]))  $info["from"] = "";
			if (!isset($info["subject"]))  $info["subject"] = "";

			return $info;
		}

		public function ConfigSave()
		{
			global $sso_settings;

			$info = $this->GetInfo();
			$info["first"] = trim($_REQUEST["sso_sms_recovery_first"]);
			$info["subject"] = trim($_REQUEST["sso_sms_recovery_subject"]);

			if ($_REQUEST["sso_sms_recovery_from"] != "")
			{
				$email = SMTP::MakeValidEmailAddress($_REQUEST["sso_sms_recovery_from"]);
				if (!$email["success"])  BB_SetPageMessage("error", BB_Translate("The e-mail address '%s' is invalid.  %s", $_REQUEST["sso_sms_recovery_from"], $email["error"]));
				else
				{
					if ($email["email"] != trim($_REQUEST["sso_sms_recovery_from"]))  BB_SetPageMessage("info", BB_Translate("Invalid e-mail address.  Perhaps you meant '%s' instead?", $email["email"]));

					$info["from"] = $email["email"];
				}
			}

			$sso_settings["sso_login"]["modules"]["sso_sms_recovery"] = $info;
		}

		public function Config(&$contentopts)
		{
			$data = @json_decode(@file_get_contents(SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/sms_mms_gateways.txt"));
			if (is_object($data))
			{
				$opts = array("" => "No Preference");
				foreach ($data->countries as $key => $val)  $opts[$key] = $val;

				$info = $this->GetInfo();
				$contentopts["fields"][] = array(
					"title" => "First Country",
					"type" => "select",
					"name" => "sso_sms_recovery_first",
					"options" => $opts,
					"select" => BB_GetValue("sso_sms_recovery_first", $info["first"]),
					"desc" => "The country to list first.  A convenience option to save time for users within your country."
				);
				$contentopts["fields"][] = array(
					"title" => "Recovery From Address",
					"type" => "text",
					"name" => "sso_sms_recovery_from",
					"value" => BB_GetValue("sso_sms_recovery_from", $info["from"]),
					"desc" => "The from address for the e-mail message to send to users recovering their password via SMS.  Leave blank for the server default.  Using a black hole e-mail address is highly recommended."
				);
				$contentopts["fields"][] = array(
					"title" => "Recovery Subject Line",
					"type" => "text",
					"name" => "sso_sms_recovery_subject",
					"value" => BB_GetValue("sso_sms_recovery_subject", $info["subject"]),
					"desc" => "The subject line for the e-mail message to send to users recovering their password via SMS.  Keep it short."
				);
			}
			else
			{
				$contentopts["fields"][] = array(
					"title" => "Error",
					"type" => "static",
					"value" => "Unable to load 'sms_mms_gateways.txt'",
					"desc" => "This module won't work until the problem is corrected."
				);
			}
		}

		public function CheckEditUserFields(&$userinfo)
		{
			$data = @json_decode(@file_get_contents(SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/sms_mms_gateways.txt"));
			if (is_object($data))
			{
				if ($_REQUEST["sso_sms_recovery_phone"] != "" || $_REQUEST["sso_sms_recovery_carrier"] != "")
				{
					if ($_REQUEST["sso_sms_recovery_phone"] == "")
					{
						if ($_REQUEST["sso_sms_recovery_carrier"] != "")  BB_SetPageMessage("error", "Please specify a SMS recovery phone number.");
					}
					else if ($_REQUEST["sso_sms_recovery_carrier"] == "")  BB_SetPageMessage("error", "Please specify a SMS recovery carrier.");
					else
					{
						$info = explode("-", $_REQUEST["sso_sms_recovery_carrier"]);
						if (count($info) != 2)  BB_SetPageMessage("error", "Please specify a SMS recovery carrier.");
						else
						{
							$country = $info[0];
							$carrier = $info[1];
							if (!isset($data->sms_carriers->$country) || !isset($data->sms_carriers->$country->$carrier))  BB_SetPageMessage("error", "Please specify a SMS recovery carrier.");
							else
							{
								$userinfo["sso_sms_recovery"] = array(
									"phone" => $_REQUEST["sso_sms_recovery_phone"],
									"carrier" => $_REQUEST["sso_sms_recovery_carrier"]
								);
							}
						}
					}
				}
			}
		}

		public function AddEditUserFields(&$contentopts, &$userinfo)
		{
			$data = @json_decode(@file_get_contents(SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/sms_mms_gateways.txt"));
			if (is_object($data))
			{
				$contentopts["fields"][] = "startrow";
				$contentopts["fields"][] = array(
					"title" => "SMS Recovery Phone",
					"type" => "text",
					"width" => "15em",
					"name" => "sso_sms_recovery_phone",
					"value" => BB_GetValue("sso_sms_recovery_phone", (isset($userinfo["sso_sms_recovery"]) ? $userinfo["sso_sms_recovery"]["phone"] : ""))
				);

				$carriers = array("" => "None");
				$info = $this->GetInfo();
				$country = $info["first"];
				if ($country != "" && isset($data->countries->$country) && isset($data->sms_carriers->$country))
				{
					$dispcountry = $data->countries->$country;
					$carriers[$dispcountry] = array();
					foreach ($data->sms_carriers->$country as $key => $item)
					{
						$select = $country . "-" . $key;
						$carriers[$dispcountry][$select] = $item[0];
					}

					unset($data->sms_carriers->$country);
				}
				foreach ($data->sms_carriers as $country => $items)
				{
					$dispcountry = $data->countries->$country;
					$carriers[$dispcountry] = array();
					foreach ($items as $key => $item)
					{
						$select = $country . "-" . $key;
						$carriers[$dispcountry][$select] = $item[0];
					}
				}

				$contentopts["fields"][] = array(
					"title" => "SMS Recovery Carrier",
					"type" => "select",
					"width" => "25em",
					"name" => "sso_sms_recovery_carrier",
					"options" => $carriers,
					"select" => BB_GetValue("sso_sms_recovery_carrier", (isset($userinfo["sso_sms_recovery"]) ? $userinfo["sso_sms_recovery"]["carrier"] : ""))
				);
				$contentopts["fields"][] = "endrow";
			}
		}

		private function SignupUpdateCheck(&$result, $ajax, $update, $admin)
		{
			$data = @json_decode(@file_get_contents(SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/sms_mms_gateways.txt"));
			if (is_object($data))
			{
				$field = ($admin ? BB_GetValue("sso_login_sms_recovery_phone", false) : SSO_FrontendFieldValue($update ? "sso_login_sms_recovery_phone_update" : "sso_login_sms_recovery_phone"));
				$field2 = ($admin ? BB_GetValue("sso_login_sms_recovery_carrier", false) : SSO_FrontendFieldValue($update ? "sso_login_sms_recovery_carrier_update" : "sso_login_sms_recovery_carrier"));
				if (!$ajax || $field !== false)
				{
					$info = $this->GetInfo();
					if ($field === false || $field == "")
					{
						if ($ajax && $field2 !== false && $field2 != "")  $result["errors"][] = BB_Translate($admin ? "Fill in the user's mobile phone number." : "Fill in your mobile phone number.");
					}
					else
					{
						$phone = preg_replace("/[^0-9]/", "", $field);
						if (strlen($phone) < 9)  $result["warnings"][] = BB_Translate("Phone numbers are typically longer.  Format is usually trunk/country code + area/region code + local number.");
						else if (strlen($phone) > 15)  $result["warnings"][] = BB_Translate("Phone numbers are typically shorter.  Format is usually trunk/country code + area/region code + local number.");

						if (!$ajax || $field2 !== false)
						{
							if ($field2 === false || $field2 == "")
							{
								if ($ajax)  $result["errors"][] = BB_Translate($admin ? "Select the user's mobile phone carrier." : "Select your mobile phone carrier.");
							}
							else
							{
								$field2 = explode("-", $field2);
								if (count($field2) != 2)  $result["errors"][] = BB_Translate($admin ? "Select the user's mobile phone carrier." : "Select your mobile phone carrier.");
								else
								{
									$country = $field2[0];
									$carrier = $field2[1];
									if (!isset($data->sms_carriers->$country) || !isset($data->sms_carriers->$country->$carrier))  $result["errors"][] = BB_Translate($admin ? "Select the user's mobile phone carrier." : "Select your mobile phone carrier.");
									else
									{
										$item = $data->sms_carriers->$country->$carrier;
										$emailaddr = str_replace("{number}", $field, $item[1]);

										if (!class_exists("SMTP"))
										{
											define("CS_TRANSLATE_FUNC", "BB_Translate");
											require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/smtp.php";
										}
										$email = SMTP::MakeValidEmailAddress($emailaddr);
										if (!$email["success"])  $result["errors"][] = BB_Translate("Invalid e-mail address.  %s", $email["error"]);
										else
										{
											if ($email["email"] != trim($emailaddr))  $result["warnings"][] = BB_Translate("Invalid e-mail address.  Perhaps you meant '%s' instead?", $email["email"]);

											$result["htmlsuccess"] = BB_Translate("Mobile phone information looks okay.  SMS messages will be sent via the e-mail address '%s'.", "<a href=\"mailto:" . htmlspecialchars($emailaddr) . "\">" . htmlspecialchars($emailaddr) . "</a>");
										}
									}
								}
							}
						}
					}
				}
			}
		}

		public function SignupCheck(&$result, $ajax, $admin)
		{
			$this->SignupUpdateCheck($result, $ajax, false, $admin);
		}

		private function DisplaySignup($userinfo, $admin)
		{
			global $sso_target_url;

			$data = @json_decode(@file_get_contents(SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/sms_mms_gateways.txt"));
			if (is_object($data))
			{
				$info = $this->GetInfo();

				if ($admin)
				{
					$options = array(
						"" => "None",
					);

					$country = $info["first"];
					if ($country != "" && isset($data->countries->$country) && isset($data->sms_carriers->$country))
					{
						$options2 = array();
						foreach ($data->sms_carriers->$country as $key => $item)  $options2[$country . "-" . $key] = $item[0];
						$options[$data->countries->$country] = $options2;

						unset($data->sms_carriers->$country);
					}

					foreach ($data->sms_carriers as $country => $items)
					{
						$options2 = array();
						foreach ($items as $key => $item)  $options2[$country . "-" . $key] = $item[0];
						$options[$data->countries->$country] = $options2;
					}

					$result = array(
						array(
							"title" => "Mobile Phone Number",
							"type" => "text",
							"name" => "sso_login_sms_recovery_phone",
							"value" => BB_GetValue("sso_login_sms_recovery_phone", ""),
							"desc" => "Optional.  Can be used to recover access to this account."
						),
						array(
							"title" => "Mobile Phone Carrier",
							"type" => "select",
							"name" => "sso_login_sms_recovery_carrier",
							"options" => $options,
							"select" => BB_GetValue("sso_login_sms_recovery_carrier", ""),
							"desc" => "Required when Mobile Phone Number is specified."
						)
					);

					return $result;
				}
				else
				{
					$carrier = SSO_FrontendFieldValue(($userinfo !== false ? "sso_login_sms_recovery_carrier_update" : "sso_login_sms_recovery_carrier"), ($userinfo !== false && isset($userinfo["sso_sms_recovery"]) ? $userinfo["sso_sms_recovery"]["carrier"] : ""));
?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Your Mobile Phone Number")); ?></div>
				<div class="sso_main_formdata"><input class="sso_main_text sso_login_changehook_smsrecovery" type="text" name="<?php echo SSO_FrontendField($userinfo !== false ? "sso_login_sms_recovery_phone_update" : "sso_login_sms_recovery_phone"); ?>" value="<?php echo htmlspecialchars(SSO_FrontendFieldValue(($userinfo !== false ? "sso_login_sms_recovery_phone_update" : "sso_login_sms_recovery_phone"), ($userinfo !== false && isset($userinfo["sso_sms_recovery"]) ? $userinfo["sso_sms_recovery"]["phone"] : ""))); ?>" /></div>
				<div class="sso_main_formdesc"><?php echo htmlspecialchars(BB_Translate("Optional.  Can be used to recover access to this account.")); ?></div>
			</div>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Your Mobile Phone Carrier")); ?></div>
				<div class="sso_main_formdata"><select class="sso_main_dropdown sso_login_changehook_smsrecovery" name="<?php echo SSO_FrontendField($userinfo !== false ? "sso_login_sms_recovery_carrier_update" : "sso_login_sms_recovery_carrier"); ?>">
					<option value=""<?php if ($carrier == "")  echo " selected"; ?>><?php echo htmlspecialchars(BB_Translate("None")); ?></option>
<?php
					$country = $info["first"];
					if ($country != "" && isset($data->countries->$country) && isset($data->sms_carriers->$country))
					{
?>
					<optgroup label="<?php echo htmlspecialchars(BB_Translate($data->countries->$country)); ?>">
<?php
						foreach ($data->sms_carriers->$country as $key => $item)
						{
							$select = $country . "-" . $key;
?>
						<option value="<?php echo htmlspecialchars($select); ?>"<?php if ($carrier == $select)  echo " selected"; ?>><?php echo htmlspecialchars(BB_Translate($item[0])); ?></option>
<?php
						}
?>
					</optgroup>
<?php

						unset($data->sms_carriers->$country);
					}

					foreach ($data->sms_carriers as $country => $items)
					{
?>
					<optgroup label="<?php echo htmlspecialchars(BB_Translate($data->countries->$country)); ?>">
<?php
						foreach ($items as $key => $item)
						{
							$select = $country . "-" . $key;
?>
						<option value="<?php echo htmlspecialchars($select); ?>"<?php if ($carrier == $select)  echo " selected"; ?>><?php echo htmlspecialchars(BB_Translate($item[0])); ?></option>
<?php
						}
?>
					</optgroup>
<?php
					}
?>
				</select></div>
				<div class="sso_main_formresult sso_sms_recovery_result"></div>
			</div>
			<script type="text/javascript">
			var SSO_SendFields_SMSRecovery_data = {};
			function SSO_SendFields_SMSRecovery()
			{
				var found = false;
				jQuery('.sso_login_changehook_smsrecovery').each(function() {
					if (SSO_SendFields_SMSRecovery_data[this.name] != jQuery(this).val())
					{
						SSO_SendFields_SMSRecovery_data[this.name] = jQuery(this).val();
						found = true;
					}
				});

				if (found)
				{
					jQuery('.sso_sms_recovery_result').html('<div class="sso_main_formchecking"><?php echo BB_JSSafe(BB_Translate("Checking...")); ?></div>');
					jQuery('.sso_sms_recovery_result').load('<?php echo BB_JSSafe($userinfo !== false ? $sso_target_url . "&sso_login_action=update_info&sso_v=" . urlencode($_REQUEST["sso_v"]) . "&sso_ajax=1" : $sso_target_url . "&sso_login_action=signup_check&sso_ajax=1"); ?>', SSO_SendFields_SMSRecovery_data);
				}
			}

			jQuery(function() {
				jQuery('.sso_login_changehook_smsrecovery').each(function() {
					SSO_SendFields_SMSRecovery_data[this.name] = jQuery(this).val();
				});

				jQuery('.sso_login_changehook_smsrecovery').change(SSO_SendFields_SMSRecovery);
				jQuery('select.sso_login_changehook_smsrecovery').keyup(SSO_SendFields_SMSRecovery);
			});
			</script>
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
			if ($admin)
			{
				$userinfo["sso_sms_recovery"] = array(
					"phone" => $_REQUEST["sso_login_sms_recovery_phone"],
					"carrier" => $_REQUEST["sso_login_sms_recovery_carrier"]
				);
			}
			else
			{
				$userinfo["sso_sms_recovery"] = array(
					"phone" => SSO_FrontendFieldValue($update ? "sso_login_sms_recovery_phone_update" : "sso_login_sms_recovery_phone", ""),
					"carrier" => SSO_FrontendFieldValue($update ? "sso_login_sms_recovery_carrier_update" : "sso_login_sms_recovery_carrier", "")
				);
			}
		}

		public function SignupAddInfo(&$userinfo, $admin)
		{
			$this->SignupUpdateAddInfo($userinfo, false, $admin);
		}

		public function InitMessages(&$result)
		{
			if ($_REQUEST["sso_msg"] == "sso_login_sms_sent")  $result["success"] = BB_Translate("Account recovery phrase sent.  Check your SMS text messages.");
		}

		public function IsRecoveryAllowed($allowoptional)
		{
			return $allowoptional;
		}

		public function AddRecoveryMethod($method)
		{
			$data = @json_decode(@file_get_contents(SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/sms_mms_gateways.txt"));
			if (is_object($data))
			{
				echo "<option value=\"sso_sms_recovery\"" . ($method == "sso_sms_recovery" ? " selected" : "") . ">" . htmlspecialchars(BB_Translate("Text Message (SMS)")) . "</option>";
			}
		}

		public function RecoveryDone(&$result, $method, $userrow, $userinfo)
		{
			global $sso_session_info, $sso_target_url;

			if ($method == "sso_sms_recovery")
			{
				$data = @json_decode(@file_get_contents(SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/sms_mms_gateways.txt"));
				if (!is_object($data))  $result["errors"][] = BB_Translate("Fatal error:  Unable to load SMS carrier database.");
				else if (!isset($userinfo["sso_sms_recovery"]) || $userinfo["sso_sms_recovery"]["phone"] == "" || $userinfo["sso_sms_recovery"]["carrier"] == "")  $result["errors"][] = BB_Translate("Login does not have complete SMS information.  Unable to send message.");
				else
				{
					$info = explode("-", $userinfo["sso_sms_recovery"]["carrier"]);
					if (count($info) != 2)  $result["errors"][] = BB_Translate("Mangled SMS carrier.  Unable to send message.");
					else
					{
						$country = $info[0];
						$carrier = $info[1];
						if (!isset($data->sms_carriers->$country) || !isset($data->sms_carriers->$country->$carrier))  $result["errors"][] = BB_Translate("The login's SMS carrier setting is no longer valid.");
						else
						{
							$item = $data->sms_carriers->$country->$carrier;
							$email = str_replace("{number}", $userinfo["sso_sms_recovery"]["phone"], $item[1]);

							$sso_session_info["sso_login_recover"] = array(
								"id" => $userrow->id,
								"method" => "sso_sms_recovery",
								"v" => trim(preg_replace('/\s+/', " ", strtolower(SSO_GetRandomWord()) . " " . strtolower(SSO_GetRandomWord()) . " " . strtolower(SSO_GetRandomWord()))),
								"attempts" => 3
							);

							if (!SSO_SaveSessionInfo())  $result["errors"][] = BB_Translate("Fatal error:  Unable to save session information.");
							else
							{
								$info = $this->GetInfo();
								$fromaddr = BB_PostTranslate($info["from"] != "" ? $info["from"] : SSO_SMTP_FROM);
								$subject = BB_Translate($info["subject"]);
								$textmsg = $sso_session_info["sso_login_recover"]["v"];
								$result = SSO_SendEmail($fromaddr, $email, $subject, "<html><body>" . htmlspecialchars($textmsg) . "</body></html>", $textmsg);
								if (!$result["success"])  $result["errors"][] = BB_Translate("Fatal error:  Unable to send SMS via e-mail.  %s", $result["error"]);
								else
								{
									header("Location: " . BB_GetRequestHost() . $sso_target_url . "&sso_login_action=recover2&sso_method=sso_sms_recovery&sso_msg=sso_login_sms_sent");
									exit();
								}
							}
						}
					}
				}
			}
		}

		public function RecoveryCheck2(&$result, $userinfo)
		{
			global $sso_session_info, $sso_target_url;

			if ($userinfo !== false && $_REQUEST["sso_method"] == "sso_sms_recovery")
			{
				$field = SSO_FrontendFieldValue("sso_login_sms_recovery_phrase", "");
				$field = strtolower(trim(preg_replace('/\s+/', " ", $field)));
				if ($field == "" || $field != $sso_session_info["sso_login_recover"]["v"])
				{
					$sso_session_info["sso_login_recover"]["attempts"]--;
					if ($sso_session_info["sso_login_recover"]["attempts"] < 1)
					{
						unset($sso_session_info["sso_login_recover"]);
						SSO_SaveSessionInfo();

						header("Location: " . BB_GetRequestHost() . $sso_target_url . "&sso_login_action=recover&sso_msg=recovery_expired_invalid");
						exit();
					}
					else if (!SSO_SaveSessionInfo())  $result["errors"][] = BB_Translate("Incorrect recovery phrase and a fatal error occurred.  Fatal error:  Unable to save session information.");
					else if ($sso_session_info["sso_login_recover"]["attempts"] == 1)  $result["errors"][] = BB_Translate("Incorrect recovery phrase.  1 attempt left.");
					else  $result["errors"][] = BB_Translate("Incorrect recovery phrase.  %d attempts left.", $sso_session_info["sso_login_recover"]["attempts"]);
				}
				else
				{
					unset($sso_session_info["sso_login_recover"]);
				}
			}
		}

		public function GenerateRecovery2($messages)
		{
			if ($_REQUEST["sso_method"] == "sso_sms_recovery")
			{
?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Recovery Phrase")); ?></div>
				<div class="sso_main_formdata"><input class="sso_main_text" type="text" name="<?php echo SSO_FrontendField("sso_login_sms_recovery_phrase"); ?>" value="<?php echo htmlspecialchars(SSO_FrontendFieldValue("sso_login_sms_recovery_phrase", "")); ?>" /></div>
				<div class="sso_main_formdesc"><?php echo htmlspecialchars(BB_Translate("Enter the recovery phrase that was sent via text message (SMS).")); ?></div>
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
	}
?>