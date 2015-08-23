<?php
	// SSO LinkedIn Account Provider
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	if (!defined("SSO_FILE"))  exit();

	class sso_linkedin extends SSO_ProviderBase
	{
		// See:
		// http://developer.linkedin.com/documents/authentication
		private static $fieldmap = array(
			"firstName" => array("title" => "First Name", "desc" => "user's first name", "extra" => ""),
			"lastName" => array("title" => "Last Name", "desc" => "user's last name", "extra" => ""),
			"maidenName" => array("title" => "Maiden Name", "desc" => "user's maiden name", "extra" => ""),
			"formattedName" => array("title" => "Full Name", "desc" => "user's full name", "extra" => ""),
			"phoneticFirstName" => array("title" => "Phonetic First Name", "desc" => "user's first name spelled phonetically", "extra" => ""),
			"phoneticLastName" => array("title" => "Phonetic Last Name", "desc" => "user's last name spelled phonetically", "extra" => ""),
			"formattedPhoneticName" => array("title" => "Phonetic Full Name", "desc" => "user's full name spelled phonetically", "extra" => ""),
			"headline" => array("title" => "Headline", "desc" => "user's headline (usually '[Job Title] at [Company]')", "extra" => ""),
			"location_name" => array("title" => "Location - Name", "desc" => "user's location (generic name such as 'San Francisco Bay Area')", "extra" => ""),
			"location_country_code" => array("title" => "Location - Country Code", "desc" => "user's country code", "extra" => ""),
			"industry" => array("title" => "Industry Code", "desc" => "user's industry code", "extra" => ""),
			"currentShare" => array("title" => "Current Share", "desc" => "user's current share", "extra" => ""),
			"currentStatus" => array("title" => "Current Status", "desc" => "user's current status", "extra" => ""),
			"numConnections" => array("title" => "Number of Connections", "desc" => "user's connection count", "extra" => ""),
			"summary" => array("title" => "Summary", "desc" => "user's professional profile", "extra" => ""),
			"specialties" => array("title" => "Specialties", "desc" => "user's specialties", "extra" => ""),
			"pictureUrl" => array("title" => "Profile Photo URL", "desc" => "user's profile picture", "extra" => ""),
			"siteStandardProfileRequest_url" => array("title" => "Profile URL", "desc" => "user's profile URL", "extra" => ""),
			"emailAddress" => array("title" => "E-mail Address", "desc" => "user's e-mail address", "extra" => "r_emailaddress"),
		);

		public function Init()
		{
			global $sso_settings;

			if (!isset($sso_settings["sso_linkedin"]["client_id"]))  $sso_settings["sso_linkedin"]["client_id"] = "";
			if (!isset($sso_settings["sso_linkedin"]["client_secret"]))  $sso_settings["sso_linkedin"]["client_secret"] = "";
			if (!isset($sso_settings["sso_linkedin"]["enabled"]))  $sso_settings["sso_linkedin"]["enabled"] = false;
			if (!isset($sso_settings["sso_linkedin"]["email_bad_domains"]))  $sso_settings["sso_linkedin"]["email_bad_domains"] = "";
			if (!isset($sso_settings["sso_linkedin"]["iprestrict"]))  $sso_settings["sso_linkedin"]["iprestrict"] = SSO_InitIPFields();

			foreach (self::$fieldmap as $key => $info)
			{
				if (!isset($sso_settings["sso_linkedin"]["map_" . $key]) || !SSO_IsField($sso_settings["sso_linkedin"]["map_" . $key]))  $sso_settings["sso_linkedin"]["map_" . $key] = "";
			}
		}

		public function DisplayName()
		{
			return BB_Translate("LinkedIn");
		}

		public function DefaultOrder()
		{
			return 100;
		}

		public function MenuOpts()
		{
			global $sso_site_admin, $sso_settings;

			$result = array(
				"name" => "LinkedIn Login"
			);

			if ($sso_site_admin)
			{
				if ($sso_settings["sso_linkedin"]["enabled"])
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
			global $sso_site_admin, $sso_settings, $sso_menuopts, $sso_select_fields, $sso_provider;

			if ($sso_site_admin && $sso_settings["sso_linkedin"]["enabled"] && $_REQUEST["action2"] == "config")
			{
				if (isset($_REQUEST["configsave"]))
				{
					$_REQUEST["client_id"] = trim($_REQUEST["client_id"]);
					$_REQUEST["client_secret"] = trim($_REQUEST["client_secret"]);

					if ($_REQUEST["client_id"] == "")  BB_SetPageMessage("info", "The 'LinkedIn API Client ID' field is empty.");
					else if ($_REQUEST["client_secret"] == "")  BB_SetPageMessage("info", "The 'LinkedIn API Client Secret' field is empty.");

					$sso_settings["sso_linkedin"]["iprestrict"] = SSO_ProcessIPFields();

					if (BB_GetPageMessageType() != "error")
					{
						$sso_settings["sso_linkedin"]["client_id"] = $_REQUEST["client_id"];
						$sso_settings["sso_linkedin"]["client_secret"] = $_REQUEST["client_secret"];

						foreach (self::$fieldmap as $key => $info)
						{
							$sso_settings["sso_linkedin"]["map_" . $key] = (SSO_IsField($_REQUEST["map_" . $key]) ? $_REQUEST["map_" . $key] : "");
						}

						$sso_settings["sso_linkedin"]["email_bad_domains"] = $_REQUEST["email_bad_domains"];

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
						"provider" => "sso_linkedin",
						"action2" => "config",
						"configsave" => "1"
					),
					"fields" => array(
						array(
							"title" => "LinkedIn API Redirect URI",
							"type" => "static",
							"value" => BB_GetRequestHost() . SSO_ROOT_URL . "/index.php?sso_provider=" . urlencode($sso_provider) . "&sso_linkedin_action=signin",
							"htmldesc" => "<br />When you <a href=\"https://www.linkedin.com/secure/developer\" target=\"_blank\">create a LinkedIn application</a>, use the above URL for the 'OAuth 2.0 Redirect URLs'.  This provider will not work without a correct Redirect URI."
						),
						array(
							"title" => "LinkedIn API Client ID",
							"type" => "text",
							"name" => "client_id",
							"value" => BB_GetValue("client_id", $sso_settings["sso_linkedin"]["client_id"]),
							"htmldesc" => "You get a LinkedIn API Client ID (aka API Key) when you <a href=\"https://www.linkedin.com/secure/developer\" target=\"_blank\">create a LinkedIn application</a>.  This provider will not work without a Client ID."
						),
						array(
							"title" => "LinkedIn API Client Secret",
							"type" => "text",
							"name" => "client_secret",
							"value" => BB_GetValue("client_secret", $sso_settings["sso_linkedin"]["client_secret"]),
							"htmldesc" => "You get a LinkedIn API Client Secret (aka API Secret) when you <a href=\"https://www.linkedin.com/secure/developer\" target=\"_blank\">create a LinkedIn application</a>.  This provider will not work without a Client Secret."
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
						"select" => BB_GetValue("map_" . $key, (string)$sso_settings["sso_linkedin"]["map_" . $key]),
						"desc" => ($info["extra"] == "" ? BB_Translate("The field in the SSO system to map the %s to.%s", BB_Translate($info["desc"]), (isset($info["notes"]) ? "  " . BB_Translate($info["notes"]) : "")) : BB_Translate("The field in the SSO system to map the %s to.  Mapping this field will request the '%s' permission from the user.%s", BB_Translate($info["desc"]), $info["extra"], (isset($info["notes"]) ? "  " . BB_Translate($info["notes"]) : "")))
					);
				}

				$contentopts["fields"][] = array(
					"title" => "E-mail Domain Blacklist",
					"type" => "textarea",
					"height" => "300px",
					"name" => "email_bad_domains",
					"value" => BB_GetValue("email_bad_domains", $sso_settings["sso_linkedin"]["email_bad_domains"]),
					"desc" => "A blacklist of e-mail address domains that are not allowed to create accounts.  One per line.  E-mail Address must be mapped."
				);

				SSO_AppendIPFields($contentopts, $sso_settings["sso_linkedin"]["iprestrict"]);

				BB_GeneratePage(BB_Translate("Configure %s", $this->DisplayName()), $sso_menuopts, $contentopts);
			}
			else if ($sso_site_admin && $sso_settings["sso_linkedin"]["enabled"] && $_REQUEST["action2"] == "disable")
			{
				$sso_settings["sso_linkedin"]["enabled"] = false;

				if (!SSO_SaveSettings())  BB_RedirectPage("error", "Unable to save settings.");
				else  BB_RedirectPage("success", BB_Translate("Successfully disabled the %s provider.", $this->DisplayName()));
			}
			else if ($sso_site_admin && !$sso_settings["sso_linkedin"]["enabled"] && $_REQUEST["action2"] == "enable")
			{
				$sso_settings["sso_linkedin"]["enabled"] = true;

				if (!SSO_SaveSettings())  BB_RedirectPage("error", "Unable to save settings.");
				else  BB_RedirectPage("success", BB_Translate("Successfully enabled the %s provider.", $this->DisplayName()));
			}
		}

		public function IsEnabled()
		{
			global $sso_settings;

			if (!$sso_settings["sso_linkedin"]["enabled"])  return false;

			if ($sso_settings["sso_linkedin"]["client_id"] == "" || $sso_settings["sso_linkedin"]["client_secret"] == "")  return false;

			if (!SSO_IsIPAllowed($sso_settings["sso_linkedin"]["iprestrict"]) || SSO_IsSpammer($sso_settings["sso_linkedin"]["iprestrict"]))  return false;

			return true;
		}

		public function GetProtectedFields()
		{
			global $sso_settings;

			$result = array();
			foreach (self::$fieldmap as $key => $info)
			{
				$key2 = $sso_settings["sso_linkedin"]["map_" . $key];
				if ($key2 != "")  $result[$key2] = true;
			}

			return $result;
		}

		public function GenerateSelector()
		{
			global $sso_target_url;
?>
<div class="sso_selector">
	<a class="sso_linkedin" href="<?php echo htmlspecialchars($sso_target_url); ?>"><?php echo htmlspecialchars($this->DisplayName()); ?></a>
</div>
<?php
		}

		private function DisplayError($message)
		{
			global $sso_header, $sso_footer, $sso_target_url, $sso_providers, $sso_selectors_url;

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

		public function ProcessFrontend()
		{
			global $sso_rng, $sso_provider, $sso_settings, $sso_session_info;

			$redirect_uri = BB_GetRequestHost() . SSO_ROOT_URL . "/index.php?sso_provider=" . urlencode($sso_provider) . "&sso_linkedin_action=signin";

			if (isset($_REQUEST["sso_linkedin_action"]) && $_REQUEST["sso_linkedin_action"] == "signin")
			{
				// Recover the language settings.
				if (!isset($sso_session_info["sso_linkedin_info"]))
				{
					$this->DisplayError(BB_Translate("Unable to authenticate the request."));

					return;
				}

				$url = BB_GetRequestHost() . SSO_ROOT_URL . "/index.php?sso_provider=" . urlencode($sso_provider) . "&sso_linkedin_action=signin2";
				if (isset($_REQUEST["state"]))  $url .= "&state=" . urlencode($_REQUEST["state"]);
				if (isset($_REQUEST["code"]))  $url .= "&code=" . urlencode($_REQUEST["code"]);
				if (isset($_REQUEST["error"]))  $url .= "&error=" . urlencode($_REQUEST["error"]);
				if (isset($_REQUEST["error_description"]))  $url .= "&error_description=" . urlencode($_REQUEST["error_description"]);
				$url .= "&lang=" . urlencode($sso_session_info["sso_linkedin_info"]["lang"]);

				header("Location: " . $url);
			}
			else if (isset($_REQUEST["sso_linkedin_action"]) && $_REQUEST["sso_linkedin_action"] == "signin2")
			{
				// Validate the token.
				if (!isset($_REQUEST["state"]) || !isset($sso_session_info["sso_linkedin_info"]) || $_REQUEST["state"] !== $sso_session_info["sso_linkedin_info"]["token"])
				{
					$this->DisplayError(BB_Translate("Unable to authenticate the request."));

					return;
				}

				// Check for token expiration.
				if (CSDB::ConvertFromDBTime($sso_session_info["sso_linkedin_info"]["expires"]) < time())
				{
					$this->DisplayError(BB_Translate("Verification token has expired."));

					return;
				}

				if (isset($_REQUEST["error"]))
				{
					if ($_REQUEST["error"] == "access_denied")  $message = BB_Translate("The request to sign in with Google was denied.");
					else if (isset($_REQUEST["error_description"]))  $message = BB_Translate("The error message returned was '%s'.  %s", $_REQUEST["error"], $_REQUEST["error_description"]);
					else  $message = BB_Translate("The error message returned was '%s'.", $_REQUEST["error"]);

					$this->DisplayError(BB_Translate("Sign in failed.  %s", $message));

					return;
				}

				if (!isset($_REQUEST["code"]))
				{
					$this->DisplayError(BB_Translate("Sign in failed.  Authorization code missing."));

					return;
				}

				// Get an access token from the authorization code.
				require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/http.php";
				require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/web_browser.php";

				$url = "https://www.linkedin.com/uas/oauth2/accessToken";
				$options = array(
					"postvars" => array(
						"grant_type" => "authorization_code",
						"code" => $_REQUEST["code"],
						"client_id" => $sso_settings["sso_linkedin"]["client_id"],
						"client_secret" => $sso_settings["sso_linkedin"]["client_secret"],
						"redirect_uri" => $redirect_uri
					)
				);
				$web = new WebBrowser();
				$result = $web->Process($url, "auto", $options);

				if (!$result["success"])  $this->DisplayError(BB_Translate("Sign in failed.  Error retrieving URL for LinkedIn access token.  %s", $result["error"]));
				else if ($result["response"]["code"] != 200)  $this->DisplayError(BB_Translate("Sign in failed.  The LinkedIn access token server returned:  %s", $result["response"]["code"] . " " . $result["response"]["meaning"]));
				else
				{
					// Get the access token.
					$data = @json_decode($result["body"], true);
					if ($data === false || !isset($data["access_token"]))  $this->DisplayError(BB_Translate("Sign in failed.  Error retrieving access token from LinkedIn."));
					else
					{
						// Get the user's profile information.
						$fields = array("id" => true);
						foreach (self::$fieldmap as $key => $info)
						{
							if ($sso_settings["sso_linkedin"]["map_" . $key] != "")
							{
								$key2 = explode("_", $key);
								$fields[$key2[0]] = true;
							}
						}

						$url = "https://api.linkedin.com/v1/people/~:(" . implode(",", array_keys($fields)) . ")?format=json&oauth2_access_token=" . urlencode($data["access_token"]);
						$result = $web->Process($url);

						if (!$result["success"])  $this->DisplayError(BB_Translate("Sign in failed.  Error retrieving URL for LinkedIn profile information.  %s", $result["error"]));
						else if ($result["response"]["code"] != 200)  $this->DisplayError(BB_Translate("Sign in failed.  The LinkedIn profile information server returned:  %s", $result["response"]["code"] . " " . $result["response"]["meaning"]));
						else
						{
							$profile = @json_decode($result["body"], true);
							if ($profile === false)  $this->DisplayError(BB_Translate("Sign in failed.  Error retrieving profile information from LinkedIn."));
							{
								$origprofile = $profile;

								// Convert most profile fields into strings.
								foreach ($profile as $key => $val)
								{
									if (is_string($val))  continue;

									if (is_bool($val))  $val = (string)(int)$val;
									else if (is_numeric($val))  $val = (string)$val;

									$profile[$key] = $val;
								}

								$mapinfo = array();
								foreach (self::$fieldmap as $key => $info)
								{
									$key2 = $sso_settings["sso_linkedin"]["map_" . $key];
									if ($key2 != "")
									{
										$key = explode("_", $key);
										$val = $profile;
										while (count($key) > 1)
										{
											$key3 = array_shift($key);
											if (isset($val[$key3]))  $val = $val[$key3];
										}

										if (isset($val[$key[0]]))  $mapinfo[$key2] = $val[$key[0]];
									}
								}

								SSO_ActivateUser($profile["id"], serialize($origprofile), $mapinfo);

								// Only falls through on account lockout or a fatal error.
								$this->DisplayError(BB_Translate("User activation failed."));
							}
						}
					}
				}
			}
			else
			{
				// Create internal data packet.
				$token = $sso_rng->GenerateString();
				$sso_session_info["sso_linkedin_info"] = array(
					"lang" => (isset($_REQUEST["lang"]) ? $_REQUEST["lang"] : ""),
					"token" => $token,
					"expires" => CSDB::ConvertToDBTime(time() + 30 * 60)
				);
				if (!SSO_SaveSessionInfo())
				{
					$this->DisplayError(BB_Translate("Unable to save session information."));

					return;
				}

				// Calculate the required scope.  LinkedIn doesn't use numeric IDs since e-mail is the unique identifier.
				$scope = array("r_basicprofile" => true);
				foreach (self::$fieldmap as $key => $info)
				{
					if ($info["extra"] != "" && $sso_settings["sso_linkedin"]["map_" . $key] != "")  $scope[$info["extra"]] = true;
				}

				// Get the login redirection URL.
				$options = array(
					"response_type" => "code",
					"client_id" => $sso_settings["sso_linkedin"]["client_id"],
					"scope" => implode(" ", array_keys($scope)),
					"state" => $token,
					"redirect_uri" => $redirect_uri
				);
				$options2 = array();
				foreach ($options as $key => $val)  $options2[] = urlencode($key) . "=" . urlencode($val);
				$url = "https://www.linkedin.com/uas/oauth2/authorization?" . implode("&", $options2);

				SSO_ExternalRedirect($url);
			}
		}
	}
?>