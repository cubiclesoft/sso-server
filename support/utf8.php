<?php
	// CubicleSoft PHP UTF8 (Unicode) functions.
	// (C) 2021 CubicleSoft.  All Rights Reserved.

	class UTF8
	{
		// Removes invalid characters from the data string.
		// http://www.w3.org/International/questions/qa-forms-utf-8
		public static function MakeValidStream(&$prefix, &$data, $open)
		{
			if ($prefix !== "")  $data = $prefix . $data;

			$result = "";
			$x = 0;
			$y = strlen($data);
			while ($x < $y)
			{
				$tempchr = ord($data[$x]);
				if (($tempchr >= 0x20 && $tempchr <= 0x7E) || $tempchr == 0x09 || $tempchr == 0x0A || $tempchr == 0x0D)
				{
					// ASCII minus control and special characters.
					$result .= chr($tempchr);
					$x++;
				}
				else
				{
					if ($y - $x > 1)  $tempchr2 = ord($data[$x + 1]);
					else  $tempchr2 = 0x00;
					if ($y - $x > 2)  $tempchr3 = ord($data[$x + 2]);
					else  $tempchr3 = 0x00;
					if ($y - $x > 3)  $tempchr4 = ord($data[$x + 3]);
					else  $tempchr4 = 0x00;

					if (($tempchr >= 0xC2 && $tempchr <= 0xDF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF))
					{
						// Non-overlong (2 bytes).
						$result .= chr($tempchr);
						$result .= chr($tempchr2);
						$x += 2;
					}
					else if ($tempchr == 0xE0 && ($tempchr2 >= 0xA0 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF))
					{
						// Non-overlong (3 bytes).
						$result .= chr($tempchr);
						$result .= chr($tempchr2);
						$result .= chr($tempchr3);
						$x += 3;
					}
					else if ((($tempchr >= 0xE1 && $tempchr <= 0xEC) || $tempchr == 0xEE || $tempchr == 0xEF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF))
					{
						// Normal/straight (3 bytes).
						$result .= chr($tempchr);
						$result .= chr($tempchr2);
						$result .= chr($tempchr3);
						$x += 3;
					}
					else if ($tempchr == 0xED && ($tempchr2 >= 0x80 && $tempchr2 <= 0x9F) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF))
					{
						// Non-surrogates (3 bytes).
						$result .= chr($tempchr);
						$result .= chr($tempchr2);
						$result .= chr($tempchr3);
						$x += 3;
					}
					else if ($tempchr == 0xF0 && ($tempchr2 >= 0x90 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF) && ($tempchr4 >= 0x80 && $tempchr4 <= 0xBF))
					{
						// Planes 1-3 (4 bytes).
						$result .= chr($tempchr);
						$result .= chr($tempchr2);
						$result .= chr($tempchr3);
						$result .= chr($tempchr4);
						$x += 4;
					}
					else if (($tempchr >= 0xF1 && $tempchr <= 0xF3) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF) && ($tempchr4 >= 0x80 && $tempchr4 <= 0xBF))
					{
						// Planes 4-15 (4 bytes).
						$result .= chr($tempchr);
						$result .= chr($tempchr2);
						$result .= chr($tempchr3);
						$result .= chr($tempchr4);
						$x += 4;
					}
					else if ($tempchr == 0xF4 && ($tempchr2 >= 0x80 && $tempchr2 <= 0x8F) && ($tempchr3 >= 0x80 && $tempchr3 <= 0xBF) && ($tempchr4 >= 0x80 && $tempchr4 <= 0xBF))
					{
						// Plane 16 (4 bytes).
						$result .= chr($tempchr);
						$result .= chr($tempchr2);
						$result .= chr($tempchr3);
						$result .= chr($tempchr4);
						$x += 4;
					}
					else if ($open && $x + 4 > $y)  break;
					else  $x++;
				}
			}

			$prefix = substr($data, $x);

			return $result;
		}

		public static function MakeValid($data)
		{
			$prefix = "";

			if (!is_string($data))  $data = (string)$data;

			return self::MakeValidStream($prefix, $data, false);
		}

		public static function IsValid($data)
		{
			$x = 0;
			$y = strlen($data);
			while ($x < $y)
			{
				$tempchr = ord($data[$x]);
				if (($tempchr >= 0x20 && $tempchr <= 0x7E) || $tempchr == 0x09 || $tempchr == 0x0A || $tempchr == 0x0D)  $x++;
				else if ($tempchr < 0xC2)  return false;
				else
				{
					$left = $y - $x;
					if ($left > 1)  $tempchr2 = ord($data[$x + 1]);
					else  return false;

					if (($tempchr >= 0xC2 && $tempchr <= 0xDF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF))  $x += 2;
					else
					{
						if ($left > 2)  $tempchr3 = ord($data[$x + 2]);
						else  return false;

						if ($tempchr3 < 0x80 || $tempchr3 > 0xBF)  return false;

						if ($tempchr == 0xE0 && ($tempchr2 >= 0xA0 && $tempchr2 <= 0xBF))  $x += 3;
						else if ((($tempchr >= 0xE1 && $tempchr <= 0xEC) || $tempchr == 0xEE || $tempchr == 0xEF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF))  $x += 3;
						else if ($tempchr == 0xED && ($tempchr2 >= 0x80 && $tempchr2 <= 0x9F))  $x += 3;
						else
						{
							if ($left > 3)  $tempchr4 = ord($data[$x + 3]);
							else  return false;

							if ($tempchr4 < 0x80 || $tempchr4 > 0xBF)  return false;

							if ($tempchr == 0xF0 && ($tempchr2 >= 0x90 && $tempchr2 <= 0xBF))  $x += 4;
							else if (($tempchr >= 0xF1 && $tempchr <= 0xF3) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF))  $x += 4;
							else if ($tempchr == 0xF4 && ($tempchr2 >= 0x80 && $tempchr2 <= 0x8F))  $x += 4;
							else  return false;
						}
					}
				}
			}

			return true;
		}

		// Locates the next UTF8 character in a UTF8 string.
		// Set Pos and Size to 0 to start at the beginning.
		// Returns false at the end of the string or bad UTF8 character.  Otherwise, returns true.
		public static function NextChrPos(&$data, $datalen, &$pos, &$size)
		{
			$pos += $size;
			$size = 0;
			$x = $pos;
			$y = $datalen;
			if ($x >= $y)  return false;

			$tempchr = ord($data[$x]);
			if (($tempchr >= 0x20 && $tempchr <= 0x7E) || $tempchr == 0x09 || $tempchr == 0x0A || $tempchr == 0x0D)  $size = 1;
			else if ($tempchr < 0xC2)  return false;
			else
			{
				$left = $y - $x;
				if ($left > 1)  $tempchr2 = ord($data[$x + 1]);
				else  return false;

				if (($tempchr >= 0xC2 && $tempchr <= 0xDF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF))  $size = 2;
				else
				{
					if ($left > 2)  $tempchr3 = ord($data[$x + 2]);
					else  return false;

					if ($tempchr3 < 0x80 || $tempchr3 > 0xBF)  return false;

					if ($tempchr == 0xE0 && ($tempchr2 >= 0xA0 && $tempchr2 <= 0xBF))  $size = 3;
					else if ((($tempchr >= 0xE1 && $tempchr <= 0xEC) || $tempchr == 0xEE || $tempchr == 0xEF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF))  $size = 3;
					else if ($tempchr == 0xED && ($tempchr2 >= 0x80 && $tempchr2 <= 0x9F))  $size = 3;
					else
					{
						if ($left > 3)  $tempchr4 = ord($data[$x + 3]);
						else  return false;

						if ($tempchr4 < 0x80 || $tempchr4 > 0xBF)  return false;

						if ($tempchr == 0xF0 && ($tempchr2 >= 0x90 && $tempchr2 <= 0xBF))  $size = 4;
						else if (($tempchr >= 0xF1 && $tempchr <= 0xF3) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF))  $size = 4;
						else if ($tempchr == 0xF4 && ($tempchr2 >= 0x80 && $tempchr2 <= 0x8F))  $size = 4;
						else  return false;
					}
				}
			}

			return true;
		}

		// Converts a numeric value to a UTF8 character (code point).
		public static function chr($num)
		{
			if ($num < 0 || ($num >= 0xD800 && $num <= 0xDFFF) || ($num >= 0xFDD0 && $num <= 0xFDEF) || ($num & 0xFFFE) == 0xFFFE)  return "";

			if ($num <= 0x7F)  $result = chr($num);
			else if ($num <= 0x7FF)  $result = chr(0xC0 | ($num >> 6)) . chr(0x80 | ($num & 0x3F));
			else if ($num <= 0xFFFF)  $result = chr(0xE0 | ($num >> 12)) . chr(0x80 | (($num >> 6) & 0x3F)) . chr(0x80 | ($num & 0x3F));
			else if ($num <= 0x10FFFF)  $result = chr(0xF0 | ($num >> 18)) . chr(0x80 | (($num >> 12) & 0x3F)) . chr(0x80 | (($num  >> 6) & 0x3F)) . chr(0x80 | ($num & 0x3F));
			else  $result = "";

			return $result;
		}

		public static function MakeChr($num)
		{
			return self::chr($num);
		}

		// Converts a UTF8 code point to a numeric value.
		public static function ord($str)
		{
			$tempchr = ord($str[0]);
			if ($tempchr <= 0x7F)  return $tempchr;
			else if ($tempchr < 0xC2)  return false;
			else
			{
				$y = strlen($str);
				if ($y > 1)  $tempchr2 = ord($str[1]);
				else  return false;

				if (($tempchr >= 0xC2 && $tempchr <= 0xDF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF))  return (($tempchr & 0x1F) << 6) | ($tempchr2 & 0x3F);
				else
				{
					if ($y > 2)  $tempchr3 = ord($str[2]);
					else  return false;

					if ($tempchr3 < 0x80 || $tempchr3 > 0xBF)  return false;

					if (($tempchr == 0xE0 && ($tempchr2 >= 0xA0 && $tempchr2 <= 0xBF)) || ((($tempchr >= 0xE1 && $tempchr <= 0xEC) || $tempchr == 0xEE || $tempchr == 0xEF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF)) || ($tempchr == 0xED && ($tempchr2 >= 0x80 && $tempchr2 <= 0x9F)))
					{
						return (($tempchr & 0x0F) << 12) | (($tempchr2 & 0x3F) << 6) | ($tempchr3 & 0x3F);
					}
					else
					{
						if ($y > 3)  $tempchr4 = ord($str[3]);
						else  return false;

						if ($tempchr4 < 0x80 || $tempchr4 > 0xBF)  return false;

						if (($tempchr == 0xF0 && ($tempchr2 >= 0x90 && $tempchr2 <= 0xBF)) || (($tempchr >= 0xF1 && $tempchr <= 0xF3) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF)) || ($tempchr == 0xF4 && ($tempchr2 >= 0x80 && $tempchr2 <= 0x8F)))
						{
							return (($tempchr & 0x07) << 18) | (($tempchr2 & 0x3F) << 12) | (($tempchr3 & 0x3F) << 6) | ($tempchr4 & 0x3F);
						}
					}
				}
			}

			return false;
		}

		// Checks a numeric value to see if it is a combining code point.
		public static function IsCombiningCodePoint($val)
		{
			return (($val >= 0x0300 && $val <= 0x036F) || ($val >= 0x1DC0 && $val <= 0x1DFF) || ($val >= 0x20D0 && $val <= 0x20FF) || ($val >= 0xFE20 && $val <= 0xFE2F));
		}

		// Determines if a UTF8 string can also be viewed as ASCII.
		public static function IsASCII($data)
		{
			$pos = 0;
			$size = 0;
			$y = strlen($data);
			while (self::NextChrPos($data, $y, $pos, $size) && $size == 1)  {}
			if ($pos < $y || $size > 1)  return false;

			return true;
		}

		// Returns the number of characters in a UTF8 string.
		public static function strlen($data)
		{
			$num = 0;
			$pos = 0;
			$size = 0;
			$y = strlen($data);
			while (self::NextChrPos($data, $y, $pos, $size))  $num++;

			return $num;
		}

		// Converts a UTF8 string to ASCII and drops bad UTF8 and non-ASCII characters in the process.
		public static function ConvertToASCII($data)
		{
			$result = "";

			$pos = 0;
			$size = 0;
			$y = strlen($data);
			while ($pos < $y)
			{
				if (self::NextChrPos($data, $y, $pos, $size) && $size == 1)  $result .= $data[$pos];
				else if (!$size)  $size = 1;
			}

			return $result;
		}

		// Converts UTF8 characters in a string to HTML entities.
		public static function ConvertToHTML($data)
		{
			return preg_replace_callback('/([\xC0-\xF7]{1,1}[\x80-\xBF]+)/', __CLASS__ . '::ConvertToHTML__Callback', $data);
		}

		protected static function ConvertToHTML__Callback($data)
		{
			$data = $data[1];
			$num = 0;
			$data = str_split(strrev(chr((ord(substr($data, 0, 1)) % 252 % 248 % 240 % 224 % 192) + 128) . substr($data, 1)));
			foreach ($data as $k => $v)  $num += (ord($v) % 128) * pow(64, $k);

			return "&#" . $num . ";";
		}
	}
?>