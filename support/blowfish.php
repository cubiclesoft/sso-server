<?php
	// Blowfish packetization and hashing class.  Requires phpseclib Blowfish.php
	// (C) 2017 CubicleSoft.  All Rights Reserved.

	class ExtendedBlowfish
	{
		// Uses Blowfish to create an encapsulated data packet.  Does not support streams.
		static function CreateDataPacket($data, $key, $options = array())
		{
			$data = (string)$data;
			if (!isset($options["prefix"]))  $options["prefix"] = uniqid(mt_rand(), true);
			$options["prefix"] = strtolower(dechex(crc32($options["prefix"])));
			if (!isset($options["lightweight"]) || !$options["lightweight"])  $data = $options["prefix"] . "\n" . strtolower(sha1($data)) . "\n" . $data . "\n";
			else  $data = $options["prefix"] . "\n" . strtolower(dechex(crc32($data))) . "\n" . $data . "\n";

			if (!class_exists("Crypt_Blowfish", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/phpseclib/Blowfish.php";

			if (!isset($options["mode"]))  $options["mode"] = "ECB";
			if ($options["mode"] == "CBC" && !isset($options["iv"]))  $options["iv"] = str_repeat("\x00", strlen($key));

			$bf = new Crypt_Blowfish($options["mode"] == "CBC" ? CRYPT_BLOWFISH_MODE_CBC : CRYPT_BLOWFISH_MODE_ECB);
			$bf->setKey($key);
			if (isset($options["iv"]))  $bf->setIV($options["iv"]);
			$bf->disablePadding();
			if (strlen($data) % 8 != 0)  $data = str_pad($data, strlen($data) + (8 - (strlen($data) % 8)), "\x00");
			$data = $bf->encrypt($data);

			if (isset($options["key2"]))
			{
				$data = substr($data, -1) . substr($data, 0, -1);

				if (isset($options["iv2"]))  $options["iv"] = $options["iv2"];
				else  unset($options["iv"]);

				if ($options["mode"] != "ECB" && (!isset($options["iv"]) || $options["iv"] == ""))  return false;

				$bf->setKey($options["key2"]);
				if (isset($options["iv"]))  $bf->setIV($options["iv"]);
				$data = $bf->encrypt($data);
			}

			return $data;
		}

		// Uses Blowfish to extract the data from an encapsulated data packet and validates the data.  Does not support streams.
		static function ExtractDataPacket($data, $key, $options = array())
		{
			$data = (string)$data;

			if (!isset($options["mode"]))  $options["mode"] = "ECB";
			if ($options["mode"] != "ECB" && (!isset($options["iv"]) || $options["iv"] == ""))  return false;

			if (!class_exists("Crypt_Blowfish", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/phpseclib/Blowfish.php";

			if (isset($options["key2"]))
			{
				$options2 = $options;
				if (isset($options["iv2"]))  $options["iv"] = $options["iv2"];
				else  unset($options["iv"]);

				$bf = new Crypt_Blowfish($options["mode"] == "CBC" ? CRYPT_BLOWFISH_MODE_CBC : CRYPT_BLOWFISH_MODE_ECB);
				$bf->setKey($options["key2"]);
				if (isset($options["iv"]))  $bf->setIV($options["iv"]);
				$bf->disablePadding();
				$data = $bf->decrypt($data);

				$data = substr($data, 1) . substr($data, 0, 1);
				$options = $options2;
			}

			$bf = new Crypt_Blowfish($options["mode"] == "CBC" ? CRYPT_BLOWFISH_MODE_CBC : CRYPT_BLOWFISH_MODE_ECB);
			$bf->setKey($key);
			if (isset($options["iv"]))  $bf->setIV($options["iv"]);
			$bf->disablePadding();
			$data = $bf->decrypt($data);

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

		// Uses Blowfish to create a hash of some data.  Typically used to securely hash passwords.
		// The recommended minimum number of rounds is 16.  Powers of two are preferred.
		// The recommended minimum amount of time is 250 (milliseconds).  Ignored when $mintime is 0.
		static function Hash($data, $rounds, $mintime)
		{
			$data = (string)$data;
			if ($data == "")  return array("success" => false, "error" => "No data.");

			// Expand data.
			$origdata = $data;
			while (strlen($data) < 56)  $data .= $origdata;
			$maxpos = strlen($data);
			$data .= $data;

			// Run through Blowfish.
			$result = "";
			for ($x = 0; $x < 32; $x++)  $result .= chr($x);
			$x = 0;
			$ts = microtime(true) + $mintime / 1000;
			$totalrounds = 0;

			$bf = new Crypt_Blowfish();
			$bf->disablePadding();

			while ($rounds > 0)
			{
				$key = substr($data, $x, 56);
				$x = ($x + 56) % $maxpos;

				$bf->setKey($key);
				$result = $bf->encrypt($result);

				$result = substr($result, -1) . substr($result, 0, -1);

				$rounds--;
				$totalrounds++;
				if (!$rounds && $mintime > 0 && microtime(true) < $ts)  $rounds++;
			}

			return array("success" => true, "hash" => $result, "rounds" => $totalrounds);
		}
	}
?>