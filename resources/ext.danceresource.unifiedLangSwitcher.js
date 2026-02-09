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
			if ( config.position === 'header' ) {
				var $headerLinks = $( '#mw-page-header-links' );
				if ( $headerLinks.length && !$container.closest( '#mw-page-header-links' ).length ) {
					$headerLinks.append( $container );
					$container.attr( 'data-dr-uls-position', 'header' );
				}
			}
			return $container;
		}

		if ( config.position === 'header' ) {
			var $headerLinks = $( '#mw-page-header-links' );
			if ( $headerLinks.length ) {
				$container = $( '<div>' )
					.addClass( 'dr-uls-container' )
					.attr( 'data-dr-uls-position', 'header' );
				$headerLinks.append( $container );
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
		if ( window.matchMedia && window.matchMedia( '(max-width: 720px)' ).matches ) {
			return;
		}

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

		var contentLanguageCount = Object.keys( available ).length;
		var labelText = contentLanguageCount + ' ' + mw.message( 'druls-languages' ).text();
		var $label = $( '<h3>' )
			.attr( 'id', 'p-language-compact-label' )
			.append( $( '<span>' ).addClass( 'dr-lang-count' ).text( labelText ) )
			.append( $( '<span>' ).addClass( 'dr-lang-caret' ) );

		var $list = $( '<ul>' )
			.addClass( 'dr-uls-list' )
			.attr( 'lang', mw.config.get( 'wgUserLanguage' ) || 'en' )
			.attr( 'dir', mw.config.get( 'wgUserLanguageDir' ) || 'ltr' );

		languages.forEach( function ( item ) {
			var label = getLabel( item );
			var $link = $( '<a>' )
				.attr( 'href', '#' )
				.attr( 'data-code', item.code )
				.attr( 'data-content-code', item.contentCode )
				.text( label );

			var isCurrent = normalizeContentCode( item.code ) === normalizeContentCode( data.currentLanguage );
			var $li = $( '<li>' )
				.addClass( 'mw-list-item' );

			if ( isCurrent ) {
				$li.addClass( 'selected' );
				$link.addClass( 'dr-uls-current' );
			}

			$li.append( $link );
			$list.append( $li );
		} );

		var $body = $( '<div>' )
			.addClass( 'mw-portlet-body dr-lang-dropdown' )
			.append( $list );

		var $portlet = $( '#p-language-compact' );
		if ( !$portlet.length ) {
			$portlet = $( '<div>' )
				.attr( 'role', 'navigation' )
				.addClass( 'mw-portlet dr-uls-portlet dr-header-dropdown dr-lang-portlet' )
				.attr( 'id', 'p-language-compact' )
				.attr( 'aria-labelledby', 'p-language-compact-label' );
		}

		$portlet.removeClass( 'dr-open' ).empty().append( $label ).append( $body );

		$container.empty().append( $portlet );

		var $variants = $( '#p-variants-desktop' );
		if ( $variants.length ) {
			$variants.addClass( 'dr-header-dropdown' );
		}

		if ( config.position === 'header' ) {
			var $title = $( '#firstHeading' );
			if ( $title.length ) {
				var $headingWrap = $title.parent();
				if ( $headingWrap.length ) {
					// Remove any old misplaced containers
					$( '#mw-page-header-links .dr-lang-tools' ).remove();

					var $tools = $headingWrap.find( '> .dr-titlebar-tools' );
					if ( !$tools.length ) {
						$tools = $( '<div>' ).addClass( 'dr-titlebar-tools' );
						$title.after( $tools );
					}

					if ( $headingWrap.attr( 'id' ) !== 'content' ) {
						$headingWrap.addClass( 'dr-titlebar' );
					}

					if ( !$portlet.closest( '.dr-titlebar-tools' ).length ) {
						$tools.append( $portlet );
					}

					// Clean up any stray tool containers outside the heading wrapper
					$( '.dr-titlebar-tools' ).not( $tools ).remove();
				}
			}
		}

		$( document ).off( 'click.drLangCompact' ).on( 'click.drLangCompact', function () {
			$portlet.removeClass( 'dr-open' );
		} );

		$label.off( 'click.drLangCompact' ).on( 'click.drLangCompact', function ( event ) {
			event.preventDefault();
			event.stopPropagation();
			$portlet.toggleClass( 'dr-open' );
		} );

		$container.on( 'click', '.dr-uls-list a', function ( event ) {
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

	function loadAndRender() {
		api.get( {
			action: 'danceresource-languagestatus',
			title: config.baseTitle
		} ).then( function ( res ) {
			var data = res[ 'danceresource-languagestatus' ];
			var eligible = data && ( data.isEligible === 1 || data.isEligible === '1' || data.isEligible === true );
			if ( !eligible ) {
				return;
			}

			render( data );
		} );
	}

	$( loadAndRender );
}() );
