<?php
class _DevblocksMultiFactorAuthService {
	static $instance = null;
	
	private function __construct(){
	}
	
	static function getInstance() {
		if(null == self::$instance) {
			self::$instance = new _DevblocksMultiFactorAuthService();
		}
		
		return self::$instance;
	}
	
	public function generateMultiFactorOtpSeed($length=24) {
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$seed = '';
		
		for($x=0; $x<$length; $x++) {
			$seed .= substr($alphabet, mt_rand(0, 31), 1);
		}
		
		return $seed;
	}
	
	public function getMultiFactorOtpFromSeed($seed) {
		$timestamp = floor(microtime(true)/30);
		$binary_key = DevblocksPlatform::strBase32Decode($seed);
		$binary_timestamp = pack('N*', 0) . pack('N*', $timestamp);
		
		// References: https://github.com/tadeck/onetimepass
		$hash = hash_hmac('sha1', $binary_timestamp, $binary_key, true);
		$offset = ord($hash[19]) & 0xf;
		$bytes = unpack("N*", substr($hash, $offset, 4));
		
		return (array_shift($bytes) & 0x7fffffff) % 1000000;
	}
};
