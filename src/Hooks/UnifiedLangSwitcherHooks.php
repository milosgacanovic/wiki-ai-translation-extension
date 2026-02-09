<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\AiTranslationExtension\Hooks;

use MediaWiki\Extension\Translate\PageTranslation\TranslatablePage;
use MediaWiki\Extension\Translate\Utilities\Utilities;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class UnifiedLangSwitcherHooks {
	public static function onBeforePageDisplay( $out, $skin ): bool {
		$context = self::getContext( $out );
		if ( $context === null ) {
			return true;
		}

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$out->addModules( [ 'ext.danceresource.unifiedLangSwitcher' ] );
		$out->addJsConfigVars( 'drUls', [
			'enabled' => true,
			'position' => $config->get( 'DRUnifiedLangSwitcherPosition' ),
			'fallbackBehavior' => $config->get( 'DRUnifiedLangSwitcherFallbackBehavior' ),
			'preferAvailableOnly' => $config->get( 'DRUnifiedLangSwitcherPreferAvailableOnly' ),
			'uiLanguageMode' => $config->get( 'DRUnifiedLangSwitcherUILanguageMode' ),
			'baseTitle' => $context['baseTitle'],
			'baseTitleDbKey' => $context['baseTitleDbKey'],
			'currentLanguage' => $context['currentLanguage'],
			'sourceLanguage' => $context['sourceLanguage'],
			'namespaces' => $config->get( 'DRUnifiedLangSwitcherNamespaces' )
		] );

		return true;
	}

	public static function onSkinAfterPortlet( $skin, string $portlet, &$html ): bool {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$position = $config->get( 'DRUnifiedLangSwitcherPosition' );
		if ( $position !== 'sidebar' && $position !== 'personal' ) {
			return true;
		}

		$out = $skin->getOutput();
		$context = self::getContext( $out );
		if ( $context === null ) {
			return true;
		}

		$portletKey = strtolower( $portlet );
		if ( $position === 'sidebar' && !in_array( $portletKey, [ 'languages', 'lang' ], true ) ) {
			return true;
		}
		if ( $position === 'personal' && $portletKey !== 'personal' ) {
			return true;
		}

		$html .= self::getPlaceholder( $position );
		return true;
	}

	public static function onOutputPageBeforeHTML( $out, &$text ): bool {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$position = $config->get( 'DRUnifiedLangSwitcherPosition' );
		if ( $position !== 'header' ) {
			return true;
		}

		$context = self::getContext( $out );
		if ( $context === null ) {
			return true;
		}

		$text = self::getPlaceholder( $position ) . $text;
		return true;
	}

	private static function getContext( $out ): ?array {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		if ( !$config->get( 'DRUnifiedLangSwitcherEnabled' ) ) {
			return null;
		}

		$title = $out->getTitle();
		if ( !$title ) {
			return null;
		}

		if ( $title->isSpecialPage() ) {
			return null;
		}

		$action = $out->getRequest()->getVal( 'action', 'view' );
		if ( $action !== 'view' ) {
			return null;
		}

		$allowedNamespaces = $config->get( 'DRUnifiedLangSwitcherNamespaces' );
		if ( !in_array( $title->getNamespace(), $allowedNamespaces, true ) ) {
			return null;
		}

		if ( !class_exists( TranslatablePage::class ) ) {
			return null;
		}

		$handle = new \MessageHandle( $title );
		$baseTitle = $title;
		$currentLanguage = '';
		if ( Utilities::isTranslationPage( $handle ) ) {
			$baseTitle = $handle->getTitleForBase();
			if ( !$baseTitle instanceof Title ) {
				return null;
			}
			$currentLanguage = $handle->getCode();
		}

		$translatable = TranslatablePage::newFromTitle( $baseTitle );
		if ( $translatable->getMarkedTag() === null ) {
			return null;
		}

		$sourceLanguage = $translatable->getSourceLanguageCode();
		if ( $currentLanguage === '' ) {
			$currentLanguage = $sourceLanguage;
		}

		return [
			'baseTitle' => $baseTitle->getPrefixedText(),
			'baseTitleDbKey' => $baseTitle->getPrefixedDBkey(),
			'currentLanguage' => $currentLanguage,
			'sourceLanguage' => $sourceLanguage
		];
	}

	private static function getPlaceholder( string $position ): string {
		return '<div class="dr-uls-container" data-dr-uls-position="' .
			htmlspecialchars( $position ) . '"></div>';
	}
}
