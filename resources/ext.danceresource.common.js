/* Shared site-level JS
   Keep breadcrumb localization, without any header/tab DOM reparenting. */
( function () {
	'use strict';

	mw.hook( 'wikipage.content' ).add( function () {
		var subpages = document.querySelector( '#mw-content-subtitle .subpages' );
		replaceBreadcrumbLeadGlyph( subpages );
		replaceBreadcrumbPipeSeparators( subpages );
		localizeSubpageBreadcrumb( subpages );
		enhanceSubpageNav();
	} );

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
