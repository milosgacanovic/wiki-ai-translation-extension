/* Shared site-level JS
   Keep breadcrumb localization, without any header/tab DOM reparenting. */
( function () {
	'use strict';

	mw.hook( 'wikipage.content' ).add( function () {
		var subpages = document.querySelector( '#mw-content-subtitle .subpages' );
		localizeSubpageBreadcrumb( subpages );
	} );

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
