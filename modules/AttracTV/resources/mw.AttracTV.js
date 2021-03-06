( function( mw, $ ) { "use strict";

var _this;

mw.AttracTV = function( embedPlayer, callback ){
	return this.init( embedPlayer, callback );
};

mw.AttracTV.prototype = {
	bindPostfix: '.AttracTV',
	
	init: function(  embedPlayer, callback){
		_this = this;
		this.embedPlayer = embedPlayer;
		this.bindPlayer();
		
		/* var bdp = document.getElementById( "myVideoTarget" );
		bdp.sendNotification( "changeMedia", { 'entryId' : "1_hjbaye7l" });
		*/
		
		mw.setConfig('Borhan.LeadWithHTML5', true);

		callback();
	},
	
	loadATV : function() {
		
		var _this = this;
		
		bWidget.appendScriptUrl("http://services.attractv.net/newtvhive/rest/asset/atvloader.js", function() {
		//bWidget.appendScriptUrl("http://www.atv.local/ATV5/Trunk/atv/js/atvloader.js", function() {
			ATV.BorhanPlayer = _this.embedPlayer;
			ATV.init(_this.getConfig("publisherKey"),"http://services.attractv.net/newtvhive/cxf",_this.getConfig("barId"),"ATVBox");
		}, document);
	},
	
	bindPlayer: function(){
		
		var _this = this;
		// checkPlayerSourcesEvent ( add peer4 mediaStream source )
		
		this.embedPlayer.bindHelper( 'playerReady', function(){
				_this.embedPlayer.getVideoHolder().append($('<div>')
				.attr('id', "ATVBox" )
				.css({
					'border' : 'none',
					'position': 'absolute',
					'z-index': 11
				}));
				
				$("#ATVBox").click(function(e){
					// Since our overlay is all over the player we need to make it play when pressed
					var domElem = document.elementFromPoint(e.pageX, e.pageY);
					if (domElem.id === "ATVBox") {
						if (_this.embedPlayer.isPlaying()) {
							_this.embedPlayer.pause();
						}
						else {
							_this.embedPlayer.play();	
						}
						
					}
				 });
				
				_this.loadATV();
			});
		
		this.embedPlayer.bindHelper( 'monitorEvent' , function() {
				var currentTime = _this.embedPlayer.currentTime;
				if (ATV && ATV.Player && ATV.Player.videoTimeEvent) {
					ATV.Player.videoTimeEvent(currentTime);   
				}
			} );
	},
	
	getConfig: function( propId ){
		// return the attribute value
		return this.embedPlayer.getBorhanConfig( 'AttracTV', propId );
	}
}

} )( window.mw, window.jQuery );