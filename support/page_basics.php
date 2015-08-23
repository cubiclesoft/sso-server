<?php
	// Admin Pack server-side page manipulation functions.
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	// Code swiped from Barebones CMS support functions.
	function BB_JSSafe($data)
	{
		return str_replace(array("'", "\r", "\n"), array("\\'", "\\r", "\\n"), $data);
	}

	function BB_IsSSLRequest()
	{
		return ((isset($_SERVER["HTTPS"]) && ($_SERVER["HTTPS"] == "on" || $_SERVER["HTTPS"] == "1")) || (isset($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] == "443") || (str_replace("\\", "/", strtolower(substr($_SERVER["REQUEST_URI"], 0, 8))) == "https://"));
	}

	// Returns 'http[s]://www.something.com[:port]' based on the current page request.
	function BB_GetRequestHost($protocol = "")
	{
		global $bb_getrequesthost_cache;

		$protocol = strtolower($protocol);
		$ssl = ($protocol == "https" || ($protocol == "" && BB_IsSSLRequest()));
		if ($protocol == "")  $type = "def";
		else if ($ssl)  $type = "https";
		else  $type = "http";

		if (!isset($bb_getrequesthost_cache))  $bb_getrequesthost_cache = array();
		if (isset($bb_getrequesthost_cache[$type]))  return $bb_getrequesthost_cache[$type];

		$url = "http" . ($ssl ? "s" : "") . "://";
		if ($ssl && defined("HTTPS_SERVER") && HTTPS_SERVER != "")  $url .= HTTPS_SERVER;
		else if (!$ssl && defined("HTTP_SERVER") && HTTP_SERVER != "")  $url .= HTTP_SERVER;
		else
		{
			$str = str_replace("\\", "/", $_SERVER["REQUEST_URI"]);
			$pos = strpos($str, "?");
			if ($pos !== false)  $str = substr($str, 0, $pos);
			$str2 = strtolower($str);
			if (substr($str2, 0, 7) == "http://")
			{
				$pos = strpos($str, "/", 7);
				if ($pos === false)  $str = "";
				else  $str = substr($str, 7, $pos);
			}
			else if (substr($str2, 0, 8) == "https://")
			{
				$pos = strpos($str, "/", 8);
				if ($pos === false)  $str = "";
				else  $str = substr($str, 8, $pos);
			}
			else  $str = "";

			if ($str != "")  $host = $str;
			else if (isset($_SERVER["HTTP_HOST"]))  $host = $_SERVER["HTTP_HOST"];
			else  $host = $_SERVER["SERVER_NAME"] . ":" . (int)$_SERVER["SERVER_PORT"];

			$pos = strpos($host, ":");
			if ($pos === false)  $port = 0;
			else
			{
				$port = (int)substr($host, $pos + 1);
				$host = substr($host, 0, $pos);
			}
			if ($port < 1 || $port > 65535)  $port = ($ssl ? 443 : 80);
			$url .= preg_replace('/[^a-z0-9.\-]/', "", strtolower($host));
			if ($protocol == "" && ((!$ssl && $port != 80) || ($ssl && $port != 443)))  $url .= ":" . $port;
			else if ($protocol == "http" && !$ssl && $port != 80)  $url .= ":" . $port;
			else if ($protocol == "https" && $ssl && $port != 443)  $url .= ":" . $port;
		}

		$bb_getrequesthost_cache[$type] = $url;

		return $url;
	}

	function BB_GetRequestURLBase()
	{
		$str = str_replace("\\", "/", $_SERVER["REQUEST_URI"]);
		$pos = strpos($str, "?");
		if ($pos !== false)  $str = substr($str, 0, $pos);
		$str2 = strtolower($str);
		if (substr($str2, 0, 7) == "http://" || substr($str2, 0, 8) == "https://")
		{
			$pos = strpos($str, "/", 8);
			if ($pos === false)  $str = "/";
			else  $str = substr($str, $pos);
		}

		return $str;
	}

	function BB_GetFullRequestURLBase($protocol = "")
	{
		return BB_GetRequestHost($protocol) . BB_GetRequestURLBase();
	}

	// Multilingual admin functions.
	function BB_Translate()
	{
		global $bb_admin_lang, $bb_admin_def_lang, $bb_langmap;

		$args = func_get_args();
		if (!count($args) || $args[0] == "")  return "";
		if (isset($bb_admin_lang) && isset($bb_admin_def_lang) && isset($bb_langmap))
		{
			$arg = $args[0];
			if (isset($bb_langmap[$bb_admin_lang]) && isset($bb_langmap[$bb_admin_lang][$arg]))  $args[0] = $bb_langmap[$bb_admin_lang][$arg];
			else if (isset($bb_langmap[$bb_admin_def_lang]) && isset($bb_langmap[$bb_admin_def_lang][$arg]))  $args[0] = $bb_langmap[$bb_admin_def_lang][$arg];
			else if (isset($bb_langmap[""][$arg]))  $args[0] = $bb_langmap[""][$arg];
			else if (function_exists("BB_Untranslated"))  BB_Untranslated($args);
		}
		if (count($args) == 1 && strpos($args[0], "%") !== false)  $args[0] = str_replace("%", "%%", $args[0]);

		return call_user_func_array("sprintf", $args);
	}

	function BB_PostTranslate($str)
	{
		global $bb_admin_lang, $bb_admin_def_lang, $bb_langmap;

		if (isset($bb_admin_lang) && isset($bb_admin_def_lang) && isset($bb_langmap))
		{
			if (isset($bb_langmap[$bb_admin_lang]) && isset($bb_langmap[$bb_admin_lang][""]) && is_array($bb_langmap[$bb_admin_lang][""]))  $str = str_replace($bb_langmap[$bb_admin_lang][""][0], $bb_langmap[$bb_admin_lang][""][1], $str);
			else if (isset($bb_langmap[$bb_admin_def_lang]) && isset($bb_langmap[$bb_admin_def_lang][""]) && is_array($bb_langmap[$bb_admin_def_lang][""]))  $str = str_replace($bb_langmap[$bb_admin_def_lang][""][0], $bb_langmap[$bb_admin_def_lang][""][1], $str);
			else if (isset($bb_langmap[""][""]) && is_array($bb_langmap[""][""]))  $str = str_replace($bb_langmap[""][""][0], $bb_langmap[""][""][1], $str);
		}

		return $str;
	}

	function BB_FormatTimestamp($format, $ts)
	{
		return BB_PostTranslate(date(BB_Translate($format), $ts));
	}

	function BB_SetLanguage($path, $lang)
	{
		global $bb_langmap, $bb_admin_lang;

		$lang = preg_replace('/\s+/', "_", trim(preg_replace('/[^a-z]/', " ", strtolower($lang))));
		if ($lang == "")
		{
			$path .= "default/";
		}
		else
		{
			if ($lang == "default")  return array("success" => false, "error" => "Invalid language.");
			$path .= $lang . "/";
		}

		if (isset($bb_langmap[$lang]))
		{
			if ($lang != "")  $bb_admin_lang = $lang;

			return array("success" => true);
		}
		$bb_langmap[$lang] = array();

		$dir = @opendir($path);
		if ($dir === false)  return array("success" => false, "error" => "Directory does not exist.", "info" => $path);

		while (($file = readdir($dir)) !== false)
		{
			if (strtolower(substr($file, -4)) == ".php")  require_once $path . $file;
		}

		closedir($dir);

		if (isset($bb_langmap[$lang][""]) && is_array($bb_langmap[$lang][""]))  $bb_langmap[$lang][""] = array(array_keys($bb_langmap[$lang][""]), array_values($bb_langmap[$lang][""]));

		$bb_admin_lang = $lang;

		return array("success" => true);
	}

	function BB_InitLangmap($path, $default = "")
	{
		global $bb_admin_lang, $bb_admin_def_lang, $bb_langmap;

		$bb_langmap = array();
		BB_SetLanguage($path, "");
		if ($default != "")  BB_SetLanguage($path, $default);
		$bb_admin_def_lang = $bb_admin_lang;
		if (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]))
		{
			$langs = explode(",", $_SERVER["HTTP_ACCEPT_LANGUAGE"]);
			foreach ($langs as $lang)
			{
				$lang = trim($lang);
				$pos = strpos($lang, ";");
				if ($pos !== false)  $lang = substr($lang, 0, $pos);
				if ($lang != "")
				{
					$result = BB_SetLanguage($path, $lang);
					if ($result["success"])  break;
				}
			}
		}
	}


	// Code swiped from CubicleSoft browser cookie support functions.
	function SetCookieFixDomain($name, $value = "", $expires = 0, $path = "", $domain = "", $secure = false, $httponly = false)
	{
		if (!empty($domain))
		{
			// Remove port information.
			$pos = strpos($domain, "]");
			if (substr($domain, 0, 1) == "[" && $pos !== false)  $domain = substr($domain, 0, $pos + 1);
			else
			{
				$port = strpos($domain, ":");
				if ($port !== false)  $domain = substr($domain, 0, $port);

				// Fix the domain to accept domains with and without 'www.'.
				if (strtolower(substr($domain, 0, 4)) == "www.")  $domain = substr($domain, 4);
				if (strpos($domain, ".") === false)  $domain = "";
				else if (substr($domain, 0, 1) != ".")  $domain = "." . $domain;
			}
		}

		header('Set-Cookie: ' . rawurlencode($name) . "=" . rawurlencode($value)
							. (empty($expires) ? "" : "; expires=" . gmdate("D, d-M-Y H:i:s", $expires) . " GMT")
							. (empty($path) ? "" : "; path=" . $path)
							. (empty($domain) ? "" : "; domain=" . $domain)
							. (!$secure ? "" : "; secure")
							. (!$httponly ? "" : "; HttpOnly"), false);
	}


	function BB_OutputJQueryUI($rooturl, $supportpath)
	{
?>
	<link rel="stylesheet" href="<?php echo htmlspecialchars($rooturl . "/" . $supportpath . "/jquery_ui_themes/smoothness/jquery-ui-1.10.4.css"); ?>" type="text/css" media="all" />
	<script type="text/javascript" src="<?php echo htmlspecialchars($rooturl . "/" . $supportpath . "/jquery-ui-1.10.4.min.js"); ?>"></script>
<?php
	}

	// Slightly modified code swiped from Barebone CMS editing routines.
	function BB_PropertyForm($options)
	{
		global $bb_formtables, $bb_formwidths;

		if (!isset($bb_formtables) || !is_bool($bb_formtables))  $bb_formtables = true;
		if (!isset($bb_formwidths) || !is_bool($bb_formwidths))  $bb_formwidths = true;

		$dateused = false;
		$accordionused = false;
		$multiselectused = array();
		$multiselectheight = 200;
		$tableorderused = false;
		$tablestickyheaderused = false;
		$autofocus = false;

		// Certain types of fields require the Admin Pack extras package.
		$jqueryuiused = false;
		if (defined("BB_ROOT_URL"))  $rooturl = BB_ROOT_URL;
		else if (defined("ROOT_URL"))  $rooturl = ROOT_URL;
		else
		{
			$rooturl = BB_GetRequestURLBase();
			if (substr($rooturl, -1) != "/")  $rooturl = dirname($rooturl);
			if (substr($rooturl, -1) == "/")  $rooturl = substr($rooturl, 0, -1);
		}

		if (defined("BB_SUPPORT_PATH"))  $supportpath = BB_SUPPORT_PATH;
		else if (defined("SUPPORT_PATH"))  $supportpath = SUPPORT_PATH;
		else  $supportpath = "support";

?>
	<noscript><style type="text/css">
		div.maincontent div.proptitle div.navbutton { display: none; }
		div.leftnav { display: block; }
	</style></noscript>
	<div class="proptitle"><div id="navbutton">Menu</div><div id="navdropdown"></div><?php echo htmlspecialchars(BB_Translate($options["title"])); ?></div>
	<div class="propdesc"><?php echo htmlspecialchars(BB_Translate($options["desc"])); ?><?php if (isset($options["htmldesc"]))  echo $options["htmldesc"]; ?></div>
	<div class="propinfo"></div>
	<div class="propmain">
<?php
		if (isset($options["submit"]) || (isset($options["useform"]) && $options["useform"]))
		{
?>
		<form id="propform" method="post" enctype="multipart/form-data" action="<?php echo htmlspecialchars(BB_GetRequestURLBase()); ?>">
<?php

			$extra = array();
			if (isset($options["hidden"]))
			{
				foreach ($options["hidden"] as $name => $value)
				{
?>
		<input type="hidden" name="<?php echo htmlspecialchars($name); ?>" value="<?php echo htmlspecialchars($value); ?>" />
<?php
					if ($options["nonce"] != $name)  $extra[$name] = $value;
				}

?>
		<input type="hidden" name="sec_extra" value="<?php echo htmlspecialchars(implode(",", array_keys($extra))); ?>" />
		<input type="hidden" name="sec_t" value="<?php echo htmlspecialchars(BB_CreateSecurityToken($options["hidden"][$options["nonce"]], $extra)); ?>" />
<?php
			}
			unset($extra);
		}

		if (isset($options["fields"]))
		{
?>
		<div class="formfields<?php if (count($options["fields"]) == 1 && !isset($options["fields"][0]["title"]) && !isset($options["fields"][0]["htmltitle"]))  echo " alt"; ?>">
<?php
			$insiderow = false;
			$insideaccordion = false;
			foreach ($options["fields"] as $num => $field)
			{
				if (is_string($field))
				{
					if ($field == "split" && !$insiderow)  echo "<hr />";
					else if ($field == "endaccordion" || $field == "endaccordian")
					{
						if ($insiderow)
						{
?>
			</tr></table></div>
<?php
							$insiderow = false;
						}
?>
				</div>
			</div>
<?php
						$insideaccordion = false;
					}
					else if ($field == "nosplit")
					{
						if ($insideaccordion)  $firstaccordionitem = true;
					}
					else if ($field == "startrow")
					{
						if ($insiderow)  echo "</tr><tr>";
						else if ($bb_formtables)
						{
							$insiderow = true;
?>
			<div class="fieldtablewrap<?php if ($insideaccordion && $firstaccordionitem)  echo " firstitem"; ?>"><table class="rowwrap"><tr>
<?php
							$firstaccordionitem = false;
						}
					}
					else if ($field == "endrow" && $bb_formtables)
					{
?>
			</tr></table></div>
<?php
						$insiderow = false;
					}
					else if (substr($field, 0, 5) == "html:")
					{
						echo substr($field, 5);
					}
				}
				else if ($field["type"] == "accordion" || $field["type"] == "accordian")
				{
					if ($insiderow)
					{
?>
			</tr></table></div>
<?php
						$insiderow = false;
					}

					if ($insideaccordion)
					{
?>
				</div>
				<h3><?php echo htmlspecialchars(BB_Translate($field["title"])); ?></h3>
				<div class="formaccordionitems">
<?php
					}
					else
					{
?>
			<div class="formaccordionwrap">
				<h3><?php echo htmlspecialchars(BB_Translate($field["title"])); ?></h3>
				<div class="formaccordionitems">
<?php
						$insideaccordion = true;
						$accordionused = true;
					}

					$firstaccordionitem = true;
				}
				else
				{
					if ($insiderow)  echo "<td>";
?>
			<div class="formitem<?php echo ((isset($field["split"]) && $field["split"] === false) || ($insideaccordion && $firstaccordionitem) ? " firstitem" : ""); ?>">
<?php
					$firstaccordionitem = false;
					if (isset($field["title"]))
					{
						if (is_string($field["title"]))
						{
?>
			<div class="formitemtitle"><?php echo htmlspecialchars(BB_Translate($field["title"])); ?></div>
<?php
						}
					}
					else if (isset($field["htmltitle"]))
					{
?>
			<div class="formitemtitle"><?php echo BB_Translate($field["htmltitle"]); ?></div>
<?php
					}
					else if ($field["type"] == "checkbox" && $insiderow)
					{
?>
			<div class="formitemtitle">&nbsp;</div>
<?php
					}

					if (isset($field["width"]) && !$bb_formwidths)  unset($field["width"]);

					if (isset($field["name"]) && isset($field["default"]))
					{
						if ($field["type"] == "select")
						{
							if (!isset($field["select"]))
							{
								$field["select"] = BB_GetValue($field["name"], $field["default"]);
								if (is_array($field["select"]))  $field["select"] = BB_SelectValues($field["select"]);
							}
						}
						else
						{
							if (!isset($field["value"]))  $field["value"] = BB_GetValue($field["name"], $field["default"]);
						}
					}

					switch ($field["type"])
					{
						case "static":
						{
?>
			<div class="static"<?php if (isset($field["width"]))  echo " style=\"width: " . htmlspecialchars($field["width"]) . ";\""; ?>><?php echo htmlspecialchars($field["value"]); ?></div>
<?php
							break;
						}
						case "text":
						{
							if ($autofocus === false)  $autofocus = htmlspecialchars("f" . $num . "_" . $field["name"]);
?>
			<input class="text"<?php if (isset($field["width"]))  echo " style=\"width: " . htmlspecialchars($field["width"]) . ";\""; ?> type="text" id="<?php echo htmlspecialchars("f" . $num . "_" . $field["name"]); ?>" name="<?php echo htmlspecialchars($field["name"]); ?>" value="<?php echo htmlspecialchars($field["value"]); ?>" />
<?php
							break;
						}
						case "password":
						{
							if ($autofocus === false)  $autofocus = htmlspecialchars("f" . $num . "_" . $field["name"]);
?>
			<input class="text"<?php if (isset($field["width"]))  echo " style=\"width: " . htmlspecialchars($field["width"]) . ";\""; ?> type="password" id="<?php echo htmlspecialchars("f" . $num . "_" . $field["name"]); ?>" name="<?php echo htmlspecialchars($field["name"]); ?>" value="<?php echo htmlspecialchars($field["value"]); ?>" />
<?php
							break;
						}
						case "checkbox":
						{
							if ($autofocus === false)  $autofocus = htmlspecialchars("f" . $num . "_" . $field["name"]);
?>
			<input class="checkbox" type="checkbox" id="<?php echo htmlspecialchars("f" . $num . "_" . $field["name"]); ?>" name="<?php echo htmlspecialchars($field["name"]); ?>" value="<?php echo htmlspecialchars($field["value"]); ?>"<?php if (isset($field["check"]) && $field["check"])  echo " checked"; ?> />
			<label for="<?php echo htmlspecialchars("f" . $num . "_" . $field["name"]); ?>"><?php echo htmlspecialchars(BB_Translate($field["display"])); ?></label>
<?php
							break;
						}
						case "select":
						{
							if ($autofocus === false)  $autofocus = htmlspecialchars("f" . $num . "_" . $field["name"]);

							if (!isset($field["multiple"]) || $field["multiple"] !== true)  $mode = "select";
							else if (!isset($field["mode"]) || ($field["mode"] != "flat" && $field["mode"] != "dropdown" && $field["mode"] != "tags" && $field["mode"] != "select"))  $mode = "checkbox";
							else  $mode = $field["mode"];

							if (!isset($field["width"]) && !isset($field["height"]))  $style = "";
							else
							{
								$style = array();
								if (isset($field["width"]))  $style[] = "width: " . htmlspecialchars($field["width"]);
								if (isset($field["height"]) && isset($field["multiple"]) && $field["multiple"] === true)
								{
									$style[] = "height: " . htmlspecialchars($field["height"]);
									$multiselectheight = (int)$field["height"];
								}
								$style = " style=\"" . implode("; ", $style) . ";\"";
							}

							if (!isset($field["select"]))  $field["select"] = array();
							else if (is_string($field["select"]))  $field["select"] = array($field["select"] => true);

							$idbase = htmlspecialchars("f" . $num . "_" . $field["name"]);
							if ($mode == "checkbox")
							{
								$idnum = 0;
								foreach ($field["options"] as $name => $value)
								{
									if (is_array($value))
									{
										foreach ($value as $name2 => $value2)
										{
											$id = $idbase . ($idnum ? "_" . $idnum : "");
?>
			<input class="checkbox" type="checkbox" id="<?php echo $id; ?>" name="<?php echo htmlspecialchars($field["name"]); ?>[]" value="<?php echo htmlspecialchars($name2); ?>"<?php if (isset($field["select"][$name2]))  echo " checked"; ?> />
			<label for="<?php echo $id; ?>"><?php echo htmlspecialchars(BB_Translate($name)); ?> - <?php echo ($value2 == "" ? "&nbsp;" : htmlspecialchars(BB_Translate($value2))); ?></label><br />
<?php
											$idnum++;
										}
									}
									else
									{
										$id = $idbase . ($idnum ? "_" . $idnum : "");
?>
			<input class="checkbox" type="checkbox" id="<?php echo $id; ?>" name="<?php echo htmlspecialchars($field["name"]); ?>[]" value="<?php echo htmlspecialchars($name); ?>"<?php if (isset($field["select"][$name]))  echo " checked"; ?> />
			<label for="<?php echo $id; ?>"><?php echo ($value == "" ? "&nbsp;" : htmlspecialchars(BB_Translate($value))); ?></label><br />
<?php
										$idnum++;
									}
								}
							}
							else
							{
?>
			<select class="<?php echo (isset($field["multiple"]) && $field["multiple"] === true ? "multi" : "single"); ?>" id="<?php echo $idbase; ?>" name="<?php echo htmlspecialchars($field["name"]) . (isset($field["multiple"]) && $field["multiple"] === true ? "[]" : ""); ?>"<?php if (isset($field["multiple"]) && $field["multiple"] === true)  echo " multiple"; ?><?php echo $style; ?>>
<?php
								foreach ($field["options"] as $name => $value)
								{
									if (is_array($value))
									{
?>
				<optgroup label="<?php echo htmlspecialchars(BB_Translate($name)); ?>">
<?php
										foreach ($value as $name2 => $value2)
										{
?>
					<option value="<?php echo htmlspecialchars($name2); ?>"<?php if (isset($field["select"][$name2]))  echo " selected"; ?>><?php echo ($value2 == "" ? "&nbsp;" : htmlspecialchars(BB_Translate($value2))); ?></option>
<?php
										}
?>
				</optgroup>
<?php
									}
									else
									{
?>
				<option value="<?php echo htmlspecialchars($name); ?>"<?php if (isset($field["select"][$name]))  echo " selected"; ?>><?php echo ($value == "" ? "&nbsp;" : htmlspecialchars(BB_Translate($value))); ?></option>
<?php
									}
								}
?>
			</select>
<?php
								if (isset($field["multiple"]) && $field["multiple"] === true)
								{
									if (!$jqueryuiused)
									{
										BB_OutputJQueryUI($rooturl, $supportpath);
										$jqueryuiused = true;
									}

									if ($mode == "tags")
									{
										if (!isset($multiselectused[$mode]))
										{
?>
	<link rel="stylesheet" href="<?php echo htmlspecialchars($rooturl . "/" . $supportpath . "/multiselect-select2/select2.css"); ?>" type="text/css" media="all" />
	<script type="text/javascript" src="<?php echo htmlspecialchars($rooturl . "/" . $supportpath . "/multiselect-select2/select2.min.js"); ?>"></script>
<?php
										}
?>
	<script type="text/javascript">
	$(function() {
		if (jQuery.fn.select2)  $('div.formfields div.formitem select.multi[name="<?php echo BB_JSSafe($field["name"] . "[]"); ?>"]').select2({ <?php if (isset($field["mininput"]))  echo "minimumInputLength: " . (int)$field["mininput"]; ?> });
		else  alert('<?php echo BB_JSSafe(BB_Translate("Warning:  Missing jQuery UI select2 for multiple selection field.\n\This feature requires AdminPack Extras.")); ?>');
	});
	</script>
<?php
									}
									else if ($mode == "dropdown")
									{
										if (!isset($multiselectused[$mode]))
										{
?>
	<link rel="stylesheet" href="<?php echo htmlspecialchars($rooturl . "/" . $supportpath . "/multiselect-widget/jquery.multiselect.css"); ?>" type="text/css" media="all" />
	<link rel="stylesheet" href="<?php echo htmlspecialchars($rooturl . "/" . $supportpath . "/multiselect-widget/jquery.multiselect.filter.css"); ?>" type="text/css" media="all" />
	<script type="text/javascript" src="<?php echo htmlspecialchars($rooturl . "/" . $supportpath . "/multiselect-widget/jquery.multiselect.min.js"); ?>"></script>
	<script type="text/javascript" src="<?php echo htmlspecialchars($rooturl . "/" . $supportpath . "/multiselect-widget/jquery.multiselect.filter.js"); ?>"></script>
<?php
										}
?>
	<script type="text/javascript">
	$(function() {
		if (jQuery.fn.multiselect && jQuery.fn.multiselectfilter)  $('div.formfields div.formitem select.multi[name="<?php echo BB_JSSafe($field["name"] . "[]"); ?>"]').multiselect({ selectedText: '<?php echo BB_JSSafe(BB_Translate("# of # selected")); ?>', selectedList: 5, height: <?php echo $multiselectheight; ?>, position: { my: 'left top', at: 'left bottom', collision: 'flip' } }).multiselectfilter();
		else  alert('<?php echo BB_JSSafe(BB_Translate("Warning:  Missing jQuery UI multiselect widget or multiselectfilter for dropdown multiple selection field.\n\This feature requires AdminPack Extras.")); ?>');
	});
	</script>
<?php
									}
									else if ($mode == "flat")
									{
										if (!isset($multiselectused[$mode]))
										{
?>
	<link rel="stylesheet" href="<?php echo htmlspecialchars($rooturl . "/" . $supportpath . "/multiselect-flat/css/jquery.uix.multiselect.css"); ?>" type="text/css" media="all" />
	<script type="text/javascript" src="<?php echo htmlspecialchars($rooturl . "/" . $supportpath . "/multiselect-flat/js/jquery.uix.multiselect.js"); ?>"></script>
<?php
										}
?>
	<script type="text/javascript">
	$(function() {
		if (jQuery.fn.multiselect)
		{
			$('div.formfields div.formitem select.multi[name="<?php echo BB_JSSafe($field["name"] . "[]"); ?>"]').multiselect({ availableListPosition: <?php echo ($bb_formtables ? "'left'" : "'top'"); ?>, sortable: true, sortMethod: null });
			$(window).resize(function() {
				$('div.formfields div.formitem select.multi[name="<?php echo BB_JSSafe($field["name"] . "[]"); ?>"]').multiselect('refresh');
			});
		}
		else
		{
			alert('<?php echo BB_JSSafe(BB_Translate("Warning:  Missing jQuery UI multiselect plugin for flat multiple selection field.\n\This feature requires AdminPack Extras.")); ?>');
		}
	});
	</script>
	<div style="clear: both;"></div>
<?php
									}

									$multiselectused[$mode] = true;
								}
							}

							break;
						}
						case "textarea":
						{
							if ($autofocus === false)  $autofocus = htmlspecialchars("f" . $num . "_" . $field["name"]);
							if (!isset($field["width"]) && !isset($field["height"]))  $style = "";
							else
							{
								$style = array();
								if (isset($field["width"]))  $style[] = "width: " . htmlspecialchars($field["width"]);
								if (isset($field["height"]))  $style[] = "height: " . htmlspecialchars($field["height"]);
								$style = " style=\"" . implode("; ", $style) . ";\"";
							}
?>
			<div class="textareawrap"><textarea class="text"<?php echo $style; ?> id="<?php echo htmlspecialchars("f" . $num . "_" . $field["name"]); ?>" name="<?php echo htmlspecialchars($field["name"]); ?>" rows="5" cols="50"><?php echo htmlspecialchars($field["value"]); ?></textarea></div>
<?php
							break;
						}
						case "table":
						{
							$order = (isset($field["order"]) ? $field["order"] : "");
							$idbase = "f" . $num . "_" . (isset($field["name"]) ? $field["name"] : "table");

							if ($bb_formtables)
							{
?>
			<table id="<?php echo htmlspecialchars($idbase); ?>"<?php if (isset($field["class"]))  echo " class=\"" . htmlspecialchars($field["class"]) . "\""; ?><?php if (isset($field["width"]))  echo " style=\"width: " . htmlspecialchars($field["width"]) . "\""; ?>>
				<thead>
				<tr<?php if ($order != "")  echo " id=\"" . htmlspecialchars($idbase . "_head") . "\""; ?> class="head<?php if ($order != "")  echo " nodrag nodrop"; ?>">
<?php
								if ($order != "")
								{
?>
					<th><?php echo htmlspecialchars(BB_Translate($order)); ?></th>
<?php
								}

								foreach ($field["cols"] as $num2 => $col)
								{
?>
					<th><?php echo htmlspecialchars(BB_Translate($col)); ?></th>
<?php
								}
?>
				</tr>
				</thead>
				<tbody>
<?php
								$rownum = 0;
								$altrow = false;
								if (isset($field["callback"]) && is_callable($field["callback"]))  $field["rows"] = call_user_func($field["callback"]);
								while (count($field["rows"]))
								{
									foreach ($field["rows"] as $row)
									{
?>
				<tr<?php if ($order != "")  echo " id=\"" . htmlspecialchars($idbase . "_" . $rownum) . "\""; ?> class="row<?php if ($altrow)  echo " altrow"; ?>">
<?php
										if ($order != "")
										{
?>
					<td class="draghandle">&nbsp;</td>
<?php
										}

										$num2 = 0;
										foreach ($row as $col)
										{
?>
					<td<?php if (count($row) < count($field["cols"]) && $num2 + 1 == count($row))  echo " colspan=\"" . (count($field["cols"]) - count($row) + 1) . "\""; ?>><?php echo $col; ?></td>
<?php
											$num2++;
										}
?>
				</tr>
<?php
										$rownum++;
										$altrow = !$altrow;
									}

									if (isset($field["callback"]) && is_callable($field["callback"]))  $field["rows"] = call_user_func($field["callback"]);
									else  $field["rows"] = array();
								}
?>
				</tbody>
			</table>
<?php

								if ($order != "")
								{
									if (!$tableorderused)
									{
?>
			<script type="text/javascript" src="<?php echo htmlspecialchars($rooturl . "/" . $supportpath . "/jquery.tablednd-20140418.min.js"); ?>"></script>
<?php
										$tableorderused = true;
									}
?>
			<script type="text/javascript">
			if (jQuery.fn.tableDnD)
			{
				InitPropertiesTableDragAndDrop('<?php echo BB_JSSafe($idbase); ?>'<?php if (isset($field["reordercallback"]))  echo ", " . $field["reordercallback"]; ?>);
			}
			else
			{
				alert('<?php echo BB_JSSafe(BB_Translate("Warning:  Missing jQuery TableDnD plugin for drag-and-drop row ordering.\n\This feature requires AdminPack Extras.")); ?>');
			}
			</script>
<?php
								}

								if (isset($field["stickyheader"]) && $field["stickyheader"])
								{
									if (!$tablestickyheaderused)
									{
?>
			<script type="text/javascript" src="<?php echo htmlspecialchars($rooturl . "/" . $supportpath . "/jquery.stickytableheaders.min.js"); ?>"></script>
<?php
										$tablestickyheaderused = true;
									}
?>
			<script type="text/javascript">
			if (jQuery.fn.stickyTableHeaders)
			{
				$('#<?php echo BB_JSSafe($idbase); ?>').stickyTableHeaders();
			}
			else
			{
				alert('<?php echo BB_JSSafe(BB_Translate("Warning:  Missing jQuery Sticky Table Headers plugin.\n\This feature requires AdminPack Extras.")); ?>');
			}
			</script>
<?php
								}
							}
							else
							{
?>
			<div class="nontablewrap" id="<?php echo htmlspecialchars("f" . $num . "_" . (isset($field["name"]) ? $field["name"] : "table")); ?>">
<?php
								$altrow = false;
								foreach ($field["rows"] as $num2 => $row)
								{
?>
				<div class="nontable_row<?php if ($altrow)  echo " altrow"; ?><?php if (!$num2)  echo " firstrow"; ?>">
<?php
									foreach ($row as $num3 => $col)
									{
?>
					<div class="nontable_th<?php if (!$num3)  echo " firstcol"; ?>"><?php echo htmlspecialchars(BB_Translate($field["cols"][$num3])); ?></div>
					<div class="nontable_td"><?php echo $col; ?></div>
<?php
									}
?>
				</div>
<?php
									$altrow = !$altrow;
								}
?>
			</div>
<?php
							}

							break;
						}
						case "file":
						{
							if ($autofocus === false)  $autofocus = htmlspecialchars("f" . $num . "_" . $field["name"]);
?>
			<input class="text" type="file" id="<?php echo htmlspecialchars("f" . $num . "_" . $field["name"]); ?>" name="<?php echo htmlspecialchars($field["name"]); ?>" />
<?php
							break;
						}
						case "date":
						{
							if ($autofocus === false)  $autofocus = htmlspecialchars("f" . $num . "_" . $field["name"]);
?>
			<input class="date"<?php if (isset($field["width"]))  echo " style=\"width: " . htmlspecialchars($field["width"]) . "\""; ?> type="text" id="<?php echo htmlspecialchars("f" . $num . "_" . $field["name"]); ?>" name="<?php echo htmlspecialchars($field["name"]); ?>" value="<?php echo htmlspecialchars($field["value"]); ?>" />
<?php
							$dateused = true;

							break;
						}
						case "custom":
						{
							echo $field["value"];
							break;
						}
					}

					if (isset($field["desc"]) && $field["desc"] != "")
					{
?>
			<div class="formitemdesc"><?php echo htmlspecialchars(BB_Translate($field["desc"])); ?></div>
<?php
					}
					else if (isset($field["htmldesc"]) && $field["htmldesc"] != "")
					{
?>
			<div class="formitemdesc"><?php echo $field["htmldesc"]; ?></div>
<?php
					}
?>
			</div>
<?php
					if ($insiderow)  echo "</td>";
				}
			}

			if ($insiderow)
			{
?>
			</tr></table></div>
<?php
			}

			if ($insideaccordion)
			{
?>
				</div>
			</div>
<?php
			}
?>
		</div>
<?php
		}

		if (isset($options["submit"]))
		{
			if (is_string($options["submit"]))  $options["submit"] = array($options["submit"]);
?>
		<div class="formsubmit">
<?php
			foreach ($options["submit"] as $val)
			{
?>
			<input class="submit" type="submit"<?php if (isset($options["submitname"]))  echo " name=\"" . htmlspecialchars($options["submitname"]) . "\""; ?> value="<?php echo htmlspecialchars(BB_Translate($val)); ?>" />
<?php
			}
?>
		</div>
<?php
		}

		if (isset($options["submit"]) || (isset($options["useform"]) && $options["useform"]))
		{
?>
		</form>
<?php
		}
?>
	</div>
<?php
		if ($dateused)
		{
			if (!$jqueryuiused)
			{
				BB_OutputJQueryUI($rooturl, $supportpath);
				$jqueryuiused = true;
			}
?>
	<script type="text/javascript">
	$(function() {
		if (jQuery.fn.datepicker)  $('div.formfields div.formitem input.date').datepicker({ dateFormat: 'yy-mm-dd' });
		else  alert('<?php echo BB_JSSafe(BB_Translate("Warning:  Missing jQuery UI for date field.\n\nThis feature requires AdminPack Extras.")); ?>');
	});
	</script>
<?php
		}

		if ($accordionused)
		{
			if (!$jqueryuiused)
			{
				BB_OutputJQueryUI($rooturl, $supportpath);
				$jqueryuiused = true;
			}

?>
	<script type="text/javascript">
	$(function() {
		if (jQuery.fn.accordion)  $('div.formaccordionwrap').accordion({ collapsible : true, active : false, heightStyle : 'content' });
		else  alert('<?php echo BB_JSSafe(BB_Translate("Warning:  Missing jQuery UI for accordion.\n\nThis feature requires AdminPack Extras.")); ?>');
	});
	</script>
<?php
		}

		if (isset($options["focus"]) && (is_string($options["focus"]) || ($options["focus"] === true && $autofocus !== false)))
		{
?>
	<script type="text/javascript">
	$('#<?php echo BB_JSSafe(is_string($options["focus"]) ? $options["focus"] : $autofocus); ?>').focus();
	</script>
<?php
		}
	}


	// Drop-in replacement for hash_hmac() on hosts where Hash is not available.
	// Only supports HMAC-MD5 and HMAC-SHA1.
	if (!function_exists("hash_hmac"))
	{
		function hash_hmac($algo, $data, $key, $raw_output = false)
		{
			$algo = strtolower($algo);
			$size = 64;
			$opad = str_repeat("\x5C", $size);
			$ipad = str_repeat("\x36", $size);

			if (strlen($key) > $size)  $key = $algo($key, true);
			$key = str_pad($key, $size, "\x00");

			$y = strlen($key) - 1;
			for ($x = 0; $x < $y; $x++)
			{
				$opad[$x] = $opad[$x] ^ $key[$x];
				$ipad[$x] = $ipad[$x] ^ $key[$x];
			}

			$result = $algo($opad . $algo($ipad . $data, true), $raw_output);

			return $result;
		}
	}

	// Function swiped from Barebones CMS edit.php.
	// Create a valid language-level security token (also known as a 'nonce').
	function BB_CreateSecurityToken($name, $extra = "")
	{
		global $bb_randpage, $bb_usertoken;

		$str = $name . ":";
		if (is_string($extra) && $extra != "")
		{
			$extra = explode(",", $extra);
			foreach ($extra as $key)
			{
				$key = trim($key);
				if ($key != "" && isset($_REQUEST[$key]))  $str .= (string)$_REQUEST[$key] . ":";
			}
		}
		else if (is_array($extra))
		{
			foreach ($extra as $val)  $str .= $val . ":";
		}

		return hash_hmac("sha1", $str, $bb_randpage . ":" . $bb_usertoken);
	}

	function BB_IsSecExtraOpt($opt)
	{
		return (isset($_REQUEST["sec_extra"]) && strpos("," . $_REQUEST["sec_extra"] . ",", "," . $opt . ",") !== false);
	}

	// Custom-built routines specifically for displaying the final page.
	function BB_ProcessPageToken($name)
	{
		// Check the security token.  If it doesn't exist, load the main page.
		if (isset($_REQUEST[$name]) && (!isset($_REQUEST["sec_t"]) || $_REQUEST["sec_t"] != BB_CreateSecurityToken($_REQUEST[$name], (isset($_REQUEST["sec_extra"]) ? $_REQUEST["sec_extra"] : ""))))
		{
			echo BB_Translate("Invalid security token.");
			exit();
		}
	}

	function BB_GetBackQueryString()
	{
		$result = $_GET;
		unset($result["bb_msg"]);
		unset($result["bb_msgtype"]);

		return str_replace(array("=", "+", "/"), array("", "-", "_"), base64_encode(serialize($result)));
	}

	function BB_GetBackURL($query = array(), $fullrequest = false, $protocol = "")
	{
		if (isset($_REQUEST["bb_back"]))
		{
			$items = unserialize(base64_decode(str_replace(array("-", "_"), array("+", "/"), $_REQUEST["bb_back"])));
			if (is_array($items))
			{
				foreach ($items as $key => $val)
				{
					if (!is_array($val))  $query[] = urlencode($key) . "=" . urlencode($val);
					else
					{
						foreach ($val as $val2)  $query[] = urlencode($key) . "[]=" . urlencode($val2);
					}
				}
			}
		}

		return ($fullrequest ? BB_GetFullRequestURLBase($protocol) : BB_GetRequestURLBase()) . (count($query) ? "?" . implode("&", $query) : "");
	}

	function BB_RedirectPage($msgtype = "", $msg = "", $query = array())
	{
		if (count($query))  unset($_REQUEST["bb_back"]);

		if ($msgtype != "")
		{
			if (!isset($_REQUEST["bb_msgtype"]) || ($_REQUEST["bb_msgtype"] != "error" && $_REQUEST["bb_msgtype"] != "success" && $_REQUEST["bb_msgtype"] != "info"))  $_REQUEST["bb_msgtype"] = $msgtype;
			else if ($msgtype == "error")  $_REQUEST["bb_msgtype"] = "error";
			else if ($msgtype == "info" && $_REQUEST["bb_msgtype"] != "error")  $_REQUEST["bb_msgtype"] = "info";
			else  $_REQUEST["bb_msgtype"] = "success";

			if (!isset($_REQUEST["bb_msg"]))  $_REQUEST["bb_msg"] = $msg;
			else  $_REQUEST["bb_msg"] = $msg . "  " . $_REQUEST["bb_msg"];

			$query[] = "bb_msgtype=" . urlencode($_REQUEST["bb_msgtype"]);
			$query[] = "bb_msg=" . urlencode($_REQUEST["bb_msg"]);
		}

		header("Location: " . BB_GetBackURL($query, true));

		exit();
	}

	function BB_SetPageMessage($msgtype, $msg)
	{
		if (!isset($_REQUEST["bb_msgtype"]) || $msgtype == "error" || ($msgtype == "info" && $_REQUEST["bb_msgtype"] != "error") || ($msgtype == "success" && $_REQUEST["bb_msgtype"] == "success"))
		{
			$_REQUEST["bb_msgtype"] = $msgtype;
			$_REQUEST["bb_msg"] = $msg;
		}
	}

	function BB_GetPageMessageType()
	{
		return (isset($_REQUEST["bb_msg"]) && isset($_REQUEST["bb_msgtype"]) ? ($_REQUEST["bb_msgtype"] == "error" || $_REQUEST["bb_msgtype"] == "success" ? $_REQUEST["bb_msgtype"] : "info") : "");
	}

	function BB_GetValue($key, $default)
	{
		return (isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default);
	}

	function BB_SelectValues($data)
	{
		$result = array();
		foreach ($data as $val)  $result[$val] = true;

		return $result;
	}

	function BB_ProcessInfoDefaults($info, $defaults)
	{
		foreach ($defaults as $key => $val)
		{
			if (!isset($info[$key]))  $info[$key] = $val;
		}

		return $info;
	}

	function BB_GetIDDiff($origids, $newids)
	{
		$result = array("remove" => array(), "add" => array());
		foreach ($origids as $id => $val)
		{
			if (!isset($newids[$id]))  $result["remove"][$id] = $val;
		}

		foreach ($newids as $id => $val)
		{
			if (!isset($origids[$id]))  $result["add"][$id] = $val;
		}

		return $result;
	}

	function BB_InitLayouts()
	{
		global $bb_page_layout, $bb_menu_layout, $bb_menu_item_layout, $bb_message_layout;

		// Default layout swiped from the Barebones CMS Layout widget.
		// SEO-friendly (2-1) admin-style 2-column pixel-widths liquid layout (200px 100% height, content).
		// Sources:  http://matthewjamestaylor.com/blog/holy-grail-no-quirks-mode.htm, http://matthewjamestaylor.com/blog/ultimate-2-column-left-menu-pixels.htm
		if (!isset($bb_page_layout))
		{
			ob_start();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>@TITLE@</title>
<link rel="stylesheet" href="@ROOTURL@/@SUPPORTPATH@/admin.css" type="text/css" media="all" />
<link rel="stylesheet" href="@ROOTURL@/@SUPPORTPATH@/admin_print.css" type="text/css" media="print" />
<script type="text/javascript" src="@ROOTURL@/@SUPPORTPATH@/jquery-1.11.0.min.js"></script>
<script type="text/javascript" src="@ROOTURL@/@SUPPORTPATH@/admin.js"></script>
<?php if (function_exists("BB_InjectLayoutHead"))  BB_InjectLayoutHead(); ?>
</head>
<body>
<div class="pagewrap">
	<div class="contentwrap">
		<div class="colmask">
			<div class="colright">
				<div class="col1wrap">
					<div class="col1">
						<div class="col1inner">
@MESSAGE@
<div class="maincontent">
@CONTENT@
</div>
						</div>
					</div>
				</div>
				<div class="col2"><div class="leftnav">@MENU@</div></div>
			</div>
		</div>
	</div>
	<div class="stickycol"></div>
</div>
</body>
</html>
<?php
			$bb_page_layout = ob_get_contents();
			ob_end_clean();
		}

		if (!isset($bb_menu_layout))
		{
			$bb_menu_layout = <<<EOF
<div class="menu">
	<div class="title">@TITLE@</div>
@ITEMS@
</div>
EOF;
		}

		if (!isset($bb_menu_item_layout))
		{
			$bb_menu_item_layout = <<<EOF
	<a @OPTS@>@NAME@</a>
EOF;
		}

		if (!isset($bb_message_layout))
		{
			$bb_message_layout = <<<EOF
<div class="message"><div class="@MSGTYPE@">@MESSAGE@</div></div>
EOF;
		}
	}

	function BB_GeneratePage($title, $menuopts, $contentopts)
	{
		global $bb_rootname, $bb_page_layout, $bb_menu_layout, $bb_menu_item_layout, $bb_message_layout;

		if (!isset($contentopts["title"]))  $contentopts["title"] = $title;
		if (isset($contentopts["hidden"]) && !isset($contentopts["hidden"]["bb_back"]))  $contentopts["hidden"]["bb_back"] = (isset($_POST["bb_back"]) ? $_POST["bb_back"] : BB_GetBackQueryString());

		header("Content-Type: text/html; charset=UTF-8");

		BB_InitLayouts();

		// Process the header.
		if (defined("BB_ROOT_URL"))  $rooturl = BB_ROOT_URL;
		else if (defined("ROOT_URL"))  $rooturl = ROOT_URL;
		else
		{
			$rooturl = BB_GetRequestURLBase();
			if (substr($rooturl, -1) != "/")  $rooturl = dirname($rooturl);
			if (substr($rooturl, -1) == "/")  $rooturl = substr($rooturl, 0, -1);
		}

		if (defined("BB_SUPPORT_PATH"))  $supportpath = BB_SUPPORT_PATH;
		else if (defined("SUPPORT_PATH"))  $supportpath = SUPPORT_PATH;
		else  $supportpath = "support";

		$data = str_replace("@ROOTURL@", htmlspecialchars($rooturl), $bb_page_layout);
		$data = str_replace("@SUPPORTPATH@", htmlspecialchars($supportpath), $data);

		// Process the title and message.
		$data = str_replace("@TITLE@", htmlspecialchars(BB_Translate(($bb_rootname != "" ? $bb_rootname . " | " : "") . $title)), $data);
		$data = str_replace("@ROOTNAME@", htmlspecialchars(BB_Translate($bb_rootname)), $data);
		if (!isset($_REQUEST["bb_msg"]))  $data = str_replace("@MESSAGE@", "", $data);
		else
		{
			if (!isset($_REQUEST["bb_msgtype"]) || ($_REQUEST["bb_msgtype"] != "error" && $_REQUEST["bb_msgtype"] != "success"))  $_REQUEST["bb_msgtype"] = "info";

			$data2 = str_replace("@MSGTYPE@", htmlspecialchars($_REQUEST["bb_msgtype"]), $bb_message_layout);
			$data2 = str_replace("@MESSAGE@", htmlspecialchars(BB_Translate($_REQUEST["bb_msg"])), $data2);
			$data = str_replace("@MESSAGE@", $data2, $data);
		}

		// Process the menu.
		$data2 = "";
		foreach ($menuopts as $title => $items)
		{
			$data3 = "";
			foreach ($items as $name => $opts)
			{
				if (!is_array($opts))  $opts = array("href" => $opts);

				$data5 = array();
				foreach ($opts as $name2 => $val)
				{
					$data5[] = htmlspecialchars($name2) . "=\"" . htmlspecialchars($val) . "\"";
				}

				$data4 = str_replace("@OPTS@", implode(" ", $data5), $bb_menu_item_layout);

				$data3 .= str_replace("@NAME@", htmlspecialchars(BB_Translate($name)), $data4);
			}

			$data3 = str_replace("@ITEMS@", $data3, $bb_menu_layout);
			$data2 .= str_replace("@TITLE@", htmlspecialchars(BB_Translate($title)), $data3);
		}
		$data = str_replace("@MENU@", $data2, $data);

		// Process and display the content.
		$pos = strpos($data, "@CONTENT@");
		echo substr($data, 0, $pos);
		BB_PropertyForm($contentopts);
		echo substr($data, $pos + 9);
	}
?>