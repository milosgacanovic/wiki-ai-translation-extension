<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\AiTranslationExtension;

use ContentHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use WikiPage;

class HookHandler {
	private const PROP_STATUS = 'ai_translation_status';
	private const PROP_SOURCE_REV = 'ai_translation_source_rev';
	private const PROP_REVIEWED_BY = 'ai_translation_reviewed_by';
	private const PROP_REVIEWED_AT = 'ai_translation_reviewed_at';
	private const PROP_OUTDATED_SOURCE_REV = 'ai_translation_outdated_source_rev';
	private const PROP_SOURCE_TITLE = 'ai_translation_source_title';
	private const PROP_SOURCE_LANG = 'ai_translation_source_lang';

	public static function onSkinBuildSidebar( $skin, &$bar ): bool {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		if ( !$config->get( 'AiTranslationExtensionLanguageSidebar' ) ) {
			return true;
		}

		$lang = $skin->getLanguage()->getCode();
		$candidates = [ $lang ];
		if ( strpos( $lang, '-' ) !== false ) {
			$candidates[] = explode( '-', $lang )[0];
		}

		$text = '';
		foreach ( $candidates as $code ) {
			$title = Title::newFromText( "MediaWiki:Sidebar/$code" );
			if ( $title && $title->exists() ) {
				$page = MediaWikiServices::getInstance()
					->getWikiPageFactory()
					->newFromTitle( $title );
				$content = $page->getContent();
				if ( $content ) {
					$candidate = ContentHandler::getContentText( $content );
					if ( trim( $candidate ) !== '' ) {
						$text = $candidate;
						break;
					}
				}
			}
		}

		if ( $text === '' ) {
			self::debugSidebar( $skin, $lang, $candidates, '', 'fallback' );
			return true;
		}

		$bar = [];
		$skin->addToSidebarPlain( $bar, $text );
		self::debugSidebar( $skin, $lang, $candidates, $text, 'custom' );
		return false;
	}

	private static function debugSidebar( $skin, string $lang, array $candidates, string $text, string $mode ): void {
		$request = $skin->getRequest();
		if ( !$request || $request->getVal( 'debugsidebar' ) !== '1' ) {
			return;
		}

		$len = strlen( trim( $text ) );
		$label = sprintf(
			'Sidebar lang=%s candidates=%s mode=%s textlen=%d',
			$lang,
			implode( ',', $candidates ),
			$mode,
			$len
		);

		$skin->getOutput()->addHTML(
			'<div class="dr-sidebar-debug" style="font-size:12px;color:#666;margin:4px 0;">' .
			htmlspecialchars( $label ) .
			'</div>'
		);
	}

	public static function onBeforePageDisplay( $out, $skin ): bool {
		$context = self::getUnifiedLangSwitcherContext( $out );
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
		$context = self::getUnifiedLangSwitcherContext( $out );
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

		$html .= self::getUnifiedLangSwitcherPlaceholder( $position );
		return true;
	}

	public static function onOutputPageBeforeHTML( $out, &$text ): bool {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$position = $config->get( 'DRUnifiedLangSwitcherPosition' );
		if ( $position !== 'header' ) {
			return true;
		}

		$context = self::getUnifiedLangSwitcherContext( $out );
		if ( $context === null ) {
			return true;
		}

		$text = self::getUnifiedLangSwitcherPlaceholder( $position ) . $text;
		return true;
	}

	private static function getUnifiedLangSwitcherContext( $out ): ?array {
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

	private static function getUnifiedLangSwitcherPlaceholder( string $position ): string {
		return '<div class="dr-uls-container" data-dr-uls-position="' .
			htmlspecialchars( $position ) . '"></div>';
	}

	public static function onPageSaveComplete(
		WikiPage $wikiPage,
		$user,
		$summary,
		$flags,
		$revisionRecord,
		$editResult
	): bool {
		$title = $wikiPage->getTitle();
		if ( !$title || !$title->exists() ) {
			return true;
		}

		// Unit-page save path from Special:Translate (Translations:* pages).
		if ( $title->inNamespace( NS_TRANSLATIONS ) ) {
			$handle = new \MessageHandle( $title );
			if ( $handle->isPageTranslation() ) {
				self::syncFromTranslationUnitSave( $wikiPage, $handle );
			}
			return true;
		}

		// Keep ai_* props scoped to translated pages only.
		if (
			!class_exists( \MediaWiki\Extension\Translate\Utilities\Utilities::class ) ||
			!\MediaWiki\Extension\Translate\Utilities\Utilities::isTranslationPage( new \MessageHandle( $title ) )
		) {
			return true;
		}

		$content = $wikiPage->getContent();
		if ( !$content ) {
			return true;
		}

		$text = ContentHandler::getContentText( $content );
		if ( !is_string( $text ) ) {
			return true;
		}

		$values = self::extractTranslationStatusProps( $text );
		if ( $values === [] ) {
			// Render/update saves of translated pages may not include template text.
			// Try reading the status template from the first translation unit page.
			$values = self::extractTranslationStatusFromFirstUnitPage( $title );
			if ( $values === [] ) {
				// Do not clear existing ai_* metadata when no explicit status is found.
				return true;
			}
		}
		self::persistTranslationStatusProps( (int)$title->getArticleID(), $values );
		return true;
	}

	private static function extractTranslationStatusFromFirstUnitPage( Title $translatedTitle ): array {
		$translatedHandle = new \MessageHandle( $translatedTitle );
		if ( !\MediaWiki\Extension\Translate\Utilities\Utilities::isTranslationPage( $translatedHandle ) ) {
			return [];
		}

		$base = $translatedHandle->getTitleForBase();
		$lang = $translatedHandle->getCode();
		if ( !$base || $lang === '' ) {
			return [];
		}

		$unitTitle = Title::makeTitle(
			NS_TRANSLATIONS,
			$base->getPrefixedDBkey() . '/1/' . $lang
		);
		if ( !$unitTitle || !$unitTitle->exists() ) {
			return [];
		}

		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $unitTitle );
		$content = $page->getContent();
		if ( !$content ) {
			return [];
		}

		$text = ContentHandler::getContentText( $content );
		if ( !is_string( $text ) || $text === '' ) {
			return [];
		}

		return self::extractTranslationStatusProps( $text );
	}

	private static function syncFromTranslationUnitSave( WikiPage $wikiPage, \MessageHandle $handle ): void {
		$languageCode = $handle->getCode();
		if ( $languageCode === '' ) {
			return;
		}

		$groupId = $handle->getGroupIds()[0] ?? '';
		if ( !str_starts_with( $groupId, 'page-' ) ) {
			return;
		}

		$sourceTitleText = substr( $groupId, 5 );
		$sourceTitle = Title::newFromText( $sourceTitleText );
		$translatedTitle = Title::newFromText( $sourceTitleText . '/' . $languageCode );
		if ( !$sourceTitle || !$translatedTitle || !$translatedTitle->exists() ) {
			return;
		}

		$pageId = (int)$translatedTitle->getArticleID();
		$existing = self::loadTranslationStatusProps( $pageId );

		$content = $wikiPage->getContent();
		$text = $content ? ContentHandler::getContentText( $content ) : '';
		$parsed = is_string( $text ) ? self::extractTranslationStatusProps( $text ) : [];

		$merged = $existing;
		foreach ( $parsed as $key => $value ) {
			$merged[$key] = $value;
		}

		if ( empty( $merged[self::PROP_STATUS] ) ) {
			$merged[self::PROP_STATUS] = 'machine';
		}
		if ( empty( $merged[self::PROP_SOURCE_REV] ) ) {
			$sourceRev = (int)$sourceTitle->getLatestRevID();
			if ( $sourceRev > 0 ) {
				$merged[self::PROP_SOURCE_REV] = (string)$sourceRev;
			}
		}
		if ( empty( $merged[self::PROP_SOURCE_TITLE] ) ) {
			$merged[self::PROP_SOURCE_TITLE] = $sourceTitle->getPrefixedText();
		}
		if ( empty( $merged[self::PROP_SOURCE_LANG] ) ) {
			$merged[self::PROP_SOURCE_LANG] = 'en';
		}

		self::persistTranslationStatusProps( $pageId, $merged );
	}

	private static function extractTranslationStatusProps( string $text ): array {
		$templateMatch = [];
		if ( !preg_match(
			'/\{\{\s*(?:Template\s*:\s*)?Translation(?:_| )status\b(.*?)\}\}/is',
			$text,
			$templateMatch
		) ) {
			return [];
		}

		$params = [];
		$raw = (string)( $templateMatch[1] ?? '' );
		foreach ( explode( '|', $raw ) as $part ) {
			$part = trim( $part );
			if ( $part === '' || strpos( $part, '=' ) === false ) {
				continue;
			}
			[ $key, $value ] = explode( '=', $part, 2 );
			$key = strtolower( trim( $key ) );
			$value = trim( $value );
			$params[$key] = $value;
		}

		$status = strtolower( $params['status'] ?? '' );
		if ( !in_array( $status, [ 'machine', 'reviewed', 'outdated' ], true ) ) {
			return [];
		}

		$values = [
			self::PROP_STATUS => $status,
		];

		$sourceRev = $params['source_rev_at_translation'] ?? ( $params['source_rev'] ?? '' );
		if ( $sourceRev !== '' && ctype_digit( $sourceRev ) ) {
			$values[self::PROP_SOURCE_REV] = $sourceRev;
		}
		if ( !empty( $params['reviewed_by'] ) ) {
			$values[self::PROP_REVIEWED_BY] = $params['reviewed_by'];
		}
		if (
			!empty( $params['reviewed_at'] ) &&
			preg_match( '/^\d{4}-\d{2}-\d{2}$/', $params['reviewed_at'] )
		) {
			$values[self::PROP_REVIEWED_AT] = $params['reviewed_at'];
		}
		if ( !empty( $params['outdated_source_rev'] ) && ctype_digit( $params['outdated_source_rev'] ) ) {
			$values[self::PROP_OUTDATED_SOURCE_REV] = $params['outdated_source_rev'];
		}
		if ( !empty( $params['source_title'] ) ) {
			$values[self::PROP_SOURCE_TITLE] = $params['source_title'];
		}
		if ( !empty( $params['source_lang'] ) ) {
			$values[self::PROP_SOURCE_LANG] = $params['source_lang'];
		}

		return $values;
	}

	private static function persistTranslationStatusProps( int $pageId, array $values ): void {
		if ( $pageId <= 0 ) {
			return;
		}

		$propNames = [
			self::PROP_STATUS,
			self::PROP_SOURCE_REV,
			self::PROP_REVIEWED_BY,
			self::PROP_REVIEWED_AT,
			self::PROP_OUTDATED_SOURCE_REV,
			self::PROP_SOURCE_TITLE,
			self::PROP_SOURCE_LANG,
		];

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		$dbw->startAtomic( __METHOD__ );
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'page_props' )
			->where( [
				'pp_page' => $pageId,
				'pp_propname' => $propNames,
			] )
			->caller( __METHOD__ )
			->execute();

		if ( $values !== [] ) {
			$rows = [];
			foreach ( $values as $name => $value ) {
				$rows[] = [
					'pp_page' => $pageId,
					'pp_propname' => $name,
					'pp_value' => (string)$value,
					'pp_sortkey' => is_numeric( $value ) ? (float)$value : null,
				];
			}
			$dbw->newInsertQueryBuilder()
				->insertInto( 'page_props' )
				->rows( $rows )
				->caller( __METHOD__ )
				->execute();
		}

		$dbw->endAtomic( __METHOD__ );
	}

	public static function onTranslateNewTranslation(
		\MessageHandle $handle,
		int $revisionId,
		string $text,
		$user
	): bool {
		if ( !$handle->isPageTranslation() ) {
			return true;
		}

		$languageCode = $handle->getCode();
		if ( $languageCode === '' ) {
			return true;
		}

		$groupId = $handle->getGroupIds()[0] ?? '';
		if ( !str_starts_with( $groupId, 'page-' ) ) {
			return true;
		}

		$sourceTitleText = substr( $groupId, 5 );
		$sourceTitle = Title::newFromText( $sourceTitleText );
		$translatedTitle = Title::newFromText( $sourceTitleText . '/' . $languageCode );
		if ( !$sourceTitle || !$translatedTitle || !$translatedTitle->exists() ) {
			return true;
		}

		$pageId = (int)$translatedTitle->getArticleID();
		$existing = self::loadTranslationStatusProps( $pageId );

		// Preserve reviewed/outdated; only initialize missing metadata.
		if ( empty( $existing[self::PROP_STATUS] ) ) {
			$existing[self::PROP_STATUS] = 'machine';
		}
		if ( empty( $existing[self::PROP_SOURCE_REV] ) ) {
			$sourceRev = (int)$sourceTitle->getLatestRevID();
			if ( $sourceRev > 0 ) {
				$existing[self::PROP_SOURCE_REV] = (string)$sourceRev;
			}
		}
		if ( empty( $existing[self::PROP_SOURCE_TITLE] ) ) {
			$existing[self::PROP_SOURCE_TITLE] = $sourceTitle->getPrefixedText();
		}
		if ( empty( $existing[self::PROP_SOURCE_LANG] ) ) {
			$existing[self::PROP_SOURCE_LANG] = 'en';
		}

		self::persistTranslationStatusProps( $pageId, $existing );
		return true;
	}

	private static function loadTranslationStatusProps( int $pageId ): array {
		if ( $pageId <= 0 ) {
			return [];
		}

		$propNames = [
			self::PROP_STATUS,
			self::PROP_SOURCE_REV,
			self::PROP_REVIEWED_BY,
			self::PROP_REVIEWED_AT,
			self::PROP_OUTDATED_SOURCE_REV,
			self::PROP_SOURCE_TITLE,
			self::PROP_SOURCE_LANG,
		];

		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'pp_propname', 'pp_value' ] )
			->from( 'page_props' )
			->where( [
				'pp_page' => $pageId,
				'pp_propname' => $propNames,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$out = [];
		foreach ( $res as $row ) {
			$out[(string)$row->pp_propname] = (string)$row->pp_value;
		}
		return $out;
	}

	public static function ensureTranslationStatusForTranslatedPage( Title $translatedTitle ): void {
		$translatedHandle = new \MessageHandle( $translatedTitle );
		if (
			!class_exists( \MediaWiki\Extension\Translate\Utilities\Utilities::class ) ||
			!\MediaWiki\Extension\Translate\Utilities\Utilities::isTranslationPage( $translatedHandle )
		) {
			return;
		}

		$pageId = (int)$translatedTitle->getArticleID();
		if ( $pageId <= 0 ) {
			return;
		}

		$existing = self::loadTranslationStatusProps( $pageId );
		if ( !empty( $existing[self::PROP_STATUS] ) ) {
			return;
		}

		$base = $translatedHandle->getTitleForBase();
		$lang = $translatedHandle->getCode();
		if ( !$base || $lang === '' ) {
			return;
		}

		$values = self::extractTranslationStatusFromFirstUnitPage( $translatedTitle );
		if ( $values === [] ) {
			$values[self::PROP_STATUS] = 'machine';
		}

		if ( empty( $values[self::PROP_SOURCE_REV] ) ) {
			$sourceRev = (int)$base->getLatestRevID();
			if ( $sourceRev > 0 ) {
				$values[self::PROP_SOURCE_REV] = (string)$sourceRev;
			}
		}
		if ( empty( $values[self::PROP_SOURCE_TITLE] ) ) {
			$values[self::PROP_SOURCE_TITLE] = $base->getPrefixedText();
		}
		if ( empty( $values[self::PROP_SOURCE_LANG] ) ) {
			$values[self::PROP_SOURCE_LANG] = 'en';
		}

		self::persistTranslationStatusProps( $pageId, $values );
	}
}
