<?php
// Local hook registrations for AiTranslationExtension.

$wgAutoloadClasses['MediaWiki\\Extension\\AiTranslationExtension\\ApiMarkForTranslation'] =
	"$IP/extensions/AiTranslationExtension/src/ApiMarkForTranslation.php";
$wgAutoloadClasses['MediaWiki\\Extension\\AiTranslationExtension\\HookHandler'] =
	"$IP/extensions/AiTranslationExtension/src/HookHandler.php";
$wgAPIModules['markfortranslation'] =
	'MediaWiki\\Extension\\AiTranslationExtension\\ApiMarkForTranslation';

// Language-specific sidebar via AiTranslationExtension (with optional debug)
$wgHooks['SkinBuildSidebar'][] = static function ( $skin, &$bar ) {
	return \MediaWiki\Extension\AiTranslationExtension\HookHandler::onSkinBuildSidebar( $skin, $bar );
};

$wgHooks['PageSaveComplete'][] = static function (
	$wikiPage,
	$user,
	$summary,
	$flags,
	$revisionRecord,
	$editResult
) {
	return \MediaWiki\Extension\AiTranslationExtension\HookHandler::onPageSaveComplete(
		$wikiPage,
		$user,
		$summary,
		$flags,
		$revisionRecord,
		$editResult
	);
};

$wgHooks['Translate:newTranslation'][] = static function (
	$handle,
	$revisionId,
	$text,
	$user
) {
	return \MediaWiki\Extension\AiTranslationExtension\HookHandler::onTranslateNewTranslation(
		$handle,
		$revisionId,
		$text,
		$user
	);
};

$wgHooks['SearchDataForIndex2'][] = static function (
	array &$fields,
	$handler,
	$page,
	$output,
	$engine,
	$revision
) {
	return \MediaWiki\Extension\AiTranslationExtension\HookHandler::onSearchDataForIndex2(
		$fields,
		$handler,
		$page,
		$output,
		$engine,
		$revision
	);
};

// Search: clean wikitext snippets + language-scoped result filtering.
$wgHooks['ShowSearchHit'][] = static function (
	$searchPage, $result, $terms, &$link, &$redirect,
	&$section, &$extract, &$score, &$size, &$date, &$related, &$html
) {
	return \MediaWiki\Extension\AiTranslationExtension\HookHandler::onShowSearchHit(
		$searchPage, $result, $terms, $link, $redirect,
		$section, $extract, $score, $size, $date, $related, $html
	);
};

$wgHooks['SpecialSearchSetupEngine'][] = static function ( $search, $profile, $engine ) {
	\MediaWiki\Extension\AiTranslationExtension\HookHandler::onSpecialSearchSetupEngine(
		$search, $profile, $engine
	);
};

$wgHooks['ApiOpenSearchSuggest'][] = static function ( array &$results ) {
	\MediaWiki\Extension\AiTranslationExtension\HookHandler::onApiOpenSearchSuggest( $results );
};

$wgHooks['SpecialSearchResultsPrepend'][] = static function ( $specialSearch, $output, $term ) {
	return \MediaWiki\Extension\AiTranslationExtension\HookHandler::onSpecialSearchResultsPrepend(
		$specialSearch, $output, $term
	);
};

$wgHooks['SpecialSearchResultsAppend'][] = static function ( $specialSearch, $output, $term ) {
	\MediaWiki\Extension\AiTranslationExtension\HookHandler::onSpecialSearchResultsAppend(
		$specialSearch, $output, $term
	);
};

$wgHooks['ShowSearchHitTitle'][] = static function (
	&$title, &$titleSnippet, $result, $terms, $specialSearch, &$query, &$attributes
) {
	\MediaWiki\Extension\AiTranslationExtension\HookHandler::onShowSearchHitTitle(
		$title, $titleSnippet, $result, $terms, $specialSearch, $query, $attributes
	);
};

// Unified language switcher hooks (registered locally to avoid autoload conflicts).
$wgHooks['BeforePageDisplay'][] = static function ( $out, $skin ) {
	$title = $out->getTitle();
	if ( !$title ) {
		return true;
	}

	// Load common module on every page so the drsearch autocomplete override
	// in ext.danceresource.common.js works everywhere (including Special:Search).
	$out->addModules( [ 'ext.danceresource.common' ] );
	$out->addModuleStyles( [ 'ext.danceresource.common' ] );

	// Shared fullscreen overlay for SSO redirects.
	if (
		$out->getUser()->isAnon() &&
		(
			!empty( $GLOBALS['wgDRSSOSilentCheckEnabled'] ) ||
			!empty( $GLOBALS['wgDRSSOAutoRedirectLogin'] )
		)
	) {
		$out->addHeadItem(
			'dr-sso-overlay-style',
			'<style id="dr-sso-overlay-style">'
			. '.sso-redirect{position:fixed;inset:0;display:none;z-index:2147483647;background:#fff;align-items:center;justify-content:center;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;}'
			. '.sso-redirect.is-active{display:flex;}'
			. '.sso-redirect__card{text-align:center;color:#666;padding:24px;}'
			. '.sso-redirect__spinner{width:22px;height:22px;margin:0 auto 14px;border:2px solid #d2d2d2;border-top-color:#666;border-radius:50%;animation:dr-sso-spin .9s linear infinite;}'
			. '.sso-redirect__title{margin:0 0 6px;font-size:18px;font-weight:500;}'
			. '.sso-redirect__text{margin:0;font-size:14px;}'
			. '@keyframes dr-sso-spin{to{transform:rotate(360deg);}}'
			. 'html[data-theme="dark"] .sso-redirect{background:#1b1b1f;}'
			. 'html[data-theme="dark"] .sso-redirect__card{color:#a1a1aa;}'
			. 'html[data-theme="dark"] .sso-redirect__spinner{border-color:#3f3f46;border-top-color:#a1a1aa;}'
			. '@media(prefers-color-scheme:dark){html:not([data-theme]) .sso-redirect{background:#1b1b1f;}html:not([data-theme]) .sso-redirect__card{color:#a1a1aa;}html:not([data-theme]) .sso-redirect__spinner{border-color:#3f3f46;border-top-color:#a1a1aa;}}'
			. '</style>'
			. '<script>!function(){var s=localStorage.getItem("dr-theme");if(s)document.documentElement.setAttribute("data-theme",s);else if(window.matchMedia&&window.matchMedia("(prefers-color-scheme:dark)").matches);else document.documentElement.setAttribute("data-theme","light");}();</script>'
		);
		$out->addInlineScript(
			"(function(){"
			. "if(window.drShowSsoRedirectOverlay){return;}"
			. "window.drShowSsoRedirectOverlay=function(){"
			. "var overlay=document.getElementById('sso-redirect');"
			. "if(!overlay){"
			. "overlay=document.createElement('div');"
			. "overlay.id='sso-redirect';"
			. "overlay.className='sso-redirect';"
			. "overlay.setAttribute('role','status');"
			. "overlay.setAttribute('aria-live','polite');"
			. "overlay.innerHTML='<div class=\"sso-redirect__card\"><div class=\"sso-redirect__spinner\" aria-hidden=\"true\"></div><h1 class=\"sso-redirect__title\">Redirecting to DanceResource login system...</h1><p class=\"sso-redirect__text\">Please wait.</p></div>';"
			. "(document.body||document.documentElement).appendChild(overlay);"
			. "}"
			. "overlay.classList.add('is-active');"
			. "};"
			. "})();"
		);
	}

	// Optional: silent background SSO probe on anonymous page views.
	// If the user already has a Keycloak session, redirect to local wiki login flow.
	if (
		!empty( $GLOBALS['wgDRSSOSilentCheckEnabled'] ) &&
		$out->getUser()->isAnon() &&
		!$title->isSpecialPage()
	) {
		$request = $out->getRequest();
		$bypassParam = $GLOBALS['wgDRSSOAutoRedirectBypassParam'] ?? 'local';
		$issuer = isset( $GLOBALS['wgDanceResourceKeycloakIssuer'] ) ? trim( (string)$GLOBALS['wgDanceResourceKeycloakIssuer'] ) : '';
		$clientId = isset( $GLOBALS['wgDanceResourceKeycloakClientId'] ) ? trim( (string)$GLOBALS['wgDanceResourceKeycloakClientId'] ) : '';

		if ( !$request->getBool( $bypassParam ) && $issuer !== '' && $clientId !== '' ) {
			$callbackUrl = isset( $GLOBALS['wgDRSSOSilentCheckCallbackUrl'] ) &&
				is_string( $GLOBALS['wgDRSSOSilentCheckCallbackUrl'] ) &&
				$GLOBALS['wgDRSSOSilentCheckCallbackUrl'] !== ''
				? $GLOBALS['wgDRSSOSilentCheckCallbackUrl']
				: rtrim( $GLOBALS['wgServer'] ?? '', '/' ) . '/extensions/AiTranslationExtension/resources/sso-probe-callback.html';

			$authEndpoint = rtrim( $issuer, '/' ) . '/protocol/openid-connect/auth';
			$fallbackLoginUrl = \SpecialPage::getTitleFor( 'Userlogin' )->getFullURL( [
				'returnto' => $title->getPrefixedText(),
				'returntoquery' => $request->getRawQueryString()
			] );

			$out->addInlineScript(
				"(function(){"
				. "if(window.__drSsoProbeInitialized){return;}window.__drSsoProbeInitialized=true;"
				. "var callbackUrl=" . json_encode( $callbackUrl ) . ";"
				. "var authEndpoint=" . json_encode( $authEndpoint ) . ";"
				. "var clientId=" . json_encode( $clientId ) . ";"
				. "var fallbackLoginUrl=" . json_encode( $fallbackLoginUrl ) . ";"
				. "var state='drsso_'+Math.random().toString(36).slice(2)+Date.now().toString(36);"
				. "var nonce='drnonce_'+Math.random().toString(36).slice(2)+Date.now().toString(36);"
				. "var authUrl=new URL(authEndpoint);"
				. "authUrl.searchParams.set('client_id',clientId);"
				. "authUrl.searchParams.set('redirect_uri',callbackUrl);"
				. "authUrl.searchParams.set('response_type','code');"
				. "authUrl.searchParams.set('scope','openid');"
				. "authUrl.searchParams.set('prompt','none');"
				. "authUrl.searchParams.set('state',state);"
				. "authUrl.searchParams.set('nonce',nonce);"
				. "var iframe=document.createElement('iframe');"
				. "iframe.style.display='none';"
				. "iframe.setAttribute('aria-hidden','true');"
				. "var done=false;"
				. "var cleanup=function(){"
				. "if(done){return;}done=true;"
				. "window.removeEventListener('message',onMessage);"
				. "if(iframe&&iframe.parentNode){iframe.parentNode.removeChild(iframe);}"
				. "};"
				. "var onMessage=function(ev){"
				. "if(ev.origin!==window.location.origin){return;}"
				. "var data=ev.data||{};"
				. "if(data.type!=='dr-sso-silent-probe'){return;}"
				. "if(data.state!==state){return;}"
				. "if(data.status==='has_session'){"
				. "cleanup();"
				. "var loginAnchor=document.querySelector('#pt-login a');"
				. "var target=loginAnchor&&loginAnchor.href?loginAnchor.href:fallbackLoginUrl;"
				. "if(window.drShowSsoRedirectOverlay){window.drShowSsoRedirectOverlay();}"
				. "setTimeout(function(){window.location.replace(target);},40);"
				. "return;"
				. "}"
				. "cleanup();"
				. "};"
				. "window.addEventListener('message',onMessage);"
				. "setTimeout(cleanup,3000);"
				. "var appendIframe=function(){"
				. "iframe.src=authUrl.toString();"
				. "document.body.appendChild(iframe);"
				. "};"
				. "if(document.body){appendIframe();}else{document.addEventListener('DOMContentLoaded',appendIframe,{once:true});}"
				. "})();"
			);
		}
	}

	// Optionally auto-forward login page to SSO while keeping an emergency local-login bypass.
	if (
		!empty( $GLOBALS['wgDRSSOAutoRedirectLogin'] ) &&
		$title->isSpecial( 'Userlogin' ) &&
		$out->getUser()->isAnon()
	) {
		$bypassParam = $GLOBALS['wgDRSSOAutoRedirectBypassParam'] ?? 'local';
		$request = $out->getRequest();
		if ( !$request->getBool( $bypassParam ) ) {
			$issuerForHost = isset( $GLOBALS['wgDanceResourceKeycloakIssuer'] )
				? trim( (string)$GLOBALS['wgDanceResourceKeycloakIssuer'] )
				: '';
			$ssoHost = '';
			if ( $issuerForHost !== '' ) {
				$parsedHost = parse_url( $issuerForHost, PHP_URL_HOST );
				if ( is_string( $parsedHost ) ) {
					$ssoHost = $parsedHost;
				}
			}
			$out->addHeadItem(
				'dr-sso-login-hide-form',
				'<style id="dr-sso-login-hide-form">body.mw-special-Userlogin #userloginForm{display:none !important;}</style>'
			);
			$out->addInlineScript(
				"(function(){"
				. "var params=new URLSearchParams(window.location.search);"
				. "var unhide=function(){"
				. "var styleNode=document.getElementById('dr-sso-login-hide-form');"
				. "if(styleNode&&styleNode.parentNode){styleNode.parentNode.removeChild(styleNode);}"
				. "var overlay=document.getElementById('sso-redirect');"
				. "if(overlay){overlay.classList.remove('is-active');}"
				. "};"
				. "if(params.get('" . addslashes( $bypassParam ) . "')==='1'){unhide();return;}"
				. "var storage=null;"
				. "try{storage=window.sessionStorage;}catch(_e){}"
				. "var guardKey='dr-sso-login-guard-until';"
				. "var now=Date.now();"
				. "var guardUntil=0;"
				. "if(storage){guardUntil=parseInt(storage.getItem(guardKey)||'0',10)||0;}"
				. "var expectedSsoHost=" . json_encode( $ssoHost ) . ";"
				. "var refHost='';"
				. "try{refHost=(new URL(document.referrer||'')).hostname||'';}catch(_e){}"
				. "var fromSsoRef=(expectedSsoHost!==''&&refHost===expectedSsoHost);"
				. "if(guardUntil>now){"
				. "if(window.drShowSsoRedirectOverlay){window.drShowSsoRedirectOverlay();}"
				. "if(fromSsoRef){setTimeout(unhide,2500);return;}"
				. "unhide();"
				. "return;"
				. "}"
				. "var markGuard=function(ms){if(storage){storage.setItem(guardKey,String(Date.now()+ms));}};"
				. "var cooldownMs=12000;"
				. "var submitSso=function(){"
				. "var btn=document.querySelector('button[name=\"pluggableauthlogin0\"],input[name=\"pluggableauthlogin0\"]');"
				. "if(btn){"
				. "markGuard(cooldownMs);"
				. "if(document.activeElement&&document.activeElement.blur){document.activeElement.blur();}"
				. "if(window.drShowSsoRedirectOverlay){window.drShowSsoRedirectOverlay();}"
				. "btn.click();"
				. "setTimeout(function(){"
				. "var url=window.location.href||'';"
				. "var stillOnLogin=(url.indexOf('Special:UserLogin')!==-1)||(url.indexOf('Special:Userlogin')!==-1);"
				. "if(!stillOnLogin){"
				. "if(storage){storage.removeItem(guardKey);}"
				. "return;"
				. "}"
				. "unhide();"
				. "},3500);"
				. "return true;"
				. "}"
				. "return false;"
				. "};"
				. "var tries=0;"
				. "var run=function(){"
				. "tries++;"
				. "if(submitSso()){return;}"
				. "if(tries<80){setTimeout(run,50);return;}"
				. "unhide();"
				. "};"
				. "if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',run);}else{run();}"
				. "})();"
			);
		} else {
			// Bypass param is active (?local=1). Patch the local-login form so a POST
			// preserves the bypass param — otherwise the POST lands at /Special:UserLogin
			// (no bypass) and on re-render the autoredirect block above auto-clicks SSO,
			// making the emergency local fallback unusable.
			$out->addInlineScript(
				"(function(){"
				. "var bp=" . json_encode( $bypassParam ) . ";"
				. "var patchAction=function(){"
				. "var fs=document.querySelectorAll('form');"
				. "for(var i=0;i<fs.length;i++){var f=fs[i];"
				. "var hasLoginField=false;"
				. "for(var j=0;j<f.elements.length;j++){var n=f.elements[j].name;if(n==='wpName'||n==='wpLoginToken'){hasLoginField=true;break;}}"
				. "if(!hasLoginField){continue;}"
				. "var a=f.getAttribute('action')||'';"
				. "if(a.indexOf(bp+'=')!==-1){continue;}"
				. "f.setAttribute('action', a + (a.indexOf('?')===-1?'?':'&') + bp + '=1');"
				. "}"
				. "};"
				. "if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',patchAction,{once:true});}else{patchAction();}"
				. "})();"
			);
		}
	}

	$action = $out->getRequest()->getVal( 'action', 'view' );
	if ( $action !== 'view' ) {
		return true;
	}

	$hasTranslate = class_exists( \MediaWiki\Extension\Translate\PageTranslation\TranslatablePage::class ) &&
		class_exists( \MediaWiki\Extension\Translate\Utilities\Utilities::class ) &&
		class_exists( \MessageHandle::class );

	$baseTitle = $title;
	$currentLanguage = '';
	$sourceLanguage = '';
	$isMarkedTranslatable = false;

	if ( $hasTranslate && !$title->isSpecialPage() ) {
		$handle = new \MessageHandle( $title );
		if ( \MediaWiki\Extension\Translate\Utilities\Utilities::isTranslationPage( $handle ) ) {
			// SEO: noindex translation subpages — only the base (English) page should be indexed.
			// Added as a head item because ApprovedRevs' ParserBeforeInternalParse hook
			// resets the robot policy to index,follow during skin rendering.
			// Google combines directives from all robots meta tags; noindex wins.
			$out->addHeadItem( 'dr-noindex-translation',
				'<meta name="robots" content="noindex,follow"/>' );

			\MediaWiki\Extension\AiTranslationExtension\HookHandler::ensureTranslationStatusForTranslatedPage( $title );
			$baseTitle = $handle->getTitleForBase();
			if ( !$baseTitle ) {
				$baseTitle = $title;
			} else {
				$currentLanguage = $handle->getCode();
			}
		}

		$translatable = \MediaWiki\Extension\Translate\PageTranslation\TranslatablePage::newFromTitle( $baseTitle );
		if ( $translatable->getMarkedTag() !== null ) {
			$isMarkedTranslatable = true;
			$sourceLanguage = $translatable->getSourceLanguageCode();
			if ( $currentLanguage === '' ) {
				$currentLanguage = $sourceLanguage;
			}
		}
	}

	$suppressList = $GLOBALS['wgAiTranslationStatusBannerSuppressedPages'] ?? [];
	$bannerSuppressed = false;
	if ( $suppressList && $baseTitle ) {
		$baseDbKey = $baseTitle->getPrefixedDBkey();
		foreach ( $suppressList as $entry ) {
			if ( str_replace( ' ', '_', (string)$entry ) === $baseDbKey ) {
				$bannerSuppressed = true;
				break;
			}
		}
	}

	$out->addModuleStyles( [ 'ext.aitranslation.statusUi' ] );
	$out->addModules( [ 'ext.aitranslation.statusUi' ] );
	$out->addJsConfigVars( 'aiTranslationStatus', [
		'enabled' => true,
		'title' => $title->getPrefixedText(),
		'sourceTitle' => $isMarkedTranslatable ? $baseTitle->getPrefixedText() : '',
		'bannerSuppressed' => $bannerSuppressed,
	] );

	if ( empty( $GLOBALS['wgDRUnifiedLangSwitcherEnabled'] ) ) {
		return true;
	}
	if ( !$hasTranslate || $title->isSpecialPage() ) {
		return true;
	}

	$allowed = $GLOBALS['wgDRUnifiedLangSwitcherNamespaces'] ?? [ NS_MAIN ];
	if ( !in_array( $title->getNamespace(), $allowed, true ) ) {
		return true;
	}
	if ( !$isMarkedTranslatable ) {
		return true;
	}

	$out->addModuleStyles( [ 'ext.danceresource.common' ] );
	$out->addModuleStyles( [ 'ext.danceresource.unifiedLangSwitcher' ] );
	$out->addModules( [ 'ext.danceresource.common' ] );
	$out->addModules( [ 'ext.danceresource.unifiedLangSwitcher' ] );
	$out->addJsConfigVars( 'drUls', [
		'enabled' => true,
		'position' => $GLOBALS['wgDRUnifiedLangSwitcherPosition'] ?? 'sidebar',
		'fallbackBehavior' => $GLOBALS['wgDRUnifiedLangSwitcherFallbackBehavior'] ?? 'stay_and_notify',
		'preferAvailableOnly' => $GLOBALS['wgDRUnifiedLangSwitcherPreferAvailableOnly'] ?? true,
		'uiLanguageMode' => $GLOBALS['wgDRUnifiedLangSwitcherUILanguageMode'] ?? 'uls_cookie',
		'baseTitle' => $baseTitle->getPrefixedText(),
		'baseTitleDbKey' => $baseTitle->getPrefixedDBkey(),
		'currentLanguage' => $currentLanguage,
		'sourceLanguage' => $sourceLanguage,
		'namespaces' => $allowed
	] );

	return true;
};

$wgHooks['SkinAfterPortlet'][] = static function ( $skin, string $portlet, &$html ) {
	if ( empty( $GLOBALS['wgDRUnifiedLangSwitcherEnabled'] ) ) {
		return true;
	}

	$title = $skin->getOutput()->getTitle();
	if ( !$title || $title->isSpecialPage() ) {
		return true;
	}

	$allowed = $GLOBALS['wgDRUnifiedLangSwitcherNamespaces'] ?? [ NS_MAIN ];
	if ( !in_array( $title->getNamespace(), $allowed, true ) ) {
		return true;
	}

	$position = $GLOBALS['wgDRUnifiedLangSwitcherPosition'] ?? 'sidebar';
	$portletKey = strtolower( $portlet );
	if ( $position === 'sidebar' && !in_array( $portletKey, [ 'languages', 'lang' ], true ) ) {
		return true;
	}
	if ( $position === 'personal' && $portletKey !== 'personal' ) {
		return true;
	}
	if ( $position === 'header' ) {
		return true;
	}

	$html .= '<div class="dr-uls-container" data-dr-uls-position="' . htmlspecialchars( $position ) . '"></div>';
	return true;
};

// SEO: Open Graph, meta description, Twitter Card, JSON-LD, canonical URL support.
$wgHooks['BeforePageDisplay'][] = static function ( $out, $skin ) {
	$title = $out->getTitle();
	if ( !$title || !$title->isContentPage() ) {
		return true;
	}

	$action = $out->getRequest()->getVal( 'action', 'view' );
	if ( $action !== 'view' ) {
		return true;
	}

	$siteName = 'DanceResource Wiki';
	$canonicalUrl = $title->getCanonicalURL();
	$isMainPage = $title->isMainPage();
	$langCode = $title->getPageLanguage()->getCode();

	// Use the display title (respects DISPLAYTITLE set by translated pages) rather than the raw slug.
	$pageTitle = strip_tags( $out->getPageTitle() );
	if ( $pageTitle === '' ) {
		$pageTitle = $title->getText();
	}

	// Only use the English fallback description on English pages.
	// For translated pages without an explicit description property, omit the description tags.
	$description = (string)$out->getProperty( 'description' );
	if ( $description === '' && $langCode === 'en' ) {
		$description = "Explore $pageTitle on the DanceResource Wiki"
			. " — an open knowledge base on conscious dance, movement practices, and somatic traditions.";
	}
	if ( $description !== '' ) {
		$description = mb_substr( $description, 0, 160 );
	}

	$imageUrl = 'https://wiki.danceresource.org/images/9/99/Danceresource.org_logo.png';

	$ogType = $isMainPage ? 'website' : 'article';
	// og:locale uses underscores (e.g. sr_RS, en_US); BCP47 uses hyphens (e.g. sr-el)
	$ogLocale = str_replace( '-', '_', $langCode );

	$escapedTitle = htmlspecialchars( $pageTitle, ENT_QUOTES, 'UTF-8' );
	$escapedUrl = htmlspecialchars( $canonicalUrl, ENT_QUOTES, 'UTF-8' );
	$escapedImage = htmlspecialchars( $imageUrl, ENT_QUOTES, 'UTF-8' );
	$escapedSiteName = htmlspecialchars( $siteName, ENT_QUOTES, 'UTF-8' );
	$escapedOgType = htmlspecialchars( $ogType, ENT_QUOTES, 'UTF-8' );
	$escapedLocale = htmlspecialchars( $ogLocale, ENT_QUOTES, 'UTF-8' );

	$jsonLd = json_encode( [
		'@context' => 'https://schema.org',
		'@type' => 'Article',
		'headline' => $pageTitle,
		'url' => $canonicalUrl,
		'name' => $pageTitle,
		'isPartOf' => [
			'@type' => 'WebSite',
			'name' => $siteName,
			'url' => 'https://wiki.danceresource.org',
		],
		'inLanguage' => $langCode,
		'publisher' => [
			'@type' => 'Organization',
			'name' => 'DanceResource',
			'url' => 'https://danceresource.org',
		],
	], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	$html = '<meta property="og:locale" content="' . $escapedLocale . '"/>' . "\n"
		. '<meta property="og:type" content="' . $escapedOgType . '"/>' . "\n"
		. '<meta property="og:title" content="' . $escapedTitle . '"/>' . "\n"
		. '<meta property="og:url" content="' . $escapedUrl . '"/>' . "\n"
		. '<meta property="og:site_name" content="' . $escapedSiteName . '"/>' . "\n"
		. '<meta property="og:image" content="' . $escapedImage . '"/>' . "\n"
		. '<meta name="twitter:card" content="summary_large_image"/>' . "\n"
		. '<meta name="twitter:title" content="' . $escapedTitle . '"/>' . "\n";

	if ( $description !== '' ) {
		$escapedDesc = htmlspecialchars( $description, ENT_QUOTES, 'UTF-8' );
		$html .= '<meta name="description" content="' . $escapedDesc . '"/>' . "\n"
			. '<meta property="og:description" content="' . $escapedDesc . '"/>' . "\n"
			. '<meta name="twitter:description" content="' . $escapedDesc . '"/>' . "\n";
	}

	$html .= '<script type="application/ld+json">' . $jsonLd . '</script>';

	$out->addHeadItem( 'dr-seo-meta', $html );
	return true;
};

// GTM dataLayer: detect fresh login via session flag
$wgHooks['UserLoginComplete'][] = static function ( User &$user, string &$inject_html, bool $direct ) {
	RequestContext::getMain()->getRequest()->setSessionData( 'drJustLoggedIn', true );
};

// GTM dataLayer: pass page data to JS and load tracking module
$wgHooks['BeforePageDisplay'][] = static function ( OutputPage $out, Skin $skin ) {
	$request = $out->getRequest();
	$title   = $out->getTitle();
	$user    = $out->getUser();

	// Read and immediately clear the fresh-login flag (one-shot)
	$justLoggedIn = (bool) $request->getSessionData( 'drJustLoggedIn' );
	if ( $justLoggedIn ) {
		$request->setSessionData( 'drJustLoggedIn', null );
	}

	$out->addJsConfigVars( 'drGtm', [
		'userAuthenticated' => $user->isRegistered(),
		'userGroups'        => \MediaWiki\MediaWikiServices::getInstance()
			->getUserGroupManager()->getUserEffectiveGroups( $user ),
		'justLoggedIn'      => $justLoggedIn,
		'articleTitle'      => $title->getText(),
		'articleNamespace'  => $title->getNamespace(),
		'articleId'         => $title->getArticleID(),
		'isMainPage'        => $title->isMainPage(),
		'categories'        => array_keys( $out->getCategories() ),
		'pageName'          => $title->getPrefixedText(),
	] );

	$out->addModules( [ 'ext.danceresource.gtmTracking' ] );
};

$wgHooks['OutputPageBeforeHTML'][] = static function ( $out, &$text ) {
	if ( empty( $GLOBALS['wgDRUnifiedLangSwitcherEnabled'] ) ) {
		return true;
	}

	if ( ( $GLOBALS['wgDRUnifiedLangSwitcherPosition'] ?? 'sidebar' ) !== 'header' ) {
		return true;
	}

	$title = $out->getTitle();
	if ( !$title || $title->isSpecialPage() ) {
		return true;
	}

	$allowed = $GLOBALS['wgDRUnifiedLangSwitcherNamespaces'] ?? [ NS_MAIN ];
	if ( !in_array( $title->getNamespace(), $allowed, true ) ) {
		return true;
	}

	$text = '<div class="dr-uls-container" data-dr-uls-position="header"></div>' . $text;
	return true;
};

// Cross-site language sync via shared dr_locale cookie on .danceresource.org.
// Source of truth for language across www, wiki, events, sso. Must match the
// 35 realm locales configured in Keycloak.
$wgDRLocaleAllowed = [
	'ar', 'cs', 'da', 'de', 'el', 'en', 'es', 'fi', 'fr', 'he', 'hi', 'hr',
	'hu', 'id', 'is', 'it', 'ja', 'ka', 'ko', 'nl', 'no', 'pl', 'pt', 'ro',
	'ru', 'sk', 'sl', 'sr', 'sv', 'th', 'tr', 'uk', 'vi', 'zh', 'zu',
];

// Normalize Serbian/Chinese script variants to their base code — dr_locale
// and wikilanguage persist the base language only; variants like sr-el,
// sr-ec, zh-hans, zh-hant are UI-only and applied via ?uselang=.
$wgDRLocaleNormalize = static function ( $code ) {
	if ( $code === 'sr-el' || $code === 'sr-ec' || $code === 'sr-latn' || $code === 'sr-cyrl' ) {
		return 'sr';
	}
	if ( $code === 'zh-hans' || $code === 'zh-hant' || $code === 'zh-cn' || $code === 'zh-tw' || $code === 'zh-hk' ) {
		return 'zh';
	}
	return $code;
};

// Read dr_locale on page load and use it as the page language when valid.
// Also sync ULS's own `wikilanguage` cookie in-memory so ULS's own
// UserGetLanguageObject hook (which reads that cookie) doesn't overwrite us
// with a stale value when it runs after this one.
$wgHooks['UserGetLanguageObject'][] = static function ( $user, &$code, $context ) {
	if ( !isset( $_COOKIE['dr_locale'] ) ) {
		return true;
	}
	$candidate = $GLOBALS['wgDRLocaleNormalize']( $_COOKIE['dr_locale'] );
	if ( in_array( $candidate, $GLOBALS['wgDRLocaleAllowed'], true ) ) {
		$code = $candidate;
		$prefix = $GLOBALS['wgCookiePrefix'] ?? '';
		$_COOKIE[$prefix . 'language'] = $candidate;
	}
	return true;
};

// On /sr translation pages, force the UI to Serbian Latin (sr-el).
// Site convention: Serbian content is published only in Latin script, and the
// language switcher shows a single "Srpski" entry that links to ?uselang=sr-el.
// Without this hook, MW would resolve plain 'sr' to its default variant
// (Cyrillic via fallback to sr-ec), making chrome inconsistent with content.
// Runs after the dr_locale hook above so its $code is upgraded sr → sr-el;
// also syncs the in-memory `language` cookie so ULS's own later hook agrees.
$wgHooks['UserGetLanguageObject'][] = static function ( $user, &$code, $context ) {
	$title = $context->getTitle();
	if ( !$title || $title->getNamespace() !== NS_MAIN ) {
		return true;
	}
	if ( !str_ends_with( $title->getPrefixedDBkey(), '/sr' ) ) {
		return true;
	}
	$code = 'sr-el';
	$prefix = $GLOBALS['wgCookiePrefix'] ?? '';
	$_COOKIE[$prefix . 'language'] = 'sr-el';
	return true;
};

// No server-side dr_locale write hook: the cookie is only written in response
// to explicit user actions (click in the language switcher on any subdomain).
// Writing on page load previously clobbered the shared cookie when MW fell
// back to English for a locale it had no UI translation for (e.g. sr), which
// then propagated that clobber back to www/events/sso.

// Keep the article URL aligned with dr_locale in either direction:
//   /Title          + dr_locale=hr  -> /Title/hr   (when /Title/hr exists)
//   /Title/hr       + dr_locale=it  -> /Title/it   (when /Title/it exists)
//   /Title/hr       + dr_locale=en  -> /Title      (back to source)
// Skipped when ?uselang= is present (explicit per-request override), for
// non-view actions, outside NS_MAIN, or when the desired target doesn't
// exist (avoid 404 loops on missing translations).
$wgHooks['BeforeInitialize'][] = static function (
	&$title, &$article, &$output, &$user, $request, $mediaWiki
) {
	if ( $request->getRawVal( 'uselang' ) !== null ) {
		return;
	}
	if ( $request->getRawVal( 'action', 'view' ) !== 'view' ) {
		return;
	}
	if ( !$title || $title->getNamespace() !== NS_MAIN || !$title->exists() ) {
		return;
	}
	if ( !isset( $_COOKIE['dr_locale'] ) ) {
		return;
	}
	$desired = $GLOBALS['wgDRLocaleNormalize']( $_COOKIE['dr_locale'] );
	if ( !in_array( $desired, $GLOBALS['wgDRLocaleAllowed'], true ) ) {
		return;
	}

	// Decode current title: base article or /<lang> subpage?
	$text = $title->getText();
	$baseText = $text;
	$currentLang = 'en';
	if ( preg_match( '#^(.+)/([a-z]{2,3}(?:-[a-z0-9]{2,8})?)$#i', $text, $m ) ) {
		$suffix = $GLOBALS['wgDRLocaleNormalize']( strtolower( $m[2] ) );
		if ( in_array( $suffix, $GLOBALS['wgDRLocaleAllowed'], true ) ) {
			$baseText = $m[1];
			$currentLang = $suffix;
		}
	}

	if ( $currentLang === $desired ) {
		return;
	}

	$targetText = ( $desired === 'en' ) ? $baseText : ( $baseText . '/' . $desired );
	$target = Title::makeTitleSafe( NS_MAIN, $targetText );
	if ( !$target || !$target->exists() || $target->equals( $title ) ) {
		return;
	}

	$output->redirect( $target->getFullURL(), '302' );
};

// Cross-site theme sync via shared dr_theme cookie on .danceresource.org.
// The server never writes this cookie — the user's toggle in main.js does.
// We only inject a tiny blocking <head> script so the right theme is applied
// before the skin CSS paints, avoiding a white flash on dark-mode loads.
$wgDRThemeAllowed = [ 'light', 'dark' ];

$wgHooks['BeforePageDisplay'][] = static function ( $out, $skin ) {
	// Inline, runs before CSS. Mirrors the IIFE in main.js but executes
	// earlier (main.js is loaded via ResourceLoader later in <head>).
	$script = <<<'JS'
<script>(function(){try{
var m=document.cookie.match(/(?:^|; )dr_theme=([^;]*)/);
var v=m?decodeURIComponent(m[1]):null;
if(v!=='light'&&v!=='dark'){
 var l=null;try{l=localStorage.getItem('dr-theme');}catch(e){}
 if(l==='light'||l==='dark'){v=l;
  document.cookie='dr_theme='+v+'; Max-Age=31536000; Path=/; Domain=.danceresource.org; Secure; SameSite=Lax';
 }
}
if(v==='light'||v==='dark'){document.documentElement.setAttribute('data-theme',v);}
else if(!(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)){
 document.documentElement.setAttribute('data-theme','light');
}
}catch(e){}}());</script>
JS;
	$out->addHeadItem( 'dr-theme-fouc', $script );
};

$wgHooks['DifferenceEngineShowDiffPage'][] = static function ( $out ) {
	// VisualEditor's onTextSlotDiffRendererTablePrefix builds OOUI widgets while
	// assembling the diff but only enables OOUI in onDifferenceEngineViewHeader,
	// which core skips when the old revision is missing (e.g. the (prev) link on a
	// page's first revision). Enable OOUI for every diff so the theme singleton is
	// set before the table-prefix hook stringifies widgets. Fixes Theme.php:31 fatal.
	$out->enableOOUI();
};

// Pending-approval banner: when the viewer is the latest editor of a page whose
// current revision is unapproved (and they can't self-approve), show a notice so
// they don't think their edit failed. Without this, $egApprovedRevsBlankIfUnapproved
// silently renders a blank page for the editor — confusing for new contributors.
$wgHooks['BeforePageDisplay'][] = static function ( OutputPage $out, Skin $skin ) {
	if ( !class_exists( ApprovedRevs::class ) ) {
		return;
	}
	$title = $out->getTitle();
	$user  = $out->getUser();
	if ( !$title || !$user->isRegistered() ) {
		return;
	}
	$request = $out->getRequest();
	if ( $request->getVal( 'action', 'view' ) !== 'view' ) {
		return;
	}
	if ( !$title->exists() || $title->isSpecialPage() ) {
		return;
	}
	if ( !ApprovedRevs::pageIsApprovable( $title ) ) {
		return;
	}
	$approvedRevId = ApprovedRevs::getApprovedRevID( $title );
	$latestRevId   = $title->getLatestRevID();
	if ( $approvedRevId === (int)$latestRevId ) {
		return;
	}
	if ( ApprovedRevs::userCanApprove( $user, $title ) ) {
		return;
	}
	$latestRev = \MediaWiki\MediaWikiServices::getInstance()
		->getRevisionLookup()->getRevisionById( (int)$latestRevId );
	if ( !$latestRev ) {
		return;
	}
	$latestEditor = $latestRev->getUser();
	if ( !$latestEditor || $latestEditor->getId() !== $user->getId() ) {
		return;
	}
	$msg = $out->msg( 'aits-pending-approval-banner' )->parse();
	$out->prependHTML(
		'<div class="dr-pending-approval-banner cdx-message cdx-message--block cdx-message--notice" '
		. 'role="status" aria-live="polite" '
		. 'style="margin:0 0 1em 0;padding:12px 16px;border-left:4px solid #36c;background:#eaf3ff;'
		. 'color:#202122;border-radius:2px;font-size:0.95em;line-height:1.5;">'
		. $msg
		. '</div>'
	);
};
