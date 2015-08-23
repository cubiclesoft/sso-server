<?php
	// CubicleSoft Basic PHP String helper/processing functions.
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	class Str
	{
		private static function ProcPOSTStr($data)
		{
			$data = trim($data);
			if (get_magic_quotes_gpc())  $data = stripslashes($data);

			return $data;
		}

		private static function ProcessSingleInput($data)
		{
			foreach ($data as $key => $val)
			{
				if (is_string($val))  $_REQUEST[$key] = self::ProcPOSTStr($val);
				else if (is_array($val))
				{
					$_REQUEST[$key] = array();
					foreach ($val as $key2 => $val2)  $_REQUEST[$key][$key2] = self::ProcPOSTStr($val2);
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
			return preg_replace('/[_]+/', "_", preg_replace('/[^A-Za-z0-9_.\-]/', "_", $filename));
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
	}
?>
