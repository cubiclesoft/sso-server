<?php
	// Twilio SDK.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	class TwilioSDK
	{
		protected $sid, $token, $apibase;

		public function __construct()
		{
			$this->sid = false;
			$this->token = false;
			$this->apibase = false;
		}

		public function SetAccessInfo($sid, $token, $apibase = "https://api.twilio.com/2010-04-01")
		{
			$this->sid = $sid;
			$this->token = $token;
			$this->apibase = $apibase;
		}

		// Drop-in replacement for hash_hmac() on hosts where Hash is not available.
		// Only supports HMAC-MD5 and HMAC-SHA1.
		public static function hash_hmac_internal($algo, $data, $key, $raw_output = false)
		{
			if (function_exists("hash_hmac"))  return hash_hmac($algo, $data, $key, $raw_output);

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

		public function ValidateWebhookRequest($checksig = true)
		{
			if ($this->sid === false || $this->token === false)
			{
				http_response_code(403);

				echo "Account SID or Token not set.";

				exit();
			}

			if (!class_exists("Str", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/str_basics.php";
			if (!class_exists("Request", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/request.php";

			$valid = ((isset($_POST["AccountSid"]) && Str::CTstrcmp($this->sid, $_POST["AccountSid"]) === 0) || (isset($_GET["AccountSid"]) && Str::CTstrcmp($this->sid, $_GET["AccountSid"]) === 0));

			if (!$valid)
			{
				http_response_code(403);

				echo "Missing or invalid account SID.";

				exit();
			}

			if (!$checksig)  return;

			$url = Request::GetFullURLBase();
			if (isset($_SERVER["QUERY_STRING"]) && $_SERVER["QUERY_STRING"] !== "")  $url .= "?" . $_SERVER["QUERY_STRING"];

			if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST))
			{
				ksort($_POST);

				foreach ($_POST as $key => $val)
				{
					$url .= $key . $val;
				}
			}

			if (!isset($_SERVER["HTTP_X_TWILIO_SIGNATURE"]) || Str::CTstrcmp(base64_encode(self::hash_hmac_internal("sha1", $url, $this->token, true)), $_SERVER["HTTP_X_TWILIO_SIGNATURE"]) !== 0)
			{
				http_response_code(403);

				echo "Missing or invalid signature.";

				exit();
			}
		}

		public static function StartXMLResponse()
		{
			header("Content-Type: text/xml");

			echo "<" . "?xml version=\"1.0\" encoding=\"UTF-8\" ?" . ">\n";
			self::OpenXMLTag("Response");
		}

		public static function OpenXMLTag($tagname, $attrs = array(), $void = false)
		{
			echo "<" . $tagname;

			foreach ($attrs as $key => $val)
			{
				echo " " . htmlspecialchars($key) . "=\"" . htmlspecialchars($val, ENT_QUOTES | ENT_XML1, "UTF-8") . "\"";
			}

			if ($void)  echo " /";

			echo ">";
		}

		public static function AppendXMLData($str)
		{
			if ($str !== "")  echo htmlspecialchars($str, ENT_QUOTES | ENT_XML1, "UTF-8");
		}

		public static function CloseXMLTag($tagname)
		{
			echo "</" . $tagname . ">\n";
		}

		public static function OutputXMLTag($tagname, $attrs = array(), $str = "")
		{
			if ($str === "")  self::OpenXMLTag($tagname, $attrs, true);
			else
			{
				self::OpenXMLTag($tagname, $attrs);
				self::AppendXMLData($str);
				self::CloseXMLTag($tagname);
			}
		}

		public static function EndXMLResponse()
		{
			self::CloseXMLTag("Response");
		}

		public function Internal_DownloadRecordingCallback($response, $data, $opts)
		{
			if ($response["code"] == 200)
			{
				fwrite($opts, $data);
			}

			return true;
		}

		public function DownloadRecording($sid, $format = ".wav", $filename = false)
		{
			$options = array();

			if ($filename !== false)
			{
				$fp = @fopen($filename, "wb");
				if ($fp === false)  return array("success" => false, "error" => self::Twilio_Translate("Unable to create file for storing the recording."), "errorcode" => "fopen_failed", "info" => $filename);

				$options["read_body_callback"] = array($this, "Internal_DownloadRecordingCallback");
				$options["read_body_callback_opts"] = $fp;
			}

			return $this->RunAPI("GET", "Recordings/" . $sid, array(), $options, 200, false, ($format !== ".wav" ? $format : ""));
		}

		public function RunAPI($method, $apipath, $postvars = array(), $options = array(), $expected = 200, $decodebody = true, $extension = ".json")
		{
			if ($this->sid === false || $this->token === false)  return array("success" => false, "error" => self::Twilio_Translate("Account SID or Token not set."), "errorcode" => "missing_account_sid_or_token");
			if ($this->apibase === false)  return array("success" => false, "error" => self::Twilio_Translate("API base not set."), "errorcode" => "missing_apibase");

			if (!class_exists("WebBrowser", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/web_browser.php";

			$url = $this->apibase . "/Accounts/" . $this->sid . "/" . $apipath . $extension;

			$options2 = array(
				"method" => $method,
				"headers" => array(
					"Authorization" => "Basic " . base64_encode($this->sid . ":" . $this->token)
				)
			);

			if ($method === "POST")
			{
				$options2["postvars"] = $postvars;

				foreach ($options as $key => $val)
				{
					if (isset($options2[$key]) && is_array($options2[$key]))  $options2[$key] = array_merge($options2[$key], $val);
					else  $options2[$key] = $val;
				}
			}
			else
			{
				$options2 = array_merge($options2, $options);
			}

			$web = new WebBrowser();

			$result = $web->Process($url, $options2);

			if (!$result["success"])  return $result;

			if ($result["response"]["code"] != $expected)  return array("success" => false, "error" => self::Twilio_Translate("Expected a %d response from the Twilio API.  Received '%s'.", $expected, $result["response"]["line"]), "errorcode" => "unexpected_twilio_api_response", "info" => $result);

			if ($decodebody)
			{
				$data = json_decode($result["body"], true);
				if (!is_array($data))  return array("success" => false, "error" => self::Twilio_Translate("Unable to decode the server response as JSON."), "errorcode" => "expected_json", "info" => $result);

				$result = array(
					"success" => true,
					"data" => $data
				);
			}

			return $result;
		}

		protected static function Twilio_Translate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}
	}
?>