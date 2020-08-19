<?php
	// Cryptographically Secure Pseudo-Random String Generator (CSPRSG) and CSPRNG.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	class CSPRNG
	{
		private static $alphanum = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
		private $mode, $fp, $cryptosafe;

		// Crypto-safe uses the best quality sources (e.g. /dev/random), but those sources can hang the application.
		// Will raise an exception if the constructor can't find a suitable source of randomness.
		public function __construct($cryptosafe = false)
		{
			$this->mode = false;
			$this->fp = false;
			$this->cryptosafe = $cryptosafe;

			// Native first (PHP 7 and later).
			if (function_exists("random_bytes"))  $this->mode = "native";

			// OpenSSL fallback.
			if ($this->mode === false && function_exists("openssl_random_pseudo_bytes"))
			{
				// PHP 5.4.0 introduced native Windows CryptGenRandom() integration via php_win32_get_random_bytes() for performance.
				@openssl_random_pseudo_bytes(4, $strong);
				if ($strong)  $this->mode = "openssl";
			}

			// Locate a (relatively) suitable source of entropy or raise an exception.
			if (strtoupper(substr(PHP_OS, 0, 3)) === "WIN")
			{
				// PHP 5.3.0 introduced native Windows CryptGenRandom() integration via php_win32_get_random_bytes() for functionality.
				if ($this->mode === false && PHP_VERSION_ID > 50300 && function_exists("mcrypt_create_iv"))  $this->mode = "mcrypt";
			}
			else
			{
				if (!$cryptosafe && $this->mode === false && file_exists("/dev/arandom"))
				{
					// OpenBSD.  mcrypt doesn't attempt to use this despite claims of higher quality entropy with performance.
					$this->fp = @fopen("/dev/arandom", "rb");
					if ($this->fp !== false)  $this->mode = "file";
				}

				if ($cryptosafe && $this->mode === false && file_exists("/dev/random"))
				{
					// Everything else.
					$this->fp = @fopen("/dev/random", "rb");
					if ($this->fp !== false)  $this->mode = "file";
				}

				if (!$cryptosafe && $this->mode === false && file_exists("/dev/urandom"))
				{
					// Everything else.
					$this->fp = @fopen("/dev/urandom", "rb");
					if ($this->fp !== false)  $this->mode = "file";
				}

				if ($this->mode === false && function_exists("mcrypt_create_iv"))
				{
					// mcrypt_create_iv() is last because it opens and closes a file handle every single call.
					$this->mode = "mcrypt";
				}
			}

			// Throw an exception if unable to find a suitable entropy source.
			if ($this->mode === false)
			{
				throw new Exception(self::RNG_Translate("Unable to locate a suitable entropy source."));
				exit();
			}
		}

		public function __destruct()
		{
			if ($this->mode === "file")  fclose($this->fp);
		}

		public function GetBytes($length)
		{
			if ($this->mode === false)  return false;

			$length = (int)$length;
			if ($length < 1)  return false;

			$result = "";
			do
			{
				switch ($this->mode)
				{
					case "native":  $data = @random_bytes($length);  break;
					case "openssl":  $data = @openssl_random_pseudo_bytes($length, $strong);  if (!$strong)  $data = false;  break;
					case "mcrypt":  $data = @mcrypt_create_iv($length, ($this->cryptosafe ? MCRYPT_DEV_RANDOM : MCRYPT_DEV_URANDOM));  break;
					case "file":  $data = @fread($this->fp, $length);  break;
					default:  $data = false;
				}
				if ($data === false)  return false;

				$result .= $data;
			} while (strlen($result) < $length);

			return substr($result, 0, $length);
		}

		public function GenerateToken($length = 64)
		{
			$data = $this->GetBytes($length);
			if ($data === false)  return false;

			return bin2hex($data);
		}

		// Get a random number between $min and $max (inclusive).
		public function GetInt($min, $max)
		{
			$min = (int)$min;
			$max = (int)$max;
			if ($max < $min)  return false;
			if ($min == $max)  return $min;

			$range = $max - $min + 1;

			$bits = 1;
			while ((1 << $bits) <= $range)  $bits++;

			$numbytes = (int)(($bits + 7) / 8);
			$mask = (1 << $bits) - 1;

			do
			{
				$data = $this->GetBytes($numbytes);
				if ($data === false)  return false;

				$result = 0;
				for ($x = 0; $x < $numbytes; $x++)
				{
					$result = ($result * 256) + ord($data[$x]);
				}

				$result = $result & $mask;
			} while ($result >= $range);

			return $result + $min;
		}

		// Convenience method to generate a random alphanumeric string.
		public function GenerateString($size = 32)
		{
			$result = "";
			for ($x = 0; $x < $size; $x++)
			{
				$data = $this->GetInt(0, 61);
				if ($data === false)  return false;

				$result .= self::$alphanum[$data];
			}

			return $result;
		}

		public function GenerateWordLite(&$freqmap, $len)
		{
			$totalc = 0;
			$totalv = 0;
			foreach ($freqmap["consonants"] as $chr => $num)  $totalc += $num;
			foreach ($freqmap["vowels"] as $chr => $num)  $totalv += $num;

			if ($totalc <= 0 || $totalv <= 0)  return false;

			$result = "";
			for ($x = 0; $x < $len; $x++)
			{
				if ($x % 2)
				{
					$data = $this->GetInt(0, $totalv - 1);
					if ($data === false)  return false;

					foreach ($freqmap["vowels"] as $chr => $num)
					{
						if ($num > $data)
						{
							$result .= $chr;

							break;
						}

						$data -= $num;
					}
				}
				else
				{
					$data = $this->GetInt(0, $totalc - 1);
					if ($data === false)  return false;

					foreach ($freqmap["consonants"] as $chr => $num)
					{
						if ($num > $data)
						{
							$result .= $chr;

							break;
						}

						$data -= $num;
					}
				}
			}

			return $result;
		}

		public function GenerateWord(&$freqmap, $len, $separator = "-")
		{
			$result = "";
			$queue = array();
			$threshold = $freqmap["threshold"];
			$state = "start";
			while ($len)
			{
//echo $state . " - " . $len . ":  " . $result . "\n";
				switch ($state)
				{
					case "start":
					{
						// The start of the word (or restart).
						$path = &$freqmap["start"];
						while (count($queue) < $threshold && $len)
						{
							if ($len > 1 || !$path["*"])
							{
								// Some part of the word.
								$found = false;
								if ($path[""])
								{
									$pos = $this->GetInt(0, $path[""] - 1);

									foreach ($path as $chr => &$info)
									{
										if (!is_array($info))  continue;

										if ($info["+"] > $pos)
										{
											$result .= $chr;
											$queue[] = $chr;
											$path = &$path[$chr];
											$len--;

											$found = true;

											break;
										}

										$pos -= $info["+"];
									}
								}

								if (!$found)
								{
									$state = (count($queue) ? "recovery" : "restart");

									break;
								}
							}
							else
							{
								// Last letter of the word.
								$found = false;
								if ($path["*"])
								{
									$pos = $this->GetInt(0, $path["*"] - 1);

									foreach ($path as $chr => &$info)
									{
										if (!is_array($info))  continue;

										if ($info["-"] > $pos)
										{
											$result .= $chr;
											$queue[] = $chr;
											$path = &$path[$chr];
											$len--;

											$found = true;

											break;
										}

										$pos -= $info["-"];
									}
								}

								if (!$found)
								{
									$state = (count($queue) ? "end" : "restart");

									break;
								}
							}
						}

						if (count($queue) >= $threshold)  $state = ($len >= $threshold ? "middle" : "end");

						break;
					}
					case "middle":
					{
						// The middle of the word.
						$str = implode("", $queue);

						if (!isset($freqmap["middle"][$str]))  $state = "recovery";
						else
						{
							$found = false;

							if ($freqmap["middle"][$str][""])
							{
								$pos = $this->GetInt(0, $freqmap["middle"][$str][""] - 1);

								foreach ($freqmap["middle"][$str] as $chr => $num)
								{
									if ($chr === "")  continue;

									if ($num > $pos)
									{
										$result .= $chr;
										$queue[] = $chr;
										array_shift($queue);
										$len--;

										if ($len < $threshold)  $state = "end";

										$found = true;

										break;
									}

									$pos -= $num;
								}
							}

							if (!$found)  $state = "recovery";
						}

						break;
					}
					case "end":
					{
						if (!isset($freqmap["end"][$len]) || !count($queue) || !isset($freqmap["end"][$len][$queue[count($queue) - 1]]))  $state = "restart";
						else
						{
							$path = &$freqmap["end"][$len][$queue[count($queue) - 1]];

							$found = false;

							if ($path[""])
							{
								$pos = $this->GetInt(0, $path[""] - 1);

								foreach ($path as $str => $num)
								{
									if ($str === "")  continue;

									if ($num > $pos)
									{
										$result .= $str;
										$len = 0;

										$found = true;

										break;
									}

									$pos -= $num;
								}
							}

							if (!$found)  $state = "restart";
						}

						break;
					}
					case "recovery":
					{
						if (!count($queue) || !isset($freqmap["recovery"][$queue[count($queue) - 1]]))  $state = "restart";
						else
						{
							$path = &$freqmap["recovery"][$queue[count($queue) - 1]];

							$found = false;

							if ($path[""])
							{
								$pos = $this->GetInt(0, $path[""] - 1);

								foreach ($path as $chr => $num)
								{
									if ($chr === "")  continue;

									if ($num > $pos)
									{
										$result .= $chr;
										$queue[] = $chr;
										array_shift($queue);
										$len--;

										$state = ($len >= $threshold ? "middle" : "end");

										$found = true;

										break;
									}

									$pos -= $num;
								}
							}

							if (!$found)  $state = "restart";
						}

						break;
					}
					case "restart":
					{
						$result .= $separator;
						$queue = array();
						$len -= strlen($separator);

						$state = "start";

						break;
					}
				}
			}

			return $result;
		}

		public function GetMode()
		{
			return $this->mode;
		}

		protected static function RNG_Translate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}
	}
?>