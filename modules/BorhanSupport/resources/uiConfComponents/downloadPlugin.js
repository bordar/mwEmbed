( function( mw, $ ) {"use strict";

	mw.PluginManager.add( 'download', mw.KBaseComponent.extend({

		defaultConfig: {
			align: "right",
			parent: "controlsContainer",
			displayImportance: "low",
			downloadName:"video",
			showTooltip: true,
			preferredBitrate: '',
		 	order: 53
		},
		isSafeEnviornment: function(){
			return !mw.isIOS();
		},
		setup: function(){
			var _this = this;
			this.bind( 'downloadMedia', function() {
				_this.downloadMedia();
			});
		},
		downloadMedia: function() {
			var downloadUrl = mw.getMwEmbedPath() + '/modules/BorhanSupport/download.php/wid/';
				downloadUrl += this.getPlayer().bwidgetid + '/uiconf_id/' + this.getPlayer().kuiconfid;
				downloadUrl += '/entry_id/' + this.getPlayer().kentryid + '?forceDownload=true';
				downloadUrl += '&downloadName=' + encodeURIComponent(this.getConfig('downloadName'));
				if ( this.getConfig( 'preferredBitrate' ) != '' && this.getConfig( 'preferredBitrate' ) != null ){
					downloadUrl += '&preferredBitrate=' + encodeURIComponent( this.getConfig( 'preferredBitrate' ));
				}
				downloadUrl += '&ks=' + this.getPlayer().getFlashvars('ks');
				
			window.open( downloadUrl );
		},
		getComponent: function() {
			var _this = this;
			if( !this.$el ) {
				this.$el = $( '<button />' )
							.attr( 'title', 'Download Media' )
							.addClass( "btn icon-download" + this.getCssClass() )
							.click( function() {
								_this.getPlayer().triggerHelper('downloadMedia');
							});
			}
			return this.$el;
		}
	}));

} )( window.mw, window.jQuery );