<?php
	// SSO Generic Login Module for Remember Me
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	if (!defined("SSO_FILE"))  exit();

	$g_sso_login_modules["sso_remember_me"] = array(
		"name" => "Remember Me",
		"desc" => "Adds 'Remember Me' support to the login screen."
	);

	class sso_login_module_sso_remember_me extends sso_login_ModuleBase
	{
		public function DefaultOrder()
		{
			return 25;
		}

		private function GetInfo()
		{
			global $sso_settings;

			$info = $sso_settings["sso_login"]["modules"]["sso_remember_me"];
			if (!isset($info["bypass_twofactor"]))  $info["bypass_twofactor"] = false;
			if (!isset($info["maxdays"]))  $info["maxdays"] = 365;
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
			$info["bypass_twofactor"] = ($_REQUEST["sso_remember_me_bypass_twofactor"] > 0);
			$info["maxdays"] = (int)$_REQUEST["sso_remember_me_maxdays"];
			if ($_REQUEST["sso_remember_me_resetkey"] > 0)
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

			$sso_settings["sso_login"]["modules"]["sso_remember_me"] = $info;
		}

		public function Config(&$contentopts)
		{
			$info = $this->GetInfo();
			$contentopts["fields"][] = array(
				"title" => "Maximum Days",
				"type" => "text",
				"name" => "sso_remember_me_maxdays",
				"value" => BB_GetValue("sso_remember_me_maxdays", $info["maxdays"]),
				"desc" => "The maximum number of days that a user may remember their sign in for."
			);
			$contentopts["fields"][] = array(
				"title" => "Allow Bypass Two-Factor Authentication?",
				"type" => "select",
				"name" => "sso_remember_me_bypass_twofactor",
				"options" => array(1 => "Yes", 0 => "No"),
				"select" => BB_GetValue("sso_remember_me_bypass_twofactor", (string)(int)$info["bypass_twofactor"]),
				"desc" => "Allows the user to bypass two-factor authentication (if any) for subsequent sign-ins."
			);
			$contentopts["fields"][] = array(
				"title" => "Reset Secret Key?",
				"type" => "select",
				"name" => "sso_remember_me_resetkey",
				"options" => array(1 => "Yes", 0 => "No"),
				"select" => BB_GetValue("sso_remember_me_resetkey", "0"),
				"desc" => "Resets the internal key and initialization vector used to encrypt the Remember Me cookie.  Will cause all existing cookies to become invalid."
			);
		}

		public function GenerateLogin($messages)
		{
			global $sso_db, $sso_target_url, $sso_settings;

			$info = $this->GetInfo();
			if ($info["cookiekey"] != "" && $info["cookieiv"] != "" && $info["cookiekey2"] != "" && $info["cookieiv2"] != "")
			{
?>
			<div class="sso_main_formitem sso_has_js">
				<div class="sso_main_formdesc" style="margin-left: 0;"><a href="#" onclick="jQuery('#sso_login_remember_me_wrap').slideDown();  jQuery(this).parent().parent().slideUp();  return false;"><?php echo htmlspecialchars(BB_Translate("Show 'Remember Me' options")); ?></a></div>
			</div>

			<div id="sso_login_remember_me_wrap" class="sso_no_js">
				<div class="sso_main_formitem">
					<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Remember Me - Days")); ?></div>
					<div class="sso_main_formdata"><input class="sso_main_text" type="text" name="<?php echo SSO_FrontendField("sso_login_remember_me"); ?>" value="<?php echo htmlspecialchars(SSO_FrontendFieldValue("sso_login_remember_me", "0")); ?>" /></div>
					<div class="sso_main_formdesc"><?php echo htmlspecialchars(BB_Translate("Enter the number of days to remember this sign in for.  Maximum number of days is %d.  Use only on trusted computers/devices on a trusted network.", $info["maxdays"])); ?></div>
				</div>
<?php
				if ($info["bypass_twofactor"])
				{
?>
				<div class="sso_main_formitem">
					<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Remember Me - Skip Two-Factor")); ?></div>
					<div class="sso_main_formdata"><input class="sso_main_checkbox" type="checkbox" id="<?php echo SSO_FrontendField("sso_login_remember_me_bypass_twofactor"); ?>" name="<?php echo SSO_FrontendField("sso_login_remember_me_bypass_twofactor"); ?>" value="yes"<?php echo (SSO_FrontendFieldValue("sso_login_remember_me_bypass_twofactor") == "yes" ? " checked" : ""); ?> /> <label for="<?php echo SSO_FrontendField("sso_login_remember_me_bypass_twofactor"); ?>"><?php echo htmlspecialchars(BB_Translate("Skip two-factor authentication for future Remember Me sign-ins")); ?></label></div>
				</div>
<?php
				}
?>
				<div class="sso_main_formitem">
					<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Remember Me - Reset")); ?></div>
					<div class="sso_main_formdata"><input class="sso_main_checkbox" type="checkbox" id="<?php echo SSO_FrontendField("sso_login_remember_me_reset"); ?>" name="<?php echo SSO_FrontendField("sso_login_remember_me_reset"); ?>" value="yes"<?php echo (SSO_FrontendFieldValue("sso_login_remember_me_reset") == "yes" ? " checked" : ""); ?> /> <label for="<?php echo SSO_FrontendField("sso_login_remember_me_reset"); ?>"><?php echo htmlspecialchars(BB_Translate("Remove all Remember Me sign-ins from all computers/devices")); ?></label></div>
				</div>
			</div>

			<noscript>
			<style type="text/css">
			.sso_has_js { display: none; }
			.sso_no_js { display: block; }
			</style>
			</noscript>

<?php
				if (isset($_COOKIE["sso_l_rme"]))
				{
					// Decrypt data.
					$info2 = @base64_decode($_COOKIE["sso_l_rme"]);
					if ($info2 !== false)  $info2 = Blowfish::ExtractDataPacket($info2, pack("H*", $info["cookiekey"]), array("mode" => "CBC", "iv" => pack("H*", $info["cookieiv"]), "key2" => pack("H*", $info["cookiekey2"]), "iv2" => pack("H*", $info["cookieiv2"]), "lightweight" => true));
					if ($info2 !== false)  $info2 = @unserialize($info2);
					if ($info2 !== false)
					{
						$ids = array();
						foreach ($info2 as $id => $tokens)
						{
							if (is_array($tokens) && count($tokens) == 2)  $ids[] = (int)$id;
						}

						$sso_db_sso_login_users = SSO_DB_PREFIX . "p_sso_login_users";

						$found = false;
						$result = $sso_db->Query("SELECT", array(
							"*",
							"FROM" => "?",
							"WHERE" => "id IN ('" . implode("','", $ids) . "')",
						), $sso_db_sso_login_users);
						while ($row = $result->NextRow())
						{
 							if (!isset($row->verified) || $row->verified)
							{
								$userinfo = SSO_DecryptDBData($row->info);

								if ($userinfo !== false && isset($userinfo["sso_remember_me"]) && isset($userinfo["sso_remember_me"][$info2[$row->id][0]]))
								{
									$info3 = $userinfo["sso_remember_me"][$info2[$row->id][0]];

									$ts = CSDB::ConvertFromDBTime($info3["expires"]);
									if ($ts > time())
									{
										if (!$found)
										{
?>
			<div id="sso_remembered_sign_ins" class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Remembered Sign Ins")); ?></div>
				<div class="sso_main_formdesc">
<?php
											$found = true;
										}

										// Calculate the number of days left.
										$daysleft = (int)round(($ts - time()) / (24 * 60 * 60));
										if ($daysleft < 1)  $title = BB_Translate("Expires in less than a day.");
										else if ($daysleft == 1)  $title = BB_Translate("Expires in one day.");
										else  $title = BB_Translate("Expires in %d days.", $daysleft);

										if ($info3["bypass"])  $title = BB_Translate("%s  Skips two-factor authentication.", $title);

										// Bypass the normal sign in process.
										echo "<a href=\"" . BB_GetRequestHost() . $sso_target_url . "&sso_login_action=module&sso_login_module=sso_remember_me&id=" . $row->id . "\" title=\"" . htmlspecialchars($title) . "\">";
										if ($sso_settings["sso_login"]["install_type"] == "email_username")
										{
											echo htmlspecialchars(BB_Translate("%s (%s)", $row->email, $row->username));
										}
										else if ($sso_settings["sso_login"]["install_type"] == "email")
										{
											echo htmlspecialchars(BB_Translate("%s", $row->email));
										}
										else if ($sso_settings["sso_login"]["install_type"] == "username")
										{
											echo htmlspecialchars(BB_Translate("%s", $row->username));
										}
										echo "</a><br />";
									}
								}
							}
						}

						if ($found)
						{
?>
				</div>
			</div>

			<script type="text/javascript">
			jQuery('#sso_remembered_sign_ins').prependTo('form.sso_main_form');
			</script>
<?php
						}
					}
				}
			}
		}

		public function LoginCheck(&$result, $userinfo, $recoveryallowed)
		{
			global $sso_session_info;

			if ($userinfo !== false)
			{
				$info = $this->GetInfo();
				if ($info["cookiekey"] != "" && $info["cookieiv"] != "" && $info["cookiekey2"] != "" && $info["cookieiv2"] != "")
				{
					$numdays = (int)SSO_FrontendFieldValue("sso_login_remember_me", "0");
					if ($numdays < 0)  $numdays = 0;
					else if ($numdays > $info["maxdays"])  $numdays = $info["maxdays"];

					// Put the login information into the session since the user might have another form or two to fill in.
					$sso_session_info["sso_login_remember_me"] = array(
						"numdays" => $numdays,
						"bypass" => ($info["bypass_twofactor"] ? SSO_FrontendFieldValue("sso_login_remember_me_bypass_twofactor", "") == "yes" : false),
						"reset" => (SSO_FrontendFieldValue("sso_login_remember_me_reset", "") == "yes")
					);

					if (!SSO_SaveSessionInfo())  $result["errors"][] = BB_Translate("Unable to save Remember Me information.  Fatal error:  Unable to save session information.");
				}
			}
		}

		public function LoginAddMap(&$mapinfo, $userrow, &$userinfo, $admin)
		{
			global $sso_rng, $sso_session_info;

			$info = $this->GetInfo();
			if (!$admin && $info["cookiekey"] != "" && $info["cookieiv"] != "" && $info["cookiekey2"] != "" && $info["cookieiv2"] != "" && isset($sso_session_info["sso_login_remember_me"]))
			{
				if (!isset($userinfo["sso_remember_me"]))  $userinfo["sso_remember_me"] = array();

				if ($sso_session_info["sso_login_remember_me"]["reset"])  $userinfo["sso_remember_me"] = array();

				// Remove expired tokens.
				foreach ($userinfo["sso_remember_me"] as $token => $info2)
				{
					if (CSDB::ConvertFromDBTime($info2["expires"]) < time())  unset($userinfo["sso_remember_me"][$token]);
				}

				if ($sso_session_info["sso_login_remember_me"]["numdays"] > 0)
				{
					$token = $sso_rng->GenerateString();
					$token2 = $sso_rng->GenerateString();
					$salt = $sso_rng->GenerateString();
					$data = $salt . ":" . $token2;
					$passwordinfo = sso_login::HashPasswordInfo($data);
					if ($passwordinfo["success"])
					{
						// Add temporary session data to user information.
						$userinfo["sso_remember_me"][$token] = array(
							"salt" => $salt,
							"rounds" => (int)$passwordinfo["rounds"],
							"hash" => bin2hex($passwordinfo["hash"]),
							"expires" => CSDB::ConvertToDBTime(time() + $sso_session_info["sso_login_remember_me"]["numdays"] * 24 * 60 * 60),
							"bypass" => $sso_session_info["sso_login_remember_me"]["bypass"]
						);

						// Append user ID and token to the cookie.
						$info2 = false;
						if (isset($_COOKIE["sso_l_rme"]))
						{
							// Decrypt existing data.
							$info2 = @base64_decode($_COOKIE["sso_l_rme"]);
							if ($info2 !== false)  $info2 = Blowfish::ExtractDataPacket($info2, pack("H*", $info["cookiekey"]), array("mode" => "CBC", "iv" => pack("H*", $info["cookieiv"]), "key2" => pack("H*", $info["cookiekey2"]), "iv2" => pack("H*", $info["cookieiv2"]), "lightweight" => true));
							if ($info2 !== false)  $info2 = @unserialize($info2);
						}
						if ($info2 === false)  $info2 = array();

						$info2[$userrow->id] = array($token, $token2);

						// Set the Remember Me cookie.
						$data = base64_encode(Blowfish::CreateDataPacket(serialize($info2), pack("H*", $info["cookiekey"]), array("prefix" => $sso_rng->GenerateString(), "mode" => "CBC", "iv" => pack("H*", $info["cookieiv"]), "key2" => pack("H*", $info["cookiekey2"]), "iv2" => pack("H*", $info["cookieiv2"]), "lightweight" => true)));
						SetCookieFixDomain("sso_l_rme", $data, time() + $info["maxdays"] * 24 * 60 * 60, "", "", BB_IsSSLRequest(), true);
					}
				}
			}
		}

		public function CustomFrontend()
		{
			global $g_sso_login_modules, $sso_settings, $sso_header, $sso_footer, $sso_target_url, $sso_db, $sso_session_info, $sso_rng;

			$messages = array("errors" => array(), "warnings" => array(), "success" => "");
			$info = $this->GetInfo();
			if ($info["cookiekey"] != "" && $info["cookieiv"] != "" && $info["cookiekey2"] != "" && $info["cookieiv2"] != "")
			{
				// Initialize active modules.
				$this->activemodules = array();
				foreach ($g_sso_login_modules as $key => $info2)
				{
					if ($sso_settings["sso_login"]["modules"][$key]["_a"])
					{
						$module = "sso_login_module_" . $key;
						$this->activemodules[$key] = new $module;
					}
				}

				$sso_db_sso_login_users = SSO_DB_PREFIX . "p_sso_login_users";

				if (isset($_REQUEST["id"]) && isset($_COOKIE["sso_l_rme"]))
				{
					// Decrypt data.
					$info2 = @base64_decode($_COOKIE["sso_l_rme"]);
					if ($info2 !== false)  $info2 = Blowfish::ExtractDataPacket($info2, pack("H*", $info["cookiekey"]), array("mode" => "CBC", "iv" => pack("H*", $info["cookieiv"]), "key2" => pack("H*", $info["cookiekey2"]), "iv2" => pack("H*", $info["cookieiv2"]), "lightweight" => true));
					if ($info2 !== false)  $info2 = @unserialize($info2);
					if ($info2 !== false)
					{
						$id = (int)$_REQUEST["id"];
						if (isset($info2[$id]) && is_array($info2[$id]) && count($info2[$id]) == 2)
						{
							// Load database information and verify the sign in.
							$userrow = $sso_db->GetRow("SELECT", array(
								"*",
								"FROM" => "?",
								"WHERE" => "id = ?",
							), $sso_db_sso_login_users, $id);
 							if ($userrow && (!isset($userrow->verified) || $userrow->verified))
							{
								$userinfo = SSO_DecryptDBData($userrow->info);

								if ($userinfo !== false && isset($userinfo["sso_remember_me"]) && isset($userinfo["sso_remember_me"][$info2[$userrow->id][0]]))
								{
									$info3 = $userinfo["sso_remember_me"][$info2[$userrow->id][0]];

									$ts = CSDB::ConvertFromDBTime($info3["expires"]);
									if ($ts > time())
									{
										$data = $info3["salt"] . ":" . $info2[$userrow->id][1];
										if (sso_login::VerifyPasswordInfo($data, $info3["hash"], $info3["rounds"]))
										{
											// Sign in is now verified to be valid.
											if (!$info3["bypass"] && ($sso_settings["sso_login"]["require_two_factor"] || (isset($userinfo["two_factor_method"]) && $userinfo["two_factor_method"] != "")))
											{
												// Go to two-factor authentication page.
												$methods = array();
												foreach ($this->activemodules as $key => &$instance)
												{
													$name = $instance->GetTwoFactorName(false);
													if ($name !== false)  $methods[$key] = true;
												}

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
						}
					}
				}

				echo $sso_header;

				SSO_OutputHeartbeat();
?>
<div class="sso_main_wrap sso_login">
<div class="sso_main_wrap_inner">
	<div class="sso_main_messages_wrap">
		<div class="sso_main_messages">
<?php
				if (count($messages["errors"]))
				{
?>
			<div class="sso_main_messageerror"><?php echo htmlspecialchars($messages["errors"][0]); ?></div>
<?php
				}
?>
			<div class="sso_main_messageerror"><?php echo htmlspecialchars(BB_Translate("An error occurred while processing the remembered sign in.  You will have to sign in normally.")); ?></div>
		</div>
	</div>
	<div class="sso_login_signin"><a href="<?php echo htmlspecialchars($sso_target_url); ?>"><?php echo htmlspecialchars(BB_Translate("Sign in")); ?></a></div>
</div>
</div>
<?php
				echo $sso_footer;
			}
		}
	}
?>