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

// Unified language switcher hooks (registered locally to avoid autoload conflicts).
$wgHooks['BeforePageDisplay'][] = static function ( $out, $skin ) {
	if ( empty( $GLOBALS['wgDRUnifiedLangSwitcherEnabled'] ) ) {
		return true;
	}

	if ( !class_exists( \MediaWiki\Extension\Translate\PageTranslation\TranslatablePage::class ) ) {
		return true;
	}

	$title = $out->getTitle();
	if ( !$title || $title->isSpecialPage() ) {
		return true;
	}

	$action = $out->getRequest()->getVal( 'action', 'view' );
	if ( $action !== 'view' ) {
		return true;
	}

	$allowed = $GLOBALS['wgDRUnifiedLangSwitcherNamespaces'] ?? [ NS_MAIN ];
	if ( !in_array( $title->getNamespace(), $allowed, true ) ) {
		return true;
	}

	$handle = new \MessageHandle( $title );
	$baseTitle = $title;
	$currentLanguage = '';
	$isTranslationPage = false;
	if ( \MediaWiki\Extension\Translate\Utilities\Utilities::isTranslationPage( $handle ) ) {
		$isTranslationPage = true;
		\MediaWiki\Extension\AiTranslationExtension\HookHandler::ensureTranslationStatusForTranslatedPage( $title );
		$baseTitle = $handle->getTitleForBase();
		if ( !$baseTitle ) {
			return true;
		}
		$currentLanguage = $handle->getCode();
	}

	$translatable = \MediaWiki\Extension\Translate\PageTranslation\TranslatablePage::newFromTitle( $baseTitle );
	if ( $translatable->getMarkedTag() === null ) {
		return true;
	}

	$sourceLanguage = $translatable->getSourceLanguageCode();
	if ( $currentLanguage === '' ) {
		$currentLanguage = $sourceLanguage;
	}

	$out->addModuleStyles( [ 'ext.danceresource.common' ] );
	$out->addModuleStyles( [ 'ext.danceresource.unifiedLangSwitcher' ] );
	$out->addModules( [ 'ext.danceresource.common' ] );
	$out->addModules( [ 'ext.danceresource.unifiedLangSwitcher' ] );
	$out->addModuleStyles( [ 'ext.aitranslation.statusUi' ] );
	$out->addModules( [ 'ext.aitranslation.statusUi' ] );
	$out->addJsConfigVars( 'aiTranslationStatus', [
		'enabled' => true,
		'title' => $title->getPrefixedText(),
		'sourceTitle' => $baseTitle->getPrefixedText(),
	] );
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
