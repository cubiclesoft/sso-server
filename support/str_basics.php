<?php
	// CubicleSoft Basic PHP String helper/processing functions.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	class Str
	{
		protected static function ProcessSingleInput($data)
		{
			foreach ($data as $key => $val)
			{
				if (is_string($val))  $_REQUEST[$key] = trim($val);
				else if (is_array($val))
				{
					$_REQUEST[$key] = array();
					foreach ($val as $key2 => $val2)  $_REQUEST[$key][$key2] = (is_string($val2) ? trim($val2) : $val2);
				}
				else  $_REQUEST[$key] = $val;
			}
		}

		// Cleans up all PHP input issues so that $_REQUEST may be used as expected.
		public static function ProcessAllInput()
		{
			self::ProcessSingleInput($_COOKIE);
			self::ProcessSingleInput($_GET);
			self::ProcessSingleInput($_POST);
		}

		public static function ExtractPathname($dirfile)
		{
			$dirfile = str_replace("\\", "/", $dirfile);
			$pos = strrpos($dirfile, "/");
			if ($pos === false)  $dirfile = "";
			else  $dirfile = substr($dirfile, 0, $pos + 1);

			return $dirfile;
		}

		public static function ExtractFilename($dirfile)
		{
			$dirfile = str_replace("\\", "/", $dirfile);
			$pos = strrpos($dirfile, "/");
			if ($pos !== false)  $dirfile = substr($dirfile, $pos + 1);

			return $dirfile;
		}

		public static function ExtractFileExtension($dirfile)
		{
			$dirfile = self::ExtractFilename($dirfile);
			$pos = strrpos($dirfile, ".");
			if ($pos !== false)  $dirfile = substr($dirfile, $pos + 1);
			else  $dirfile = "";

			return $dirfile;
		}

		public static function ExtractFilenameNoExtension($dirfile)
		{
			$dirfile = self::ExtractFilename($dirfile);
			$pos = strrpos($dirfile, ".");
			if ($pos !== false)  $dirfile = substr($dirfile, 0, $pos);

			return $dirfile;
		}

		// Makes an input filename safe for use.
		// Allows a very limited number of characters through.
		public static function FilenameSafe($filename)
		{
			return preg_replace('/\s+/', "-", trim(trim(preg_replace('/[^A-Za-z0-9_.\-]/', " ", $filename), ".")));
		}

		public static function ReplaceNewlines($replacewith, $data)
		{
			$data = str_replace("\r\n", "\n", $data);
			$data = str_replace("\r", "\n", $data);
			$data = str_replace("\n", $replacewith, $data);

			return $data;
		}

		public static function LineInput($data, &$pos)
		{
			$CR = ord("\r");
			$LF = ord("\n");

			$result = "";
			$y = strlen($data);
			if ($pos > $y)  $pos = $y;
			while ($pos < $y && ord($data[$pos]) != $CR && ord($data[$pos]) != $LF)
			{
				$result .= $data[$pos];
				$pos++;
			}
			if ($pos + 1 < $y && ord($data[$pos]) == $CR && ord($data[$pos + 1]) == $LF)  $pos++;
			if ($pos < $y)  $pos++;

			return $result;
		}

		// Constant-time string comparison.  Ported from CubicleSoft C++ code.
		public static function CTstrcmp($secret, $userinput)
		{
			$sx = 0;
			$sy = strlen($secret);
			$uy = strlen($userinput);
			$result = $sy - $uy;
			for ($ux = 0; $ux < $uy; $ux++)
			{
				$result |= ord($userinput[$ux]) ^ ord($secret[$sx]);
				$sx = ($sx + 1) % $sy;
			}

			return $result;
		}

		public static function ConvertUserStrToBytes($str)
		{
			$str = trim($str);
			$num = (double)$str;
			if (strtoupper(substr($str, -1)) == "B")  $str = substr($str, 0, -1);
			switch (strtoupper(substr($str, -1)))
			{
				case "P":  $num *= 1024;
				case "T":  $num *= 1024;
				case "G":  $num *= 1024;
				case "M":  $num *= 1024;
				case "K":  $num *= 1024;
			}

			return $num;
		}

		public static function ConvertBytesToUserStr($num)
		{
			$num = (double)$num;

			if ($num < 0)  return "0 B";
			if ($num < 1024)  return number_format($num, 0) . " B";
			if ($num < 1048576)  return str_replace(".0 ", "", number_format($num / 1024, 1)) . " KB";
			if ($num < 1073741824)  return str_replace(".0 ", "", number_format($num / 1048576, 1)) . " MB";
			if ($num < 1099511627776.0)  return str_replace(".0 ", "", number_format($num / 1073741824.0, 1)) . " GB";
			if ($num < 1125899906842624.0)  return str_replace(".0 ", "", number_format($num / 1099511627776.0, 1)) . " TB";

			return str_replace(".0 ", "", number_format($num / 1125899906842624.0, 1)) . " PB";
		}

		public static function JSSafe($data)
		{
			return str_replace(array("'", "\r", "\n"), array("\\'", "\\r", "\\n"), $data);
		}
	}
?>