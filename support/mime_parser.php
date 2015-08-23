<?php
	// CubicleSoft PHP MIME Parser class.
	// (C) 2014 CubicleSoft.  All Rights Reserved.

	// Load dependencies.
	if (!class_exists("UTF8"))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/utf8.php";

	class MIMEParser
	{
		// Reduce dependencies.  Duplicates code though.
		private static function ReplaceNewlines($replacewith, $data)
		{
			$data = str_replace("\r\n", "\n", $data);
			$data = str_replace("\r", "\n", $data);
			$data = str_replace("\n", $replacewith, $data);

			return $data;
		}

		// RFC1341 is a hacky workaround to allow 8-bit over 7-bit transport.
		// Also known as "Quoted Printable".
		public static function ConvertFromRFC1341($data)
		{
			$result = "";

			$data = self::ReplaceNewlines("\r\n", $data);
			$data = explode("\r\n", $data);
			$y = count($data);
			for ($x = 0; $x < $y; $x++)
			{
				$currline = $data[$x];
				do
				{
					$pos = strpos($currline, "=");
					if ($pos !== false)
					{
						if ($pos == strlen($currline) - 1)
						{
							$result .= substr($currline, 0, $pos);
							$x++;
							$currline = $data[$x];
						}
						else if ($pos <= strlen($currline) - 3)
						{
							$result .= substr($currline, 0, $pos) . chr(hexdec(substr($currline, $pos + 1, 2)));
							$currline = substr($currline, $pos + 3);
						}
						else
						{
							$x++;
							$currline = $data[$x];
						}
					}
					else
					{
						$result .= $currline;
						$currline = "";
					}
				} while ($currline != "");

				$result .= "\r\n";
			}

			$result = rtrim($result) . "\r\n";

			return $result;
		}

		// RFC1342 is a hacky workaround to encode headers in e-mails.
		// This function decodes RFC1342 to UTF-8.
		public static function ConvertFromRFC1342($data)
		{
			$pos = strpos($data, "=?");
			$pos2 = ($pos !== false ? strpos($data, "?", (int)$pos + 2) : false);
			$pos3 = ($pos2 !== false ? strpos($data, "?", (int)$pos2 + 1) : false);
			$pos4 = ($pos3 !== false ? strpos($data, "?=", (int)$pos3 + 1) : false);
			while ($pos !== false && $pos2 !== false && $pos3 !== false && $pos4 !== false)
			{
				$encoding = strtoupper(substr($data, $pos + 2, $pos2 - $pos - 2));
				$type = strtoupper(substr($data, $pos2 + 1, $pos3 - $pos2 - 1));
				$data2 = substr($data, $pos3 + 1, $pos4 - $pos3 - 1);
				if ($type != "B" && $type != "Q")  $data2 = "";
				else
				{
					if ($type == "B")  $data2 = base64_decode($data2);
					else  $data2 = self::ConvertFromRFC1341($data2);

					$data3 = self::ConvertCharset($data2, $encoding, "UTF-8");
					if ($data3 !== false)  $data2 = $data3;

					$data2 = UTF8::MakeValid(trim($data2));
				}
				$data = substr($data, 0, $pos) . $data2 . substr($data, $pos4 + 2);

				$pos = strpos($data, "=?");
				$pos2 = ($pos !== false ? strpos($data, "?", (int)$pos + 2) : false);
				$pos3 = ($pos2 !== false ? strpos($data, "?", (int)$pos2 + 1) : false);
				$pos4 = ($pos3 !== false ? strpos($data, "?=", (int)$pos3 + 1) : false);
			}

			return $data;
		}

		// Convert between character sets (mostly UTF-8, ISO-8859-1, and ASCII) using PHP's functions.
		// Returns false on failure.
		public static function ConvertCharset($data, $incharset, $outcharset)
		{
			$result = false;

			$incharset = strtoupper($incharset);
			$outcharset = strtoupper($outcharset);
			if ($incharset == $outcharset)  return $data;

			if ($incharset == "UTF-8")  $data = UTF8::MakeValid($data);
			if (function_exists("iconv"))
			{
				// Try transliteration, regular, and then ignore in that order.
				$result = iconv($incharset, $outcharset . "//TRANSLIT", $data);
				if ($result === false)  $result = iconv($incharset, $outcharset, $data);
				if ($result === false)  $result = iconv($incharset, $outcharset . "//IGNORE", $data);
			}
			if ($result === false && function_exists("mb_convert_encoding"))
			{
				$result = @mb_convert_encoding($data, $outcharset, $incharset);
				if ($data != "" && $result == "")  $result = false;
			}
			if ($result === false)
			{
				if ($incharset == "ISO-8859-1" && $outcharset == "UTF-8")  $result = utf8_encode($data);
				else if ($incharset == "UTF-8" && $outcharset == "ISO-8859-1")  $result = utf8_decode($data);
				if ($data != "" && $result == "")  $result = false;
			}

			return $result;
		}

		public static function ExplodeHeader($data)
		{
			$result = array();
			$data = trim($data);
			while ($data != "")
			{
				// Extract name/value pair.
				$pos = strpos($data, "=");
				$pos2 = strpos($data, ";");
				if (($pos !== false && $pos2 === false) || ($pos !== false && $pos2 !== false && $pos < $pos2))
				{
					$name = trim(substr($data, 0, $pos));
					$data = trim(substr($data, $pos + 1));
					if (ord($data[0]) == ord("\""))
					{
						$pos = strpos($data, "\"", 1);
						if ($pos !== false)
						{
							$value = substr($data, 1, $pos - 1);
							$data = trim(substr($data, $pos + 1));
							$pos = strpos($data, ";");
							if ($pos !== false)  $data = substr($data, $pos + 1);
							else  $data = "";
						}
						else
						{
							$value = $data;
							$data = "";
						}
					}
					else
					{
						$pos = strpos($data, ";");
						if ($pos !== false)
						{
							$value = trim(substr($data, 0, $pos));
							$data = substr($data, $pos + 1);
						}
						else
						{
							$value = $data;
							$data = "";
						}
					}
				}
				else if ($pos2 !== false)
				{
					$name = "";
					$value = trim(substr($data, 0, $pos2));
					$data = substr($data, $pos2 + 1);
				}
				else
				{
					$name = "";
					$value = $data;
					$data = "";
				}

				if ($name != "" || $value != "")  $result[strtolower($name)] = $value;

				$data = trim($data);
			}

			return $result;
		}

		public static function Parse($data, $depth = 0)
		{
			$result = array();

			if ($depth == 10)  return $result;

			// Extract headers.
			$space = ord(" ");
			$tab = ord("\t");
			$data = self::ReplaceNewlines("\r\n", $data);
			$data = explode("\r\n", $data);
			$y = count($data);
			$lastheader = "";
			for ($x = 0; $x < $y; $x++)
			{
				$currline = rtrim($data[$x]);
				if ($currline == "")  break;
				$TempChr = ord($currline[0]);
				if ($TempChr == $space || $TempChr == $tab)
				{
					if ($lastheader != "")  $result["headers"][$lastheader] .= " " . self::ConvertFromRFC1342(ltrim($currline));
				}
				else
				{
					$pos = strpos($currline, ":");
					if ($pos !== false)
					{
						$lastheader = strtolower(substr($currline, 0, $pos));
						$result["headers"][$lastheader] = self::ConvertFromRFC1342(ltrim(substr($currline, $pos + 1)));
					}
				}
			}

			// Extract body.
			$data = implode("\r\n", array_slice($data, $x + 1));
			if (isset($result["headers"]["content-transfer-encoding"]))
			{
				$encoding = self::ExplodeHeader($result["headers"]["content-transfer-encoding"]);
				if (isset($encoding[""]))
				{
					if ($encoding[""] == "base64")  $data = base64_decode(preg_replace("/\\s/", "", $data));
					else if ($encoding[""] == "quoted-printable")  $data = self::ConvertFromRFC1341($data);
				}
			}

			// Process body for more MIME content.
			if (!isset($result["headers"]["content-type"]))
			{
				$result["body"] = UTF8::MakeValid($data);
				$result["mime"] = array();
			}
			else
			{
				$contenttype = self::ExplodeHeader($result["headers"]["content-type"]);
				if (array_key_exists("charset", $contenttype))
				{
					$data2 = self::ConvertCharset($data, $contenttype["charset"], "UTF-8");
					if ($data2 !== false)  $data = $data2;

					$data = UTF8::MakeValid($data);
				}

				if (!isset($contenttype["boundary"]))
				{
					$result["body"] = $data;
					$result["mime"] = array();
				}
				else
				{
					$pos = strpos($data, "--" . $contenttype["boundary"]);
					if ($pos !== false && !$pos)  $data = "\r\n" . $data;
					$data = explode("\r\n--" . $contenttype["boundary"], $data);
					$result["body"] = UTF8::MakeValid($data[0]);
					$result["mime"] = array();
					$y = count($data);
					for ($x = 1; $x < $y; $x++)
					{
						if (substr($data[$x], 0, 2) != "--")  $result["mime"][$x - 1] = self::Parse(ltrim($data[$x]), $depth + 1);
						else  break;
					}
				}
			}

			return $result;
		}

		public static function ExtractContent($message, $depth = 0)
		{
			$result = array();

			if ($depth == 10)  return $result;

			if (!$depth)  $result["text/plain"] = $message["body"];

			if (!isset($message["headers"]["content-type"]))  $result["text/plain"] = $message["body"];
			else
			{
				$contenttype = self::ExplodeHeader($message["headers"]["content-type"]);
				if (!isset($contenttype[""]))  $result["text/plain"] = $message["body"];
				else if (strtolower($contenttype[""]) == "text/plain")  $result["text/plain"] = $message["body"];
				else if (strtolower($contenttype[""]) == "text/html")  $result["text/html"] = $message["body"];

				$y = count($message["mime"]);
				for ($x = 0; $x < $y; $x++)
				{
					$data = self::ExtractContent($message["mime"][$x], $depth + 1);
					if (isset($data["text/plain"]))  $result["text/plain"] = $data["text/plain"];
					if (isset($data["text/html"]))  $result["text/html"] = $data["text/html"];
				}
			}

			return $result;
		}
	}
?>