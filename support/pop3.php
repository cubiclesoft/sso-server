<?php
	// CubicleSoft PHP POP3 class.
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	class POP3
	{
		private $fp, $debug;

		public function __construct()
		{
			$this->fp = false;
			$this->messagelist = array();
			$this->debug = false;
		}

		public function __destruct()
		{
			$this->Disconnect();
		}

		private static function ProcessSSLOptions(&$options, $key, $host)
		{
			if (isset($options[$key]["auto_cainfo"]))
			{
				unset($options[$key]["auto_cainfo"]);

				$cainfo = ini_get("curl.cainfo");
				if ($cainfo !== false && strlen($cainfo) > 0)  $options[$key]["cafile"] = $cainfo;
				else if (file_exists(str_replace("\\", "/", dirname(__FILE__)) . "/cacert.pem"))  $options[$key]["cafile"] = str_replace("\\", "/", dirname(__FILE__)) . "/cacert.pem";
			}

			if (isset($options[$key]["auto_cn_match"]))
			{
				unset($options[$key]["auto_cn_match"]);

				$options[$key]["CN_match"] = $host;
			}

			if (isset($options[$key]["auto_sni"]))
			{
				unset($options[$key]["auto_sni"]);

				$options[$key]["SNI_enabled"] = true;
				$options[$key]["SNI_server_name"] = $host;
			}
		}

		public function Connect($username, $password, $options = array())
		{
			if ($this->fp !== false)  $this->Disconnect();

			$server = trim(isset($options["server"]) ? $options["server"] : "localhost");
			if ($server == "")  return array("success" => false, "error" => self::POP3_Translate("Invalid server specified."), "errorcode" => "invalid_server");
			$secure = (isset($options["secure"]) ? $options["secure"] : false);
			$port = (isset($options["port"]) ? (int)$options["port"] : -1);
			if ($port < 0 || $port > 65535)  $port = ($secure ? 995 : 110);

			$this->debug = (isset($options["debug"]) ? $options["debug"] : false);
			$errornum = 0;
			$errorstr = "";
			if (!isset($options["connecttimeout"]))  $options["connecttimeout"] = 10;
			if (!function_exists("stream_socket_client"))  $this->fp = @fsockopen(($secure ? "tls://" : "") . $server, $port, $errornum, $errorstr, $options["connecttimeout"]);
			else
			{
				$context = @stream_context_create();
				if ($secure && isset($options["sslopts"]) && is_array($options["sslopts"]))
				{
					self::ProcessSSLOptions($options, "sslopts", $server);
					foreach ($options["sslopts"] as $key => $val)  @stream_context_set_option($context, "ssl", $key, $val);
				}
				$this->fp = @stream_socket_client(($secure ? "tls://" : "") . $server . ":" . $port, $errornum, $errorstr, $options["connecttimeout"], STREAM_CLIENT_CONNECT, $context);

				$contextopts = stream_context_get_options($context);
				if ($secure && isset($options["sslopts"]) && is_array($options["sslopts"]) && isset($contextopts["ssl"]["peer_certificate"]))
				{
					if (isset($options["debug_callback"]))  $options["debug_callback"]("peercert", @openssl_x509_parse($contextopts["ssl"]["peer_certificate"]), $options["debug_callback_opts"]);
				}
			}
			if ($this->fp === false)  return array("success" => false, "error" => self::POP3_Translate("Unable to establish a POP3 connection to '%s'.", ($secure ? "tls://" : "") . $server . ":" . $port), "errorcode" => "connection_failure", "info" => $errorstr . " (" . $errornum . ")");

			// Get the initial connection data.
			$result = $this->GetPOP3Response(false);
			if (!$result["success"])  return array("success" => false, "error" => self::POP3_Translate("Unable to get initial POP3 data."), "errorcode" => "no_response", "info" => $result);
			$rawrecv = $result["rawrecv"];
			$rawsend = "";

			// Extract APOP information (if any).
			$pos = strpos($result["response"], "<");
			$pos2 = strpos($result["response"], ">", (int)$pos);
			if ($pos !== false && $pos2 !== false)  $apop = substr($result["response"], $pos, $pos2 - $pos + 1);
			else  $apop = "";

//			// Determine authentication capabilities.
//			fwrite($this->fp, "CAPA\r\n");

			if ($apop != "" && (!isset($options["use_apop"]) || $options["use_apop"]))
			{
				$result = $this->POP3Request("APOP " . $username . " " . md5($apop . $password), $rawsend, $rawrecv);
				if (!$result["success"])  return array("success" => false, "error" => self::POP3_Translate("The POP3 login request failed (APOP failed)."), "errorcode" => "invalid_response", "info" => $result);
			}
			else
			{
				$result = $this->POP3Request("USER " . $username, $rawsend, $rawrecv);
				if (!$result["success"])  return array("success" => false, "error" => self::POP3_Translate("The POP3 login username is invalid (USER failed)."), "errorcode" => "invalid_response", "info" => $result);

				$result = $this->POP3Request("PASS " . $password, $rawsend, $rawrecv);
				if (!$result["success"])  return array("success" => false, "error" => self::POP3_Translate("The POP3 login password is invalid (PASS failed)."), "errorcode" => "invalid_response", "info" => $result);
			}

			return array("success" => true, "rawsend" => $rawsend, "rawrecv" => $rawrecv);
		}

		public function GetMessageList()
		{
			$rawrecv = "";
			$rawsend = "";
			$result = $this->POP3Request("LIST", $rawsend, $rawrecv, true);
			if (!$result["success"])  return array("success" => false, "error" => self::POP3_Translate("The message list request failed (LIST failed)."), "errorcode" => "invalid_response", "info" => $result);

			$ids = array();
			foreach ($result["data"] as $data)
			{
				$data = explode(" ", $data);
				if (count($data) > 1)  $ids[(int)$data[0]] = (int)$data[1];
			}

			return array("success" => true, "ids" => $ids, "rawsend" => $rawsend, "rawrecv" => $rawrecv);
		}

		public function GetMessage($id)
		{
			$rawrecv = "";
			$rawsend = "";
			$result = $this->POP3Request("RETR " . (int)$id, $rawsend, $rawrecv, true);
			if (!$result["success"])  return array("success" => false, "error" => self::POP3_Translate("The message retrieval request failed (RETR %d failed).", (int)$id), "errorcode" => "invalid_response", "info" => $result);

			return array("success" => true, "message" => implode("\r\n", $result["data"]) . "\r\n", "rawsend" => $rawsend, "rawrecv" => $rawrecv);
		}

		public function DeleteMessage($id)
		{
			$rawrecv = "";
			$rawsend = "";
			$result = $this->POP3Request("DELE " . (int)$id, $rawsend, $rawrecv);
			if (!$result["success"])  return array("success" => false, "error" => self::POP3_Translate("The message deletion request failed (DELE %d failed).", (int)$id), "errorcode" => "invalid_response", "info" => $result);

			return array("success" => true, "rawsend" => $rawsend, "rawrecv" => $rawrecv);
		}

		public function Disconnect()
		{
			if ($this->fp === false)  return true;

			$rawrecv = "";
			$rawsend = "";
			$this->POP3Request("QUIT", $rawsend, $rawrecv);

			fclose($this->fp);
			$this->fp = false;

			return true;
		}

		private function POP3Request($command, &$rawsend, &$rawrecv, $multiline = false)
		{
			if ($this->fp === false)  return array("success" => false, "error" => self::POP3_Translate("Not connected to a POP3 server."), "errorcode" => "not_connected");

			fwrite($this->fp, $command . "\r\n");
			if ($this->debug)  $rawsend .= $command . "\r\n";

			$result = $this->GetPOP3Response($multiline);
			if ($this->debug)  $rawrecv .= $result["rawrecv"];

			return $result;
		}

		private function GetPOP3Response($multiline)
		{
			$rawrecv = "";
			$currline = fgets($this->fp);
			if ($currline === false)  return array("success" => false, "error" => self::POP3_Translate("Connection terminated."), "errorcode" => "connection_terminated", "rawrecv" => $rawrecv);
			if ($this->debug)  $rawrecv .= $currline;
			$currline = rtrim($currline);
			if (strtoupper(substr($currline, 0, 5)) == "-ERR ")
			{
				$data = substr($currline, 5);
				return array("success" => false, "error" => self::POP3_Translate("POP3 server returned an error."), "errorcode" => "error_response", "info" => $data, "rawrecv" => $rawrecv);
			}

			$response = substr($currline, 4);
			if (feof($this->fp))  return array("success" => false, "error" => self::POP3_Translate("Connection terminated."), "errorcode" => "connection_terminated", "info" => $response, "rawrecv" => $rawrecv);
			$data = array();
			if ($multiline)
			{
				do
				{
					$currline = fgets($this->fp);
					if ($currline === false)  return array("success" => false, "error" => self::POP3_Translate("Connection terminated."), "errorcode" => "connection_terminated", "rawrecv" => $rawrecv);
					if ($this->debug)  $rawrecv .= $currline;
					$currline = rtrim($currline);
					if ($currline == ".")  break;
					if ($currline == "..")  $currline = ".";
					$data[] = $currline;
				} while (!feof($this->fp));
			}

			if (feof($this->fp))  return array("success" => false, "error" => self::POP3_Translate("Connection terminated."), "errorcode" => "connection_terminated", "info" => $response, "rawrecv" => $rawrecv);

			return array("success" => true, "response" => $response, "data" => $data, "rawrecv" => $rawrecv);
		}

		private static function POP3_Translate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}
	}
?>
