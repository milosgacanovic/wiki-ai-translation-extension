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
				. "var storageKey='dr_sso_silent_probe_v1';"
				. "if(window.sessionStorage&&sessionStorage.getItem(storageKey)==='done'){return;}"
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
				. "if(window.sessionStorage){sessionStorage.setItem(storageKey,'done');}"
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
				. "window.location.replace(target);"
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
			$out->addHeadItem(
				'dr-sso-redirect-style',
				'<style id="dr-sso-redirect-style">'
				. 'body.mw-special-Userlogin #userloginForm{visibility:hidden;}'
				. '#dr-sso-redirect-note{display:block;max-width:38rem;margin:0 0 1rem 0;}'
				. '</style>'
			);
			$out->addInlineScript(
				"(function(){"
				. "var showNotice=function(){"
				. "if(document.getElementById('dr-sso-redirect-note')){return;}"
				. "var formWrap=document.getElementById('userloginForm');"
				. "if(!formWrap||!formWrap.parentNode){return;}"
				. "var note=document.createElement('div');"
				. "note.id='dr-sso-redirect-note';"
				. "note.className='mw-message-box cdx-message cdx-message--block cdx-message--notice';"
				. "note.innerHTML='<span class=\"cdx-message__icon\"></span><div class=\"cdx-message__content\">Redirecting to DanceResource SSO...</div>';"
				. "formWrap.parentNode.insertBefore(note,formWrap);"
				. "};"
				. "var submitSso=function(){"
				. "if(new URLSearchParams(window.location.search).get('" . addslashes( $bypassParam ) . "')==='1'){return;}"
				. "var btn=document.querySelector('button[name=\"pluggableauthlogin0\"],input[name=\"pluggableauthlogin0\"]');"
				. "if(btn){btn.click();return true;}"
				. "return false;"
				. "};"
				. "var run=function(){showNotice();if(!submitSso()){setTimeout(run,50);}};"
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
