<?php
namespace {
define('CRYPT_MODE_CTR', -1);

define('CRYPT_MODE_ECB', 1);

define('CRYPT_MODE_CBC', 2);

define('CRYPT_MODE_CFB', 3);

define('CRYPT_MODE_OFB', 4);

define('CRYPT_MODE_STREAM', 5);

define('CRYPT_ENGINE_INTERNAL', 1);

define('CRYPT_ENGINE_MCRYPT', 2);

define('CRYPT_ENGINE_OPENSSL', 3);

class Crypt_Base
{

	var $mode;

	var $block_size = 16;

	var $key = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";

	var $iv;

	var $encryptIV;

	var $decryptIV;

	var $continuousBuffer = false;

	var $enbuffer;

	var $debuffer;

	var $enmcrypt;

	var $demcrypt;

	var $enchanged = true;

	var $dechanged = true;

	var $ecb;

	var $cfb_init_len = 600;

	var $changed = true;

	var $padding = true;

	var $paddable = false;

	var $engine;

	var $preferredEngine;

	var $cipher_name_mcrypt;

	var $cipher_name_openssl;

	var $cipher_name_openssl_ecb;

	var $password_default_salt = 'phpseclib/salt';

	var $const_namespace;

	var $inline_crypt;

	var $use_inline_crypt;

	var $openssl_emulate_ctr = false;

	var $openssl_options;

	var $explicit_key_length = false;

	var $skip_key_adjustment = false;

	function __construct($mode = CRYPT_MODE_CBC)
	{
				switch ($mode) {
			case CRYPT_MODE_ECB:
				$this->paddable = true;
				$this->mode = CRYPT_MODE_ECB;
				break;
			case CRYPT_MODE_CTR:
			case CRYPT_MODE_CFB:
			case CRYPT_MODE_OFB:
			case CRYPT_MODE_STREAM:
				$this->mode = $mode;
				break;
			case CRYPT_MODE_CBC:
			default:
				$this->paddable = true;
				$this->mode = CRYPT_MODE_CBC;
		}

		$this->_setEngine();

				if ($this->use_inline_crypt !== false) {
			$this->use_inline_crypt = version_compare(PHP_VERSION, '5.3.0') >= 0 || function_exists('create_function');
		}
	}

	function Crypt_Base($mode = CRYPT_MODE_CBC)
	{
		$this->__construct($mode);
	}

	function setIV($iv)
	{
		if ($this->mode == CRYPT_MODE_ECB) {
			return;
		}

		$this->iv = $iv;
		$this->changed = true;
	}

	function setKeyLength($length)
	{
		$this->explicit_key_length = true;
		$this->changed = true;
		$this->_setEngine();
	}

	function getKeyLength()
	{
		return $this->key_length << 3;
	}

	function getBlockLength()
	{
		return $this->block_size << 3;
	}

	function setKey($key)
	{
		if (!$this->explicit_key_length) {
			$this->setKeyLength(strlen($key) << 3);
			$this->explicit_key_length = false;
		}

		$this->key = $key;
		$this->changed = true;
		$this->_setEngine();
	}

	function setPassword($password, $method = 'pbkdf2')
	{
		$key = '';

		switch ($method) {
			default: 				$func_args = func_get_args();

								$hash = isset($func_args[2]) ? $func_args[2] : 'sha1';

								$salt = isset($func_args[3]) ? $func_args[3] : $this->password_default_salt;

												$count = isset($func_args[4]) ? $func_args[4] : 1000;

								if (isset($func_args[5]) && $func_args[5] > 0) {
					$dkLen = $func_args[5];
				} else {
					$dkLen = $method == 'pbkdf1' ? 2 * $this->key_length : $this->key_length;
				}

				switch (true) {
					case $method == 'pbkdf1':
						if (!class_exists('Crypt_Hash')) {
							include_once 'Crypt/Hash.php';
						}
						$hashObj = new Crypt_Hash();
						$hashObj->setHash($hash);
						if ($dkLen > $hashObj->getLength()) {
							user_error('Derived key too long');
							return false;
						}
						$t = $password . $salt;
						for ($i = 0; $i < $count; ++$i) {
							$t = $hashObj->hash($t);
						}
						$key = substr($t, 0, $dkLen);

						$this->setKey(substr($key, 0, $dkLen >> 1));
						$this->setIV(substr($key, $dkLen >> 1));

						return true;
										case !function_exists('hash_pbkdf2'):
					case !function_exists('hash_algos'):
					case !in_array($hash, hash_algos()):
						if (!class_exists('Crypt_Hash')) {
							include_once 'Crypt/Hash.php';
						}
						$i = 1;
						$hmac = new Crypt_Hash();
						$hmac->setHash($hash);
						$hmac->setKey($password);
						while (strlen($key) < $dkLen) {
							$f = $u = $hmac->hash($salt . pack('N', $i++));
							for ($j = 2; $j <= $count; ++$j) {
								$u = $hmac->hash($u);
								$f^= $u;
							}
							$key.= $f;
						}
						$key = substr($key, 0, $dkLen);
						break;
					default:
						$key = hash_pbkdf2($hash, $password, $salt, $count, $dkLen, true);
				}
		}

		$this->setKey($key);

		return true;
	}

	function encrypt($plaintext)
	{
		if ($this->paddable) {
			$plaintext = $this->_pad($plaintext);
		}

		if ($this->engine === CRYPT_ENGINE_OPENSSL) {
			if ($this->changed) {
				$this->_clearBuffers();
				$this->changed = false;
			}
			switch ($this->mode) {
				case CRYPT_MODE_STREAM:
					return openssl_encrypt($plaintext, $this->cipher_name_openssl, $this->key, $this->openssl_options);
				case CRYPT_MODE_ECB:
					$result = @openssl_encrypt($plaintext, $this->cipher_name_openssl, $this->key, $this->openssl_options);
					return !defined('OPENSSL_RAW_DATA') ? substr($result, 0, -$this->block_size) : $result;
				case CRYPT_MODE_CBC:
					$result = openssl_encrypt($plaintext, $this->cipher_name_openssl, $this->key, $this->openssl_options, $this->encryptIV);
					if (!defined('OPENSSL_RAW_DATA')) {
						$result = substr($result, 0, -$this->block_size);
					}
					if ($this->continuousBuffer) {
						$this->encryptIV = substr($result, -$this->block_size);
					}
					return $result;
				case CRYPT_MODE_CTR:
					return $this->_openssl_ctr_process($plaintext, $this->encryptIV, $this->enbuffer);
				case CRYPT_MODE_CFB:
															$ciphertext = '';
					if ($this->continuousBuffer) {
						$iv = &$this->encryptIV;
						$pos = &$this->enbuffer['pos'];
					} else {
						$iv = $this->encryptIV;
						$pos = 0;
					}
					$len = strlen($plaintext);
					$i = 0;
					if ($pos) {
						$orig_pos = $pos;
						$max = $this->block_size - $pos;
						if ($len >= $max) {
							$i = $max;
							$len-= $max;
							$pos = 0;
						} else {
							$i = $len;
							$pos+= $len;
							$len = 0;
						}
												$ciphertext = substr($iv, $orig_pos) ^ $plaintext;
						$iv = substr_replace($iv, $ciphertext, $orig_pos, $i);
						$plaintext = substr($plaintext, $i);
					}

					$overflow = $len % $this->block_size;

					if ($overflow) {
						$ciphertext.= openssl_encrypt(substr($plaintext, 0, -$overflow) . str_repeat("\0", $this->block_size), $this->cipher_name_openssl, $this->key, $this->openssl_options, $iv);
						$iv = $this->_string_pop($ciphertext, $this->block_size);

						$size = $len - $overflow;
						$block = $iv ^ substr($plaintext, -$overflow);
						$iv = substr_replace($iv, $block, 0, $overflow);
						$ciphertext.= $block;
						$pos = $overflow;
					} elseif ($len) {
						$ciphertext = openssl_encrypt($plaintext, $this->cipher_name_openssl, $this->key, $this->openssl_options, $iv);
						$iv = substr($ciphertext, -$this->block_size);
					}

					return $ciphertext;
				case CRYPT_MODE_OFB:
					return $this->_openssl_ofb_process($plaintext, $this->encryptIV, $this->enbuffer);
			}
		}

		if ($this->engine === CRYPT_ENGINE_MCRYPT) {
			if ($this->changed) {
				$this->_setupMcrypt();
				$this->changed = false;
			}
			if ($this->enchanged) {
				@mcrypt_generic_init($this->enmcrypt, $this->key, $this->encryptIV);
				$this->enchanged = false;
			}

												if ($this->mode == CRYPT_MODE_CFB && $this->continuousBuffer) {
				$block_size = $this->block_size;
				$iv = &$this->encryptIV;
				$pos = &$this->enbuffer['pos'];
				$len = strlen($plaintext);
				$ciphertext = '';
				$i = 0;
				if ($pos) {
					$orig_pos = $pos;
					$max = $block_size - $pos;
					if ($len >= $max) {
						$i = $max;
						$len-= $max;
						$pos = 0;
					} else {
						$i = $len;
						$pos+= $len;
						$len = 0;
					}
					$ciphertext = substr($iv, $orig_pos) ^ $plaintext;
					$iv = substr_replace($iv, $ciphertext, $orig_pos, $i);
					$this->enbuffer['enmcrypt_init'] = true;
				}
				if ($len >= $block_size) {
					if ($this->enbuffer['enmcrypt_init'] === false || $len > $this->cfb_init_len) {
						if ($this->enbuffer['enmcrypt_init'] === true) {
							@mcrypt_generic_init($this->enmcrypt, $this->key, $iv);
							$this->enbuffer['enmcrypt_init'] = false;
						}
						$ciphertext.= @mcrypt_generic($this->enmcrypt, substr($plaintext, $i, $len - $len % $block_size));
						$iv = substr($ciphertext, -$block_size);
						$len%= $block_size;
					} else {
						while ($len >= $block_size) {
							$iv = @mcrypt_generic($this->ecb, $iv) ^ substr($plaintext, $i, $block_size);
							$ciphertext.= $iv;
							$len-= $block_size;
							$i+= $block_size;
						}
					}
				}

				if ($len) {
					$iv = @mcrypt_generic($this->ecb, $iv);
					$block = $iv ^ substr($plaintext, -$len);
					$iv = substr_replace($iv, $block, 0, $len);
					$ciphertext.= $block;
					$pos = $len;
				}

				return $ciphertext;
			}

			$ciphertext = @mcrypt_generic($this->enmcrypt, $plaintext);

			if (!$this->continuousBuffer) {
				@mcrypt_generic_init($this->enmcrypt, $this->key, $this->encryptIV);
			}

			return $ciphertext;
		}

		if ($this->changed) {
			$this->_setup();
			$this->changed = false;
		}
		if ($this->use_inline_crypt) {
			$inline = $this->inline_crypt;
			return $inline('encrypt', $this, $plaintext);
		}

		$buffer = &$this->enbuffer;
		$block_size = $this->block_size;
		$ciphertext = '';
		switch ($this->mode) {
			case CRYPT_MODE_ECB:
				for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
					$ciphertext.= $this->_encryptBlock(substr($plaintext, $i, $block_size));
				}
				break;
			case CRYPT_MODE_CBC:
				$xor = $this->encryptIV;
				for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
					$block = substr($plaintext, $i, $block_size);
					$block = $this->_encryptBlock($block ^ $xor);
					$xor = $block;
					$ciphertext.= $block;
				}
				if ($this->continuousBuffer) {
					$this->encryptIV = $xor;
				}
				break;
			case CRYPT_MODE_CTR:
				$xor = $this->encryptIV;
				if (strlen($buffer['ciphertext'])) {
					for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
						$block = substr($plaintext, $i, $block_size);
						if (strlen($block) > strlen($buffer['ciphertext'])) {
							$buffer['ciphertext'].= $this->_encryptBlock($xor);
						}
						$this->_increment_str($xor);
						$key = $this->_string_shift($buffer['ciphertext'], $block_size);
						$ciphertext.= $block ^ $key;
					}
				} else {
					for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
						$block = substr($plaintext, $i, $block_size);
						$key = $this->_encryptBlock($xor);
						$this->_increment_str($xor);
						$ciphertext.= $block ^ $key;
					}
				}
				if ($this->continuousBuffer) {
					$this->encryptIV = $xor;
					if ($start = strlen($plaintext) % $block_size) {
						$buffer['ciphertext'] = substr($key, $start) . $buffer['ciphertext'];
					}
				}
				break;
			case CRYPT_MODE_CFB:
												if ($this->continuousBuffer) {
					$iv = &$this->encryptIV;
					$pos = &$buffer['pos'];
				} else {
					$iv = $this->encryptIV;
					$pos = 0;
				}
				$len = strlen($plaintext);
				$i = 0;
				if ($pos) {
					$orig_pos = $pos;
					$max = $block_size - $pos;
					if ($len >= $max) {
						$i = $max;
						$len-= $max;
						$pos = 0;
					} else {
						$i = $len;
						$pos+= $len;
						$len = 0;
					}
										$ciphertext = substr($iv, $orig_pos) ^ $plaintext;
					$iv = substr_replace($iv, $ciphertext, $orig_pos, $i);
				}
				while ($len >= $block_size) {
					$iv = $this->_encryptBlock($iv) ^ substr($plaintext, $i, $block_size);
					$ciphertext.= $iv;
					$len-= $block_size;
					$i+= $block_size;
				}
				if ($len) {
					$iv = $this->_encryptBlock($iv);
					$block = $iv ^ substr($plaintext, $i);
					$iv = substr_replace($iv, $block, 0, $len);
					$ciphertext.= $block;
					$pos = $len;
				}
				break;
			case CRYPT_MODE_OFB:
				$xor = $this->encryptIV;
				if (strlen($buffer['xor'])) {
					for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
						$block = substr($plaintext, $i, $block_size);
						if (strlen($block) > strlen($buffer['xor'])) {
							$xor = $this->_encryptBlock($xor);
							$buffer['xor'].= $xor;
						}
						$key = $this->_string_shift($buffer['xor'], $block_size);
						$ciphertext.= $block ^ $key;
					}
				} else {
					for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
						$xor = $this->_encryptBlock($xor);
						$ciphertext.= substr($plaintext, $i, $block_size) ^ $xor;
					}
					$key = $xor;
				}
				if ($this->continuousBuffer) {
					$this->encryptIV = $xor;
					if ($start = strlen($plaintext) % $block_size) {
						$buffer['xor'] = substr($key, $start) . $buffer['xor'];
					}
				}
				break;
			case CRYPT_MODE_STREAM:
				$ciphertext = $this->_encryptBlock($plaintext);
				break;
		}

		return $ciphertext;
	}

	function decrypt($ciphertext)
	{
		if ($this->paddable) {
									$ciphertext = str_pad($ciphertext, strlen($ciphertext) + ($this->block_size - strlen($ciphertext) % $this->block_size) % $this->block_size, chr(0));
		}

		if ($this->engine === CRYPT_ENGINE_OPENSSL) {
			if ($this->changed) {
				$this->_clearBuffers();
				$this->changed = false;
			}
			switch ($this->mode) {
				case CRYPT_MODE_STREAM:
					$plaintext = openssl_decrypt($ciphertext, $this->cipher_name_openssl, $this->key, $this->openssl_options);
					break;
				case CRYPT_MODE_ECB:
					if (!defined('OPENSSL_RAW_DATA')) {
						$ciphertext.= @openssl_encrypt('', $this->cipher_name_openssl_ecb, $this->key, true);
					}
					$plaintext = openssl_decrypt($ciphertext, $this->cipher_name_openssl, $this->key, $this->openssl_options);
					break;
				case CRYPT_MODE_CBC:
					if (!defined('OPENSSL_RAW_DATA')) {
						$padding = str_repeat(chr($this->block_size), $this->block_size) ^ substr($ciphertext, -$this->block_size);
						$ciphertext.= substr(@openssl_encrypt($padding, $this->cipher_name_openssl_ecb, $this->key, true), 0, $this->block_size);
						$offset = 2 * $this->block_size;
					} else {
						$offset = $this->block_size;
					}
					$plaintext = openssl_decrypt($ciphertext, $this->cipher_name_openssl, $this->key, $this->openssl_options, $this->decryptIV);
					if ($this->continuousBuffer) {
						$this->decryptIV = substr($ciphertext, -$offset, $this->block_size);
					}
					break;
				case CRYPT_MODE_CTR:
					$plaintext = $this->_openssl_ctr_process($ciphertext, $this->decryptIV, $this->debuffer);
					break;
				case CRYPT_MODE_CFB:
															$plaintext = '';
					if ($this->continuousBuffer) {
						$iv = &$this->decryptIV;
						$pos = &$this->buffer['pos'];
					} else {
						$iv = $this->decryptIV;
						$pos = 0;
					}
					$len = strlen($ciphertext);
					$i = 0;
					if ($pos) {
						$orig_pos = $pos;
						$max = $this->block_size - $pos;
						if ($len >= $max) {
							$i = $max;
							$len-= $max;
							$pos = 0;
						} else {
							$i = $len;
							$pos+= $len;
							$len = 0;
						}
												$plaintext = substr($iv, $orig_pos) ^ $ciphertext;
						$iv = substr_replace($iv, substr($ciphertext, 0, $i), $orig_pos, $i);
						$ciphertext = substr($ciphertext, $i);
					}
					$overflow = $len % $this->block_size;
					if ($overflow) {
						$plaintext.= openssl_decrypt(substr($ciphertext, 0, -$overflow), $this->cipher_name_openssl, $this->key, $this->openssl_options, $iv);
						if ($len - $overflow) {
							$iv = substr($ciphertext, -$overflow - $this->block_size, -$overflow);
						}
						$iv = openssl_encrypt(str_repeat("\0", $this->block_size), $this->cipher_name_openssl, $this->key, $this->openssl_options, $iv);
						$plaintext.= $iv ^ substr($ciphertext, -$overflow);
						$iv = substr_replace($iv, substr($ciphertext, -$overflow), 0, $overflow);
						$pos = $overflow;
					} elseif ($len) {
						$plaintext.= openssl_decrypt($ciphertext, $this->cipher_name_openssl, $this->key, $this->openssl_options, $iv);
						$iv = substr($ciphertext, -$this->block_size);
					}
					break;
				case CRYPT_MODE_OFB:
					$plaintext = $this->_openssl_ofb_process($ciphertext, $this->decryptIV, $this->debuffer);
			}

			return $this->paddable ? $this->_unpad($plaintext) : $plaintext;
		}

		if ($this->engine === CRYPT_ENGINE_MCRYPT) {
			$block_size = $this->block_size;
			if ($this->changed) {
				$this->_setupMcrypt();
				$this->changed = false;
			}
			if ($this->dechanged) {
				@mcrypt_generic_init($this->demcrypt, $this->key, $this->decryptIV);
				$this->dechanged = false;
			}

			if ($this->mode == CRYPT_MODE_CFB && $this->continuousBuffer) {
				$iv = &$this->decryptIV;
				$pos = &$this->debuffer['pos'];
				$len = strlen($ciphertext);
				$plaintext = '';
				$i = 0;
				if ($pos) {
					$orig_pos = $pos;
					$max = $block_size - $pos;
					if ($len >= $max) {
						$i = $max;
						$len-= $max;
						$pos = 0;
					} else {
						$i = $len;
						$pos+= $len;
						$len = 0;
					}
										$plaintext = substr($iv, $orig_pos) ^ $ciphertext;
					$iv = substr_replace($iv, substr($ciphertext, 0, $i), $orig_pos, $i);
				}
				if ($len >= $block_size) {
					$cb = substr($ciphertext, $i, $len - $len % $block_size);
					$plaintext.= @mcrypt_generic($this->ecb, $iv . $cb) ^ $cb;
					$iv = substr($cb, -$block_size);
					$len%= $block_size;
				}
				if ($len) {
					$iv = @mcrypt_generic($this->ecb, $iv);
					$plaintext.= $iv ^ substr($ciphertext, -$len);
					$iv = substr_replace($iv, substr($ciphertext, -$len), 0, $len);
					$pos = $len;
				}

				return $plaintext;
			}

			$plaintext = @mdecrypt_generic($this->demcrypt, $ciphertext);

			if (!$this->continuousBuffer) {
				@mcrypt_generic_init($this->demcrypt, $this->key, $this->decryptIV);
			}

			return $this->paddable ? $this->_unpad($plaintext) : $plaintext;
		}

		if ($this->changed) {
			$this->_setup();
			$this->changed = false;
		}
		if ($this->use_inline_crypt) {
			$inline = $this->inline_crypt;
			return $inline('decrypt', $this, $ciphertext);
		}

		$block_size = $this->block_size;

		$buffer = &$this->debuffer;
		$plaintext = '';
		switch ($this->mode) {
			case CRYPT_MODE_ECB:
				for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
					$plaintext.= $this->_decryptBlock(substr($ciphertext, $i, $block_size));
				}
				break;
			case CRYPT_MODE_CBC:
				$xor = $this->decryptIV;
				for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
					$block = substr($ciphertext, $i, $block_size);
					$plaintext.= $this->_decryptBlock($block) ^ $xor;
					$xor = $block;
				}
				if ($this->continuousBuffer) {
					$this->decryptIV = $xor;
				}
				break;
			case CRYPT_MODE_CTR:
				$xor = $this->decryptIV;
				if (strlen($buffer['ciphertext'])) {
					for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
						$block = substr($ciphertext, $i, $block_size);
						if (strlen($block) > strlen($buffer['ciphertext'])) {
							$buffer['ciphertext'].= $this->_encryptBlock($xor);
							$this->_increment_str($xor);
						}
						$key = $this->_string_shift($buffer['ciphertext'], $block_size);
						$plaintext.= $block ^ $key;
					}
				} else {
					for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
						$block = substr($ciphertext, $i, $block_size);
						$key = $this->_encryptBlock($xor);
						$this->_increment_str($xor);
						$plaintext.= $block ^ $key;
					}
				}
				if ($this->continuousBuffer) {
					$this->decryptIV = $xor;
					if ($start = strlen($ciphertext) % $block_size) {
						$buffer['ciphertext'] = substr($key, $start) . $buffer['ciphertext'];
					}
				}
				break;
			case CRYPT_MODE_CFB:
				if ($this->continuousBuffer) {
					$iv = &$this->decryptIV;
					$pos = &$buffer['pos'];
				} else {
					$iv = $this->decryptIV;
					$pos = 0;
				}
				$len = strlen($ciphertext);
				$i = 0;
				if ($pos) {
					$orig_pos = $pos;
					$max = $block_size - $pos;
					if ($len >= $max) {
						$i = $max;
						$len-= $max;
						$pos = 0;
					} else {
						$i = $len;
						$pos+= $len;
						$len = 0;
					}
										$plaintext = substr($iv, $orig_pos) ^ $ciphertext;
					$iv = substr_replace($iv, substr($ciphertext, 0, $i), $orig_pos, $i);
				}
				while ($len >= $block_size) {
					$iv = $this->_encryptBlock($iv);
					$cb = substr($ciphertext, $i, $block_size);
					$plaintext.= $iv ^ $cb;
					$iv = $cb;
					$len-= $block_size;
					$i+= $block_size;
				}
				if ($len) {
					$iv = $this->_encryptBlock($iv);
					$plaintext.= $iv ^ substr($ciphertext, $i);
					$iv = substr_replace($iv, substr($ciphertext, $i), 0, $len);
					$pos = $len;
				}
				break;
			case CRYPT_MODE_OFB:
				$xor = $this->decryptIV;
				if (strlen($buffer['xor'])) {
					for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
						$block = substr($ciphertext, $i, $block_size);
						if (strlen($block) > strlen($buffer['xor'])) {
							$xor = $this->_encryptBlock($xor);
							$buffer['xor'].= $xor;
						}
						$key = $this->_string_shift($buffer['xor'], $block_size);
						$plaintext.= $block ^ $key;
					}
				} else {
					for ($i = 0; $i < strlen($ciphertext); $i+=$block_size) {
						$xor = $this->_encryptBlock($xor);
						$plaintext.= substr($ciphertext, $i, $block_size) ^ $xor;
					}
					$key = $xor;
				}
				if ($this->continuousBuffer) {
					$this->decryptIV = $xor;
					if ($start = strlen($ciphertext) % $block_size) {
						$buffer['xor'] = substr($key, $start) . $buffer['xor'];
					}
				}
				break;
			case CRYPT_MODE_STREAM:
				$plaintext = $this->_decryptBlock($ciphertext);
				break;
		}
		return $this->paddable ? $this->_unpad($plaintext) : $plaintext;
	}

	function _openssl_ctr_process($plaintext, &$encryptIV, &$buffer)
	{
		$ciphertext = '';

		$block_size = $this->block_size;
		$key = $this->key;

		if ($this->openssl_emulate_ctr) {
			$xor = $encryptIV;
			if (strlen($buffer['ciphertext'])) {
				for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
					$block = substr($plaintext, $i, $block_size);
					if (strlen($block) > strlen($buffer['ciphertext'])) {
						$result = @openssl_encrypt($xor, $this->cipher_name_openssl_ecb, $key, $this->openssl_options);
						$result = !defined('OPENSSL_RAW_DATA') ? substr($result, 0, -$this->block_size) : $result;
						$buffer['ciphertext'].= $result;
					}
					$this->_increment_str($xor);
					$otp = $this->_string_shift($buffer['ciphertext'], $block_size);
					$ciphertext.= $block ^ $otp;
				}
			} else {
				for ($i = 0; $i < strlen($plaintext); $i+=$block_size) {
					$block = substr($plaintext, $i, $block_size);
					$otp = @openssl_encrypt($xor, $this->cipher_name_openssl_ecb, $key, $this->openssl_options);
					$otp = !defined('OPENSSL_RAW_DATA') ? substr($otp, 0, -$this->block_size) : $otp;
					$this->_increment_str($xor);
					$ciphertext.= $block ^ $otp;
				}
			}
			if ($this->continuousBuffer) {
				$encryptIV = $xor;
				if ($start = strlen($plaintext) % $block_size) {
					$buffer['ciphertext'] = substr($key, $start) . $buffer['ciphertext'];
				}
			}

			return $ciphertext;
		}

		if (strlen($buffer['ciphertext'])) {
			$ciphertext = $plaintext ^ $this->_string_shift($buffer['ciphertext'], strlen($plaintext));
			$plaintext = substr($plaintext, strlen($ciphertext));

			if (!strlen($plaintext)) {
				return $ciphertext;
			}
		}

		$overflow = strlen($plaintext) % $block_size;
		if ($overflow) {
			$plaintext2 = $this->_string_pop($plaintext, $overflow); 			$encrypted = openssl_encrypt($plaintext . str_repeat("\0", $block_size), $this->cipher_name_openssl, $key, $this->openssl_options, $encryptIV);
			$temp = $this->_string_pop($encrypted, $block_size);
			$ciphertext.= $encrypted . ($plaintext2 ^ $temp);
			if ($this->continuousBuffer) {
				$buffer['ciphertext'] = substr($temp, $overflow);
				$encryptIV = $temp;
			}
		} elseif (!strlen($buffer['ciphertext'])) {
			$ciphertext.= openssl_encrypt($plaintext . str_repeat("\0", $block_size), $this->cipher_name_openssl, $key, $this->openssl_options, $encryptIV);
			$temp = $this->_string_pop($ciphertext, $block_size);
			if ($this->continuousBuffer) {
				$encryptIV = $temp;
			}
		}
		if ($this->continuousBuffer) {
			if (!defined('OPENSSL_RAW_DATA')) {
				$encryptIV.= @openssl_encrypt('', $this->cipher_name_openssl_ecb, $key, $this->openssl_options);
			}
			$encryptIV = openssl_decrypt($encryptIV, $this->cipher_name_openssl_ecb, $key, $this->openssl_options);
			if ($overflow) {
				$this->_increment_str($encryptIV);
			}
		}

		return $ciphertext;
	}

	function _openssl_ofb_process($plaintext, &$encryptIV, &$buffer)
	{
		if (strlen($buffer['xor'])) {
			$ciphertext = $plaintext ^ $buffer['xor'];
			$buffer['xor'] = substr($buffer['xor'], strlen($ciphertext));
			$plaintext = substr($plaintext, strlen($ciphertext));
		} else {
			$ciphertext = '';
		}

		$block_size = $this->block_size;

		$len = strlen($plaintext);
		$key = $this->key;
		$overflow = $len % $block_size;

		if (strlen($plaintext)) {
			if ($overflow) {
				$ciphertext.= openssl_encrypt(substr($plaintext, 0, -$overflow) . str_repeat("\0", $block_size), $this->cipher_name_openssl, $key, $this->openssl_options, $encryptIV);
				$xor = $this->_string_pop($ciphertext, $block_size);
				if ($this->continuousBuffer) {
					$encryptIV = $xor;
				}
				$ciphertext.= $this->_string_shift($xor, $overflow) ^ substr($plaintext, -$overflow);
				if ($this->continuousBuffer) {
					$buffer['xor'] = $xor;
				}
			} else {
				$ciphertext = openssl_encrypt($plaintext, $this->cipher_name_openssl, $key, $this->openssl_options, $encryptIV);
				if ($this->continuousBuffer) {
					$encryptIV = substr($ciphertext, -$block_size) ^ substr($plaintext, -$block_size);
				}
			}
		}

		return $ciphertext;
	}

	function _openssl_translate_mode()
	{
		switch ($this->mode) {
			case CRYPT_MODE_ECB:
				return 'ecb';
			case CRYPT_MODE_CBC:
				return 'cbc';
			case CRYPT_MODE_CTR:
				return 'ctr';
			case CRYPT_MODE_CFB:
				return 'cfb';
			case CRYPT_MODE_OFB:
				return 'ofb';
		}
	}

	function enablePadding()
	{
		$this->padding = true;
	}

	function disablePadding()
	{
		$this->padding = false;
	}

	function enableContinuousBuffer()
	{
		if ($this->mode == CRYPT_MODE_ECB) {
			return;
		}

		$this->continuousBuffer = true;

		$this->_setEngine();
	}

	function disableContinuousBuffer()
	{
		if ($this->mode == CRYPT_MODE_ECB) {
			return;
		}
		if (!$this->continuousBuffer) {
			return;
		}

		$this->continuousBuffer = false;
		$this->changed = true;

		$this->_setEngine();
	}

	function isValidEngine($engine)
	{
		switch ($engine) {
			case CRYPT_ENGINE_OPENSSL:
				if ($this->mode == CRYPT_MODE_STREAM && $this->continuousBuffer) {
					return false;
				}
				$this->openssl_emulate_ctr = false;
				$result = $this->cipher_name_openssl &&
							extension_loaded('openssl') &&
														version_compare(PHP_VERSION, '5.3.3', '>=');
				if (!$result) {
					return false;
				}

												if (!defined('OPENSSL_RAW_DATA')) {
					$this->openssl_options = true;
				} else {
					$this->openssl_options = OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING;
				}

				$methods = openssl_get_cipher_methods();
				if (in_array($this->cipher_name_openssl, $methods)) {
					return true;
				}
												switch ($this->mode) {
					case CRYPT_MODE_CTR:
						if (in_array($this->cipher_name_openssl_ecb, $methods)) {
							$this->openssl_emulate_ctr = true;
							return true;
						}
				}
				return false;
			case CRYPT_ENGINE_MCRYPT:
				return $this->cipher_name_mcrypt &&
						extension_loaded('mcrypt') &&
						in_array($this->cipher_name_mcrypt, @mcrypt_list_algorithms());
			case CRYPT_ENGINE_INTERNAL:
				return true;
		}

		return false;
	}

	function setPreferredEngine($engine)
	{
		switch ($engine) {
						case CRYPT_ENGINE_MCRYPT:
			case CRYPT_ENGINE_INTERNAL:
				$this->preferredEngine = $engine;
				break;
			default:
				$this->preferredEngine = CRYPT_ENGINE_OPENSSL;
		}

		$this->_setEngine();
	}

	function getEngine()
	{
		return $this->engine;
	}

	function _setEngine()
	{
		$this->engine = null;

		$candidateEngines = array(
			$this->preferredEngine,
			CRYPT_ENGINE_OPENSSL,
			CRYPT_ENGINE_MCRYPT
		);
		foreach ($candidateEngines as $engine) {
			if ($this->isValidEngine($engine)) {
				$this->engine = $engine;
				break;
			}
		}
		if (!$this->engine) {
			$this->engine = CRYPT_ENGINE_INTERNAL;
		}

		if ($this->engine != CRYPT_ENGINE_MCRYPT && $this->enmcrypt) {
									@mcrypt_module_close($this->enmcrypt);
			@mcrypt_module_close($this->demcrypt);
			$this->enmcrypt = null;
			$this->demcrypt = null;

			if ($this->ecb) {
				@mcrypt_module_close($this->ecb);
				$this->ecb = null;
			}
		}

		$this->changed = true;
	}

	function _encryptBlock($in)
	{
		user_error((version_compare(PHP_VERSION, '5.0.0', '>=')	? __METHOD__ : __FUNCTION__)	. '() must extend by class ' . get_class($this), E_USER_ERROR);
	}

	function _decryptBlock($in)
	{
		user_error((version_compare(PHP_VERSION, '5.0.0', '>=')	? __METHOD__ : __FUNCTION__)	. '() must extend by class ' . get_class($this), E_USER_ERROR);
	}

	function _setupKey()
	{
		user_error((version_compare(PHP_VERSION, '5.0.0', '>=')	? __METHOD__ : __FUNCTION__)	. '() must extend by class ' . get_class($this), E_USER_ERROR);
	}

	function _setup()
	{
		$this->_clearBuffers();
		$this->_setupKey();

		if ($this->use_inline_crypt) {
			$this->_setupInlineCrypt();
		}
	}

	function _setupMcrypt()
	{
		$this->_clearBuffers();
		$this->enchanged = $this->dechanged = true;

		if (!isset($this->enmcrypt)) {
			static $mcrypt_modes = array(
				CRYPT_MODE_CTR	=> 'ctr',
				CRYPT_MODE_ECB	=> MCRYPT_MODE_ECB,
				CRYPT_MODE_CBC	=> MCRYPT_MODE_CBC,
				CRYPT_MODE_CFB	=> 'ncfb',
				CRYPT_MODE_OFB	=> MCRYPT_MODE_NOFB,
				CRYPT_MODE_STREAM => MCRYPT_MODE_STREAM,
			);

			$this->demcrypt = @mcrypt_module_open($this->cipher_name_mcrypt, '', $mcrypt_modes[$this->mode], '');
			$this->enmcrypt = @mcrypt_module_open($this->cipher_name_mcrypt, '', $mcrypt_modes[$this->mode], '');

												if ($this->mode == CRYPT_MODE_CFB) {
				$this->ecb = @mcrypt_module_open($this->cipher_name_mcrypt, '', MCRYPT_MODE_ECB, '');
			}
		}
		if ($this->mode == CRYPT_MODE_CFB) {
			@mcrypt_generic_init($this->ecb, $this->key, str_repeat("\0", $this->block_size));
		}
	}

	function _pad($text)
	{
		$length = strlen($text);

		if (!$this->padding) {
			if ($length % $this->block_size == 0) {
				return $text;
			} else {
				user_error("The plaintext's length ($length) is not a multiple of the block size ({$this->block_size})");
				$this->padding = true;
			}
		}

		$pad = $this->block_size - ($length % $this->block_size);

		return str_pad($text, $length + $pad, chr($pad));
	}

	function _unpad($text)
	{
		if (!$this->padding) {
			return $text;
		}

		$length = ord($text[strlen($text) - 1]);

		if (!$length || $length > $this->block_size) {
			return false;
		}

		return substr($text, 0, -$length);
	}

	function _clearBuffers()
	{
		$this->enbuffer = $this->debuffer = array('ciphertext' => '', 'xor' => '', 'pos' => 0, 'enmcrypt_init' => true);

						$this->encryptIV = $this->decryptIV = str_pad(substr($this->iv, 0, $this->block_size), $this->block_size, "\0");

		if (!$this->skip_key_adjustment) {
			$this->key = str_pad(substr($this->key, 0, $this->key_length), $this->key_length, "\0");
		}
	}

	function _string_shift(&$string, $index = 1)
	{
		$substr = substr($string, 0, $index);
		$string = substr($string, $index);
		return $substr;
	}

	function _string_pop(&$string, $index = 1)
	{
		$substr = substr($string, -$index);
		$string = substr($string, 0, -$index);
		return $substr;
	}

	function _increment_str(&$var)
	{
		for ($i = 4; $i <= strlen($var); $i+= 4) {
			$temp = substr($var, -$i, 4);
			switch ($temp) {
				case "\xFF\xFF\xFF\xFF":
					$var = substr_replace($var, "\x00\x00\x00\x00", -$i, 4);
					break;
				case "\x7F\xFF\xFF\xFF":
					$var = substr_replace($var, "\x80\x00\x00\x00", -$i, 4);
					return;
				default:
					$temp = unpack('Nnum', $temp);
					$var = substr_replace($var, pack('N', $temp['num'] + 1), -$i, 4);
					return;
			}
		}

		$remainder = strlen($var) % 4;

		if ($remainder == 0) {
			return;
		}

		$temp = unpack('Nnum', str_pad(substr($var, 0, $remainder), 4, "\0", STR_PAD_LEFT));
		$temp = substr(pack('N', $temp['num'] + 1), -$remainder);
		$var = substr_replace($var, $temp, 0, $remainder);
	}

	function _setupInlineCrypt()
	{

		$this->use_inline_crypt = false;
	}

	function _createInlineCryptFunction($cipher_code)
	{
		$block_size = $this->block_size;

				$init_crypt	= isset($cipher_code['init_crypt'])	? $cipher_code['init_crypt']	: '';
		$init_encrypt	= isset($cipher_code['init_encrypt'])	? $cipher_code['init_encrypt']	: '';
		$init_decrypt	= isset($cipher_code['init_decrypt'])	? $cipher_code['init_decrypt']	: '';
				$encrypt_block = $cipher_code['encrypt_block'];
		$decrypt_block = $cipher_code['decrypt_block'];

								switch ($this->mode) {
			case CRYPT_MODE_ECB:
				$encrypt = $init_encrypt . '
                    $_ciphertext = "";
                    $_plaintext_len = strlen($_text);

                    for ($_i = 0; $_i < $_plaintext_len; $_i+= '.$block_size.') {
                        $in = substr($_text, $_i, '.$block_size.');
                        '.$encrypt_block.'
                        $_ciphertext.= $in;
                    }

                    return $_ciphertext;
                    ';

				$decrypt = $init_decrypt . '
                    $_plaintext = "";
                    $_text = str_pad($_text, strlen($_text) + ('.$block_size.' - strlen($_text) % '.$block_size.') % '.$block_size.', chr(0));
                    $_ciphertext_len = strlen($_text);

                    for ($_i = 0; $_i < $_ciphertext_len; $_i+= '.$block_size.') {
                        $in = substr($_text, $_i, '.$block_size.');
                        '.$decrypt_block.'
                        $_plaintext.= $in;
                    }

                    return $self->_unpad($_plaintext);
                    ';
				break;
			case CRYPT_MODE_CTR:
				$encrypt = $init_encrypt . '
                    $_ciphertext = "";
                    $_plaintext_len = strlen($_text);
                    $_xor = $self->encryptIV;
                    $_buffer = &$self->enbuffer;
                    if (strlen($_buffer["ciphertext"])) {
                        for ($_i = 0; $_i < $_plaintext_len; $_i+= '.$block_size.') {
                            $_block = substr($_text, $_i, '.$block_size.');
                            if (strlen($_block) > strlen($_buffer["ciphertext"])) {
                                $in = $_xor;
                                '.$encrypt_block.'
                                $self->_increment_str($_xor);
                                $_buffer["ciphertext"].= $in;
                            }
                            $_key = $self->_string_shift($_buffer["ciphertext"], '.$block_size.');
                            $_ciphertext.= $_block ^ $_key;
                        }
                    } else {
                        for ($_i = 0; $_i < $_plaintext_len; $_i+= '.$block_size.') {
                            $_block = substr($_text, $_i, '.$block_size.');
                            $in = $_xor;
                            '.$encrypt_block.'
                            $self->_increment_str($_xor);
                            $_key = $in;
                            $_ciphertext.= $_block ^ $_key;
                        }
                    }
                    if ($self->continuousBuffer) {
                        $self->encryptIV = $_xor;
                        if ($_start = $_plaintext_len % '.$block_size.') {
                            $_buffer["ciphertext"] = substr($_key, $_start) . $_buffer["ciphertext"];
                        }
                    }

                    return $_ciphertext;
                ';

				$decrypt = $init_encrypt . '
                    $_plaintext = "";
                    $_ciphertext_len = strlen($_text);
                    $_xor = $self->decryptIV;
                    $_buffer = &$self->debuffer;

                    if (strlen($_buffer["ciphertext"])) {
                        for ($_i = 0; $_i < $_ciphertext_len; $_i+= '.$block_size.') {
                            $_block = substr($_text, $_i, '.$block_size.');
                            if (strlen($_block) > strlen($_buffer["ciphertext"])) {
                                $in = $_xor;
                                '.$encrypt_block.'
                                $self->_increment_str($_xor);
                                $_buffer["ciphertext"].= $in;
                            }
                            $_key = $self->_string_shift($_buffer["ciphertext"], '.$block_size.');
                            $_plaintext.= $_block ^ $_key;
                        }
                    } else {
                        for ($_i = 0; $_i < $_ciphertext_len; $_i+= '.$block_size.') {
                            $_block = substr($_text, $_i, '.$block_size.');
                            $in = $_xor;
                            '.$encrypt_block.'
                            $self->_increment_str($_xor);
                            $_key = $in;
                            $_plaintext.= $_block ^ $_key;
                        }
                    }
                    if ($self->continuousBuffer) {
                        $self->decryptIV = $_xor;
                        if ($_start = $_ciphertext_len % '.$block_size.') {
                            $_buffer["ciphertext"] = substr($_key, $_start) . $_buffer["ciphertext"];
                        }
                    }

                    return $_plaintext;
                    ';
				break;
			case CRYPT_MODE_CFB:
				$encrypt = $init_encrypt . '
                    $_ciphertext = "";
                    $_buffer = &$self->enbuffer;

                    if ($self->continuousBuffer) {
                        $_iv = &$self->encryptIV;
                        $_pos = &$_buffer["pos"];
                    } else {
                        $_iv = $self->encryptIV;
                        $_pos = 0;
                    }
                    $_len = strlen($_text);
                    $_i = 0;
                    if ($_pos) {
                        $_orig_pos = $_pos;
                        $_max = '.$block_size.' - $_pos;
                        if ($_len >= $_max) {
                            $_i = $_max;
                            $_len-= $_max;
                            $_pos = 0;
                        } else {
                            $_i = $_len;
                            $_pos+= $_len;
                            $_len = 0;
                        }
                        $_ciphertext = substr($_iv, $_orig_pos) ^ $_text;
                        $_iv = substr_replace($_iv, $_ciphertext, $_orig_pos, $_i);
                    }
                    while ($_len >= '.$block_size.') {
                        $in = $_iv;
                        '.$encrypt_block.';
                        $_iv = $in ^ substr($_text, $_i, '.$block_size.');
                        $_ciphertext.= $_iv;
                        $_len-= '.$block_size.';
                        $_i+= '.$block_size.';
                    }
                    if ($_len) {
                        $in = $_iv;
                        '.$encrypt_block.'
                        $_iv = $in;
                        $_block = $_iv ^ substr($_text, $_i);
                        $_iv = substr_replace($_iv, $_block, 0, $_len);
                        $_ciphertext.= $_block;
                        $_pos = $_len;
                    }
                    return $_ciphertext;
                ';

				$decrypt = $init_encrypt . '
                    $_plaintext = "";
                    $_buffer = &$self->debuffer;

                    if ($self->continuousBuffer) {
                        $_iv = &$self->decryptIV;
                        $_pos = &$_buffer["pos"];
                    } else {
                        $_iv = $self->decryptIV;
                        $_pos = 0;
                    }
                    $_len = strlen($_text);
                    $_i = 0;
                    if ($_pos) {
                        $_orig_pos = $_pos;
                        $_max = '.$block_size.' - $_pos;
                        if ($_len >= $_max) {
                            $_i = $_max;
                            $_len-= $_max;
                            $_pos = 0;
                        } else {
                            $_i = $_len;
                            $_pos+= $_len;
                            $_len = 0;
                        }
                        $_plaintext = substr($_iv, $_orig_pos) ^ $_text;
                        $_iv = substr_replace($_iv, substr($_text, 0, $_i), $_orig_pos, $_i);
                    }
                    while ($_len >= '.$block_size.') {
                        $in = $_iv;
                        '.$encrypt_block.'
                        $_iv = $in;
                        $cb = substr($_text, $_i, '.$block_size.');
                        $_plaintext.= $_iv ^ $cb;
                        $_iv = $cb;
                        $_len-= '.$block_size.';
                        $_i+= '.$block_size.';
                    }
                    if ($_len) {
                        $in = $_iv;
                        '.$encrypt_block.'
                        $_iv = $in;
                        $_plaintext.= $_iv ^ substr($_text, $_i);
                        $_iv = substr_replace($_iv, substr($_text, $_i), 0, $_len);
                        $_pos = $_len;
                    }

                    return $_plaintext;
                    ';
				break;
			case CRYPT_MODE_OFB:
				$encrypt = $init_encrypt . '
                    $_ciphertext = "";
                    $_plaintext_len = strlen($_text);
                    $_xor = $self->encryptIV;
                    $_buffer = &$self->enbuffer;

                    if (strlen($_buffer["xor"])) {
                        for ($_i = 0; $_i < $_plaintext_len; $_i+= '.$block_size.') {
                            $_block = substr($_text, $_i, '.$block_size.');
                            if (strlen($_block) > strlen($_buffer["xor"])) {
                                $in = $_xor;
                                '.$encrypt_block.'
                                $_xor = $in;
                                $_buffer["xor"].= $_xor;
                            }
                            $_key = $self->_string_shift($_buffer["xor"], '.$block_size.');
                            $_ciphertext.= $_block ^ $_key;
                        }
                    } else {
                        for ($_i = 0; $_i < $_plaintext_len; $_i+= '.$block_size.') {
                            $in = $_xor;
                            '.$encrypt_block.'
                            $_xor = $in;
                            $_ciphertext.= substr($_text, $_i, '.$block_size.') ^ $_xor;
                        }
                        $_key = $_xor;
                    }
                    if ($self->continuousBuffer) {
                        $self->encryptIV = $_xor;
                        if ($_start = $_plaintext_len % '.$block_size.') {
                             $_buffer["xor"] = substr($_key, $_start) . $_buffer["xor"];
                        }
                    }
                    return $_ciphertext;
                    ';

				$decrypt = $init_encrypt . '
                    $_plaintext = "";
                    $_ciphertext_len = strlen($_text);
                    $_xor = $self->decryptIV;
                    $_buffer = &$self->debuffer;

                    if (strlen($_buffer["xor"])) {
                        for ($_i = 0; $_i < $_ciphertext_len; $_i+= '.$block_size.') {
                            $_block = substr($_text, $_i, '.$block_size.');
                            if (strlen($_block) > strlen($_buffer["xor"])) {
                                $in = $_xor;
                                '.$encrypt_block.'
                                $_xor = $in;
                                $_buffer["xor"].= $_xor;
                            }
                            $_key = $self->_string_shift($_buffer["xor"], '.$block_size.');
                            $_plaintext.= $_block ^ $_key;
                        }
                    } else {
                        for ($_i = 0; $_i < $_ciphertext_len; $_i+= '.$block_size.') {
                            $in = $_xor;
                            '.$encrypt_block.'
                            $_xor = $in;
                            $_plaintext.= substr($_text, $_i, '.$block_size.') ^ $_xor;
                        }
                        $_key = $_xor;
                    }
                    if ($self->continuousBuffer) {
                        $self->decryptIV = $_xor;
                        if ($_start = $_ciphertext_len % '.$block_size.') {
                             $_buffer["xor"] = substr($_key, $_start) . $_buffer["xor"];
                        }
                    }
                    return $_plaintext;
                    ';
				break;
			case CRYPT_MODE_STREAM:
				$encrypt = $init_encrypt . '
                    $_ciphertext = "";
                    '.$encrypt_block.'
                    return $_ciphertext;
                    ';
				$decrypt = $init_decrypt . '
                    $_plaintext = "";
                    '.$decrypt_block.'
                    return $_plaintext;
                    ';
				break;
						default:
				$encrypt = $init_encrypt . '
                    $_ciphertext = "";
                    $_plaintext_len = strlen($_text);

                    $in = $self->encryptIV;

                    for ($_i = 0; $_i < $_plaintext_len; $_i+= '.$block_size.') {
                        $in = substr($_text, $_i, '.$block_size.') ^ $in;
                        '.$encrypt_block.'
                        $_ciphertext.= $in;
                    }

                    if ($self->continuousBuffer) {
                        $self->encryptIV = $in;
                    }

                    return $_ciphertext;
                    ';

				$decrypt = $init_decrypt . '
                    $_plaintext = "";
                    $_text = str_pad($_text, strlen($_text) + ('.$block_size.' - strlen($_text) % '.$block_size.') % '.$block_size.', chr(0));
                    $_ciphertext_len = strlen($_text);

                    $_iv = $self->decryptIV;

                    for ($_i = 0; $_i < $_ciphertext_len; $_i+= '.$block_size.') {
                        $in = $_block = substr($_text, $_i, '.$block_size.');
                        '.$decrypt_block.'
                        $_plaintext.= $in ^ $_iv;
                        $_iv = $_block;
                    }

                    if ($self->continuousBuffer) {
                        $self->decryptIV = $_iv;
                    }

                    return $self->_unpad($_plaintext);
                    ';
				break;
		}

				if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
			eval('$func = function ($_action, &$self, $_text) { ' . $init_crypt . 'if ($_action == "encrypt") { ' . $encrypt . ' } else { ' . $decrypt . ' } };');
			return $func;
		}

		return create_function('$_action, &$self, $_text', $init_crypt . 'if ($_action == "encrypt") { ' . $encrypt . ' } else { ' . $decrypt . ' }');
	}

	function &_getLambdaFunctions()
	{
		static $functions = array();
		return $functions;
	}

	function _hashInlineCryptFunction($bytes)
	{
		if (!defined('CRYPT_BASE_WHIRLPOOL_AVAILABLE')) {
			define('CRYPT_BASE_WHIRLPOOL_AVAILABLE', (bool)(extension_loaded('hash') && in_array('whirlpool', hash_algos())));
		}

		$result = '';
		$hash = $bytes;

		switch (true) {
			case CRYPT_BASE_WHIRLPOOL_AVAILABLE:
				foreach (str_split($bytes, 64) as $t) {
					$hash = hash('whirlpool', $hash, true);
					$result .= $t ^ $hash;
				}
				return $result . hash('whirlpool', $hash, true);
			default:
				$len = strlen($bytes);
				for ($i = 0; $i < $len; $i+=20) {
					$t = substr($bytes, $i, 20);
					$hash = pack('H*', sha1($hash));
					$result .= $t ^ $hash;
				}
				return $result . pack('H*', sha1($hash));
		}
	}

	function safe_intval($x)
	{
		switch (true) {
			case is_int($x):
						case version_compare(PHP_VERSION, '5.3.0') >= 0 && (php_uname('m') & "\xDF\xDF\xDF") != 'ARM':
						case (PHP_OS & "\xDF\xDF\xDF") === 'WIN':
				return $x;
		}
		return (fmod($x, 0x80000000) & 0x7FFFFFFF) |
			((fmod(floor($x / 0x80000000), 2) & 1) << 31);
	}

	function safe_intval_inline()
	{
				switch (true) {
			case defined('PHP_INT_SIZE') && PHP_INT_SIZE == 8:
			case version_compare(PHP_VERSION, '5.3.0') >= 0 && (php_uname('m') & "\xDF\xDF\xDF") != 'ARM':
			case (PHP_OS & "\xDF\xDF\xDF") === 'WIN':
				return '%s';
				break;
			default:
				$safeint = '(is_int($temp = %s) ? $temp : (fmod($temp, 0x80000000) & 0x7FFFFFFF) | ';
				return $safeint . '((fmod(floor($temp / 0x80000000), 2) & 1) << 31))';
		}
	}
}}