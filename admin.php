<?php
	// SSO Server admin.  Based on Admin Pack.
	// (C) 2015 CubicleSoft.  All Rights Reserved.

	define("SSO_FILE", 1);
	define("SSO_MODE", "admin");

	require_once "config.php";
	define("BB_ROOT_URL", SSO_ROOT_URL);
	define("BB_SUPPORT_PATH", SSO_SUPPORT_PATH);

	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/debug.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/str_basics.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/page_basics.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/sso_functions.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/blowfish.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/aes.php";
	if (!ExtendedAES::IsMcryptAvailable())  require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/phpseclib/AES.php";
	require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/random.php";

	SetDebugLevel();

	Str::ProcessAllInput();

	// Don't proceed any further if this is an acciental re-upload of this file to the root path.
	if (SSO_STO_ADMIN && SSO_ROOT_PATH == str_replace("\\", "/", dirname(__FILE__)))  exit();

	if (SSO_USE_HTTPS && !BB_IsSSLRequest())
	{
		header("Location: " . BB_GetFullRequestURLBase("https"));
		exit();
	}

	// Initialize language settings.
	BB_InitLangmap(SSO_ROOT_PATH . "/" . SSO_LANG_PATH . "/", SSO_DEFAULT_LANG);
	BB_SetLanguage(SSO_ROOT_PATH . "/" . SSO_LANG_PATH . "/", SSO_ADMIN_LANG);

	// Initialize the global CSPRNG instance.
	$sso_rng = new CSPRNG();

	// Calculate the remote IP address.
	$sso_ipaddr = SSO_GetRemoteIP();

	$bb_randpage = SSO_BASE_RAND_SEED;
	$bb_rootname = "SSO Server Admin";
	$bb_usertoken = "";
	$sso_site_admin = false;
	$sso_user_id = "0";

	// Require developers to inject code here.  For example, integration with a specific login system or IP address restrictions.
	if (file_exists("admin_hook.php"))  require_once "admin_hook.php";

	if (!is_string($bb_usertoken) || $bb_usertoken === "")
	{
		echo "Invalid user token.\n";
		exit();
	}

	BB_ProcessPageToken("action");

	// Connect to the database and generate database globals.
	SSO_DBConnect(true);

	// Load in fields with admin select.
	SSO_LoadFields(true);

	// Load in $sso_settings and initialize it.
	SSO_LoadSettings();

	// Menu/Navigation options.
	if ($sso_site_admin)
	{
		$sso_menuopts = array(
			"SSO Server Options" => array(
				"Find User" => BB_GetRequestURLBase() . "?action=finduser&sec_t=" . BB_CreateSecurityToken("finduser"),
				"Manage Fields" => BB_GetRequestURLBase() . "?action=managefields&sec_t=" . BB_CreateSecurityToken("managefields"),
				"Manage Tags" => BB_GetRequestURLBase() . "?action=managetags&sec_t=" . BB_CreateSecurityToken("managetags"),
				"Manage API Keys" => BB_GetRequestURLBase() . "?action=manageapikeys&sec_t=" . BB_CreateSecurityToken("manageapikeys"),
				"Manage IP Cache" => BB_GetRequestURLBase() . "?action=manageipcache&sec_t=" . BB_CreateSecurityToken("manageipcache"),
				"Configure" => BB_GetRequestURLBase() . "?action=configure&sec_t=" . BB_CreateSecurityToken("configure"),
				"Reset All Sessions" => array("href" => BB_GetRequestURLBase() . "?action=resetsessions&sec_t=" . BB_CreateSecurityToken("resetsessions"), "onclick" => "return confirm('" . htmlspecialchars(BB_JSSafe(BB_Translate("Are you sure you want to reset all sessions?"))) . "');"),
			)
		);
	}
	else
	{
		$sso_menuopts = array(
			"SSO Server Options" => array(
				"Find User" => BB_GetRequestURLBase() . "?action=finduser&sec_t=" . BB_CreateSecurityToken("finduser"),
			)
		);
	}

	// Load providers.
	$providers = SSO_GetProviderList();
	$sso_providers = array();
	$menuopts = array();
	$newprovider = false;
	foreach ($providers as $sso_provider)
	{
		if (!isset($sso_settings[$sso_provider]))
		{
			$sso_settings[$sso_provider] = array();

			$newprovider = true;
		}

		require_once SSO_ROOT_PATH . "/" . SSO_PROVIDER_PATH . "/" . $sso_provider . "/index.php";
		if (class_exists($sso_provider))
		{
			$sso_providers[$sso_provider] = new $sso_provider;
			$sso_providers[$sso_provider]->Init();
			$result = $sso_providers[$sso_provider]->MenuOpts();
			if (is_array($result) && isset($result["name"]) && isset($result["items"]))
			{
				$order = (isset($sso_settings[""]["order"][$sso_provider]) ? $sso_settings[""]["order"][$sso_provider] : $sso_providers[$sso_provider]->DefaultOrder());
				SSO_AddSortedOutput($menuopts, $order, $result["name"], $result["items"]);
			}
		}
	}

	// Some providers take a while to initialize the first time (e.g. Generic Login).
	if ($newprovider)  SSO_SaveSettings();

	// Merge provider menus into the main menu.
	ksort($menuopts);
	foreach ($menuopts as $menus)
	{
		ksort($menus);
		$sso_menuopts = array_merge($sso_menuopts, $menus);
	}

	// Append product information.
	$sso_menuopts["About SSO Server"] = array(
		"Homepage" => array("href" => "http://barebonescms.com/documentation/sso/", "target" => "_blank"),
		"Donate" => array("style" => "color: #008800;", "href" => "http://barebonescms.com/donate/", "target" => "_blank", "title" => BB_Translate("Don't be a cheapskate.  Support the author to keep SSO Server development going.")),
		"Forums" => array("href" => "http://barebonescms.com/forums/", "target" => "_blank"),
	);

	if (function_exists("AdminHook_MenuOpts"))  AdminHook_MenuOpts();

	function SSO_CreateConfigURL($action2, $extra = array())
	{
		global $sso_provider;

		$extra["provider"] = $sso_provider;
		if ($action2 != "")  $extra["action2"] = $action2;

		$extra2 = "";
		foreach ($extra as $key => $val)  $extra2 .= "&" . urlencode($key) . "=" . urlencode($val);

		return BB_GetRequestURLBase() . "?action=config" . $extra2 . "&sec_t=" . BB_CreateSecurityToken("config", array_values($extra)) . "&sec_extra=" . implode(",", array_keys($extra));
	}

	function SSO_CreateConfigLink($title, $action2, $extra = array(), $confirm = "")
	{
		return "<a href=\"" . htmlspecialchars(SSO_CreateConfigURL($action2, $extra)) . "\"" . ($confirm != "" ? " onclick=\"return confirm('" . htmlspecialchars(BB_JSSafe(BB_Translate($confirm))) . "');\"" : "") . ">" . htmlspecialchars(BB_Translate($title)) . "</a>";
	}

	function SSO_ConfigRedirect($action2, $extra = array(), $msgtype = "", $msg = "")
	{
		header("Location: " . SSO_CreateConfigURL($action2, $extra) . ($msg != "" ? "&bb_msgtype=" . urlencode($msgtype) . "&bb_msg=" . urlencode($msg) : ""));

		exit();
	}

	if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "config" && isset($_REQUEST["provider"]) && isset($sso_providers[$_REQUEST["provider"]]) && isset($_REQUEST["action2"]))
	{
		// Pass the request to the specified provider.
		$sso_provider = $_REQUEST["provider"];
		$sso_providers[$sso_provider]->Config();
	}
	else if ($sso_site_admin && isset($_REQUEST["action"]) && $_REQUEST["action"] == "deleteusertag")
	{
		$row = $sso_db->GetRow("SELECT", array(
			"*",
			"FROM" => "?",
			"WHERE" => "id = ?",
		), $sso_db_users, $_REQUEST["id"]);

		if ($row === false)  BB_RedirectPage("error", "User does not exist.");
		else if (isset($_REQUEST["id2"]))
		{
			$row2 = $sso_db->GetRow("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "id = ?",
			), $sso_db_tags, $_REQUEST["id2"]);

			if ($row2 !== false && ($row2->tag_name != SSO_SITE_ADMIN_TAG || $sso_site_admin))
			{
				$sso_db->Query("DELETE", array($sso_db_user_tags, "WHERE" => "user_id = ? AND tag_id = ?"), $row->id, $row2->id);
			}

			BB_RedirectPage("success", "Successfully deleted the user tag.", array("action=edituser&id=" . $row->id . "&sec_t=" . BB_CreateSecurityToken("edituser")));
		}
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "edituser")
	{
		$row = $sso_db->GetRow("SELECT", array(
			"*",
			"FROM" => "?",
			"WHERE" => "id = ?",
		), $sso_db_users, $_REQUEST["id"]);

		if ($row === false)  BB_RedirectPage("error", "User does not exist.");
		else
		{
			$tags = array("" => "");

			$result = $sso_db->Query("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "enabled = 1",
				"ORDER BY" => "tag_name"
			), $sso_db_tags);

			while ($row2 = $result->NextRow())
			{
				if ($row2->tag_name != SSO_SITE_ADMIN_TAG || $sso_site_admin)  $tags[$row2->id] = $row2->tag_name;
			}

			$sso_provider = $row->provider_name;

			$userinfo = SSO_LoadDecryptedUserInfo($row);
			if ($userinfo === false)  BB_RedirectPage("error", "Unable to load user information.");

			if (isset($sso_providers[$sso_provider]))  $protectedfields = $sso_providers[$sso_provider]->GetProtectedFields();
			else
			{
				foreach ($sso_fields as $key => $encrypted)  $protectedfields[$key] = true;
			}

			$geoip_opts = SSO_GetGeoIPOpts();
			foreach ($geoip_opts as $opt => $val)
			{
				if ($sso_settings[""]["iprestrict"]["geoip_map_" . $opt] != "")  $protectedfields[$sso_settings[""]["iprestrict"]["geoip_map_" . $opt]] = true;
			}

			$fields = $sso_fields;
			foreach ($userinfo as $key => $val)  $fields[$key] = $key;
			if (function_exists("AdminHook_EditUser_PreFields"))  AdminHook_EditUser_PreFields();

			if (isset($_REQUEST["version"]))
			{
				if ((int)$_REQUEST["version"] < 0)  BB_SetPageMessage("error", "Account Version must 0 or higher.");
				if (!isset($tags[$_REQUEST["tag_id"]]))  BB_SetPageMessage("error", "Please select a valid tag.");
				else if ($_REQUEST["tag_id"] == "" && $_REQUEST["tag_reason"] != "")  BB_SetPageMessage("error", "Please select a tag.");
				else if ($_REQUEST["tag_id"] != "" && $_REQUEST["tag_reason"] == "")  BB_SetPageMessage("error", "Please enter a reason for the new tag.");
				else if ($_REQUEST["tag_id"] != "" && strlen($_REQUEST["tag_reason"]) > 100)  BB_SetPageMessage("error", "The reason for the new tag is too long.");

				if (function_exists("AdminHook_EditUser_Check"))  AdminHook_EditUser_Check();

				if (BB_GetPageMessageType() != "error")
				{
					foreach ($fields as $key => $fieldinfo)
					{
						if (substr($key, 0, 5) == "sso__")  continue;

						if (!isset($protectedfields[$key]) || !$protectedfields[$key])
						{
							$userinfo[$key] = (isset($_REQUEST["field_edit_" . md5($key)]) ? $_REQUEST["field_edit_" . md5($key)] : "");
						}
					}

					if (function_exists("AdminHook_EditUser_PostFieldsCheck"))  AdminHook_EditUser_PostFieldsCheck();

					if ($sso_site_admin && isset($_REQUEST["impersonation"]))
					{
						if (!(int)$_REQUEST["impersonation"])
						{
							unset($userinfo["sso__impersonation"]);
							unset($userinfo["sso__impersonation_key"]);
							unset($userinfo["sso__impersonation_auto"]);
						}
						else
						{
							if (!isset($userinfo["sso__impersonation"]))
							{
								$userinfo["sso__impersonation"] = "1";
								$userinfo["sso__impersonation_key"] = $sso_rng->GenerateString(64);
								$userinfo["sso__impersonation_auto"] = "0";
							}

							if (isset($_REQUEST["reset_impersonation_key"]) && $_REQUEST["reset_impersonation_key"] == "yes")  $userinfo["sso__impersonation_key"] = $sso_rng->GenerateString(64);
							if (isset($_REQUEST["impersonation_auto"]))  $userinfo["sso__impersonation_auto"] = (string)(int)$_REQUEST["impersonation_auto"];
						}
					}

					$info2 = SSO_CreateEncryptedUserInfo($userinfo);

					$sso_db->Query("UPDATE", array($sso_db_users, array(
						"version" => (int)$_REQUEST["version"],
						"info" => serialize($userinfo),
						"info2" => $info2,
					), "WHERE" => "id = ?"), $row->id);

					if ($sso_site_admin && $_REQUEST["tag_id"] != "" && $_REQUEST["tag_reason"] != "")
					{
						try
						{
							$sso_db->Query("INSERT", array($sso_db_user_tags, array(
								"user_id" => $row->id,
								"tag_id" => (int)$_REQUEST["tag_id"],
								"issuer_id" => $sso_user_id,
								"reason" => $_REQUEST["tag_reason"],
								"created" => CSDB::ConvertToDBTime(time()),
							)));
						}
						catch (Exception $e)
						{
							// Don't do anything here.  Just catch the database exception and let the code fall through.
							// It should be nearly impossible to get here in the first place.
						}
					}

					BB_RedirectPage("success", "Successfully updated the user.", array("action=edituser&id=" . $row->id . "&sec_t=" . BB_CreateSecurityToken("edituser")));
				}
			}

			$lastipaddr = IPAddr::NormalizeIP($row->lastipaddr);
			$lastipaddrid = $sso_db->GetOne("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "ipaddr = ?",
			), $sso_db_ipcache, $lastipaddr["ipv6"]);

			$desc = "<br />" . implode(" | ", (isset($sso_providers[$sso_provider]) ? $sso_providers[$sso_provider]->GetEditUserLinks($row->provider_id) : array()));

			$contentopts = array(
				"desc" => "Edit the user.",
				"htmldesc" => $desc,
				"nonce" => "action",
				"hidden" => array(
					"action" => "edituser",
					"id" => $row->id
				),
				"fields" => array(
					"startrow",
					array(
						"title" => "User ID",
						"type" => "static",
						"value" => $row->id
					),
					array(
						"title" => "Provider Name",
						"type" => "static",
						"value" => (isset($sso_providers[$sso_provider]) ? $sso_providers[$sso_provider]->DisplayName() : $sso_provider)
					),
					array(
						"title" => "Provider ID",
						"type" => "static",
						"value" => $row->provider_id
					),
					"startrow",
					array(
						"title" => "Account Version",
						"type" => "text",
						"width" => "15em",
						"name" => "version",
						"value" => BB_GetValue("version", $row->version)
					),
					array(
						"title" => "Last IP Address",
						"type" => "custom",
						"value" => ($lastipaddrid ? "<a href=\"" . BB_GetRequestURLBase() . "?action=viewipaddr&id=" . htmlspecialchars($lastipaddrid) . "&sec_t=" . BB_CreateSecurityToken("viewipaddr") . "\">" : "") . htmlspecialchars($lastipaddr["ipv4"] != "" ? $lastipaddr["ipv4"] : $lastipaddr["shortipv6"]) . ($lastipaddrid ? "</a>" : "")
					),
					array(
						"title" => "Last Activated",
						"type" => "static",
						"width" => "15em",
						"value" => BB_FormatTimestamp("M j, Y @ g:i A", CSDB::ConvertFromDBTime($row->lastactivated))
					),
					"endrow",
				),
				"submit" => "Save",
				"focus" => true
			);

			foreach ($fields as $key => $fieldinfo)
			{
				if (substr($key, 0, 5) == "sso__")  continue;

				if (isset($protectedfields[$key]) && $protectedfields[$key])
				{
					$contentopts["fields"][] = array(
						"title" => "Field - '" . $key . "'",
						"type" => "custom",
						"value" => "<div class=\"static\">" . (isset($userinfo[$key]) ? htmlspecialchars($userinfo[$key]) : "<i>" . htmlspecialchars(BB_Translate("Undefined")) . "</i>") . "</div>"
					);
				}
				else
				{
					$contentopts["fields"][] = array(
						"title" => "Field - '" . $key . "'",
						"type" => (isset($userinfo[$key]) && strpos($userinfo[$key], "\n") !== false ? "textarea" : "text"),
						"name" => "field_edit_" . md5($key),
						"value" => BB_GetValue("field_edit_" . md5($key), (isset($userinfo[$key]) ? $userinfo[$key] : "")),
						"desc" => (isset($sso_select_fields[$key]) ? substr($sso_select_fields[$key], strlen($key) + 3) : "")
					);
				}
			}

			if (function_exists("AdminHook_EditUser_PostFields"))  AdminHook_EditUser_PostFields();

			if ($sso_site_admin)
			{
				if (!isset($userinfo["sso__impersonation"]))  $userinfo["sso__impersonation"] = "0";
				$contentopts["fields"][] = array(
					"title" => "Allow User Impersonation?",
					"type" => "select",
					"name" => "impersonation",
					"options" => array("No", "Yes"),
					"select" => BB_GetValue("impersonation", (string)(int)$userinfo["sso__impersonation"])
				);
				if ((int)$userinfo["sso__impersonation"])
				{
					$contentopts["fields"][] = array(
						"title" => "Impersonation Key",
						"type" => "custom",
						"value" => "<div class=\"textareawrap\"><textarea class=\"text\" style=\"background-color: #EEEEEE;\" rows=\"3\" readonly>" . htmlspecialchars($userinfo["sso__impersonation_key"] . "-" . $row->id) . "</textarea></div>",
						"htmldesc" => "<input type=\"checkbox\" id=\"reset_impersonation_key\" name=\"reset_impersonation_key\" value=\"yes\" /> <label for=\"reset_impersonation_key\">" . BB_Translate("Generate new impersonation key") . "</label>"
					);
					$contentopts["fields"][] = array(
						"title" => "Automate User Impersonation Sign In?",
						"type" => "select",
						"name" => "impersonation_auto",
						"options" => array("No", "Yes"),
						"select" => BB_GetValue("impersonation_auto", (string)(int)$userinfo["sso__impersonation_auto"])
					);
				}

				$contentopts["fields"][] = "split";

				$rows = array();
				$result = $sso_db->Query("SELECT", array(
					"*",
					"FROM" => "?",
					"WHERE" => "user_id = ?",
					"ORDER BY" => "created"
				), $sso_db_user_tags, $row->id);
				while ($row2 = $result->NextRow())
				{
					if (isset($tags[$row2->tag_id]))
					{
						$rows[] = array(htmlspecialchars($tags[$row2->tag_id]), BB_FormatTimestamp("M j, Y", CSDB::ConvertFromDBTime($row2->created)), ($row2->issuer_id !== "0" ? "<a href=\"" . BB_GetRequestURLBase() . "?action=edituser&id=" . $row2->issuer_id . "&sec_t=" . BB_CreateSecurityToken("edituser") . "\">" . $row2->issuer_id . "</a>" : 0), htmlspecialchars($row2->reason), "<a href=\"" . BB_GetRequestURLBase() . "?action=deleteusertag&id=" . $row2->user_id . "&id2=" . $row2->tag_id . "&sec_t=" . BB_CreateSecurityToken("deleteusertag") . "\" onclick=\"return confirm('" . htmlspecialchars(BB_JSSafe(BB_Translate("Are you sure you want to remove the tag '%s' from this user?", $tags[$row2->tag_id]))) . "');\">" . BB_Translate("Delete") . "</a>");
						unset($tags[$row2->tag_id]);
					}
				}

				if (count($rows))
				{
					$contentopts["fields"][] = array(
						"title" => "User Tags",
						"type" => "table",
						"cols" => array("Tag Name", "Issued On", "Issued By", "Reason", "Options"),
						"rows" => $rows
					);
				}

				$contentopts["fields"][] = "startrow";
				$contentopts["fields"][] = array(
					"title" => "Add Tag",
					"type" => "select",
					"width" => "15em",
					"name" => "tag_id",
					"options" => $tags,
					"select" => BB_GetValue("tag_id", array()),
				);
				$contentopts["fields"][] = array(
					"title" => "Reason",
					"type" => "text",
					"width" => "33em",
					"name" => "tag_reason",
					"value" => BB_GetValue("tag_reason", ""),
				);
				$contentopts["fields"][] = "endrow";
			}

			BB_GeneratePage("Edit User", $sso_menuopts, $contentopts);
		}
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "finduser")
	{
		if (isset($_REQUEST["opts"]))
		{
			if (BB_GetPageMessageType() != "error")
			{
				$sqlfrom = array("? AS u");
				$sqlwhere = array();
				$sqlvars = array($sso_db_users);
				$optdesc = array();

				// Extract special prefixes.
				$specialopts = array();
				$opts = $_REQUEST["opts"];
				do
				{
					$found = false;
					$pos = strpos($opts, ":");
					if ($pos !== false)
					{
						$key = substr($opts, 0, $pos);
						if ($key === "id" || $key === "provider_name" || $key === "provider_id" || $key === "version" || $key === "lastipaddr" || $key === "tag_name")
						{
							$opts = trim(substr($opts, $pos + 1));

							if (strlen($opts))
							{
								if ($opts{0} === "\"")
								{
									$opts = substr($opts, 1);
									$pos = strpos($opts, $chr);
									if ($pos === false)
									{
										$pos = strlen($opts);
										$opts .= "\"";
									}

									$val = substr($opts, 0, $pos);
									$opts = trim(substr($opts, $pos + 1));
								}
								else
								{
									$pos = strpos($opts, " ");
									if ($pos === false)  $pos = strlen($opts);

									$val = substr($opts, 0, $pos);
									$opts = trim(substr($opts, $pos));
								}

								if ($val !== "")  $specialopts[$key] = $val;

								$found = true;
							}
						}
					}
				} while ($found);

				$tagname = false;
				if (isset($specialopts["tag_name"]))
				{
					$tags = array();
					$row = $sso_db->GetRow("SELECT", array(
						"*",
						"FROM" => "?",
						"WHERE" => "enabled = 1 AND tag_name = ?"
					), $sso_db_tags, $specialopts["tag_name"]);

					if ($row && ($row->tag_name != SSO_SITE_ADMIN_TAG || $sso_site_admin))
					{
						$sqlfrom[] = "? AS ut";
						$sqlwhere[] = "u.id = ut.user_id";
						$sqlwhere[] = "ut.tag_id = ?";
						$sqlvars[] = $sso_db_user_tags;
						$sqlvars[] = $row->id;
						$tagname = $row->tag_name;
					}
				}

				if (isset($specialopts["id"]))
				{
					$sqlwhere[] = "u.id = ?";
					$sqlvars[] = $specialopts["id"];
					$optdesc[] = htmlspecialchars(BB_Translate("User ID:  %s", $specialopts["id"]));
				}
				if (isset($specialopts["provider_name"]) && isset($sso_providers[$specialopts["provider_name"]]))
				{
					$sqlwhere[] = "u.provider_name = ?";
					$sqlvars[] = $specialopts["provider_name"];
					$optdesc[] = htmlspecialchars(BB_Translate("Provider Name:  %s", $sso_providers[$specialopts["provider_name"]]->DisplayName()));
				}
				if (isset($specialopts["provider_id"]))
				{
					$sqlwhere[] = "u.provider_id = ?";
					$sqlvars[] = $specialopts["provider_id"];
					$optdesc[] = htmlspecialchars(BB_Translate("Provider ID:  %s", $specialopts["provider_id"]));
				}
				if (isset($specialopts["version"]) && (int)$specialopts["version"] >= 0)
				{
					$sqlwhere[] = "u.version = ?";
					$sqlvars[] = (int)$specialopts["version"];
					$optdesc[] = htmlspecialchars(BB_Translate("Version:  %s", (int)$specialopts["version"]));
				}
				if (isset($specialopts["lastipaddr"]))
				{
					$sqlwhere[] = "u.lastipaddr = ?";
					$ipaddr = IPAddr::NormalizeIP($specialopts["lastipaddr"]);
					$sqlvars[] = $ipaddr["ipv6"];
					$optdesc[] = htmlspecialchars(BB_Translate("IP Address:  %s", $ipaddr["ipv6"] . ($ipaddr["ipv4"] != "" ? " (" . $ipaddr["ipv4"] . ")" : "")));
				}
				if ($tagname !== false)  $optdesc[] = htmlspecialchars(BB_Translate("Tag:  %s", $tagname));

				if ($opts === "")  $opts = array();
				else  $opts = explode(" ", preg_replace('/\s+/', " ", $opts));

				if (count($opts))
				{
					foreach ($opts as $opt)
					{
						$sqlwhere[] = "u.info LIKE ?";
						$sqlvars[] = "%" . $opt . "%";
					}

					$optdesc[] = htmlspecialchars(BB_Translate("Unencrypted fields contain:  %s", implode(", ", $opts)));
				}

				if (!count($optdesc))  $optdesc[] = BB_Translate("Latest Accounts");

				$desc = "<ul><li>" . implode("</li><li>", $optdesc) . "</li></ul>";

				SSO_LoadFieldSearchOrder();

				$rows = array();
				$sqlopts = array(
					"u.*",
					"FROM" => implode(", ", $sqlfrom),
					"LIMIT" => "300"
				);
				if (count($sqlwhere))  $sqlopts["WHERE"] = implode(" AND ", $sqlwhere);
				else  $sqlopts["ORDER BY"] = "u.id DESC";
				$result = $sso_db->Query("SELECT", $sqlopts, $sqlvars);
				while ($row = $result->NextRow())
				{
					$userinfo = SSO_LoadDecryptedUserInfo($row);

					$user = "";
					foreach ($sso_settings[""]["search_order"] as $key => $display)
					{
						$desc2 = false;
						$val = false;

						if ($key === "id")
						{
							$desc2 = "Account ID";
							$val = $row->id;
						}
						else if ($key === "provider_name")
						{
							$desc2 = "Provider Name";
							$val = $row->provider_name . (isset($sso_providers[$row->provider_name]) ? " - " . $sso_providers[$row->provider_name]->DisplayName() : "");
						}
						else if ($key === "provider_id")
						{
							$desc2 = "Provider ID";
							$val = $row->provider_id;
						}
						else if ($key === "version")
						{
							$desc2 = "Account Version";
							$val = $row->version;
						}
						else if ($key === "lastipaddr")
						{
							$desc2 = "Last IP Address";
							$ipaddr = IPAddr::NormalizeIP($row->lastipaddr);
							$val = $ipaddr["ipv6"] . ($ipaddr["ipv4"] != "" ? " (" . $ipaddr["ipv4"] . ")" : "");
						}
						else if ($key === "lastactivated")
						{
							$desc2 = "Last Activated";
							$val = BB_FormatTimestamp("M j, Y @ g:i A", CSDB::ConvertFromDBTime($row->lastactivated));
						}
						else if ($key === "tag_id")
						{
							$desc2 = "Tag";
							$val = $tagname;
						}
						else if (substr($key, 0, 6) === "field_")
						{
							if (isset($sso_fields[substr($key, 6)]))
							{
								$desc2 = substr($key, 6);
								$val = (isset($userinfo[$desc2]) ? $userinfo[$desc2] : "");
							}
						}

						if ($desc2 !== false && $val !== false)
						{
							$val = htmlspecialchars($val);

							$found = false;
							foreach ($opts as $opt)
							{
								if (stripos($val, $opt) !== false)
								{
									$val = str_ireplace($opt, "<b>" . $opt . "</b>", $val);
									$found = true;
								}
							}

							if (($display && $val != "") || $found)
							{
								$user .= "<div class=\"search_field\"><div class=\"search_field_key\">" . htmlspecialchars($desc2) . "</div><div class=\"search_field_val\">" . $val . "</div></div>";
							}
						}
					}

					$rows[] = array($user, "<a href=\"" . BB_GetRequestURLBase() . "?action=edituser&id=" . htmlspecialchars($row->id) . "&sec_t=" . BB_CreateSecurityToken("edituser") . "\">Edit</a>");
				}

				ob_start();
?>
<style type="text/css">
div.formfields div.formitem table div.search_field {
	float: left;
	border: 1px solid #CCCCCC;
	margin: 0.5em 1.0em 0.5em 0;
}

div.formfields div.formitem table div.search_field_key {
	float: left;
	padding: 0.2em 0.5em;
	background-color: #EEEEEE;
	border-right: 1px solid #CCCCCC;
}

div.formfields div.formitem table div.search_field_val {
	float: left;
	padding: 0.2em 0.5em;
}

div.formfields div.formitem table div.search_field_val b {
	background-color: #FFFBCC;
}
</style>
<?php
				$desc .= ob_get_contents();
				ob_end_clean();

				$contentopts = array(
					"desc" => "Search results for users with the following search options:",
					"htmldesc" => $desc,
					"fields" => array(
						array(
							"type" => "table",
							"cols" => array("User", "Options"),
							"rows" => $rows
						)
					)
				);

				// Let providers add their own search results.
				foreach ($sso_providers as $sso_provider => &$instance)
				{
					$instance->FindUsers();
				}

				if (count($contentopts["fields"]) > 1)  $contentopts["fields"][0]["title"] = "SSO Server";

				BB_GeneratePage("Search Results", $sso_menuopts, $contentopts);

				exit();
			}
		}

		$encryptedfields = array();
		foreach ($sso_fields as $key => $encrypted)
		{
			if ($encrypted)  $encryptedfields[] = $key;
		}

		$contentopts = array(
			"desc" => "Find a user.",
			"nonce" => "action",
			"hidden" => array(
				"action" => "finduser",
			),
			"fields" => array(
				array(
					"title" => "Search Terms",
					"type" => "text",
					"name" => "opts",
					"value" => BB_GetValue("opts", ""),
					"desc" => "Runs an AND query for the specified search terms across all non-encrypted fields.  Leave blank for the 300 most recent users.  Some providers may also run searches."
				),
				(count($encryptedfields) ? array(
					"title" => "Encrypted Fields",
					"type" => "static",
					"value" => htmlspecialchars(implode(", ", $encryptedfields)),
				) : ""),
				array(
					"title" => "Special Prefixes",
					"type" => "static",
					"value" => "id, provider_name, provider_id, version, lastipaddr, tag_name",
					"desc" => "If Search Terms starts with one of these prefixes, a colon, and a value, an exact match will be made.  Prefixes must appear before other search terms.  (e.g. id:5 would retrieve the user account with ID 5.)"
				),
				array(
					"title" => "Internal Provider Names",
					"type" => "static",
					"value" => htmlspecialchars(implode(", ", array_keys($sso_providers))),
					"desc" => "When using the 'provider_name' prefix above, it expects the internal provider name to be used."
				),
			),
			"submit" => "Search",
			"focus" => true
		);

		BB_GeneratePage("Find User", $sso_menuopts, $contentopts);
	}
	else if ($sso_site_admin && isset($_REQUEST["action"]) && $_REQUEST["action"] == "addfield")
	{
		if (isset($_REQUEST["name"]))
		{
			$_REQUEST["name"] = UTF8::MakeValid($_REQUEST["name"]);
			if ($_REQUEST["name"] == "" || is_numeric($_REQUEST["name"]))  BB_SetPageMessage("error", "Please fill in 'Field Name'.");
			else if (substr($_REQUEST["name"], 0, 5) == "sso__")  BB_SetPageMessage("error", "The 'Field Name' field contains a reserved prefix.");
			else if ($sso_db->GetOne("SELECT", array("COUNT(*)", "FROM" => "?", "WHERE" => "field_name = ?"), $sso_db_fields, $_REQUEST["name"]))  BB_SetPageMessage("error", "The Field Name '" . $_REQUEST["name"] . "' already exists.");
			else if ($sso_db->GetOne("SELECT", array("COUNT(*)", "FROM" => "?", "WHERE" => "field_hash = ?"), $sso_db_fields, md5($_REQUEST["name"])))  BB_SetPageMessage("error", "The Field Name has a MD5 hash collision with another field.");
			else if ($_REQUEST["desc"] == "")  BB_SetPageMessage("error", "Please fill in 'Field Description'.");

			if (BB_GetPageMessageType() != "error")
			{
				$sso_db->Query("INSERT", array($sso_db_fields, array(
					"field_name" => $_REQUEST["name"],
					"field_desc" => $_REQUEST["desc"],
					"field_hash" => md5($_REQUEST["name"]),
					"encrypted" => ((int)$_REQUEST["encrypt"] ? 1 : 0),
					"enabled" => 1,
					"created" => CSDB::ConvertToDBTime(time()),
				)));

				BB_RedirectPage("success", "Successfully created the field.", array("action=managefields&sec_t=" . BB_CreateSecurityToken("managefields")));
			}
		}

		$contentopts = array(
			"desc" => "Add a new field.",
			"nonce" => "action",
			"hidden" => array(
				"action" => "addfield"
			),
			"fields" => array(
				array(
					"title" => "Field Name",
					"type" => "text",
					"name" => "name",
					"value" => BB_GetValue("name", ""),
					"desc" => "The name of the field to create.  (e.g. 'first_name', 'email')"
				),
				array(
					"title" => "Field Description",
					"type" => "text",
					"name" => "desc",
					"value" => BB_GetValue("desc", ""),
					"desc" => "A short description of this field and what it is for."
				),
				array(
					"title" => "Encrypt Field?",
					"type" => "select",
					"name" => "encrypt",
					"options" => array("No", "Yes"),
					"select" => BB_GetValue("encrypt", "0"),
					"desc" => "When enabled, data in this field will be encrypted and information can be viewed and edited but the field will not be searchable."
				)
			),
			"submit" => "Create",
			"focus" => true
		);

		BB_GeneratePage("Add Field", $sso_menuopts, $contentopts);
	}
	else if ($sso_site_admin && isset($_REQUEST["action"]) && $_REQUEST["action"] == "togglefield")
	{
		$row = $sso_db->GetRow("SELECT", array(
			"*",
			"FROM" => "?",
			"WHERE" => "id = ?",
		), $sso_db_fields, $_REQUEST["id"]);

		if ($row)
		{
			if ($_REQUEST["type"] == "enabled")
			{
				$sso_db->Query("UPDATE", array($sso_db_fields, array(
					"enabled" => ($row->enabled ? 0 : 1),
				), "WHERE" => "id = ?"), $row->id);

				BB_RedirectPage("success", "Successfully " . ($row->enabled ? "disabled" : "enabled") . " the field.", array("action=managefields&sec_t=" . BB_CreateSecurityToken("managefields")));
			}
			else if ($_REQUEST["type"] == "encrypted")
			{
				$sso_db->Query("UPDATE", array($sso_db_fields, array(
					"encrypted" => ($row->encrypted ? 0 : 1),
				), "WHERE" => "id = ?"), $row->id);

				BB_RedirectPage("success", "Successfully " . ($row->encrypted ? "disabled" : "enabled") . " encryption for the field.", array("action=managefields&sec_t=" . BB_CreateSecurityToken("managefields")));
			}
		}

		BB_RedirectPage("error", "Unable to find field.", array("action=managefields&sec_t=" . BB_CreateSecurityToken("managefields")));
	}
	else if ($sso_site_admin && isset($_REQUEST["action"]) && $_REQUEST["action"] == "deletefield")
	{
		if (isset($_REQUEST["id"]))  $sso_db->Query("DELETE", array($sso_db_fields, "WHERE" => "id = ?"), $_REQUEST["id"]);

		BB_RedirectPage("success", "Successfully deleted the field.", array("action=managefields&sec_t=" . BB_CreateSecurityToken("managefields")));
	}
	else if ($sso_site_admin && isset($_REQUEST["action"]) && $_REQUEST["action"] == "managefields")
	{
		$desc = "<br />";
		$desc .= "<a href=\"" . BB_GetRequestURLBase() . "?action=addfield&sec_t=" . BB_CreateSecurityToken("addfield") . "\">Add Field</a>";

		$rows = array();

		$result = $sso_db->Query("SELECT", array(
			"*",
			"FROM" => "?",
			"ORDER BY" => "field_name",
		), $sso_db_fields);

		while ($row = $result->NextRow())
		{
			$rows[] = array(htmlspecialchars($row->field_name), htmlspecialchars($row->field_desc), BB_Translate($row->enabled ? "Yes" : "No"), BB_Translate($row->encrypted ? "Yes" : "No"), "<a href=\"" . BB_GetRequestURLBase() . "?action=togglefield&id=" . $row->id . "&type=enabled&sec_t=" . BB_CreateSecurityToken("togglefield") . "\">" . htmlspecialchars(BB_Translate($row->enabled ? "Disable" : "Enable")) . "</a> | <a href=\"" . BB_GetRequestURLBase() . "?action=togglefield&id=" . $row->id . "&type=encrypted&sec_t=" . BB_CreateSecurityToken("togglefield") . "\" onclick=\"return confirm('" . htmlspecialchars(BB_JSSafe(BB_Translate("Toggling the encryption status of fields doesn't immediately affect existing data.  Are you sure you want to toggle the encryption status of '%s'?", $row->field_name))) . "');\">" . htmlspecialchars(BB_Translate($row->encrypted ? "Decrypt" : "Encrypt")) . "</a> | <a href=\"" . BB_GetRequestURLBase() . "?action=deletefield&id=" . $row->id . "&sec_t=" . BB_CreateSecurityToken("deletefield") . "\" onclick=\"return confirm('" . htmlspecialchars(BB_JSSafe(BB_Translate("Deleting fields doesn't affect existing data but disabling is usually better.  Are you sure you want to delete '%s'?", $row->field_name))) . "');\">" . htmlspecialchars(BB_Translate("Delete")) . "</a>");
		}

		$contentopts = array(
			"desc" => "Manage user fields.",
			"htmldesc" => $desc,
			"fields" => array(
				array(
					"type" => "table",
					"cols" => array("Field", "Description", "Enabled", "Encrypted", "Options"),
					"rows" => $rows
				)
			)
		);

		BB_GeneratePage("Manage Fields", $sso_menuopts, $contentopts);
	}
	else if ($sso_site_admin && isset($_REQUEST["action"]) && $_REQUEST["action"] == "addtag")
	{
		if (isset($_REQUEST["name"]))
		{
			$_REQUEST["name"] = UTF8::MakeValid($_REQUEST["name"]);
			if ($_REQUEST["name"] == "" || is_numeric($_REQUEST["name"]))  BB_SetPageMessage("error", "Please fill in 'Tag Name'.");
			else if ($sso_db->GetOne("SELECT", array("COUNT(*)", "FROM" => "?", "WHERE" => "tag_name = ?"), $sso_db_tags, $_REQUEST["name"]))  BB_SetPageMessage("error", "The Tag Name '" . $_REQUEST["name"] . "' already exists.");
			else if ($_REQUEST["desc"] == "")  BB_SetPageMessage("error", "Please fill in 'Tag Description'.");

			if (BB_GetPageMessageType() != "error")
			{
				$sso_db->Query("INSERT", array($sso_db_tags, array(
					"tag_name" => $_REQUEST["name"],
					"tag_desc" => $_REQUEST["desc"],
					"enabled" => 1,
					"created" => CSDB::ConvertToDBTime(time()),
				)));

				BB_RedirectPage("success", "Successfully created the tag.", array("action=managetags&sec_t=" . BB_CreateSecurityToken("managetags")));
			}
		}

		$contentopts = array(
			"desc" => "Add a new tag.",
			"nonce" => "action",
			"hidden" => array(
				"action" => "addtag"
			),
			"fields" => array(
				array(
					"title" => "Tag Name",
					"type" => "text",
					"name" => "name",
					"value" => BB_GetValue("name", ""),
					"desc" => "The name of the tag to create.  (e.g. 'forum_moderator', 'bb_developer')"
				),
				array(
					"title" => "Tag Description",
					"type" => "text",
					"name" => "desc",
					"value" => BB_GetValue("desc", ""),
					"desc" => "A short description of this tag and what it is for."
				)
			),
			"submit" => "Create",
			"focus" => true
		);

		BB_GeneratePage("Add Tag", $sso_menuopts, $contentopts);
	}
	else if ($sso_site_admin && isset($_REQUEST["action"]) && $_REQUEST["action"] == "toggletag")
	{
		$row = $sso_db->GetRow("SELECT", array(
			"*",
			"FROM" => "?",
			"WHERE" => "id = ?",
		), $sso_db_tags, $_REQUEST["id"]);

		if ($row)
		{
			if ($row->tag_name != SSO_SITE_ADMIN_TAG && $row->tag_name != SSO_ADMIN_TAG && $row->tag_name != SSO_LOCKED_TAG)
			{
				$sso_db->Query("UPDATE", array($sso_db_tags, array(
					"enabled" => ($row->enabled ? 0 : 1),
				), "WHERE" => "id = ?"), $row->id);
			}

			BB_RedirectPage("success", "Successfully " . ($row->enabled ? "disabled" : "enabled") . " the tag.", array("action=managetags&sec_t=" . BB_CreateSecurityToken("managetags")));
		}

		BB_RedirectPage("error", "Unable to find tag.", array("action=managetags&sec_t=" . BB_CreateSecurityToken("managetags")));
	}
	else if ($sso_site_admin && isset($_REQUEST["action"]) && $_REQUEST["action"] == "deletetag")
	{
		if (isset($_REQUEST["id"]))
		{
			$sso_db->Query("DELETE", array($sso_db_user_tags, "WHERE" => "tag_id = ?"), $_REQUEST["id"]);
			$sso_db->Query("DELETE", array($sso_db_tags, "WHERE" => "id = ?"), $_REQUEST["id"]);
		}

		BB_RedirectPage("success", "Successfully deleted the tag.", array("action=managetags&sec_t=" . BB_CreateSecurityToken("managetags")));
	}
	else if ($sso_site_admin && isset($_REQUEST["action"]) && $_REQUEST["action"] == "managetags")
	{
		$desc = "<br />";
		$desc .= "<a href=\"" . BB_GetRequestURLBase() . "?action=addtag&sec_t=" . BB_CreateSecurityToken("addtag") . "\">Add Tag</a>";

		$rows = array();

		$result = $sso_db->Query("SELECT", array(
			"*",
			"FROM" => "?",
			"ORDER BY" => "tag_name",
		), $sso_db_tags);

		while ($row = $result->NextRow())
		{
			$rows[] = array(htmlspecialchars($row->tag_name), htmlspecialchars($row->tag_desc), BB_Translate($row->enabled ? "Yes" : "No"), ($row->tag_name == SSO_SITE_ADMIN_TAG || $row->tag_name == SSO_ADMIN_TAG || $row->tag_name == SSO_LOCKED_TAG ? "" : "<a href=\"" . BB_GetRequestURLBase() . "?action=toggletag&id=" . $row->id . "&sec_t=" . BB_CreateSecurityToken("toggletag") . "\">" . htmlspecialchars(BB_Translate($row->enabled ? "Disable" : "Enable")) . "</a> | <a href=\"" . BB_GetRequestURLBase() . "?action=deletetag&id=" . $row->id . "&sec_t=" . BB_CreateSecurityToken("deletetag") . "\" onclick=\"return confirm('" . htmlspecialchars(BB_JSSafe(BB_Translate("Deleting a tag will affect all users with the tag.  Are you sure you want to delete '%s'?", $row->tag_name))) . "');\">" . htmlspecialchars(BB_Translate("Delete")) . "</a>"));
		}

		$contentopts = array(
			"desc" => "Manage tags.",
			"htmldesc" => $desc,
			"fields" => array(
				array(
					"type" => "table",
					"cols" => array("Tag Name", "Description", "Enabled", "Options"),
					"rows" => $rows
				)
			)
		);

		BB_GeneratePage("Manage Tags", $sso_menuopts, $contentopts);
	}
	else if ($sso_site_admin && isset($_REQUEST["action"]) && $_REQUEST["action"] == "editapikey")
	{
		$row = $sso_db->GetRow("SELECT", array(
			"*",
			"FROM" => "?",
			"WHERE" => "id = ?",
		), $sso_db_apikeys, $_REQUEST["id"]);

		if ($row)
		{
			$info = unserialize($row->info);
			if (!isset($info["type"]))  $info["type"] = "normal";
			if (!isset($info["impersonation"]))  $info["impersonation"] = false;
			if (!isset($info["clock_drift"]))  $info["clock_drift"] = 0;

			if (isset($_REQUEST["purpose"]))
			{
				if ($_REQUEST["purpose"] == "")  BB_SetPageMessage("error", "Please fill in 'Purpose'.");
				if (strlen($_REQUEST["namespace"]) > 20)  BB_SetPageMessage("error", "'Namespace' can only be 20 characters long.");
				if ($_REQUEST["type"] != "normal" && $_REQUEST["type"] != "remote" && $_REQUEST["type"] != "custom")  BB_SetPageMessage("error", "Please select a 'Type'.");
				if ((int)$_REQUEST["clock_drift"] < 0)  BB_SetPageMessage("error", "Invalid clock drift specified.");
				if ($_REQUEST["cipher"] != "blowfish" && $_REQUEST["cipher"] != "aes256")  BB_SetPageMessage("error", "Please select a 'Symmetric Cipher'.");

				if (BB_GetPageMessageType() != "error")
				{
					if (!isset($_REQUEST["reset_key"]) || $_REQUEST["reset_key"] != "yes")  $secretkey = $info["key"];
					else
					{
						$secretkey = $_REQUEST["cipher"];

						$secretkey .= ":" . $sso_rng->GenerateToken($_REQUEST["cipher"] == "aes256" ? 32 : 56);
						$secretkey .= ":" . $sso_rng->GenerateToken($_REQUEST["cipher"] == "aes256" ? 32 : 8);
						if ($_REQUEST["dual_encrypt"] > 0)
						{
							$secretkey .= ":" . $sso_rng->GenerateToken($_REQUEST["cipher"] == "aes256" ? 32 : 56);
							$secretkey .= ":" . $sso_rng->GenerateToken($_REQUEST["cipher"] == "aes256" ? 32 : 8);
						}
					}

					$info = array(
						"key" => $secretkey,
						"type" => $_REQUEST["type"],
						"purpose" => $_REQUEST["purpose"],
						"url" => $_REQUEST["url"],
						"impersonation" => (bool)(int)$_REQUEST["impersonation"],
						"clock_drift" => (int)$_REQUEST["clock_drift"],
						"field_map" => array(),
						"tag_map" => array(),
						"patterns" => $_REQUEST["patterns"]
					);

					foreach ($sso_fields as $key => $encrypted)
					{
						$md5key = md5($key);
						if (isset($_REQUEST["field_map_" . $md5key]) && $_REQUEST["field_map_" . $md5key] != "" && isset($_REQUEST["field_perms_" . $md5key]))
						{
							$info["field_map"][$key] = array("name" => $_REQUEST["field_map_" . $md5key], "perms" => $_REQUEST["field_perms_" . $md5key]);
						}
					}

					$result = $sso_db->Query("SELECT", array(
						"*",
						"FROM" => "?",
						"ORDER BY" => "tag_name",
					), $sso_db_tags);

					while ($row2 = $result->NextRow())
					{
						if ($row2->tag_name != SSO_SITE_ADMIN_TAG && $row2->tag_name != SSO_LOCKED_TAG && isset($_REQUEST["tag_map_" . $row2->id]) && $_REQUEST["tag_map_" . $row2->id] != "")
						{
							$info["tag_map"][$row2->tag_name] = $_REQUEST["tag_map_" . $row2->id];
						}
					}

					$sso_db->Query("UPDATE", array($sso_db_apikeys, array(
						"namespace" => strtolower($_REQUEST["namespace"]),
						"info" => serialize($info),
					), "WHERE" => "id = ?"), $row->id);

					BB_RedirectPage("success", "Successfully updated the API key.");
				}
			}

			$contentopts = array(
				"desc" => "Edit API key.",
				"nonce" => "action",
				"hidden" => array(
					"action" => "editapikey",
					"id" => $row->id
				),
				"fields" => array(
					array(
						"title" => "Endpoint URL",
						"type" => "static",
						"value" => (function_exists("AdminHook_GetEndpointURL") ? AdminHook_GetEndpointURL() : SSO_ENDPOINT_URL)
					),
					array(
						"title" => "API Key",
						"type" => "static",
						"value" => $row->apikey . "-" . $row->id
					),
					array(
						"title" => "Secret Key",
						"type" => "custom",
						"value" => "<div class=\"textareawrap\"><textarea class=\"text\" style=\"background-color: #EEEEEE;\" rows=\"3\" readonly>" . htmlspecialchars($info["key"]) . "</textarea></div>",
						"htmldesc" => "<input type=\"checkbox\" id=\"reset_key\" name=\"reset_key\" value=\"yes\" /> <label for=\"reset_key\">" . BB_Translate("Generate new secret key") . "</label>"
					),
					array(
						"title" => "Symmetric Cipher",
						"type" => "select",
						"name" => "cipher",
						"options" => array("blowfish" => "Blowfish", "aes256" => "AES-256"),
						"value" => BB_GetValue("cipher", ""),
						"desc" => "Used when generating a new secret key.  The cipher to use to encrypt/decrypt data sent across the network.  The ordering of the ciphers is intentional."
					),
					array(
						"title" => "Dual Encryption",
						"type" => "select",
						"name" => "dual_encrypt",
						"options" => array("1" => "Yes", "0" => "No"),
						"value" => BB_GetValue("dual_encrypt", ""),
						"desc" => "Used when generating a new secret key.  Generate two keys and two IVs to use to encrypt/decrypt data sent across the network."
					),
					array(
						"title" => "Namespace",
						"type" => "text",
						"name" => "namespace",
						"value" => BB_GetValue("namespace", $row->namespace),
						"desc" => "The namespace for this API key.  When two API keys share the same namespace, a user can sign in with one API key and automatically sign in with the other API key.  20 characters or less, case-insensitive."
					),
					array(
						"title" => "Type",
						"type" => "select",
						"name" => "type",
						"options" => array("normal" => "Normal", "remote" => "Remote", "custom" => "Custom"),
						"select" => BB_GetValue("type", $info["type"]),
						"desc" => "The type of this API key.  Normal API keys allow the client to only call SSO_Login().  Remote API keys allow the client to only call SSO_RemoteLogin().  Custom API keys require writing a custom endpoint hook."
					),
					array(
						"title" => "Purpose",
						"type" => "text",
						"name" => "purpose",
						"value" => BB_GetValue("purpose", $info["purpose"]),
						"desc" => "The purpose for this API key's existence.  Keep it to a short description."
					),
					array(
						"title" => "Live URL",
						"type" => "text",
						"name" => "url",
						"value" => BB_GetValue("url", $info["url"]),
						"desc" => "An optional URL where the public will access the system using the API key.  Useful for reverse engineering future issues."
					),
					array(
						"title" => "User Impersonation?",
						"type" => "select",
						"name" => "impersonation",
						"options" => array("No", "Yes"),
						"select" => BB_GetValue("impersonation", (string)(int)$info["impersonation"]),
						"desc" => "Specifies whether or not the API key supports user impersonation.  This only works for accounts that have user impersonation enabled and applications with user impersonation support."
					),
					array(
						"title" => "Clock Drift",
						"type" => "text",
						"name" => "clock_drift",
						"value" => BB_GetValue("clock_drift", $info["clock_drift"]),
						"desc" => "The number of seconds to allow for server-side clock drift.  The default is 0, which uses the global server setting."
					),
					array(
						"title" => "Whitelist IP Address Patterns",
						"type" => "textarea",
						"height" => "150px",
						"name" => "patterns",
						"value" => BB_GetValue("patterns", $info["patterns"]),
						"desc" => "A whitelist of IP address patterns that allows access to this API key.  One pattern per line.  (e.g. '10.0.0-15,17.*')"
					)
				),
				"submit" => "Save",
				"focus" => true
			);

			if (count($sso_fields))
			{
				$contentopts["fields"][] = "split";
				foreach ($sso_fields as $key => $encrypted)
				{
					$contentopts["fields"][] = "startrow";
					$contentopts["fields"][] = array(
						"title" => "Fields - Map '" . $key . "'",
						"type" => "text",
						"name" => "field_map_" . md5($key),
						"value" => BB_GetValue("field_map_" . md5($key), (isset($info["field_map"][$key]) ? $info["field_map"][$key]["name"] : "")),
						"desc" => "The field name to map the field to for the SSO client."
					);
					$contentopts["fields"][] = array(
						"title" => "Permissions",
						"type" => "select",
						"name" => "field_perms_" . md5($key),
						"options" => array("r" => "Read Only", "rw" => "Read/Write"),
						"select" => BB_GetValue("field_perms_" . md5($key), (isset($info["field_map"][$key]) ? $info["field_map"][$key]["perms"] : "r")),
						"desc" => "The SSO client permissions."
					);
 					$contentopts["fields"][] = "endrow";
				}
			}

			$found = false;

			$result = $sso_db->Query("SELECT", array(
				"*",
				"FROM" => "?",
				"ORDER BY" => "tag_name",
			), $sso_db_tags);

			while ($row2 = $result->NextRow())
			{
				if ($row2->tag_name != SSO_SITE_ADMIN_TAG && $row2->tag_name != SSO_LOCKED_TAG)
				{
					if (!$found)
					{
						$contentopts["fields"][] = "split";
						$found = true;
					}

					$contentopts["fields"][] = array(
						"title" => "Tags - Map '" . $row2->tag_name . "'",
						"type" => "text",
						"name" => "tag_map_" . $row2->id,
						"value" => BB_GetValue("tag_map_" . $row2->id, (isset($info["tag_map"][$row2->tag_name]) ? $info["tag_map"][$row2->tag_name] : "")),
						"desc" => "The tag name to map the tag to for the SSO client." . ($row2->enabled ? "" : "  This tag is disabled.")
					);
				}
			}

			BB_GeneratePage("Edit API Key", $sso_menuopts, $contentopts);
		}
	}
	else if ($sso_site_admin && isset($_REQUEST["action"]) && $_REQUEST["action"] == "addapikey")
	{
		if (isset($_REQUEST["purpose"]))
		{
			if ($_REQUEST["purpose"] == "")  BB_SetPageMessage("error", "Please fill in 'Purpose'.");
			if (strlen($_REQUEST["namespace"]) > 20)  BB_SetPageMessage("error", "'Namespace' can only be 20 characters long.");
			if ($_REQUEST["cipher"] != "blowfish" && $_REQUEST["cipher"] != "aes256")  BB_SetPageMessage("error", "Please select a 'Symmetric Cipher'.");

			if (BB_GetPageMessageType() != "error")
			{
				$apikey = $sso_rng->GenerateString();

				$secretkey = $_REQUEST["cipher"];

				$secretkey .= ":" . $sso_rng->GenerateToken($_REQUEST["cipher"] == "aes256" ? 32 : 56);
				$secretkey .= ":" . $sso_rng->GenerateToken($_REQUEST["cipher"] == "aes256" ? 32 : 8);
				if ($_REQUEST["dual_encrypt"] > 0)
				{
					$secretkey .= ":" . $sso_rng->GenerateToken($_REQUEST["cipher"] == "aes256" ? 32 : 56);
					$secretkey .= ":" . $sso_rng->GenerateToken($_REQUEST["cipher"] == "aes256" ? 32 : 8);
				}

				$info = array(
					"key" => $secretkey,
					"type" => "normal",
					"purpose" => $_REQUEST["purpose"],
					"url" => $_REQUEST["url"],
					"impersonation" => false,
					"clock_drift" => 0,
					"field_map" => array(),
					"tag_map" => array(),
					"patterns" => "*:*:*:*:*:*:*:*"
				);

				$sso_db->Query("INSERT", array($sso_db_apikeys, array(
					"user_id" => $sso_user_id,
					"apikey" => $apikey,
					"namespace" => strtolower($_REQUEST["namespace"]),
					"created" => CSDB::ConvertToDBTime(time()),
					"info" => serialize($info),
				), "AUTO INCREMENT" => "id"));

				$id = $sso_db->GetInsertID();

				BB_RedirectPage("success", "Successfully created the API key.", array("action=editapikey&id=" . $id . "&sec_t=" . BB_CreateSecurityToken("editapikey")));
			}
		}

		$contentopts = array(
			"desc" => "Add API key.  Manually create a new API key.",
			"nonce" => "action",
			"hidden" => array(
				"action" => "addapikey"
			),
			"fields" => array(
				array(
					"title" => "Namespace",
					"type" => "text",
					"name" => "namespace",
					"value" => BB_GetValue("namespace", ""),
					"desc" => "The namespace for this API key.  When two API keys share the same namespace, a user can sign in with one API key and automatically sign in with the other API key.  Maximum of 20 characters, case-insensitive."
				),
				array(
					"title" => "Purpose",
					"type" => "text",
					"name" => "purpose",
					"value" => BB_GetValue("purpose", ""),
					"desc" => "The purpose for this API key's existence.  Keep it to a short description."
				),
				array(
					"title" => "Live URL",
					"type" => "text",
					"name" => "url",
					"value" => BB_GetValue("url", ""),
					"desc" => "An optional URL where the public will access the system using the API key.  Useful for reverse engineering future issues."
				),
				array(
					"title" => "Symmetric Cipher",
					"type" => "select",
					"name" => "cipher",
					"options" => array("blowfish" => "Blowfish", "aes256" => "AES-256"),
					"value" => BB_GetValue("cipher", ""),
					"desc" => "The cipher to use to encrypt/decrypt data sent across the network.  The ordering of the ciphers is intentional."
				),
				array(
					"title" => "Dual Encryption",
					"type" => "select",
					"name" => "dual_encrypt",
					"options" => array("1" => "Yes", "0" => "No"),
					"value" => BB_GetValue("dual_encrypt", ""),
					"desc" => "Generate two keys and two IVs to use to encrypt/decrypt data sent across the network."
				)
			),
			"submit" => "Create",
			"focus" => true
		);

		BB_GeneratePage("Add API Key", $sso_menuopts, $contentopts);
	}
	else if ($sso_site_admin && isset($_REQUEST["action"]) && $_REQUEST["action"] == "deleteapikey")
	{
		$row = $sso_db->GetRow("SELECT", array(
			"*",
			"FROM" => "?",
			"WHERE" => "id = ?",
		), $sso_db_apikeys, $_REQUEST["id"]);

		if ($row)
		{
			$sso_db->Query("DELETE", array($sso_db_apikeys, "WHERE" => "id = ?"), $row->id);
			$sso_db->Query("DELETE", array($sso_db_user_sessions, "WHERE" => "apikey_id = ?"), $row->id);
			$sso_db->Query("DELETE", array($sso_db_temp_sessions, "WHERE" => "apikey_id = ?"), $row->id);
		}

		BB_RedirectPage("success", "Successfully deleted the API key.", array("action=manageapikeys&sec_t=" . BB_CreateSecurityToken("manageapikeys")));
	}
	else if ($sso_site_admin && isset($_REQUEST["action"]) && $_REQUEST["action"] == "manageapikeys")
	{
		$desc = "<br />";
		$desc .= "<a href=\"" . BB_GetRequestURLBase() . "?action=addapikey&sec_t=" . BB_CreateSecurityToken("addapikey") . "\">Add API Key</a>";
		$desc .= " | <a href=\"" . BB_GetRequestURLBase() . "?action=manageapikeys&showall=1&sec_t=" . BB_CreateSecurityToken("manageapikeys") . "\">Show All</a>";

		$rows = array();
		if (isset($_REQUEST["showall"]))  $result = $sso_db->Query("SELECT", array("*", "FROM" => "?"), $sso_db_apikeys);
		else
		{
			if ($sso_user_id !== "0")
			{
				$result = $sso_db->Query("SELECT", array(
					"*",
					"FROM" => "?",
					"WHERE" => "user_id = ?",
				), $sso_db_apikeys, $sso_user_id);

				while ($row = $result->NextRow())
				{
					$info = unserialize($row->info);
					$rows[] = array($row->id, htmlspecialchars($row->namespace), htmlspecialchars($info["purpose"]), ($row->user_id !== "0" ? "<a href=\"" . BB_GetRequestURLBase() . "?action=edituser&id=" . $row->user_id . "&sec_t=" . BB_CreateSecurityToken("edituser") . "\">" . $row->user_id . "</a>" : 0), ($info["url"] == "" ? "" : "<a href=\"" . htmlspecialchars($info["url"]) . "\" target=\"_blank\">" . htmlspecialchars(BB_Translate("Live")) . "</a> | ") . "<a href=\"" . BB_GetRequestURLBase() . "?action=editapikey&id=" . $row->id . "&sec_t=" . BB_CreateSecurityToken("editapikey") . "\">" . htmlspecialchars(BB_Translate("Edit")) . "</a> | <a href=\"" . BB_GetRequestURLBase() . "?action=deleteapikey&id=" . $row->id . "&sec_t=" . BB_CreateSecurityToken("deleteapikey") . "\" onclick=\"return confirm('" . htmlspecialchars(BB_JSSafe(BB_Translate("Deleting an API key will affect any clients using the key.  Are you sure you want to delete the API key '%s'?", $row->apikey . "-" . $row->id))) . "');\">" . htmlspecialchars(BB_Translate("Delete")) . "</a>");
				}
			}

			$result = $sso_db->Query("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "user_id = 0",
			), $sso_db_apikeys);
		}
		while ($row = $result->NextRow())
		{
			$info = unserialize($row->info);
			$rows[] = array($row->id, htmlspecialchars($row->namespace), htmlspecialchars($info["purpose"]), ($row->user_id !== "0" ? "<a href=\"" . BB_GetRequestURLBase() . "?action=edituser&id=" . $row->user_id . "&sec_t=" . BB_CreateSecurityToken("edituser") . "\">" . $row->user_id . "</a>" : 0), ($info["url"] == "" ? "" : "<a href=\"" . htmlspecialchars($info["url"]) . "\" target=\"_blank\">" . htmlspecialchars(BB_Translate("Live")) . "</a> | ") . "<a href=\"" . BB_GetRequestURLBase() . "?action=editapikey&id=" . $row->id . "&sec_t=" . BB_CreateSecurityToken("editapikey") . "\">" . htmlspecialchars(BB_Translate("Edit")) . "</a> | <a href=\"" . BB_GetRequestURLBase() . "?action=deleteapikey&id=" . $row->id . "&sec_t=" . BB_CreateSecurityToken("deleteapikey") . "\" onclick=\"return confirm('" . htmlspecialchars(BB_JSSafe(BB_Translate("Deleting an API key will affect any clients using the key.  Are you sure you want to delete the API key '%s'?", $row->apikey . "-" . $row->id))) . "');\">" . htmlspecialchars(BB_Translate("Delete")) . "</a>");
		}

		$contentopts = array(
			"desc" => "Manage API keys.  An API key is used to provide SSO client access to the SSO server.",
			"htmldesc" => $desc,
			"fields" => array(
				array(
					"type" => "table",
					"cols" => array("ID", "Namespace", "Purpose", "User ID", "Options"),
					"rows" => $rows
				)
			)
		);

		BB_GeneratePage("Manage API Keys", $sso_menuopts, $contentopts);
	}
	else if ($sso_site_admin && isset($_REQUEST["action"]) && $_REQUEST["action"] == "configure")
	{
		if (isset($_REQUEST["timezone"]))
		{
			if (!@date_default_timezone_set($_REQUEST["timezone"]))  BB_SetPageMessage("error", "Invalid timezone specified.");
			foreach ($sso_providers as $provider => &$instance)
			{
				if (isset($_REQUEST["order_" . $provider]) && (int)$_REQUEST["order_" . $provider] < 0)  BB_SetPageMessage("error", BB_Translate("The '%s' field contains an invalid value.", $instance->DisplayName()));
			}
			if ((int)$_REQUEST["clock_drift"] < 0)  BB_SetPageMessage("error", "Invalid clock drift specified.");
			$sso_settings[""]["iprestrict"] = SSO_ProcessIPFields(true);

			if (BB_GetPageMessageType() != "error")
			{
				$sso_settings[""]["timezone"] = $_REQUEST["timezone"];

				$sso_settings[""]["clock_drift"] = (int)$_REQUEST["clock_drift"];
				$sso_settings[""]["no_providers_msg"] = $_REQUEST["no_providers_msg"];
				$sso_settings[""]["expose_namespaces"] = (int)$_REQUEST["expose_namespaces"];
				$sso_settings[""]["hide_index"] = (int)$_REQUEST["hide_index"];
				$sso_settings[""]["first_activated_map"] = (SSO_IsField($_REQUEST["first_activated_map"]) ? $_REQUEST["first_activated_map"] : "");
				$sso_settings[""]["created_map"] = (SSO_IsField($_REQUEST["created_map"]) ? $_REQUEST["created_map"] : "");

				if ((int)$_REQUEST["reset_namespace"])  SSO_GenerateNamespaceKeys();

				$sso_settings[""]["search_order"] = array();
				for ($x = 0; isset($_REQUEST["search_order"][$x]); $x++)
				{
					$key = $_REQUEST["search_order"][$x];
					if ($key === "id" || $key === "provider_name" || $key === "provider_id" || $key === "version" || $key === "lastipaddr" || $key === "lastactivated" || $key === "tag_id" || (substr($key, 0, 6) === "field_" && isset($sso_select_fields[substr($key, 6)])))
					{
						$y = (int)$_REQUEST["search_display"][$x];
						$sso_settings[""]["search_order"][$key] = (isset($_REQUEST["search_display_" . $y]) && $_REQUEST["search_display_" . $y] === "yes");
					}
				}

				SSO_SaveSettings();

				BB_RedirectPage("success", "Successfully updated the settings.");
			}
		}

		$timezones = timezone_identifiers_list();
		$timezones2 = array();
		foreach ($timezones as $timezone)
		{
			$timezones2[$timezone] = $timezone;
		}

		SSO_LoadFieldSearchOrder();

		$searchrows = array();
		foreach ($sso_settings[""]["search_order"] as $key => $display)
		{
			$desc = false;

			if ($key === "id")  $desc = "Account ID";
			else if ($key === "provider_name")  $desc = "Provider Name";
			else if ($key === "provider_id")  $desc = "Provider ID";
			else if ($key === "version")  $desc = "Account Version";
			else if ($key === "lastipaddr")  $desc = "Last IP Address";
			else if ($key === "lastactivated")  $desc = "Last Activated";
			else if ($key === "tag_id")  $desc = "Exact Tag Match";
			else if (substr($key, 0, 6) === "field_")
			{
				if (isset($sso_select_fields[substr($key, 6)]))  $desc = $sso_select_fields[substr($key, 6)];
			}

			if ($desc !== false)  $searchrows[] = array("<input type=\"hidden\" name=\"search_order[]\" value=\"" . htmlspecialchars($key) . "\" /><input type=\"hidden\" name=\"search_display[]\" value=\"" . count($searchrows) . "\" /><input type=\"checkbox\" id=\"search_display_" . count($searchrows) . "\" name=\"search_display_" . count($searchrows) . "\" value=\"yes\"" . ($display ? " checked" : "") . " /> <label for=\"search_display_" . count($searchrows) . "\">" . htmlspecialchars($desc) . "</label>");
		}

		$contentopts = array(
			"desc" => "Configure the SSO Server.  These options affect all providers.",
			"nonce" => "action",
			"hidden" => array(
				"action" => "configure"
			),
			"fields" => array(
				array(
					"title" => "Display Timezone",
					"type" => "select",
					"name" => "timezone",
					"options" => $timezones2,
					"select" => BB_GetValue("timezone", $sso_settings[""]["timezone"]),
					"desc" => BB_Translate("The timezone to use for displaying dates and times throughout SSO Server Admin.  Current time is:  %s", BB_FormatTimestamp("M j, Y @ g:i A (e)", time()))
				),
				array(
					"title" => "Clock Drift",
					"type" => "text",
					"name" => "clock_drift",
					"value" => BB_GetValue("clock_drift", (isset($sso_settings[""]["clock_drift"]) ? $sso_settings[""]["clock_drift"] : 300)),
					"desc" => "The number of seconds to allow for server-side clock drift.  The default is 5 minutes (300 seconds), which results in 10 minutes of clock drift and therefore sessions last a minimum of 10 minutes."
				),
				array(
					"title" => "No Providers Message",
					"type" => "textarea",
					"height" => "200px",
					"name" => "no_providers_msg",
					"value" => BB_GetValue("no_providers_msg", (isset($sso_settings[""]["no_providers_msg"]) ? $sso_settings[""]["no_providers_msg"] : "")),
					"desc" => "The message to display to users in addition to the default message shown when they are presented with no providers to select from.  For example, arriving from a known spammer IP.  HTML is supported and the special token @BLOCKDETAILS@ will be replaced with details on why the user is blocked."
				),
				array(
					"title" => "Find User Search Results Display",
					"type" => "table",
					"order" => "Order",
					"cols" => array("Field"),
					"rows" => $searchrows,
					"desc" => "Drag and drop the order of the fields for Find User search results.  Check the boxes next to the fields that will always appear in Find User search results."
				),
				array(
					"title" => "Expose Namespace Cookie To All Subdomains?",
					"type" => "select",
					"name" => "expose_namespaces",
					"options" => array("No", "Yes"),
					"select" => BB_GetValue("expose_namespaces", (string)(int)(isset($sso_settings[""]["expose_namespaces"]) ? $sso_settings[""]["expose_namespaces"] : "0")),
					"desc" => "Enabling this will allow SSO clients on the same domain to detect whether or not the user is already signed in by creating a second namespace cookie with reduced information.  This option reduces the security of the system."
				),
				array(
					"title" => "Reset Namespace Key and IV?",
					"type" => "select",
					"name" => "reset_namespace",
					"options" => array("No", "Yes"),
					"select" => BB_GetValue("reset_namespace", "0")
				),
				array(
					"title" => "Remove 'index.php' From Frontend URL?",
					"type" => "select",
					"name" => "hide_index",
					"options" => array("No", "Yes"),
					"select" => BB_GetValue("hide_index", (string)(int)(isset($sso_settings[""]["hide_index"]) ? $sso_settings[""]["hide_index"] : "0")),
					"desc" => "This will adjust the URL that the user sees for the SSO server frontend.  The web server must support finding 'index.php' when only a directory is referenced (e.g. the Apache web server's DirectoryIndex configuration directive)."
				),
				array(
					"title" => "Map 'first_activated'",
					"type" => "select",
					"name" => "first_activated_map",
					"options" => $sso_select_fields,
					"select" => BB_GetValue("first_activated_map", (string)(isset($sso_settings[""]["first_activated_map"]) ? $sso_settings[""]["first_activated_map"] : "")),
					"desc" => "The field in the SSO system to map the first activation date/time to."
				),
				array(
					"title" => "Map 'created'",
					"type" => "select",
					"name" => "created_map",
					"options" => $sso_select_fields,
					"select" => BB_GetValue("created_map", (string)(isset($sso_settings[""]["created_map"]) ? $sso_settings[""]["created_map"] : "")),
					"desc" => "The field in the SSO system to map the account created date/time to.  This value is supplied by each Provider and may be significantly earlier than 'first_activated'."
				),
			),
			"submit" => "Save",
			"focus" => true
		);

		$contentopts["fields"][] = "split";
		foreach ($sso_providers as $provider => &$instance)
		{
			$contentopts["fields"][] = array(
				"title" => BB_Translate("%s Display Order", $instance->DisplayName()),
				"type" => "text",
				"name" => "order_" . $provider,
				"value" => BB_GetValue("order_" . $provider, (isset($sso_settings[""]["order"][$provider]) ? $sso_settings[""]["order"][$provider] : $instance->DefaultOrder())),
				"desc" => BB_Translate("The display order to use for the %s provider.", $instance->DisplayName())
			);
		}

		SSO_AppendIPFields($contentopts, $sso_settings[""]["iprestrict"], true);

		BB_GeneratePage("Configure SSO Server", $sso_menuopts, $contentopts);
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "viewipaddr")
	{
		$row = $sso_db->GetRow("SELECT", array(
			"*",
			"FROM" => "?",
			"WHERE" => "id = ?",
		), $sso_db_ipcache, $_REQUEST["id"]);

		if ($row === false)  BB_RedirectPage("error", "IP address does not exist.");

		$ipaddr = IPAddr::NormalizeIP($row->ipaddr);
		$info = unserialize($row->info);

		$contentopts = array(
			"desc" => BB_Translate("Viewing detailed cache information for IP address '%s'.", ($ipaddr["ipv4"] != "" ? $ipaddr["ipv4"] : $ipaddr["shortipv6"])),
			"fields" => array(
				array(
					"title" => "Created",
					"type" => "static",
					"value" => BB_FormatTimestamp("M j, Y @ g:i A", CSDB::ConvertFromDBTime($row->created))
				),
			)
		);

		if (!isset($info["spaminfo"]))
		{
			$contentopts["fields"][] = array(
				"title" => "Cached Information",
				"type" => "custom",
				"value" => "<i>" . htmlspecialchars(BB_Translate("Undefined (No cache found)")) . "</i>"
			);
		}
		else
		{
			if (isset($info["spaminfo_cache"]))
			{
				if (isset($info["spaminfo_cache"]["dnsrbl"]) && count($info["spaminfo_cache"]["dnsrbl"]))
				{
					$rows = array();
					foreach ($info["spaminfo_cache"]["dnsrbl"] as $domain => $mapips)
					{
						$rows[] = array(htmlspecialchars($domain), ($mapips !== false && is_array($mapips) ? htmlspecialchars(implode(", ", $mapips)) : "<i>" . htmlspecialchars(BB_Translate("None")) . "</i>"));
					}

					$contentopts["fields"][] = array(
						"title" => "DNSRBL Information",
						"type" => "table",
						"cols" => array("DNS Server", "Response"),
						"rows" => $rows,
						"desc" => "Each DNS server query and its response for this IP address."
					);
				}
				else
				{
					$contentopts["fields"][] = array(
						"title" => "DNSRBL Information",
						"type" => "custom",
						"value" => "<i>" . htmlspecialchars(BB_Translate("None (No queries made)")) . "</i>"
					);
				}

				if (isset($info["spaminfo_cache"]["geoip"]) && $info["spaminfo_cache"]["geoip"] !== false)
				{
					$rows = array();
					foreach ($info["spaminfo_cache"]["geoip"] as $key => $val)
					{
						$rows[] = array(htmlspecialchars($key), htmlspecialchars($val));
					}

					$contentopts["fields"][] = array(
						"title" => "IP Geolocation Information",
						"type" => "table",
						"cols" => array("Field", "Data"),
						"rows" => $rows,
						"desc" => "Each geolocation field and its data for this IP address."
					);
				}
				else
				{
					$contentopts["fields"][] = array(
						"title" => "IP Geolocation Information",
						"type" => "custom",
						"value" => "<i>" . htmlspecialchars(BB_Translate("None (No geolocation database)")) . "</i>"
					);
				}
			}

			foreach ($sso_providers as $provider => &$instance)
			{
				$contentopts["fields"][] = "split";
				if (isset($info["spaminfo"][$provider]))
				{
					if ($info["spaminfo"][$provider]["spammer"])
					{
						$rows = array();
						foreach ($info["spaminfo"][$provider]["reasons"] as $num => $reason)
						{
							$rows[] = array($num + 1, htmlspecialchars($reason));
						}

						$contentopts["fields"][] = array(
							"title" => BB_Translate("%s - Spammer Information", $instance->DisplayName()),
							"type" => "table",
							"cols" => array("#", "Reason"),
							"rows" => $rows,
							"desc" => "The reason" . (count($info["spaminfo"][$provider]["reasons"]) == 1 ? "" : "s") . " why this IP address is declared a spammer IP."
						);
					}
					else
					{
						$contentopts["fields"][] = array(
							"title" => BB_Translate("%s - Spammer Information", $instance->DisplayName()),
							"type" => "custom",
							"value" => htmlspecialchars(BB_Translate("Not a spammer."))
						);
					}
				}
				else
				{
					$contentopts["fields"][] = array(
						"title" => BB_Translate("%s - Spammer Information", $instance->DisplayName()),
						"type" => "custom",
						"value" => "<i>" . htmlspecialchars(BB_Translate("Undefined (No information found)")) . "</i>"
					);
				}

				$instance->AddIPCacheInfo();
			}
		}

		BB_GeneratePage("View IP Address Cache", $sso_menuopts, $contentopts);
	}
	else if ($sso_site_admin && isset($_REQUEST["action"]) && $_REQUEST["action"] == "resetipcache")
	{
		$sso_db->Query("TRUNCATE TABLE", array($sso_db_ipcache));

		BB_RedirectPage("success", "Successfully reset the IP address cache.", array("action=manageipcache&sec_t=" . BB_CreateSecurityToken("manageipcache")));
	}
	else if ($sso_site_admin && isset($_REQUEST["action"]) && $_REQUEST["action"] == "manageipcache")
	{
		$desc = "<br />";
		$desc .= "<a href=\"" . BB_GetRequestURLBase() . "?action=resetipcache&sec_t=" . BB_CreateSecurityToken("resetipcache") . "\" onclick=\"return confirm('" . htmlspecialchars(BB_JSSafe(BB_Translate("Resetting will wipe the entire cache.  Are you sure you want to reset the IP address cache?"))) . "');\">" . htmlspecialchars(BB_Translate("Reset IP Address Cache")) . "</a>";

		$rows = array();
		if (isset($_REQUEST["ipaddr"]) && $_REQUEST["ipaddr"] != "")
		{
			$pattern = $_REQUEST["ipaddr"];

			if (strpos($pattern, "-") !== false || strpos($pattern, ",") !== false || strpos($pattern, "*") !== false)
			{
				$result = $sso_db->Query("SELECT", array(
					"*",
					"FROM" => "?",
				), $sso_db_ipcache);
			}
			else
			{
				$ipaddr = IPAddr::NormalizeIP($pattern);
				$pattern = $ipaddr["ipv6"];

				$result = $sso_db->Query("SELECT", array(
					"*",
					"FROM" => "?",
					"WHERE" => "ipaddr = ?",
				), $sso_db_ipcache, $pattern);
			}

			while ($row = $result->NextRow())
			{
				$ipaddr = IPAddr::NormalizeIP($row->ipaddr);
				if (IPAddr::IsMatch($pattern, $ipaddr))
				{
					$info = unserialize($row->info);
					$spammer = false;
					if (isset($info["spaminfo"]))
					{
						foreach ($sso_providers as $provider => &$instance)
						{
							if (isset($info["spaminfo"][$provider]) && $info["spaminfo"][$provider]["spammer"])  $spammer = true;
						}
					}

					$rows[] = array(htmlspecialchars($ipaddr["ipv4"] != "" ? $ipaddr["ipv4"] : $ipaddr["shortipv6"]), htmlspecialchars(BB_Translate($spammer ? "Yes" : "No")), BB_FormatTimestamp("M j, Y @ g:i A", CSDB::ConvertFromDBTime($row->created)), "<a href=\"" . BB_GetRequestURLBase() . "?action=viewipaddr&id=" . $row->id . "&sec_t=" . BB_CreateSecurityToken("viewipaddr") . "\">" . htmlspecialchars(BB_Translate("View")) . "</a>");
				}
			}

			$table = array(
				"title" => "Search Results",
				"type" => "table",
				"cols" => array("IP Address", "Spammer?", "Created", "Options"),
				"rows" => $rows,
				"desc" => BB_Translate("The search results for the IP address pattern '%s'.", $pattern)
			);
		}
		else
		{
			if (isset($_REQUEST["ipaddr"]) && $_REQUEST["ipaddr"] == "")  BB_SetPageMessage("error", "Please specify an IP address or pattern.");

			$ts = time();

			$result = $sso_db->Query("SELECT", array(
				"*",
				"FROM" => "?",
				"WHERE" => "created >= ?",
				"ORDER BY" => "created DESC"
			), $sso_db_ipcache, CSDB::ConvertToDBTime(time() - (2 * 24 * 60 * 60)));

			while ($row = $result->NextRow())
			{
				$ipaddr = IPAddr::NormalizeIP($row->ipaddr);

				$info = unserialize($row->info);
				$spammer = false;
				if (isset($info["spaminfo"]))
				{
					foreach ($sso_providers as $provider => &$instance)
					{
						if (isset($info["spaminfo"][$provider]) && $info["spaminfo"][$provider]["spammer"])  $spammer = true;
					}
				}

				$rows[] = array(htmlspecialchars($ipaddr["ipv4"] != "" ? $ipaddr["ipv4"] : $ipaddr["shortipv6"]), htmlspecialchars(BB_Translate($spammer ? "Yes" : "No")), BB_FormatTimestamp("M j, Y @ g:i A", CSDB::ConvertFromDBTime($row->created)), "<a href=\"" . BB_GetRequestURLBase() . "?action=viewipaddr&id=" . $row->id . "&sec_t=" . BB_CreateSecurityToken("viewipaddr") . "\">" . htmlspecialchars(BB_Translate("View")) . "</a>");
			}

			$table = array(
				"title" => "Last 48 Hours",
				"type" => "table",
				"cols" => array("IP Address", "Spammer?", "Created", "Options"),
				"rows" => $rows,
				"desc" => "New IP addresses in the last 48 hours."
			);
		}

		$contentopts = array(
			"desc" => "Manage the IP address cache.",
			"htmldesc" => $desc,
			"nonce" => "action",
			"hidden" => array(
				"action" => "manageipcache"
			),
			"fields" => array(
				$table,
				"split",
				array(
					"title" => "Find IP Address",
					"type" => "text",
					"name" => "ipaddr",
					"value" => BB_GetValue("ipaddr", ""),
					"desc" => "Enter an IP address or IP address pattern to search for.  (e.g. '10.0.0-15,17.*')"
				)
			),
			"submit" => "Search",
			"focus" => false
		);

		BB_GeneratePage("Manage IP Cache", $sso_menuopts, $contentopts);
	}
	else if ($sso_site_admin && isset($_REQUEST["action"]) && $_REQUEST["action"] == "resetsessions")
	{
		$sso_db->Query("TRUNCATE TABLE", array($sso_db_user_sessions));
		$sso_db->Query("TRUNCATE TABLE", array($sso_db_temp_sessions));

		BB_RedirectPage("success", "Successfully reset all sessions.");
	}
	else
	{
		$contentopts = array(
			"desc" => "Pick an option from the menu."
		);

		BB_GeneratePage("Home", $sso_menuopts, $contentopts);
	}
?>