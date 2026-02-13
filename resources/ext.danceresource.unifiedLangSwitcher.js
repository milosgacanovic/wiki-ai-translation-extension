( function () {
	'use strict';

	var config = mw.config.get( 'drUls' );
	if ( !config || !config.enabled ) {
		return;
	}

var debugUls = ( /\bdebuguls=1\b/ ).test( window.location.search );

var api = new mw.Api();

	function getContainer() {
		if ( config.position === 'header' ) {
			var $title = $( '#firstHeading' );
			if ( $title.length ) {
				var $headingWrap = $title.parent();
				var $tools = $headingWrap.find( '> .dr-titlebar-tools' );
				if ( !$tools.length ) {
					$tools = $( '<div>' ).addClass( 'dr-titlebar-tools' );
					$title.after( $tools );
				}

				var $container = $tools.find( '> .dr-uls-container[data-dr-uls-position="header"]' );
				if ( !$container.length ) {
					$container = $( '<div>' )
						.addClass( 'dr-uls-container' )
						.attr( 'data-dr-uls-position', 'header' );
					$tools.append( $container );
				}
				return $container;
			}
		}

		var $container = $( '.dr-uls-container' ).first();
		if ( $container.length ) {
			return $container;
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

	function animatePortletIn( $portlet ) {
		if ( !$portlet || !$portlet.length ) {
			return;
		}
		var el = $portlet[ 0 ];
		el.style.opacity = '0';
		el.style.transform = 'translateY(-8px)';
		el.style.transition = 'opacity 1000ms ease-out, transform 1000ms ease-out';
		el.style.willChange = 'opacity, transform';

		window.setTimeout( function () {
			el.style.opacity = '1';
			el.style.transform = 'translateY(0)';
			window.setTimeout( function () {
				el.style.willChange = '';
			}, 1100 );
		}, 50 );
	}

	function animateContainerIn( $container ) {
		if ( !$container || !$container.length ) {
			return;
		}
		var el = $container[ 0 ];
		if ( el.animate ) {
			el.animate(
				[
					{ opacity: 0 },
					{ opacity: 1 }
				],
				{
					duration: 1000,
					easing: 'ease-out',
					fill: 'both'
				}
			);
		}
	}

	function getAutonym( code ) {
		if ( window.$ && $.uls && $.uls.data && $.uls.data.getAutonym ) {
			return $.uls.data.getAutonym( code );
		}
		return code;
	}

	function getCurrentContentLanguage( data ) {
		var pageName = mw.config.get( 'wgPageName' ) || '';
		// Only treat a trailing segment as language if it looks like a lang code
		// and is known for this page.
		var match = pageName.match( /\/([a-z]{2,3}(?:-[a-z0-9]{2,8})?)$/i );
		var candidate = match ? match[1].toLowerCase() : '';
		var known = {};
		known[ data.sourceLanguage ] = true;
		( data.languages || [] ).forEach( function ( item ) {
			known[ item.code ] = true;
		} );

		var current = '';
		if ( candidate && ( known[candidate] || known[ normalizeContentCode( candidate ) ] ) ) {
			current = candidate;
		}
		if ( !current ) {
			current = ( data && data.currentLanguage ) ||
				mw.config.get( 'wgPageContentLanguage' ) ||
				mw.config.get( 'wgUserLanguage' ) ||
				data.sourceLanguage;
		}

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
		if ( !languages.some( function ( item ) { return item.code === data.sourceLanguage; } ) ) {
			languages.push( {
				code: data.sourceLanguage,
				contentCode: data.sourceLanguage,
				autonym: getAutonym( data.sourceLanguage ),
				name: getAutonym( data.sourceLanguage )
			} );
		}

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
			return getLabel( a ).localeCompare( getLabel( b ) );
		} );
		var currentItem = null;
		for ( var i = 0; i < languages.length; i++ ) {
			if ( normalizeContentCode( languages[ i ].code ) === normalizeContentCode( currentContentLanguage ) ) {
				currentItem = languages[ i ];
				break;
			}
		}
		var labelText = currentItem ? getLabel( currentItem ) : mw.message( 'druls-languages' ).text();
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

		$portlet.removeClass( 'dr-open dr-ready' ).empty().append( $label ).append( $body );

		var $dot = $( '#ai-translation-status-dot' );
		if ( !$dot.length ) {
			$dot = $( '<button>' )
				.attr( 'id', 'ai-translation-status-dot' )
				.attr( 'type', 'button' )
				.attr( 'aria-hidden', 'true' )
				.attr( 'tabindex', '-1' )
				.addClass( 'ai-translation-status-dot ai-translation-status-dot-unknown ai-translation-status-dot-pending' );
		}

		$container.empty().append( $dot ).append( $portlet );
		animateContainerIn( $container );
		animatePortletIn( $portlet );

		var $variants = $( '#p-variants-desktop' );
		if ( $variants.length ) {
			$variants.addClass( 'dr-header-dropdown' );
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
