<?php
	// SSO LDAP Sign In Provider
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	if (!defined("SSO_FILE"))  exit();

	class sso_ldap extends SSO_ProviderBase
	{
		public function Init()
		{
			global $sso_settings;

			if (!isset($sso_settings["sso_ldap"]["server"]))  $sso_settings["sso_ldap"]["server"] = "";
			if (!isset($sso_settings["sso_ldap"]["dn"]))  $sso_settings["sso_ldap"]["dn"] = "";
			if (!isset($sso_settings["sso_ldap"]["enabled"]))  $sso_settings["sso_ldap"]["enabled"] = false;
			if (!isset($sso_settings["sso_ldap"]["map_username"]) || !SSO_IsField($sso_settings["sso_ldap"]["map_username"]))  $sso_settings["sso_ldap"]["map_username"] = "";
			if (!isset($sso_settings["sso_ldap"]["remove_domain"]))  $sso_settings["sso_ldap"]["remove_domain"] = true;
			if (!isset($sso_settings["sso_ldap"]["map_custom"]))  $sso_settings["sso_ldap"]["map_custom"] = "";
			if (!isset($sso_settings["sso_ldap"]["password"]))  $sso_settings["sso_ldap"]["password"] = true;
			if (!isset($sso_settings["sso_ldap"]["debug"]))  $sso_settings["sso_ldap"]["debug"] = false;
			if (!isset($sso_settings["sso_ldap"]["iprestrict"]))  $sso_settings["sso_ldap"]["iprestrict"] = SSO_InitIPFields();
		}

		public function DisplayName()
		{
			return BB_Translate("LDAP Login");
		}

		public function DefaultOrder()
		{
			return 100;
		}

		public function MenuOpts()
		{
			global $sso_site_admin, $sso_settings;

			$result = array(
				"name" => "LDAP Login"
			);

			if ($sso_site_admin)
			{
				if ($sso_settings["sso_ldap"]["enabled"])
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

			if ($sso_site_admin && $sso_settings["sso_ldap"]["enabled"] && $_REQUEST["action2"] == "config")
			{
				if (isset($_REQUEST["configsave"]))
				{
					$_REQUEST["server"] = trim($_REQUEST["server"]);
					$_REQUEST["dn"] = trim($_REQUEST["dn"]);

					if ($_REQUEST["server"] == "")  BB_SetPageMessage("info", "The 'LDAP Server URL' field is empty.");
					else if ($_REQUEST["dn"] == "")  BB_SetPageMessage("info", "The 'LDAP Distinguished Name' field is empty.");
					else if (!function_exists("ldap_connect"))  BB_SetPageMessage("info", "The ldap_connect() function does not exist.  LDAP won't work until the LDAP PHP extension is enabled.");

					require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/http.php";
					$url = HTTP::ExtractURL($_REQUEST["server"]);
					if ($url["scheme"] != "ldap")  BB_SetPageMessage("error", "The 'LDAP Server URL' field has an invalid scheme.");
					else if ($url["host"] == "")  BB_SetPageMessage("error", "The 'LDAP Server URL' field has an invalid host.");

					$sso_settings["sso_ldap"]["iprestrict"] = SSO_ProcessIPFields();

					if (BB_GetPageMessageType() != "error")
					{
						$sso_settings["sso_ldap"]["server"] = $_REQUEST["server"];
						$sso_settings["sso_ldap"]["dn"] = $_REQUEST["dn"];
						$sso_settings["sso_ldap"]["map_username"] = (SSO_IsField($_REQUEST["map_username"]) ? $_REQUEST["map_username"] : "");
						$sso_settings["sso_ldap"]["remove_domain"] = ($_REQUEST["remove_domain"] > 0);
						$sso_settings["sso_ldap"]["map_custom"] = trim($_REQUEST["map_custom"]);
						$sso_settings["sso_ldap"]["password"] = ($_REQUEST["password"] > 0);
						$sso_settings["sso_ldap"]["debug"] = ($_REQUEST["debug"] > 0);

						if (!SSO_SaveSettings())  BB_SetPageMessage("error", "Unable to save settings.");
						else if (BB_GetPageMessageType() == "info")  SSO_ConfigRedirect("config", array(), "info", $_REQUEST["bb_msg"] . "  " . BB_Translate("Successfully updated the %s provider configuration.", $this->DisplayName()));
						else  SSO_ConfigRedirect("config", array(), "success", BB_Translate("Successfully updated the %s provider configuration.", $this->DisplayName()));
					}
				}

				$contentopts = array(
					"desc" => BB_Translate("Configure the %s provider.  This provider is intended to be used behind a firewall in a relatively trusted environment.  Use the IP whitelist to control access to this provider.", $this->DisplayName()),
					"nonce" => "action",
					"hidden" => array(
						"action" => "config",
						"provider" => "sso_ldap",
						"action2" => "config",
						"configsave" => "1"
					),
					"fields" => array(
						array(
							"title" => "LDAP Server URL",
							"type" => "text",
							"name" => "server",
							"value" => BB_GetValue("server", $sso_settings["sso_ldap"]["server"]),
							"desc" => "The LDAP URL to a LDAP server.  Should be in the format 'ldap://server[:port]/'.  Default port is 389."
						),
						array(
							"title" => "LDAP Distinguished Name",
							"type" => "text",
							"name" => "dn",
							"value" => BB_GetValue("dn", $sso_settings["sso_ldap"]["dn"]),
							"desc" => "The LDAP Distinguished Name (DN) pattern to use to check logins against and load user information.  Should be in the format 'CN=@USERNAME@,OU=users,DC=somewhere,DC=com' or similar.  The special string @USERNAME@ will be replaced with the username."
						),
						array(
							"title" => "Map Username",
							"type" => "select",
							"name" => "map_username",
							"options" => $sso_select_fields,
							"select" => BB_GetValue("map_username", (string)$sso_settings["sso_ldap"]["map_username"]),
							"desc" => "The field in the SSO system to map the username to.  Overrides any custom mapping."
						),
						array(
							"title" => "Remove Domain",
							"type" => "select",
							"name" => "remove_domain",
							"options" => array(1 => "Yes", 0 => "No"),
							"select" => BB_GetValue("remove_domain", (string)(int)$sso_settings["sso_ldap"]["remove_domain"]),
							"desc" => "Remove domain prefix from the above mapped username.  (e.g. 'NT\username' becomes 'username')"
						),
						array(
							"title" => "Custom Mapping",
							"type" => "textarea",
							"name" => "map_custom",
							"value" => BB_GetValue("map_custom", $sso_settings["sso_ldap"]["map_custom"]),
							"desc" => "The fields in the SSO system to map LDAP fields to.  Format is 'ldapfield=ssofield'.  One mapping per line.  See 'Debugging Mode' below to turn on debugging to discover valid LDAP field names.  See the 'Map Username' dropdown above for valid SSO field names."
						),
						array(
							"title" => "Require Password",
							"type" => "select",
							"name" => "password",
							"options" => array(1 => "Yes", 0 => "No"),
							"select" => BB_GetValue("password", (string)(int)$sso_settings["sso_ldap"]["password"]),
							"desc" => "Require passwords to not be empty strings."
						),
						array(
							"title" => "Debugging Mode",
							"type" => "select",
							"name" => "debug",
							"options" => array(1 => "Yes", 0 => "No"),
							"select" => BB_GetValue("debug", (string)(int)$sso_settings["sso_ldap"]["debug"]),
							"desc" => "Turn on debugging mode to get an idea of what LDAP fields are available for your LDAP server.  When enabled and a login is successful, this will output the fields and data of the user, then output successfully mapped LDAP to SSO fields, and then exit."
						),
					),
					"submit" => "Save",
					"focus" => true
				);

				SSO_AppendIPFields($contentopts, $sso_settings["sso_ldap"]["iprestrict"]);

				BB_GeneratePage(BB_Translate("Configure %s", $this->DisplayName()), $sso_menuopts, $contentopts);
			}
			else if ($sso_site_admin && $sso_settings["sso_ldap"]["enabled"] && $_REQUEST["action2"] == "disable")
			{
				$sso_settings["sso_ldap"]["enabled"] = false;

				if (!SSO_SaveSettings())  BB_RedirectPage("error", "Unable to save settings.");
				else  BB_RedirectPage("success", BB_Translate("Successfully disabled the %s provider.", $this->DisplayName()));
			}
			else if ($sso_site_admin && !$sso_settings["sso_ldap"]["enabled"] && $_REQUEST["action2"] == "enable")
			{
				if (!function_exists("ldap_connect"))  BB_RedirectPage("error", "The ldap_connect() function does not exist.  LDAP won't work until the LDAP PHP extension is enabled.");

				$sso_settings["sso_ldap"]["enabled"] = true;

				if (!SSO_SaveSettings())  BB_RedirectPage("error", "Unable to save settings.");
				else  BB_RedirectPage("success", BB_Translate("Successfully enabled the %s provider.", $this->DisplayName()));
			}
		}

		public function IsEnabled()
		{
			global $sso_settings;

			if (!function_exists("ldap_connect") || !$sso_settings["sso_ldap"]["enabled"])  return false;

			if ($sso_settings["sso_ldap"]["server"] == "" || $sso_settings["sso_ldap"]["dn"] == "" || $sso_settings["sso_ldap"]["map_username"] == "")  return false;

			if (!SSO_IsIPAllowed($sso_settings["sso_ldap"]["iprestrict"]) || SSO_IsSpammer($sso_settings["sso_ldap"]["iprestrict"]))  return false;

			return true;
		}

		public function GetProtectedFields()
		{
			global $sso_settings;

			$result = array();

			$lines = explode("\n", str_replace("\r", "\n", $sso_settings["sso_ldap"]["map_custom"]));
			foreach ($lines as $line)
			{
				$line = trim($line);
				$pos = strpos($line, "=");
				if ($pos !== false)
				{
					$field = substr($line, $pos + 1);

					if (SSO_IsField($field))  $result[$field] = true;
				}
			}

			$result[$sso_settings["sso_ldap"]["map_username"]] = true;

			return $result;
		}

		public function GenerateSelector()
		{
			global $sso_target_url;
?>
<div class="sso_selector">
	<a class="sso_ldap" href="<?php echo htmlspecialchars($sso_target_url); ?>"><?php echo htmlspecialchars($this->DisplayName()); ?></a>
</div>
<?php
		}

		public function ProcessFrontend()
		{
			global $sso_provider, $sso_settings, $sso_target_url, $sso_header, $sso_footer, $sso_providers;

			$message = "";

			if (SSO_FrontendFieldValue("submit") !== false)
			{
				$username = SSO_FrontendFieldValue("username");
				$password = SSO_FrontendFieldValue("password");
				if ($username === false || $username == "" || $password === false || ($sso_settings["sso_ldap"]["password"] && $password == ""))  $message = BB_Translate("Please fill in the fields.");
				else
				{
					$ldap = @ldap_connect($sso_settings["sso_ldap"]["server"]);
					if ($ldap === false)  $message = BB_Translate("Unable to connect to the LDAP server.  Error:  %s", ldap_error($ldap));
					else
					{
						$replacemap = array(
							"," => "\\,",
							"\\" => "\\\\",
							"/" => "\\/",
							"#" => "\\#",
							"+" => "\\+",
							"<" => "\\<",
							">" => "\\>",
							";" => "\\;",
							"\"" => "\\\"",
							"=" => "\\=",
						);

						$dnusername = str_replace(array_keys($replacemap), array_values($replacemap), $username);
						if (substr($dnusername, 0, 1) === " ")  $dnusername = "\\" . $dnusername;
						if (strlen($dnusername) > 2 && substr($dnusername, -1) === " ")  $dnusername = substr($dnusername, 0, -1) . "\\ ";
						$dn = str_replace("@USERNAME@", $dnusername, $sso_settings["sso_ldap"]["dn"]);

						$userinfo = array();
						@ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
						$result = @ldap_bind($ldap, $dn, $password);
						if ($result === false && ldap_errno($ldap))  $extra = ldap_error($ldap);
						else
						{
							$extra = "";

							$result = @ldap_read($ldap, $dn, "objectClass=*");
							if (!is_resource($result))
							{
								$extra = ldap_error($ldap);
								$result = false;
							}
							else
							{
								$items = @ldap_get_entries($ldap, $result);
								@ldap_free_result($result);
								$result = ($items["count"] > 0);

								// Boil down the results to just key-value pairs.
								if ($result === false)  $extra = "Unable to retrieve entries";
								else
								{
									foreach ($items[0] as $key => $val)
									{
										if (is_string($key) && $key != "count")
										{
											if (is_string($val))  $userinfo[$key] = $val;
											else if (is_array($val) && $val["count"] > 0)  $userinfo[$key] = $val[0];
										}
									}

									if ($sso_settings["sso_ldap"]["debug"])
									{
										echo "LDAP fields:<br />";
										echo "<table>";
										foreach ($userinfo as $key => $val)  echo "<tr><td style=\"padding-right: 15px;\"><b>" . htmlspecialchars($key) . "</b></td><td>" . htmlspecialchars($val) . "</td></tr>";
										echo "</table>";
									}
								}
							}
						}
						@ldap_close($ldap);

						if ($result === false)  $message = BB_Translate("Invalid username or password.  %s.", $extra);
						else
						{
							$origusername = $username;
							if ($sso_settings["sso_ldap"]["remove_domain"])
							{
								$username = str_replace("\\", "/", $username);
								$pos = strrpos("/", $username);
								if ($pos !== false)  $username = substr($username, $pos + 1);
							}

							$mapinfo = array();
							$lines = explode("\n", str_replace("\r", "\n", $sso_settings["sso_ldap"]["map_custom"]));
							foreach ($lines as $line)
							{
								$line = trim($line);
								$pos = strpos($line, "=");
								if ($pos !== false)
								{
									$srcfield = substr($line, 0, $pos);
									$destfield = substr($line, $pos + 1);

									if (isset($userinfo[$srcfield]) && SSO_IsField($destfield))
									{
										$mapinfo[$destfield] = $userinfo[$srcfield];
									}
								}
							}

							$mapinfo[$sso_settings["sso_ldap"]["map_username"]] = $username;

							if ($sso_settings["sso_ldap"]["debug"])
							{
								echo "Mapped fields:<br />";
								echo "<table>";
								foreach ($mapinfo as $key => $val)  echo "<tr><td style=\"padding-right: 15px;\"><b>" . htmlspecialchars($key) . "</b></td><td>" . htmlspecialchars($val) . "</td></tr>";
								echo "</table>";
								exit();
							}

							SSO_ActivateUser($dn, serialize($sso_settings["sso_ldap"]), $mapinfo);

							// Only falls through on account lockout or a fatal error.
							$message = BB_Translate("User activation failed.");
						}
					}
				}
			}

			echo $sso_header;

			SSO_OutputHeartbeat();
?>
<script type="text/javascript">
SSO_Vars = {
	'showpassword' : '<?php echo htmlspecialchars(BB_JSSafe(BB_Translate("Show password"))); ?>'
};
</script>
<script type="text/javascript" src="<?php echo htmlspecialchars(SSO_ROOT_URL . "/" . SSO_PROVIDER_PATH . "/sso_ldap/sso_ldap.js"); ?>"></script>
<div class="sso_main_wrap sso_ldap">
<div class="sso_main_wrap_inner">
<?php
			if ($message != "")
			{
?>
	<div class="sso_main_messages_wrap">
		<div class="sso_main_messages">
			<div class="sso_main_messageerror"><?php echo htmlspecialchars($message); ?></div>
		</div>
	</div>
<?php
			}
?>
	<div class="sso_main_form_wrap sso_ldap_signin_form">
		<div class="sso_main_form_header"><?php echo htmlspecialchars(BB_Translate("Sign in")); ?></div>
		<form class="sso_main_form" name="sso_ldap_form" method="post" accept-charset="UTF-8" enctype="multipart/form-data" action="<?php echo htmlspecialchars($sso_target_url); ?>">
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Username")); ?></div>
				<div class="sso_main_formdata"><input class="sso_main_text" type="text" name="<?php echo SSO_FrontendField("username"); ?>" /></div>
			</div>
			<script type="text/javascript">
			jQuery('input.sso_main_text:first').focus();
			</script>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Password")); ?></div>
				<div class="sso_main_formdata"><input class="sso_main_text" type="password" name="<?php echo SSO_FrontendField("password"); ?>" /></div>
			</div>
			<div class="sso_main_formsubmit">
				<input type="submit" name="<?php echo SSO_FrontendField("submit"); ?>" value="<?php echo htmlspecialchars(BB_Translate("Sign in")); ?>" />
			</div>
		</form>
	</div>
<?php
?>
</div>
</div>
<?php
			echo $sso_footer;
		}
	}
?>