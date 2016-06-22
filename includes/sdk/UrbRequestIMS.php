<?php
	class UrbRequestIMS extends UrbRequest {
		const IMS_PROD_BASE_URL = 'http://ims-api.urb-it.com/v1/';
		const IMS_STAGE_BASE_URL = 'http://stage-ims-api.urb-it.com/';
		
		
		public function __construct($storeKey = '', $sharedSecret = '', $stage = false) {
			parent::__construct($storeKey, $sharedSecret, $stage);
			
			$this->baseUrl = ($this->stage ? self::IMS_STAGE_BASE_URL : self::IMS_PROD_BASE_URL);
		}
		
		
		public function GetProducts($attr = array()) {
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
		}
	}
?>