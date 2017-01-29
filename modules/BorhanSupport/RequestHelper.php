<?php

class RequestHelper {

	var $ks = null;
	var $noCache = false;
	var $debug = false;
	var $utility = null;

	/**
	 * Variables set by the Frame request:
	 */
	public $urlParameters = array(
		'cache_st' => null,
		'p' => null,
		'partner_id' => null,
		'wid' => null,
		'uiconf_id' => null,
		'entry_id' => null,
		'flashvars' => null,
		'playlist_id' => null,
		'urid' => null,
		// Custom service url properties ( only used when wgBorhanAllowIframeRemoteService is set to true ) 
		'ServiceUrl'=> null,
		'ServiceBase'=>null,
		'CdnUrl'=> null,
		'UseManifestUrls' => null,
		'ks' => null,
		'debug' => null,
		// for thumbnails
		'width' => null,
		'height'=> null,
		'playerId' => null,
		'vid_sec' => null,
		'vid_slices' => null,
		'jsonConfig' => null
	);


	function __construct( $utility ){
		if(!$utility)
			throw new Exception("Error missing utility object");

		$this->utility = $utility;
		//parse input:
		$this->parseRequest();
		// Set KS if available in URL parameter or flashvar
		$this->setKSIfExists();
	}

	// Parse the embedFrame request and sanitize input
	private function parseRequest(){
		global $wgEnableScriptDebug, $wgBorhanUseAppleAdaptive,
				$wgBorhanPartnerDisableAppleAdaptive;
		// Support /key/value path request:
		if( isset( $_SERVER['PATH_INFO'] ) ){
			$urlParts = explode( '/', $_SERVER['PATH_INFO'] );
			foreach( $urlParts as $inx => $urlPart ){
				foreach( $this->urlParameters as $attributeKey => $na){
					if( $urlPart == $attributeKey && isset( $urlParts[$inx+1] ) ){
						$_REQUEST[ $attributeKey ] = $urlParts[$inx+1];
					}
				}
			}
		}

		// Check for urlParameters in the request:
		foreach( $this->urlParameters as $attributeKey => $na){
			if( isset( $_REQUEST[ $attributeKey ] ) ){
				// set the url parameter and don't let any html in:
				if( is_array( $_REQUEST[$attributeKey] ) ){
					$payLoad = array();
					foreach( $_REQUEST[$attributeKey] as $key => $val ){
						$payLoad[$key] = htmlspecialchars( $val );
					}
					$this->urlParameters[ $attributeKey ] = $payLoad;
				} else {
					$this->urlParameters[ $attributeKey ] = htmlspecialchars( $_REQUEST[$attributeKey] );
				}
			}
		}
		// support CORS for IE9 and lower
		global $HTTP_RAW_POST_DATA;
		if (count($_POST)==0 && count($HTTP_RAW_POST_DATA)>0 && strpos($HTTP_RAW_POST_DATA,'jsonConfig')!==false){
			// remove "jsonConfig=" from raw data string
			$config = substr($HTTP_RAW_POST_DATA, 11);
			// set the unescaped jsonConfig raw data string in the URL parameters
			$this->urlParameters[ 'jsonConfig' ] = (html_entity_decode(preg_replace("/%u([0-9a-f]{3,4})/i", "&#x\\1;", urldecode($config)), null, 'UTF-8'));
		}
		// string to bollean  
		foreach( $this->urlParameters as $k=>$v){
			if( $v == 'false'){
				$this->urlParameters[$k] = false;
			}
			if( $v == 'true' ){
				$this->urlParameters[$k] = true;
			}
		}
		
		if( isset( $this->urlParameters['p'] ) && !isset( $this->urlParameters['wid'] ) ){
			$this->urlParameters['wid'] = '_' . $this->urlParameters['p'];  
		}

		if( isset( $this->urlParameters['partner_id'] ) && !isset( $this->urlParameters['wid'] ) ){
			$this->urlParameters['wid'] = '_' . $this->urlParameters['partner_id'];  
		}		
			
		// Check for debug flag
		if( isset( $_REQUEST['debug'] ) ){
			$this->debug = true;
			$wgEnableScriptDebug = true;
		}

		// Check for no cache flag
		if( isset( $_REQUEST['nocache'] ) && $_REQUEST['nocache'] == 'true' ) {
			$this->noCache = true;
		}

		// Check for required config
		if( $this->urlParameters['wid'] == null ){
			//throw new Exception( 'Can not display player, missing widget id' );
		}
	}

	function get( $name = null ) {
		if( $name && isset( $this->urlParameters[ $name ] ) ) {
			return $this->urlParameters[ $name ];
		}
		return null;
	}

	function set( $key = null, $val = null ) {
		if( $key && $val ) {
			$this->urlParameters[ $key ] = $val;
			return true;
		}
		return false;
	}

	function getServiceConfig( $name ){
		global $wgBorhanAllowIframeRemoteService;
		
		// Check if we allow URL override: 
		if( $wgBorhanAllowIframeRemoteService == true ){
			// Check for urlParameters
			if( $this->get( $name ) ){
				return $this->get( $name );
			}
		}
		
		// Else use the global config: 
		switch( $name ){
			case 'ServiceUrl' : 
				global $wgBorhanServiceUrl;
				return $wgBorhanServiceUrl;
				break;
			case 'ServiceBase':
				global $wgBorhanServiceBase;
				return $wgBorhanServiceBase;
				break;
			case 'CdnUrl':
				global $wgBorhanCDNUrl;
				return $wgBorhanCDNUrl;
				break;
			case 'UseManifestUrls':
				global $wgBorhanUseManifestUrls;
				return $wgBorhanUseManifestUrls;
				break;
		}
	}

	public function getUserAgent() {
		return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	}

	public function getReferer(){
		global $wgBorhanForceReferer;
		if( $wgBorhanForceReferer !== false ){
			return $wgBorhanForceReferer;
		}
		if( isset( $_SERVER['HTTP_REFERER'] ) ){
			$urlParts = parse_url( $_SERVER['HTTP_REFERER'] );
			return $urlParts['scheme'] . "://" . $urlParts['host'] . "/";
		}
		return 'http://www.borhan.com/';
	}

	// Check if private IP
	private function isIpPrivate($ip){
		$privateRanges = array(
			'10.0.0.0|10.255.255.255',
			'172.16.0.0|172.31.255.255',
			'192.168.0.0|192.168.255.255',
			'169.254.0.0|169.254.255.255',
			'127.0.0.0|127.255.255.255',
		);

		$longIp = ip2long($ip);
		if ($longIp && $longIp != -1)
		{
			foreach ($privateRanges as $range)
			{
				list($start, $end) = explode('|', $range);
				if ($longIp >= ip2long($start) && $longIp <= ip2long($end)) {
					return true;
				}
			}
		}

		return false;
	}

	// Get the first real IP
	private function getRealIP( $headerIPs ){
		$remote_addr = null;
		$headerIPs = trim( $headerIPs, ',' );
		$headerIPs = explode(',', $headerIPs);
		foreach( $headerIPs as $ip ) {
			// ignore any string after the ip address
			preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', trim($ip), $matches); 
			if (!isset($matches[0]))
				continue;

 			$tempAddr = trim($matches[0]);
 			if ($this->isIpPrivate($tempAddr))	// verify that ip is not from a private range
 				continue;

 			$remote_addr = $tempAddr;
 			break;
		}
		return $remote_addr;
	}

	public function getRemoteAddrHeader(){
		global $wgBorhanRemoteAddressSalt, $wgBorhanForceIP;
		if( $wgBorhanRemoteAddressSalt === false ){
			return '';
		}
		$ip = null;
		// Check for x-forward-for and x-real-ip headers 
		$requestHeaders = getallheaders(); 
		if( isset( $requestHeaders['X-Forwarded-For'] ) ){
			$ip = $this->getRealIP( $requestHeaders['X-Forwarded-For'] );
		}
		// Check for x-real-ip
		if( !$ip && isset( $requestHeaders['X-Real-IP'] ) ){
			// also trim any white space
			list( $ip ) = explode( ',', $requestHeaders['X-Real-IP'] );
		}
		if( !$ip ){
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		if( $wgBorhanForceIP ){
			$ip = $wgBorhanForceIP;
		}
		// make sure there is no white space
		$ip = trim( $ip );
		$s = $ip . "," . time() . "," . microtime( true );
		return "X_BORHAN_REMOTE_ADDR: " . $s . ',' . md5( $s . "," . $wgBorhanRemoteAddressSalt );
	}

	public function getCacheSt(){
		return ( $this->get('cache_st') ) ? $this->get('cache_st') : '';
	}
	public function getUiConfId(){
		return $this->get('uiconf_id');
	}
	public function getWidgetId() {
		return $this->get('wid');
	}
	public function getEntryId(){
		return $this->get('entry_id');
	}
	public function getReferenceId() {
		if ( $this->getFlashVars('referenceId') ) {
			return $this->getFlashVars('referenceId');
		}
		return false;
	}
	/**
	 * getFlashVars
	 * returns flashVars from the request
	 * If no key passed, return the entire flashVars array
	 */
	public function getFlashVars( $key = null, $default = null ) {
		if( $this->get('flashvars') ) {
			$flashVars = $this->get('flashvars');
			if( ! is_null( $key ) ) {
				if( isset($flashVars[$key]) ) {
					return $this->utility->formatString($flashVars[$key]);
				} else {
					return $default;
				}
			}
			return is_array($flashVars) ? $flashVars : array();
		}
		return (!is_null($key)) ? $default : array();
	}

	private function setKSIfExists() {
		$ks = null;
		if( $this->getFlashVars('ks') ) {
			$ks = $this->getFlashVars('ks');
		} else if( $this->get('ks') ) {
			$ks = $this->get('ks');
		}
		// check for empty ks
		if( $ks && trim($ks) != '' ){
			$this->ks = $ks;
		}
	}
	
	public function hasKS() {
		return isset($this->ks);
	}

	public function getKS() {
		return $this->ks;
	}
}