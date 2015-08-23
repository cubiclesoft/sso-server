<?php
	// SSO Generic Login Module for Password Requirements
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	if (!defined("SSO_FILE"))  exit();

	$g_sso_login_modules["sso_password"] = array(
		"name" => "Password Requirements",
		"desc" => "Increases the strength of passwords in the login system."
	);

	class sso_login_module_sso_password extends sso_login_ModuleBase
	{
		public function DefaultOrder()
		{
			return 10;
		}

		private function GetInfo()
		{
			global $sso_settings;

			$info = $sso_settings["sso_login"]["modules"]["sso_password"];
			if (!isset($info["minbits"]))  $info["minbits"] = 18;
			if (!isset($info["analyze"]))  $info["analyze"] = true;
			if (!isset($info["analyze_ajax"]))  $info["analyze_ajax"] = false;
			if (!isset($info["suggest"]))  $info["suggest"] = true;
			if (!isset($info["expire"]))  $info["expire"] = 31;

			return $info;
		}

		public function ConfigSave()
		{
			global $sso_settings;

			$info = $this->GetInfo();
			$info["minbits"] = (int)$_REQUEST["sso_password_minbits"];
			$info["analyze"] = ($_REQUEST["sso_password_analyze"] > 0);
			$info["analyze_ajax"] = ($_REQUEST["sso_password_analyze_ajax"] > 0);
			$info["suggest"] = ($_REQUEST["sso_password_suggest"] > 0);
			$info["expire"] = (int)$_REQUEST["sso_password_expire"];

			if ($info["minbits"] < 1)  BB_SetPageMessage("error", "The 'Minimum Password Strength' field contains an invalid value.");
			else if ($info["expire"] < 0)  BB_SetPageMessage("error", "The 'Password Expiration' field contains an invalid value.");

			$sso_settings["sso_login"]["modules"]["sso_password"] = $info;
		}

		public function Config(&$contentopts)
		{
			$info = $this->GetInfo();
			$contentopts["fields"][] = array(
				"title" => "Minimum Password Strength",
				"type" => "text",
				"name" => "sso_password_minbits",
				"value" => BB_GetValue("sso_password_minbits", $info["minbits"]),
				"desc" => "The minimum number of bits of entropy required.  An eight character password is approximately 18 bits according to NIST."
			);
			$contentopts["fields"][] = array(
				"title" => "Deep Analyze Passwords?",
				"type" => "select",
				"name" => "sso_password_analyze",
				"options" => array(1 => "Yes", 0 => "No"),
				"select" => BB_GetValue("sso_password_analyze", (string)(int)$info["analyze"]),
				"desc" => "Performs in-depth analysis of user-submitted passwords against a 300,000+ word dictionary, keyboard shifting attacks, etc.  Takes up to 2 seconds and 4MB RAM to analyze each password."
			);
			$contentopts["fields"][] = array(
				"title" => "Deep Analyze AJAX Passwords?",
				"type" => "select",
				"name" => "sso_password_analyze_ajax",
				"options" => array(1 => "Yes", 0 => "No"),
				"select" => BB_GetValue("sso_password_analyze_ajax", (string)(int)$info["analyze_ajax"]),
				"desc" => "Performs in-depth analysis of user-submitted passwords when checking via AJAX.  Requires the 'Deep Analyze Passwords' option to be enabled."
			);
			$contentopts["fields"][] = array(
				"title" => "Suggest Random Password Ideas?",
				"type" => "select",
				"name" => "sso_password_suggest",
				"options" => array(1 => "Yes", 0 => "No"),
				"select" => BB_GetValue("sso_password_suggest", (string)(int)$info["suggest"]),
				"desc" => "Suggests some words from the dictionary to use as part of a password."
			);
			$contentopts["fields"][] = array(
				"title" => "Password Expiration",
				"type" => "text",
				"name" => "sso_password_expire",
				"value" => BB_GetValue("sso_password_expire", $info["expire"]),
				"desc" => "The number of days until a password expires and the user is required to create a new one upon successful login.  Set to 0 for no expiration."
			);
		}

		private function SignupUpdateCheck(&$result, $ajax, $update)
		{
			$field = SSO_FrontendFieldValue($update ? "update_pass" : "createpass");
			if (!$ajax || $field !== false)
			{
				$info = $this->GetInfo();
				if ($field === false || $field == "")
				{
				}
				else if (!$this->IsStrongPassword($field, $info["minbits"] + ($info["analyze"] && $ajax && !$info["analyze_ajax"] ? 3 : 0), ($info["analyze"] && (!$ajax || $info["analyze_ajax"]))))
				{
					$result["errors"][] = BB_Translate("Password is not strong enough.  Sentences make good passwords.");
					if ($info["suggest"])  $result["warnings"][] = BB_Translate("Tip:  Use one or more words from 'Password Ideas' (below) to form a complete sentence for your new password.");
				}
				else if ($info["analyze"] && $ajax && !$info["analyze_ajax"])
				{
					$result["warnings"][] = BB_Translate("Password looks okay but was only partially validated.  Full validation will take place when the form is submitted.");
				}
				else
				{
					$result["success"] = BB_Translate("Password looks okay.");
				}
			}
		}

		public function SignupCheck(&$result, $ajax, $admin)
		{
			if ($admin)  return;

			$this->SignupUpdateCheck($result, $ajax, false);
		}

		public function GenerateSignup($admin)
		{
			if ($admin)  return false;

			$info = $this->GetInfo();
			if ($info["suggest"])
			{
				$phrase = "";
				for ($x = 0; $x < 6; $x++)  $phrase .= " " . SSO_GetRandomWord();
				$phrase = trim($phrase);

?>
			<div class="sso_main_formitem">
				<div class="sso_main_formtitle"><?php echo htmlspecialchars(BB_Translate("Password Ideas")); ?></div>
				<div class="sso_main_formdata"><div class="sso_main_static"><?php echo htmlspecialchars($phrase); ?></div></div>
			</div>
<?php
			}
		}

		public function SignupAddInfo(&$userinfo, $admin)
		{
			$info = $this->GetInfo();

			$userinfo["sso_password"] = array(
				"expires" => CSDB::ConvertToDBTime(time() + 24 * 60 * 60 * $info["expire"])
			);
		}

		public function InitMessages(&$result)
		{
			if ($_REQUEST["sso_msg"] == "sso_login_password_expired")  $result["errors"][] = BB_Translate("Password has expired.");
		}

		public function LoginCheck(&$result, $userinfo, $recoveryallowed)
		{
			global $sso_target_url;

			if ($userinfo !== false)
			{
				$info = $this->GetInfo();
				if ($info["expire"] > 0 && (!isset($userinfo["sso_password"]) || !isset($userinfo["sso_password"]["expires"]) || CSDB::ConvertFromDBTime($userinfo["sso_password"]["expires"]) <= time()))
				{
					if (!$recoveryallowed)
					{
						if (SSO_FrontendFieldValue("update_info", "") != "yes")
						{
							$result["errors"][] = BB_Translate("Password has expired.  Check the checkbox under 'Update Information' and sign in again to change your password.");
						}
					}
					else
					{
						header("Location: " . BB_GetRequestHost() . $sso_target_url . "&sso_login_action=recover&sso_msg=sso_login_password_expired");
						exit();
					}
				}
			}
		}

		public function UpdateInfoCheck(&$result, $userinfo, $ajax)
		{
			if ($userinfo !== false)  $this->SignupCheck($result, $ajax, true);
		}

		public function UpdateAddInfo(&$userinfo)
		{
			if (SSO_FrontendFieldValue("update_pass", "") != "")  $this->SignupAddInfo($userinfo, false);
		}

		public function GenerateUpdateInfo($userrow, $userinfo)
		{
			$this->GenerateSignup(false);
		}

		// Strong password checking functions.
		public function GetNISTNumBits($password, $repeatcalc = false)
		{
			$y = strlen($password);
			if ($repeatcalc)
			{
				// Variant on NIST rules to reduce long sequences of repeated characters.
				$result = 0;
				$charmult = array_fill(0, 256, 1);
				for ($x = 0; $x < $y; $x++)
				{
					$tempchr = ord(substr($password, $x, 1));
					if ($x > 19)  $result += $charmult[$tempchr];
					else if ($x > 7)  $result += $charmult[$tempchr] * 1.5;
					else if ($x > 0)  $result += $charmult[$tempchr] * 2;
					else  $result += 4;

					$charmult[$tempchr] *= 0.75;
				}

				return $result;
			}
			else
			{
				if ($y > 20)  return 4 + (7 * 2) + (12 * 1.5) + $y - 20;
				if ($y > 8)  return 4 + (7 * 2) + (($y - 8) * 1.5);
				if ($y > 1)  return 4 + (($y - 1) * 2);

				return ($y == 1 ? 4 : 0);
			}
		}

		public function IsStrongPassword($password, $minbits = 18, $usedict = false, $minwordlen = 4)
		{
			// NIST password strength rules allow up to 6 extra bits for mixed case and non-alphabetic.
			$upper = false;
			$lower = false;
			$numeric = false;
			$other = false;
			$space = false;
			$y = strlen($password);
			for ($x = 0; $x < $y; $x++)
			{
				$tempchr = ord(substr($password, $x, 1));
				if ($tempchr >= ord("A") && $tempchr <= ord("Z"))  $upper = true;
				else if ($tempchr >= ord("a") && $tempchr <= ord("z"))  $lower = true;
				else if ($tempchr >= ord("0") && $tempchr <= ord("9"))  $numeric = true;
				else if ($tempchr == ord(" "))  $space = true;
				else  $other = true;
			}
			$extrabits = ($upper && $lower && $other ? ($numeric ? 6 : 5) : ($numeric && !$upper && !$lower ? ($other ? -2 : -6) : 0));
			if (!$space)  $extrabits -= 2;
			else if (count(explode(" ", preg_replace('/\s+/', " ", $password))) > 3)  $extrabits++;
			$result = $this->GetNISTNumBits($password, true) + $extrabits;

			$password = strtolower($password);
			$revpassword = strrev($password);
			$numbits = $this->GetNISTNumBits($password) + $extrabits;
			if ($result > $numbits)  $result = $numbits;

			// Remove QWERTY strings.
			$qwertystrs = array(
				"1234567890-qwertyuiopasdfghjkl;zxcvbnm,./",
				"1qaz2wsx3edc4rfv5tgb6yhn7ujm8ik,9ol.0p;/-['=]:?_{\"+}",
				"1qaz2wsx3edc4rfv5tgb6yhn7ujm8ik9ol0p",
				"qazwsxedcrfvtgbyhnujmik,ol.p;/-['=]:?_{\"+}",
				"qazwsxedcrfvtgbyhnujmikolp",
				"]\"/=[;.-pl,0okm9ijn8uhb7ygv6tfc5rdx4esz3wa2q1",
				"pl0okm9ijn8uhb7ygv6tfc5rdx4esz3wa2q1",
				"]\"/[;.pl,okmijnuhbygvtfcrdxeszwaq",
				"plokmijnuhbygvtfcrdxeszwaq",
				"014725836914702583697894561230258/369*+-*/",
				"abcdefghijklmnopqrstuvwxyz"
			);
			foreach ($qwertystrs as $qwertystr)
			{
				$qpassword = $password;
				$qrevpassword = $revpassword;
				$z = 6;
				do
				{
					$y = strlen($qwertystr) - $z;
					for ($x = 0; $x < $y; $x++)
					{
						$str = substr($qwertystr, $x, $z);
						$qpassword = str_replace($str, "*", $qpassword);
						$qrevpassword = str_replace($str, "*", $qrevpassword);
					}

					$z--;
				} while ($z > 2);

				$numbits = $this->GetNISTNumBits($qpassword) + $extrabits;
				if ($result > $numbits)  $result = $numbits;
				$numbits = $this->GetNISTNumBits($qrevpassword) + $extrabits;
				if ($result > $numbits)  $result = $numbits;

				if ($result < $minbits)  return false;
			}

			if ($usedict && $result >= $minbits)
			{
				$passwords = array();

				// Add keyboard shifting password variants.
				$keyboardmap_down_noshift = array(
					"z" => "", "x" => "", "c" => "", "v" => "", "b" => "", "n" => "", "m" => "", "," => "", "." => "", "/" => "", "<" => "", ">" => "", "?" => ""
				);
				if ($password == str_replace(array_keys($keyboardmap_down_noshift), array_values($keyboardmap_down_noshift), $password))
				{
					$keyboardmap_downright = array(
						"a" => "z",
						"q" => "a",
						"1" => "q",
						"s" => "x",
						"w" => "s",
						"2" => "w",
						"d" => "c",
						"e" => "d",
						"3" => "e",
						"f" => "v",
						"r" => "f",
						"4" => "r",
						"g" => "b",
						"t" => "g",
						"5" => "t",
						"h" => "n",
						"y" => "h",
						"6" => "y",
						"j" => "m",
						"u" => "j",
						"7" => "u",
						"i" => "k",
						"8" => "i",
						"o" => "l",
						"9" => "o",
						"0" => "p",
					);

					$keyboardmap_downleft = array(
						"2" => "q",
						"w" => "a",
						"3" => "w",
						"s" => "z",
						"e" => "s",
						"4" => "e",
						"d" => "x",
						"r" => "d",
						"5" => "r",
						"f" => "c",
						"t" => "f",
						"6" => "t",
						"g" => "v",
						"y" => "g",
						"7" => "y",
						"h" => "b",
						"u" => "h",
						"8" => "u",
						"j" => "n",
						"i" => "j",
						"9" => "i",
						"k" => "m",
						"o" => "k",
						"0" => "o",
						"p" => "l",
						"-" => "p",
					);

					$password2 = str_replace(array_keys($keyboardmap_downright), array_values($keyboardmap_downright), $password);
					$passwords[] = $password2;
					$passwords[] = strrev($password2);

					$password2 = str_replace(array_keys($keyboardmap_downleft), array_values($keyboardmap_downleft), $password);
					$passwords[] = $password2;
					$passwords[] = strrev($password2);
				}

				// Deal with LEET-Speak substitutions.
				$leetspeakmap = array(
					"@" => "a",
					"!" => "i",
					"$" => "s",
					"1" => "i",
					"2" => "z",
					"3" => "e",
					"4" => "a",
					"5" => "s",
					"6" => "g",
					"7" => "t",
					"8" => "b",
					"9" => "g",
					"0" => "o"
				);

				$password2 = str_replace(array_keys($leetspeakmap), array_values($leetspeakmap), $password);
				$passwords[] = $password2;
				$passwords[] = strrev($password2);

				$leetspeakmap["1"] = "l";
				$password3 = str_replace(array_keys($leetspeakmap), array_values($leetspeakmap), $password);
				if ($password3 != $password2)
				{
					$passwords[] = $password3;
					$passwords[] = strrev($password3);
				}

				// Process the password, while looking for words in the dictionary.
				$a = ord("a");
				$z = ord("z");
				$data = file_get_contents(SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/dictionary.txt");
				foreach ($passwords as $num => $password)
				{
					$y = strlen($password);
					for ($x = 0; $x < $y; $x++)
					{
						$tempchr = ord(substr($password, $x, 1));
						if ($tempchr >= $a && $tempchr <= $z)
						{
							for ($x2 = $x + 1; $x2 < $y; $x2++)
							{
								$tempchr = ord(substr($password, $x2, 1));
								if ($tempchr < $a || $tempchr > $z)  break;
							}

							$found = false;
							while (!$found && $x2 - $x >= $minwordlen)
							{
								$word = "/\\n" . substr($password, $x, $minwordlen);
								for ($x3 = $x + $minwordlen; $x3 < $x2; $x3++)  $word .= "(" . $password{$x3};
								for ($x3 = $x + $minwordlen; $x3 < $x2; $x3++)  $word .= ")?";
								$word .= "\\n/";

								preg_match_all($word, $data, $matches);
								if (!count($matches[0]))
								{
									$password{$x} = "*";
									$x++;
									$numbits = $this->GetNISTNumBits(substr($password, 0, $x)) + $extrabits;
									if ($numbits >= $minbits)  $found = true;
								}
								else
								{
									foreach ($matches[0] as $match)
									{
										$password2 = str_replace(trim($match), "*", $password);
										$numbits = $this->GetNISTNumBits($password2) + $extrabits;
										if ($result > $numbits)  $result = $numbits;

										if ($result < $minbits)  return false;
									}

									$found = true;
								}
							}

							if ($found)  break;

							$x = $x2 - 1;
						}
					}
				}
			}

			return $result >= $minbits;
		}
	}
?>