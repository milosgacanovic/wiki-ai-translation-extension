/* Shared site-level JS
   Keep breadcrumb localization, without any header/tab DOM reparenting. */
( function () {
	'use strict';

	// Supported languages for the search language selector.
	var DR_LANGUAGES = [
		{ code: 'en', name: 'English' },
		{ code: 'ar', name: 'العربية', uselang: 'ar' },
		{ code: 'cs', name: 'Čeština', uselang: 'cs' },
		{ code: 'da', name: 'Dansk', uselang: 'da' },
		{ code: 'de', name: 'Deutsch', uselang: 'de' },
		{ code: 'el', name: 'Ελληνικά', uselang: 'el' },
		{ code: 'es', name: 'Español', uselang: 'es' },
		{ code: 'fi', name: 'Suomi', uselang: 'fi' },
		{ code: 'fr', name: 'Français', uselang: 'fr' },
		{ code: 'he', name: 'עברית', uselang: 'he' },
		{ code: 'hi', name: 'हिन्दी', uselang: 'hi' },
		{ code: 'hr', name: 'Hrvatski', uselang: 'hr' },
		{ code: 'hu', name: 'Magyar', uselang: 'hu' },
		{ code: 'id', name: 'Bahasa Indonesia', uselang: 'id' },
		{ code: 'is', name: 'Íslenska', uselang: 'is' },
		{ code: 'it', name: 'Italiano', uselang: 'it' },
		{ code: 'ja', name: '日本語', uselang: 'ja' },
		{ code: 'ka', name: 'ქართული', uselang: 'ka' },
		{ code: 'ko', name: '한국어', uselang: 'ko' },
		{ code: 'nb', name: 'Norsk bokmål', uselang: 'nb' },
		{ code: 'nl', name: 'Nederlands', uselang: 'nl' },
		{ code: 'pl', name: 'Polski', uselang: 'pl' },
		{ code: 'pt', name: 'Português', uselang: 'pt' },
		{ code: 'ro', name: 'Română', uselang: 'ro' },
		{ code: 'ru', name: 'Русский', uselang: 'ru' },
		{ code: 'sk', name: 'Slovenčina', uselang: 'sk' },
		{ code: 'sl', name: 'Slovenščina', uselang: 'sl' },
		{ code: 'sr', name: 'Srpski', uselang: 'sr-el' },
		{ code: 'sv', name: 'Svenska', uselang: 'sv' },
		{ code: 'th', name: 'ไทย', uselang: 'th' },
		{ code: 'tr', name: 'Türkçe', uselang: 'tr' },
		{ code: 'uk', name: 'Українська', uselang: 'uk' },
		{ code: 'vi', name: 'Tiếng Việt', uselang: 'vi' },
		{ code: 'zh', name: '中文', uselang: 'zh' },
		{ code: 'zu', name: 'isiZulu', uselang: 'zu' }
	];

	// Use custom drsearch API for autocomplete — returns display titles
	// (translated page names) so users see results in their language.
	// We pass page titles to response() so URLs work, then a MutationObserver
	// swaps the visible text to display titles when the DOM updates.
	// .then() returns opensearch-format data for OOUI SearchInputWidget.
	var drDisplayTitleMap = {};

	if ( mw.searchSuggest ) {
		mw.searchSuggest.request = function ( api, query, response, limit ) {
			var lang = mw.config.get( 'wgUserLanguage' ) || 'en';
			return api.get( {
				formatversion: 2,
				action: 'drsearch',
				search: query,
				limit: limit,
				uselang: lang
			} ).done( function ( data ) {
				var results = ( data && data.drsearch ) || [];
				drDisplayTitleMap = {};
				results.forEach( function ( r ) {
					if ( r.displaytitle && r.displaytitle !== r.title ) {
						drDisplayTitleMap[ r.title ] = r.displaytitle;
					}
				} );
				var titles = results.map( function ( r ) {
					return r.title;
				} );
				var urls = results.map( function ( r ) {
					return r.url || '';
				} );
				response( titles, { query: query } );
				// Mutate response object with opensearch-format numeric keys
				// so OOUI SearchInputWidget.getOptionsFromData() can read
				// data.data[1] (titles), data.data[2] (descriptions), data.data[3] (urls).
				data[ 0 ] = query;
				data[ 1 ] = titles;
				data[ 2 ] = [];
				data[ 3 ] = urls;
			} );
		};

		// Observe the suggestions container for new result nodes and swap
		// their text to display titles. Works for both jquery.suggestions
		// (.suggestions-result) and OOUI SearchInputWidget menu items.
		( function () {
			var observer = new MutationObserver( function () {
				if ( !Object.keys( drDisplayTitleMap ).length ) {
					return;
				}
				// jquery.suggestions dropdown items
				document.querySelectorAll( '.suggestions-result' ).forEach( function ( el ) {
					if ( el.getAttribute( 'data-dr-swapped' ) ) {
						return;
					}
					var textEl = el;
					var link = el.closest( 'a.mw-searchSuggest-link' );
					if ( link ) {
						textEl = link.querySelector( 'div' ) || link;
					}
					var raw = ( textEl.textContent || '' ).trim();
					if ( drDisplayTitleMap[ raw ] ) {
						textEl.textContent = drDisplayTitleMap[ raw ];
						el.setAttribute( 'data-dr-swapped', '1' );
					}
				} );
				// OOUI SearchInputWidget menu items
				document.querySelectorAll( '.mw-widget-searchWidget-menu .mw-widget-titleOptionWidget' ).forEach( function ( el ) {
					if ( el.getAttribute( 'data-dr-swapped' ) ) {
						return;
					}
					var labelEl = el.querySelector( '.oo-ui-labelElement-label' );
					if ( !labelEl ) {
						return;
					}
					var raw = ( labelEl.textContent || '' ).trim();
					if ( drDisplayTitleMap[ raw ] ) {
						labelEl.textContent = drDisplayTitleMap[ raw ];
						el.setAttribute( 'data-dr-swapped', '1' );
					}
				} );
			} );
			$( function () {
				observer.observe( document.body, { childList: true, subtree: true } );
			} );
		} () );
	}

	// Ensure search forms carry the current UI language so Special:Search
	// respects the language chosen via ULS, not just the saved MW preference.
	( function () {
		var lang = mw.config.get( 'wgUserLanguage' ) || 'en';
		if ( lang === mw.config.get( 'wgContentLanguage' ) ) {
			return;
		}
		$( function () {
			document.querySelectorAll( 'form' ).forEach( function ( form ) {
				if ( form.querySelector( 'input[name="uselang"]' ) ) {
					return;
				}
				if ( !form.querySelector( 'input[name="search"]' ) ) {
					return;
				}
				var hidden = document.createElement( 'input' );
				hidden.type = 'hidden';
				hidden.name = 'uselang';
				hidden.value = lang;
				form.appendChild( hidden );
			} );
		} );
	} () );

	// Header language selector — compact dropdown next to the search box.
	function normalizeLangCode( code ) {
		if ( !code ) {
			return 'en';
		}
		code = code.toLowerCase();
		if ( code === 'sr-el' || code === 'sr-ec' ) {
			return 'sr';
		}
		return code.split( '-' )[ 0 ];
	}

	function buildSearchLangSelector() {
		var currentLang = normalizeLangCode( mw.config.get( 'wgUserLanguage' ) );
		var currentItem = null;
		DR_LANGUAGES.forEach( function ( item ) {
			if ( item.code === currentLang ) {
				currentItem = item;
			}
		} );
		if ( !currentItem ) {
			currentItem = DR_LANGUAGES[ 0 ];
		}

		var label = document.createElement( 'button' );
		label.type = 'button';
		label.className = 'dr-search-lang-label';
		label.setAttribute( 'aria-expanded', 'false' );
		var nameSpan = document.createElement( 'span' );
		nameSpan.className = 'dr-search-lang-name';
		nameSpan.textContent = currentItem.name;
		var caret = document.createElement( 'span' );
		caret.className = 'dr-lang-caret';
		label.appendChild( nameSpan );
		label.appendChild( caret );

		var list = document.createElement( 'ul' );
		list.className = 'dr-uls-list dr-search-lang-list';

		DR_LANGUAGES.forEach( function ( item ) {
			var isCurrent = item.code === currentLang;
			var link = document.createElement( 'a' );
			link.href = '#';
			link.setAttribute( 'data-lang', item.code );
			link.setAttribute( 'data-uselang', item.uselang || item.code );
			link.textContent = item.name;
			if ( isCurrent ) {
				link.className = 'dr-uls-current';
			}
			link.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var uselang = this.getAttribute( 'data-uselang' );
				var url = new URL( window.location.href );
				url.searchParams.set( 'uselang', uselang );
				window.location.href = url.toString();
			} );
			var li = document.createElement( 'li' );
			li.className = 'mw-list-item';
			if ( isCurrent ) {
				li.className += ' selected';
			}
			li.appendChild( link );
			list.appendChild( li );
		} );

		var dropdown = document.createElement( 'div' );
		dropdown.className = 'mw-portlet-body dr-lang-dropdown dr-search-lang-dropdown';
		dropdown.appendChild( list );

		var portlet = document.createElement( 'div' );
		portlet.className = 'dr-search-lang-portlet';
		portlet.appendChild( label );
		portlet.appendChild( dropdown );

		// Toggle open/close
		label.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			var isOpen = portlet.classList.contains( 'dr-open' );
			portlet.classList.toggle( 'dr-open', !isOpen );
			label.setAttribute( 'aria-expanded', String( !isOpen ) );
		} );

		// Close on outside click
		document.addEventListener( 'click', function () {
			portlet.classList.remove( 'dr-open' );
			label.setAttribute( 'aria-expanded', 'false' );
		} );

		return portlet;
	}

	// Only inject the language selector on Special:Search, next to the search button.
	// Also rearrange: move .results-info under .mw-search-profile-tabs,
	// and hide .mw-search-results-info if .mw-search-createlink is empty.
	$( function () {
		if ( mw.config.get( 'wgCanonicalSpecialPageName' ) !== 'Search' ) {
			return;
		}

		// 1. Insert language selector right of the search button (inside #mw-search-top-table)
		var topTable = document.getElementById( 'mw-search-top-table' );
		if ( topTable ) {
			var selector = buildSearchLangSelector();
			var resultsInfo = topTable.querySelector( '.results-info' );
			if ( resultsInfo ) {
				// Place the language selector where .results-info was
				topTable.insertBefore( selector, resultsInfo );
			} else {
				topTable.appendChild( selector );
			}
		}

		// 2. Move .results-info under .mw-search-profile-tabs
		var profileTabs = document.querySelector( '.mw-search-profile-tabs' );
		var resultsInfo = document.querySelector( '.results-info' );
		if ( profileTabs && resultsInfo ) {
			profileTabs.parentNode.insertBefore( resultsInfo, profileTabs.nextSibling );
		}

		// 3. Hide .mw-search-results-info if .mw-search-createlink is empty
		var searchResultsInfo = document.querySelector( '.mw-search-results-info' );
		if ( searchResultsInfo ) {
			var createLink = searchResultsInfo.querySelector( '.mw-search-createlink' );
			if ( createLink && createLink.textContent.trim() === '' ) {
				searchResultsInfo.style.display = 'none';
			}
		}
	} );

	mw.hook( 'wikipage.content' ).add( function () {
		updateLogoHrefForCurrentLanguage();
		var subpages = document.querySelector( '#mw-content-subtitle .subpages' );
		replaceBreadcrumbLeadGlyph( subpages );
		replaceBreadcrumbPipeSeparators( subpages );
		localizeSubpageBreadcrumb( subpages );
		enhanceSubpageNav();
	} );

	function normalizeContentLanguageCode( code ) {
		var normalized = ( code || '' ).toLowerCase();
		if ( normalized === 'sr-ec' || normalized === 'sr-el' ) {
			return 'sr';
		}
		return normalized;
	}

	function getCurrentContentLanguageCode() {
		var pageName = mw.config.get( 'wgPageName' ) || '';
		var match = pageName.match( /\/([a-z]{2,3}(?:-[a-z0-9]+)?)$/i );
		if ( match ) {
			return normalizeContentLanguageCode( match[1] );
		}
		return normalizeContentLanguageCode( mw.config.get( 'wgPageContentLanguage' ) || 'en' ) || 'en';
	}

	function updateLogoHrefForCurrentLanguage() {
		var logo = document.querySelector( '#p-logo a.mw-wiki-logo' );
		if ( !logo ) {
			return;
		}

		var currentLanguage = getCurrentContentLanguageCode();
		resolveMainPageTitle( function ( mainPageTitle ) {
			if ( !mainPageTitle ) {
				return;
			}
			var targetTitle = currentLanguage === 'en' ?
				mainPageTitle :
				mainPageTitle + '/' + currentLanguage;

			logo.setAttribute( 'href', mw.util.getUrl( targetTitle ) );
		} );
	}

	function resolveMainPageTitle( callback ) {
		var configured = mw.config.get( 'wgMainPageTitle' ) || mw.config.get( 'wgMainPage' );
		if ( configured ) {
			callback( configured );
			return;
		}

		if ( window.drMainPageTitle ) {
			callback( window.drMainPageTitle );
			return;
		}

		new mw.Api().get( {
			action: 'query',
			meta: 'siteinfo',
			siprop: 'general',
			formatversion: 2
		} ).then( function ( res ) {
			var mainPageTitle = ( ( res || {} ).query || {} ).general ?
				( ( res || {} ).query || {} ).general.mainpage :
				null;
			if ( mainPageTitle ) {
				window.drMainPageTitle = mainPageTitle;
			}
			callback( mainPageTitle || null );
		} ).catch( function () {
			callback( null );
		} );
	}

	function enhanceSubpageNav() {
		var containers = document.querySelectorAll( '.subpage-nav' );
		containers.forEach( function ( container ) {
			if ( container.querySelector( '.dr-subpage-nav-grid' ) ) {
				return;
			}
			var links = container.querySelectorAll( 'a' );
			if ( links.length < 3 ) {
				return;
			}

			var grid = document.createElement( 'div' );
			grid.className = 'dr-subpage-nav-grid';

			var left = document.createElement( 'div' );
			left.className = 'dr-subpage-nav-left';
			left.appendChild( document.createTextNode( '← ' ) );
			left.appendChild( links[0].cloneNode( true ) );

			var center = document.createElement( 'div' );
			center.className = 'dr-subpage-nav-center';
			center.appendChild( links[1].cloneNode( true ) );

			var right = document.createElement( 'div' );
			right.className = 'dr-subpage-nav-right';
			right.appendChild( links[2].cloneNode( true ) );
			right.appendChild( document.createTextNode( ' →' ) );

			grid.appendChild( left );
			grid.appendChild( center );
			grid.appendChild( right );
			container.innerHTML = '';
			container.appendChild( grid );
		} );
	}

	function replaceBreadcrumbLeadGlyph( subpages ) {
		if ( !subpages ) {
			return;
		}

		var first = subpages.firstChild;
		if ( first && first.nodeType === Node.TEXT_NODE ) {
			first.nodeValue = first.nodeValue.replace( /^\s*</, '' ).trimStart();
		}

		if ( subpages.querySelector( '.dr-breadcrumb-icon' ) ) {
			return;
		}

		var icon = document.createElement( 'span' );
		icon.className = 'dr-breadcrumb-icon';
		icon.setAttribute( 'aria-hidden', 'true' );
		icon.textContent = '‹';
		subpages.insertBefore( icon, subpages.firstChild );
	}

	function replaceBreadcrumbPipeSeparators( subpages ) {
		if ( !subpages ) {
			return;
		}

		var nodes = Array.prototype.slice.call( subpages.childNodes );
		nodes.forEach( function ( node ) {
			if ( node.nodeType !== Node.TEXT_NODE ) {
				return;
			}

			var value = node.nodeValue || '';
			if ( value.indexOf( '|' ) === -1 ) {
				return;
			}

			var parts = value.split( '|' );
			var frag = document.createDocumentFragment();
			parts.forEach( function ( part, idx ) {
				var cleaned = part.replace( /\u200e/g, '' );
				if ( cleaned.trim() ) {
					frag.appendChild( document.createTextNode( ' ' ) );
					frag.appendChild( document.createTextNode( cleaned.trim() ) );
				}
				if ( idx < parts.length - 1 ) {
					var sep = document.createElement( 'span' );
					sep.className = 'dr-breadcrumb-sep';
					sep.setAttribute( 'aria-hidden', 'true' );
					sep.textContent = '‹';
					frag.appendChild( document.createTextNode( ' ' ) );
					frag.appendChild( sep );
					frag.appendChild( document.createTextNode( ' ' ) );
				}
			} );

			node.parentNode.replaceChild( frag, node );
		} );
	}

	function localizeSubpageBreadcrumb( subpages ) {
		if ( !subpages || !window.mw || !mw.Api ) {
			return;
		}

		var pageLang = ( mw.config.get( 'wgPageContentLanguage' ) || '' ).toLowerCase();
		if ( !pageLang || pageLang === 'en' ) {
			return;
		}

		var links = Array.prototype.slice.call( subpages.querySelectorAll( 'a[href*="/Special:MyLanguage/"]' ) );
		if ( !links.length ) {
			return;
		}

		var titleToLinks = {};
		links.forEach( function ( link ) {
			var href = link.getAttribute( 'href' ) || '';
			var marker = '/Special:MyLanguage/';
			var idx = href.indexOf( marker );
			if ( idx === -1 ) {
				return;
			}

			var rawTitle = href.slice( idx + marker.length ).split( '#' )[0].split( '?' )[0];
			if ( !rawTitle ) {
				return;
			}

			var baseTitle = decodeURIComponent( rawTitle ).replace( /_/g, ' ' );
			var localizedTitle = baseTitle;
			if ( !new RegExp( '/' + pageLang.replace( '-', '\\-' ) + '$', 'i' ).test( baseTitle ) ) {
				localizedTitle = baseTitle + '/' + pageLang;
			}

			if ( !titleToLinks[ localizedTitle ] ) {
				titleToLinks[ localizedTitle ] = [];
			}
			titleToLinks[ localizedTitle ].push( link );
		} );

		var titles = Object.keys( titleToLinks );
		if ( !titles.length ) {
			return;
		}

		new mw.Api().get( {
			action: 'query',
			prop: 'info',
			inprop: 'displaytitle',
			titles: titles.join( '|' ),
			formatversion: 2
		} ).then( function ( res ) {
			var pages = ( ( res || {} ).query || {} ).pages || [];
			pages.forEach( function ( page ) {
				if ( !page || page.missing || !page.displaytitle || !titleToLinks[ page.title ] ) {
					return;
				}
				var decoded = document.createElement( 'div' );
				decoded.innerHTML = page.displaytitle;
				var label = ( decoded.textContent || '' ).trim();
				if ( !label ) {
					return;
				}

				titleToLinks[ page.title ].forEach( function ( link ) {
					link.textContent = label;
				} );
			} );
		} ).catch( function () {
			// Fail silently.
		} );
	}
}() );
