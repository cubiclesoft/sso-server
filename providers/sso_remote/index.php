<?php
	// SSO Remote Sign In Provider
	// (C) 2015 CubicleSoft.  All Rights Reserved.

	if (!defined("SSO_FILE"))  exit();

	class sso_remote extends SSO_ProviderBase
	{
		private $info;

		public function Init()
		{
			global $sso_settings;

			if (!isset($sso_settings["sso_remote"]["installed"]))  $sso_settings["sso_remote"]["installed"] = false;
			if (!isset($sso_settings["sso_remote"]["enabled"]))  $sso_settings["sso_remote"]["enabled"] = false;
			if (!isset($sso_settings["sso_remote"]["map_remote_id"]) || !SSO_IsField($sso_settings["sso_remote"]["map_remote_id"]))  $sso_settings["sso_remote"]["map_remote_id"] = "";
			if (!isset($sso_settings["sso_remote"]["iprestrict"]))  $sso_settings["sso_remote"]["iprestrict"] = SSO_InitIPFields();

			$this->info = array();
			$this->info["display_name"] = "Remote Login";
		}

		public function DisplayName()
		{
			return BB_Translate($this->info["display_name"]);
		}

		public function DefaultOrder()
		{
			return 100;
		}

		public function MenuOpts()
		{
			global $sso_site_admin, $sso_settings;

			$result = array(
				"name" => "Remote Login"
			);

			if ($sso_site_admin)
			{
				if ($sso_settings["sso_remote"]["enabled"])
				{
					$result["items"] = array(
						"Manage Remotes" => SSO_CreateConfigURL("manageremotes"),
						"Configure" => SSO_CreateConfigURL("config"),
						"Disable" => SSO_CreateConfigURL("disable")
					);
				}
				else if ($sso_settings["sso_remote"]["installed"])
				{
					$result["items"] = array(
						"Enable" => SSO_CreateConfigURL("enable")
					);
				}
				else
				{
					$result["items"] = array(
						"Install" => SSO_CreateConfigURL("install")
					);
				}
			}

			return $result;
		}

		public function Config()
		{
			global $sso_rng, $sso_db, $sso_db_apikeys, $sso_site_admin, $sso_settings, $sso_menuopts, $sso_select_fields;

			$sso_db_sso_remote = SSO_DB_PREFIX . "p_sso_remote";
			$sso_db_sso_remote_users = SSO_DB_PREFIX . "p_sso_remote_users";

			if ($sso_site_admin && $sso_settings["sso_remote"]["enabled"] && $_REQUEST["action2"] == "editremote")
			{
				$row = $sso_db->GetRow("SELECT", array(
					"*",
					"FROM" => "?",
					"WHERE" => "id = ?",
				), $sso_db_sso_remote, $_REQUEST["id"]);

				if ($row)
				{
					$info = unserialize($row->info);

					if (isset($_REQUEST["name"]))
					{
						if (strlen($_REQUEST["name"]) > 75)  BB_SetPageMessage("error", "'Name' can only be 75 characters long.");

						if ($_REQUEST["name"] != $row->name && $sso_db->GetOne("SELECT", array("COUNT(*)", "FROM" => "?", "WHERE" => "name = ?"), $sso_db_sso_remote, $_REQUEST["name"]))  BB_SetPageMessage("error", "The specified remote 'Name' already exists.");

						$apirow = $sso_db->GetRow("SELECT", array(
							"*",
							"FROM" => "?",
							"WHERE" => "id = ?",
						), $sso_db_apikeys, $_REQUEST["apikey_id"]);

						if ($apirow === false)  BB_SetPageMessage("error", "The specified 'API Key ID' is not valid.");
						else
						{
							$apiinfo = unserialize($apirow->info);
							if (!isset($apiinfo["type"]))  $apiinfo["type"] = "normal";
							if ($apiinfo["type"] != "remote")  BB_SetPageMessage("error", "The specified 'API Key ID' is not a remote API key.");
						}

						$info["iprestrict"] = SSO_ProcessIPFields();

						if (BB_GetPageMessageType() != "error")
						{
							try
							{
								$info["icon"] = $_REQUEST["icon"];
								$info["notes"] = $_REQUEST["notes"];
								$info["automate"] = ($_REQUEST["automate"] > 0);

								$sso_db->Query("UPDATE", array($sso_db_sso_remote, array(
									"name" => $_REQUEST["name"],
									"apikey_id" => $_REQUEST["apikey_id"],
									"info" => serialize($info),
								), "WHERE" => "id = ?"), $row->id);

								SSO_ConfigRedirect("editremote", array("id" => $row->id), "success", BB_Translate("Successfully updated the remote."));
							}
							catch (Exception $e)
							{
								BB_SetPageMessage("error", "Unable to update the remote.  " . $e->getMessage());
							}
						}
					}

					$contentopts = array(
						"desc" => BB_Translate("Edit the remote."),
						"nonce" => "action",
						"hidden" => array(
							"action" => "config",
							"provider" => "sso_remote",
							"action2" => "editremote",
							"id" => $row->id
						),
						"fields" => array(
							array(
								"title" => "Remote Key",
								"type" => "static",
								"value" => $row->remotekey . "-" . $row->id
							),
							array(
								"title" => "Name",
								"type" => "text",
								"name" => "name",
								"value" => BB_GetValue("name", $row->name),
								"desc" => "The name of this remote.  Usually the name of the business or a business unit that will use this remote to sign in (e.g. Intel).  Must be unique."
							),
							array(
								"title" => "API Key ID",
								"type" => "text",
								"name" => "apikey_id",
								"value" => BB_GetValue("apikey_id", $row->apikey_id),
								"desc" => "A valid remote API key ID."
							),
							array(
								"title" => "Icon URL",
								"type" => "text",
								"name" => "icon",
								"value" => BB_GetValue("icon", $info["icon"]),
								"desc" => "An optional URL to a 48x48 pixel icon.  The URL should start with 'https://'."
							),
							array(
								"title" => "Notes",
								"type" => "textarea",
								"name" => "notes",
								"value" => BB_GetValue("notes", $info["notes"]),
								"desc" => "Optional extra information about this remote such as contract details."
							),
							array(
								"title" => "Automate Validation Phase?",
								"type" => "select",
								"name" => "automate",
								"options" => array("No", "Yes"),
								"select" => BB_GetValue("automate", (string)(int)$info["automate"]),
								"desc" => "Whether or not to attempt to automate the validation phase after authenticating the user."
							),
						),
						"submit" => "Save",
						"focus" => true
					);

					SSO_AppendIPFields($contentopts, $info["iprestrict"]);

					BB_GeneratePage("Edit Remote", $sso_menuopts, $contentopts);
				}
			}
			else if ($sso_site_admin && $sso_settings["sso_remote"]["enabled"] && $_REQUEST["action2"] == "addremote")
			{
				if (isset($_REQUEST["name"]))
				{
					if ($_REQUEST["name"] == "")  BB_SetPageMessage("error", "Please fill in 'Name'.");
					if (strlen($_REQUEST["name"]) > 75)  BB_SetPageMessage("error", "'Name' can only be 75 characters long.");
					if ($sso_db->GetOne("SELECT", array("COUNT(*)", "FROM" => "?", "WHERE" => "name = ?"), $sso_db_sso_remote, $_REQUEST["name"]))  BB_SetPageMessage("error", "The specified remote 'Name' already exists.");

					$apirow = $sso_db->GetRow("SELECT", array(
						"*",
						"FROM" => "?",
						"WHERE" => "id = ?",
					), $sso_db_apikeys, $_REQUEST["apikey_id"]);

					if ($apirow === false)  BB_SetPageMessage("error", "The specified 'API Key ID' is not valid.");
					else
					{
						$apiinfo = unserialize($apirow->info);
						if (!isset($apiinfo["type"]))  $apiinfo["type"] = "normal";
						if ($apiinfo["type"] != "remote")  BB_SetPageMessage("error", "The specified 'API Key ID' is not a remote API key.");
					}

					if (BB_GetPageMessageType() != "error")
					{
						try
						{
							$remotekey = $sso_rng->GenerateString();

							$info = array(
								"icon" => "",
								"notes" => "",
								"iprestrict" => SSO_InitIPFields(),
								"automate" => false
							);

							$sso_db->Query("INSERT", array($sso_db_sso_remote, array(
								"name" => $_REQUEST["name"],
								"remotekey" => $remotekey,
								"apikey_id" => $_REQUEST["apikey_id"],
								"created" => CSDB::ConvertToDBTime(time()),
								"info" => serialize($info),
							), "AUTO INCREMENT" => "id"));

							$id = $sso_db->GetInsertID();

							SSO_ConfigRedirect("editremote", array("id" => $id), "success", BB_Translate("Successfully created the remote."));
						}
						catch (Exception $e)
						{
							BB_SetPageMessage("error", "Unable to create the remote.  " . $e->getMessage());
						}
					}
				}

				$contentopts = array(
					"desc" => BB_Translate("Add a remote."),
					"nonce" => "action",
					"hidden" => array(
						"action" => "config",
						"provider" => "sso_remote",
						"action2" => "addremote"
					),
					"fields" => array(
						array(
							"title" => "Name",
							"type" => "text",
							"name" => "name",
							"value" => BB_GetValue("name", ""),
							"desc" => "The name of this remote.  Usually the name of the business or a business unit that will use this remote to sign in (e.g. Intel).  Must be unique."
						),
						array(
							"title" => "API Key ID",
							"type" => "text",
							"name" => "apikey_id",
							"value" => BB_GetValue("apikey_id", ""),
							"desc" => "A valid remote API key ID."
						)
					),
					"submit" => "Create",
					"focus" => true
				);

				BB_GeneratePage("Add Remote", $sso_menuopts, $contentopts);
			}
			else if ($sso_site_admin && $sso_settings["sso_remote"]["enabled"] && $_REQUEST["action2"] == "manageremotes")
			{
				$desc = "<br />";
				$desc .= SSO_CreateConfigLink("Add Remote", "addremote");

				$rows = array();
				$result = $sso_db->Query("SELECT", array(
					"r.id, r.name, r.apikey_id, a.id AS a_id",
					"FROM" => "? AS r LEFT OUTER JOIN ? AS a ON (r.apikey_id = a.id)",
				), $sso_db_sso_remote, $sso_db_apikeys);
				while ($row = $result->NextRow())
				{
					$rows[] = array($row->id, htmlspecialchars($row->name), ($row->a_id > 0 ? "<a href=\"" . BB_GetRequestURLBase() . "?action=editapikey&id=" . $row->apikey_id . "&sec_t=" . BB_CreateSecurityToken("editapikey") . "\">" . $row->apikey_id . "</a>" : BB_Translate("<i>Invalid</i>")), SSO_CreateConfigLink("Edit", "editremote", array("id" => $row->id)) . " | " . SSO_CreateConfigLink("Delete", "deleteremote", array("id" => $row->id), "Are you sure you want to delete this remote?"));
				}

				$contentopts = array(
					"desc" => BB_Translate("Manage the remotes."),
					"htmldesc" => $desc,
					"fields" => array(
						array(
							"type" => "table",
							"cols" => array("ID", "Name", "API Key", "Options"),
							"rows" => $rows
						)
					)
				);

				BB_GeneratePage("Manage Remotes", $sso_menuopts, $contentopts);
			}
			else if ($sso_site_admin && $sso_settings["sso_remote"]["enabled"] && $_REQUEST["action2"] == "config")
			{
				if (isset($_REQUEST["configsave"]))
				{
					$sso_settings["sso_remote"]["iprestrict"] = SSO_ProcessIPFields();

					if (BB_GetPageMessageType() != "error")
					{
						$sso_settings["sso_remote"]["map_remote_id"] = (SSO_IsField($_REQUEST["map_remote_id"]) ? $_REQUEST["map_remote_id"] : "");

						if (!SSO_SaveSettings())  BB_SetPageMessage("error", "Unable to save settings.");
						else if (BB_GetPageMessageType() == "info")  SSO_ConfigRedirect("config", array(), "info", $_REQUEST["bb_msg"] . "  " . BB_Translate("Successfully updated the %s provider configuration.", $this->DisplayName()));
						else  SSO_ConfigRedirect("config", array(), "success", BB_Translate("Successfully updated the %s provider configuration.", $this->DisplayName()));
					}
				}

				$contentopts = array(
					"desc" => BB_Translate("Configure the %s provider.", $this->DisplayName()),
					"nonce" => "action",
					"hidden" => array(
						"action" => "config",
						"provider" => "sso_remote",
						"action2" => "config",
						"configsave" => "1"
					),
					"fields" => array(
						array(
							"title" => "Map Remote ID",
							"type" => "select",
							"name" => "map_remote_id",
							"options" => $sso_select_fields,
							"select" => BB_GetValue("map_remote_id", (string)$sso_settings["sso_remote"]["map_remote_id"]),
							"desc" => "The field in the SSO system to map the remote ID to.  This allows applications to identify an organization and sign all users at that organization into a single instance."
						),
					),
					"submit" => "Save",
					"focus" => true
				);

				SSO_AppendIPFields($contentopts, $sso_settings["sso_remote"]["iprestrict"]);

				BB_GeneratePage(BB_Translate("Configure %s", $this->DisplayName()), $sso_menuopts, $contentopts);
			}
			else if ($sso_site_admin && $sso_settings["sso_remote"]["enabled"] && $_REQUEST["action2"] == "disable")
			{
				$sso_settings["sso_remote"]["enabled"] = false;

				if (!SSO_SaveSettings())  BB_RedirectPage("error", "Unable to save settings.");
				else  BB_RedirectPage("success", BB_Translate("Successfully disabled the %s provider.", $this->DisplayName()));
			}
			else if ($sso_site_admin && !$sso_settings["sso_remote"]["enabled"] && $_REQUEST["action2"] == "enable")
			{
				$sso_settings["sso_remote"]["enabled"] = true;

				if (!SSO_SaveSettings())  BB_RedirectPage("error", "Unable to save settings.");
				else  BB_RedirectPage("success", BB_Translate("Successfully enabled the %s provider.", $this->DisplayName()));
			}
			else if ($sso_site_admin && !$sso_settings["sso_remote"]["installed"] && $_REQUEST["action2"] == "install")
			{
				if (isset($_REQUEST["install"]))
				{
					if ($sso_db->TableExists($sso_db_sso_remote))  BB_SetPageMessage("error", "The database table '" . $sso_db_sso_remote . "' already exists.");
					if ($sso_db->TableExists($sso_db_sso_remote_users))  BB_SetPageMessage("error", "The database table '" . $sso_db_sso_remote_users . "' already exists.");

					if (BB_GetPageMessageType() != "error")
					{
						try
						{
							$sso_db->Query("CREATE TABLE", array($sso_db_sso_remote, array(
								"id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true, "PRIMARY KEY" => true, "AUTO INCREMENT" => true),
								"name" => array("STRING", 1, 75, "NOT NULL" => true),
								"remotekey" => array("STRING", 1, 64, "NOT NULL" => true),
								"apikey_id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true),
								"created" => array("DATETIME", "NOT NULL" => true),
								"info" => array("STRING", 3, "NOT NULL" => true),
							),
							array(
								array("UNIQUE", array("name"), "NAME" => $sso_db_sso_remote . "_name"),
								array("KEY", array("apikey_id"), "NAME" => $sso_db_sso_remote . "_apikey_id"),
							)));
						}
						catch (Exception $e)
						{
							BB_SetPageMessage("error", "Unable to create the database table '" . htmlspecialchars($sso_db_sso_remote) . "'.  " . $e->getMessage());
						}

						if (BB_GetPageMessageType() != "error")
						{
							try
							{
								$sso_db->Query("CREATE TABLE", array($sso_db_sso_remote_users, array(
									"id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true, "PRIMARY KEY" => true, "AUTO INCREMENT" => true),
									"remote_id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true),
									"user_id" => array("STRING", 1, 255, "NOT NULL" => true),
									"created" => array("DATETIME", "NOT NULL" => true),
								),
								array(
									array("UNIQUE", array("remote_id", "user_id"), "NAME" => $sso_db_sso_remote_users . "_remote_user_id"),
								)));

								$sso_settings["sso_remote"]["installed"] = true;
								$sso_settings["sso_remote"]["enabled"] = true;

								if (!SSO_SaveSettings())  BB_SetPageMessage("error", "Unable to save settings.");
								else  SSO_ConfigRedirect("manageremotes", array(), "success", BB_Translate("Successfully installed the %s provider.", $this->DisplayName()));
							}
							catch (Exception $e)
							{
								BB_SetPageMessage("error", "Unable to create the database table '" . htmlspecialchars($sso_db_sso_remote_users) . "'.  " . $e->getMessage());
							}
						}
					}
				}

				$contentopts = array(
					"desc" => BB_Translate("Install the %s provider.", $this->DisplayName()),
					"nonce" => "action",
					"hidden" => array(
						"action" => "config",
						"provider" => "sso_remote",
						"action2" => "install",
						"install" => "1"
					),
					"fields" => array(
					),
					"submit" => "Install",
					"focus" => true
				);

				BB_GeneratePage(BB_Translate("Install %s", $this->DisplayName()), $sso_menuopts, $contentopts);
			}
		}

		public function IsEnabled()
		{
			global $sso_settings, $sso_db, $sso_db_apikeys;

			if (!$sso_settings["sso_remote"]["enabled"])  return false;

			if (!SSO_IsIPAllowed($sso_settings["sso_remote"]["iprestrict"]))  return false;

			if (!isset($_REQUEST["sso_remote_id"]) || !is_string($_REQUEST["sso_remote_id"]))  return false;

			$remoteid = explode("-", $_REQUEST["sso_remote_id"]);
			if (count($remoteid) != 2)  return false;

			$sso_db_sso_remote = SSO_DB_PREFIX . "p_sso_remote";

			try
			{
				$row = $sso_db->GetRow("SELECT", array(
					"*",
					"FROM" => "?",
					"WHERE" => "id = ? AND remotekey = ?",
				), $sso_db_sso_remote, $remoteid[1], $remoteid[0]);

				if ($row === false)  return false;

				$this->info["row"] = $row;
				$this->info["display_name"] = BB_Translate("%s Login", $row->name);

				$info = unserialize($row->info);
				if (!isset($info["iprestrict"]) || !SSO_IsIPAllowed($info["iprestrict"]) || SSO_IsSpammer($info["iprestrict"]))  return false;
				$this->info["row_info"] = $info;

				$apirow = $sso_db->GetRow("SELECT", array(
					"*",
					"FROM" => "?",
					"WHERE" => "id = ?",
				), $sso_db_apikeys, $row->apikey_id);

				if ($apirow === false)  return false;
				$this->info["apirow"] = $apirow;
			}
			catch (Exception $e)
			{
				SSO_DisplayError("A database error has occurred.  Most likely cause:  Bad SQL query.");
			}

			// Set a cookie so future requests succeed.
			if (!isset($_COOKIE["sso_remote_id"]))
			{
				SetCookieFixDomain("sso_remote_id", $_REQUEST["sso_remote_id"], 0, "", "", SSO_IsSSLRequest(), true);
			}

			return true;
		}

		public function GetProtectedFields()
		{
			global $sso_settings, $sso_session_info;

			$result = array();
			if (isset($sso_session_info["setlogin_info"]) && isset($sso_session_info["setlogin_result"]))
			{
				foreach ($sso_session_info["setlogin_result"]["protected_fields"] as $key => $val)  $result[$key] = true;
			}

			if ($sso_settings["sso_remote"]["map_remote_id"] != "")  $result[$sso_settings["sso_remote"]["map_remote_id"]] = true;

			return $result;
		}

		public function GenerateSelector()
		{
			global $sso_target_url;
?>
<div class="sso_selector">
	<a class="sso_remote"<?php if (isset($this->info["row_info"]) && $this->info["row_info"]["icon"] != "")  echo " style=\"background-image: url('" . htmlspecialchars($this->info["row_info"]["icon"]) . "');\""; ?> href="<?php echo htmlspecialchars($sso_target_url); ?>"><?php echo htmlspecialchars($this->DisplayName()); ?></a>
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

	<div class="sso_main_info"><a href="<?php echo htmlspecialchars($sso_target_url . "&tryagain=1"); ?>"><?php echo htmlspecialchars(BB_Translate("Try again")); ?></a><?php if (count($sso_providers) > 1)  { ?> | <a href="<?php echo htmlspecialchars($sso_selectors_url); ?>"><?php echo htmlspecialchars(BB_Translate("Select another sign in method")); ?></a><?php } ?></div>
</div>
</div>
<?php
			echo $sso_footer;
		}

		public function ProcessFrontend()
		{
			global $sso_settings, $sso_rng, $sso_provider, $sso_target_url, $sso_session_info, $sso_session_id, $sso_db;

			if (isset($sso_session_info["setlogin_result"]) && !isset($_REQUEST["tryagain"]))
			{
				// Check the secret.
				if (!isset($_REQUEST["sso_setlogin_secret"]) || !isset($sso_session_info["setlogin_info"]) || $_REQUEST["sso_setlogin_secret"] !== $sso_session_info["setlogin_info"]["secret"])
				{
					$this->DisplayError(BB_Translate("Unable to authenticate the request."));

					return;
				}

				// Should be nearly impossible to get here since browser redirects are executed almost immediately.
				if (CSDB::ConvertFromDBTime($sso_session_info["setlogin_info"]["expires"]) < time())
				{
					$this->DisplayError(BB_Translate("Verification token has expired."));

					return;
				}

				// The user is signed in.  Activate the account.
				$sso_db_sso_remote_users = SSO_DB_PREFIX . "p_sso_remote_users";

				try
				{
					$id = $sso_db->GetOne("SELECT", array(
						"id",
						"FROM" => "?",
						"WHERE" => "remote_id = ? AND user_id = ?",
					), $sso_db_sso_remote_users, $this->info["row"]->id, $sso_session_info["setlogin_result"]["user_id"]);

					if ($id === false)
					{
						$sso_db->Query("INSERT", array($sso_db_sso_remote_users, array(
							"remote_id" => $this->info["row"]->id,
							"user_id" => $sso_session_info["setlogin_result"]["user_id"],
							"created" => CSDB::ConvertToDBTime(time()),
						), "AUTO INCREMENT" => "id"));

						$id = $sso_db->GetInsertID();
					}

					$mapinfo = $sso_session_info["setlogin_result"]["protected_fields"];
					$mapinfo[$sso_settings["sso_remote"]["map_remote_id"]] = $this->info["row"]->id;

					SSO_ActivateUser($id, serialize($sso_session_info["setlogin_info"]), $mapinfo, false, $this->info["row_info"]["automate"]);

					// Only falls through on account lockout or a fatal error.
					$this->DisplayError(BB_Translate("User activation failed."));
				}
				catch (Exception $e)
				{
					$this->DisplayError("A database error has occurred.  Most likely cause:  Bad SQL query.");
				}
			}
			else
			{
				// Check the API key information.
				$info = unserialize($this->info["apirow"]->info);
				if ($info["type"] != "remote")
				{
					$this->DisplayError(BB_Translate("The target client API key is not a remote API key."));

					return;
				}
				if ($info["url"] == "")
				{
					$this->DisplayError(BB_Translate("The target client API key URL is missing."));

					return;
				}

				// Set up the session so that the endpoint works.
				unset($sso_session_info["setlogin_result"]);
				$token = $sso_rng->GenerateString();
				$sso_session_info["setlogin_info"] = array(
					"provider" => $sso_provider,
					"apikey_id" => $this->info["apirow"]->id,
					"redirect_url" => BB_GetRequestHost() . $sso_target_url,
					"token" => $token,
					"secret" => $sso_rng->GenerateString(),
					"expires" => CSDB::ConvertToDBTime(time() + 30 * 60)
				);
				if (!SSO_SaveSessionInfo())
				{
					$this->DisplayError(BB_Translate("Unable to save session information."));

					return;
				}

				// Redirect to the remote host.
				$url = $info["url"] . (strpos($info["url"], "?") === false ? "?" : "&") . "from_sso_server=1&sso_setlogin_id=" . urlencode($sso_session_id[1]) . "&sso_setlogin_token=" . urlencode($token) . (isset($_REQUEST["lang"]) ? "&sso_lang=" . urlencode($_REQUEST["lang"]) : "");

				SSO_ExternalRedirect($url);
			}
		}
	}
?>