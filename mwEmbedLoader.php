<?php
// Include configuration
require_once( realpath( dirname( __FILE__ ) ) . '/includes/DefaultSettings.php' );
require_once( realpath( dirname( __FILE__ ) ) . '/modules/BorhanSupport/BorhanCommon.php' );

// only include the iframe if we need to: 
// Include MwEmbedWebStartSetup.php for all of mediawiki support
if( isset( $_GET['autoembed'] ) ){
	$externalPlayersPath =  realpath( dirname( __FILE__ ) ) . '/modules/ExternalPlayers/ExternalPlayers.json';
	$plugins = json_decode( file_get_contents($externalPlayersPath ), TRUE );
	require ( dirname( __FILE__ ) . '/includes/MwEmbedWebStartSetup.php' );
	require_once( realpath( dirname( __FILE__ ) ) . '/modules/BorhanSupport/borhanIframeClass.php' );
}

// Check for custom resource ps config file:
if( isset( $wgBorhanPSHtml5SettingsPath ) && is_file( $wgBorhanPSHtml5SettingsPath ) ){
	require_once( $wgBorhanPSHtml5SettingsPath );
}

$mwEmbedLoader = new mwEmbedLoader();
$mwEmbedLoader->output();

class mwEmbedLoader {

	var $uiconfObject = null; // lazy init
	var $error = false;

	var $request = null;
	var $utility = null;

	var $iframeHeaders = null;
	var $eTagHash = null;
	
	
	var $loaderFileList = array(
		// Get main bWidget resource:
		'bWidget/bWidget.js', 
		// Include json2 for old browsers that don't have JSON.stringify
		'resources/json/json2.js', 
		// By default include deprecated globals ( will be deprecated in 1.8 )
		'bWidget/bWidget.deprecatedGlobals.js', 
		// Get resource ( domReady.js )
		'bWidget/bWidget.domReady.js', 
		// Get resource (  mwEmbedLoader.js )
		'bWidget/mwEmbedLoader.js', 
		// Include checkUserAgentPlayer code
		'bWidget/bWidget.checkUserAgentPlayerRules.js',
		// Get bWidget utilities:
		'bWidget/bWidget.util.js',	
		// bWidget basic api wrapper
		'resources/crypto/MD5.js',
		'bWidget/bWidget.storage.js',
		'bWidget/bWidget.api.js'
	);

	function request() {
		if( ! $this->request ) {
			global $container;
			$this->request = $container['request_helper'];
		}
		return $this->request;
	}

	function utility() {
		if( ! $this->utility ) {
			global $container;
			$this->utility = $container['utility_helper'];
		}
		return $this->utility;
	}
	function getOutputHash( $o ){
		if( !$this->eTagHash ){
			$this->eTagHash = md5( $o );
		}
		return $this->eTagHash;
	}	
	function output(){
		global $wgEnableScriptDebug;
		// Get the comment never minfied
		$o = $this->getLoaderHeader();
		
		// Check for special incloader flag to ~not~ include the loader. 
		if( ! isset( $_GET['incloader'] ) 
				|| 
			$_GET['incloader'] != 'false'
		){
			$o.= $this->getLoaderPayload();
		}
		// Once setup is complete run any embed param calls is set
		if( isset( $_GET['autoembed'] ) ){
			$o.= $this->getAutoEmbedCode();
		}
		
		// Support Etag and 304
		if( $wgEnableScriptDebug == false && @trim($_SERVER['HTTP_IF_NONE_MATCH']) == $this->getOutputHash( $o ) ){
			header("HTTP/1.1 304 Not Modified");
			exit();
		}
		
		// send cache headers
		$this->sendHeaders( $o );
		
		// start gzip handler if possible:
		if(!ob_start("ob_gzhandler")) ob_start();
		
		// check for non-fatal errors: 
		if( $this->getError() ){
			echo "if( console ){ console.log('" . json_encode($this->getError()) . "'); }";
		}
		// output the script output
		echo $o;
	}
	private function getAutoEmbedCode(){
		$o='';
		
		// Get the bWidget call ( pass along iframe payload path )
		// Check required params: 
		$wid = $this->request()->get('wid');
		if( !$wid ){
			$this->setError( "missing wid param");
			return '';
		}
		$wid = htmlspecialchars( $wid );

		$uiconf_id = $this->request()->get('uiconf_id');
		if( !$uiconf_id ){
			$this->setError( "missing uiconf_id param");
			return '';
		}
		$uiconf_id = htmlspecialchars( $uiconf_id );

		$playerId = $this->request()->get('playerId');
		if( !$playerId ){
			$this->setError( "missing playerId param");
			return '';
		}
		
		// Check optional params
		$width = ( $this->request()->get('width') )? htmlspecialchars( $this->request()->get('width') ): 400;
		$height = ( $this->request()->get('height') )? htmlspecialchars( $this->request()->get('height') ): 330;

		// Get the iframe payload
		$kIframe = new borhanIframeClass();

		$this->iframeHeaders = $kIframe->getHeaders();
		
		// get the kIframe 
		$json = array(
			'content' => $kIframe->getIFramePageOutput()
		);
		$o.="bWidget.iframeAutoEmbedCache[ '{$playerId}' ] = " . json_encode( $json ) . ";\n";
		
		$o.="if(!document.getElementById('{$playerId}')) { document.write( '<div id=\"{$playerId}\" style=\"width:{$width}px;height:{$height}px\"></div>' ); } \n";
		$o.="bWidget.embed( '{$playerId}', { \n" .
			"\t'wid': '{$wid}', \n" .
			"\t'uiconf_id' : '{$uiconf_id}'";
		// conditionally add in the entry id: ( no entry id in playlists )
		if( $this->request()->get('entry_id') && ! $this->getUiConfObject()->isPlaylist() ){
			$o.=",\n\t'entry_id': '" . htmlspecialchars( $this->request()->get('entry_id') ) . "'";
		}
		$flashVars = $this->request()->getFlashVars();
		//$o.=",\n\t'width': {$width},\n\t'height': {$height}";
		// conditionally output flashvars:
		if( $flashVars && is_array($flashVars) ){
			$o.= ",\n\t'flashvars': {";
			$coma = '';
			foreach( $flashVars as $fvKey => $fvValue) {
				$o.= $coma;
				$coma = ',';
				// check for json flavar and set acordingly
				if( is_object( json_decode( html_entity_decode( $fvValue ) ) ) ){
					$o.= "\n\t\t'{$fvKey}':";
					$fvSet = json_decode( html_entity_decode( $fvValue ) );
					$o.= json_encode( $fvSet );
				} else {
					$o.= "\"{$fvKey}\"" . ':' . json_encode( $this->utility()->formatString( $fvValue ) );
				}
			}
			$o.='}';
		}
		$o.="\n});";

		return $o;
	} 
			
	private function getLoaderPayload(){
		$o = '';
		// get the main payload minfied if possible
		if( $this->isDebugMode() ){
			$o = $this->getCombinedLoaderJs();
			$o.= $this->getExportedConfig();
			// get any per uiConf js:
			$o.= $this->getPerUiConfJS();
		} else {
			$o.= $this->getMinCombinedLoaderJs();
			// don't compress config
			$o.= $this->getExportedConfig();
			// get any per uiConf js:
			$o.= $this->getMinPerUiConfJS();
		}
		
		// After we load everything ( issue the bWidget.Setup call as the last line in the loader )
		$o.="\nbWidget.setup();\n";
		
		return $o;
	}
	private function setError( $errorMsg ){
		$this->error = $errorMsg;
	}
	private function getError(){
		return $this->error;
	}
	private function isDebugMode(){
		global $wgEnableScriptDebug;
		return  isset( $_GET['debug'] ) || $wgEnableScriptDebug;
	}
	private function getCacheFileContents( $key ){
		global $wgScriptCacheDirectory;
		if( is_file( $this->getCacheFilePath( $key ) ) ){
			return @file_get_contents(  $this->getCacheFilePath( $key ) );
		}
		return false;
	}
	private function getCacheFilePath( $key ){
		global $wgScriptCacheDirectory;
		return $wgScriptCacheDirectory . '/' . substr( $key, 0, 2) . '/'.  substr( $key, 2 );
	}
	
	private function getMinPerUiConfJS(){
		global $wgResourceLoaderMinifierStatementsOnOwnLine;
		// mwEmbedLoader based uiConf values can be hashed by the uiconf
		$uiConfJs = $this->getPerUiConfJS();
		if( $uiConfJs == '' ){
			return '';
		}
		// Hash the javascript string to see if we we can use a cached version and avoid JavaScriptMinifier call
		$key = md5( $uiConfJs );
		$cacheJS = $this->getCacheFileContents( $key );
		if( $cacheJS !== false ){
			return $cacheJS;
		}
		//minfy js 
		require_once( realpath( dirname( __FILE__ ) ) . '/includes/libs/JavaScriptMinifier.php' );
		$minjs = JavaScriptMinifier::minify( $uiConfJs, $wgResourceLoaderMinifierStatementsOnOwnLine );
		// output minified cache: 
		$this->outputFileCache( $key, $minjs);
		return $minjs;
	}
	
	/** gets any defiend on-page uiConf js */
	private function getPerUiConfJS(){
		if( ! $this->request()->get('uiconf_id')
				||
			!$this->getUiConfObject() 
				|| 
			( ! $this->request()->get('wid') 
				&&
			  ! $this->request()->get('p') 	
			)
		){
			// directly issue the UiConfJs callback
			return 'bWidget.inLoaderUiConfJsCallback();';
		}
		// load the onPage js services
		$mweUiConfJs = new mweApiUiConfJs();
		// output is set to empty string:
		$o='';
		// always include UserAgentPlayerRules:
		$o.= $mweUiConfJs->getUserAgentPlayerRules();
		
		// support including special player rewrite flags if set in uiConf:
		if( $this->getUiConfObject()->getPlayerConfig( null, 'Borhan.LeadWithHTML5' ) === true
			||
			$this->getUiConfObject()->getPlayerConfig( null, 'BorhanSupport.LeadWithHTML5' ) === true
		){
			$o.="\n"."bWidget.addUserAgentRule('{$this->request()->get('uiconf_id')}', '/.*/', 'leadWithHTML5');";
		
		}
		if( $this->getUiConfObject()->getPlayerConfig( null, 'Borhan.ForceFlashOnIE10' ) === true ){
			$o.="\n".'mw.setConfig(\'Borhan.ForceFlashOnIE10\', true );' . "\n";
		}

		if( $this->getUiConfObject()->getPlayerConfig( null, 'Borhan.SupressNonProductionUrlsWarning' ) === true ){
			$o.="\n".'mw.setConfig(\'Borhan.SupressNonProductionUrlsWarning\', true );' . "\n";
		}

		if( $this->getUiConfObject()->isJson() ) {
			$o.="\n"."bWidget.addUserAgentRule('{$this->request()->get('uiconf_id')}', '/.*/', 'leadWithHTML5');";
		}
		
		// If we have entry data
		if( $this->request()->get('entry_id') ){
			global $container, $wgExternalPlayersSupportedTypes;
			try{
				$entryResult = $container['entry_result'];
				$entry = $entryResult->getResult();
				$metaData = @get_object_vars($entry['meta']);
				if ( isset( $metaData[ "externalSourceType" ] ) ) {
					if ( in_array( strtolower( $metaData[ "externalSourceType" ] ), array_map('strtolower', $wgExternalPlayersSupportedTypes) ) ) {
						$o.="\n".'mw.setConfig(\'forceMobileHTML5\', true );'. "\n";
					}
				}
			} catch ( Exception $e ){
				//
			}
		}
		
		// Only include on page plugins if not in iframe Server
		if( !isset( $_REQUEST['iframeServer'] ) ){
			$o.= $mweUiConfJs->getPluginPageJs( 'bWidget.inLoaderUiConfJsCallback' );
		} else{
			$o.='bWidget.inLoaderUiConfJsCallback();';
		}
		// set the flag so that we don't have to request the services.php
		$o.= "\n" . 'bWidget.uiConfScriptLoadList[\'' . 
			$this->request()->get('uiconf_id') .
			'\'] = 1; ';
		return $o;
	}
	/**
	* The result object grabber, caches a local result object for easy access
	* to result object properties.
	*/
	function getUiConfObject(){
		global $container;
		if( ! $this->uiconfObject ){
			try {
				// Init a new result object with the client tag:
				$this->uiconfObject = $container['uiconf_result'];
			} catch ( Exception $e ){
				$this->setError( $e->getMessage() );
				// don't throw any exception just return false;
				// any uiConf level exception should not block normal loader response
				// the error details will be displayed in the player
				return false;
			}
		}
		return $this->uiconfObject;
	}
	
	private function getMinCombinedLoaderJs(){
		global $wgHTTPProtocol, $wgMwEmbedVersion, $wgResourceLoaderMinifierStatementsOnOwnLine;
		$key = '/loader_' . $wgHTTPProtocol . '.min.' . $wgMwEmbedVersion . '.js' ;
		$cacheJS = $this->getCacheFileContents( $key );
		if( $cacheJS !== false ){
			return $cacheJS;
		}
		// Else get from files: 
		$rawScript = $this->getCombinedLoaderJs();
		// Get the JSmin class:
		require_once( realpath( dirname( __FILE__ ) ) . '/includes/libs/JavaScriptMinifier.php' );
		$minjs = JavaScriptMinifier::minify( $rawScript, $wgResourceLoaderMinifierStatementsOnOwnLine );
		// output the file to the cache:
		$this->outputFileCache( $key, $minjs);
		// return the minified js:
		return $minjs;
	}
	function outputFileCache( $key, $js ){
		$path = $this->getCacheFilePath( $key );
		$pathAry = explode( "/", $path );
		$pathDir = implode( '/', array_slice( $pathAry, 0, count( $pathAry ) - 1 ) );
		// Create cache directory if not exists
		if( ! is_dir( $pathDir ) ) {
			$created = @mkdir( $pathDir, 0777, true );
			if( ! $created ) {
				$this->setError( 'Error in creating cache directory' );
				return ;
			}
		}
		// make sure the path exists
		if( !@file_put_contents( $path, $js ) ){
			$this->setError( 'Error outputting file to cache');
		}
	}
	private function getCombinedLoaderJs(){
		global $wgResourceLoaderUrl, $wgMwEmbedVersion;
		$loaderJs = '';
		
		// Output all the files
		foreach( $this->loaderFileList as $file ){
			$loaderJs .= file_get_contents( $file );
		}
		
		return $loaderJs;
	}
	private function getExportedConfig(){
		global $wgEnableScriptDebug, $wgResourceLoaderUrl, $wgMwEmbedVersion, $wgMwEmbedProxyUrl, $wgBorhanUseManifestUrls,
			$wgBorhanUseManifestUrls, $wgHTTPProtocol, $wgBorhanServiceUrl, $wgBorhanServiceBase,
			$wgBorhanCDNUrl, $wgBorhanStatsServiceUrl,$wgBorhanLiveStatsServiceUrl, $wgBorhanAnalyticsServiceUrl, $wgBorhanIframeRewrite, $wgEnableIpadHTMLControls,
			$wgBorhanAllowIframeRemoteService, $wgBorhanUseAppleAdaptive, $wgBorhanEnableEmbedUiConfJs,
			$wgBorhanGoogleAnalyticsUA, $wgHTML5PsWebPath, $wgAllowedVars, $wgAllowedPluginVars, $wgAllowedPluginVarsValPartials, $wgAllowedVarsKeyPartials,
			$wgCacheTTL, $wgMaxCacheEntries, $wgBorhanSupressNonProductionUrlsWarning;
		$exportedJS ='';
		// Set up globals to be exported as mwEmbed config:
		$exportedJsConfig= array(
			'debug' => $wgEnableScriptDebug,
			//  export the http url for the loader
			'Mw.XmlProxyUrl' => $wgMwEmbedProxyUrl,
			'Borhan.UseManifestUrls' => $wgBorhanUseManifestUrls,
			'Borhan.Protocol'	=>	$wgHTTPProtocol,
			'Borhan.ServiceUrl' => $wgBorhanServiceUrl,
			'Borhan.ServiceBase' => $wgBorhanServiceBase,
			'Borhan.CdnUrl' => $wgBorhanCDNUrl,
			'Borhan.StatsServiceUrl' => $wgBorhanStatsServiceUrl,
			'Borhan.LiveStatsServiceUrl'=>$wgBorhanLiveStatsServiceUrl,
			'Borhan.AnalyticsUrl'=>$wgBorhanAnalyticsServiceUrl,
			'Borhan.IframeRewrite' => $wgBorhanIframeRewrite,
			'EmbedPlayer.EnableIpadHTMLControls' => $wgEnableIpadHTMLControls,
			'EmbedPlayer.UseFlashOnAndroid' => true,
			'Borhan.LoadScriptForVideoTags' => true,
			'Borhan.AllowIframeRemoteService' => $wgBorhanAllowIframeRemoteService,
			'Borhan.UseAppleAdaptive' => $wgBorhanUseAppleAdaptive,
			'Borhan.EnableEmbedUiConfJs' => $wgBorhanEnableEmbedUiConfJs,
			'Borhan.PageGoogleAnalytics' => $wgBorhanGoogleAnalyticsUA,
			'Borhan.SupressNonProductionUrlsWarning' => $wgBorhanSupressNonProductionUrlsWarning,
			'Borhan.APITimeout' => 10000,
			'Borhan.bWidgetPsUrl' => $wgHTML5PsWebPath,
			'Borhan.CacheTTL' => $wgCacheTTL,
			'Borhan.MaxCacheEntries' => $wgMaxCacheEntries,
			'Borhan.AllowedVars' => $wgAllowedVars,
			'Borhan.AllowedVarsKeyPartials' => $wgAllowedVarsKeyPartials,
			'Borhan.AllowedPluginVars' => $wgAllowedPluginVars,
			'Borhan.AllowedPluginVarsValPartials' => $wgAllowedPluginVarsValPartials
		);
		if( isset( $_GET['psbwidgetpath'] ) ){
			$exportedJsConfig[ 'Borhan.BWidgetPsPath' ] = htmlspecialchars( $_GET['psbwidgetpath'] );
		}
		//For embed services pass "AllowIframeRemoteService" to client so it will be able to pass back the alternative service URL
		if ($this->request()->isEmbedServicesEnabled()){
		    $exportedJsConfig['Borhan.AllowIframeRemoteService'] = true;
		}
		
		// Append Custom config:
		foreach( $exportedJsConfig as $key => $val ){
			// @@TODO use a library Boolean conversion routine:
			$val = ( $val === true )? $val = 'true' : $val;
			$val = ( $val === false )? $val = 'false' : $val;
			$val = ( $val != 'true' && $val != 'false' )? "'" . addslashes( $val ) . "'": $val;
			$exportedJS .= "mw.setConfig('". addslashes( $key ). "', $val );\n";
		}
		// Add user language
		$language = json_encode($this->utility()->getUserLanguage());
		$exportedJS .= "mw.setConfig('Borhan.UserLanguage', $language );\n";

		return $exportedJS;
	}
	// Borhan Comment
	private function getLoaderHeader(){
		global $wgMwEmbedVersion, $wgResourceLoaderUrl, $wgMwEmbedVersion;
		$o = "/**
* Borhan HTML5 Library v$wgMwEmbedVersion  
* http://html5video.org/borhan-player/docs/
* 
* This is free software released under the GPL2 see README more info 
* http://html5video.org/borhan-player/docs/readme
* 
* Copyright " . date("Y") . " Borhan Inc.
*/\n";
		// Add the library version:
		$o .= "window['MWEMBED_VERSION'] = '$wgMwEmbedVersion';\n";

		// Append ResourceLoder path to loader.js
		$o.= "window['SCRIPT_LOADER_URL'] = '". addslashes( $wgResourceLoaderUrl ) . "';\n";

		return $o;
	}
	/** send the cdn headers */
	private function sendHeaders( $o ){
		global $wgEnableScriptDebug;
		
		header("Etag: " . $this->getOutputHash( $o ) );
		header("Content-Type: text/javascript");
		if( isset( $_GET['debug'] ) || $wgEnableScriptDebug ){
			header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
			header("Pragma: no-cache");
			header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
			header("Access-Control-Allow-Origin: *"); // allow 3rd party domains to access. 
		} else if ( isset($_GET['autoembed']) && $this->iframeHeaders ){
			// Grab iframe headers and pass them to our loader
			foreach( $this->iframeHeaders as $header ) {
				// Don't include iframe Etag 
				// ( use mwEmbedLoader Etag that includes loader in the overall hash )
				if( strpos($header, 'Etag:') !== -1 ){
					header( $header );
				}
			}
		} else {
			
			// Default expire time for the loader to 10 min ( we support 304 not modified so no need for long expire )
			$max_age = 60*10;
			// if the loader request includes uiConf set age to 10 min ( uiConf updates should propgate in ~10 min )
			if( $this->request()->get('uiconf_id') ){
				$max_age = 60*10;
			}
			// Check for an error ( only cache for 60 seconds )
			if( $this->getError() ){
				$max_age = 60; 
			}
			header("Cache-Control: public, max-age=$max_age max-stale=0");
			header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $max_age) . ' GMT');
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
		}
	}
}
