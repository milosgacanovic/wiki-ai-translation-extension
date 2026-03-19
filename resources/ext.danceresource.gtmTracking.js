( function () {
	'use strict';

	var dl  = window.dataLayer = window.dataLayer || [];
	var cfg = mw.config.get( 'drGtm' ) || {};

	/* ── 1 & 4. User auth state + article page view ── */
	dl.push( {
		user_authenticated: cfg.userAuthenticated || false,
		user_groups:        cfg.userGroups        || []
	} );

	dl.push( {
		event:              'wiki_page_view',
		article_title:      cfg.articleTitle     || '',
		article_namespace:  cfg.articleNamespace,
		article_categories: cfg.categories       || [],
		article_word_count: ( document.querySelector( '#mw-content-text' ) || {} )
			.innerText
			? document.querySelector( '#mw-content-text' ).innerText
				.trim().split( /\s+/ ).length
			: 0,
		is_main_page:       cfg.isMainPage        || false,
		article_id:         cfg.articleId
	} );

	/* ── 2. Fresh-login detection ── */
	if ( cfg.justLoggedIn ) {
		dl.push( {
			event:            'login_complete',
			login_source:     'sso_keycloak',
			login_return_to:  cfg.pageName
		} );
	}

	/* ── 3. Login intent click tracking ── */
	document.querySelectorAll(
		'a[href*="PluggableAuthLogin"], a[href*="UserLogin"]'
	).forEach( function ( link ) {
		link.addEventListener( 'click', function () {
			dl.push( {
				event:             'login_intent',
				login_source_page: cfg.pageName
			} );
		} );
	} );

	/* ── 6. Internal link clicks in article body ── */
	var contentEl = document.querySelector( '#mw-content-text' );
	if ( contentEl ) {
		contentEl.addEventListener( 'click', function ( e ) {
			var link = e.target.closest( 'a' );
			if ( link && link.href && link.href.includes( 'wiki.danceresource.org' ) ) {
				dl.push( {
					event:          'wiki_internal_link',
					link_text:      link.textContent.trim().substring( 0, 100 ),
					source_article: cfg.articleTitle,
					target_url:     link.pathname
				} );
			}
		} );
	}

	/* ── 7. Search intercept ── */
	var searchForms = document.querySelectorAll(
		'form[action*="Special:Search"], #searchform, .mw-searchInput'
	);
	searchForms.forEach( function ( form ) {
		var el = ( form.tagName === 'FORM' ) ? form : form.closest( 'form' );
		if ( !el ) { return; }
		el.addEventListener( 'submit', function () {
			var input = el.querySelector( 'input[type="search"], input[name="search"]' );
			dl.push( {
				event:       'wiki_search',
				search_term: input ? input.value.trim() : ''
			} );
		} );
	} );

	/* ── 8. Table of contents clicks ── */
	var tocEl = document.querySelector( '#toc, .mw-parser-output .toc' );
	if ( tocEl ) {
		tocEl.addEventListener( 'click', function ( e ) {
			var link = e.target.closest( 'a' );
			if ( link ) {
				dl.push( {
					event:         'toc_click',
					section_name:  link.textContent.trim(),
					article_title: cfg.articleTitle
				} );
			}
		} );
	}

	/* ── 5 & 9. Scroll depth + time on article (NS_0 only) ── */
	if ( cfg.articleNamespace === 0 && contentEl ) {

		/* Scroll depth — IntersectionObserver sentinels at 25/50/75/100% */
		var depthsReported = {};
		function makeSentinel( pct ) {
			var sentinel      = document.createElement( 'div' );
			sentinel.setAttribute( 'aria-hidden', 'true' );
			sentinel.style.cssText = 'position:absolute;width:1px;height:1px;';
			var wrapper = contentEl;
			wrapper.style.position = wrapper.style.position || 'relative';
			sentinel.style.top = pct + '%';
			wrapper.appendChild( sentinel );

			new IntersectionObserver( function ( entries, obs ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting && !depthsReported[ pct ] ) {
						depthsReported[ pct ] = true;
						dl.push( {
							event:         'article_scroll',
							scroll_depth:  pct,
							article_title: cfg.articleTitle
						} );
						obs.disconnect();
					}
				} );
			} ).observe( sentinel );
		}
		[ 25, 50, 75, 100 ].forEach( makeSentinel );

		/* Time-on-article — track visible engagement seconds */
		var engagedSeconds = 0;
		var lastVisible    = null;
		var pingInterval   = null;

		function onVisibilityChange() {
			if ( document.visibilityState === 'visible' ) {
				lastVisible  = Date.now();
				pingInterval = setInterval( function () {
					engagedSeconds += 30;
					dl.push( {
						event:           'article_engagement_time',
						seconds_engaged: engagedSeconds,
						article_title:   cfg.articleTitle
					} );
				}, 30000 );
			} else {
				if ( lastVisible !== null ) {
					engagedSeconds += Math.round( ( Date.now() - lastVisible ) / 1000 );
					lastVisible = null;
				}
				clearInterval( pingInterval );
			}
		}

		document.addEventListener( 'visibilitychange', onVisibilityChange );
		if ( document.visibilityState === 'visible' ) { onVisibilityChange(); }

		window.addEventListener( 'beforeunload', function () {
			if ( document.visibilityState === 'visible' && lastVisible !== null ) {
				engagedSeconds += Math.round( ( Date.now() - lastVisible ) / 1000 );
			}
			if ( engagedSeconds > 0 ) {
				dl.push( {
					event:           'article_engagement_time',
					seconds_engaged: engagedSeconds,
					article_title:   cfg.articleTitle
				} );
			}
		} );
	}

}() );
