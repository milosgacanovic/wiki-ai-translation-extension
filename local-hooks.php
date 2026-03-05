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

// Unified language switcher hooks (registered locally to avoid autoload conflicts).
$wgHooks['BeforePageDisplay'][] = static function ( $out, $skin ) {
	$title = $out->getTitle();
	if ( !$title ) {
		return true;
	}

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

	$out->addModuleStyles( [ 'ext.aitranslation.statusUi' ] );
	$out->addModules( [ 'ext.aitranslation.statusUi' ] );
	$out->addJsConfigVars( 'aiTranslationStatus', [
		'enabled' => true,
		'title' => $title->getPrefixedText(),
		'sourceTitle' => $isMarkedTranslatable ? $baseTitle->getPrefixedText() : '',
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
