<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\AiTranslationExtension;

use ContentHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use SearchEngine;
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

	public static function onSearchDataForIndex2(
		array &$fields,
		ContentHandler $handler,
		WikiPage $page,
		ParserOutput $output,
		SearchEngine $engine,
		$revision
	): bool {
		$displayTitleHtml = $output->getDisplayTitle();
		if ( !$displayTitleHtml || !is_string( $displayTitleHtml ) ) {
			return true;
		}

		$displayTitle = html_entity_decode( strip_tags( $displayTitleHtml ), ENT_QUOTES | ENT_HTML5 );
		$displayTitle = preg_replace( '/\s+/u', ' ', trim( $displayTitle ) );
		if ( !$displayTitle ) {
			return true;
		}

		$existingText = (string)( $fields['text'] ?? '' );
		if ( $existingText === '' ) {
			return true;
		}

		// Boost search recall for translated/display titles by indexing plain display title text.
		if ( stripos( $existingText, $displayTitle ) === false ) {
			$fields['text'] = $existingText . "\n" . $displayTitle;
		}
		if ( isset( $fields['source_text'] ) && is_string( $fields['source_text'] ) &&
			stripos( $fields['source_text'], $displayTitle ) === false
		) {
			$fields['source_text'] .= "\n" . $displayTitle;
		}

		return true;
	}

	// ── Search: snippet cleanup + language-scoped filtering ──────────

	/**
	 * Get the language suffix for search filtering based on the current UI language.
	 * Returns '' for English (base pages), or the language code for translations.
	 * Returns null if filtering should be skipped.
	 */
	public static function getSearchLanguageSuffix( $context ): ?string {
		if ( method_exists( $context, 'getContext' ) ) {
			$context = $context->getContext();
		}
		if ( $context->getRequest()->getVal( 'searchlang' ) === 'all' ) {
			return null;
		}
		$code = $context->getLanguage()->getCode();
		// Normalize variant codes: sr-el -> sr, sr-ec -> sr, zh-hans -> zh
		$parts = explode( '-', $code );
		return $parts[0];
	}

	/**
	 * Check whether a page title matches the desired language scope.
	 */
	public static function titleMatchesLanguage( string $titleText, string $langSuffix ): bool {
		if ( $langSuffix === 'en' ) {
			// English: show pages WITHOUT a language suffix (base pages)
			return !preg_match( '#/[a-z]{2}(-[a-z]+)?$#', $titleText );
		}
		// Non-English: show only pages ending in /$langSuffix
		return (bool)preg_match(
			'#/' . preg_quote( $langSuffix, '#' ) . '$#',
			$titleText
		);
	}

	/**
	 * Strip residual wikitext markup from a search snippet.
	 */
	private static function cleanSearchExtract( string $extract ): string {
		if ( $extract === '' ) {
			return '';
		}
		// Strip {{template|...}} calls (including nested single-level)
		$cleaned = preg_replace( '/\{\{[^{}]*\}\}/', '', $extract );
		// Second pass for any that were nested
		$cleaned = preg_replace( '/\{\{[^{}]*\}\}/', '', $cleaned );
		// Strip [[File:...|...]] and [[Image:...|...]]
		$cleaned = preg_replace( '/\[\[(File|Image):[^\]]*\]\]/i', '', $cleaned );
		// Convert [[link|display]] -> display, [[link]] -> link
		$cleaned = preg_replace( '/\[\[(?:[^|\]]*\|)?([^\]]*)\]\]/', '$1', $cleaned );
		// Strip bold/italic markers
		$cleaned = preg_replace( "/'{2,5}/", '', $cleaned );
		// Strip <ref>...</ref> tags
		$cleaned = preg_replace( '/<ref[^>]*>.*?<\/ref>/si', '', $cleaned );
		$cleaned = preg_replace( '/<ref[^>]*\/?>/i', '', $cleaned );
		// Collapse whitespace
		$cleaned = preg_replace( '/\s{2,}/', ' ', trim( $cleaned ) );
		return $cleaned;
	}

	/**
	 * ShowSearchHit: clean snippets + filter by language.
	 */
	public static function onShowSearchHit(
		$searchPage, $result, $terms, &$link, &$redirect,
		&$section, &$extract, &$score, &$size, &$date, &$related, &$html
	): bool {
		// Skip language filtering on the "translation" search profile (Translate extension)
		$profile = $searchPage->getRequest()->getVal( 'profile', '' );
		if ( $profile === 'translation' ) {
			// Still clean snippets
			$extract = self::cleanSearchExtract( $extract );
			return true;
		}

		// Language filtering
		$langSuffix = self::getSearchLanguageSuffix( $searchPage );
		if ( $langSuffix !== null ) {
			$title = $result->getTitle();
			if ( $title && !self::titleMatchesLanguage( $title->getText(), $langSuffix ) ) {
				$html = '';
				return false;
			}
		}

		// Clean wikitext from snippet
		$extract = self::cleanSearchExtract( $extract );
		return true;
	}

	/**
	 * SpecialSearchSetupEngine: increase limit to compensate for language post-filtering.
	 */
	public static function onSpecialSearchSetupEngine( $search, $profile, $engine ): void {
		if ( $profile === 'translation' ) {
			return;
		}
		$langSuffix = self::getSearchLanguageSuffix( $search );
		if ( $langSuffix !== null && $langSuffix !== 'en' ) {
			// Non-English: many results will be filtered out, so fetch more.
			// SearchEngine has no getLimit() — use a generous fixed limit.
			$engine->setLimitOffset( 200, 0 );
		}
	}

	/**
	 * ApiOpenSearchSuggest: filter autocomplete suggestions by language and
	 * supplement with substring matches so that e.g. "Inner" finds
	 * "Conscious Dance Practices/InnerMotion".
	 */
	public static function onApiOpenSearchSuggest( array &$results ): void {
		$context = \RequestContext::getMain();
		$langSuffix = self::getSearchLanguageSuffix( $context );

		if ( $langSuffix === null ) {
			return;
		}

		$search = $context->getRequest()->getVal( 'search', '' );
		if ( $search === '' ) {
			return;
		}

		$limit = 10;

		// 1. Start with prefix-based completion results (language-filtered).
		$services = MediaWikiServices::getInstance();
		$searchEngine = $services->newSearchEngine();
		$searchEngine->setNamespaces( [ NS_MAIN ] );
		$searchEngine->setLimitOffset( 100, 0 );
		$suggestions = $searchEngine->completionSearchWithVariants( $search );
		$prefixTitles = $searchEngine->extractTitles( $suggestions );

		$seen = [];
		$filtered = [];
		foreach ( $prefixTitles as $title ) {
			if ( self::titleMatchesLanguage( $title->getText(), $langSuffix ) ) {
				$dbKey = $title->getPrefixedDBkey();
				if ( isset( $seen[$dbKey] ) ) {
					continue;
				}
				$seen[$dbKey] = true;
				$filtered[] = self::makeOpenSearchEntry( $title );
				if ( count( $filtered ) >= $limit ) {
					break;
				}
			}
		}

		// 2. If we still have room, find pages where a subpage segment matches.
		//    e.g. "Inner" matches "Conscious_Dance_Practices/InnerMotion".
		if ( count( $filtered ) < $limit ) {
			$substringTitles = self::substringTitleSearch(
				$search, $langSuffix, $limit * 3
			);
			foreach ( $substringTitles as $title ) {
				$dbKey = $title->getPrefixedDBkey();
				if ( isset( $seen[$dbKey] ) ) {
					continue;
				}
				$seen[$dbKey] = true;
				$filtered[] = self::makeOpenSearchEntry( $title );
				if ( count( $filtered ) >= $limit ) {
					break;
				}
			}
		}

		// 3. Search display titles (translated page names) for the term.
		if ( count( $filtered ) < $limit ) {
			$dtTitles = self::displayTitleSearch(
				$search, $langSuffix, $limit * 3
			);
			foreach ( $dtTitles as $title ) {
				$dbKey = $title->getPrefixedDBkey();
				if ( isset( $seen[$dbKey] ) ) {
					continue;
				}
				$seen[$dbKey] = true;
				$filtered[] = self::makeOpenSearchEntry( $title );
				if ( count( $filtered ) >= $limit ) {
					break;
				}
			}
		}

		// 4. Cross-language fallback: if we still have zero suggestions,
		//    surface prefix matches AND display-title matches from other
		//    languages so the user can discover translated pages by their
		//    localized name (e.g. English user typing "Uvod" gets the
		//    Serbian intro page).
		if ( $filtered === [] ) {
			foreach ( $prefixTitles as $title ) {
				$dbKey = $title->getPrefixedDBkey();
				if ( isset( $seen[$dbKey] ) ) {
					continue;
				}
				$seen[$dbKey] = true;
				$filtered[] = self::makeOpenSearchEntry( $title );
				if ( count( $filtered ) >= $limit ) {
					break;
				}
			}
			if ( count( $filtered ) < $limit ) {
				foreach ( self::displayTitleSearchAny( $search, $limit * 3 ) as $title ) {
					$dbKey = $title->getPrefixedDBkey();
					if ( isset( $seen[$dbKey] ) ) {
						continue;
					}
					$seen[$dbKey] = true;
					$filtered[] = self::makeOpenSearchEntry( $title );
					if ( count( $filtered ) >= $limit ) {
						break;
					}
				}
			}
		}

		$results = $filtered;
	}

	/**
	 * Build an opensearch result entry from a Title.
	 */
	private static function makeOpenSearchEntry( \Title $title ): array {
		return [
			'title' => $title,
			'redirect from' => null,
			'extract' => false,
			'extract trimmed' => false,
			'image' => false,
			'url' => $title->getFullURL(),
		];
	}

	/**
	 * Find pages whose title contains the search term as a substring,
	 * already filtered by language suffix.  This catches subpage-segment
	 * matches that prefix search misses (e.g. "Inner" → ".../InnerMotion").
	 */
	private static function substringTitleSearch(
		string $search, string $langSuffix, int $limit
	): array {
		$dbr = MediaWikiServices::getInstance()
			->getConnectionProvider()->getReplicaDatabase();

		$conditions = [
			'page_namespace' => NS_MAIN,
			'page_is_redirect' => 0,
		];

		// Match title containing the search term anywhere.
		// DB uses binary charset, so LIKE is case-sensitive and LOWER() is a no-op.
		// CONVERT to utf8mb4 gives case-insensitive LIKE via utf8mb4_general_ci.
		$titleExpr = 'CONVERT(page_title USING utf8mb4)';
		$searchLower = str_replace( ' ', '_', mb_strtolower( $search ) );
		$like = $dbr->buildLike(
			$dbr->anyString(), $searchLower, $dbr->anyString()
		);
		$conditions[] = $titleExpr . $like;

		// Language filter: exclude translated subpages for English,
		// require the suffix for others.
		if ( $langSuffix === 'en' ) {
			// Exclude pages ending in /xx (2-3 letter lang code) or /xx-yy variant.
			// Use anyChar() for _ wildcards — buildLike escapes literal _ chars.
			$ac = $dbr->anyChar();
			$notLike2 = $dbr->buildLike( $dbr->anyString(), '/', $ac, $ac );
			$notLike3 = $dbr->buildLike( $dbr->anyString(), '/', $ac, $ac, $ac );
			$notLike5 = $dbr->buildLike( $dbr->anyString(), '/', $ac, $ac, '-', $ac, $ac );
			$notLike6 = $dbr->buildLike( $dbr->anyString(), '/', $ac, $ac, '-', $ac, $ac, $ac );
			$conditions[] = $titleExpr . ' NOT' . $notLike2;
			$conditions[] = $titleExpr . ' NOT' . $notLike3;
			$conditions[] = $titleExpr . ' NOT' . $notLike5;
			$conditions[] = $titleExpr . ' NOT' . $notLike6;
		} else {
			$langLike = $dbr->buildLike(
				$dbr->anyString(), '/' . $langSuffix
			);
			$conditions[] = 'page_title' . $langLike;
		}

		// Prefer shorter titles (closer matches) over longer ones.
		$rows = $dbr->newSelectQueryBuilder()
			->select( [ 'page_namespace', 'page_title' ] )
			->from( 'page' )
			->where( $conditions )
			->limit( $limit )
			->orderBy( 'LENGTH(page_title)', 'ASC' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$titles = [];
		foreach ( $rows as $row ) {
			$title = Title::makeTitle( (int)$row->page_namespace, $row->page_title );
			if ( $title && self::titleMatchesLanguage( $title->getText(), $langSuffix ) ) {
				$titles[] = $title;
			}
		}
		return $titles;
	}

	/**
	 * Public wrapper for substringTitleSearch (used by ApiDRSearch).
	 * @return Title[]
	 */
	public static function substringTitleSearchPublic(
		string $search, string $langSuffix, int $limit
	): array {
		return self::substringTitleSearch( $search, $langSuffix, $limit );
	}

	/**
	 * Find pages whose display title (page_props.displaytitle) contains
	 * the search term.  This lets users search in their language for
	 * translated pages (e.g. "rezonanca" → Heart Resonance/sr).
	 *
	 * @return Title[]
	 */
	private static function displayTitleSearch(
		string $search, string $langSuffix, int $limit
	): array {
		$dbr = MediaWikiServices::getInstance()
			->getConnectionProvider()->getReplicaDatabase();

		$searchLower = mb_strtolower( $search );
		$valueLike = $dbr->buildLike(
			$dbr->anyString(), $searchLower, $dbr->anyString()
		);

		// pp_value is stored in binary charset, so LOWER() is a no-op.
		// CONVERT to utf8mb4 gives case-insensitive LIKE matching.
		$conditions = [
			'pp_propname' => 'displaytitle',
			'page_namespace' => NS_MAIN,
			'page_is_redirect' => 0,
			'CONVERT(pp_value USING utf8mb4)' . $valueLike,
		];

		// Language filter on the page title (not the display title).
		if ( $langSuffix === 'en' ) {
			$ac = $dbr->anyChar();
			$notLike2 = $dbr->buildLike( $dbr->anyString(), '/', $ac, $ac );
			$notLike3 = $dbr->buildLike( $dbr->anyString(), '/', $ac, $ac, $ac );
			$notLike5 = $dbr->buildLike( $dbr->anyString(), '/', $ac, $ac, '-', $ac, $ac );
			$notLike6 = $dbr->buildLike( $dbr->anyString(), '/', $ac, $ac, '-', $ac, $ac, $ac );
			$titleExpr = 'CONVERT(page_title USING utf8mb4)';
			$conditions[] = $titleExpr . ' NOT' . $notLike2;
			$conditions[] = $titleExpr . ' NOT' . $notLike3;
			$conditions[] = $titleExpr . ' NOT' . $notLike5;
			$conditions[] = $titleExpr . ' NOT' . $notLike6;
		} else {
			$langLike = $dbr->buildLike(
				$dbr->anyString(), '/' . $langSuffix
			);
			$conditions[] = 'page_title' . $langLike;
		}

		$rows = $dbr->newSelectQueryBuilder()
			->select( [ 'page_namespace', 'page_title' ] )
			->from( 'page_props' )
			->join( 'page', null, 'pp_page = page_id' )
			->where( $conditions )
			->limit( $limit )
			->orderBy( 'LENGTH(page_title)', 'ASC' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$titles = [];
		foreach ( $rows as $row ) {
			$title = Title::makeTitle( (int)$row->page_namespace, $row->page_title );
			if ( $title && self::titleMatchesLanguage( $title->getText(), $langSuffix ) ) {
				$titles[] = $title;
			}
		}
		return $titles;
	}

	/**
	 * displayTitleSearch variant that ignores the language suffix — used for
	 * cross-language fallback when the in-language autocomplete would be empty.
	 *
	 * @return Title[]
	 */
	private static function displayTitleSearchAny( string $search, int $limit ): array {
		$dbr = MediaWikiServices::getInstance()
			->getConnectionProvider()->getReplicaDatabase();

		$searchLower = mb_strtolower( $search );
		$valueLike = $dbr->buildLike(
			$dbr->anyString(), $searchLower, $dbr->anyString()
		);

		$rows = $dbr->newSelectQueryBuilder()
			->select( [ 'page_namespace', 'page_title' ] )
			->from( 'page_props' )
			->join( 'page', null, 'pp_page = page_id' )
			->where( [
				'pp_propname' => 'displaytitle',
				'page_namespace' => NS_MAIN,
				'page_is_redirect' => 0,
				'CONVERT(pp_value USING utf8mb4)' . $valueLike,
			] )
			->limit( $limit )
			->orderBy( 'LENGTH(page_title)', 'ASC' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$titles = [];
		foreach ( $rows as $row ) {
			$title = Title::makeTitle( (int)$row->page_namespace, $row->page_title );
			if ( $title ) {
				$titles[] = $title;
			}
		}
		return $titles;
	}

	/**
	 * Public wrapper for displayTitleSearch (used by ApiDRSearch).
	 * @return Title[]
	 */
	public static function displayTitleSearchPublic(
		string $search, string $langSuffix, int $limit
	): array {
		return self::displayTitleSearch( $search, $langSuffix, $limit );
	}

	/**
	 * Bulk-fetch display titles for a list of Title objects.
	 *
	 * @param Title[] $titles
	 * @return array<int, string> Map of page ID → display title
	 */
	private static function getDisplayTitles( array $titles ): array {
		if ( $titles === [] ) {
			return [];
		}

		$pageIds = [];
		foreach ( $titles as $t ) {
			$id = $t->getArticleID();
			if ( $id > 0 ) {
				$pageIds[] = $id;
			}
		}
		if ( $pageIds === [] ) {
			return [];
		}

		$dbr = MediaWikiServices::getInstance()
			->getConnectionProvider()->getReplicaDatabase();

		$rows = $dbr->newSelectQueryBuilder()
			->select( [ 'pp_page', 'pp_value' ] )
			->from( 'page_props' )
			->where( [
				'pp_propname' => 'displaytitle',
				'pp_page' => $pageIds,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$map = [];
		foreach ( $rows as $row ) {
			$map[(int)$row->pp_page] = $row->pp_value;
		}
		return $map;
	}

	/**
	 * Public wrapper for getDisplayTitles (used by ApiDRSearch).
	 *
	 * @param Title[] $titles
	 * @return array<int, string>
	 */
	public static function getDisplayTitlesPublic( array $titles ): array {
		return self::getDisplayTitles( $titles );
	}

	/**
	 * SpecialSearchResultsPrepend: when in-language filtering would drop
	 * every hit, prepend a banner offering the same query in all languages.
	 */
	public static function onSpecialSearchResultsPrepend(
		$specialSearch, $output, $term
	): bool {
		if ( trim( (string)$term ) === '' ) {
			return true;
		}
		$request = $specialSearch->getRequest();
		if ( $request->getVal( 'profile', '' ) === 'translation' ) {
			return true;
		}
		if ( $request->getVal( 'searchlang' ) === 'all' ) {
			return true;
		}

		$langSuffix = self::getSearchLanguageSuffix( $specialSearch );
		if ( $langSuffix === null ) {
			return true;
		}

		// Probe: how many raw hits does the query have, and how many survive
		// the in-language filter?
		$services = MediaWikiServices::getInstance();
		$engine = $services->newSearchEngine();
		$engine->setNamespaces( [ NS_MAIN ] );
		$engine->setLimitOffset( 200, 0 );
		$matches = $engine->searchText( $term );
		if ( !$matches ) {
			return true;
		}

		$totalHits = 0;
		$inLangHits = 0;
		foreach ( $matches as $result ) {
			$title = $result->getTitle();
			if ( !$title ) {
				continue;
			}
			$totalHits++;
			if ( self::titleMatchesLanguage( $title->getText(), $langSuffix ) ) {
				$inLangHits++;
			}
		}
		if ( $inLangHits > 0 || $totalHits === 0 ) {
			return true;
		}

		$langNameUtils = $services->getLanguageNameUtils();
		$displayName = $langNameUtils->getLanguageName( $langSuffix ) ?: $langSuffix;

		$allUrl = $specialSearch->getPageTitle()->getLocalURL( [
			'search' => $term,
			'fulltext' => 1,
			'searchlang' => 'all',
		] );
		$linkText = $specialSearch->msg( 'aits-search-see-all-languages-link' )
			->numParams( $totalHits )->text();
		$linkHtml = \Html::element( 'a',
			[ 'href' => $allUrl, 'class' => 'aits-search-all-languages-link' ],
			$linkText
		);
		$bannerText = $specialSearch->msg( 'aits-search-no-in-language-results' )
			->params( $displayName )->rawParams( $linkHtml )->parse();

		$bannerHtml = \Html::rawElement( 'div',
			[
				'class' => 'aits-search-fallback-banner',
				'style' => 'padding:12px 16px;margin:0 0 1em;background:#fef6e7;'
					. 'border:1px solid #fc3;border-radius:4px;',
			],
			$bannerText
		);
		$output->addHTML( $bannerHtml );
		return true;
	}

	/**
	 * SpecialSearchResultsAppend: when default full-text search finds nothing,
	 * show title-substring matches (MySQL FULLTEXT is word-based, so "inner"
	 * won't match "InnerMotion" — this bridges that gap).
	 */
	public static function onSpecialSearchResultsAppend(
		$specialSearch, $output, $term
	): void {
		if ( trim( $term ) === '' ) {
			return;
		}

		$profile = $specialSearch->getRequest()->getVal( 'profile', '' );
		if ( $profile === 'translation' ) {
			return;
		}

		$langSuffix = self::getSearchLanguageSuffix( $specialSearch );
		if ( $langSuffix === null ) {
			$langSuffix = 'en';
		}

		// Combine substring title matches + display title matches.
		$seen = [];
		$allTitles = [];
		foreach ( self::substringTitleSearch( $term, $langSuffix, 20 ) as $t ) {
			$key = $t->getPrefixedDBkey();
			if ( !isset( $seen[$key] ) ) {
				$seen[$key] = true;
				$allTitles[] = $t;
			}
		}
		if ( count( $allTitles ) < 20 ) {
			foreach ( self::displayTitleSearch( $term, $langSuffix, 20 ) as $t ) {
				$key = $t->getPrefixedDBkey();
				if ( !isset( $seen[$key] ) ) {
					$seen[$key] = true;
					$allTitles[] = $t;
				}
				if ( count( $allTitles ) >= 20 ) {
					break;
				}
			}
		}

		if ( $allTitles === [] ) {
			return;
		}

		// Fetch display titles so we show translated names.
		$displayMap = self::getDisplayTitles( $allTitles );

		$html = '<div class="mw-search-title-matches" style="margin-top:1em;">'
			. '<h2 style="font-size:1.1em;border-bottom:1px solid #a2a9b1;padding-bottom:4px;">'
			. htmlspecialchars( 'Pages with matching titles' )
			. '</h2><ul>';

		foreach ( $allTitles as $title ) {
			$url = $title->getLocalURL();
			$pageId = $title->getArticleID();
			$display = $displayMap[$pageId] ?? $title->getText();
			$html .= '<li><a href="' . htmlspecialchars( $url ) . '">'
				. htmlspecialchars( $display ) . '</a></li>';
		}

		$html .= '</ul></div>';
		$output->addHTML( $html );
	}

	/**
	 * ShowSearchHitTitle: replace the English page title shown in search
	 * results with the page's display title (translated name).
	 */
	public static function onShowSearchHitTitle(
		&$title, &$titleSnippet, $result, $terms, $specialSearch, &$query, &$attributes
	): void {
		if ( !$title instanceof \MediaWiki\Title\Title ) {
			return;
		}
		$pageId = $title->getArticleID();
		if ( $pageId <= 0 ) {
			return;
		}
		$displayMap = self::getDisplayTitles( [ $title ] );
		if ( isset( $displayMap[$pageId] ) ) {
			$titleSnippet = new \HtmlArmor( htmlspecialchars( $displayMap[$pageId] ) );
		}
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
