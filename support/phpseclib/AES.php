<?php
namespace {
if (!class_exists('Crypt_Rijndael')) {
	include_once 'Rijndael.php';
}

define('CRYPT_AES_MODE_CTR', CRYPT_MODE_CTR);

define('CRYPT_AES_MODE_ECB', CRYPT_MODE_ECB);

define('CRYPT_AES_MODE_CBC', CRYPT_MODE_CBC);

define('CRYPT_AES_MODE_CFB', CRYPT_MODE_CFB);

define('CRYPT_AES_MODE_OFB', CRYPT_MODE_OFB);

class Crypt_AES extends Crypt_Rijndael
{

	var $const_namespace = 'AES';

	function setBlockLength($length)
	{
		return;
	}

	function setKeyLength($length)
	{
		switch ($length) {
			case 160:
				$length = 192;
				break;
			case 224:
				$length = 256;
		}
		parent::setKeyLength($length);
	}

	function setKey($key)
	{
		parent::setKey($key);

		if (!$this->explicit_key_length) {
			$length = strlen($key);
			switch (true) {
				case $length <= 16:
					$this->key_length = 16;
					break;
				case $length <= 24:
					$this->key_length = 24;
					break;
				default:
					$this->key_length = 32;
			}
			$this->_setEngine();
		}
	}
}}