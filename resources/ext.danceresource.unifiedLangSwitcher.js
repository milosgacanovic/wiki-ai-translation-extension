( function () {
	'use strict';

	var config = mw.config.get( 'drUls' );
	if ( !config || !config.enabled ) {
		return;
	}

	var api = new mw.Api();

	function getContainer() {
		var $container = $( '.dr-uls-container' ).first();
		if ( $container.length ) {
			return $container;
		}

		if ( config.position === 'header' ) {
			var $heading = $( '#firstHeading' );
			if ( $heading.length ) {
				$container = $( '<div>' )
					.addClass( 'dr-uls-container' )
					.attr( 'data-dr-uls-position', 'header' );
				$heading.after( $container );
				return $container;
			}
		}

		if ( config.position === 'personal' ) {
			var $personal = $( '#p-personal' );
			if ( $personal.length ) {
				$container = $( '<div>' )
					.addClass( 'dr-uls-container' )
					.attr( 'data-dr-uls-position', 'personal' );
				$personal.append( $container );
				return $container;
			}
		}

		return $();
	}

	function getLabel( item ) {
		return item.autonym || item.name || item.code;
	}

	function normalizeContentCode( code ) {
		if ( code.indexOf( 'sr-' ) === 0 ) {
			return 'sr';
		}
		return code;
	}

	function getAutonym( code ) {
		if ( window.$ && $.uls && $.uls.data && $.uls.data.getAutonym ) {
			return $.uls.data.getAutonym( code );
		}
		return code;
	}

	function render( data ) {
		var $container = getContainer();
		if ( !$container.length ) {
			return;
		}

		var available = {};
		var languages = ( data.languages || [] ).map( function ( item ) {
			available[ item.code ] = true;
			return {
				code: item.code,
				contentCode: item.code,
				autonym: item.autonym,
				name: item.name
			};
		} );

		available[ data.sourceLanguage ] = true;

		if ( available.sr ) {
			[ 'sr-ec', 'sr-el' ].forEach( function ( variant ) {
				languages.push( {
					code: variant,
					contentCode: 'sr',
					autonym: getAutonym( variant ),
					name: getAutonym( variant )
				} );
			} );
		}

		languages.sort( function ( a, b ) {
			return getLabel( a ).localeCompare( getLabel( b ) );
		} );

		var currentLabel = getLabel( {
			code: data.currentLanguage,
			autonym: getAutonym( data.currentLanguage ),
			name: data.currentLanguage
		} );

		var $details = $( '<details>' ).addClass( 'dr-uls-details' );
		var $summary = $( '<summary>' )
			.addClass( 'dr-uls-summary' )
			.text( mw.message( 'druls-languages' ).text() + ': ' + currentLabel );

		var $search = $( '<input>' )
			.addClass( 'dr-uls-search' )
			.attr( 'type', 'search' )
			.attr( 'placeholder', mw.message( 'druls-search-languages' ).text() );

		var $list = $( '<ul>' ).addClass( 'dr-uls-list' );

		languages.forEach( function ( item ) {
			var label = getLabel( item );
			var $link = $( '<a>' )
				.attr( 'href', '#' )
				.attr( 'data-code', item.code )
				.attr( 'data-content-code', item.contentCode )
				.text( label );

			if ( normalizeContentCode( item.code ) === normalizeContentCode( data.currentLanguage ) ) {
				$link.addClass( 'dr-uls-current' );
			}

			$list.append( $( '<li>' ).append( $link ) );
		} );

		$details
			.append( $summary )
			.append( $search )
			.append( $list );

		$container.empty().append( $details );

		$search.on( 'input', function () {
			var query = $search.val().toLowerCase();
			$list.find( 'li' ).each( function () {
				var $li = $( this );
				var text = $li.text().toLowerCase();
				$li.toggle( text.indexOf( query ) !== -1 );
			} );
		} );

		$list.on( 'click', 'a', function ( event ) {
			event.preventDefault();

			var uiCode = $( this ).data( 'code' );
			var contentCode = $( this ).data( 'content-code' ) || uiCode;
			contentCode = normalizeContentCode( contentCode );

			if ( config.uiLanguageMode === 'user_preference_only' && !mw.user.isNamed() ) {
				uiCode = null;
			}

			var targetTitle = data.title;
			var targetPageName = config.baseTitleDbKey;
			if ( contentCode !== data.sourceLanguage ) {
				targetTitle = data.title + '/' + contentCode;
				targetPageName = config.baseTitleDbKey + '/' + contentCode;
			}

			var currentPage = mw.config.get( 'wgPageName' );
			var isSamePage = currentPage === targetPageName;

			if ( !available[ contentCode ] && contentCode !== data.sourceLanguage ) {
				if ( config.fallbackBehavior === 'stay_and_notify' ) {
					mw.notify( mw.message( 'druls-translation-not-available' ).text() );
					setInterfaceLanguage( uiCode );
					return;
				}
			}

			if ( isSamePage ) {
				setInterfaceLanguage( uiCode );
				return;
			}

			setInterfaceLanguage( uiCode ).then( function () {
				window.location.href = mw.util.getUrl( targetTitle );
			} );
		} );
	}

	function setInterfaceLanguage( uiCode ) {
		if ( !uiCode ) {
			return $.Deferred().resolve();
		}

		if ( mw.uls && mw.uls.setLanguage ) {
			return mw.uls.setLanguage( uiCode );
		}

		return $.Deferred().resolve();
	}

	api.get( {
		action: 'danceresource-languagestatus',
		title: config.baseTitle
	} ).then( function ( res ) {
		var data = res[ 'danceresource-languagestatus' ];
		if ( !data || !data.isEligible ) {
			return;
		}

		render( data );
	} );
}() );
