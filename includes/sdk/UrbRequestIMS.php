<?php
	class UrbRequestIMS extends UrbRequest {
		const IMS_PROD_BASE_URL = 'http://ims-api.urb-it.com/v1/';
		#const IMS_STAGE_BASE_URL = 'http://stage-ims-api.urb-it.com/';
		const IMS_STAGE_BASE_URL = 'http://52.31.215.64/v1/';
		const IMS_DEV_BASE_URL = 'http://52.49.94.47/v1/';
		
		
		public function __construct($storeKey = '', $sharedSecret = '', $stage = false) {
			parent::__construct($storeKey, $sharedSecret, $stage);
			
			#$this->baseUrl = ($this->stage ? self::IMS_STAGE_BASE_URL : self::IMS_PROD_BASE_URL);
			$this->baseUrl = self::IMS_DEV_BASE_URL;
		}
		
		
		public function __call($name, $args = array()) {
			// Turn "CamelCase" to "camel_case"
			$underscored_name = ltrim(strtolower(preg_replace('/[A-Z]/', '_$0', $name)), '_');
			
			$splitted_name = explode('_', $underscored_name);
			$http_method = strtoupper(array_shift($splitted_name));
			$api_method = implode('_', $splitted_name);
			
			$this->Call($http_method, $api_method, isset($args[0]) ? $args[0] : array());
			
			if($this->httpStatus < 200 || $this->httpStatus > 299) {
				if($this->httpBody && isset($this->httpBody->developer_message)) {
					throw new UrbException($this->httpBody->developer_message, $this->httpBody);
				}
				else {
					throw new UrbException('HTTP ' . $this->httpStatus);
				}
			}
			
			return $this->httpBody;
		}
		
		
		/*public function GetProducts($attr = array()) {
			$this->Call('GET', 'products', $attr);
			
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
		
		
		public function GetStockLevels($attr = array()) {
			$this->Call('GET', 'stock_levels', $attr);
			
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
		
		
		public function GetPickupLocations() {
			$this->Call('GET', 'pickup_locations');
			
			if($this->httpStatus !== 200) {
				if($this->httpBody && isset($this->httpBody->developer_message)) {
					throw new UrbException($this->httpBody->developer_message, $this->httpBody);
				}
				else {
					throw new UrbException('HTTP ' . $this->httpStatus);
				}
			}
			
			return $this->httpBody;
		}*/
	}
?>