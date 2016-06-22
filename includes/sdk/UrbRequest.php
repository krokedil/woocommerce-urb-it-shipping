<?php
	if(!class_exists('UrbRequest')) {
		class UrbRequest {
			const PROD_BASE_URL = 'https://retailer-api.urb-it.com/api/';
			const STAGE_BASE_URL = 'https://stage-retailer-api.urb-it.com/api/';
			
			public $storeKey;
			public $sharedSecret;
			protected $stage = false;
			protected $baseUrl;
			
			public $httpStatus;
			public $httpBody;
			
			
			public function __construct($storeKey = '', $sharedSecret = '', $stage = false) {
				if(version_compare(PHP_VERSION, '5.2.0') < 0) {
					throw new UrbException('UrbRequest requires at least PHP version 5.2.0.');
				}
				
				if(!function_exists('curl_init')) {
					throw new UrbException('cURL is required for UrbRequest to work.');
				}
				
				$this->storeKey = (string)$storeKey;
				$this->sharedSecret = (string)$sharedSecret;
				$this->stage = (bool)$stage;
				$this->baseUrl = ($this->stage ? self::STAGE_BASE_URL : self::PROD_BASE_URL);
				
				if(!$this->storeKey) {
					throw new UrbException('Store key is missing.');
				}
				
				if(!$this->sharedSecret) {
					throw new UrbException('Shared secret is missing.');
				}
			}
			
			
			public function GetOpeningHours($from = '', $to = '') {
				$dateRegex = '/^[\d]{4}-[\d]{2}-[\d]{2}$/';
				
				if(!preg_match($dateRegex, $from) || !preg_match($dateRegex, $to)) {
					throw new UrbException('From and To parameters must be in YYYY-MM-DD format.');
				}
				
				if($from > $to) {
					throw new UrbException('From cannot be greater than To.');
				}
				
				$this->Call('GET', 'openinghours', array('from' => $from, 'to' => $to));
				
				if($this->httpStatus !== 200) {
					throw new UrbException('HTTP ' . $this->httpStatus, $this->httpBody);
				}
				
				return $this->httpBody;
			}
			
			
			public function ValidatePostalCode($postalCode = '') {
				if(!preg_match('/^[\d]{3}\s?[\d]{2}$/', $postalCode)) {
					throw new UrbException('Invalid postal code.');
				}
				
				$postalCode = str_replace(' ', '', $postalCode);
				
				$this->Call('POST', 'postalcode/validate', array('postal_code' => $postalCode));
				
				return ($this->httpStatus === 200);
			}
			
			
			public function ValidateDelivery($order = array()) {
				$this->Call('POST', 'delivery/validate', $order);
				
				if($this->httpStatus !== 200) {
					if($this->httpBody && isset($this->httpBody->developer_message)) {
						throw new UrbException($this->httpBody->developer_message, $this->httpBody);
					}
					else {
						throw new UrbException('HTTP ' . $this->httpStatus);
					}
				}
				
				return $this->httpBody;
			}
			
			
			public function CreateOrder($order = array()) {
				$this->Call('POST', 'order', $order);
				
				if($this->httpStatus !== 200) {
					if($this->httpBody && isset($this->httpBody->developer_message)) {
						throw new UrbException($this->httpBody->developer_message, $this->httpBody);
					}
					else {
						throw new UrbException('HTTP ' . $this->httpStatus);
					}
				}
				
				return $this->httpBody;
			}
			
			
			protected function Call($method = 'GET', $url = '', $data = array()) {
				$url = $this->baseUrl . $url;
				
				$json = ($method === 'POST' && $data) ? json_encode($data) : '';
				
				if($method === 'GET' && $data) $url .= '?' . http_build_query($data);
				
				$ch = curl_init($url);
				
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
				if($json) curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
				curl_setopt($ch, CURLOPT_TIMEOUT, 15);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
				curl_setopt($ch, CURLINFO_HEADER_OUT, true);
				
				$headers = array(
					'Accept-Encoding: gzip,deflate',
					'Content-Type: application/json;charset=UTF-8',
					'Cache-Control: no-cache',
					'Content-Length: ' . strlen($json),
					'Authorization: ' . $this->GetAuthorizationHeader($this->storeKey, $this->sharedSecret, $method, $url, $json)
				);
				
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				
				$response = curl_exec($ch);
				
				$this->httpBody = ($response ? json_decode($response) : null);
				$this->httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
				
				curl_close($ch);
				
				return $this->httpStatus;
			}
			
			
			protected function GetAuthorizationHeader($store_key = '', $shared_secret = '', $method = 'POST', $url = '', $json = '') {
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
		}
	}
	
	if(!class_exists('UrbException')) {
		class UrbException extends Exception {
			protected $params = array();
			
			public function __construct($message = '', $params = array(), $code = 0, $previous = null) {
				parent::__construct($message, $code, $previous);
				
				$this->params = (array)$params;
			}
			
			public function getParams() {
				return $this->params;
			}
			
			public function getParam($param) {
				return $this->params[$param];
			}
			
			public function hasParam($param) {
				return isset($this->params[$param]);
			}
		}
	}
?>