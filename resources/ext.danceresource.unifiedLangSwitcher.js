( function () {
	'use strict';

	var config = mw.config.get( 'drUls' );
	if ( !config || !config.enabled ) {
		return;
	}

var debugUls = ( /\bdebuguls=1\b/ ).test( window.location.search );

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
		var label = item.autonym || item.name || item.code;
		if ( !label ) {
			return label;
		}
		return label.charAt( 0 ).toUpperCase() + label.slice( 1 );
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

	function getCurrentContentLanguage( data ) {
		var pageName = mw.config.get( 'wgPageName' ) || '';
		var match = pageName.match( /\/([a-z-]+)$/i );
		var current = ( match ? match[1].toLowerCase() : '' ) ||
			( data && data.currentLanguage ) ||
			mw.config.get( 'wgPageContentLanguage' ) ||
			mw.config.get( 'wgUserLanguage' ) ||
			data.sourceLanguage;

		var variant = mw.config.get( 'wgUserVariant' );
		if ( current === 'sr' && variant && variant.indexOf( 'sr-' ) === 0 ) {
			current = variant;
		}

		return current;
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
			languages = languages.map( function ( item ) {
				if ( item.code === 'sr' ) {
					return {
						code: 'sr',
						contentCode: 'sr',
						autonym: 'Srpski',
						name: 'Srpski'
					};
				}
				return item;
			} );
		}

		var currentContentLanguage = getCurrentContentLanguage( data );
		if ( currentContentLanguage.indexOf( 'sr-' ) === 0 ) {
			currentContentLanguage = 'sr';
		}
		languages.sort( function ( a, b ) {
			var aCurrent = normalizeContentCode( a.code ) === normalizeContentCode( currentContentLanguage );
			var bCurrent = normalizeContentCode( b.code ) === normalizeContentCode( currentContentLanguage );
			if ( aCurrent && !bCurrent ) {
				return -1;
			}
			if ( bCurrent && !aCurrent ) {
				return 1;
			}
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
			var uiLang = item.code;
			if ( item.code === 'sr' ) {
				uiLang = 'sr-el';
			}
			var hrefTitle = data.title;
			if ( item.contentCode !== data.sourceLanguage ) {
				hrefTitle = data.title + '/' + item.contentCode;
			}
			var href = mw.util.getUrl( hrefTitle, { uselang: uiLang } );
			var hrefNoUi = mw.util.getUrl( hrefTitle );
			var $link = $( '<a>' )
				.attr( 'href', href )
				.attr( 'data-href-noui', hrefNoUi )
				.attr( 'data-code', item.code )
				.attr( 'data-content-code', item.contentCode )
				.text( label );

			var isCurrent = normalizeContentCode( item.code ) === normalizeContentCode( currentContentLanguage );
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

		$( document ).off( 'click.drUlsLang', '#p-language-compact .dr-uls-list a' )
			.on( 'click.drUlsLang', '#p-language-compact .dr-uls-list a', function ( event ) {
			var debug = debugUls;
			var uiCode = $( this ).data( 'code' );
			var contentCode = $( this ).data( 'content-code' ) || uiCode;
			contentCode = normalizeContentCode( contentCode );
			var hrefNoUi = $( this ).data( 'href-noui' );
			var hrefUi = $( this ).attr( 'href' );
			var uiCodeForUi = uiCode === 'sr' ? 'sr-el' : uiCode;

			if ( config.uiLanguageMode === 'user_preference_only' && !mw.user.isNamed() ) {
				uiCodeForUi = null;
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
					event.preventDefault();
					mw.notify( mw.message( 'druls-translation-not-available' ).text() );
					if ( uiCodeForUi ) {
						setInterfaceLanguage( uiCodeForUi );
					}
					return;
				}
			}
			// Persist UI language when possible, then navigate without uselang.
			if ( uiCodeForUi ) {
				event.preventDefault();
				mw.loader.using( 'ext.uls.common' ).then( function () {
					return setInterfaceLanguage( uiCodeForUi );
				} ).then( function () {
					window.location.href = hrefNoUi || hrefUi;
				} ).catch( function ( err ) {
					window.location.href = hrefUi;
				} );
				return;
			}
			// Allow normal navigation; UI language is set via uselang param.
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
		autoSyncUiLanguage( data );
	} );
}

$( loadAndRender );

function autoSetAnonUiLanguage() {
	if ( mw.user.isNamed() ) {
		return;
	}

	var pageName = mw.config.get( 'wgPageName' ) || '';
	var match = pageName.match( /\/(sr(?:-el|-ec)?)$/i );
	if ( !match ) {
		return;
	}

	var targetUi = 'sr-el';
	var currentUi = mw.config.get( 'wgUserLanguage' ) || '';
	if ( currentUi.indexOf( 'sr' ) === 0 ) {
		return;
	}

	var cookieLang = mw.cookie ? mw.cookie.get( 'language' ) : null;
	if ( cookieLang && cookieLang.indexOf( 'sr' ) === 0 ) {
		return;
	}

	mw.loader.using( [ 'ext.uls.common', 'mediawiki.cookie' ] ).then( function () {
		return setInterfaceLanguage( targetUi );
	} ).then( function () {
		window.location.reload();
	} );
}

function autoSyncUiLanguage( data ) {
	if ( !mw.user.isNamed() ) {
		autoSetAnonUiLanguage();
		return;
	}

	if ( config.uiLanguageMode === 'user_preference_only' || config.uiLanguageMode === 'uls_cookie' ) {
		var currentContent = getCurrentContentLanguage( data );
		if ( !currentContent ) {
			return;
		}

		var targetUi = currentContent.indexOf( 'sr' ) === 0 ? 'sr-el' : currentContent;
		var currentUi = mw.config.get( 'wgUserLanguage' ) || '';

		if ( currentUi === targetUi ) {
			if ( window.sessionStorage ) {
				window.sessionStorage.removeItem( 'drUlsSync' );
			}
			return;
		}

		if ( window.sessionStorage ) {
			var lastSync = window.sessionStorage.getItem( 'drUlsSync' );
			if ( lastSync === targetUi ) {
				return;
			}
			window.sessionStorage.setItem( 'drUlsSync', targetUi );
		}

		mw.loader.using( [ 'ext.uls.common', 'mediawiki.cookie' ] ).then( function () {
			return setInterfaceLanguage( targetUi );
		} ).then( function () {
			window.location.reload();
		} );
	}
}
}() );
