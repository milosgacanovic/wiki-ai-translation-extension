<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\AiTranslationExtension;

use ApiBase;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

class ApiTranslationInfo extends ApiBase {
	private const PROP_STATUS = 'ai_translation_status';
	private const PROP_SOURCE_REV = 'ai_translation_source_rev';
	private const PROP_REVIEWED_BY = 'ai_translation_reviewed_by';
	private const PROP_REVIEWED_AT = 'ai_translation_reviewed_at';
	private const PROP_OUTDATED_SOURCE_REV = 'ai_translation_outdated_source_rev';
	private const PROP_SOURCE_TITLE = 'ai_translation_source_title';
	private const PROP_SOURCE_LANG = 'ai_translation_source_lang';

	public function execute(): void {
		$params = $this->extractRequestParams();
		$title = Title::newFromText( $params['title'] );
		if ( !$title ) {
			$this->dieWithError( [ 'apierror-invalidtitle', $params['title'] ], 'invalidtitle' );
		}
		if ( !$title->exists() ) {
			$this->dieWithError( [ 'apierror-missingtitle', $params['title'] ], 'missingtitle' );
		}

		$pageProps = MediaWikiServices::getInstance()->getPageProps();
		$allProps = $pageProps->getAllProperties( $title );
		$props = $allProps[$title->getArticleID()] ?? [];

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'title' => $title->getPrefixedText(),
			'exists' => 1,
			'status' => $props[self::PROP_STATUS] ?? null,
			'source_rev' => isset( $props[self::PROP_SOURCE_REV] ) ? (int)$props[self::PROP_SOURCE_REV] : null,
			'reviewed_by' => $props[self::PROP_REVIEWED_BY] ?? null,
			'reviewed_at' => $props[self::PROP_REVIEWED_AT] ?? null,
			'outdated_source_rev' => isset( $props[self::PROP_OUTDATED_SOURCE_REV] ) ?
				(int)$props[self::PROP_OUTDATED_SOURCE_REV] : null,
			'source_title' => $props[self::PROP_SOURCE_TITLE] ?? null,
			'source_lang' => $props[self::PROP_SOURCE_LANG] ?? null,
			'pageprops' => $props,
		] );
	}

	protected function getAllowedParams(): array {
		return [
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}

