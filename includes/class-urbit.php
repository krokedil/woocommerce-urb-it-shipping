<?php
	class Urbit_Client {
		const BASE_PATH = 'https://retailer-api.urb-it.com/api';
		const BASE_PATH_TEST = 'https://stage-retailer-api.urb-it.com/api';
		const BASE_PATH_DEV = 'https://dev-retailer-api.urb-it.com/api';
		
		const CONNECTTIMEOUT = 15;
		const TIMEOUT = 15;
		
		protected $consumer_key;
		protected $consumer_secret;
		protected $token;
		protected $location_id;
		
		public $path;
		protected $method = 'POST';
		public $params = array();
		
		public $test = false;
		public $dev = false;
		public $result;
		
		
		public function __construct($settings = array()) {
			$this->settings($settings);
		}
		
		
		public function __call($method, $arguments) {
			$this->method = strtoupper($method);
			$this->get_path($arguments[0]);
			
			if($this->send() !== 200) return false;
			
			return $this->result;
		}
		
		
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
		
		
		public function settings($settings = array()) {
			if(isset($settings['consumer_key'])) $this->consumer_key = $settings['consumer_key'];
			if(isset($settings['consumer_secret'])) $this->consumer_secret = $settings['consumer_secret'];
			if(isset($settings['token'])) $this->token = $settings['token'];
			if(isset($settings['location_id'])) $this->location_id = $settings['location_id'];
			if(isset($settings['is_test'])) $this->test = ($settings['is_test'] ? true : false);
			
			$this->params['store_location']['id'] = $this->location_id;
			$this->params['pickup_location']['id'] = $this->location_id;
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
		
		
		protected function send() {
			if(!function_exists('curl_version') || empty($this->consumer_key) || empty($this->consumer_secret) || empty($this->token) || empty($this->location_id)) return false;
			
			$endpoint = $this->path;
			
			if(!empty($this->params)) {
				if($this->method === 'GET') $endpoint .= '?' . http_build_query($this->params);
				else $json = json_encode($this->params);
			}
			
			#$this->path .= '?from=2015-09-03&to=2015-09-03';
			#echo $this->path;
			
			$ch = curl_init($endpoint);
			
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
			if($json) curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECTTIMEOUT);
			curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			
			$sign_params = array(
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
			);
			
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			
			$this->result = @json_decode(curl_exec($ch));
			
			$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			
			curl_close($ch);
			
			return $status_code;
		}
		
		
		protected function signature($sign_params, $json) {
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
		}
		
		
		protected function quoted_query($array) {
			$return = '';
			
			foreach($array as $key => $value) {
				$return .= $key . '="' . $value . '", ';
			}
			
			return substr($return, 0, -2);
		}
		
		
		protected function get_path($path = '') {
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
?>