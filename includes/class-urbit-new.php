<?php
	class Urbit_Client {
		const BASE_PATH = 'https://retailer-api.urb-it.com/api';
		const BASE_PATH_TEST = 'https://stage-retailer-api.urb-it.com/api';
		const BASE_PATH_DEV = 'https://dev-retailer-api.urb-it.com/api';
		
		const CONNECTTIMEOUT = 15;
		const TIMEOUT = 15;
		
		#public $consumer_key;
		public $consumer_secret;
		public $token;
		
		public $path;
		public $method = 'POST';
		public $params = array();
		public $request = array();
		public $request_body = '';
		
		public $test = false;
		public $dev = false;
		public $result;
		
		
		public function validate() {
			$this->get_path('delivery/validate');
			
			return $this->send();
		}
		
		
		public function create_order() {
			$this->get_path('order');
			
			return $this->send();
		}
		
		
		public function get_opening_hours() {
			$this->get_path('openinghours');
			$this->method = 'GET';
			
			foreach(array_keys($this->params) as $key) {
				if(!in_array($key, array('to', 'from'))) unset($this->params[$key]);
			}
			
			return $this->send();
		}
		
		
		public function set($key, $value) {
			if($key == 'articles') return;
			
			if($value instanceof DateTime) $value = $value->format(DateTime::RFC3339);
			
			$this->params[$key] = $value;
		}
		
		
		public function get($key) {
			if(isset($this->params[$key])) return $this->params[$key];
		}
		
		
		public function add_article($args = array()) {
			if(!is_array($args)) return;
			
			if(!isset($this->params['articles'])) $this->params['articles'] = array();
			
			$this->params['articles'][] = $args;
		}
		
		
		public function send() {
			if(!function_exists('curl_version') || empty($this->consumer_secret) || empty($this->token)) return false;
			
			$endpoint = $this->path;
			
			/*if(!empty($this->params)) {
				if($this->method === 'GET') $endpoint .= '?' . http_build_query($this->params);
				else $json = json_encode($this->params);
			}*/
			
			$json = $this->params ? json_encode($this->params) : '';
			
			$this->request_body = $json;
			
			#$this->path .= '?from=2015-09-03&to=2015-09-03';
			#echo $this->path;
			
			$ch = curl_init($endpoint);
			
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
			if($this->method === 'POST') curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECTTIMEOUT);
			curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
			curl_setopt($ch, CURLINFO_HEADER_OUT, true);
			
			#$timestamp = time();
			#$nonce = md5(microtime(true) . rand(0, 999999));
			#$timestamp = 1456997419;
			#$nonce = 'b140491583a8dfd29a506d5d5ab13057';
			#$signature = $this->signature($json, $timestamp, $nonce);
			
			#echo $timestamp . "\n";
			#echo $nonce . "\n";
			#echo $signature . "\n";
			
			$headers = array(
				'Accept-Encoding: gzip,deflate',
				'Content-Type: application/json;charset=UTF-8',
				'Cache-Control: no-cache',
				'Content-Length: ' . strlen($json),
				'Authorization: ' . get_authorization_header($this->token, $this->consumer_secret, $this->method, $endpoint, $json)
				#'Authorization: UWA ' . implode(':', array($this->token, $signature, $nonce, $timestamp))
			);
			
			#var_dump($headers);
			
			/*$sign_params = array(
				'oauth_consumer_key' => $this->consumer_key,
				'oauth_nonce' => md5($_SERVER['SERVER_NAME'] . $_SERVER['REMOTE_ADDR'] . microtime() . rand(0, 9999)),
				'oauth_signature_method' => 'HMAC-SHA1',
				'oauth_timestamp' => time(),
				'oauth_token' => $this->token,
				'oauth_version' => '1.0'
			);
			
			$sign_params['oauth_signature'] = $this->signature($sign_params, $json);
			
			$headers = array(
				'Content-Type: application/json',
				'Cache-Control: no-cache',
				'Content-Length: ' . strlen($json),
				'Authorization: OAuth ' . $this->quoted_query($sign_params)
			);*/
			
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			
			$this->result = @json_decode(curl_exec($ch));
			
			$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$this->request = curl_getinfo($ch);
			
			curl_close($ch);
			
			return $status_code;
		}
		
		
		/*private function signature($sign_params, $json) {
			$parameters = http_build_query($sign_params, '', '&', PHP_QUERY_RFC3986);
			
			if($this->method === 'GET') {
				$parameters_and_content = (!empty($this->params) ? (http_build_query($this->params) . '&') : '') . $parameters;
			}
			else {
				$content = rawurlencode($json);
				$parameters_and_content = $parameters . '&content=' . $content;
			}
			
			$message = $this->method . '&' . rawurlencode($this->path) . '&' . rawurlencode($parameters_and_content);
			
			$hmac = hash_hmac('sha1', $message, rawurlencode($this->consumer_secret), true);
			
			return base64_encode($hmac);
		}*/
		
		
		private function signature($json = '', $timestamp = 0, $nonce = '') {
			#1
			//$json = $data ? json_encode($data) : '';
			$json = utf8_encode($json);
			$md5_digest = md5($json, true);
			$base64_digest = base64_encode($md5_digest);
			
			#echo $json . "\n\n";
			#echo $md5_digest . "\n\n";
			#echo $base64_digest . "\n\n";
			
			#2
			//$timestamp = time();
			
			#3
			//$nonce = md5(microtime(true) . rand(0, 999999));
			
			#4
			$msg = implode('', array($this->token, $this->method, $this->path, $timestamp, $nonce, $base64_digest));
			#echo $msg . "\n\n";
			#return $msg;
			
			#5
			$byte_array = base64_decode($this->consumer_secret);
			$signature = hash_hmac('sha256', utf8_encode($msg), $byte_array, true);
			
			return base64_encode($signature);
		}
		
		
		private function quoted_query($array) {
			$return = '';
			
			foreach($array as $key => $value) {
				$return .= $key . '="' . $value . '", ';
			}
			
			return substr($return, 0, -2);
		}
		
		
		private function get_path($path = '') {
			if($path) $path = '/' . trim($path, '/');
			
			if($this->test) {
				$this->path = ($this->dev ? self::BASE_PATH_DEV : self::BASE_PATH_TEST) . $path;
			}
			else {
				$this->path = self::BASE_PATH . $path;
			}
			
			return $this->path;
		}
	}
	
	
	function get_authorization_header($store_key = '', $shared_secret = '', $method = 'POST', $url = '', $json = '') {
		// Ensure JSON content is encoded a UTF-8
		$json = utf8_encode($json);
		
		// Create MD5 digest ($raw_output = true)
		$md5_digest = md5($json, true);
		
		// Create Base64 digest
		$base64_digest = base64_encode($md5_digest);
		
		// Get current Unix timestamp
		$timestamp = time();
		
		// Create a unique nonce
		$nonce = md5(microtime(true) . $_SERVER['REMOTE_ADDR'] . rand(0, 999999));
		
		// Concatenate data
		$msg = implode('', array(
			$store_key,
			strtoupper($method),
			strtolower($url),
			$timestamp,
			$nonce,
			$json ? $base64_digest : ''
		));
		
		// Decode shared secret (used as a byte array)
		$byte_array = base64_decode($shared_secret);
		
		// Create signature
		$signature = base64_encode(hash_hmac('sha256', utf8_encode($msg), $byte_array, true));
		
		// Return header
		return 'UWA ' . implode(':', array($store_key, $signature, $nonce, $timestamp));
	}
?>