<?php
	// SSO Facebook Connect Provider - Derived class
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	if (!defined("SSO_FILE"))  exit();

	if (!function_exists("curl_init"))
	{
		require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/http.php";
		require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/web_browser.php";
		require_once SSO_ROOT_PATH . "/" . SSO_SUPPORT_PATH . "/emulate_curl.php";
	}

	global $sso_provider;

	require_once SSO_ROOT_PATH . "/" . SSO_PROVIDER_PATH . "/" . $sso_provider . "/facebook-sdk-src/base_facebook.php";

	class SSO_FacebookSDK extends BaseFacebook
	{
		protected static $allowed = array("state" => true, "code" => true, "access_token" => true, "user_id" => true);

		// Original function is poorly written.
		protected function establishCSRFTokenState()
		{
			global $sso_rng;

			if (!isset($this->state))
			{
				$this->state = $sso_rng->GenerateString();
				$this->setPersistentData("state", $this->state);
			}
		}

		protected function setPersistentData($key, $value)
		{
			global $sso_session_info;

			if (!isset(self::$allowed[$key]))
			{
				self::errorLog("Unsupported key passed to setPersistentData.");

				return;
			}

			if (!isset($sso_session_info["sso_facebook"]))  $sso_session_info["sso_facebook"] = array();
			$sso_session_info["sso_facebook"][$key] = $value;

			if (!SSO_SaveSessionInfo())  self::errorLog("Unable to save session info.");
		}

		protected function getPersistentData($key, $default = false)
		{
			global $sso_session_info;

			if (!isset(self::$allowed[$key]))
			{
				self::errorLog("Unsupported key passed to setPersistentData.");

				return;
			}

			return (isset($sso_session_info["sso_facebook"]) && isset($sso_session_info["sso_facebook"][$key]) ? $sso_session_info["sso_facebook"][$key] : $default);
		}

		protected function clearPersistentData($key)
		{
			global $sso_session_info;

			if (!isset(self::$allowed[$key]))
			{
				self::errorLog("Unsupported key passed to setPersistentData.");

				return;
			}

			unset($sso_session_info["sso_facebook"][$key]);

			SSO_SaveSessionInfo();
		}

		protected function clearAllPersistentData()
		{
			global $sso_session_info;

			unset($sso_session_info["sso_facebook"]);

			SSO_SaveSessionInfo();
		}
	}
?>