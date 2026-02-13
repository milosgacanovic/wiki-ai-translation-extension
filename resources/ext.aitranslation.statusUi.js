( function () {
	'use strict';

	var cfg = mw.config.get( 'aiTranslationStatus' );
	if ( !cfg || !cfg.enabled ) {
		return;
	}

	var STATUS_MACHINE = 'machine';
	var STATUS_REVIEWED = 'reviewed';
	var STATUS_OUTDATED = 'outdated';
	var STATUS_UNKNOWN = 'unknown';
	var HIDE_MACHINE_KEY = 'ai_hide_machine_translation_banner';

	function getStorage() {
		try {
			return window.localStorage;
		} catch ( e ) {
			return null;
		}
	}

	function isMachineBannerHidden() {
		var storage = getStorage();
		return !!( storage && storage.getItem( HIDE_MACHINE_KEY ) === '1' );
	}

	function hideMachineBanner() {
		var storage = getStorage();
		if ( storage ) {
			storage.setItem( HIDE_MACHINE_KEY, '1' );
		}
	}

	function setMachineBannerHidden( hidden ) {
		var storage = getStorage();
		if ( !storage ) {
			return;
		}
		if ( hidden ) {
			storage.setItem( HIDE_MACHINE_KEY, '1' );
		} else {
			storage.removeItem( HIDE_MACHINE_KEY );
		}
	}

	function appendTextWithBreaks( parent, text ) {
		var parts = String( text || '' ).split( /<br\s*\/?>/i );
		for ( var i = 0; i < parts.length; i++ ) {
			if ( parts[i] ) {
				parent.appendChild( document.createTextNode( parts[i] ) );
			}
			if ( i < parts.length - 1 ) {
				parent.appendChild( document.createElement( 'br' ) );
			}
		}
	}

	function findLanguageSelector() {
		var selectors = [
			'.mw-interlanguage-selector',
			'#p-lang-btn',
			'#p-lang',
			'#p-language-compact'
		];
		for ( var i = 0; i < selectors.length; i++ ) {
			var el = document.querySelector( selectors[ i ] );
			if ( el ) {
				return el;
			}
		}
		return null;
	}

	function getContentLanguageCode() {
		var page = mw.config.get( 'wgPageName' ) || '';
		var match = page.match( /\/([a-z-]+)$/i );
		if ( !match ) {
			return 'en';
		}
		var code = match[1].toLowerCase();
		if ( code === 'sr-el' || code === 'sr-ec' ) {
			return 'sr';
		}
		return code.split( '-' )[0] || 'en';
	}

	function translateEditorUrl() {
		var sourceTitle = cfg.sourceTitle ||
			( mw.config.get( 'wgPageName' ) || '' ).replace( /\/[a-z-]+$/i, '' );
		var group = 'page-' + String( sourceTitle ).replace( /_/g, ' ' );
		return mw.util.getUrl( 'Special:Translate', {
			group: group,
			action: 'page',
			filter: '',
			language: getContentLanguageCode()
		} );
	}

	function buildTooltipText( info ) {
		var parts = [];
		parts.push( mw.message( 'aits-tooltip-translation-status' ).text() + ': ' + getStatusLabel( info.status ) );
		if ( info.source_rev ) {
			parts.push( mw.message( 'aits-tooltip-source-rev' ).text() + ': ' + info.source_rev );
		}
		if ( info.outdated_source_rev ) {
			parts.push( mw.message( 'aits-tooltip-outdated-rev' ).text() + ': ' + info.outdated_source_rev );
		}
		if ( info.reviewed_by ) {
			parts.push( mw.message( 'aits-tooltip-reviewed-by' ).text() + ': ' + info.reviewed_by );
		}
		if ( info.reviewed_at ) {
			parts.push( mw.message( 'aits-tooltip-reviewed-at' ).text() + ': ' + info.reviewed_at );
		}
		return parts.join( '\n' );
	}

	function getPageSourceLabel() {
		var msg = mw.message( 'aits-open-page-source' );
		if ( msg.exists() ) {
			return msg.text();
		}
		return mw.message( 'viewsource' ).exists() ? mw.message( 'viewsource' ).text() : 'Page source';
	}

	function getPortletLink( id, fallbackHref, fallbackLabel ) {
		var anchor = document.querySelector( id + ' a' );
		if ( !anchor ) {
			return {
				href: fallbackHref,
				label: fallbackLabel
			};
		}
		return {
			href: anchor.getAttribute( 'href' ) || anchor.href || fallbackHref,
			label: ( anchor.textContent || '' ).trim() || fallbackLabel
		};
	}

	function isUserLoggedIn() {
		return !!mw.config.get( 'wgUserName' );
	}

	function getWatchStateFromDom() {
		var tab = document.querySelector( '#ca-watch, #ca-unwatch' );
		if ( !tab ) {
			return false;
		}
		if ( tab.id === 'ca-unwatch' || tab.classList.contains( 'ca-unwatch' ) || tab.classList.contains( 'watched' ) ) {
			return true;
		}
		var anchor = tab.querySelector( 'a' );
		if ( !anchor ) {
			return false;
		}
		var href = anchor.getAttribute( 'href' ) || '';
		if ( /[?&]action=unwatch(?:&|$)/.test( href ) ) {
			return true;
		}
		if ( /[?&]action=watch(?:&|$)/.test( href ) ) {
			return false;
		}
		return false;
	}

	function syncWatchStateFromApi( checkbox ) {
		new mw.Api().get( {
			action: 'query',
			titles: mw.config.get( 'wgPageName' ),
			prop: 'info',
			inprop: 'watched'
		} ).then( function ( data ) {
			var pages = ( data && data.query && data.query.pages ) || {};
			var keys = Object.keys( pages );
			if ( !keys.length ) {
				return;
			}
			var page = pages[keys[0]] || {};
			checkbox.checked = Object.prototype.hasOwnProperty.call( page, 'watched' );
		} ).catch( function () {
			// Ignore state sync errors; checkbox still works as toggle.
		} );
	}

	function buildWatchCheckboxItem() {
		var row = document.createElement( 'label' );
		row.className = 'ai-translation-status-tooltip-watch';

		var checkbox = document.createElement( 'input' );
		checkbox.type = 'checkbox';
		checkbox.checked = getWatchStateFromDom();
		checkbox.disabled = !isUserLoggedIn();
		row.appendChild( checkbox );

		var labelText = mw.message( 'watch' ).exists() ? mw.message( 'watch' ).text() : 'Watch';
		row.appendChild( document.createTextNode( ' ' + labelText ) );

		if ( isUserLoggedIn() ) {
			syncWatchStateFromApi( checkbox );
			row.addEventListener( 'mousedown', function ( e ) {
				e.stopPropagation();
			} );
			row.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
			} );
			checkbox.addEventListener( 'change', function () {
				checkbox.disabled = true;
				var params = {
					action: 'watch',
					format: 'json',
					formatversion: 2,
					titles: mw.config.get( 'wgPageName' )
				};
				if ( !checkbox.checked ) {
					params.unwatch = 1;
				}
				new mw.Api().postWithToken( 'watch', params ).then( function () {
					checkbox.disabled = false;
				} ).catch( function () {
					checkbox.checked = !checkbox.checked;
					checkbox.disabled = false;
				} );
			} );
		}

		return row;
	}

	function createTooltip( info ) {
		var tooltip = document.createElement( 'div' );
		tooltip.className = 'ai-translation-status-tooltip';
		tooltip.setAttribute( 'role', 'tooltip' );
		var isSourcePage = cfg && cfg.title && cfg.sourceTitle && cfg.title === cfg.sourceTitle;

		function addRow( labelMsg, value ) {
			if ( !value ) {
				return;
			}
			var row = document.createElement( 'div' );
			row.className = 'ai-translation-status-tooltip-row';
			var label = document.createElement( 'strong' );
			label.textContent = mw.message( labelMsg ).text() + ': ';
			row.appendChild( label );
			row.appendChild( document.createTextNode( String( value ) ) );
			tooltip.appendChild( row );
		}

		addRow( 'aits-tooltip-translation-status', getStatusLabel( info.status ) );
		addRow( 'aits-tooltip-source-rev', info.source_rev );
		addRow( 'aits-tooltip-outdated-rev', info.outdated_source_rev );
		addRow( 'aits-tooltip-reviewed-by', info.reviewed_by );
		addRow( 'aits-tooltip-reviewed-at', info.reviewed_at );

		var pageName = mw.config.get( 'wgPageName' ) || '';
		var talkFallbackHref = mw.util.getUrl( 'Talk:' + pageName, {
			action: 'edit',
			redlink: 1
		} );
		var links = document.createElement( 'div' );
		links.className = 'ai-translation-status-tooltip-links';
		var menuLinks = [];
		if ( isSourcePage ) {
			menuLinks = [
				{
					href: mw.util.getUrl( pageName, { veaction: 'edit' } ),
					label: mw.message( 'edit' ).exists() ? mw.message( 'edit' ).text() : 'Edit'
				},
				{
					href: mw.util.getUrl( pageName, { action: 'edit' } ),
					label: mw.message( 'viewsource' ).exists() ? mw.message( 'viewsource' ).text() : 'Edit source'
				},
				getPortletLink(
					'#ca-talk',
					talkFallbackHref,
					mw.message( 'talk' ).exists() ? mw.message( 'talk' ).text() : 'Talk'
				)
			];
		} else {
			menuLinks = [
				{
					href: translateEditorUrl(),
					label: mw.message( 'aits-open-translate-editor' ).text()
				},
				{
					href: sourceUrl(),
					label: mw.message( 'aits-open-source' ).text()
				},
				getPortletLink(
					'#ca-talk',
					talkFallbackHref,
					mw.message( 'talk' ).exists() ? mw.message( 'talk' ).text() : 'Talk'
				)
			];
		}
		menuLinks.forEach( function ( item ) {
			var link = document.createElement( 'a' );
			link.href = item.href;
			link.textContent = item.label;
			links.appendChild( link );
		} );
		tooltip.appendChild( links );

		if ( isSourcePage ) {
			var sourcePref = document.createElement( 'div' );
			sourcePref.className = 'ai-translation-status-tooltip-pref';
			var sourcePrefActions = document.createElement( 'div' );
			sourcePrefActions.className = 'ai-translation-status-tooltip-pref-actions';
			sourcePrefActions.appendChild( buildWatchCheckboxItem() );
			sourcePref.appendChild( sourcePrefActions );
			tooltip.appendChild( sourcePref );
		} else if ( info.status === STATUS_MACHINE ) {
			var pref = document.createElement( 'div' );
			pref.className = 'ai-translation-status-tooltip-pref';
			var prefActions = document.createElement( 'div' );
			prefActions.className = 'ai-translation-status-tooltip-pref-actions';
			var pageSourceLink = document.createElement( 'a' );
			pageSourceLink.href = mw.util.getUrl( pageName, { action: 'edit' } );
			pageSourceLink.textContent = getPageSourceLabel();
			prefActions.appendChild( pageSourceLink );
			prefActions.appendChild( buildWatchCheckboxItem() );
			pref.appendChild( prefActions );

			var prefToggle = document.createElement( 'label' );
			prefToggle.className = 'ai-translation-status-tooltip-pref-toggle';
			var checkbox = document.createElement( 'input' );
			checkbox.type = 'checkbox';
			checkbox.checked = !isMachineBannerHidden();
			checkbox.addEventListener( 'mousedown', function ( e ) {
				e.stopPropagation();
			} );
			checkbox.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
			} );
			checkbox.addEventListener( 'change', function () {
				setMachineBannerHidden( !checkbox.checked );
				if ( checkbox.checked ) {
					renderBanner( info );
				} else {
					var banner = document.getElementById( 'ai-translation-status-banner' );
					if ( banner ) {
						banner.remove();
					}
				}
			} );
			prefToggle.appendChild( checkbox );
			prefToggle.appendChild( document.createTextNode( ' ' + mw.message( 'aits-show-machine-banner' ).text() ) );
			pref.appendChild( prefToggle );
			tooltip.appendChild( pref );
		}

		document.body.appendChild( tooltip );
		return tooltip;
	}

	function positionTooltip( dot, tooltip ) {
		var rect = dot.getBoundingClientRect();
		var top = rect.bottom + window.scrollY + 8;
		var left = rect.left + window.scrollX - 120;
		if ( left < 8 ) {
			left = 8;
		}
		tooltip.style.top = top + 'px';
		tooltip.style.left = left + 'px';
	}

	function bindTooltip( dot, info ) {
		var tooltip = null;
		var hideTimer = null;

		function ensureTooltip() {
			if ( hideTimer ) {
				clearTimeout( hideTimer );
			}
			if ( !tooltip ) {
				tooltip = createTooltip( info );
				tooltip.addEventListener( 'mouseenter', ensureTooltip );
				tooltip.addEventListener( 'focusin', ensureTooltip );
				tooltip.addEventListener( 'mouseleave', function ( e ) {
					if ( e.relatedTarget && ( dot.contains( e.relatedTarget ) || tooltip.contains( e.relatedTarget ) ) ) {
						return;
					}
					hideTooltip();
				} );
				tooltip.addEventListener( 'focusout', function ( e ) {
					if ( e.relatedTarget && ( dot.contains( e.relatedTarget ) || tooltip.contains( e.relatedTarget ) ) ) {
						return;
					}
					if ( tooltip.contains( document.activeElement ) || tooltip.matches( ':hover' ) ) {
						return;
					}
					hideTooltip();
				} );
			}
			positionTooltip( dot, tooltip );
			tooltip.classList.add( 'is-open' );
		}

		function hideTooltip() {
			if ( hideTimer ) {
				clearTimeout( hideTimer );
			}
			hideTimer = setTimeout( function () {
				if ( tooltip ) {
					tooltip.classList.remove( 'is-open' );
				}
			}, 100 );
		}

		dot.addEventListener( 'mouseenter', ensureTooltip );
		dot.addEventListener( 'focus', ensureTooltip );
		dot.addEventListener( 'mouseleave', function ( e ) {
			if ( tooltip && e.relatedTarget && tooltip.contains( e.relatedTarget ) ) {
				return;
			}
			hideTooltip();
		} );
		dot.addEventListener( 'blur', function ( e ) {
			if ( tooltip && e.relatedTarget && tooltip.contains( e.relatedTarget ) ) {
				return;
			}
			hideTooltip();
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && tooltip ) {
				tooltip.classList.remove( 'is-open' );
			}
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( !tooltip || !tooltip.classList.contains( 'is-open' ) ) {
				return;
			}
			if ( e.target === dot || dot.contains( e.target ) || tooltip.contains( e.target ) ) {
				return;
			}
			tooltip.classList.remove( 'is-open' );
		} );
	}

	function getStatusLabel( status ) {
		var isSourcePage = cfg && cfg.title && cfg.sourceTitle && cfg.title === cfg.sourceTitle;
		if ( isSourcePage ) {
			return mw.message( 'aits-status-source-language' ).text();
		}
		if ( status === STATUS_REVIEWED ) {
			return mw.message( 'aits-status-reviewed' ).text();
		}
		if ( status === STATUS_OUTDATED ) {
			return mw.message( 'aits-status-outdated' ).text();
		}
		if ( status === STATUS_UNKNOWN ) {
			return mw.message( 'aits-status-unknown' ).text();
		}
		return mw.message( 'aits-status-machine' ).text();
	}

	function renderDot( info ) {
		var anchor = findLanguageSelector();
		if ( !anchor ) {
			return false;
		}

		var dot = document.getElementById( 'ai-translation-status-dot' );
		if ( !dot ) {
			dot = document.createElement( 'button' );
			dot.id = 'ai-translation-status-dot';
			dot.type = 'button';
			anchor.parentNode.insertBefore( dot, anchor );
		}

		dot.className = 'ai-translation-status-dot ai-translation-status-dot-' + info.status;
		dot.setAttribute( 'aria-label', getStatusLabel( info.status ) );
		dot.setAttribute( 'aria-description', buildTooltipText( info ) );
		dot.setAttribute( 'tabindex', '0' );
		dot.removeAttribute( 'aria-hidden' );

		if ( !dot.dataset.bound ) {
			bindTooltip( dot, info );
			dot.dataset.bound = '1';
		}
		return true;
	}

	function ensureDotPlaceholder() {
		var anchor = findLanguageSelector();
		if ( !anchor ) {
			return false;
		}
		if ( document.getElementById( 'ai-translation-status-dot' ) ) {
			return true;
		}
		var dot = document.createElement( 'button' );
		dot.id = 'ai-translation-status-dot';
		dot.type = 'button';
		dot.className = 'ai-translation-status-dot ai-translation-status-dot-unknown ai-translation-status-dot-pending';
		dot.setAttribute( 'aria-hidden', 'true' );
		dot.setAttribute( 'tabindex', '-1' );
		anchor.parentNode.insertBefore( dot, anchor );
		return true;
	}

	function ensureDotPlaceholderWithRetry() {
		if ( ensureDotPlaceholder() ) {
			return;
		}
		var attempts = 0;
		var maxAttempts = 24;
		var timer = setInterval( function () {
			attempts++;
			if ( ensureDotPlaceholder() || attempts >= maxAttempts ) {
				clearInterval( timer );
			}
		}, 250 );
	}

	function renderDotWithRetry( info ) {
		if ( renderDot( info ) ) {
			return;
		}

		var attempts = 0;
		var maxAttempts = 24;
		var timer = setInterval( function () {
			attempts++;
			if ( renderDot( info ) || attempts >= maxAttempts ) {
				clearInterval( timer );
			}
		}, 250 );
	}

	function contentContainer() {
		return document.querySelector( '#mw-content-text' ) ||
			document.querySelector( '.mw-parser-output' ) ||
			document.querySelector( '.mw-body-content' );
	}

	function sourceUrl() {
		if ( cfg.sourceTitle ) {
			return mw.util.getUrl( cfg.sourceTitle );
		}
		var page = mw.config.get( 'wgPageName' ) || '';
		return mw.util.getUrl( page.replace( /\/[a-z-]+$/i, '' ) );
	}

	function renderBanner( info ) {
		if ( info.status !== STATUS_MACHINE && info.status !== STATUS_OUTDATED ) {
			return;
		}

		if ( info.status === STATUS_MACHINE && isMachineBannerHidden() ) {
			return;
		}

		var container = contentContainer();
		if ( !container || document.getElementById( 'ai-translation-status-banner' ) ) {
			return;
		}

		var banner = document.createElement( 'div' );
		banner.id = 'ai-translation-status-banner';
		banner.className = 'ai-translation-status-banner ai-translation-status-banner-' + info.status;

		var close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'ai-translation-status-close';
		close.textContent = 'x';
		close.setAttribute( 'aria-label', mw.message( 'aits-close' ).text() );

		var title = document.createElement( 'strong' );
		title.className = 'ai-translation-status-title';
		title.textContent = info.status === STATUS_OUTDATED ?
			mw.message( 'aits-banner-outdated-title' ).text() : '';

		var body = document.createElement( 'span' );
		body.className = 'ai-translation-status-body';
		if ( info.status === STATUS_OUTDATED ) {
			body.textContent = mw.message( 'aits-banner-outdated-body' ).text();
		}

		var editLink = document.createElement( 'a' );
		editLink.href = translateEditorUrl();
		if ( info.status === STATUS_OUTDATED ) {
			editLink.textContent = mw.message( 'aits-banner-outdated-cta' ).text();
		} else {
			editLink.textContent = mw.message( 'aits-banner-machine-link-text' ).text();
			var machineTemplate = mw.message( 'aits-banner-machine-body' ).text();
			var machineParts = machineTemplate.split( '$1' );
			appendTextWithBreaks( body, machineParts[0] || '' );
			body.appendChild( editLink );
			appendTextWithBreaks( body, machineParts.slice( 1 ).join( '$1' ) || '' );
		}

		var actions = document.createElement( 'span' );
		actions.className = 'ai-translation-status-actions';
		if ( info.status === STATUS_OUTDATED ) {
			actions.appendChild( editLink );
		}

		if ( info.status === STATUS_OUTDATED ) {
			var sourceLink = document.createElement( 'a' );
			sourceLink.href = sourceUrl();
			sourceLink.textContent = mw.message( 'aits-open-source' ).text();
			actions.appendChild( document.createTextNode( ' | ' ) );
			actions.appendChild( sourceLink );
		}

		banner.appendChild( close );
		if ( title.textContent ) {
			banner.appendChild( title );
			banner.appendChild( document.createTextNode( ' ' ) );
		}
		banner.appendChild( body );
		banner.appendChild( document.createTextNode( ' ' ) );
		banner.appendChild( actions );
		container.insertBefore( banner, container.firstChild );

		close.addEventListener( 'click', function () {
			if ( info.status === STATUS_MACHINE ) {
				hideMachineBanner();
			}
			banner.remove();
		} );
	}

	function renderStatus( info ) {
		if ( !info ) {
			return;
		}
		if ( !info.status ) {
			// Source page can legitimately have no ai_* metadata; treat it as healthy.
			if ( cfg && cfg.title && cfg.sourceTitle && cfg.title === cfg.sourceTitle ) {
				info.status = STATUS_REVIEWED;
			} else {
				info.status = STATUS_UNKNOWN;
			}
		}
		renderDotWithRetry( info );
		renderBanner( info );
		hideClassicPageTabs();
	}

	function hideClassicPageTabs() {
		var selectors = [
			'#ca-nstab-main',
			'#ca-talk',
			'#ca-watch',
			'#ca-history'
		];
		selectors.forEach( function ( selector ) {
			var el = document.querySelector( selector );
			if ( el ) {
				el.style.display = 'none';
			}
		} );
	}

	function load() {
		ensureDotPlaceholderWithRetry();

		new mw.Api().get( {
			action: 'aitranslationinfo',
			title: cfg.title || mw.config.get( 'wgPageName' )
		} ).then( function ( data ) {
			renderStatus( data.aitranslationinfo || {} );
		} ).catch( function () {
			// Fail silently by design.
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', load );
	} else {
		load();
	}
}() );
