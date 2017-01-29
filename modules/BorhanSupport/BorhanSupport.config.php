<?php 
	/**
	 * BorhanSupport base configuration
	 * 
	* Do not edit this file instead use LocalSettings.php and
	* $wgMwEmbedModuleConfig[ {configuration name} ] = value; format
	*/
	global $wgBorhanUseManifestUrls;
	global $wgBorhanLicenseServerUrl;
	
	return array (
		'Borhan.ServiceUrl' => 'http://www.borhan.com',
		'Borhan.StatsServiceUrl' => 'http://www.borhan.com',
		'Borhan.ServiceBase' => '/api_v3/index.php?service=',
		'Borhan.CdnUrl' => 'http://cdnakmi.borhan.com',
		// By default tell the client to cache results
		'Borhan.NoApiCache' => false, 

		// By default support apple adaptive
		//'Borhan.UseAppleAdaptive' => true,
		
		// if Manifest urls should be used: 
		'Borhan.UseManifestUrls' => $wgBorhanUseManifestUrls,
		
		// By default we should include flavorIds urls for supporting akami HD
		'Borhan.UseFlavorIdsUrls' => true,
		
		// A video file for when no suitable flavor can be found
		'Borhan.MissingFlavorSources' => array(
			array(
				'src' => 'http://www.borhan.com/p/243342/sp/24334200/playManifest/entryId/1_g18we0u3/flavorId/1_ktavj42z/format/url/protocol/http/a.mp4',
				'type' => 'video/h264'
			),
			array(
				'src' => 'http://www.borhan.com/p/243342/sp/24334200/playManifest/entryId/1_g18we0u3/flavorId/1_gtm9gzz2/format/url/protocol/http/a.ogg',
				'type' => 'video/ogg'
			),
			array(
				'src' => 'http://www.borhan.com/p/243342/sp/24334200/playManifest/entryId/1_g18we0u3/flavorId/1_bqsosjph/format/url/protocol/http/a.webm',
				'type' => 'video/webm'
			)
		),
		// Black video sources. Useful for capturing play user gesture events on a live video tag for iPad
		// while displaying an error message or image overlay and not any 'real' video content.
		'Borhan.BlackVideoSources' => array(
			array(
				'src' => 'http://www.borhan.com/p/243342/sp/24334200/playManifest/entryId/1_vp5cng42/flavorId/1_6wf0o9n7/format/url/protocol/http/a.mp4',
				'type' => 'video/h264'
			),
			array(
				'src' => 'http://www.borhan.com/p/243342/sp/24334200/playManifest/entryId/1_vp5cng42/flavorId/1_oiyfyphl/format/url/protocol/http/a.webm',
				'type' => 'video/webm'
			),
			array(
				'src' => 'http://www.borhan.com/p/243342/sp/24334200/playManifest/entryId/1_vp5cng42/flavorId/1_6yqa4nmd/format/url/protocol/http/a.ogg',
				'type' => 'video/ogg'
			)
		),
		// Do not send KS for isLive requests
		'SkipKSOnIsLiveRequest' => true,

		'Borhan.LicenseServerURL' => $wgBorhanLicenseServerUrl
	);