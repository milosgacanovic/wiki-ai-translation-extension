<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\AiTranslationExtension;

use ApiBase;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Lightweight autocomplete API that returns display titles.
 *
 * Usage: api.php?action=drsearch&search=rezonanca&uselang=sr-el
 *
 * Returns an array of { title, displaytitle, url } objects.
 */
class ApiDRSearch extends ApiBase {

	public function execute(): void {
		$params = $this->extractRequestParams();
		$search = trim( $params['search'] );
		if ( $search === '' ) {
			$this->getResult()->addValue( null, $this->getModuleName(), [] );
			return;
		}

		$limit = min( (int)$params['limit'], 20 );
		$langSuffix = HookHandler::getSearchLanguageSuffix( $this );
		if ( $langSuffix === null ) {
			$langSuffix = 'en';
		}

		$seen = [];
		$titles = [];

		// 1. Prefix-based completion (MediaWiki search engine).
		$services = MediaWikiServices::getInstance();
		$searchEngine = $services->newSearchEngine();
		$searchEngine->setNamespaces( [ NS_MAIN ] );
		$searchEngine->setLimitOffset( 100, 0 );
		$suggestions = $searchEngine->completionSearchWithVariants( $search );
		$prefixTitles = $searchEngine->extractTitles( $suggestions );

		foreach ( $prefixTitles as $title ) {
			if ( HookHandler::titleMatchesLanguage( $title->getText(), $langSuffix ) ) {
				$dbKey = $title->getPrefixedDBkey();
				if ( !isset( $seen[$dbKey] ) ) {
					$seen[$dbKey] = true;
					$titles[] = $title;
				}
				if ( count( $titles ) >= $limit ) {
					break;
				}
			}
		}

		// 2. Substring title matches.
		if ( count( $titles ) < $limit ) {
			foreach ( HookHandler::substringTitleSearchPublic( $search, $langSuffix, $limit * 3 ) as $title ) {
				$dbKey = $title->getPrefixedDBkey();
				if ( !isset( $seen[$dbKey] ) ) {
					$seen[$dbKey] = true;
					$titles[] = $title;
				}
				if ( count( $titles ) >= $limit ) {
					break;
				}
			}
		}

		// 3. Display title matches (find translated pages by their localised name).
		if ( count( $titles ) < $limit ) {
			foreach ( HookHandler::displayTitleSearchPublic( $search, $langSuffix, $limit * 3 ) as $title ) {
				$dbKey = $title->getPrefixedDBkey();
				if ( !isset( $seen[$dbKey] ) ) {
					$seen[$dbKey] = true;
					$titles[] = $title;
				}
				if ( count( $titles ) >= $limit ) {
					break;
				}
			}
		}

		// Fetch display titles in bulk.
		$displayMap = HookHandler::getDisplayTitlesPublic( $titles );

		$results = [];
		foreach ( $titles as $title ) {
			$pageId = $title->getArticleID();
			$results[] = [
				'title' => $title->getPrefixedText(),
				'displaytitle' => $displayMap[$pageId] ?? $title->getText(),
				'url' => $title->getLocalURL(),
			];
		}

		$this->getResult()->addValue( null, $this->getModuleName(), $results );
	}

	/** @inheritDoc */
	public function getAllowedParams(): array {
		return [
			'search' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'limit' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_DEFAULT => 10,
			],
		];
	}

	/** @inheritDoc */
	public function isReadMode(): bool {
		return false;
	}
}
