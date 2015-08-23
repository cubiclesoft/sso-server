<?php
	// SSO Facebook Connect Provider
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	if (!defined("SSO_FILE"))  exit();

	class sso_facebook extends SSO_ProviderBase
	{
		// See:
		//   http://developers.facebook.com/docs/reference/api/user/
		//   http://developers.facebook.com/docs/authentication/permissions/
		private static $fieldmap = array(
			"email" => array("title" => "E-mail Address", "desc" => "user's e-mail address", "extra" => "email"),
			"username" => array("title" => "Username", "desc" => "user's pseudo-username", "extra" => "", "notes" => "This is generated using the most reasonable field available.", "pseudo" => true),
			"age_range" => array("title" => "Age Range", "desc" => "user's unspecified age range", "extra" => ""),
			"name" => array("title" => "Full Name", "desc" => "user's full name", "extra" => ""),
			"first_name" => array("title" => "First Name", "desc" => "user's first name", "extra" => ""),
			"middle_name" => array("title" => "Middle Name", "desc" => "user's middle name", "extra" => ""),
			"last_name" => array("title" => "Last Name", "desc" => "user's last name", "extra" => ""),
			"gender" => array("title" => "Gender", "desc" => "user's gender", "extra" => ""),
			"link" => array("title" => "Profile URL", "desc" => "user's profile URL", "extra" => ""),
			"locale" => array("title" => "Locale", "desc" => "user's locale", "extra" => ""),
			"timezone" => array("title" => "Timezone Offset", "desc" => "user's UTC timezone offset", "extra" => ""),
			"verified" => array("title" => "Account Verified", "desc" => "user's verification status", "extra" => ""),
			"third_party_id" => array("title" => "Third Party ID", "desc" => "anonymous, unique ID", "extra" => ""),

			"about" => array("title" => "About Me", "desc" => "user's 'about me'", "extra" => "user_about_me"),
			"bio" => array("title" => "Bio", "desc" => "user's biography", "extra" => "user_about_me"),
			"birthday" => array("title" => "Birthday", "desc" => "user's birthday", "extra" => "user_birthday"),
			"birthday_year" => array("title" => "Birthday Year", "desc" => "user's birthday year", "extra" => "user_birthday", "notes" => "This is the year extracted from the user's birthday.", "parent" => "birthday"),
			"birthday_month" => array("title" => "Birthday Month", "desc" => "user's birthday month", "extra" => "user_birthday", "notes" => "This is the month extracted from the user's birthday.", "parent" => "birthday"),
			"birthday_day" => array("title" => "Birthday Day", "desc" => "user's birthday day", "extra" => "user_birthday", "notes" => "This is the day extracted from the user's birthday.", "parent" => "birthday"),
			// cover
			"education" => array("title" => "Education", "desc" => "user's education", "extra" => "user_education_history"),
			// favorite_atheletes
			// favorite_teams
			"hometown" => array("title" => "Hometown", "desc" => "user's hometown", "extra" => "user_hometown"),
			// inspirational_people
			"is_verified" => array("title" => "Facebook Verified", "desc" => "Facebook verified", "extra" => "", "notes" => "This is only true if the user has been manually verified by Facebook."),
			// languages
			"location" => array("title" => "Current City", "desc" => "user's location", "extra" => "user_location"),
			"name_format" => array("title" => "Formatted Name", "desc" => "user's formatted name", "extra" => ""),
			"political" => array("title" => "Political View", "desc" => "user's political view", "extra" => "user_religion_politics"),
			"quotes" => array("title" => "Favorite Quotes", "desc" => "user's favorite quotes", "extra" => "user_about_me"),
			"relationship_status" => array("title" => "Relationship Status", "desc" => "user's relationship status", "extra" => "user_relationships"),
			"religion" => array("title" => "Religion", "desc" => "user's religion", "extra" => "user_religion_politics"),
			"significant_other" => array("title" => "Significant Other", "desc" => "user's significant other", "extra" => "user_relationships"),
			"website" => array("title" => "Website", "desc" => "user's personal website", "extra" => "user_website"),
			"work" => array("title" => "Work History", "desc" => "user's work history", "extra" => "user_work_history"),
		);

		public function Init()
		{
			global $sso_settings;

			if (!isset($sso_settings["sso_facebook"]["app_id"]))  $sso_settings["sso_facebook"]["app_id"] = "";
			if (!isset($sso_settings["sso_facebook"]["app_secret"]))  $sso_settings["sso_facebook"]["app_secret"] = "";
			if (!isset($sso_settings["sso_facebook"]["enabled"]))  $sso_settings["sso_facebook"]["enabled"] = false;
			if (!isset($sso_settings["sso_facebook"]["username_blacklist"]))  $sso_settings["sso_facebook"]["username_blacklist"] = "";
			if (!isset($sso_settings["sso_facebook"]["email_bad_domains"]))  $sso_settings["sso_facebook"]["email_bad_domains"] = "";
			if (!isset($sso_settings["sso_facebook"]["iprestrict"]))  $sso_settings["sso_facebook"]["iprestrict"] = SSO_InitIPFields();

			foreach (self::$fieldmap as $key => $info)
			{
				if (!isset($sso_settings["sso_facebook"]["map_" . $key]) || !SSO_IsField($sso_settings["sso_facebook"]["map_" . $key]))  $sso_settings["sso_facebook"]["map_" . $key] = "";
			}
		}

		public function DisplayName()
		{
			return BB_Translate("Facebook");
		}

		public function DefaultOrder()
		{
			return 100;
		}

		public function MenuOpts()
		{
			global $sso_site_admin, $sso_settings;

			$result = array(
				"name" => "Facebook Login"
			);

			if ($sso_site_admin)
			{
				if ($sso_settings["sso_facebook"]["enabled"])
				{
					$result["items"] = array(
						"Configure" => SSO_CreateConfigURL("config"),
						"Disable" => SSO_CreateConfigURL("disable")
					);
				}
				else
				{
					$result["items"] = array(
						"Enable" => SSO_CreateConfigURL("enable")
					);
				}
			}

			return $result;
		}

		public function Config()
		{
			global $sso_site_admin, $sso_settings, $sso_menuopts, $sso_select_fields;

			if ($sso_site_admin && $sso_settings["sso_facebook"]["enabled"] && $_REQUEST["action2"] == "config")
			{
				if (isset($_REQUEST["configsave"]))
				{
					$_REQUEST["app_id"] = trim($_REQUEST["app_id"]);
					$_REQUEST["app_secret"] = trim($_REQUEST["app_secret"]);

					if ($_REQUEST["app_id"] == "")  BB_SetPageMessage("info", "The 'Facebook App ID' field is empty.");
					else if ($_REQUEST["app_secret"] == "")  BB_SetPageMessage("info", "The 'Facebook App Secret' field is empty.");

					$sso_settings["sso_facebook"]["iprestrict"] = SSO_ProcessIPFields();

					if (BB_GetPageMessageType() != "error")
					{
						$sso_settings["sso_facebook"]["app_id"] = $_REQUEST["app_id"];
						$sso_settings["sso_facebook"]["app_secret"] = $_REQUEST["app_secret"];

						foreach (self::$fieldmap as $key => $info)
						{
							$sso_settings["sso_facebook"]["map_" . $key] = (SSO_IsField($_REQUEST["map_" . $key]) ? $_REQUEST["map_" . $key] : "");
						}

						$sso_settings["sso_facebook"]["username_blacklist"] = $_REQUEST["username_blacklist"];
						$sso_settings["sso_facebook"]["email_bad_domains"] = $_REQUEST["email_bad_domains"];

						if (!SSO_SaveSettings())  BB_SetPageMessage("error", "Unable to save settings.");
						else if (BB_GetPageMessageType() == "info")  SSO_ConfigRedirect("config", array(), "info", $_REQUEST["bb_msg"] . "  " . BB_Translate("Successfully updated the %s provider configuration.", $this->DisplayName()));
						else  SSO_ConfigRedirect("config", array(), "success", BB_Translate("Successfully updated the %s provider configuration.", $this->DisplayName()));
					}
				}

				$contentopts = array(
					"desc" => BB_Translate("Configure the %s provider.  Mapping additional fields that require extra permissions will significantly reduce the likelihood the user will sign in.", $this->DisplayName()),
					"nonce" => "action",
					"hidden" => array(
						"action" => "config",
						"provider" => "sso_facebook",
						"action2" => "config",
						"configsave" => "1"
					),
					"fields" => array(
						array(
							"title" => "Facebook App ID",
							"type" => "text",
							"name" => "app_id",
							"value" => BB_GetValue("app_id", $sso_settings["sso_facebook"]["app_id"]),
							"htmldesc" => "You get a Facebook App ID when you <a href=\"https://developers.facebook.com/\" target=\"_blank\">register as a Facebook developer</a> and then <a href=\"https://developers.facebook.com/apps\" target=\"_blank\">create a Facebook application</a>.  This provider will not work without an App ID."
						),
						array(
							"title" => "Facebook App Secret",
							"type" => "text",
							"name" => "app_secret",
							"value" => BB_GetValue("app_secret", $sso_settings["sso_facebook"]["app_secret"]),
							"htmldesc" => "You get a Facebook App Secret when you <a href=\"https://developers.facebook.com/\" target=\"_blank\">register as a Facebook developer</a> and then <a href=\"https://developers.facebook.com/apps\" target=\"_blank\">create a Facebook application</a>.  This provider will not work without an App Secret."
						),
					),
					"submit" => "Save",
					"focus" => true
				);

				foreach (self::$fieldmap as $key => $info)
				{
					$contentopts["fields"][] = array(
						"title" => BB_Translate("Map %s", $info["title"]),
						"type" => "select",
						"name" => "map_" . $key,
						"options" => $sso_select_fields,
						"select" => BB_GetValue("map_". $key, (string)$sso_settings["sso_facebook"]["map_" . $key]),
						"desc" => ($info["extra"] == "" ? BB_Translate("The field in the SSO system to map the %s to.%s", BB_Translate($info["desc"]), (isset($info["notes"]) ? "  " . BB_Translate($info["notes"]) : "")) : BB_Translate("The field in the SSO system to map the %s to.  Mapping this field will request the '%s' permission from the user" . ($info["extra"] != "email" ? " and will require approval from Facebook" : "") . ".%s", BB_Translate($info["desc"]), $info["extra"], (isset($info["notes"]) ? "  " . BB_Translate($info["notes"]) : "")))
					);
				}

				$contentopts["fields"][] = array(
					"title" => "Username Blacklist",
					"type" => "textarea",
					"height" => "300px",
					"name" => "username_blacklist",
					"value" => BB_GetValue("username_blacklist", $sso_settings["sso_facebook"]["username_blacklist"]),
					"desc" => "A blacklist of words that a username may not contain.  One per line.  Username must be mapped."
				);
				$contentopts["fields"][] = array(
					"title" => "E-mail Domain Blacklist",
					"type" => "textarea",
					"height" => "300px",
					"name" => "email_bad_domains",
					"value" => BB_GetValue("email_bad_domains", $sso_settings["sso_facebook"]["email_bad_domains"]),
					"desc" => "A blacklist of e-mail address domains that are not allowed to create accounts.  One per line.  E-mail Address must be mapped."
				);

				SSO_AppendIPFields($contentopts, $sso_settings["sso_facebook"]["iprestrict"]);

				BB_GeneratePage(BB_Translate("Configure %s", $this->DisplayName()), $sso_menuopts, $contentopts);
			}
			else if ($sso_site_admin && $sso_settings["sso_facebook"]["enabled"] && $_REQUEST["action2"] == "disable")
			{
				$sso_settings["sso_facebook"]["enabled"] = false;

				if (!SSO_SaveSettings())  BB_RedirectPage("error", "Unable to save settings.");
				else  BB_RedirectPage("success", BB_Translate("Successfully disabled the %s provider.", $this->DisplayName()));
			}
			else if ($sso_site_admin && !$sso_settings["sso_facebook"]["enabled"] && $_REQUEST["action2"] == "enable")
			{
				$sso_settings["sso_facebook"]["enabled"] = true;

				if (!SSO_SaveSettings())  BB_RedirectPage("error", "Unable to save settings.");
				else  BB_RedirectPage("success", BB_Translate("Successfully enabled the %s provider.", $this->DisplayName()));
			}
		}

		public function IsEnabled()
		{
			global $sso_settings;

			if (!$sso_settings["sso_facebook"]["enabled"])  return false;

			if ($sso_settings["sso_facebook"]["app_id"] == "" || $sso_settings["sso_facebook"]["app_secret"] == "")  return false;

			if (!SSO_IsIPAllowed($sso_settings["sso_facebook"]["iprestrict"]) || SSO_IsSpammer($sso_settings["sso_facebook"]["iprestrict"]))  return false;

			return true;
		}

		public function GetProtectedFields()
		{
			global $sso_settings;

			$result = array();
			foreach (self::$fieldmap as $key => $info)
			{
				$key2 = $sso_settings["sso_facebook"]["map_" . $key];
				if ($key2 != "")  $result[$key2] = true;
			}

			return $result;
		}

		public function GenerateSelector()
		{
			global $sso_target_url;
?>
<div class="sso_selector">
	<a class="sso_facebook" href="<?php echo htmlspecialchars($sso_target_url); ?>"><?php echo htmlspecialchars($this->DisplayName()); ?></a>
</div>
<?php
		}

		public function ProcessFrontend()
		{
			global $sso_provider, $sso_settings, $sso_target_url, $sso_header, $sso_footer, $sso_providers, $sso_selectors_url;

			require_once SSO_ROOT_PATH . "/" . SSO_PROVIDER_PATH . "/" . $sso_provider . "/facebook.php";

			$facebook = new SSO_FacebookSDK(array(
				"appId" => $sso_settings["sso_facebook"]["app_id"],
				"secret" => $sso_settings["sso_facebook"]["app_secret"],
			));

			$id = $facebook->getUser();
			if ($id)
			{
				try
				{
					// Calculate the required fields.
					$fields = array("id" => true, "first_name" => true, "last_name" => true);
					foreach (self::$fieldmap as $key => $info)
					{
						if ($sso_settings["sso_facebook"]["map_" . $key] != "" && !isset($info["pseudo"]))  $fields[(isset($info["parent"]) ? $info["parent"] : $key)] = true;
					}

					$profile = $facebook->api("/me", "GET", array("fields" => implode(",", array_keys($fields))));
				}
				catch (FacebookApiException $e)
				{
					// Fall through here to go to the next step.
					$id = 0;
					$exceptionmessage = $e->getMessage();
				}
			}

			if (isset($_REQUEST["sso_facebook_action"]) && $_REQUEST["sso_facebook_action"] == "signin")
			{
				if ($id)
				{
					// Create a fake username based on available information.
					if ($sso_settings["sso_facebook"]["map_username"] != "")
					{
						if (isset($profile["email"]))  $profile["username"] = (string)@substr($profile["email"], 0, strpos($profile["email"], "@"));
						else if (isset($profile["first_name"]) && isset($profile["last_name"]))  $profile["username"] = $profile["first_name"] . @substr($profile["last_name"], 0, 1);
						else if (isset($profile["name"]))
						{
							$name = explode(" ", $name);
							$profile["username"] = $name[0] . @substr($name[count($name) - 1], 0, 1);
						}
						else  $profile["username"] = (string)$id;

						$profile["username"] = preg_replace('/\s+/', "_", trim(preg_replace('/[^a-z0-9]/', " ", strtolower((string)$profile["username"]))));
					}

					// Check username blacklist.
					$message = "";
					if (isset($profile["username"]))
					{
						$blacklist = explode("\n", str_replace("\r", "\n", $sso_settings["sso_facebook"]["username_blacklist"]));
						foreach ($blacklist as $word)
						{
							$word = trim($word);
							if ($word != "" && stripos($profile["username"], $word) !== false)
							{
								$message = BB_Translate("Username contains a blocked word.");
								break;
							}
						}
					}

					// Check e-mail domain blacklist.
					if (isset($profile["email"]))
					{
						define("CS_TRANSLATE_FUNC", "BB_Translate");
						require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/smtp.php";

						$email = SMTP::MakeValidEmailAddress($profile["email"]);
						if (!$email["success"])  $message = BB_Translate("Invalid e-mail address.  %s", $email["error"]);
						else
						{
							$domain = strtolower(substr($email["email"], strrpos($email["email"], "@") + 1));
							$y = strlen($domain);
							$baddomains = explode("\n", strtolower($sso_settings["sso_facebook"]["email_bad_domains"]));
							foreach ($baddomains as $baddomain)
							{
								$baddomain = trim($baddomain);
								if ($baddomain != "")
								{
									$y2 = strlen($baddomain);
									if ($domain == $baddomain || ($y < $y2 && substr($domain, $y - $y2 - 1, 1) == "." && substr($domain, $y - $y2) == $baddomain))
									{
										$message = BB_Translate("E-mail address is in a blacklisted domain.");

										break;
									}
								}
							}
						}
					}

					if ($message == "")
					{
						// Fix birthday to be in international format YYYY-MM-DD.
						if (isset($profile["birthday"]))
						{
							$birthday = explode("/", $profile["birthday"]);
							$year = array_pop($birthday);
							array_unshift($birthday, $year);
							$profile["birthday"] = implode("-", $birthday);
						}

						// Convert most profile fields into strings.
						foreach ($profile as $key => $val)
						{
							if (is_string($val))  continue;

							if (is_bool($val))  $val = (string)(int)$val;
							else if (is_numeric($val))  $val = (string)$val;
							else if (is_object($val) && isset($val->id) && isset($val->name))  $val = $val->name;

							$profile[$key] = $val;
						}

						// Convert specialized fields into strings.
						if (isset($profile["age_range"]))
						{
							$profile["age_range"] = trim($item->min . "-" . $item->max);
						}
						if (isset($profile["education"]))
						{
							$items = array();
							foreach ($profile["education"] as $item)  $items[] = trim($item->year . " " . $item->type . " " . $item->school->name);
							$profile["education"] = implode("\n", $items);
						}
						if (isset($profile["work"]))
						{
							$items = array();
							foreach ($profile["work"] as $item)  $items[] = trim($item->employer . ", " . $item->location . ", " . $item->position);
							$profile["work"] = implode("\n", $items);
						}

						$mapinfo = array();
						foreach (self::$fieldmap as $key => $info)
						{
							$key2 = $sso_settings["sso_facebook"]["map_" . $key];
							if ($key2 != "" && isset($profile[$key]))  $mapinfo[$key2] = $profile[$key];
						}

						// Process specialized fields.
						if (isset($profile["birthday"]))
						{
							$birthday = explode("-", $profile["birthday"]);
							$key = "birthday_year";
							$key2 = $sso_settings["sso_facebook"]["map_" . $key];
							if ($key2 != "")  $mapinfo[$key2] = $birthday[0];

							$key = "birthday_month";
							$key2 = $sso_settings["sso_facebook"]["map_" . $key];
							if ($key2 != "")  $mapinfo[$key2] = $birthday[1];

							$key = "birthday_day";
							$key2 = $sso_settings["sso_facebook"]["map_" . $key];
							if ($key2 != "")  $mapinfo[$key2] = $birthday[2];
						}

						SSO_ActivateUser($profile["id"], serialize($profile), $mapinfo);

						// Only falls through on account lockout or a fatal error.
						$message = BB_Translate("User activation failed.");
					}
				}
				else if (isset($_REQUEST["error"]) && $_REQUEST["error"] == "access_denied")
				{
					$message = BB_Translate("The request to sign in with Facebook was denied.");
				}
				else if (isset($_REQUEST["error_description"]))
				{
					$message = BB_Translate($_REQUEST["error_description"]);
				}
				else
				{
					$message = BB_Translate("An unknown error occurred.  %s", $exceptionmessage);
				}

				$message = BB_Translate("Sign in failed.  %s", $message);

				echo $sso_header;

				SSO_OutputHeartbeat();
?>
<div class="sso_main_wrap">
<div class="sso_main_wrap_inner">
	<div class="sso_main_messages_wrap">
		<div class="sso_main_messages">
			<div class="sso_main_messageerror"><?php echo htmlspecialchars($message); ?></div>
		</div>
	</div>

	<div class="sso_main_info"><a href="<?php echo htmlspecialchars($sso_target_url); ?>"><?php echo htmlspecialchars(BB_Translate("Try again")); ?></a><?php if (count($sso_providers) > 1)  { ?> | <a href="<?php echo htmlspecialchars($sso_selectors_url); ?>"><?php echo htmlspecialchars(BB_Translate("Select another sign in method")); ?></a><?php } ?></div>
</div>
</div>
<?php
				echo $sso_footer;
			}
			else
			{
				// Calculate the required scope.
				$scope = array();
				foreach (self::$fieldmap as $key => $info)
				{
					if ($info["extra"] != "" && $sso_settings["sso_facebook"]["map_" . $key] != "")  $scope[$info["extra"]] = true;
				}

				// Get the login redirection URL.
				$options = array(
					"scope" => implode(",", array_keys($scope)),
					"redirect_uri" => BB_GetRequestHost() . $sso_target_url . "&sso_facebook_action=signin"
				);
				$url = $facebook->getLoginUrl($options);

				SSO_ExternalRedirect($url);
			}
		}
	}
?>