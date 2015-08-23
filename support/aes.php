<?php
	// AES packetization class.  Requires 'mcrypt' OR phpseclib AES.php + Rijndael.php
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	class ExtendedAES
	{
		static private $mcryptavailable;

		// Mcrypt-specific routines for faster encryption and decryption.
		static function IsMcryptAvailable()
		{
			if (!is_bool(self::$mcryptavailable))  self::$mcryptavailable = (function_exists("mcrypt_module_open") && in_array("rijndael-128", mcrypt_list_algorithms()));

			return self::$mcryptavailable;
		}

		static function McryptEncrypt($data, $key, $options = array())
		{
			if (!self::IsMcryptAvailable())  return false;

			if (!isset($options["mode"]))  $options["mode"] = "ECB";
			switch ($options["mode"])
			{
				case "ECB":  $options["mode"] = MCRYPT_MODE_ECB;  break;
				case "CBC":  $options["mode"] = MCRYPT_MODE_CBC;  break;
				default:  $options["mode"] = strtolower($options["mode"]);  break;
			}
			$mp = mcrypt_module_open(MCRYPT_RIJNDAEL_128, "", $options["mode"], "");
			if ($options["mode"] != MCRYPT_MODE_ECB && (!isset($options["iv"]) || $options["iv"] == ""))  $options["iv"] = mcrypt_create_iv(mcrypt_enc_get_iv_size($mp), (PHP_SHLIB_SUFFIX == "dll" ? MCRYPT_RAND : MCRYPT_DEV_URANDOM));
			else if (!isset($options["iv"]))  $options["iv"] = str_repeat("\x00", mcrypt_enc_get_iv_size($mp));
			$options["iv"] = substr($options["iv"], 0, mcrypt_enc_get_iv_size($mp));
			$key = substr($key, 0, mcrypt_enc_get_key_size($mp));
			$data = (string)$data;

			mcrypt_generic_init($mp, $key, $options["iv"]);
			if ($data != "")  $data = mcrypt_generic($mp, $data);
			mcrypt_generic_deinit($mp);
			mcrypt_module_close($mp);

			return $data;
		}

		static function McryptDecrypt($data, $key, $options = array())
		{
			if (!self::IsMcryptAvailable())  return false;

			if (!isset($options["mode"]))  $options["mode"] = "ECB";
			switch ($options["mode"])
			{
				case "ECB":  $options["mode"] = MCRYPT_MODE_ECB;  break;
				case "CBC":  $options["mode"] = MCRYPT_MODE_CBC;  break;
				default:  $options["mode"] = strtolower($options["mode"]);  break;
			}
			if ($options["mode"] != MCRYPT_MODE_ECB && (!isset($options["iv"]) || $options["iv"] == ""))  return false;
			$mp = mcrypt_module_open(MCRYPT_RIJNDAEL_128, "", $options["mode"], "");
			if (!isset($options["iv"]))  $options["iv"] = str_repeat("\x00", mcrypt_enc_get_iv_size($mp));
			$options["iv"] = substr($options["iv"], 0, mcrypt_enc_get_iv_size($mp));
			$key = substr($key, 0, mcrypt_enc_get_key_size($mp));
			$data = (string)$data;

			mcrypt_generic_init($mp, $key, $options["iv"]);
			if ($data != "")  $data = mdecrypt_generic($mp, $data);
			mcrypt_generic_deinit($mp);
			mcrypt_module_close($mp);

			return $data;
		}

		// Uses AES to create an encapsulated data packet.  Does not support streams.
		static function CreateDataPacket($data, $key, $options = array())
		{
			$data = (string)$data;
			if (!isset($options["prefix"]))  $options["prefix"] = uniqid(mt_rand(), true);
			$options["prefix"] = strtolower(dechex(crc32($options["prefix"])));
			if (!isset($options["lightweight"]) || !$options["lightweight"])  $data = $options["prefix"] . "\n" . strtolower(sha1($data)) . "\n" . $data . "\n";
			else  $data = $options["prefix"] . "\n" . strtolower(dechex(crc32($data))) . "\n" . $data . "\n";

			if (self::IsMcryptAvailable())  $data = self::McryptEncrypt($data, $key, $options);
			else if (class_exists("Crypt_AES"))
			{
				if (!isset($options["mode"]))  $options["mode"] = "ECB";
				if (!isset($options["iv"]))  $options["iv"] = str_repeat("\x00", 16);

				$aes = new Crypt_AES($options["mode"] == "CBC" ? CRYPT_AES_MODE_CBC : CRYPT_AES_MODE_ECB);
				$aes->setKey($key);
				if (isset($options["iv"]))  $aes->setIV($options["iv"]);
				$aes->disablePadding();
				if (strlen($data) % 16 != 0)  $data = str_pad($data, strlen($data) + (16 - (strlen($data) % 16)), "\x00");
				$data = $aes->encrypt($data);
			}
			else  return false;

			if (isset($options["key2"]))
			{
				$data = substr($data, -1) . substr($data, 0, -1);

				if (isset($options["iv2"]))  $options["iv"] = $options["iv2"];
				else  unset($options["iv"]);

				if (self::IsMcryptAvailable())  $data = self::McryptEncrypt($data, $options["key2"], $options);
				else if (class_exists("Crypt_AES"))
				{
					if ($options["mode"] != "ECB" && (!isset($options["iv"]) || $options["iv"] == ""))  return false;

					$aes->setKey($options["key2"]);
					if (isset($options["iv"]))  $aes->setIV($options["iv"]);
					$data = $aes->encrypt($data);
				}
			}

			return $data;
		}

		// Uses AES to extract the data from an encapsulated data packet and validates the data.  Does not support streams.
		static function ExtractDataPacket($data, $key, $options = array())
		{
			$data = (string)$data;

			if (!isset($options["mode"]))  $options["mode"] = "ECB";
			if ($options["mode"] != "ECB" && (!isset($options["iv"]) || $options["iv"] == ""))  return false;

			if (isset($options["key2"]))
			{
				$options2 = $options;
				if (isset($options["iv2"]))  $options["iv"] = $options["iv2"];
				else  unset($options["iv"]);

				if (self::IsMcryptAvailable())  $data = self::McryptDecrypt($data, $options["key2"], $options);
				else if (class_exists("Crypt_AES"))
				{
					$aes = new Crypt_AES($options["mode"] == "CBC" ? CRYPT_AES_MODE_CBC : CRYPT_AES_MODE_ECB);
					$aes->setKey($options["key2"]);
					if (isset($options["iv"]))  $aes->setIV($options["iv"]);
					$aes->disablePadding();
					$data = $aes->decrypt($data);
				}
				else  return false;

				$data = substr($data, 1) . substr($data, 0, 1);
				$options = $options2;
			}

			if (self::IsMcryptAvailable())  $data = self::McryptDecrypt($data, $key, $options);
			else if (class_exists("Crypt_AES"))
			{
				$aes = new Crypt_AES($options["mode"] == "CBC" ? CRYPT_AES_MODE_CBC : CRYPT_AES_MODE_ECB);
				$aes->setKey($key);
				if (isset($options["iv"]))  $aes->setIV($options["iv"]);
				$aes->disablePadding();
				$data = $aes->decrypt($data);
			}
			else  return false;

			if ($data === false)  return false;

			$pos = strpos($data, "\n");
			if ($pos === false)  return false;
			$data = substr($data, $pos + 1);

			$pos = strpos($data, "\n");
			if ($pos === false)  return false;
			$check = substr($data, 0, $pos);
			$data = substr($data, $pos + 1);

			$pos = strrpos($data, "\n");
			if ($pos === false)  return false;
			$data = substr($data, 0, $pos);

			if (!isset($options["lightweight"]) || !$options["lightweight"])
			{
				if ($check !== strtolower(sha1($data)))  return false;
			}
			else if ($check !== strtolower(dechex(crc32($data))))  return false;

			return $data;
		}
	}
?>