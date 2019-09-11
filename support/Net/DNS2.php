<?php

spl_autoload_register('Net_DNS2::autoload');

class Net_DNS2
{

	const VERSION = '1.4.4';

	const RESOLV_CONF = '/etc/resolv.conf';

	public $use_resolv_options = false;

	public $use_tcp = false;

	public $dns_port = 53;

	public $local_host = '';
	public $local_port = 0;

	public $timeout = 5;

	public $ns_random = false;

	public $domain = '';

	public $search_list = array();

	public $cache_type = 'none';

	public $cache_file = '/tmp/net_dns2.cache';

	public $cache_size = 50000;

	public $cache_serializer = 'serialize';

	public $strict_query_mode = false;

	public $recurse = true;

	public $dnssec = false;

	public $dnssec_ad_flag = false;

	public $dnssec_cd_flag = false;

	public $dnssec_payload_size = 4000;

	public $last_exception = null;

	public $last_exception_list = array();

	public $nameservers = array();

	protected $sock = array(Net_DNS2_Socket::SOCK_DGRAM => array(), Net_DNS2_Socket::SOCK_STREAM => array());

	protected $sockets_enabled = false;

	protected $auth_signature = null;

	protected $cache = null;

	protected $use_cache = false;

	public function __construct(array $options = null)
	{
		//

		//

		//
		if ( (extension_loaded('sockets') == true) && (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') ) {

			$this->sockets_enabled = true;
		}

		//

		//
		if (!empty($options)) {

			foreach ($options as $key => $value) {

				if ($key == 'nameservers') {

					$this->setServers($value);
				} else {

					$this->$key = $value;
				}
			}
		}

		//

		//
		switch($this->cache_type) {
		case 'shared':
			if (extension_loaded('shmop')) {

				$this->cache = new Net_DNS2_Cache_Shm;
				$this->use_cache = true;
			} else {

				throw new Net_DNS2_Exception(
					'shmop library is not available for cache',
					Net_DNS2_Lookups::E_CACHE_SHM_UNAVAIL
				);
			}
			break;
		case 'file':

			$this->cache = new Net_DNS2_Cache_File;
			$this->use_cache = true;

			break;
		case 'none':
			$this->use_cache = false;
			break;
		default:

			throw new Net_DNS2_Exception(
				'un-supported cache type: ' . $this->cache_type,
				Net_DNS2_Lookups::E_CACHE_UNSUPPORTED
			);
		}
	}

	static public function autoload($name)
	{
		//

		//
		if (strncmp($name, 'Net_DNS2', 8) == 0) {

			include str_replace('_', '/', $name) . '.php';
		}

		return;
	}

	public function setServers($nameservers)
	{
		//

		//

		//
		if (is_array($nameservers)) {

			$this->nameservers = $nameservers;

		} else {

			//

			//
			$ns = array();

			//

			//
			if (is_readable($nameservers) === true) {

				$data = file_get_contents($nameservers);
				if ($data === false) {
					throw new Net_DNS2_Exception(
						'failed to read contents of file: ' . $nameservers,
						Net_DNS2_Lookups::E_NS_INVALID_FILE
					);
				}

				$lines = explode("\n", $data);

				foreach ($lines as $line) {

					$line = trim($line);

					//

					//
					if ( (strlen($line) == 0)
						|| ($line[0] == '#')
						|| ($line[0] == ';')
					) {
						continue;
					}

					//

					//
					if (strpos($line, ' ') === false) {
						continue;
					}

					list($key, $value) = preg_split('/\s+/', $line, 2);

					$key    = trim(strtolower($key));
					$value  = trim(strtolower($value));

					switch($key) {
					case 'nameserver':

						//

						//
						if ( (self::isIPv4($value) == true)
							|| (self::isIPv6($value) == true)
						) {

							$ns[] = $value;
						} else {

							throw new Net_DNS2_Exception(
								'invalid nameserver entry: ' . $value,
								Net_DNS2_Lookups::E_NS_INVALID_ENTRY
							);
						}
						break;

					case 'domain':
						$this->domain = $value;
						break;

					case 'search':
						$this->search_list = preg_split('/\s+/', $value);
						break;

					case 'options':
						$this->parseOptions($value);
						break;

					default:
						;
					}
				}

				//

				//
				if ( (strlen($this->domain) == 0)
					&& (count($this->search_list) > 0)
				) {
					$this->domain = $this->search_list[0];
				}

			} else {
				throw new Net_DNS2_Exception(
					'resolver file file provided is not readable: ' . $nameservers,
					Net_DNS2_Lookups::E_NS_INVALID_FILE
				);
			}

			//

			//
			if (count($ns) > 0) {
				$this->nameservers = $ns;
			}
		}

		//

		//
		$this->nameservers = array_unique($this->nameservers);

		//

		//
		$this->checkServers();

		return true;
	}

	private function parseOptions($value)
	{
		//

		//
		if ( ($this->use_resolv_options == false) || (strlen($value) == 0) ) {

			return true;
		}

		$options = preg_split('/\s+/', strtolower($value));

		foreach ($options as $option) {

			//

			//
			if ( (strncmp($option, 'timeout', 7) == 0) && (strpos($option, ':') !== false) ) {

				list($key, $val) = explode(':', $option);

				if ( ($val > 0) && ($val <= 30) ) {

					$this->timeout = $val;
				}

			//

			//
			} else if (strncmp($option, 'rotate', 6) == 0) {

				$this->ns_random = true;
			}
		}

		return true;
	}

	protected function checkServers($default = null)
	{
		if (empty($this->nameservers)) {

			if (isset($default)) {

				$this->setServers($default);
			} else {

				throw new Net_DNS2_Exception(
					'empty name servers list; you must provide a list of name '.
					'servers, or the path to a resolv.conf file.',
					Net_DNS2_Lookups::E_NS_INVALID_ENTRY
				);
			}
		}

		return true;
	}

	public function signTSIG(
		$keyname, $signature = '', $algorithm = Net_DNS2_RR_TSIG::HMAC_MD5
	) {
		//

		//
		if ($keyname instanceof Net_DNS2_RR_TSIG) {

			$this->auth_signature = $keyname;

		} else {

			//

			//
			$this->auth_signature = Net_DNS2_RR::fromString(
				strtolower(trim($keyname)) .
				' TSIG '. $signature
			);

			//

			//
			$this->auth_signature->algorithm = $algorithm;
		}

		return true;
	}

	public function signSIG0($filename)
	{
		//

		//
		if (extension_loaded('openssl') === false) {

			throw new Net_DNS2_Exception(
				'the OpenSSL extension is required to use SIG(0).',
				Net_DNS2_Lookups::E_OPENSSL_UNAVAIL
			);
		}

		//

		//
		if ($filename instanceof Net_DNS2_RR_SIG) {

			$this->auth_signature = $filename;

		} else {

			//

			//
			$private = new Net_DNS2_PrivateKey($filename);

			//

			//
			$this->auth_signature = new Net_DNS2_RR_SIG();

			//

			//
			$this->auth_signature->name         = $private->signname;
			$this->auth_signature->ttl          = 0;
			$this->auth_signature->class        = 'ANY';

			//

			//
			$this->auth_signature->algorithm    = $private->algorithm;
			$this->auth_signature->keytag       = $private->keytag;
			$this->auth_signature->signname     = $private->signname;

			//

			//
			$this->auth_signature->typecovered  = 'SIG0';
			$this->auth_signature->labels       = 0;
			$this->auth_signature->origttl      = 0;

			//

			//
			$t = time();

			$this->auth_signature->sigincep     = gmdate('YmdHis', $t);
			$this->auth_signature->sigexp       = gmdate('YmdHis', $t + 500);

			//

			//
			$this->auth_signature->private_key  = $private;
		}

		//

		//
		switch($this->auth_signature->algorithm) {
		case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSAMD5:
		case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSASHA1:
		case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSASHA256:
		case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSASHA512:
		case Net_DNS2_Lookups::DNSSEC_ALGORITHM_DSA:
			break;
		default:
			throw new Net_DNS2_Exception(
				'only asymmetric algorithms work with SIG(0)!',
				Net_DNS2_Lookups::E_OPENSSL_INV_ALGO
			);
		}

		return true;
	}

	public function cacheable($_type)
	{
		switch($_type) {
		case 'AXFR':
		case 'OPT':
			return false;
		}

		return true;
	}

	public static function expandUint32($_int)
	{
		if ( ($_int < 0) && (PHP_INT_MAX == 2147483647) ) {
			return sprintf('%u', $_int);
		} else {
			return $_int;
		}
	}

	public static function isIPv4($_address)
	{
		//

		//
		if (extension_loaded('filter') == true) {

			if (filter_var($_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) == false) {
				return false;
			}
		} else {

			//

			//
			if (inet_pton($_address) === false) {
				return false;
			}

			//

			//
			if (preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $_address) == 0) {
				return false;
			}
		}

		return true;
	}

	public static function isIPv6($_address)
	{
		//

		//
		if (extension_loaded('filter') == true) {
			if (filter_var($_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) == false) {
				return false;
			}
		} else {

			//

			//
			if (inet_pton($_address) === false) {
				return false;
			}

			//

			//
			if (preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $_address) == 1) {
				return false;
			}
		}

		return true;
	}

	public static function expandIPv6($_address)
	{
		$hex = unpack('H*hex', inet_pton($_address));

		return substr(preg_replace('/([A-f0-9]{4})/', "$1:", $hex['hex']), 0, -1);
	}

	protected function sendPacket(Net_DNS2_Packet $request, $use_tcp)
	{
		//

		//
		$data = $request->get();
		if (strlen($data) < Net_DNS2_Lookups::DNS_HEADER_SIZE) {

			throw new Net_DNS2_Exception(
				'invalid or empty packet for sending!',
				Net_DNS2_Lookups::E_PACKET_INVALID,
				null,
				$request
			);
		}

		reset($this->nameservers);

		//

		//
		if ($this->ns_random == true) {

			shuffle($this->nameservers);
		}

		//

		//
		$response = null;
		$ns = '';

		while (1) {

			//

			//
			$ns = current($this->nameservers);
			next($this->nameservers);

			if ($ns === false) {

				if (is_null($this->last_exception) == false) {

					throw $this->last_exception;
				} else {

					throw new Net_DNS2_Exception(
						'every name server provided has failed',
						Net_DNS2_Lookups::E_NS_FAILED
					);
				}
			}

			//

			//
			$max_udp_size = Net_DNS2_Lookups::DNS_MAX_UDP_SIZE;
			if ($this->dnssec == true)
			{
				$max_udp_size = $this->dnssec_payload_size;
			}

			if ( ($use_tcp == true) || (strlen($data) > $max_udp_size) ) {

				try
				{
					$response = $this->sendTCPRequest($ns, $data, ($request->question[0]->qtype == 'AXFR') ? true : false);

				} catch(Net_DNS2_Exception $e) {

					$this->last_exception = $e;
					$this->last_exception_list[$ns] = $e;

					continue;
				}

			//

			//
			} else {

				try
				{
					$response = $this->sendUDPRequest($ns, $data);

					//

					//
					if ($response->header->tc == 1) {

						$response = $this->sendTCPRequest($ns, $data);
					}

				} catch(Net_DNS2_Exception $e) {

					$this->last_exception = $e;
					$this->last_exception_list[$ns] = $e;

					continue;
				}
			}

			//

			//
			if ($request->header->id != $response->header->id) {

				$this->last_exception = new Net_DNS2_Exception(

					'invalid header: the request and response id do not match.',
					Net_DNS2_Lookups::E_HEADER_INVALID,
					null,
					$request,
					$response
				);

				$this->last_exception_list[$ns] = $this->last_exception;
				continue;
			}

			//

			//

			//
			if ($response->header->qr != Net_DNS2_Lookups::QR_RESPONSE) {

				$this->last_exception = new Net_DNS2_Exception(

					'invalid header: the response provided is not a response packet.',
					Net_DNS2_Lookups::E_HEADER_INVALID,
					null,
					$request,
					$response
				);

				$this->last_exception_list[$ns] = $this->last_exception;
				continue;
			}

			//

			//
			if ($response->header->rcode != Net_DNS2_Lookups::RCODE_NOERROR) {

				$this->last_exception = new Net_DNS2_Exception(

					'DNS request failed: ' .
					Net_DNS2_Lookups::$result_code_messages[$response->header->rcode],
					$response->header->rcode,
					null,
					$request,
					$response
				);

				$this->last_exception_list[$ns] = $this->last_exception;
				continue;
			}

			break;
		}

		return $response;
	}

	private function generateError($_proto, $_ns, $_error)
	{
		if (isset($this->sock[$_proto][$_ns]) == false)
		{
			throw new Net_DNS2_Exception('invalid socket referenced', Net_DNS2_Lookups::E_NS_INVALID_SOCKET);
		}

		//

		//
		$last_error = $this->sock[$_proto][$_ns]->last_error;

		//

		//
		$this->sock[$_proto][$_ns]->close();

		//

		//
		unset($this->sock[$_proto][$_ns]);

		//

		//
		throw new Net_DNS2_Exception($last_error, $_error);
	}

	private function sendTCPRequest($_ns, $_data, $_axfr = false)
	{
		//

		//
		$start_time = microtime(true);

		//

		//
		if ( (!isset($this->sock[Net_DNS2_Socket::SOCK_STREAM][$_ns]))
			|| (!($this->sock[Net_DNS2_Socket::SOCK_STREAM][$_ns] instanceof Net_DNS2_Socket))
		) {

			//

			//
			if ($this->sockets_enabled === true) {

				$this->sock[Net_DNS2_Socket::SOCK_STREAM][$_ns] = new Net_DNS2_Socket_Sockets(
					Net_DNS2_Socket::SOCK_STREAM, $_ns, $this->dns_port, $this->timeout
				);

			//

			//
			} else {

				$this->sock[Net_DNS2_Socket::SOCK_STREAM][$_ns] = new Net_DNS2_Socket_Streams(
					Net_DNS2_Socket::SOCK_STREAM, $_ns, $this->dns_port, $this->timeout
				);
			}

			//

			//
			if (strlen($this->local_host) > 0) {

				$this->sock[Net_DNS2_Socket::SOCK_STREAM][$_ns]->bindAddress(
					$this->local_host, $this->local_port
				);
			}

			//

			//
			if ($this->sock[Net_DNS2_Socket::SOCK_STREAM][$_ns]->open() === false) {

				$this->generateError(Net_DNS2_Socket::SOCK_STREAM, $_ns, Net_DNS2_Lookups::E_NS_SOCKET_FAILED);
			}
		}

		//

		//
		if ($this->sock[Net_DNS2_Socket::SOCK_STREAM][$_ns]->write($_data) === false) {

			$this->generateError(Net_DNS2_Socket::SOCK_STREAM, $_ns, Net_DNS2_Lookups::E_NS_SOCKET_FAILED);
		}

		//

		//
		$size = 0;
		$result = null;
		$response = null;

		//

		//
		if ($_axfr == true) {

			$soa_count = 0;

			while (1) {

				//

				//
				$result = $this->sock[Net_DNS2_Socket::SOCK_STREAM][$_ns]->read($size, ($this->dnssec == true) ? $this->dnssec_payload_size : Net_DNS2_Lookups::DNS_MAX_UDP_SIZE);
				if ( ($result === false) || ($size < Net_DNS2_Lookups::DNS_HEADER_SIZE) ) {

					//

					//

					//

					//
					$this->generateError(Net_DNS2_Socket::SOCK_STREAM, $_ns, Net_DNS2_Lookups::E_NS_SOCKET_FAILED);
				}

				//

				//
				$chunk = new Net_DNS2_Packet_Response($result, $size);

				//

				//
				if (is_null($response) == true) {

					$response = clone $chunk;

					//

					//
					if ($response->header->rcode != Net_DNS2_Lookups::RCODE_NOERROR) {
						break;
					}

					//

					//
					foreach ($response->answer as $index => $rr) {

						//

						//
						if ($rr->type == 'SOA') {
							$soa_count++;
						}
					}

					//

					//
					if ($soa_count >= 2) {
						break;
					} else {
						continue;
					}

				} else {

					//

					//
					foreach ($chunk->answer as $index => $rr) {

						//

						//
						if ($rr->type == 'SOA') {
							$soa_count++;
						}

						//

						//
						$response->answer[] = $rr;
					}

					//

					//
					if ($soa_count >= 2) {
						break;
					}
				}
			}

		//

		//
		} else {

			$result = $this->sock[Net_DNS2_Socket::SOCK_STREAM][$_ns]->read($size, ($this->dnssec == true) ? $this->dnssec_payload_size : Net_DNS2_Lookups::DNS_MAX_UDP_SIZE);
			if ( ($result === false) || ($size < Net_DNS2_Lookups::DNS_HEADER_SIZE) ) {

				$this->generateError(Net_DNS2_Socket::SOCK_STREAM, $_ns, Net_DNS2_Lookups::E_NS_SOCKET_FAILED);
			}

			//

			//
			$response = new Net_DNS2_Packet_Response($result, $size);
		}

		//

		//
		$response->response_time = microtime(true) - $start_time;

		//

		//
		$response->answer_from = $_ns;
		$response->answer_socket_type = Net_DNS2_Socket::SOCK_STREAM;

		//

		//
		return $response;
	}

	private function sendUDPRequest($_ns, $_data)
	{
		//

		//
		$start_time = microtime(true);

		//

		//
		if ( (!isset($this->sock[Net_DNS2_Socket::SOCK_DGRAM][$_ns]))
			|| (!($this->sock[Net_DNS2_Socket::SOCK_DGRAM][$_ns] instanceof Net_DNS2_Socket))
		) {

			//

			//
			if ($this->sockets_enabled === true) {

				$this->sock[Net_DNS2_Socket::SOCK_DGRAM][$_ns] = new Net_DNS2_Socket_Sockets(
					Net_DNS2_Socket::SOCK_DGRAM, $_ns, $this->dns_port, $this->timeout
				);

			//

			//
			} else {

				$this->sock[Net_DNS2_Socket::SOCK_DGRAM][$_ns] = new Net_DNS2_Socket_Streams(
					Net_DNS2_Socket::SOCK_DGRAM, $_ns, $this->dns_port, $this->timeout
				);
			}

			//

			//
			if (strlen($this->local_host) > 0) {

				$this->sock[Net_DNS2_Socket::SOCK_DGRAM][$_ns]->bindAddress(
					$this->local_host, $this->local_port
				);
			}

			//

			//
			if ($this->sock[Net_DNS2_Socket::SOCK_DGRAM][$_ns]->open() === false) {

				$this->generateError(Net_DNS2_Socket::SOCK_DGRAM, $_ns, Net_DNS2_Lookups::E_NS_SOCKET_FAILED);
			}
		}

		//

		//
		if ($this->sock[Net_DNS2_Socket::SOCK_DGRAM][$_ns]->write($_data) === false) {

			$this->generateError(Net_DNS2_Socket::SOCK_DGRAM, $_ns, Net_DNS2_Lookups::E_NS_SOCKET_FAILED);
		}

		//

		//
		$size = 0;

		$result = $this->sock[Net_DNS2_Socket::SOCK_DGRAM][$_ns]->read($size, ($this->dnssec == true) ? $this->dnssec_payload_size : Net_DNS2_Lookups::DNS_MAX_UDP_SIZE);
		if (( $result === false) || ($size < Net_DNS2_Lookups::DNS_HEADER_SIZE)) {

			$this->generateError(Net_DNS2_Socket::SOCK_DGRAM, $_ns, Net_DNS2_Lookups::E_NS_SOCKET_FAILED);
		}

		//

		//
		$response = new Net_DNS2_Packet_Response($result, $size);

		//

		//
		$response->response_time = microtime(true) - $start_time;

		//

		//
		$response->answer_from = $_ns;
		$response->answer_socket_type = Net_DNS2_Socket::SOCK_DGRAM;

		//

		//
		return $response;
	}
}

?><?php

class Net_DNS2_BitMap
{

	public static function bitMapToArray($data)
	{
		if (strlen($data) == 0) {
			return array();
		}

		$output = array();
		$offset = 0;
		$length = strlen($data);

		while ($offset < $length) {

			//

			//
			$x = unpack('@' . $offset . '/Cwindow/Clength', $data);
			$offset += 2;

			//

			//
			$bitmap = unpack('C*', substr($data, $offset, $x['length']));
			$offset += $x['length'];

			//

			//
			$bitstr = '';
			foreach ($bitmap as $r) {

				$bitstr .= sprintf('%08b', $r);
			}

			$blen = strlen($bitstr);
			for ($i=0; $i<$blen; $i++) {

				if ($bitstr[$i] == '1') {

					$type = $x['window'] * 256 + $i;

					if (isset(Net_DNS2_Lookups::$rr_types_by_id[$type])) {

						$output[] = Net_DNS2_Lookups::$rr_types_by_id[$type];
					} else {

						$output[] = 'TYPE' . $type;
					}
				}
			}
		}

		return $output;
	}

	public static function arrayToBitMap(array $data)
	{
		if (count($data) == 0) {
			return '';
		}

		$current_window = 0;

		//

		//
		$max = 0;
		$bm = array();

		foreach ($data as $rr) {

			$rr = strtoupper($rr);

			//

			//
			$type = @Net_DNS2_Lookups::$rr_types_by_name[$rr];
			if (isset($type)) {

				//

				//
				if ( (isset(Net_DNS2_Lookups::$rr_qtypes_by_id[$type]))
					|| (isset(Net_DNS2_Lookups::$rr_metatypes_by_id[$type]))
				) {
					continue;
				}

			} else {

				//

				//
				list($name, $type) = explode('TYPE', $rr);
				if (!isset($type)) {

					continue;
				}
			}

			//

			//
			$current_window = (int)($type / 256);

			$val = $type - $current_window * 256.0;
			if ($val > $max) {
				$max = $val;
			}

			$bm[$current_window][$val] = 1;
			$bm[$current_window]['length'] = ceil(($max + 1) / 8);
		}

		$output = '';

		foreach ($bm as $window => $bitdata) {

			$bitstr = '';

			for ($i=0; $i<$bm[$window]['length'] * 8; $i++) {
				if (isset($bm[$window][$i])) {
					$bitstr .= '1';
				} else {
					$bitstr .= '0';
				}
			}

			$output .= pack('CC', $window, $bm[$window]['length']);
			$output .= pack('H*', self::bigBaseConvert($bitstr));
		}

		return $output;
	}

	public static function bigBaseConvert($number)
	{
		$result = '';

		$bin = substr(chunk_split(strrev($number), 4, '-'), 0, -1);
		$temp = preg_split('[-]', $bin, -1, PREG_SPLIT_DELIM_CAPTURE);

		for ($i = count($temp)-1;$i >= 0;$i--) {

			$result = $result . base_convert(strrev($temp[$i]), 2, 16);
		}

		return strtoupper($result);
	}
}

?><?php

class Net_DNS2_Cache
{

	protected $cache_file = '';

	protected $cache_data = array();

	protected $cache_size = 0;

	protected $cache_serializer;

	protected $cache_opened = false;

	public function has($key)
	{
		return isset($this->cache_data[$key]);
	}

	public function get($key)
	{
		if (isset($this->cache_data[$key])) {

			if ($this->cache_serializer == 'json') {
				return json_decode($this->cache_data[$key]['object']);
			} else {
				return unserialize($this->cache_data[$key]['object']);
			}
		} else {

			return false;
		}
	}

	public function put($key, $data)
	{
		$ttl = 86400 * 365;

		//

		//
		$data->rdata = '';
		$data->rdlength = 0;

		//

		//

		//
		foreach ($data->answer as $index => $rr) {

			if ($rr->ttl < $ttl) {
				$ttl = $rr->ttl;
			}

			$rr->rdata = '';
			$rr->rdlength = 0;
		}
		foreach ($data->authority as $index => $rr) {

			if ($rr->ttl < $ttl) {
				$ttl = $rr->ttl;
			}

			$rr->rdata = '';
			$rr->rdlength = 0;
		}
		foreach ($data->additional as $index => $rr) {

			if ($rr->ttl < $ttl) {
				$ttl = $rr->ttl;
			}

			$rr->rdata = '';
			$rr->rdlength = 0;
		}

		$this->cache_data[$key] = array(

			'cache_date'    => time(),
			'ttl'           => $ttl
		);

		if ($this->cache_serializer == 'json') {
			$this->cache_data[$key]['object'] = json_encode($data);
		} else {
			$this->cache_data[$key]['object'] = serialize($data);
		}
	}

	protected function clean()
	{
		if (count($this->cache_data) > 0) {

			//

			//
			$now = time();

			foreach ($this->cache_data as $key => $data) {

				$diff = $now - $data['cache_date'];

				if ($data['ttl'] <= $diff) {

					unset($this->cache_data[$key]);
				} else {

					$this->cache_data[$key]['ttl'] -= $diff;
					$this->cache_data[$key]['cache_date'] = $now;
				}
			}
		}
	}

	protected function resize()
	{
		if (count($this->cache_data) > 0) {

			//

			//
			if ($this->cache_serializer == 'json') {
				$cache = json_encode($this->cache_data);
			} else {
				$cache = serialize($this->cache_data);
			}

			//

			//
			if (strlen($cache) > $this->cache_size) {

				while (strlen($cache) > $this->cache_size) {

					//

					//
					$smallest_ttl = time();
					$smallest_key = null;

					foreach ($this->cache_data as $key => $data) {

						if ($data['ttl'] < $smallest_ttl) {

							$smallest_ttl = $data['ttl'];
							$smallest_key = $key;
						}
					}

					//

					//
					unset($this->cache_data[$smallest_key]);

					//

					//
					if ($this->cache_serializer == 'json') {
						$cache = json_encode($this->cache_data);
					} else {
						$cache = serialize($this->cache_data);
					}
				}
			}

			if ( ($cache == 'a:0:{}') || ($cache == '{}') ) {
				return null;
			} else {
				return $cache;
			}
		}

		return null;
	}
};

?><?php

class Net_DNS2_Exception extends Exception
{
	private $_request;
	private $_response;

	public function __construct(
		$message = '',
		$code = 0,
		$previous = null,
		Net_DNS2_Packet_Request $request = null,
		Net_DNS2_Packet_Response $response = null
	) {
		//

		//
		$this->_request = $request;
		$this->_response = $response;

		//

		//

		//

		//
		if (version_compare(PHP_VERSION, '5.3.0', '>=') == true) {

			parent::__construct($message, $code, $previous);
		} else {

			parent::__construct($message, $code);
		}
	}

	public function getRequest()
	{
		return $this->_request;
	}

	public function getResponse()
	{
		return $this->_response;
	}
}

?><?php

class Net_DNS2_Header
{
	public $id;
	public $qr;
	public $opcode;
	public $aa;
	public $tc;
	public $rd;
	public $ra;
	public $z;
	public $ad;
	public $cd;
	public $rcode;
	public $qdcount;
	public $ancount;
	public $nscount;
	public $arcount;

	public function __construct(Net_DNS2_Packet &$packet = null)
	{
		if (!is_null($packet)) {

			$this->set($packet);
		} else {

			$this->id       = $this->nextPacketId();
			$this->qr       = Net_DNS2_Lookups::QR_QUERY;
			$this->opcode   = Net_DNS2_Lookups::OPCODE_QUERY;
			$this->aa       = 0;
			$this->tc       = 0;
			$this->rd       = 1;
			$this->ra       = 0;
			$this->z        = 0;
			$this->ad       = 0;
			$this->cd       = 0;
			$this->rcode    = Net_DNS2_Lookups::RCODE_NOERROR;
			$this->qdcount  = 1;
			$this->ancount  = 0;
			$this->nscount  = 0;
			$this->arcount  = 0;
		}
	}

	public function nextPacketId()
	{
		if (++Net_DNS2_Lookups::$next_packet_id > 65535) {

			Net_DNS2_Lookups::$next_packet_id = 1;
		}

		return Net_DNS2_Lookups::$next_packet_id;
	}

	public function __toString()
	{
		$output = ";;\n;; Header:\n";

		$output .= ";;\t id         = " . $this->id . "\n";
		$output .= ";;\t qr         = " . $this->qr . "\n";
		$output .= ";;\t opcode     = " . $this->opcode . "\n";
		$output .= ";;\t aa         = " . $this->aa . "\n";
		$output .= ";;\t tc         = " . $this->tc . "\n";
		$output .= ";;\t rd         = " . $this->rd . "\n";
		$output .= ";;\t ra         = " . $this->ra . "\n";
		$output .= ";;\t z          = " . $this->z . "\n";
		$output .= ";;\t ad         = " . $this->ad . "\n";
		$output .= ";;\t cd         = " . $this->cd . "\n";
		$output .= ";;\t rcode      = " . $this->rcode . "\n";
		$output .= ";;\t qdcount    = " . $this->qdcount . "\n";
		$output .= ";;\t ancount    = " . $this->ancount . "\n";
		$output .= ";;\t nscount    = " . $this->nscount . "\n";
		$output .= ";;\t arcount    = " . $this->arcount . "\n";

		return $output;
	}

	public function set(Net_DNS2_Packet &$packet)
	{
		//

		//
		if ($packet->rdlength < Net_DNS2_Lookups::DNS_HEADER_SIZE) {

			throw new Net_DNS2_Exception(
				'invalid header data provided; to small',
				Net_DNS2_Lookups::E_HEADER_INVALID
			);
		}

		$offset = 0;

		//

		//
		$this->id       = ord($packet->rdata[$offset]) << 8 |
			ord($packet->rdata[++$offset]);

		++$offset;
		$this->qr       = (ord($packet->rdata[$offset]) >> 7) & 0x1;
		$this->opcode   = (ord($packet->rdata[$offset]) >> 3) & 0xf;
		$this->aa       = (ord($packet->rdata[$offset]) >> 2) & 0x1;
		$this->tc       = (ord($packet->rdata[$offset]) >> 1) & 0x1;
		$this->rd       = ord($packet->rdata[$offset]) & 0x1;

		++$offset;
		$this->ra       = (ord($packet->rdata[$offset]) >> 7) & 0x1;
		$this->z        = (ord($packet->rdata[$offset]) >> 6) & 0x1;
		$this->ad       = (ord($packet->rdata[$offset]) >> 5) & 0x1;
		$this->cd       = (ord($packet->rdata[$offset]) >> 4) & 0x1;
		$this->rcode    = ord($packet->rdata[$offset]) & 0xf;

		$this->qdcount  = ord($packet->rdata[++$offset]) << 8 |
			ord($packet->rdata[++$offset]);
		$this->ancount  = ord($packet->rdata[++$offset]) << 8 |
			ord($packet->rdata[++$offset]);
		$this->nscount  = ord($packet->rdata[++$offset]) << 8 |
			ord($packet->rdata[++$offset]);
		$this->arcount  = ord($packet->rdata[++$offset]) << 8 |
			ord($packet->rdata[++$offset]);

		//

		//
		$packet->offset += Net_DNS2_Lookups::DNS_HEADER_SIZE;

		return true;
	}

	public function get(Net_DNS2_Packet &$packet)
	{
		$packet->offset += Net_DNS2_Lookups::DNS_HEADER_SIZE;

		return pack('n', $this->id) .
			chr(
				($this->qr << 7) | ($this->opcode << 3) |
				($this->aa << 2) | ($this->tc << 1) | ($this->rd)
			) .
			chr(
				($this->ra << 7) | ($this->ad << 5) | ($this->cd << 4) | $this->rcode
			) .
			pack('n4', $this->qdcount, $this->ancount, $this->nscount, $this->arcount);
	}
}

?><?php

//

//
Net_DNS2_Lookups::$next_packet_id   = mt_rand(0, 65535);

//

//
Net_DNS2_Lookups::$rr_types_by_id       = array_flip(Net_DNS2_Lookups::$rr_types_by_name);
Net_DNS2_Lookups::$classes_by_id        = array_flip(Net_DNS2_Lookups::$classes_by_name);
Net_DNS2_Lookups::$rr_types_class_to_id = array_flip(Net_DNS2_Lookups::$rr_types_id_to_class);
Net_DNS2_Lookups::$algorithm_name_to_id = array_flip(Net_DNS2_Lookups::$algorithm_id_to_name);
Net_DNS2_Lookups::$digest_name_to_id    = array_flip(Net_DNS2_Lookups::$digest_id_to_name);
Net_DNS2_Lookups::$rr_qtypes_by_id      = array_flip(Net_DNS2_Lookups::$rr_qtypes_by_name);
Net_DNS2_Lookups::$rr_metatypes_by_id   = array_flip(Net_DNS2_Lookups::$rr_metatypes_by_name);
Net_DNS2_Lookups::$protocol_by_id       = array_flip(Net_DNS2_Lookups::$protocol_by_name);

class Net_DNS2_Lookups
{

	const DNS_HEADER_SIZE       = 12;

	const DNS_MAX_UDP_SIZE      = 512;

	const QR_QUERY              = 0;
	const QR_RESPONSE           = 1;

	const OPCODE_QUERY          = 0;
	const OPCODE_IQUERY         = 1;
	const OPCODE_STATUS         = 2;
	const OPCODE_NOTIFY         = 4;
	const OPCODE_UPDATE         = 5;

	const RR_CLASS_IN           = 1;
	const RR_CLASS_CH           = 3;
	const RR_CLASS_HS           = 4;
	const RR_CLASS_NONE         = 254;
	const RR_CLASS_ANY          = 255;

	const RCODE_NOERROR         = 0;
	const RCODE_FORMERR         = 1;
	const RCODE_SERVFAIL        = 2;
	const RCODE_NXDOMAIN        = 3;
	const RCODE_NOTIMP          = 4;
	const RCODE_REFUSED         = 5;
	const RCODE_YXDOMAIN        = 6;
	const RCODE_YXRRSET         = 7;
	const RCODE_NXRRSET         = 8;
	const RCODE_NOTAUTH         = 9;
	const RCODE_NOTZONE         = 10;

	const RCODE_BADSIG          = 16;
	const RCODE_BADVERS         = 16;
	const RCODE_BADKEY          = 17;
	const RCODE_BADTIME         = 18;
	const RCODE_BADMODE         = 19;
	const RCODE_BADNAME         = 20;
	const RCODE_BADALG          = 21;
	const RCODE_BADTRUNC        = 22;
	const RCODE_BADCOOKIE       = 23;

	const E_NONE                = 0;
	const E_DNS_FORMERR         = self::RCODE_FORMERR;
	const E_DNS_SERVFAIL        = self::RCODE_SERVFAIL;
	const E_DNS_NXDOMAIN        = self::RCODE_NXDOMAIN;
	const E_DNS_NOTIMP          = self::RCODE_NOTIMP;
	const E_DNS_REFUSED         = self::RCODE_REFUSED;
	const E_DNS_YXDOMAIN        = self::RCODE_YXDOMAIN;
	const E_DNS_YXRRSET         = self::RCODE_YXRRSET;
	const E_DNS_NXRRSET         = self::RCODE_NXRRSET;
	const E_DNS_NOTAUTH         = self::RCODE_NOTAUTH;
	const E_DNS_NOTZONE         = self::RCODE_NOTZONE;

	const E_DNS_BADSIG          = self::RCODE_BADSIG;
	const E_DNS_BADKEY          = self::RCODE_BADKEY;
	const E_DNS_BADTIME         = self::RCODE_BADTIME;
	const E_DNS_BADMODE         = self::RCODE_BADMODE;
	const E_DNS_BADNAME         = self::RCODE_BADNAME;
	const E_DNS_BADALG          = self::RCODE_BADALG;
	const E_DNS_BADTRUNC        = self::RCODE_BADTRUNC;
	const E_DNS_BADCOOKIE       = self::RCODE_BADCOOKIE;

	const E_NS_INVALID_FILE     = 200;
	const E_NS_INVALID_ENTRY    = 201;
	const E_NS_FAILED           = 202;
	const E_NS_SOCKET_FAILED    = 203;
	const E_NS_INVALID_SOCKET   = 204;

	const E_PACKET_INVALID      = 300;
	const E_PARSE_ERROR         = 301;
	const E_HEADER_INVALID      = 302;
	const E_QUESTION_INVALID    = 303;
	const E_RR_INVALID          = 304;

	const E_OPENSSL_ERROR       = 400;
	const E_OPENSSL_UNAVAIL     = 401;
	const E_OPENSSL_INV_PKEY    = 402;
	const E_OPENSSL_INV_ALGO    = 403;

	const E_CACHE_UNSUPPORTED   = 500;
	const E_CACHE_SHM_FILE      = 501;
	const E_CACHE_SHM_UNAVAIL   = 502;

	const EDNS0_OPT_LLQ             = 1;
	const EDNS0_OPT_UL              = 2;
	const EDNS0_OPT_NSID            = 3;

	const EDNS0_OPT_DAU             = 5;
	const EDNS0_OPT_DHU             = 6;
	const EDNS0_OPT_N3U             = 7;
	const EDNS0_OPT_CLIENT_SUBNET   = 8;
	const EDNS0_OPT_EXPIRE          = 9;
	const EDNS0_OPT_COOKIE          = 10;
	const EDNS0_OPT_TCP_KEEPALIVE   = 11;
	const EDNS0_OPT_PADDING         = 12;
	const EDNS0_OPT_CHAIN           = 13;
	const EDNS0_OPT_KEY_TAG         = 14;

	const DNSSEC_ALGORITHM_RES                  = 0;
	const DNSSEC_ALGORITHM_RSAMD5               = 1;
	const DNSSEC_ALGORITHM_DH                   = 2;
	const DNSSEC_ALGORITHM_DSA                  = 3;
	const DNSSEC_ALGORITHM_ECC                  = 4;
	const DNSSEC_ALGORITHM_RSASHA1              = 5;
	const DNSSEC_ALGORITHM_DSANSEC3SHA1         = 6;
	const DSNSEC_ALGORITHM_RSASHA1NSEC3SHA1     = 7;
	const DNSSEC_ALGORITHM_RSASHA256	        = 8;
	const DNSSEC_ALGORITHM_RSASHA512            = 10;
	const DNSSEC_ALGORITHM_ECCGOST              = 12;
	const DNSSEC_ALGORITHM_ECDSAP256SHA256      = 13;
	const DNSSEC_ALGORITHM_ECDSAP384SHA384      = 14;
	const DNSSEC_ALGORITHM_ED25519              = 15;
	const DNSSEC_ALGORITHM_ED448                = 16;
	const DNSSEC_ALGORITHM_INDIRECT             = 252;
	const DNSSEC_ALGORITHM_PRIVATEDNS           = 253;
	const DNSSEC_ALGORITHM_PRIVATEOID           = 254;

	const DNSSEC_DIGEST_RES                     = 0;
	const DNSSEC_DIGEST_SHA1                    = 1;

	public static $next_packet_id;

	public static $rr_types_by_id = array();
	public static $rr_types_by_name = array(

		'SIG0'          => 0,
		'A'             => 1,
		'NS'            => 2,
		'MD'            => 3,
		'MF'            => 4,
		'CNAME'         => 5,
		'SOA'           => 6,
		'MB'            => 7,
		'MG'            => 8,
		'MR'            => 9,
		'NULL'          => 10,
		'WKS'           => 11,
		'PTR'           => 12,
		'HINFO'         => 13,
		'MINFO'         => 14,
		'MX'            => 15,
		'TXT'           => 16,
		'RP'            => 17,
		'AFSDB'         => 18,
		'X25'           => 19,
		'ISDN'          => 20,
		'RT'            => 21,
		'NSAP'          => 22,
		'NSAP_PTR'      => 23,
		'SIG'           => 24,
		'KEY'           => 25,
		'PX'            => 26,
		'GPOS'          => 27,
		'AAAA'          => 28,
		'LOC'           => 29,
		'NXT'           => 30,
		'EID'           => 31,
		'NIMLOC'        => 32,
		'SRV'           => 33,
		'ATMA'          => 34,
		'NAPTR'         => 35,
		'KX'            => 36,
		'CERT'          => 37,
		'A6'            => 38,
		'DNAME'         => 39,
		'SINK'          => 40,
		'OPT'           => 41,
		'APL'           => 42,
		'DS'            => 43,
		'SSHFP'         => 44,
		'IPSECKEY'      => 45,
		'RRSIG'         => 46,
		'NSEC'          => 47,
		'DNSKEY'        => 48,
		'DHCID'         => 49,
		'NSEC3'         => 50,
		'NSEC3PARAM'    => 51,
		'TLSA'          => 52,
		'SMIMEA'        => 53,

		'HIP'           => 55,
		'NINFO'         => 56,
		'RKEY'          => 57,
		'TALINK'        => 58,      //
		'CDS'           => 59,
		'CDNSKEY'       => 60,
		'OPENPGPKEY'    => 61,
		'CSYNC'         => 62,

		'SPF'           => 99,
		'UINFO'         => 100,
		'UID'           => 101,
		'GID'           => 102,
		'UNSPEC'        => 103,
		'NID'           => 104,
		'L32'           => 105,
		'L64'           => 106,
		'LP'            => 107,
		'EUI48'         => 108,
		'EUI64'         => 109,

		'TKEY'          => 249,
		'TSIG'          => 250,
		'IXFR'          => 251,
		'AXFR'          => 252,
		'MAILB'         => 253,
		'MAILA'         => 254,
		'ANY'           => 255,
		'URI'           => 256,
		'CAA'           => 257,
		'AVC'           => 258,

		'TA'            => 32768,
		'DLV'           => 32769,
		'TYPE65534'     => 65534
	);

	public static $rr_qtypes_by_id = array();
	public static $rr_qtypes_by_name = array(

		'IXFR'          => 251,
		'AXFR'          => 252,
		'MAILB'         => 253,
		'MAILA'         => 254,
		'ANY'           => 255
	);

	public static $rr_metatypes_by_id = array();
	public static $rr_metatypes_by_name = array(

		'OPT'           => 41,
		'TKEY'          => 249,
		'TSIG'          => 250
	);

	public static $rr_types_class_to_id = array();
	public static $rr_types_id_to_class = array(

		1           => 'Net_DNS2_RR_A',
		2           => 'Net_DNS2_RR_NS',
		5           => 'Net_DNS2_RR_CNAME',
		6           => 'Net_DNS2_RR_SOA',
		11          => 'Net_DNS2_RR_WKS',
		12          => 'Net_DNS2_RR_PTR',
		13          => 'Net_DNS2_RR_HINFO',
		15          => 'Net_DNS2_RR_MX',
		16          => 'Net_DNS2_RR_TXT',
		17          => 'Net_DNS2_RR_RP',
		18          => 'Net_DNS2_RR_AFSDB',
		19          => 'Net_DNS2_RR_X25',
		20          => 'Net_DNS2_RR_ISDN',
		21          => 'Net_DNS2_RR_RT',
		22          => 'Net_DNS2_RR_NSAP',
		24          => 'Net_DNS2_RR_SIG',
		25          => 'Net_DNS2_RR_KEY',
		26          => 'Net_DNS2_RR_PX',
		28          => 'Net_DNS2_RR_AAAA',
		29          => 'Net_DNS2_RR_LOC',
		31          => 'Net_DNS2_RR_EID',
		32          => 'Net_DNS2_RR_NIMLOC',
		33          => 'Net_DNS2_RR_SRV',
		34          => 'Net_DNS2_RR_ATMA',
		35          => 'Net_DNS2_RR_NAPTR',
		36          => 'Net_DNS2_RR_KX',
		37          => 'Net_DNS2_RR_CERT',
		39          => 'Net_DNS2_RR_DNAME',
		41          => 'Net_DNS2_RR_OPT',
		42          => 'Net_DNS2_RR_APL',
		43          => 'Net_DNS2_RR_DS',
		44          => 'Net_DNS2_RR_SSHFP',
		45          => 'Net_DNS2_RR_IPSECKEY',
		46          => 'Net_DNS2_RR_RRSIG',
		47          => 'Net_DNS2_RR_NSEC',
		48          => 'Net_DNS2_RR_DNSKEY',
		49          => 'Net_DNS2_RR_DHCID',
		50          => 'Net_DNS2_RR_NSEC3',
		51          => 'Net_DNS2_RR_NSEC3PARAM',
		52          => 'Net_DNS2_RR_TLSA',
		53          => 'Net_DNS2_RR_SMIMEA',
		55          => 'Net_DNS2_RR_HIP',
		58          => 'Net_DNS2_RR_TALINK',
		59          => 'Net_DNS2_RR_CDS',
		60          => 'Net_DNS2_RR_CDNSKEY',
		61          => 'Net_DNS2_RR_OPENPGPKEY',
		62          => 'Net_DNS2_RR_CSYNC',
		99          => 'Net_DNS2_RR_SPF',
		104         => 'Net_DNS2_RR_NID',
		105         => 'Net_DNS2_RR_L32',
		106         => 'Net_DNS2_RR_L64',
		107         => 'Net_DNS2_RR_LP',
		108         => 'Net_DNS2_RR_EUI48',
		109         => 'Net_DNS2_RR_EUI64',

		249         => 'Net_DNS2_RR_TKEY',
		250         => 'Net_DNS2_RR_TSIG',

		255         => 'Net_DNS2_RR_ANY',
		256         => 'Net_DNS2_RR_URI',
		257         => 'Net_DNS2_RR_CAA',
		258         => 'Net_DNS2_RR_AVC',
		32768       => 'Net_DNS2_RR_TA',
		32769       => 'Net_DNS2_RR_DLV',
		65534       => 'Net_DNS2_RR_TYPE65534'
	);

	public static $classes_by_id = array();
	public static $classes_by_name = array(

		'IN'    => self::RR_CLASS_IN,
		'CH'    => self::RR_CLASS_CH,
		'HS'    => self::RR_CLASS_HS,
		'NONE'  => self::RR_CLASS_NONE,
		'ANY'   => self::RR_CLASS_ANY
	);

	public static $result_code_messages = array(

		self::RCODE_NOERROR     => 'The request completed successfully.',
		self::RCODE_FORMERR     => 'The name server was unable to interpret the query.',
		self::RCODE_SERVFAIL    => 'The name server was unable to process this query due to a problem with the name server.',
		self::RCODE_NXDOMAIN    => 'The domain name referenced in the query does not exist.',
		self::RCODE_NOTIMP      => 'The name server does not support the requested kind of query.',
		self::RCODE_REFUSED     => 'The name server refuses to perform the specified operation for policy reasons.',
		self::RCODE_YXDOMAIN    => 'Name Exists when it should not.',
		self::RCODE_YXRRSET     => 'RR Set Exists when it should not.',
		self::RCODE_NXRRSET     => 'RR Set that should exist does not.',
		self::RCODE_NOTAUTH     => 'Server Not Authoritative for zone.',
		self::RCODE_NOTZONE     => 'Name not contained in zone.',

		self::RCODE_BADSIG      => 'TSIG Signature Failure.',
		self::RCODE_BADKEY      => 'Key not recognized.',
		self::RCODE_BADTIME     => 'Signature out of time window.',
		self::RCODE_BADMODE     => 'Bad TKEY Mode.',
		self::RCODE_BADNAME     => 'Duplicate key name.',
		self::RCODE_BADALG      => 'Algorithm not supported.',
		self::RCODE_BADTRUNC    => 'Bad truncation.'
	);

	public static $algorithm_name_to_id = array();
	public static $algorithm_id_to_name = array(

		self::DNSSEC_ALGORITHM_RES                  => 'RES',
		self::DNSSEC_ALGORITHM_RSAMD5               => 'RSAMD5',
		self::DNSSEC_ALGORITHM_DH                   => 'DH',
		self::DNSSEC_ALGORITHM_DSA                  => 'DSA',
		self::DNSSEC_ALGORITHM_ECC                  => 'ECC',
		self::DNSSEC_ALGORITHM_RSASHA1              => 'RSASHA1',
		self::DNSSEC_ALGORITHM_DSANSEC3SHA1         => 'DSA-NSEC3-SHA1',
		self::DSNSEC_ALGORITHM_RSASHA1NSEC3SHA1     => 'RSASHA1-NSEC3-SHA1',
		self::DNSSEC_ALGORITHM_RSASHA256            => 'RSASHA256',
		self::DNSSEC_ALGORITHM_RSASHA512            => 'RSASHA512',
		self::DNSSEC_ALGORITHM_ECCGOST              => 'ECC-GOST',
		self::DNSSEC_ALGORITHM_ECDSAP256SHA256      => 'ECDSAP256SHA256',
		self::DNSSEC_ALGORITHM_ECDSAP384SHA384      => 'ECDSAP384SHA384',
		self::DNSSEC_ALGORITHM_ED25519              => 'ED25519',
		self::DNSSEC_ALGORITHM_ED448                => 'ED448',
		self::DNSSEC_ALGORITHM_INDIRECT             => 'INDIRECT',
		self::DNSSEC_ALGORITHM_PRIVATEDNS           => 'PRIVATEDNS',
		self::DNSSEC_ALGORITHM_PRIVATEOID           => 'PRIVATEOID'
	);

	public static $digest_name_to_id = array();
	public static $digest_id_to_name = array(

		self::DNSSEC_DIGEST_RES         => 'RES',
		self::DNSSEC_DIGEST_SHA1        => 'SHA-1'
	);

	public static $protocol_by_id = array();
	public static $protocol_by_name = array(

		'ICMP'          => 1,
		'IGMP'          => 2,
		'GGP'           => 3,
		'ST'            => 5,
		'TCP'           => 6,
		'UCL'           => 7,
		'EGP'           => 8,
		'IGP'           => 9,
		'BBN-RCC-MON'   => 10,
		'NVP-II'        => 11,
		'PUP'           => 12,
		'ARGUS'         => 13,
		'EMCON'         => 14,
		'XNET'          => 15,
		'CHAOS'         => 16,
		'UDP'           => 17,
		'MUX'           => 18,
		'DCN-MEAS'      => 19,
		'HMP'           => 20,
		'PRM'           => 21,
		'XNS-IDP'       => 22,
		'TRUNK-1'       => 23,
		'TRUNK-2'       => 24,
		'LEAF-1'        => 25,
		'LEAF-2'        => 26,
		'RDP'           => 27,
		'IRTP'          => 28,
		'ISO-TP4'       => 29,
		'NETBLT'        => 30,
		'MFE-NSP'       => 31,
		'MERIT-INP'     => 32,
		'SEP'           => 33,

		'CFTP'          => 62,

		'SAT-EXPAK'     => 64,
		'MIT-SUBNET'    => 65,
		'RVD'           => 66,
		'IPPC'          => 67,

		'SAT-MON'       => 69,

		'IPCV'          => 71,

		'BR-SAT-MON'    => 76,

		'WB-MON'        => 78,
		'WB-EXPAK'      => 79

	);
}

?><?php

class Net_DNS2_Packet
{

	public $rdata;
	public $rdlength;

	public $offset = 0;

	public $header;

	public $question = array();

	public $answer = array();

	public $authority = array();

	public $additional = array();

	private $_compressed = array();

	public function __toString()
	{
		$output = $this->header->__toString();

		foreach ($this->question as $x) {

			$output .= $x->__toString() . "\n";
		}
		foreach ($this->answer as $x) {

			$output .= $x->__toString() . "\n";
		}
		foreach ($this->authority as $x) {

			$output .= $x->__toString() . "\n";
		}
		foreach ($this->additional as $x) {

			$output .= $x->__toString() . "\n";
		}

		return $output;
	}

	public function get()
	{
		$data = $this->header->get($this);

		foreach ($this->question as $x) {

			$data .= $x->get($this);
		}
		foreach ($this->answer as $x) {

			$data .= $x->get($this);
		}
		foreach ($this->authority as $x) {

			$data .= $x->get($this);
		}
		foreach ($this->additional as $x) {

			$data .= $x->get($this);
		}

		return $data;
	}

	public function compress($name, &$offset)
	{
		$names    = explode('.', $name);
		$compname = '';

		while (!empty($names)) {

			$dname = join('.', $names);

			if (isset($this->_compressed[$dname])) {

				$compname .= pack('n', 0xc000 | $this->_compressed[$dname]);
				$offset += 2;

				break;
			}

			$this->_compressed[$dname] = $offset;
			$first = array_shift($names);

			$length = strlen($first);
			if ($length <= 0) {
				continue;
			}

			//

			//
			if ($length > 63) {

				$length = 63;
				$first = substr($first, 0, $length);
			}

			$compname .= pack('Ca*', $length, $first);
			$offset += $length + 1;
		}

		if (empty($names)) {

			$compname .= pack('C', 0);
			$offset++;
		}

		return $compname;
	}

	public static function pack($name)
	{
		$offset = 0;
		$names = explode('.', $name);
		$compname = '';

		while (!empty($names)) {

			$first = array_shift($names);
			$length = strlen($first);

			$compname .= pack('Ca*', $length, $first);
			$offset += $length + 1;
		}

		$compname .= "\0";
		$offset++;

		return $compname;
	}

	public static function expand(Net_DNS2_Packet &$packet, &$offset)
	{
		$name = '';

		while (1) {
			if ($packet->rdlength < ($offset + 1)) {
				return null;
			}

			$xlen = ord($packet->rdata[$offset]);
			if ($xlen == 0) {

				++$offset;
				break;

			} else if (($xlen & 0xc0) == 0xc0) {
				if ($packet->rdlength < ($offset + 2)) {

					return null;
				}

				$ptr = ord($packet->rdata[$offset]) << 8 |
					ord($packet->rdata[$offset+1]);
				$ptr = $ptr & 0x3fff;

				$name2 = Net_DNS2_Packet::expand($packet, $ptr);
				if (is_null($name2)) {

					return null;
				}

				$name .= $name2;
				$offset += 2;

				break;
			} else {
				++$offset;

				if ($packet->rdlength < ($offset + $xlen)) {

					return null;
				}

				$elem = '';
				$elem = substr($packet->rdata, $offset, $xlen);
				$name .= $elem . '.';
				$offset += $xlen;
			}
		}

		return trim($name, '.');
	}

	public static function label(Net_DNS2_Packet &$packet, &$offset)
	{
		$name = '';

		if ($packet->rdlength < ($offset + 1)) {

			return null;
		}

		$xlen = ord($packet->rdata[$offset]);
		++$offset;

		if (($xlen + $offset) > $packet->rdlength) {

			$name = substr($packet->rdata, $offset);
			$offset = $packet->rdlength;
		} else {

			$name = substr($packet->rdata, $offset, $xlen);
			$offset += $xlen;
		}

		return $name;
	}

	public function copy(Net_DNS2_Packet $packet)
	{
		$this->header       = $packet->header;
		$this->question     = $packet->question;
		$this->answer       = $packet->answer;
		$this->authority    = $packet->authority;
		$this->additional   = $packet->additional;

		return true;
	}

	public function reset()
	{
		$this->header->id   = $this->header->nextPacketId();
		$this->rdata        = '';
		$this->rdlength     = 0;
		$this->offset       = 0;
		$this->answer       = array();
		$this->authority    = array();
		$this->additional   = array();
		$this->_compressed  = array();

		return true;
	}
}

?><?php

class Net_DNS2_PrivateKey
{

	public $filename;

	public $keytag;

	public $signname;

	public $algorithm;

	public $key_format;

	public $instance;

	private $_modulus;

	private $_public_exponent;

	private $_private_exponent;

	private $_prime1;

	private $_prime2;

	private $_exponent1;

	private $_exponent2;

	private $_coefficient;

	public $prime;

	public $subprime;

	public $base;

	public $private_value;

	public $public_value;

	public function __construct($file = null)
	{
		if (isset($file)) {
			$this->parseFile($file);
		}
	}

	public function parseFile($file)
	{
		//

		//
		if (extension_loaded('openssl') === false) {

			throw new Net_DNS2_Exception(
				'the OpenSSL extension is required to use parse private key.',
				Net_DNS2_Lookups::E_OPENSSL_UNAVAIL
			);
		}

		//

		//
		if (is_readable($file) == false) {

			throw new Net_DNS2_Exception(
				'invalid private key file: ' . $file,
				Net_DNS2_Lookups::E_OPENSSL_INV_PKEY
			);
		}

		//

		//
		$keyname = basename($file);
		if (strlen($keyname) == 0) {

			throw new Net_DNS2_Exception(
				'failed to get basename() for: ' . $file,
				Net_DNS2_Lookups::E_OPENSSL_INV_PKEY
			);
		}

		//

		//
		if (preg_match("/K(.*)\.\+(\d{3})\+(\d*)\.private/", $keyname, $matches)) {

			$this->signname    = $matches[1];
			$this->algorithm   = intval($matches[2]);
			$this->keytag      = intval($matches[3]);

		} else {

			throw new Net_DNS2_Exception(
				'file ' . $keyname . ' does not look like a private key file!',
				Net_DNS2_Lookups::E_OPENSSL_INV_PKEY
			);
		}

		//

		//
		$data = file($file, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
		if (count($data) == 0) {

			throw new Net_DNS2_Exception(
				'file ' . $keyname . ' is empty!',
				Net_DNS2_Lookups::E_OPENSSL_INV_PKEY
			);
		}

		foreach ($data as $line) {

			list($key, $value) = explode(':', $line);

			$key    = trim($key);
			$value  = trim($value);

			switch(strtolower($key)) {

			case 'private-key-format':
				$this->_key_format = $value;
				break;

			case 'algorithm':
				if ($this->algorithm != $value) {
					throw new Net_DNS2_Exception(
						'Algorithm mis-match! filename is ' . $this->algorithm .
						', contents say ' . $value,
						Net_DNS2_Lookups::E_OPENSSL_INV_ALGO
					);
				}
				break;

			//

			//
			case 'modulus':
				$this->_modulus = $value;
				break;

			case 'publicexponent':
				$this->_public_exponent = $value;
				break;

			case 'privateexponent':
				$this->_private_exponent = $value;
				break;

			case 'prime1':
				$this->_prime1 = $value;
				break;

			case 'prime2':
				$this->_prime2 = $value;
				break;

			case 'exponent1':
				$this->_exponent1 = $value;
				break;

			case 'exponent2':
				$this->_exponent2 = $value;
				break;

			case 'coefficient':
				$this->_coefficient = $value;
				break;

			//

			//
			case 'prime(p)':
				$this->prime = $value;
				break;

			case 'subprime(q)':
				$this->subprime = $value;
				break;

			case 'base(g)':
				$this->base = $value;
				break;

			case 'private_value(x)':
				$this->private_value = $value;
				break;

			case 'public_value(y)':
				$this->public_value = $value;
				break;

			default:
				throw new Net_DNS2_Exception(
					'unknown private key data: ' . $key . ': ' . $value,
					Net_DNS2_Lookups::E_OPENSSL_INV_PKEY
				);
			}
		}

		//

		//
		$args = array();

		switch($this->algorithm) {

		//

		//
		case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSAMD5:
		case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSASHA1:
		case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSASHA256:
		case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSASHA512:

			$args = array(

				'rsa' => array(

					'n'                 => base64_decode($this->_modulus),
					'e'                 => base64_decode($this->_public_exponent),
					'd'                 => base64_decode($this->_private_exponent),
					'p'                 => base64_decode($this->_prime1),
					'q'                 => base64_decode($this->_prime2),
					'dmp1'              => base64_decode($this->_exponent1),
					'dmq1'              => base64_decode($this->_exponent2),
					'iqmp'              => base64_decode($this->_coefficient)
				)
			);

			break;

		//

		//
		case Net_DNS2_Lookups::DNSSEC_ALGORITHM_DSA:

			$args = array(

				'dsa' => array(

					'p'                 => base64_decode($this->prime),
					'q'                 => base64_decode($this->subprime),
					'g'                 => base64_decode($this->base),
					'priv_key'          => base64_decode($this->private_value),
					'pub_key'           => base64_decode($this->public_value)
				)
			);

			break;

		default:
			throw new Net_DNS2_Exception(
				'we only currently support RSAMD5 and RSASHA1 encryption.',
				Net_DNS2_Lookups::E_OPENSSL_INV_PKEY
			);
		}

		//

		//
		$this->instance = openssl_pkey_new($args);
		if ($this->instance === false) {
			throw new Net_DNS2_Exception(
				openssl_error_string(),
				Net_DNS2_Lookups::E_OPENSSL_ERROR
			);
		}

		//

		//
		$this->filename = $file;

		return true;
	}
}

?><?php

class Net_DNS2_Question
{

	public $qname;

	public $qtype;

	public $qclass;

	public function __construct(Net_DNS2_Packet &$packet = null)
	{
		if (!is_null($packet)) {

			$this->set($packet);
		} else {

			$this->qname    = '';
			$this->qtype    = 'A';
			$this->qclass   = 'IN';
		}
	}

	public function __toString()
	{
		return ";;\n;; Question:\n;;\t " . $this->qname . '. ' .
			$this->qtype . ' ' . $this->qclass . "\n";
	}

	public function set(Net_DNS2_Packet &$packet)
	{
		//

		//
		$this->qname = $packet->expand($packet, $packet->offset);
		if ($packet->rdlength < ($packet->offset + 4)) {

			throw new Net_DNS2_Exception(
				'invalid question section: to small',
				Net_DNS2_Lookups::E_QUESTION_INVALID
			);
		}

		//

		//
		$type   = ord($packet->rdata[$packet->offset++]) << 8 |
			ord($packet->rdata[$packet->offset++]);
		$class  = ord($packet->rdata[$packet->offset++]) << 8 |
			ord($packet->rdata[$packet->offset++]);

		//

		//
		$type_name  = Net_DNS2_Lookups::$rr_types_by_id[$type];
		$class_name = Net_DNS2_Lookups::$classes_by_id[$class];

		if ( (!isset($type_name)) || (!isset($class_name)) ) {

			throw new Net_DNS2_Exception(
				'invalid question section: invalid type (' . $type .
				') or class (' . $class . ') specified.',
				Net_DNS2_Lookups::E_QUESTION_INVALID
			);
		}

		//

		//
		$this->qtype     = $type_name;
		$this->qclass    = $class_name;

		return true;
	}

	public function get(Net_DNS2_Packet &$packet)
	{
		//

		//
		$type  = Net_DNS2_Lookups::$rr_types_by_name[$this->qtype];
		$class = Net_DNS2_Lookups::$classes_by_name[$this->qclass];

		if ( (!isset($type)) || (!isset($class)) ) {

			throw new Net_DNS2_Exception(
				'invalid question section: invalid type (' . $this->qtype .
				') or class (' . $this->qclass . ') specified.',
				Net_DNS2_Lookups::E_QUESTION_INVALID
			);
		}

		$data = $packet->compress($this->qname, $packet->offset);

		$data .= chr($type >> 8) . chr($type) . chr($class >> 8) . chr($class);
		$packet->offset += 4;

		return $data;
	}
}

?><?php

class Net_DNS2_Resolver extends Net_DNS2
{

	public function __construct(array $options = null)
	{
		parent::__construct($options);
	}

	public function query($name, $type = 'A', $class = 'IN')
	{
		//

		//
		$this->checkServers(Net_DNS2::RESOLV_CONF);

		//

		//
		if ($type == 'IXFR') {

			$type = 'AXFR';
		}

		//

		//
		if ( (strpos($name, '.') === false) && ($type != 'PTR') ) {

			$name .= '.' . strtolower($this->domain);
		}

		//

		//
		$packet = new Net_DNS2_Packet_Request($name, $type, $class);

		//

		//
		if (   ($this->auth_signature instanceof Net_DNS2_RR_TSIG)
			|| ($this->auth_signature instanceof Net_DNS2_RR_SIG)
		) {
			$packet->additional[]       = $this->auth_signature;
			$packet->header->arcount    = count($packet->additional);
		}

		//

		//
		if ($this->dnssec == true) {

			//

			//
			$opt = new Net_DNS2_RR_OPT();

			//

			//
			$opt->do = 1;
			$opt->class = $this->dnssec_payload_size;

			//

			//
			$packet->additional[] = $opt;
			$packet->header->arcount = count($packet->additional);
		}

		//

		//
		if ($this->dnssec_ad_flag == true) {

			$packet->header->ad = 1;
		}
		if ($this->dnssec_cd_flag == true) {

			$packet->header->cd = 1;
		}

		//

		//

		//
		$packet_hash = '';

		if ( ($this->use_cache == true) && ($this->cacheable($type) == true) ) {

			//

			//
			$this->cache->open(
				$this->cache_file, $this->cache_size, $this->cache_serializer
			);

			//

			//
			$packet_hash = md5(
				$packet->question[0]->qname . '|' . $packet->question[0]->qtype
			);

			if ($this->cache->has($packet_hash)) {

				return $this->cache->get($packet_hash);
			}
		}

		//

		//
		if ($this->recurse == false) {
			$packet->header->rd = 0;
		} else {
			$packet->header->rd = 1;
		}

		//

		//

		//
		$response = $this->sendPacket(
			$packet, ($type == 'AXFR') ? true : $this->use_tcp
		);

		//

		//

		//
		if ( ($this->strict_query_mode == true)
			&& ($response->header->ancount > 0)
		) {

			$found = false;

			//

			//
			foreach ($response->answer as $index => $object) {

				if ( (strcasecmp(trim($object->name, '.'), trim($packet->question[0]->qname, '.')) == 0)
					&& ($object->type == $packet->question[0]->qtype)
					&& ($object->class == $packet->question[0]->qclass)
				) {
					$found = true;
					break;
				}
			}

			//

			//

			//
			if ($found == false) {

				$response->answer = array();
				$response->header->ancount = 0;
			}
		}

		//

		//
		if ( ($this->use_cache == true) && ($this->cacheable($type) == true) ) {

			$this->cache->put($packet_hash, $response);
		}

		return $response;
	}

	public function iquery(Net_DNS2_RR $rr)
	{
		//

		//
		$this->checkServers(Net_DNS2::RESOLV_CONF);

		//

		//
		$packet = new Net_DNS2_Packet_Request($rr->name, 'A', 'IN');

		//

		//
		$packet->question = array();
		$packet->header->qdcount = 0;

		//

		//
		$packet->header->opcode = Net_DNS2_Lookups::OPCODE_IQUERY;

		//

		//
		$packet->answer[] = $rr;
		$packet->header->ancount = 1;

		//

		//
		if (   ($this->auth_signature instanceof Net_DNS2_RR_TSIG)
			|| ($this->auth_signature instanceof Net_DNS2_RR_SIG)
		) {
			$packet->additional[]       = $this->auth_signature;
			$packet->header->arcount    = count($packet->additional);
		}

		//

		//
		return $this->sendPacket($packet, $this->use_tcp);
	}
}

?><?php

abstract class Net_DNS2_RR
{

	public $name;

	public $type;

	public $class;

	public $ttl;

	public $rdlength;

	public $rdata;

	abstract protected function rrToString();

	abstract protected function rrFromString(array $rdata);

	abstract protected function rrSet(Net_DNS2_Packet &$packet);

	abstract protected function rrGet(Net_DNS2_Packet &$packet);

	public function __construct(Net_DNS2_Packet &$packet = null, array $rr = null)
	{
		if ( (!is_null($packet)) && (!is_null($rr)) ) {

			if ($this->set($packet, $rr) == false) {

				throw new Net_DNS2_Exception(
					'failed to generate resource record',
					Net_DNS2_Lookups::E_RR_INVALID
				);
			}
		} else {

			$class = Net_DNS2_Lookups::$rr_types_class_to_id[get_class($this)];
			if (isset($class)) {

				$this->type = Net_DNS2_Lookups::$rr_types_by_id[$class];
			}

			$this->class    = 'IN';
			$this->ttl      = 86400;
		}
	}

	public function __toString()
	{
		return $this->name . '. ' . $this->ttl . ' ' . $this->class .
			' ' . $this->type . ' ' . $this->rrToString();
	}

	protected function formatString($string)
	{
		return '"' . str_replace('"', '\"', trim($string, '"')) . '"';
	}

	protected function buildString(array $chunks)
	{
		$data = array();
		$c = 0;
		$in = false;

		foreach ($chunks as $r) {

			$r = trim($r);
			if (strlen($r) == 0) {
				continue;
			}

			if ( ($r[0] == '"')
				&& ($r[strlen($r) - 1] == '"')
				&& ($r[strlen($r) - 2] != '\\')
			) {

				$data[$c] = $r;
				++$c;
				$in = false;

			} else if ($r[0] == '"') {

				$data[$c] = $r;
				$in = true;

			} else if ( ($r[strlen($r) - 1] == '"')
				&& ($r[strlen($r) - 2] != '\\')
			) {

				$data[$c] .= ' ' . $r;
				++$c;
				$in = false;

			} else {

				if ($in == true) {
					$data[$c] .= ' ' . $r;
				} else {
					$data[$c++] = $r;
				}
			}
		}

		foreach ($data as $index => $string) {

			$data[$index] = str_replace('\"', '"', trim($string, '"'));
		}

		return $data;
	}

	public function set(Net_DNS2_Packet &$packet, array $rr)
	{
		$this->name     = $rr['name'];
		$this->type     = Net_DNS2_Lookups::$rr_types_by_id[$rr['type']];

		//

		//
		if ($this->type == 'OPT') {
			$this->class = $rr['class'];
		} else {
			$this->class = Net_DNS2_Lookups::$classes_by_id[$rr['class']];
		}

		$this->ttl      = $rr['ttl'];
		$this->rdlength = $rr['rdlength'];
		$this->rdata    = substr($packet->rdata, $packet->offset, $rr['rdlength']);

		return $this->rrSet($packet);
	}

	public function get(Net_DNS2_Packet &$packet)
	{
		$data  = '';
		$rdata = '';

		//

		//
		$data = $packet->compress($this->name, $packet->offset);

		//

		//
		if ($this->type == 'OPT') {

			//

			//
			$this->preBuild();

			//

			//
			$data .= pack(
				'nnN',
				Net_DNS2_Lookups::$rr_types_by_name[$this->type],
				$this->class,
				$this->ttl
			);
		} else {

			$data .= pack(
				'nnN',
				Net_DNS2_Lookups::$rr_types_by_name[$this->type],
				Net_DNS2_Lookups::$classes_by_name[$this->class],
				$this->ttl
			);
		}

		//

		//
		$packet->offset += 10;

		//

		//
		if ($this->rdlength != -1) {

			$rdata = $this->rrGet($packet);
		}

		//

		//
		$data .= pack('n', strlen($rdata)) . $rdata;

		return $data;
	}

	public static function parse(Net_DNS2_Packet &$packet)
	{
		$object = array();

		//

		//
		$object['name'] = $packet->expand($packet, $packet->offset);
		if (is_null($object['name'])) {

			throw new Net_DNS2_Exception(
				'failed to parse resource record: failed to expand name.',
				Net_DNS2_Lookups::E_PARSE_ERROR
			);
		}
		if ($packet->rdlength < ($packet->offset + 10)) {

			throw new Net_DNS2_Exception(
				'failed to parse resource record: packet too small.',
				Net_DNS2_Lookups::E_PARSE_ERROR
			);
		}

		//

		//
		$object['type']     = ord($packet->rdata[$packet->offset++]) << 8 |
								ord($packet->rdata[$packet->offset++]);
		$object['class']    = ord($packet->rdata[$packet->offset++]) << 8 |
								ord($packet->rdata[$packet->offset++]);

		$object['ttl']      = ord($packet->rdata[$packet->offset++]) << 24 |
								ord($packet->rdata[$packet->offset++]) << 16 |
								ord($packet->rdata[$packet->offset++]) << 8 |
								ord($packet->rdata[$packet->offset++]);

		$object['rdlength'] = ord($packet->rdata[$packet->offset++]) << 8 |
								ord($packet->rdata[$packet->offset++]);

		if ($packet->rdlength < ($packet->offset + $object['rdlength'])) {
			return null;
		}

		//

		//
		$o      = null;
		$class  = Net_DNS2_Lookups::$rr_types_id_to_class[$object['type']];

		if (isset($class)) {

			$o = new $class($packet, $object);
			if ($o) {

				$packet->offset += $object['rdlength'];
			}
		} else {

			throw new Net_DNS2_Exception(
				'un-implemented resource record type: ' . $object['type'],
				Net_DNS2_Lookups::E_RR_INVALID
			);
		}

		return $o;
	}

	public function cleanString($data)
	{
		return strtolower(rtrim($data, '.'));
	}

	public static function fromString($line)
	{
		if (strlen($line) == 0) {
			throw new Net_DNS2_Exception(
				'empty config line provided.',
				Net_DNS2_Lookups::E_PARSE_ERROR
			);
		}

		$name   = '';
		$type   = '';
		$class  = 'IN';
		$ttl    = 86400;

		//

		//
		$values = preg_split('/[\s]+/', $line);
		if (count($values) < 3) {

			throw new Net_DNS2_Exception(
				'failed to parse config: minimum of name, type and rdata required.',
				Net_DNS2_Lookups::E_PARSE_ERROR
			);
		}

		//

		//
		$name = trim(strtolower(array_shift($values)), '.');

		//

		//
		foreach ($values as $value) {

			switch(true) {
			case is_numeric($value):

				$ttl = array_shift($values);
				break;

			//

			//
			case ($value === 0):

				$ttl = array_shift($values);
				break;

			case isset(Net_DNS2_Lookups::$classes_by_name[strtoupper($value)]):

				$class = strtoupper(array_shift($values));
				break;

			case isset(Net_DNS2_Lookups::$rr_types_by_name[strtoupper($value)]):

				$type = strtoupper(array_shift($values));
				break 2;
				break;

			default:

				throw new Net_DNS2_Exception(
					'invalid config line provided: unknown file: ' . $value,
					Net_DNS2_Lookups::E_PARSE_ERROR
				);
			}
		}

		//

		//
		$o = null;
		$class_name = Net_DNS2_Lookups::$rr_types_id_to_class[
			Net_DNS2_Lookups::$rr_types_by_name[$type]
		];

		if (isset($class_name)) {

			$o = new $class_name;
			if (!is_null($o)) {

				//

				//
				$o->name    = $name;
				$o->class   = $class;
				$o->ttl     = $ttl;

				//

				//
				if ($o->rrFromString($values) === false) {

					throw new Net_DNS2_Exception(
						'failed to parse rdata for config: ' . $line,
						Net_DNS2_Lookups::E_PARSE_ERROR
					);
				}

			} else {

				throw new Net_DNS2_Exception(
					'failed to create new RR record for type: ' . $type,
					Net_DNS2_Lookups::E_RR_INVALID
				);
			}

		} else {

			throw new Net_DNS2_Exception(
				'un-implemented resource record type: '. $type,
				Net_DNS2_Lookups::E_RR_INVALID
			);
		}

		return $o;
	}
}

?><?php

if (defined('SOCK_STREAM') == false) {
	define('SOCK_STREAM', 1);
}
if (defined('SOCK_DGRAM') == false) {
	define('SOCK_DGRAM', 2);
}

abstract class Net_DNS2_Socket
{
	protected $sock;
	protected $type;
	protected $host;
	protected $port;
	protected $timeout;

	protected $local_host;
	protected $local_port;

	public $last_error;

	const SOCK_STREAM   = SOCK_STREAM;
	const SOCK_DGRAM    = SOCK_DGRAM;

	public function __construct($type, $host, $port, $timeout)
	{
		$this->type     = $type;
		$this->host     = $host;
		$this->port     = $port;
		$this->timeout  = $timeout;
	}

	public function __destruct()
	{
		$this->close();
	}

	public function bindAddress($address, $port = 0)
	{
		$this->local_host = $address;
		$this->local_port = $port;

		return true;
	}

	abstract public function open();

	abstract public function close();

	abstract public function write($data);

	abstract public function read(&$size, $max_size);
}

?><?php

class Net_DNS2_Updater extends Net_DNS2
{

	private $_packet;

	public function __construct($zone, array $options = null)
	{
		parent::__construct($options);

		//

		//
		$this->_packet = new Net_DNS2_Packet_Request(
			strtolower(trim($zone, " \n\r\t.")), 'SOA', 'IN'
		);

		//

		//
		$this->_packet->header->opcode = Net_DNS2_Lookups::OPCODE_UPDATE;
	}

	private function _checkName($name)
	{
		if (!preg_match('/' . $this->_packet->question[0]->qname . '$/', $name)) {

			throw new Net_DNS2_Exception(
				'name provided (' . $name . ') does not match zone name (' .
				$this->_packet->question[0]->qname . ')',
				Net_DNS2_Lookups::E_PACKET_INVALID
			);
		}

		return true;
	}

	public function signature($keyname, $signature)
	{
		return $this->signTSIG($keyname, $signature);
	}

	public function add(Net_DNS2_RR $rr)
	{
		$this->_checkName($rr->name);

		//

		//
		if (!in_array($rr, $this->_packet->authority)) {
			$this->_packet->authority[] = $rr;
		}

		return true;
	}

	public function delete(Net_DNS2_RR $rr)
	{
		$this->_checkName($rr->name);

		$rr->ttl    = 0;
		$rr->class  = 'NONE';

		//

		//
		if (!in_array($rr, $this->_packet->authority)) {
			$this->_packet->authority[] = $rr;
		}

		return true;
	}

	public function deleteAny($name, $type)
	{
		$this->_checkName($name);

		$class = Net_DNS2_Lookups::$rr_types_id_to_class[
			Net_DNS2_Lookups::$rr_types_by_name[$type]
		];
		if (!isset($class)) {

			throw new Net_DNS2_Exception(
				'unknown or un-supported resource record type: ' . $type,
				Net_DNS2_Lookups::E_RR_INVALID
			);
		}

		$rr = new $class;

		$rr->name       = $name;
		$rr->ttl        = 0;
		$rr->class      = 'ANY';
		$rr->rdlength   = -1;
		$rr->rdata      = '';

		//

		//
		if (!in_array($rr, $this->_packet->authority)) {
			$this->_packet->authority[] = $rr;
		}

		return true;
	}

	public function deleteAll($name)
	{
		$this->_checkName($name);

		//

		//
		$rr = new Net_DNS2_RR_ANY;

		$rr->name       = $name;
		$rr->ttl        = 0;
		$rr->type       = 'ANY';
		$rr->class      = 'ANY';
		$rr->rdlength   = -1;
		$rr->rdata      = '';

		//

		//
		if (!in_array($rr, $this->_packet->authority)) {
			$this->_packet->authority[] = $rr;
		}

		return true;
	}

	public function checkExists($name, $type)
	{
		$this->_checkName($name);

		$class = Net_DNS2_Lookups::$rr_types_id_to_class[
			Net_DNS2_Lookups::$rr_types_by_name[$type]
		];
		if (!isset($class)) {

			throw new Net_DNS2_Exception(
				'unknown or un-supported resource record type: ' . $type,
				Net_DNS2_Lookups::E_RR_INVALID
			);
		}

		$rr = new $class;

		$rr->name       = $name;
		$rr->ttl        = 0;
		$rr->class      = 'ANY';
		$rr->rdlength   = -1;
		$rr->rdata      = '';

		//

		//
		if (!in_array($rr, $this->_packet->answer)) {
			$this->_packet->answer[] = $rr;
		}

		return true;
	}

	public function checkValueExists(Net_DNS2_RR $rr)
	{
		$this->_checkName($rr->name);

		$rr->ttl = 0;

		//

		//
		if (!in_array($rr, $this->_packet->answer)) {
			$this->_packet->answer[] = $rr;
		}

		return true;
	}

	public function checkNotExists($name, $type)
	{
		$this->_checkName($name);

		$class = Net_DNS2_Lookups::$rr_types_id_to_class[
			Net_DNS2_Lookups::$rr_types_by_name[$type]
		];
		if (!isset($class)) {

			throw new Net_DNS2_Exception(
				'unknown or un-supported resource record type: ' . $type,
				Net_DNS2_Lookups::E_RR_INVALID
			);
		}

		$rr = new $class;

		$rr->name       = $name;
		$rr->ttl        = 0;
		$rr->class      = 'NONE';
		$rr->rdlength   = -1;
		$rr->rdata      = '';

		//

		//
		if (!in_array($rr, $this->_packet->answer)) {
			$this->_packet->answer[] = $rr;
		}

		return true;
	}

	public function checkNameInUse($name)
	{
		$this->_checkName($name);

		//

		//
		$rr = new Net_DNS2_RR_ANY;

		$rr->name       = $name;
		$rr->ttl        = 0;
		$rr->type       = 'ANY';
		$rr->class      = 'ANY';
		$rr->rdlength   = -1;
		$rr->rdata      = '';

		//

		//
		if (!in_array($rr, $this->_packet->answer)) {
			$this->_packet->answer[] = $rr;
		}

		return true;
	}

	public function checkNameNotInUse($name)
	{
		$this->_checkName($name);

		//

		//
		$rr = new Net_DNS2_RR_ANY;

		$rr->name       = $name;
		$rr->ttl        = 0;
		$rr->type       = 'ANY';
		$rr->class      = 'NONE';
		$rr->rdlength   = -1;
		$rr->rdata      = '';

		//

		//
		if (!in_array($rr, $this->_packet->answer)) {
			$this->_packet->answer[] = $rr;
		}

		return true;
	}

	public function packet()
	{
		//

		//
		$p = $this->_packet;

		//

		//
		if (   ($this->auth_signature instanceof Net_DNS2_RR_TSIG)
			|| ($this->auth_signature instanceof Net_DNS2_RR_SIG)
		) {
			$p->additional[] = $this->auth_signature;
		}

		//

		//
		$p->header->qdcount = count($p->question);
		$p->header->ancount = count($p->answer);
		$p->header->nscount = count($p->authority);
		$p->header->arcount = count($p->additional);

		return $p;
	}

	public function update(&$response = null)
	{
		//

		//
		$this->checkServers(Net_DNS2::RESOLV_CONF);

		//

		//
		if (   ($this->auth_signature instanceof Net_DNS2_RR_TSIG)
			|| ($this->auth_signature instanceof Net_DNS2_RR_SIG)
		) {
			$this->_packet->additional[] = $this->auth_signature;
		}

		//

		//
		$this->_packet->header->qdcount = count($this->_packet->question);
		$this->_packet->header->ancount = count($this->_packet->answer);
		$this->_packet->header->nscount = count($this->_packet->authority);
		$this->_packet->header->arcount = count($this->_packet->additional);

		//

		//
		if (   ($this->_packet->header->qdcount == 0)
			|| ($this->_packet->header->nscount == 0)
		) {
			throw new Net_DNS2_Exception(
				'empty headers- nothing to send!',
				Net_DNS2_Lookups::E_PACKET_INVALID
			);
		}

		//

		//
		$response = $this->sendPacket($this->_packet, $this->use_tcp);

		//

		//
		$this->_packet->reset();

		//

		//
		return true;
	}
}

?><?php

class Net_DNS2_Cache_File extends Net_DNS2_Cache
{

	public function open($cache_file, $size, $serializer)
	{
		$this->cache_size       = $size;
		$this->cache_file       = $cache_file;
		$this->cache_serializer = $serializer;

		//

		//
		if ( ($this->cache_opened == false)
			&& (file_exists($this->cache_file) == true)
			&& (filesize($this->cache_file) > 0)
		) {
			//

			//
			$fp = @fopen($this->cache_file, 'r');
			if ($fp !== false) {

				//

				//
				flock($fp, LOCK_EX);

				//

				//
				$data = fread($fp, filesize($this->cache_file));

				$decoded = null;

				if ($this->cache_serializer == 'json') {

					$decoded = json_decode($data, true);
				} else {

					$decoded = unserialize($data);
				}

				if (is_array($decoded) == true) {

					$this->cache_data = $decoded;
				} else {

					$this->cache_data = array();
				}

				//

				//
				flock($fp, LOCK_UN);

				//

				//
				fclose($fp);

				//

				//
				$this->clean();

				//

				//
				$this->cache_opened = true;
			}
		}
	}

	public function __destruct()
	{
		//

		//
		if (strlen($this->cache_file) == 0) {
			return;
		}

		//

		//
		$fp = fopen($this->cache_file, 'a+');
		if ($fp !== false) {

			//

			//
			flock($fp, LOCK_EX);

			//

			//
			fseek($fp, 0, SEEK_SET);

			//

			//
			$data = @fread($fp, filesize($this->cache_file));
			if ( ($data !== false) && (strlen($data) > 0) ) {

				//

				//
				$c = $this->cache_data;

				$decoded = null;

				if ($this->cache_serializer == 'json') {

					$decoded = json_decode($data, true);
				} else {

					$decoded = unserialize($data);
				}

				if (is_array($decoded) == true) {

					$this->cache_data = array_merge($c, $decoded);
				}
			}

			//

			//
			ftruncate($fp, 0);

			//

			//
			$this->clean();

			//

			//
			$data = $this->resize();
			if (!is_null($data)) {

				//

				//
				fwrite($fp, $data);
			}

			//

			//
			flock($fp, LOCK_UN);

			//

			//
			fclose($fp);
		}
	}
};

?><?php

class Net_DNS2_Cache_Shm extends Net_DNS2_Cache
{

	private $_cache_id = false;

	private $_cache_file_tok = -1;

	public function open($cache_file, $size, $serializer)
	{
		$this->cache_size       = $size;
		$this->cache_file       = $cache_file;
		$this->cache_serializer = $serializer;

		//

		//
		if ($this->cache_opened == true)
		{
			return;
		}

		//

		//
		if (!file_exists($cache_file)) {

			if (file_put_contents($cache_file, '') === false) {

				throw new Net_DNS2_Exception(
					'failed to create empty SHM file: ' . $cache_file,
					Net_DNS2_Lookups::E_CACHE_SHM_FILE
				);
			}
		}

		//

		//
		$this->_cache_file_tok = ftok($cache_file, 't');
		if ($this->_cache_file_tok == -1) {

			throw new Net_DNS2_Exception(
				'failed on ftok() file: ' . $this->_cache_file_tok,
				Net_DNS2_Lookups::E_CACHE_SHM_FILE
			);
		}

		//

		//
		$this->_cache_id = @shmop_open($this->_cache_file_tok, 'w', 0, 0);
		if ($this->_cache_id !== false) {

			//

			//
			$allocated = shmop_size($this->_cache_id);
			if ($allocated > 0) {

				//

				//
				$data = trim(shmop_read($this->_cache_id, 0, $allocated));
				if ( ($data !== false) && (strlen($data) > 0) ) {

					//

					//
					$decoded = null;

					if ($this->cache_serializer == 'json') {

						$decoded = json_decode($data, true);
					} else {

						$decoded = unserialize($data);
					}

					if (is_array($decoded) == true) {

						$this->cache_data = $decoded;
					} else {

						$this->cache_data = array();
					}

					//

					//
					$this->clean();

					//

					//
					$this->cache_opened = true;
				}
			}
		}
	}

	public function __destruct()
	{
		//

		//
		if (strlen($this->cache_file) == 0) {
			return;
		}

		$fp = fopen($this->cache_file, 'r');
		if ($fp !== false) {

			//

			//
			flock($fp, LOCK_EX);

			//

			//
			if ($this->_cache_id === false) {

				//

				//
				$this->_cache_id = @shmop_open(
					$this->_cache_file_tok, 'w', 0, 0
				);
				if ($this->_cache_id === false) {

					//

					//
					$this->_cache_id = @shmop_open(
						$this->_cache_file_tok, 'c', 0, $this->cache_size
					);
				}
			}

			//

			//
			$allocated = shmop_size($this->_cache_id);

			//

			//
			$data = trim(shmop_read($this->_cache_id, 0, $allocated));

			//

			//
			if ( ($data !== false) && (strlen($data) > 0) ) {

				//

				//
				$c = $this->cache_data;

				$decoded = null;

				if ($this->cache_serializer == 'json') {

					$decoded = json_decode($data, true);
				} else {

					$decoded = unserialize($data);
				}

				if (is_array($decoded) == true) {

					$this->cache_data = array_merge($c, $decoded);
				}
			}

			//

			//
			shmop_delete($this->_cache_id);

			//

			//
			$this->clean();

			//

			//
			$data = $this->resize();
			if (!is_null($data)) {

				//

				//
				$this->_cache_id = @shmop_open(
					$this->_cache_file_tok, 'c', 0644, $this->cache_size
				);
				if ($this->_cache_id === false) {
					return;
				}

				$o = shmop_write($this->_cache_id, $data, 0);
			}

			//

			//
			shmop_close($this->_cache_id);

			//

			//
			flock($fp, LOCK_UN);

			//

			//
			fclose($fp);
		}
	}
};

?><?php

class Net_DNS2_Packet_Request extends Net_DNS2_Packet
{

	public function __construct($name, $type = null, $class = null)
	{
		$this->set($name, $type, $class);
	}

	public function set($name, $type = 'A', $class = 'IN')
	{
		//

		//
		$this->header = new Net_DNS2_Header;

		//

		//
		$q = new Net_DNS2_Question();

		//

		//
		if ($name != '.') {
			$name = trim(strtolower($name), " \t\n\r\0\x0B.");
		}

		$type = strtoupper(trim($type));
		$class = strtoupper(trim($class));

		//

		//
		if (empty($name)) {

			throw new Net_DNS2_Exception(
				'empty query string provided',
				Net_DNS2_Lookups::E_PACKET_INVALID
			);
		}

		//

		//
		if ($type == '*') {

			$type = 'ANY';
		}

		//

		//
		if (   (!isset(Net_DNS2_Lookups::$rr_types_by_name[$type]))
			|| (!isset(Net_DNS2_Lookups::$classes_by_name[$class]))
		) {
			throw new Net_DNS2_Exception(
				'invalid type (' . $type . ') or class (' . $class . ') specified.',
				Net_DNS2_Lookups::E_PACKET_INVALID
			);
		}

		if ($type == 'PTR') {

			//

			//

			//
			if (Net_DNS2::isIPv4($name) == true) {

				//

				//
				$name = implode('.', array_reverse(explode('.', $name)));
				$name .= '.in-addr.arpa';

			} else if (Net_DNS2::isIPv6($name) == true) {

				//

				//
				$e = Net_DNS2::expandIPv6($name);
				if ($e !== false) {

					$name = implode(
						'.', array_reverse(str_split(str_replace(':', '', $e)))
					);

					$name .= '.ip6.arpa';

				} else {

					throw new Net_DNS2_Exception(
						'unsupported PTR value: ' . $name,
						Net_DNS2_Lookups::E_PACKET_INVALID
					);
				}
			}
		}

		//

		//
		$q->qname           = $name;
		$q->qtype           = $type;
		$q->qclass          = $class;

		$this->question[]   = $q;

		//

		//
		$this->answer       = array();
		$this->authority    = array();
		$this->additional   = array();

		return true;
	}
}

?><?php

class Net_DNS2_Packet_Response extends Net_DNS2_Packet
{

	public $answer_from;

	public $answer_socket_type;

	public $response_time = 0;

	public function __construct($data, $size)
	{
		$this->set($data, $size);
	}

	public function set($data, $size)
	{
		//

		//
		$this->rdata    = $data;
		$this->rdlength = $size;

		//

		//

		//
		$this->header = new Net_DNS2_Header($this);

		//

		//

		//
		if ($this->header->tc == 1) {

			return false;
		}

		//

		//
		for ($x = 0; $x < $this->header->qdcount; ++$x) {

			$this->question[$x] = new Net_DNS2_Question($this);
		}

		//

		//
		for ($x = 0; $x < $this->header->ancount; ++$x) {

			$o = Net_DNS2_RR::parse($this);
			if (!is_null($o)) {

				$this->answer[] = $o;
			}
		}

		//

		//
		for ($x = 0; $x < $this->header->nscount; ++$x) {

			$o = Net_DNS2_RR::parse($this);
			if (!is_null($o)) {

				$this->authority[] = $o;
			}
		}

		//

		//
		for ($x = 0; $x < $this->header->arcount; ++$x) {

			$o = Net_DNS2_RR::parse($this);
			if (!is_null($o)) {

				$this->additional[] = $o;
			}
		}

		return true;
	}
}

?><?php

class Net_DNS2_RR_A extends Net_DNS2_RR
{

	public $address;

	protected function rrToString()
	{
		return $this->address;
	}

	protected function rrFromString(array $rdata)
	{
		$value = array_shift($rdata);

		if (Net_DNS2::isIPv4($value) == true) {

			$this->address = $value;
			return true;
		}

		return false;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			$this->address = inet_ntop($this->rdata);
			if ($this->address !== false) {

				return true;
			}
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		$packet->offset += 4;
		return inet_pton($this->address);
	}
}

?><?php

class Net_DNS2_RR_AAAA extends Net_DNS2_RR
{

	public $address;

	protected function rrToString()
	{
		return $this->address;
	}

	protected function rrFromString(array $rdata)
	{
		//

		//
		$value = array_shift($rdata);
		if (Net_DNS2::isIPv6($value) == true) {

			$this->address = $value;
			return true;
		}

		return false;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		//

		//
		if ($this->rdlength == 16) {

			//

			//
			$x = unpack('n8', $this->rdata);
			if (count($x) == 8) {

				$this->address = vsprintf('%x:%x:%x:%x:%x:%x:%x:%x', $x);
				return true;
			}
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		$packet->offset += 16;
		return inet_pton($this->address);
	}
}

?><?php

class Net_DNS2_RR_AFSDB extends Net_DNS2_RR
{

	public $subtype;

	public $hostname;

	protected function rrToString()
	{
		return $this->subtype . ' ' . $this->cleanString($this->hostname) . '.';
	}

	protected function rrFromString(array $rdata)
	{
		$this->subtype  = array_shift($rdata);
		$this->hostname = $this->cleanString(array_shift($rdata));

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('nsubtype', $this->rdata);

			$this->subtype  = $x['subtype'];
			$offset         = $packet->offset + 2;

			$this->hostname = Net_DNS2_Packet::expand($packet, $offset);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->hostname) > 0) {

			$data = pack('n', $this->subtype);
			$packet->offset += 2;

			$data .= $packet->compress($this->hostname, $packet->offset);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_ANY extends Net_DNS2_RR
{

	protected function rrToString()
	{
		return '';
	}

	protected function rrFromString(array $rdata)
	{
		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		return true;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		return '';
	}
}

?><?php

class Net_DNS2_RR_APL extends Net_DNS2_RR
{

	public $apl_items = array();

	protected function rrToString()
	{
		$out = '';

		foreach ($this->apl_items as $item) {

			if ($item['n'] == 1) {

				$out .= '!';
			}

			$out .= $item['address_family'] . ':' .
				$item['afd_part'] . '/' . $item['prefix'] . ' ';
		}

		return trim($out);
	}

	protected function rrFromString(array $rdata)
	{
		foreach ($rdata as $item) {

			if (preg_match('/^(!?)([1|2])\:([^\/]*)\/([0-9]{1,3})$/', $item, $m)) {

				$i = array(

					'address_family'    => $m[2],
					'prefix'            => $m[4],
					'n'                 => ($m[1] == '!') ? 1 : 0,
					'afd_part'          => strtolower($m[3])
				);

				$address = $this->_trimZeros(
					$i['address_family'], $i['afd_part']
				);

				$i['afd_length'] = count(explode('.', $address));

				$this->apl_items[] = $i;
			}
		}

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			$offset = 0;

			while ($offset < $this->rdlength) {

				//

				//
				$x = unpack(
					'naddress_family/Cprefix/Cextra', substr($this->rdata, $offset)
				);

				$item = array(

					'address_family'    => $x['address_family'],
					'prefix'            => $x['prefix'],
					'n'                 => ($x['extra'] >> 7) & 0x1,
					'afd_length'        => $x['extra'] & 0xf
				);

				switch($item['address_family']) {

				case 1:
					$r = unpack(
						'C*', substr($this->rdata, $offset + 4, $item['afd_length'])
					);
					if (count($r) < 4) {

						for ($c=count($r)+1; $c<4+1; $c++) {

							$r[$c] = 0;
						}
					}

					$item['afd_part'] = implode('.', $r);

					break;
				case 2:
					$r = unpack(
						'C*', substr($this->rdata, $offset + 4, $item['afd_length'])
					);
					if (count($r) < 8) {

						for ($c=count($r)+1; $c<8+1; $c++) {

							$r[$c] = 0;
						}
					}

					$item['afd_part'] = sprintf(
						'%x:%x:%x:%x:%x:%x:%x:%x',
						$r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $r[7], $r[8]
					);

					break;
				default:
					return false;
				}

				$this->apl_items[] = $item;

				$offset += 4 + $item['afd_length'];
			}

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (count($this->apl_items) > 0) {

			$data = '';

			foreach ($this->apl_items as $item) {

				//

				//
				$data .= pack(
					'nCC',
					$item['address_family'],
					$item['prefix'],
					($item['n'] << 7) | $item['afd_length']
				);

				switch($item['address_family']) {
				case 1:
					$address = explode(
						'.',
						$this->_trimZeros($item['address_family'], $item['afd_part'])
					);

					foreach ($address as $b) {
						$data .= chr($b);
					}
					break;
				case 2:
					$address = explode(
						':',
						$this->_trimZeros($item['address_family'], $item['afd_part'])
					);

					foreach ($address as $b) {
						$data .= pack('H', $b);
					}
					break;
				default:
					return null;
				}
			}

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}

	private function _trimZeros($family, $address)
	{
		$a = array();

		switch($family) {
		case 1:
			$a = array_reverse(explode('.', $address));
			break;
		case 2:
			$a = array_reverse(explode(':', $address));
			break;
		default:
			return '';
		}

		foreach ($a as $value) {

			if ($value === '0') {

				array_shift($a);
			}
		}

		$out = '';

		switch($family) {
		case 1:
			$out = implode('.', array_reverse($a));
			break;
		case 2:
			$out = implode(':', array_reverse($a));
			break;
		default:
			return '';
		}

		return $out;
	}
}

?><?php

class Net_DNS2_RR_ATMA extends Net_DNS2_RR
{

	public $format;

	public $address;

	protected function rrToString()
	{
		return $this->address;
	}

	protected function rrFromString(array $rdata)
	{
		$value = array_shift($rdata);

		if (ctype_xdigit($value) == true) {

			$this->format   = 0;
			$this->address  = $value;

		} else if (is_numeric($value) == true) {

			$this->format   = 1;
			$this->address  = $value;

		} else {

			return false;
		}

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('Cformat/N*address', $this->rdata);

			$this->format = $x['format'];

			if ($this->format == 0) {

				$a = unpack('@1/H*address', $this->rdata);

				$this->address = $a['address'];

			} else if ($this->format == 1) {

				$this->address = substr($this->rdata, 1, $this->rdlength - 1);

			} else {

				return false;
			}

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		$data = chr($this->format);

		if ($this->format == 0) {

			$data .= pack('H*', $this->address);

		} else if ($this->format == 1) {

			$data .= $this->address;

		} else {

			return null;
		}

		$packet->offset += strlen($data);

		return $data;
	}
}

?><?php

class Net_DNS2_RR_AVC extends Net_DNS2_RR_TXT
{
}

?><?php

class Net_DNS2_RR_CAA extends Net_DNS2_RR
{

	public $flags;

	public $tag;

	public $value;

	protected function rrToString()
	{
		return $this->flags . ' ' . $this->tag . ' "' .
			trim($this->cleanString($this->value), '"') . '"';
	}

	protected function rrFromString(array $rdata)
	{
		$this->flags    = array_shift($rdata);
		$this->tag      = array_shift($rdata);

		$this->value    = trim($this->cleanString(implode($rdata, ' ')), '"');

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('Cflags/Ctag_length', $this->rdata);

			$this->flags    = $x['flags'];
			$offset         = 2;

			$this->tag      = substr($this->rdata, $offset, $x['tag_length']);
			$offset         += $x['tag_length'];

			$this->value    = substr($this->rdata, $offset);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->value) > 0) {

			$data  = chr($this->flags);
			$data .= chr(strlen($this->tag)) . $this->tag . $this->value;

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_CDNSKEY extends Net_DNS2_RR_DNSKEY
{
}

?><?php

class Net_DNS2_RR_CDS extends Net_DNS2_RR_DS
{
}

?><?php

class Net_DNS2_RR_CERT extends Net_DNS2_RR
{

	const CERT_FORMAT_RES       = 0;
	const CERT_FORMAT_PKIX      = 1;
	const CERT_FORMAT_SPKI      = 2;
	const CERT_FORMAT_PGP       = 3;
	const CERT_FORMAT_IPKIX     = 4;
	const CERT_FORMAT_ISPKI     = 5;
	const CERT_FORMAT_IPGP      = 6;
	const CERT_FORMAT_ACPKIX    = 7;
	const CERT_FORMAT_IACPKIX   = 8;
	const CERT_FORMAT_URI       = 253;
	const CERT_FORMAT_OID       = 254;

	public $cert_format_name_to_id = array();
	public $cert_format_id_to_name = array(

		self::CERT_FORMAT_RES       => 'Reserved',
		self::CERT_FORMAT_PKIX      => 'PKIX',
		self::CERT_FORMAT_SPKI      => 'SPKI',
		self::CERT_FORMAT_PGP       => 'PGP',
		self::CERT_FORMAT_IPKIX     => 'IPKIX',
		self::CERT_FORMAT_ISPKI     => 'ISPKI',
		self::CERT_FORMAT_IPGP      => 'IPGP',
		self::CERT_FORMAT_ACPKIX    => 'ACPKIX',
		self::CERT_FORMAT_IACPKIX   => 'IACPKIX',
		self::CERT_FORMAT_URI       => 'URI',
		self::CERT_FORMAT_OID       => 'OID'
	);

	public $format;

	public $keytag;

	public $algorithm;

	public $certificate;

	public function __construct(Net_DNS2_Packet &$packet = null, array $rr = null)
	{
		parent::__construct($packet, $rr);

		//

		//
		$this->cert_format_name_to_id = array_flip($this->cert_format_id_to_name);
	}

	protected function rrToString()
	{
		return $this->format . ' ' . $this->keytag . ' ' . $this->algorithm .
			' ' . base64_encode($this->certificate);
	}

	protected function rrFromString(array $rdata)
	{
		//

		//
		$this->format = array_shift($rdata);
		if (!is_numeric($this->format)) {

			$mnemonic = strtoupper(trim($this->format));
			if (!isset($this->cert_format_name_to_id[$mnemonic])) {

				return false;
			}

			$this->format = $this->cert_format_name_to_id[$mnemonic];
		} else {

			if (!isset($this->cert_format_id_to_name[$this->format])) {

				return false;
			}
		}

		$this->keytag = array_shift($rdata);

		//

		//
		$this->algorithm = array_shift($rdata);
		if (!is_numeric($this->algorithm)) {

			$mnemonic = strtoupper(trim($this->algorithm));
			if (!isset(Net_DNS2_Lookups::$algorithm_name_to_id[$mnemonic])) {

				return false;
			}

			$this->algorithm = Net_DNS2_Lookups::$algorithm_name_to_id[
				$mnemonic
			];
		} else {

			if (!isset(Net_DNS2_Lookups::$algorithm_id_to_name[$this->algorithm])) {
				return false;
			}
		}

		//

		//

		//
		$this->certificate = base64_decode(implode(' ', $rdata));

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('nformat/nkeytag/Calgorithm', $this->rdata);

			$this->format       = $x['format'];
			$this->keytag       = $x['keytag'];
			$this->algorithm    = $x['algorithm'];

			//

			//
			$this->certificate  = substr($this->rdata, 5, $this->rdlength - 5);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->certificate) > 0) {

			$data = pack('nnC', $this->format, $this->keytag, $this->algorithm) .
				$this->certificate;

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_CNAME extends Net_DNS2_RR
{

	public $cname;

	protected function rrToString()
	{
		return $this->cleanString($this->cname) . '.';
	}

	protected function rrFromString(array $rdata)
	{
		$this->cname = $this->cleanString(array_shift($rdata));
		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			$offset = $packet->offset;
			$this->cname = Net_DNS2_Packet::expand($packet, $offset);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->cname) > 0) {

			return $packet->compress($this->cname, $packet->offset);
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_CSYNC extends Net_DNS2_RR
{

	public $serial;

	public $flags;

	public $type_bit_maps = array();

	protected function rrToString()
	{
		$out = $this->serial . ' ' . $this->flags;

		//

		//
		foreach ($this->type_bit_maps as $rr) {

			$out .= ' ' . strtoupper($rr);
		}

		return $out;
	}

	protected function rrFromString(array $rdata)
	{
		$this->serial   = array_shift($rdata);
		$this->flags    = array_shift($rdata);

		$this->type_bit_maps = $rdata;

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('@' . $packet->offset . '/Nserial/nflags', $packet->rdata);

			$this->serial   = Net_DNS2::expandUint32($x['serial']);
			$this->flags    = $x['flags'];

			//

			//
			$this->type_bit_maps = Net_DNS2_BitMap::bitMapToArray(
				substr($this->rdata, 6)
			);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		//

		//
		$data = pack('Nn', $this->serial, $this->flags);

		//

		//
		$data .= Net_DNS2_BitMap::arrayToBitMap($this->type_bit_maps);

		//

		//
		$packet->offset += strlen($data);

		return $data;
	}
}

?><?php

class Net_DNS2_RR_DHCID extends Net_DNS2_RR
{

	public $id_type;

	public $digest_type;

	public $digest;

	protected function rrToString()
	{
		$out = pack('nC', $this->id_type, $this->digest_type);
		$out .= base64_decode($this->digest);

		return base64_encode($out);
	}

	protected function rrFromString(array $rdata)
	{
		$data = base64_decode(array_shift($rdata));
		if (strlen($data) > 0) {

			//

			//
			$x = unpack('nid_type/Cdigest_type', $data);

			$this->id_type      = $x['id_type'];
			$this->digest_type  = $x['digest_type'];

			//

			//
			$this->digest = base64_encode(substr($data, 3, strlen($data) - 3));

			return true;
		}

		return false;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('nid_type/Cdigest_type', $this->rdata);

			$this->id_type      = $x['id_type'];
			$this->digest_type  = $x['digest_type'];

			//

			//
			$this->digest = base64_encode(
				substr($this->rdata, 3, $this->rdlength - 3)
			);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->digest) > 0) {

			$data = pack('nC', $this->id_type, $this->digest_type) .
				base64_decode($this->digest);

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_DLV extends Net_DNS2_RR_DS
{
}

?><?php

class Net_DNS2_RR_DNAME extends Net_DNS2_RR
{

	public $dname;

	protected function rrToString()
	{
		return $this->cleanString($this->dname) . '.';
	}

	protected function rrFromString(array $rdata)
	{
		$this->dname = $this->cleanString(array_shift($rdata));
		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			$offset = $packet->offset;
			$this->dname = Net_DNS2_Packet::expand($packet, $offset);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->dname) > 0) {

			return $packet->compress($this->dname, $packet->offset);
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_DNSKEY extends Net_DNS2_RR
{

	public $flags;

	public $protocol;

	public $algorithm;

	public $key;

	protected function rrToString()
	{
		return $this->flags . ' ' . $this->protocol . ' ' .
			$this->algorithm . ' ' . $this->key;
	}

	protected function rrFromString(array $rdata)
	{
		$this->flags        = array_shift($rdata);
		$this->protocol     = array_shift($rdata);
		$this->algorithm    = array_shift($rdata);
		$this->key          = implode(' ', $rdata);

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('nflags/Cprotocol/Calgorithm', $this->rdata);

			//

			//

			//
			$this->flags        = $x['flags'];
			$this->protocol     = $x['protocol'];
			$this->algorithm    = $x['algorithm'];

			$this->key          = base64_encode(substr($this->rdata, 4));

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->key) > 0) {

			$data = pack('nCC', $this->flags, $this->protocol, $this->algorithm);
			$data .= base64_decode($this->key);

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_DS extends Net_DNS2_RR
{

	public $keytag;

	public $algorithm;

	public $digesttype;

	public $digest;

	protected function rrToString()
	{
		return $this->keytag . ' ' . $this->algorithm . ' ' .
			$this->digesttype . ' ' . $this->digest;
	}

	protected function rrFromString(array $rdata)
	{
		$this->keytag       = array_shift($rdata);
		$this->algorithm    = array_shift($rdata);
		$this->digesttype   = array_shift($rdata);
		$this->digest       = implode('', $rdata);

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('nkeytag/Calgorithm/Cdigesttype', $this->rdata);

			$this->keytag       = $x['keytag'];
			$this->algorithm    = $x['algorithm'];
			$this->digesttype   = $x['digesttype'];

			//

			//
			$digest_size = 0;
			if ($this->digesttype == 1) {

				$digest_size = 20;

			} else if ($this->digesttype == 2) {

				$digest_size = 32;
			}

			//

			//
			$x = unpack('H*', substr($this->rdata, 4, $digest_size));
			$this->digest = $x[1];

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->digest) > 0) {

			$data = pack(
				'nCCH*',
				$this->keytag, $this->algorithm, $this->digesttype, $this->digest
			);

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_EID extends Net_DNS2_RR
{

	protected function rrToString()
	{
		return '';
	}

	protected function rrFromString(array $rdata)
	{
		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		return true;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		return $this->rdata;
	}
}

?><?php

class Net_DNS2_RR_EUI48 extends Net_DNS2_RR
{

	public $address;

	protected function rrToString()
	{
		return $this->address;
	}

	protected function rrFromString(array $rdata)
	{
		$value = array_shift($rdata);

		//

		//
		$a = explode('-', $value);
		if (count($a) != 6) {

			return false;
		}

		//

		//
		foreach ($a as $i) {
			if (ctype_xdigit($i) == false) {
				return false;
			}
		}

		//

		//
		$this->address = strtolower($value);

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			$x = unpack('C6', $this->rdata);
			if (count($x) == 6) {

				$this->address = vsprintf('%02x-%02x-%02x-%02x-%02x-%02x', $x);
				return true;
			}
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		$data = '';

		$a = explode('-', $this->address);
		foreach ($a as $b) {

			$data .= chr(hexdec($b));
		}

		$packet->offset += 6;
		return $data;
	}
}

?><?php

class Net_DNS2_RR_EUI64 extends Net_DNS2_RR
{

	public $address;

	protected function rrToString()
	{
		return $this->address;
	}

	protected function rrFromString(array $rdata)
	{
		$value = array_shift($rdata);

		//

		//
		$a = explode('-', $value);
		if (count($a) != 8) {

			return false;
		}

		//

		//
		foreach ($a as $i) {
			if (ctype_xdigit($i) == false) {
				return false;
			}
		}

		//

		//
		$this->address = strtolower($value);

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			$x = unpack('C8', $this->rdata);
			if (count($x) == 8) {

				$this->address = vsprintf(
					'%02x-%02x-%02x-%02x-%02x-%02x-%02x-%02x', $x
				);
				return true;
			}
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		$data = '';

		$a = explode('-', $this->address);
		foreach ($a as $b) {

			$data .= chr(hexdec($b));
		}

		$packet->offset += 8;
		return $data;
	}
}

?><?php

class Net_DNS2_RR_HINFO extends Net_DNS2_RR
{

	public $cpu;

	public $os;

	protected function rrToString()
	{
		return $this->formatString($this->cpu) . ' ' .
			$this->formatString($this->os);
	}

	protected function rrFromString(array $rdata)
	{
		$data = $this->buildString($rdata);
		if (count($data) == 2) {

			$this->cpu  = $data[0];
			$this->os   = $data[1];

			return true;
		}

		return false;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			$offset = $packet->offset;

			$this->cpu  = trim(Net_DNS2_Packet::label($packet, $offset), '"');
			$this->os   = trim(Net_DNS2_Packet::label($packet, $offset), '"');

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->cpu) > 0) {

			$data  = chr(strlen($this->cpu)) . $this->cpu;
			$data .= chr(strlen($this->os)) . $this->os;

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_HIP extends Net_DNS2_RR
{

	public $hit_length;

	public $pk_algorithm;

	public $pk_length;

	public $hit;

	public $public_key;

	public $rendezvous_servers = array();

	protected function rrToString()
	{
		$out = $this->pk_algorithm . ' ' .
			$this->hit . ' ' . $this->public_key . ' ';

		foreach ($this->rendezvous_servers as $index => $server) {

			$out .= $server . '. ';
		}

		return trim($out);
	}

	protected function rrFromString(array $rdata)
	{
		$this->pk_algorithm     = array_shift($rdata);
		$this->hit              = strtoupper(array_shift($rdata));
		$this->public_key       = array_shift($rdata);

		//

		//
		if (count($rdata) > 0) {

			$this->rendezvous_servers = preg_replace('/\.$/', '', $rdata);
		}

		//

		//
		$this->hit_length       = strlen(pack('H*', $this->hit));
		$this->pk_length        = strlen(base64_decode($this->public_key));

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('Chit_length/Cpk_algorithm/npk_length', $this->rdata);

			$this->hit_length   = $x['hit_length'];
			$this->pk_algorithm = $x['pk_algorithm'];
			$this->pk_length    = $x['pk_length'];

			$offset = 4;

			//

			//
			$hit = unpack('H*', substr($this->rdata, $offset, $this->hit_length));

			$this->hit = strtoupper($hit[1]);
			$offset += $this->hit_length;

			//

			//
			$this->public_key = base64_encode(
				substr($this->rdata, $offset, $this->pk_length)
			);
			$offset += $this->pk_length;

			//

			//
			$offset = $packet->offset + $offset;

			while ( ($offset - $packet->offset) < $this->rdlength) {

				$this->rendezvous_servers[] = Net_DNS2_Packet::expand(
					$packet, $offset
				);
			}

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if ( (strlen($this->hit) > 0) && (strlen($this->public_key) > 0) ) {

			//

			//
			$data = pack(
				'CCnH*',
				$this->hit_length,
				$this->pk_algorithm,
				$this->pk_length,
				$this->hit
			);

			//

			//
			$data .= base64_decode($this->public_key);

			//

			//
			$packet->offset += strlen($data);

			//

			//
			foreach ($this->rendezvous_servers as $index => $server) {

				$data .= $packet->compress($server, $packet->offset);
			}

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_IPSECKEY extends Net_DNS2_RR
{
	const GATEWAY_TYPE_NONE     = 0;
	const GATEWAY_TYPE_IPV4     = 1;
	const GATEWAY_TYPE_IPV6     = 2;
	const GATEWAY_TYPE_DOMAIN   = 3;

	const ALGORITHM_NONE        = 0;
	const ALGORITHM_DSA         = 1;
	const ALGORITHM_RSA         = 2;

	public $precedence;

	public $gateway_type;

	public $algorithm;

	public $gateway;

	public $key;

	protected function rrToString()
	{
		$out = $this->precedence . ' ' . $this->gateway_type . ' ' .
			$this->algorithm . ' ';

		switch($this->gateway_type) {
		case self::GATEWAY_TYPE_NONE:
			$out .= '. ';
			break;

		case self::GATEWAY_TYPE_IPV4:
		case self::GATEWAY_TYPE_IPV6:
			$out .= $this->gateway . ' ';
			break;

		case self::GATEWAY_TYPE_DOMAIN:
			$out .= $this->gateway . '. ';
			break;
		}

		$out .= $this->key;
		return $out;
	}

	protected function rrFromString(array $rdata)
	{
		//

		//
		$precedence     = array_shift($rdata);
		$gateway_type   = array_shift($rdata);
		$algorithm      = array_shift($rdata);
		$gateway        = strtolower(trim(array_shift($rdata)));
		$key            = array_shift($rdata);

		//

		//
		switch($gateway_type) {
		case self::GATEWAY_TYPE_NONE:
			$gateway = '';
			break;

		case self::GATEWAY_TYPE_IPV4:
			if (Net_DNS2::isIPv4($gateway) == false) {
				return false;
			}
			break;

		case self::GATEWAY_TYPE_IPV6:
			if (Net_DNS2::isIPv6($gateway) == false) {
				return false;
			}
			break;

		case self::GATEWAY_TYPE_DOMAIN:
			;
			break;

		default:
			return false;
		}

		//

		//
		switch($algorithm) {
		case self::ALGORITHM_NONE:
			$key = '';
			break;

		case self::ALGORITHM_DSA:
		case self::ALGORITHM_RSA:
			;
			break;

		default:
			return false;
		}

		//

		//
		$this->precedence   = $precedence;
		$this->gateway_type = $gateway_type;
		$this->algorithm    = $algorithm;
		$this->gateway      = $gateway;
		$this->key          = $key;

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('Cprecedence/Cgateway_type/Calgorithm', $this->rdata);

			$this->precedence   = $x['precedence'];
			$this->gateway_type = $x['gateway_type'];
			$this->algorithm    = $x['algorithm'];

			$offset = 3;

			//

			//
			switch($this->gateway_type) {
			case self::GATEWAY_TYPE_NONE:
				$this->gateway = '';
				break;

			case self::GATEWAY_TYPE_IPV4:
				$this->gateway = inet_ntop(substr($this->rdata, $offset, 4));
				$offset += 4;
				break;

			case self::GATEWAY_TYPE_IPV6:
				$ip = unpack('n8', substr($this->rdata, $offset, 16));
				if (count($ip) == 8) {

					$this->gateway = vsprintf('%x:%x:%x:%x:%x:%x:%x:%x', $ip);
					$offset += 16;
				} else {

					return false;
				}
				break;

			case self::GATEWAY_TYPE_DOMAIN:

				$doffset = $offset + $packet->offset;
				$this->gateway = Net_DNS2_Packet::expand($packet, $doffset);
				$offset = ($doffset - $packet->offset);
				break;

			default:
				return false;
			}

			//

			//
			switch($this->algorithm) {
			case self::ALGORITHM_NONE:
				$this->key = '';
				break;

			case self::ALGORITHM_DSA:
			case self::ALGORITHM_RSA:
				$this->key = base64_encode(substr($this->rdata, $offset));
				break;

			default:
				return false;
			}

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		//

		//
		$data = pack(
			'CCC', $this->precedence, $this->gateway_type, $this->algorithm
		);

		//

		//
		switch($this->gateway_type) {
		case self::GATEWAY_TYPE_NONE:
			;
			break;

		case self::GATEWAY_TYPE_IPV4:
		case self::GATEWAY_TYPE_IPV6:
			$data .= inet_pton($this->gateway);
			break;

		case self::GATEWAY_TYPE_DOMAIN:
			$data .= chr(strlen($this->gateway))  . $this->gateway;
			break;

		default:
			return null;
		}

		//

		//
		switch($this->algorithm) {
		case self::ALGORITHM_NONE:
			;
			break;

		case self::ALGORITHM_DSA:
		case self::ALGORITHM_RSA:
			$data .= base64_decode($this->key);
			break;

		default:
			return null;
		}

		$packet->offset += strlen($data);

		return $data;
	}
}

?><?php

class Net_DNS2_RR_ISDN extends Net_DNS2_RR
{

	public $isdnaddress;

	public $sa;

	protected function rrToString()
	{
		return $this->formatString($this->isdnaddress) . ' ' .
			$this->formatString($this->sa);
	}

	protected function rrFromString(array $rdata)
	{
		$data = $this->buildString($rdata);
		if (count($data) >= 1) {

			$this->isdnaddress = $data[0];
			if (isset($data[1])) {

				$this->sa = $data[1];
			}

			return true;
		}

		return false;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			$this->isdnaddress = Net_DNS2_Packet::label($packet, $packet->offset);

			//

			//
			if ( (strlen($this->isdnaddress) + 1) < $this->rdlength) {

				$this->sa = Net_DNS2_Packet::label($packet, $packet->offset);
			} else {

				$this->sa = '';
			}

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->isdnaddress) > 0) {

			$data = chr(strlen($this->isdnaddress)) . $this->isdnaddress;
			if (!empty($this->sa)) {

				$data .= chr(strlen($this->sa));
				$data .= $this->sa;
			}

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_KEY extends Net_DNS2_RR_DNSKEY
{
}

?><?php

class Net_DNS2_RR_KX extends Net_DNS2_RR
{

	public $preference;

	public $exchange;

	protected function rrToString()
	{
		return $this->preference . ' ' . $this->cleanString($this->exchange) . '.';
	}

	protected function rrFromString(array $rdata)
	{
		$this->preference   = array_shift($rdata);
		$this->exchange     = $this->cleanString(array_shift($rdata));

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('npreference', $this->rdata);
			$this->preference = $x['preference'];

			//

			//
			$offset = $packet->offset + 2;
			$this->exchange = Net_DNS2_Packet::label($packet, $offset);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->exchange) > 0) {

			$data = pack('nC', $this->preference, strlen($this->exchange)) .
				$this->exchange;

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_L32 extends Net_DNS2_RR
{

	public $preference;

	public $locator32;

	protected function rrToString()
	{
		return $this->preference . ' ' . $this->locator32;
	}

	protected function rrFromString(array $rdata)
	{
		$this->preference = array_shift($rdata);
		$this->locator32 = array_shift($rdata);

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('npreference/C4locator', $this->rdata);

			$this->preference = $x['preference'];

			//

			//
			$this->locator32 = $x['locator1'] . '.' . $x['locator2'] . '.' .
				$x['locator3'] . '.' . $x['locator4'];

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->locator32) > 0) {

			//

			//
			$n = explode('.', $this->locator32);

			//

			//
			return pack('nC4', $this->preference, $n[0], $n[1], $n[2], $n[3]);
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_L64 extends Net_DNS2_RR
{

	public $preference;

	public $locator64;

	protected function rrToString()
	{
		return $this->preference . ' ' . $this->locator64;
	}

	protected function rrFromString(array $rdata)
	{
		$this->preference = array_shift($rdata);
		$this->locator64 = array_shift($rdata);

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('npreference/n4locator', $this->rdata);

			$this->preference = $x['preference'];

			//

			//
			$this->locator64 = dechex($x['locator1']) . ':' .
				dechex($x['locator2']) . ':' .
				dechex($x['locator3']) . ':' .
				dechex($x['locator4']);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->locator64) > 0) {

			//

			//
			$n = explode(':', $this->locator64);

			//

			//
			return pack(
				'n5', $this->preference, hexdec($n[0]), hexdec($n[1]),
				hexdec($n[2]), hexdec($n[3])
			);
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_LOC extends Net_DNS2_RR
{

	public $version;

	public $size;

	public $horiz_pre;

	public $vert_pre;

	public $latitude;

	public $longitude;

	public $altitude;

	private $_powerOfTen = array(1, 10, 100, 1000, 10000, 100000,
									1000000,10000000,100000000,1000000000);

	const CONV_SEC          = 1000;
	const CONV_MIN          = 60000;
	const CONV_DEG          = 3600000;

	const REFERENCE_ALT     = 10000000;
	const REFERENCE_LATLON  = 2147483648;

	protected function rrToString()
	{
		if ($this->version == 0) {

			return $this->_d2Dms($this->latitude, 'LAT') . ' ' .
				$this->_d2Dms($this->longitude, 'LNG') . ' ' .
				sprintf('%.2fm', $this->altitude) . ' ' .
				sprintf('%.2fm', $this->size) . ' ' .
				sprintf('%.2fm', $this->horiz_pre) . ' ' .
				sprintf('%.2fm', $this->vert_pre);
		}

		return '';
	}

	protected function rrFromString(array $rdata)
	{
		//

		//

		//
		$res = preg_match(
			'/^(\d+) \s+((\d+) \s+)?(([\d.]+) \s+)?(N|S) \s+(\d+) ' .
			'\s+((\d+) \s+)?(([\d.]+) \s+)?(E|W) \s+(-?[\d.]+) m?(\s+ ' .
			'([\d.]+) m?)?(\s+ ([\d.]+) m?)?(\s+ ([\d.]+) m?)?/ix',
			implode(' ', $rdata), $x
		);

		if ($res) {

			//

			//
			$latdeg     = $x[1];
			$latmin     = (isset($x[3])) ? $x[3] : 0;
			$latsec     = (isset($x[5])) ? $x[5] : 0;
			$lathem     = strtoupper($x[6]);

			$this->latitude = $this->_dms2d($latdeg, $latmin, $latsec, $lathem);

			//

			//
			$londeg     = $x[7];
			$lonmin     = (isset($x[9])) ? $x[9] : 0;
			$lonsec     = (isset($x[11])) ? $x[11] : 0;
			$lonhem     = strtoupper($x[12]);

			$this->longitude = $this->_dms2d($londeg, $lonmin, $lonsec, $lonhem);

			//

			//
			$version            = 0;

			$this->size         = (isset($x[15])) ? $x[15] : 1;
			$this->horiz_pre    = ((isset($x[17])) ? $x[17] : 10000);
			$this->vert_pre     = ((isset($x[19])) ? $x[19] : 10);
			$this->altitude     = $x[13];

			return true;
		}

		return false;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack(
				'Cver/Csize/Choriz_pre/Cvert_pre/Nlatitude/Nlongitude/Naltitude',
				$this->rdata
			);

			//

			//
			$this->version = $x['ver'];
			if ($this->version == 0) {

				$this->size         = $this->_precsizeNtoA($x['size']);
				$this->horiz_pre    = $this->_precsizeNtoA($x['horiz_pre']);
				$this->vert_pre     = $this->_precsizeNtoA($x['vert_pre']);

				//

				//
				if ($x['latitude'] < 0) {

					$this->latitude = ($x['latitude'] +
						self::REFERENCE_LATLON) / self::CONV_DEG;
				} else {

					$this->latitude = ($x['latitude'] -
						self::REFERENCE_LATLON) / self::CONV_DEG;
				}

				if ($x['longitude'] < 0) {

					$this->longitude = ($x['longitude'] +
						self::REFERENCE_LATLON) / self::CONV_DEG;
				} else {

					$this->longitude = ($x['longitude'] -
						self::REFERENCE_LATLON) / self::CONV_DEG;
				}

				//

				//
				$this->altitude = ($x['altitude'] - self::REFERENCE_ALT) / 100;

				return true;

			} else {

				return false;
			}

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if ($this->version == 0) {

			$lat = 0;
			$lng = 0;

			if ($this->latitude < 0) {

				$lat = ($this->latitude * self::CONV_DEG) - self::REFERENCE_LATLON;
			} else {

				$lat = ($this->latitude * self::CONV_DEG) + self::REFERENCE_LATLON;
			}

			if ($this->longitude < 0) {

				$lng = ($this->longitude * self::CONV_DEG) - self::REFERENCE_LATLON;
			} else {

				$lng = ($this->longitude * self::CONV_DEG) + self::REFERENCE_LATLON;
			}

			$packet->offset += 16;

			return pack(
				'CCCCNNN',
				$this->version,
				$this->_precsizeAtoN($this->size),
				$this->_precsizeAtoN($this->horiz_pre),
				$this->_precsizeAtoN($this->vert_pre),
				$lat, $lng,
				($this->altitude * 100) + self::REFERENCE_ALT
			);
		}

		return null;
	}

	private function _precsizeNtoA($prec)
	{
		$mantissa = (($prec >> 4) & 0x0f) % 10;
		$exponent = (($prec >> 0) & 0x0f) % 10;

		return $mantissa * $this->_powerOfTen[$exponent];
	}

	private function _precsizeAtoN($prec)
	{
		$exponent = 0;
		while ($prec >= 10) {

			$prec /= 10;
			++$exponent;
		}

		return ($prec << 4) | ($exponent & 0x0f);
	}

	private function _dms2d($deg, $min, $sec, $hem)
	{
		$deg = $deg - 0;
		$min = $min - 0;

		$sign = ($hem == 'W' || $hem == 'S') ? -1 : 1;
		return ((($sec/60+$min)/60)+$deg) * $sign;
	}

	private function _d2Dms($data, $latlng)
	{
		$deg = 0;
		$min = 0;
		$sec = 0;
		$msec = 0;
		$hem = '';

		if ($latlng == 'LAT') {
			$hem = ($data > 0) ? 'N' : 'S';
		} else {
			$hem = ($data > 0) ? 'E' : 'W';
		}

		$data = abs($data);

		$deg = (int)$data;
		$min = (int)(($data - $deg) * 60);
		$sec = (int)(((($data - $deg) * 60) - $min) * 60);
		$msec = round((((((($data - $deg) * 60) - $min) * 60) - $sec) * 1000));

		return sprintf('%d %02d %02d.%03d %s', $deg, $min, $sec, round($msec), $hem);
	}
}

?><?php

class Net_DNS2_RR_LP extends Net_DNS2_RR
{

	public $preference;

	public $fqdn;

	protected function rrToString()
	{
		return $this->preference . ' ' . $this->fqdn . '.';
	}

	protected function rrFromString(array $rdata)
	{
		$this->preference = array_shift($rdata);
		$this->fqdn = trim(array_shift($rdata), '.');

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('npreference', $this->rdata);
			$this->preference = $x['preference'];
			$offset = $packet->offset + 2;

			//

			//
			$this->fqdn = Net_DNS2_Packet::expand($packet, $offset);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->fqdn) > 0) {

			$data = pack('n', $this->preference);
			$packet->offset += 2;

			$data .= $packet->compress($this->fqdn, $packet->offset);
			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_MX extends Net_DNS2_RR
{

	public $preference;

	public $exchange;

	protected function rrToString()
	{
		return $this->preference . ' ' . $this->cleanString($this->exchange) . '.';
	}

	protected function rrFromString(array $rdata)
	{
		$this->preference   = array_shift($rdata);
		$this->exchange     = $this->cleanString(array_shift($rdata));

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('npreference', $this->rdata);
			$this->preference = $x['preference'];

			//

			//
			$offset = $packet->offset + 2;
			$this->exchange = Net_DNS2_Packet::expand($packet, $offset);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->exchange) > 0) {

			$data = pack('n', $this->preference);
			$packet->offset += 2;

			$data .= $packet->compress($this->exchange, $packet->offset);
			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_NAPTR extends Net_DNS2_RR
{

	public $order;

	public $preference;

	public $flags;

	public $services;

	public $regexp;

	public $replacement;

	protected function rrToString()
	{
		return $this->order . ' ' . $this->preference . ' ' .
			$this->formatString($this->flags) . ' ' .
			$this->formatString($this->services) . ' ' .
			$this->formatString($this->regexp) . ' ' .
			$this->cleanString($this->replacement) . '.';
	}

	protected function rrFromString(array $rdata)
	{
		$this->order        = array_shift($rdata);
		$this->preference   = array_shift($rdata);

		$data = $this->buildString($rdata);
		if (count($data) == 4) {

			$this->flags        = $data[0];
			$this->services     = $data[1];
			$this->regexp       = $data[2];
			$this->replacement  = $this->cleanString($data[3]);

			return true;
		}

		return false;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('norder/npreference', $this->rdata);

			$this->order        = $x['order'];
			$this->preference   = $x['preference'];

			$offset             = $packet->offset + 4;

			$this->flags        = Net_DNS2_Packet::label($packet, $offset);
			$this->services     = Net_DNS2_Packet::label($packet, $offset);
			$this->regexp       = Net_DNS2_Packet::label($packet, $offset);

			$this->replacement  = Net_DNS2_Packet::expand($packet, $offset);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if ( (isset($this->order)) && (strlen($this->services) > 0) ) {

			$data = pack('nn', $this->order, $this->preference);

			$data .= chr(strlen($this->flags)) . $this->flags;
			$data .= chr(strlen($this->services)) . $this->services;
			$data .= chr(strlen($this->regexp)) . $this->regexp;

			$packet->offset += strlen($data);

			$data .= $packet->compress($this->replacement, $packet->offset);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_NID extends Net_DNS2_RR
{

	public $preference;

	public $nodeid;

	protected function rrToString()
	{
		return $this->preference . ' ' . $this->nodeid;
	}

	protected function rrFromString(array $rdata)
	{
		$this->preference = array_shift($rdata);
		$this->nodeid = array_shift($rdata);

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('npreference/n4nodeid', $this->rdata);

			$this->preference = $x['preference'];

			//

			//
			$this->nodeid = dechex($x['nodeid1']) . ':' .
				dechex($x['nodeid2']) . ':' .
				dechex($x['nodeid3']) . ':' .
				dechex($x['nodeid4']);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->nodeid) > 0) {

			//

			//
			$n = explode(':', $this->nodeid);

			//

			//
			return pack(
				'n5', $this->preference, hexdec($n[0]), hexdec($n[1]),
				hexdec($n[2]), hexdec($n[3])
			);
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_NIMLOCK extends Net_DNS2_RR
{

	protected function rrToString()
	{
		return '';
	}

	protected function rrFromString(array $rdata)
	{
		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		return true;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		return $this->rdata;
	}
}

?><?php

class Net_DNS2_RR_NS extends Net_DNS2_RR
{

	public $nsdname;

	protected function rrToString()
	{
		return $this->cleanString($this->nsdname) . '.';
	}

	protected function rrFromString(array $rdata)
	{
		$this->nsdname = $this->cleanString(array_shift($rdata));
		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			$offset = $packet->offset;
			$this->nsdname = Net_DNS2_Packet::expand($packet, $offset);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->nsdname) > 0) {

			return $packet->compress($this->nsdname, $packet->offset);
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_NSAP extends Net_DNS2_RR
{
	public $afi;
	public $idi;
	public $dfi;
	public $aa;
	public $rsvd;
	public $rd;
	public $area;
	public $id;
	public $sel;

	protected function rrToString()
	{
		return $this->cleanString($this->afi) . '.' .
			$this->cleanString($this->idi) . '.' .
			$this->cleanString($this->dfi) . '.' .
			$this->cleanString($this->aa) . '.' .
			$this->cleanString($this->rsvd) . '.' .
			$this->cleanString($this->rd) . '.' .
			$this->cleanString($this->area) . '.' .
			$this->cleanString($this->id) . '.' .
			$this->sel;
	}

	protected function rrFromString(array $rdata)
	{
		$data = strtolower(trim(array_shift($rdata)));

		//

		//
		$data = str_replace(array('.', '0x'), '', $data);

		//

		//
		$x = unpack('A2afi/A4idi/A2dfi/A6aa/A4rsvd/A4rd/A4area/A12id/A2sel', $data);

		//

		//
		if ($x['afi'] == '47') {

			$this->afi  = '0x' . $x['afi'];
			$this->idi  = $x['idi'];
			$this->dfi  = $x['dfi'];
			$this->aa   = $x['aa'];
			$this->rsvd = $x['rsvd'];
			$this->rd   = $x['rd'];
			$this->area = $x['area'];
			$this->id   = $x['id'];
			$this->sel  = $x['sel'];

			return true;
		}

		return false;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength == 20) {

			//

			//
			$this->afi = dechex(ord($this->rdata[0]));

			//

			//
			if ($this->afi == '47') {

				//

				//
				$x = unpack(
					'Cafi/nidi/Cdfi/C3aa/nrsvd/nrd/narea/Nidh/nidl/Csel',
					$this->rdata
				);

				$this->afi  = sprintf('0x%02x', $x['afi']);
				$this->idi  = sprintf('%04x', $x['idi']);
				$this->dfi  = sprintf('%02x', $x['dfi']);
				$this->aa   = sprintf(
					'%06x', $x['aa1'] << 16 | $x['aa2'] << 8 | $x['aa3']
				);
				$this->rsvd = sprintf('%04x', $x['rsvd']);
				$this->rd   = sprintf('%04x', $x['rd']);
				$this->area = sprintf('%04x', $x['area']);
				$this->id   = sprintf('%08x', $x['idh']) .
					sprintf('%04x', $x['idl']);
				$this->sel  = sprintf('%02x', $x['sel']);

				return true;
			}
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if ($this->afi == '0x47') {

			//

			//
			$aa = unpack('A2x/A2y/A2z', $this->aa);

			//

			//
			$id = unpack('A8a/A4b', $this->id);

			//
			$data = pack(
				'CnCCCCnnnNnC',
				hexdec($this->afi),
				hexdec($this->idi),
				hexdec($this->dfi),
				hexdec($aa['x']),
				hexdec($aa['y']),
				hexdec($aa['z']),
				hexdec($this->rsvd),
				hexdec($this->rd),
				hexdec($this->area),
				hexdec($id['a']),
				hexdec($id['b']),
				hexdec($this->sel)
			);

			if (strlen($data) == 20) {

				$packet->offset += 20;
				return $data;
			}
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_NSEC extends Net_DNS2_RR
{

	public $next_domain_name;

	public $type_bit_maps = array();

	protected function rrToString()
	{
		$data = $this->cleanString($this->next_domain_name) . '.';

		foreach ($this->type_bit_maps as $rr) {

			$data .= ' ' . $rr;
		}

		return $data;
	}

	protected function rrFromString(array $rdata)
	{
		$this->next_domain_name = $this->cleanString(array_shift($rdata));
		$this->type_bit_maps = $rdata;

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$offset = $packet->offset;
			$this->next_domain_name = Net_DNS2_Packet::expand($packet, $offset);

			//

			//
			$this->type_bit_maps = Net_DNS2_BitMap::bitMapToArray(
				substr($this->rdata, $offset - $packet->offset)
			);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->next_domain_name) > 0) {

			$data = $packet->compress($this->next_domain_name, $packet->offset);
			$bitmap = Net_DNS2_BitMap::arrayToBitMap($this->type_bit_maps);

			$packet->offset += strlen($bitmap);

			return $data . $bitmap;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_NSEC3 extends Net_DNS2_RR
{

	public $algorithm;

	public $flags;

	public $iterations;

	public $salt_length;

	public $salt;

	public $hash_length;

	public $hashed_owner_name;

	public $type_bit_maps = array();

	protected function rrToString()
	{
		$out = $this->algorithm . ' ' . $this->flags . ' ' . $this->iterations . ' ';

		//

		//
		if ($this->salt_length > 0) {

			$out .= $this->salt;
		} else {

			$out .= '-';
		}

		//

		//
		$out .= ' ' . $this->hashed_owner_name;

		//

		//
		foreach ($this->type_bit_maps as $rr) {

			$out .= ' ' . strtoupper($rr);
		}

		return $out;
	}

	protected function rrFromString(array $rdata)
	{
		$this->algorithm    = array_shift($rdata);
		$this->flags        = array_shift($rdata);
		$this->iterations   = array_shift($rdata);

		//

		//
		$salt = array_shift($rdata);
		if ($salt == '-') {

			$this->salt_length = 0;
			$this->salt = '';
		} else {

			$this->salt_length = strlen(pack('H*', $salt));
			$this->salt = strtoupper($salt);
		}

		$this->hashed_owner_name = array_shift($rdata);
		$this->hash_length = strlen(base64_decode($this->hashed_owner_name));

		$this->type_bit_maps = $rdata;

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('Calgorithm/Cflags/niterations/Csalt_length', $this->rdata);

			$this->algorithm    = $x['algorithm'];
			$this->flags        = $x['flags'];
			$this->iterations   = $x['iterations'];
			$this->salt_length  = $x['salt_length'];

			$offset = 5;

			if ($this->salt_length > 0) {

				$x = unpack('H*', substr($this->rdata, $offset, $this->salt_length));
				$this->salt = strtoupper($x[1]);
				$offset += $this->salt_length;
			}

			//

			//
			$x = unpack('@' . $offset . '/Chash_length', $this->rdata);
			$offset++;

			//

			//
			$this->hash_length  = $x['hash_length'];
			if ($this->hash_length > 0) {

				$this->hashed_owner_name = base64_encode(
					substr($this->rdata, $offset, $this->hash_length)
				);
				$offset += $this->hash_length;
			}

			//

			//
			$this->type_bit_maps = Net_DNS2_BitMap::bitMapToArray(
				substr($this->rdata, $offset)
			);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		//

		//
		$salt = pack('H*', $this->salt);
		$this->salt_length = strlen($salt);

		//

		//
		$data = pack(
			'CCnC',
			$this->algorithm, $this->flags, $this->iterations, $this->salt_length
		);
		$data .= $salt;

		//

		//
		$data .= chr($this->hash_length);
		if ($this->hash_length > 0) {

			$data .= base64_decode($this->hashed_owner_name);
		}

		//

		//
		$data .= Net_DNS2_BitMap::arrayToBitMap($this->type_bit_maps);

		$packet->offset += strlen($data);

		return $data;
	}
}

?><?php

class Net_DNS2_RR_NSEC3PARAM extends Net_DNS2_RR
{

	public $algorithm;

	public $flags;

	public $iterations;

	public $salt_length;

	public $salt;

	protected function rrToString()
	{
		$out = $this->algorithm . ' ' . $this->flags . ' ' . $this->iterations . ' ';

		//

		//
		if ($this->salt_length > 0) {

			$out .= $this->salt;
		} else {

			$out .= '-';
		}

		return $out;
	}

	protected function rrFromString(array $rdata)
	{
		$this->algorithm    = array_shift($rdata);
		$this->flags        = array_shift($rdata);
		$this->iterations   = array_shift($rdata);

		$salt = array_shift($rdata);
		if ($salt == '-') {

			$this->salt_length = 0;
			$this->salt = '';
		} else {

			$this->salt_length = strlen(pack('H*', $salt));
			$this->salt = strtoupper($salt);
		}

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			$x = unpack('Calgorithm/Cflags/niterations/Csalt_length', $this->rdata);

			$this->algorithm    = $x['algorithm'];
			$this->flags        = $x['flags'];
			$this->iterations   = $x['iterations'];
			$this->salt_length  = $x['salt_length'];

			if ($this->salt_length > 0) {

				$x = unpack('H*', substr($this->rdata, 5, $this->salt_length));
				$this->salt = strtoupper($x[1]);
			}

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		$salt = pack('H*', $this->salt);
		$this->salt_length = strlen($salt);

		$data = pack(
			'CCnC',
			$this->algorithm, $this->flags, $this->iterations, $this->salt_length
		) . $salt;

		$packet->offset += strlen($data);

		return $data;
	}
}

?><?php

class Net_DNS2_RR_OPENPGPKEY extends Net_DNS2_RR
{

	public $key;

	protected function rrToString()
	{
		return $this->key;
	}

	protected function rrFromString(array $rdata)
	{
		$this->key = array_shift($rdata);

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			$this->key = base64_encode(substr($this->rdata, 0, $this->rdlength));

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->key) > 0) {

			$data = base64_decode($this->key);

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_OPT extends Net_DNS2_RR
{

	public $option_code;

	public $option_length;

	public $option_data;

	public $extended_rcode;

	public $version;

	public $do;

	public $z;

	public function __construct(Net_DNS2_Packet &$packet = null, array $rr = null)
	{
		//

		//
		$this->type             = 'OPT';
		$this->rdlength         = 0;

		$this->option_length    = 0;
		$this->extended_rcode   = 0;
		$this->version          = 0;
		$this->do               = 0;
		$this->z                = 0;

		//

		//
		if ( (!is_null($packet)) && (!is_null($rr)) ) {

			parent::__construct($packet, $rr);
		}
	}

	protected function rrToString()
	{
		return $this->option_code . ' ' . $this->option_data;
	}

	protected function rrFromString(array $rdata)
	{
		$this->option_code      = array_shift($rdata);
		$this->option_data      = array_shift($rdata);
		$this->option_length    = strlen($this->option_data);

		$x = unpack('Cextended/Cversion/Cdo/Cz', pack('N', $this->ttl));

		$this->extended_rcode   = $x['extended'];
		$this->version          = $x['version'];
		$this->do               = ($x['do'] >> 7);
		$this->z                = $x['z'];

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		//

		//
		$x = unpack('Cextended/Cversion/Cdo/Cz', pack('N', $this->ttl));

		$this->extended_rcode   = $x['extended'];
		$this->version          = $x['version'];
		$this->do               = ($x['do'] >> 7);
		$this->z                = $x['z'];

		//

		//
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('noption_code/noption_length', $this->rdata);

			$this->option_code      = $x['option_code'];
			$this->option_length    = $x['option_length'];

			//

			//
			$this->option_data      = substr($this->rdata, 4);
		}

		return true;
	}

	protected function preBuild()
	{
		//

		//
		$ttl = unpack(
			'N',
			pack('CCCC', $this->extended_rcode, $this->version, ($this->do << 7), 0)
		);

		$this->ttl = $ttl[1];

		return;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		//

		//
		if ($this->option_code) {

			$data = pack('nn', $this->option_code, $this->option_length) .
				$this->option_data;

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_PTR extends Net_DNS2_RR
{

	public $ptrdname;

	protected function rrToString()
	{
		return rtrim($this->ptrdname, '.') . '.';
	}

	protected function rrFromString(array $rdata)
	{
		$this->ptrdname = rtrim(implode(' ', $rdata), '.');
		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			$offset = $packet->offset;
			$this->ptrdname = Net_DNS2_Packet::expand($packet, $offset);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->ptrdname) > 0) {

			return $packet->compress($this->ptrdname, $packet->offset);
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_PX extends Net_DNS2_RR
{

	public $preference;

	public $map822;

	public $mapx400;

	protected function rrToString()
	{
		return $this->preference . ' ' . $this->cleanString($this->map822) . '. ' .
			$this->cleanString($this->mapx400) . '.';
	}

	protected function rrFromString(array $rdata)
	{
		$this->preference   = $rdata[0];
		$this->map822       = $this->cleanString($rdata[1]);
		$this->mapx400      = $this->cleanString($rdata[2]);

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('npreference', $this->rdata);
			$this->preference = $x['preference'];

			$offset         = $packet->offset + 2;

			$this->map822   = Net_DNS2_Packet::expand($packet, $offset);
			$this->mapx400  = Net_DNS2_Packet::expand($packet, $offset);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->map822) > 0) {

			$data = pack('n', $this->preference);
			$packet->offset += 2;

			$data .= $packet->compress($this->map822, $packet->offset);
			$data .= $packet->compress($this->mapx400, $packet->offset);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_RP extends Net_DNS2_RR
{

	public $mboxdname;

	public $txtdname;

	protected function rrToString()
	{
		return $this->cleanString($this->mboxdname) . '. ' .
			$this->cleanString($this->txtdname) . '.';
	}

	protected function rrFromString(array $rdata)
	{
		$this->mboxdname    = $this->cleanString($rdata[0]);
		$this->txtdname     = $this->cleanString($rdata[1]);

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			$offset             = $packet->offset;

			$this->mboxdname    = Net_DNS2_Packet::expand($packet, $offset);
			$this->txtdname     = Net_DNS2_Packet::expand($packet, $offset);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->mboxdname) > 0) {

			return $packet->compress($this->mboxdname, $packet->offset) .
				$packet->compress($this->txtdname, $packet->offset);
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_RRSIG extends Net_DNS2_RR
{

	public $typecovered;

	public $algorithm;

	public $labels;

	public $origttl;

	public $sigexp;

	public $sigincep;

	public $keytag;

	public $signname;

	public $signature;

	protected function rrToString()
	{
		return $this->typecovered . ' ' . $this->algorithm . ' ' .
			$this->labels . ' ' . $this->origttl . ' ' .
			$this->sigexp . ' ' . $this->sigincep . ' ' .
			$this->keytag . ' ' . $this->cleanString($this->signname) . '. ' .
			$this->signature;
	}

	protected function rrFromString(array $rdata)
	{
		$this->typecovered  = strtoupper(array_shift($rdata));
		$this->algorithm    = array_shift($rdata);
		$this->labels       = array_shift($rdata);
		$this->origttl      = array_shift($rdata);
		$this->sigexp       = array_shift($rdata);
		$this->sigincep     = array_shift($rdata);
		$this->keytag       = array_shift($rdata);
		$this->signname     = $this->cleanString(array_shift($rdata));

		foreach ($rdata as $line) {

			$this->signature .= $line;
		}

		$this->signature = trim($this->signature);

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack(
				'ntc/Calgorithm/Clabels/Norigttl/Nsigexp/Nsigincep/nkeytag',
				$this->rdata
			);

			$this->typecovered  = Net_DNS2_Lookups::$rr_types_by_id[$x['tc']];
			$this->algorithm    = $x['algorithm'];
			$this->labels       = $x['labels'];
			$this->origttl      = Net_DNS2::expandUint32($x['origttl']);

			//

			//
			$this->sigexp       = gmdate('YmdHis', $x['sigexp']);
			$this->sigincep     = gmdate('YmdHis', $x['sigincep']);

			//

			//
			$this->keytag       = $x['keytag'];

			//

			//
			$offset             = $packet->offset + 18;
			$sigoffset          = $offset;

			$this->signname     = strtolower(
				Net_DNS2_Packet::expand($packet, $sigoffset)
			);
			$this->signature    = base64_encode(
				substr($this->rdata, 18 + ($sigoffset - $offset))
			);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->signature) > 0) {

			//

			//
			preg_match(
				'/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $this->sigexp, $e
			);
			preg_match(
				'/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $this->sigincep, $i
			);

			//

			//
			$data = pack(
				'nCCNNNn',
				Net_DNS2_Lookups::$rr_types_by_name[$this->typecovered],
				$this->algorithm,
				$this->labels,
				$this->origttl,
				gmmktime($e[4], $e[5], $e[6], $e[2], $e[3], $e[1]),
				gmmktime($i[4], $i[5], $i[6], $i[2], $i[3], $i[1]),
				$this->keytag
			);

			//

			//
			$names = explode('.', strtolower($this->signname));
			foreach ($names as $name) {

				$data .= chr(strlen($name));
				$data .= $name;
			}
			$data .= "\0";

			//

			//
			$data .= base64_decode($this->signature);

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_RT extends Net_DNS2_RR
{

	public $preference;

	public $intermediatehost;

	protected function rrToString()
	{
		return $this->preference . ' ' .
			$this->cleanString($this->intermediatehost) . '.';
	}

	protected function rrFromString(array $rdata)
	{
		$this->preference       = $rdata[0];
		$this->intermediatehost = $this->cleanString($rdata[1]);

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('npreference', $this->rdata);

			$this->preference       = $x['preference'];
			$offset                 = $packet->offset + 2;

			$this->intermediatehost =  Net_DNS2_Packet::expand($packet, $offset);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->intermediatehost) > 0) {

			$data = pack('n', $this->preference);
			$packet->offset += 2;

			$data .= $packet->compress($this->intermediatehost, $packet->offset);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_SIG extends Net_DNS2_RR
{

	public $private_key = null;

	public $typecovered;

	public $algorithm;

	public $labels;

	public $origttl;

	public $sigexp;

	public $sigincep;

	public $keytag;

	public $signname;

	public $signature;

	protected function rrToString()
	{
		return $this->typecovered . ' ' . $this->algorithm . ' ' .
			$this->labels . ' ' . $this->origttl . ' ' .
			$this->sigexp . ' ' . $this->sigincep . ' ' .
			$this->keytag . ' ' . $this->cleanString($this->signname) . '. ' .
			$this->signature;
	}

	protected function rrFromString(array $rdata)
	{
		$this->typecovered  = strtoupper(array_shift($rdata));
		$this->algorithm    = array_shift($rdata);
		$this->labels       = array_shift($rdata);
		$this->origttl      = array_shift($rdata);
		$this->sigexp       = array_shift($rdata);
		$this->sigincep     = array_shift($rdata);
		$this->keytag       = array_shift($rdata);
		$this->signname     = $this->cleanString(array_shift($rdata));

		foreach ($rdata as $line) {

			$this->signature .= $line;
		}

		$this->signature = trim($this->signature);

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack(
				'ntc/Calgorithm/Clabels/Norigttl/Nsigexp/Nsigincep/nkeytag',
				$this->rdata
			);

			$this->typecovered  = Net_DNS2_Lookups::$rr_types_by_id[$x['tc']];
			$this->algorithm    = $x['algorithm'];
			$this->labels       = $x['labels'];
			$this->origttl      = Net_DNS2::expandUint32($x['origttl']);

			//

			//
			$this->sigexp       = gmdate('YmdHis', $x['sigexp']);
			$this->sigincep     = gmdate('YmdHis', $x['sigincep']);

			//

			//
			$this->keytag       = $x['keytag'];

			//

			//
			$offset             = $packet->offset + 18;
			$sigoffset          = $offset;

			$this->signname     = strtolower(
				Net_DNS2_Packet::expand($packet, $sigoffset)
			);
			$this->signature    = base64_encode(
				substr($this->rdata, 18 + ($sigoffset - $offset))
			);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		//

		//
		preg_match(
			'/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $this->sigexp, $e
		);
		preg_match(
			'/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $this->sigincep, $i
		);

		//

		//
		$data = pack(
			'nCCNNNn',
			Net_DNS2_Lookups::$rr_types_by_name[$this->typecovered],
			$this->algorithm,
			$this->labels,
			$this->origttl,
			gmmktime($e[4], $e[5], $e[6], $e[2], $e[3], $e[1]),
			gmmktime($i[4], $i[5], $i[6], $i[2], $i[3], $i[1]),
			$this->keytag
		);

		//

		//
		$names = explode('.', strtolower($this->signname));
		foreach ($names as $name) {

			$data .= chr(strlen($name));
			$data .= $name;
		}

		$data .= chr('0');

		//

		//
		if ( (strlen($this->signature) == 0)
			&& ($this->private_key instanceof Net_DNS2_PrivateKey)
			&& (extension_loaded('openssl') === true)
		) {

			//

			//
			$new_packet = new Net_DNS2_Packet_Request('example.com', 'SOA', 'IN');

			//

			//
			$new_packet->copy($packet);

			//

			//
			array_pop($new_packet->additional);
			$new_packet->header->arcount = count($new_packet->additional);

			//

			//
			$sigdata = $data . $new_packet->get();

			//

			//
			$algorithm = 0;

			switch($this->algorithm) {

			//

			//
			case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSAMD5:

				$algorithm = OPENSSL_ALGO_MD5;
				break;

			//

			//
			case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSASHA1:

				$algorithm = OPENSSL_ALGO_SHA1;
				break;

			//

			//
			case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSASHA256:

				if (version_compare(PHP_VERSION, '5.4.8', '<') == true) {

					throw new Net_DNS2_Exception(
						'SHA256 support is only available in PHP >= 5.4.8',
						Net_DNS2_Lookups::E_OPENSSL_INV_ALGO
					);
				}

				$algorithm = OPENSSL_ALGO_SHA256;
				break;

			//

			//
			case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSASHA512:

				if (version_compare(PHP_VERSION, '5.4.8', '<') == true) {

					throw new Net_DNS2_Exception(
						'SHA512 support is only available in PHP >= 5.4.8',
						Net_DNS2_Lookups::E_OPENSSL_INV_ALGO
					);
				}

				$algorithm = OPENSSL_ALGO_SHA512;
				break;

			//

			//
			case Net_DNS2_Lookups::DNSSEC_ALGORITHM_DSA:
			case Net_DNS2_Lookups::DSNSEC_ALGORITHM_RSASHA1NSEC3SHA1:
			case Net_DNS2_Lookups::DNSSEC_ALGORITHM_DSANSEC3SHA1:
			default:
				throw new Net_DNS2_Exception(
					'invalid or unsupported algorithm',
					Net_DNS2_Lookups::E_OPENSSL_INV_ALGO
				);
				break;
			}

			//

			//
			if (openssl_sign($sigdata, $this->signature, $this->private_key->instance, $algorithm) == false) {

				throw new Net_DNS2_Exception(
					openssl_error_string(),
					Net_DNS2_Lookups::E_OPENSSL_ERROR
				);
			}

			//

			//
			switch($this->algorithm) {

			//

			//
			case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSAMD5:
			case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSASHA1:
			case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSASHA256:
			case Net_DNS2_Lookups::DNSSEC_ALGORITHM_RSASHA512:

				$this->signature = base64_encode($this->signature);
				break;
			}
		}

		//

		//
		$data .= base64_decode($this->signature);

		$packet->offset += strlen($data);

		return $data;
	}
}

?><?php

class Net_DNS2_RR_SMIMEA extends Net_DNS2_RR_TLSA
{
}

?><?php

class Net_DNS2_RR_SOA extends Net_DNS2_RR
{

	public $mname;

	public $rname;

	public $serial;

	public $refresh;

	public $retry;

	public $expire;

	public $minimum;

	protected function rrToString()
	{
		return $this->cleanString($this->mname) . '. ' .
			$this->cleanString($this->rname) . '. ' .
			$this->serial . ' ' . $this->refresh . ' ' . $this->retry . ' ' .
			$this->expire . ' ' . $this->minimum;
	}

	protected function rrFromString(array $rdata)
	{
		$this->mname    = $this->cleanString($rdata[0]);
		$this->rname    = $this->cleanString($rdata[1]);

		$this->serial   = $rdata[2];
		$this->refresh  = $rdata[3];
		$this->retry    = $rdata[4];
		$this->expire   = $rdata[5];
		$this->minimum  = $rdata[6];

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$offset = $packet->offset;

			$this->mname = Net_DNS2_Packet::expand($packet, $offset);
			$this->rname = Net_DNS2_Packet::expand($packet, $offset);

			//

			//
			$x = unpack(
				'@' . $offset . '/Nserial/Nrefresh/Nretry/Nexpire/Nminimum/',
				$packet->rdata
			);

			$this->serial   = Net_DNS2::expandUint32($x['serial']);
			$this->refresh  = Net_DNS2::expandUint32($x['refresh']);
			$this->retry    = Net_DNS2::expandUint32($x['retry']);
			$this->expire   = Net_DNS2::expandUint32($x['expire']);
			$this->minimum  = Net_DNS2::expandUint32($x['minimum']);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->mname) > 0) {

			$data = $packet->compress($this->mname, $packet->offset);
			$data .= $packet->compress($this->rname, $packet->offset);

			$data .= pack(
				'N5', $this->serial, $this->refresh, $this->retry,
				$this->expire, $this->minimum
			);

			$packet->offset += 20;

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_SPF extends Net_DNS2_RR_TXT
{
}

?><?php

class Net_DNS2_RR_SRV extends Net_DNS2_RR
{

	public $priority;

	public $weight;

	public $port;

	public $target;

	protected function rrToString()
	{
		return $this->priority . ' ' . $this->weight . ' ' .
			$this->port . ' ' . $this->cleanString($this->target) . '.';
	}

	protected function rrFromString(array $rdata)
	{
		$this->priority = $rdata[0];
		$this->weight   = $rdata[1];
		$this->port     = $rdata[2];

		$this->target   = $this->cleanString($rdata[3]);

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('npriority/nweight/nport', $this->rdata);

			$this->priority = $x['priority'];
			$this->weight   = $x['weight'];
			$this->port     = $x['port'];

			$offset         = $packet->offset + 6;
			$this->target   = Net_DNS2_Packet::expand($packet, $offset);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->target) > 0) {

			$data = pack('nnn', $this->priority, $this->weight, $this->port);
			$packet->offset += 6;

			$data .= $packet->compress($this->target, $packet->offset);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_SSHFP extends Net_DNS2_RR
{

	public $algorithm;

	public $fp_type;

	public $fingerprint;

	const SSHFP_ALGORITHM_RES       = 0;
	const SSHFP_ALGORITHM_RSA       = 1;
	const SSHFP_ALGORITHM_DSS       = 2;
	const SSHFP_ALGORITHM_ECDSA     = 3;
	const SSHFP_ALGORITHM_ED25519   = 4;

	const SSHFP_FPTYPE_RES      = 0;
	const SSHFP_FPTYPE_SHA1     = 1;
	const SSHFP_FPTYPE_SHA256   = 2;

	protected function rrToString()
	{
		return $this->algorithm . ' ' . $this->fp_type . ' ' . $this->fingerprint;
	}

	protected function rrFromString(array $rdata)
	{
		//

		//

		//
		$algorithm      = array_shift($rdata);
		$fp_type        = array_shift($rdata);
		$fingerprint    = strtolower(implode('', $rdata));

		//

		//
		if ( ($algorithm != self::SSHFP_ALGORITHM_RSA)
			&& ($algorithm != self::SSHFP_ALGORITHM_DSS)
			&& ($algorithm != self::SSHFP_ALGORITHM_ECDSA)
			&& ($algorithm != self::SSHFP_ALGORITHM_ED25519)
		) {
			return false;
		}

		//

		//
		if ( ($fp_type != self::SSHFP_FPTYPE_SHA1)
			&& ($fp_type != self::SSHFP_FPTYPE_SHA256)
		) {
			return false;
		}

		$this->algorithm    = $algorithm;
		$this->fp_type      = $fp_type;
		$this->fingerprint  = $fingerprint;

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('Calgorithm/Cfp_type', $this->rdata);

			$this->algorithm    = $x['algorithm'];
			$this->fp_type      = $x['fp_type'];

			//

			//
			if ( ($this->algorithm != self::SSHFP_ALGORITHM_RSA)
				&& ($this->algorithm != self::SSHFP_ALGORITHM_DSS)
				&& ($this->algorithm != self::SSHFP_ALGORITHM_ECDSA)
				&& ($this->algorithm != self::SSHFP_ALGORITHM_ED25519)
			) {
				return false;
			}

			//

			//
			if ( ($this->fp_type != self::SSHFP_FPTYPE_SHA1)
				&& ($this->fp_type != self::SSHFP_FPTYPE_SHA256)
			) {
				return false;
			}

			//

			//
			$fp = unpack('H*a', substr($this->rdata, 2));
			$this->fingerprint = strtolower($fp['a']);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->fingerprint) > 0) {

			$data = pack(
				'CCH*', $this->algorithm, $this->fp_type, $this->fingerprint
			);

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_TA extends Net_DNS2_RR_DS
{
}

?><?php

class Net_DNS2_RR_TALINK extends Net_DNS2_RR
{

	public $previous;

	public $next;

	protected function rrToString()
	{
		return $this->cleanString($this->previous) . '. ' .
			$this->cleanString($this->next) . '.';
	}

	protected function rrFromString(array $rdata)
	{
		$this->previous = $this->cleanString($rdata[0]);
		$this->next     = $this->cleanString($rdata[1]);

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			$offset         = $packet->offset;

			$this->previous = Net_DNS2_Packet::label($packet, $offset);
			$this->next     = Net_DNS2_Packet::label($packet, $offset);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if ( (strlen($this->previous) > 0) || (strlen($this->next) > 0) ) {

			$data = chr(strlen($this->previous)) . $this->previous .
				chr(strlen($this->next)) . $this->next;

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_TKEY extends Net_DNS2_RR
{
	public $algorithm;
	public $inception;
	public $expiration;
	public $mode;
	public $error;
	public $key_size;
	public $key_data;
	public $other_size;
	public $other_data;

	const TSIG_MODE_RES           = 0;
	const TSIG_MODE_SERV_ASSIGN   = 1;
	const TSIG_MODE_DH            = 2;
	const TSIG_MODE_GSS_API       = 3;
	const TSIG_MODE_RESV_ASSIGN   = 4;
	const TSIG_MODE_KEY_DELE      = 5;

	public $tsgi_mode_id_to_name = array(

		self::TSIG_MODE_RES           => 'Reserved',
		self::TSIG_MODE_SERV_ASSIGN   => 'Server Assignment',
		self::TSIG_MODE_DH            => 'Diffie-Hellman',
		self::TSIG_MODE_GSS_API       => 'GSS-API',
		self::TSIG_MODE_RESV_ASSIGN   => 'Resolver Assignment',
		self::TSIG_MODE_KEY_DELE      => 'Key Deletion'
	);

	protected function rrToString()
	{
		$out = $this->cleanString($this->algorithm) . '. ' . $this->mode;
		if ($this->key_size > 0) {

			$out .= ' ' . trim($this->key_data, '.') . '.';
		} else {

			$out .= ' .';
		}

		return $out;
	}

	protected function rrFromString(array $rdata)
	{
		//

		//
		$this->algorithm    = $this->cleanString(array_shift($rdata));
		$this->mode         = array_shift($rdata);
		$this->key_data     = trim(array_shift($rdata), '.');

		//

		//
		$this->inception    = time();
		$this->expiration   = time() + 86400;
		$this->error        = 0;
		$this->key_size     = strlen($this->key_data);
		$this->other_size   = 0;
		$this->other_data   = '';

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$offset = $packet->offset;
			$this->algorithm = Net_DNS2_Packet::expand($packet, $offset);

			//

			//
			$x = unpack(
				'@' . $offset . '/Ninception/Nexpiration/nmode/nerror/nkey_size',
				$packet->rdata
			);

			$this->inception    = Net_DNS2::expandUint32($x['inception']);
			$this->expiration   = Net_DNS2::expandUint32($x['expiration']);
			$this->mode         = $x['mode'];
			$this->error        = $x['error'];
			$this->key_size     = $x['key_size'];

			$offset += 14;

			//

			//
			if ($this->key_size > 0) {

				$this->key_data = substr($packet->rdata, $offset, $this->key_size);
				$offset += $this->key_size;
			}

			//

			//
			$x = unpack('@' . $offset . '/nother_size', $packet->rdata);

			$this->other_size = $x['other_size'];
			$offset += 2;

			//

			//
			if ($this->other_size > 0) {

				$this->other_data = substr(
					$packet->rdata, $offset, $this->other_size
				);
			}

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->algorithm) > 0) {

			//

			//
			$this->key_size     = strlen($this->key_data);
			$this->other_size   = strlen($this->other_data);

			//

			//
			$data = Net_DNS2_Packet::pack($this->algorithm);

			//

			//
			$data .= pack(
				'NNnnn', $this->inception, $this->expiration,
				$this->mode, 0, $this->key_size
			);

			//

			//
			if ($this->key_size > 0) {

				$data .= $this->key_data;
			}

			//

			//
			$data .= pack('n', $this->other_size);
			if ($this->other_size > 0) {

				$data .= $this->other_data;
			}

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_TLSA extends Net_DNS2_RR
{

	public $cert_usage;

	public $selector;

	public $matching_type;

	public $certificate;

	protected function rrToString()
	{
		return $this->cert_usage . ' ' . $this->selector . ' ' .
			$this->matching_type . ' ' . base64_encode($this->certificate);
	}

	protected function rrFromString(array $rdata)
	{
		$this->cert_usage       = array_shift($rdata);
		$this->selector         = array_shift($rdata);
		$this->matching_type    = array_shift($rdata);
		$this->certificate      = base64_decode(implode('', $rdata));

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('Cusage/Cselector/Ctype', $this->rdata);

			$this->cert_usage       = $x['usage'];
			$this->selector         = $x['selector'];
			$this->matching_type    = $x['type'];

			//

			//
			$this->certificate  = substr($this->rdata, 3, $this->rdlength - 3);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->certificate) > 0) {

			$data = pack(
				'CCC', $this->cert_usage, $this->selector, $this->matching_type
			) . $this->certificate;

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_TSIG extends Net_DNS2_RR
{

	const HMAC_MD5      = 'hmac-md5.sig-alg.reg.int';
	const GSS_TSIG      = 'gss-tsig';
	const HMAC_SHA1     = 'hmac-sha1';
	const HMAC_SHA224   = 'hmac-sha224';
	const HMAC_SHA256   = 'hmac-sha256';
	const HMAC_SHA384   = 'hmac-sha384';
	const HMAC_SHA512   = 'hmac-sha512';

	public static $hash_algorithms = array(

		self::HMAC_MD5      => 'md5',
		self::HMAC_SHA1     => 'sha1',
		self::HMAC_SHA224   => 'sha224',
		self::HMAC_SHA256   => 'sha256',
		self::HMAC_SHA384   => 'sha384',
		self::HMAC_SHA512   => 'sha512'
	);

	public $algorithm;

	public $time_signed;

	public $fudge;

	public $mac_size;

	public $mac;

	public $original_id;

	public $error;

	public $other_length;

	public $other_data;

	public $key;

	protected function rrToString()
	{
		$out = $this->cleanString($this->algorithm) . '. ' .
			$this->time_signed . ' ' .
			$this->fudge . ' ' . $this->mac_size . ' ' .
			base64_encode($this->mac) . ' ' . $this->original_id . ' ' .
			$this->error . ' '. $this->other_length;

		if ($this->other_length > 0) {

			$out .= ' ' . $this->other_data;
		}

		return $out;
	}

	protected function rrFromString(array $rdata)
	{
		//

		//

		//
		$this->key = preg_replace('/\s+/', '', array_shift($rdata));

		//

		//
		$this->algorithm    = self::HMAC_MD5;
		$this->time_signed  = time();
		$this->fudge        = 300;
		$this->mac_size     = 0;
		$this->mac          = '';
		$this->original_id  = 0;
		$this->error        = 0;
		$this->other_length = 0;
		$this->other_data   = '';

		//

		//
		$this->class        = 'ANY';
		$this->ttl          = 0;

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$newoffset          = $packet->offset;
			$this->algorithm    = Net_DNS2_Packet::expand($packet, $newoffset);
			$offset             = $newoffset - $packet->offset;

			//

			//
			$x = unpack(
				'@' . $offset . '/ntime_high/Ntime_low/nfudge/nmac_size',
				$this->rdata
			);

			$this->time_signed  = Net_DNS2::expandUint32($x['time_low']);
			$this->fudge        = $x['fudge'];
			$this->mac_size     = $x['mac_size'];

			$offset += 10;

			//

			//
			if ($this->mac_size > 0) {

				$this->mac = substr($this->rdata, $offset, $this->mac_size);
				$offset += $this->mac_size;
			}

			//

			//
			$x = unpack(
				'@' . $offset . '/noriginal_id/nerror/nother_length',
				$this->rdata
			);

			$this->original_id  = $x['original_id'];
			$this->error        = $x['error'];
			$this->other_length = $x['other_length'];

			//

			//

			//
			if ($this->error == Net_DNS2_Lookups::RCODE_BADTIME) {

				if ($this->other_length != 6) {

					return false;
				}

				//

				//
				$x = unpack(
					'nhigh/nlow',
					substr($this->rdata, $offset + 6, $this->other_length)
				);
				$this->other_data = $x['low'];
			}

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->key) > 0) {

			//

			//
			$new_packet = new Net_DNS2_Packet_Request('example.com', 'SOA', 'IN');

			//

			//
			$new_packet->copy($packet);

			//

			//
			array_pop($new_packet->additional);
			$new_packet->header->arcount = count($new_packet->additional);

			//

			//
			$sig_data = $new_packet->get();

			//

			//
			$sig_data .= Net_DNS2_Packet::pack($this->name);

			//

			//
			$sig_data .= pack(
				'nN', Net_DNS2_Lookups::$classes_by_name[$this->class], $this->ttl
			);

			//

			//
			$sig_data .= Net_DNS2_Packet::pack(strtolower($this->algorithm));

			//

			//
			$sig_data .= pack(
				'nNnnn', 0, $this->time_signed, $this->fudge,
				$this->error, $this->other_length
			);
			if ($this->other_length > 0) {

				$sig_data .= pack('nN', 0, $this->other_data);
			}

			//

			//
			$this->mac = $this->_signHMAC(
				$sig_data, base64_decode($this->key), $this->algorithm
			);
			$this->mac_size = strlen($this->mac);

			//

			//
			$data = Net_DNS2_Packet::pack(strtolower($this->algorithm));

			//

			//
			$data .= pack(
				'nNnn', 0, $this->time_signed, $this->fudge, $this->mac_size
			);
			$data .= $this->mac;

			//

			//
			if ($this->error == Net_DNS2_Lookups::RCODE_BADTIME) {

				$this->other_length = strlen($this->other_data);
				if ($this->other_length != 6) {

					return null;
				}
			} else {

				$this->other_length = 0;
				$this->other_data = '';
			}

			//

			//
			$data .= pack(
				'nnn', $packet->header->id, $this->error, $this->other_length
			);
			if ($this->other_length > 0) {

				$data .= pack('nN', 0, $this->other_data);
			}

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}

	private function _signHMAC($data, $key = null, $algorithm = self::HMAC_MD5)
	{
		//

		//
		if (extension_loaded('hash')) {

			if (!isset(self::$hash_algorithms[$algorithm])) {

				throw new Net_DNS2_Exception(
					'invalid or unsupported algorithm',
					Net_DNS2_Lookups::E_PARSE_ERROR
				);
			}

			return hash_hmac(self::$hash_algorithms[$algorithm], $data, $key, true);
		}

		//

		//
		if ($algorithm != self::HMAC_MD5) {

			throw new Net_DNS2_Exception(
				'only HMAC-MD5 supported. please install the php-extension ' .
				'"hash" in order to use the sha-family',
				Net_DNS2_Lookups::E_PARSE_ERROR
			);
		}

		//

		//
		if (is_null($key)) {

			return pack('H*', md5($data));
		}

		$key = str_pad($key, 64, chr(0x00));
		if (strlen($key) > 64) {

			$key = pack('H*', md5($key));
		}

		$k_ipad = $key ^ str_repeat(chr(0x36), 64);
		$k_opad = $key ^ str_repeat(chr(0x5c), 64);

		return $this->_signHMAC(
			$k_opad . pack('H*', md5($k_ipad . $data)), null, $algorithm
		);
	}
}

?><?php

class Net_DNS2_RR_TXT extends Net_DNS2_RR
{

	public $text = array();

	protected function rrToString()
	{
		if (count($this->text) == 0) {
			return '""';
		}

		$data = '';

		foreach ($this->text as $t) {

			$data .= $this->formatString($t) . ' ';
		}

		return trim($data);
	}

	protected function rrFromString(array $rdata)
	{
		$data = $this->buildString($rdata);
		if (count($data) > 0) {

			$this->text = $data;
		}

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			$length = $packet->offset + $this->rdlength;
			$offset = $packet->offset;

			while ($length > $offset) {

				$this->text[] = Net_DNS2_Packet::label($packet, $offset);
			}

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		$data = null;

		foreach ($this->text as $t) {

			$data .= chr(strlen($t)) . $t;
		}

		$packet->offset += strlen($data);

		return $data;
	}
}

?><?php

class Net_DNS2_RR_TYPE65534 extends Net_DNS2_RR
{

	public $private_data;

	protected function rrToString()
	{
		return base64_encode($this->private_data);
	}

	protected function rrFromString(array $rdata)
	{
		$this->private_data = base64_decode(implode('', $rdata));

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {
			$this->private_data  = $this->rdata;

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->private_data) > 0) {

			$data = $this->private_data;

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_URI extends Net_DNS2_RR
{

	public $priority;

	public $weight;

	public $target;

	protected function rrToString()
	{
		//

		//
		return $this->priority . ' ' . $this->weight . ' "' .
			$this->cleanString($this->target) . '"';
	}

	protected function rrFromString(array $rdata)
	{
		$this->priority = $rdata[0];
		$this->weight   = $rdata[1];

		//

		//
		$this->target   = trim($this->cleanString($rdata[2]), '"');

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('npriority/nweight', $this->rdata);

			$this->priority = $x['priority'];
			$this->weight   = $x['weight'];

			$offset         = $packet->offset + 4;
			$this->target   = Net_DNS2_Packet::expand($packet, $offset);

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->target) > 0) {

			$data = pack('nn', $this->priority, $this->weight);
			$packet->offset += 4;

			$data .= $packet->compress(trim($this->target, '"'), $packet->offset);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_WKS extends Net_DNS2_RR
{

	public $address;

	public $protocol;

	public $bitmap = array();

	protected function rrToString()
	{
		$data = $this->address . ' ' . $this->protocol;

		foreach ($this->bitmap as $port) {
			$data .= ' ' . $port;
		}

		return $data;
	}

	protected function rrFromString(array $rdata)
	{
		$this->address  = strtolower(trim(array_shift($rdata), '.'));
		$this->protocol = array_shift($rdata);
		$this->bitmap   = $rdata;

		return true;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			//

			//
			$x = unpack('Naddress/Cprotocol', $this->rdata);

			$this->address  = long2ip($x['address']);
			$this->protocol = $x['protocol'];

			//

			//
			$port = 0;
			foreach (unpack('@5/C*', $this->rdata) as $set) {

				$s = sprintf('%08b', $set);

				for ($i=0; $i<8; $i++, $port++) {
					if ($s[$i] == '1') {
						$this->bitmap[] = $port;
					}
				}
			}

			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->address) > 0) {

			$data = pack('NC', ip2long($this->address), $this->protocol);

			$ports = array();

			$n = 0;
			foreach ($this->bitmap as $port) {
				$ports[$port] = 1;

				if ($port > $n) {
					$n = $port;
				}
			}
			for ($i=0; $i<ceil($n/8)*8; $i++) {
				if (!isset($ports[$i])) {
					$ports[$i] = 0;
				}
			}

			ksort($ports);

			$string = '';
			$n = 0;

			foreach ($ports as $s) {

				$string .= $s;
				$n++;

				if ($n == 8) {

					$data .= chr(bindec($string));
					$string = '';
					$n = 0;
				}
			}

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_RR_X25 extends Net_DNS2_RR
{

	public $psdnaddress;

	protected function rrToString()
	{
		return $this->formatString($this->psdnaddress);
	}

	protected function rrFromString(array $rdata)
	{
		$data = $this->buildString($rdata);
		if (count($data) == 1) {

			$this->psdnaddress = $data[0];
			return true;
		}

		return false;
	}

	protected function rrSet(Net_DNS2_Packet &$packet)
	{
		if ($this->rdlength > 0) {

			$this->psdnaddress = Net_DNS2_Packet::label($packet, $packet->offset);
			return true;
		}

		return false;
	}

	protected function rrGet(Net_DNS2_Packet &$packet)
	{
		if (strlen($this->psdnaddress) > 0) {

			$data = chr(strlen($this->psdnaddress)) . $this->psdnaddress;

			$packet->offset += strlen($data);

			return $data;
		}

		return null;
	}
}

?><?php

class Net_DNS2_Socket_Sockets extends Net_DNS2_Socket
{

	public function open()
	{
		//

		//
		if (Net_DNS2::isIPv4($this->host) == true) {

			$this->sock = @socket_create(
				AF_INET, $this->type,
				($this->type == Net_DNS2_Socket::SOCK_STREAM) ? SOL_TCP : SOL_UDP
			);

		} else if (Net_DNS2::isIPv6($this->host) == true) {

			$this->sock = @socket_create(
				AF_INET6, $this->type,
				($this->type == Net_DNS2_Socket::SOCK_STREAM) ? SOL_TCP : SOL_UDP
			);

		} else {

			$this->last_error = 'invalid address type: ' . $this->host;
			return false;
		}

		if ($this->sock === false) {

			$this->last_error = socket_strerror(socket_last_error());
			return false;
		}

		@socket_set_option($this->sock, SOL_SOCKET, SO_REUSEADDR, 1);

		//

		//
		if (strlen($this->local_host) > 0) {

			$result = @socket_bind(
				$this->sock, $this->local_host,
				($this->local_port > 0) ? $this->local_port : null
			);
			if ($result === false) {

				$this->last_error = socket_strerror(socket_last_error());
				return false;
			}
		}

		//

		//
		if (@socket_set_nonblock($this->sock) === false) {

			$this->last_error = socket_strerror(socket_last_error());
			return false;
		}

		//

		//
		@socket_connect($this->sock, $this->host, $this->port);

		$read   = null;
		$write  = array($this->sock);
		$except = null;

		//

		//
		$result = @socket_select($read, $write, $except, $this->timeout);
		if ($result === false) {

			$this->last_error = socket_strerror(socket_last_error());
			return false;

		} else if ($result == 0) {

			$this->last_error = 'timeout on write select for connect()';
			return false;
		}

		return true;
	}

	public function close()
	{
		if (is_resource($this->sock) === true) {

			@socket_close($this->sock);
		}
		return true;
	}

	public function write($data)
	{
		$length = strlen($data);
		if ($length == 0) {

			$this->last_error = 'empty data on write()';
			return false;
		}

		$read   = null;
		$write  = array($this->sock);
		$except = null;

		//

		//
		$result = @socket_select($read, $write, $except, $this->timeout);
		if ($result === false) {

			$this->last_error = socket_strerror(socket_last_error());
			return false;

		} else if ($result == 0) {

			$this->last_error = 'timeout on write select()';
			return false;
		}

		//

		//
		if ($this->type == Net_DNS2_Socket::SOCK_STREAM) {

			$s = chr($length >> 8) . chr($length);

			if (@socket_write($this->sock, $s) === false) {

				$this->last_error = socket_strerror(socket_last_error());
				return false;
			}
		}

		//

		//
		$size = @socket_write($this->sock, $data);
		if ( ($size === false) || ($size != $length) ) {

			$this->last_error = socket_strerror(socket_last_error());
			return false;
		}

		return true;
	}

	public function read(&$size, $max_size)
	{
		$read   = array($this->sock);
		$write  = null;
		$except = null;

		//

		//
		if (@socket_set_nonblock($this->sock) === false) {

			$this->last_error = socket_strerror(socket_last_error());
			return false;
		}

		//

		//
		$result = @socket_select($read, $write, $except, $this->timeout);
		if ($result === false) {

			$this->last_error = socket_strerror(socket_last_error());
			return false;

		} else if ($result == 0) {

			$this->last_error = 'timeout on read select()';
			return false;
		}

		$data = '';
		$length = $max_size;

		//

		//
		if ($this->type == Net_DNS2_Socket::SOCK_STREAM) {

			if (($size = @socket_recv($this->sock, $data, 2, 0)) === false) {

				$this->last_error = socket_strerror(socket_last_error());
				return false;
			}

			$length = ord($data[0]) << 8 | ord($data[1]);
			if ($length < Net_DNS2_Lookups::DNS_HEADER_SIZE) {

				return false;
			}
		}

		//

		//

		//
		if (@socket_set_block($this->sock) === false) {

			$this->last_error = socket_strerror(socket_last_error());
			return false;
		}

		//

		//

		//

		//
		$data = '';
		$size = 0;

		while (1) {

			$chunk_size = @socket_recv($this->sock, $chunk, $length, MSG_WAITALL);
			if ($chunk_size === false) {

				$size = $chunk_size;
				$this->last_error = socket_strerror(socket_last_error());

				return false;
			}

			$data .= $chunk;
			$size += $chunk_size;

			$length -= $chunk_size;
			if ( ($length <= 0) || ($this->type == Net_DNS2_Socket::SOCK_DGRAM) ) {
				break;
			}
		}

		return $data;
	}
}

?><?php

class Net_DNS2_Socket_Streams extends Net_DNS2_Socket
{
	private $_context;

	public function open()
	{
		//

		//
		$opts = array('socket' => array());

		//

		//
		if (strlen($this->local_host) > 0) {

			$opts['socket']['bindto'] = $this->local_host;
			if ($this->local_port > 0) {

				$opts['socket']['bindto'] .= ':' . $this->local_port;
			}
		}

		//

		//
		$this->_context = @stream_context_create($opts);

		//

		//
		$errno;
		$errstr;

		switch($this->type) {
		case Net_DNS2_Socket::SOCK_STREAM:

			if (Net_DNS2::isIPv4($this->host) == true) {

				$this->sock = @stream_socket_client(
					'tcp://' . $this->host . ':' . $this->port,
					$errno, $errstr, $this->timeout,
					STREAM_CLIENT_CONNECT, $this->_context
				);
			} else if (Net_DNS2::isIPv6($this->host) == true) {

				$this->sock = @stream_socket_client(
					'tcp://[' . $this->host . ']:' . $this->port,
					$errno, $errstr, $this->timeout,
					STREAM_CLIENT_CONNECT, $this->_context
				);
			} else {

				$this->last_error = 'invalid address type: ' . $this->host;
				return false;
			}

			break;

		case Net_DNS2_Socket::SOCK_DGRAM:

			if (Net_DNS2::isIPv4($this->host) == true) {

				$this->sock = @stream_socket_client(
					'udp://' . $this->host . ':' . $this->port,
					$errno, $errstr, $this->timeout,
					STREAM_CLIENT_CONNECT, $this->_context
				);
			} else if (Net_DNS2::isIPv6($this->host) == true) {

				$this->sock = @stream_socket_client(
					'udp://[' . $this->host . ']:' . $this->port,
					$errno, $errstr, $this->timeout,
					STREAM_CLIENT_CONNECT, $this->_context
				);
			} else {

				$this->last_error = 'invalid address type: ' . $this->host;
				return false;
			}

			break;

		default:
			$this->last_error = 'Invalid socket type: ' . $this->type;
			return false;
		}

		if ($this->sock === false) {

			$this->last_error = $errstr;
			return false;
		}

		//

		//
		@stream_set_blocking($this->sock, 0);
		@stream_set_timeout($this->sock, $this->timeout);

		return true;
	}

	public function close()
	{
		if (is_resource($this->sock) === true) {

			@fclose($this->sock);
		}
		return true;
	}

	public function write($data)
	{
		$length = strlen($data);
		if ($length == 0) {

			$this->last_error = 'empty data on write()';
			return false;
		}

		$read   = null;
		$write  = array($this->sock);
		$except = null;

		//

		//
		$result = stream_select($read, $write, $except, $this->timeout);
		if ($result === false) {

			$this->last_error = 'failed on write select()';
			return false;

		} else if ($result == 0) {

			$this->last_error = 'timeout on write select()';
			return false;
		}

		//

		//
		if ($this->type == Net_DNS2_Socket::SOCK_STREAM) {

			$s = chr($length >> 8) . chr($length);

			if (@fwrite($this->sock, $s) === false) {

				$this->last_error = 'failed to fwrite() 16bit length';
				return false;
			}
		}

		//

		//
		$size = @fwrite($this->sock, $data);
		if ( ($size === false) || ($size != $length) ) {

			$this->last_error = 'failed to fwrite() packet';
			return false;
		}

		return true;
	}

	public function read(&$size, $max_size)
	{
		$read   = array($this->sock);
		$write  = null;
		$except = null;

		//

		//
		@stream_set_blocking($this->sock, 0);

		//

		//
		$result = stream_select($read, $write, $except, $this->timeout);
		if ($result === false) {

			$this->last_error = 'error on read select()';
			return false;

		} else if ($result == 0) {

			$this->last_error = 'timeout on read select()';
			return false;
		}

		$data = '';
		$length = $max_size;

		//

		//
		if ($this->type == Net_DNS2_Socket::SOCK_STREAM) {

			if (($data = fread($this->sock, 2)) === false) {

				$this->last_error = 'failed on fread() for data length';
				return false;
			}

			$length = ord($data[0]) << 8 | ord($data[1]);
			if ($length < Net_DNS2_Lookups::DNS_HEADER_SIZE) {

				return false;
			}
		}

		//

		//

		//
		@stream_set_blocking($this->sock, 1);

		//

		//
		$data = '';

		//

		//

		//
		if ($this->type == Net_DNS2_Socket::SOCK_STREAM) {

			$chunk = '';
			$chunk_size = $length;

			//

			//
			while (1) {

				$chunk = fread($this->sock, $chunk_size);
				if ($chunk === false) {

					$this->last_error = 'failed on fread() for data';
					return false;
				}

				$data .= $chunk;
				$chunk_size -= strlen($chunk);

				if (strlen($data) >= $length) {
					break;
				}
			}

		} else {

			//

			//
			$data = fread($this->sock, $length);
			if ($length === false) {

				$this->last_error = 'failed on fread() for data';
				return false;
			}
		}

		$size = strlen($data);

		return $data;
	}
}

?>