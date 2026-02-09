<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\AiTranslationExtension;

use ApiBase;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePage;
use MediaWiki\Extension\Translate\Utilities\Utilities;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

class ApiLanguageStatus extends ApiBase {
	public function execute(): void {
		$params = $this->extractRequestParams();
		$title = Title::newFromText( $params['title'] );
		if ( !$title ) {
			$this->dieWithError( [ 'apierror-invalidtitle', $params['title'] ], 'invalidtitle' );
		}

		if ( !class_exists( TranslatablePage::class ) ) {
			$this->getResult()->addValue( null, $this->getModuleName(), [
				'isEligible' => 0,
				'reason' => 'translate-missing'
			] );
			return;
		}

		if ( $title->isSpecialPage() ) {
			$this->getResult()->addValue( null, $this->getModuleName(), [
				'isEligible' => 0,
				'reason' => 'specialpage'
			] );
			return;
		}

		$handle = new \MessageHandle( $title );
		$baseTitle = $title;
		$currentLanguage = '';
		if ( Utilities::isTranslationPage( $handle ) ) {
			$baseTitle = $handle->getTitleForBase();
			if ( !$baseTitle ) {
				$this->getResult()->addValue( null, $this->getModuleName(), [
					'isEligible' => 0,
					'reason' => 'invalid-base'
				] );
				return;
			}
			$currentLanguage = $handle->getCode();
		}

		$translatable = TranslatablePage::newFromTitle( $baseTitle );
		$marked = $translatable->getMarkedTag() !== null;

		if ( !$marked ) {
			$this->getResult()->addValue( null, $this->getModuleName(), [
				'isEligible' => 0,
				'reason' => 'not-marked'
			] );
			return;
		}

		$sourceLang = $translatable->getSourceLanguageCode();
		$translations = $translatable->getTranslationPages();
		$stats = $translatable->getTranslationPercentages();
		$languages = [];
		$languageNameUtils = MediaWikiServices::getInstance()->getLanguageNameUtils();

		foreach ( $translations as $t ) {
			$handle = new \MessageHandle( $t );
			$code = $handle->getCode();
			$languages[$code] = [
				'code' => $code,
				'name' => $languageNameUtils->getLanguageName( $code ),
				'autonym' => $languageNameUtils->getLanguageName( $code, $code ),
				'percent' => $stats[$code] ?? null,
				'exists' => 1
			];
		}

		// Always include source language
		if ( !isset( $languages[$sourceLang] ) ) {
			$languages[$sourceLang] = [
				'code' => $sourceLang,
				'name' => $languageNameUtils->getLanguageName( $sourceLang ),
				'autonym' => $languageNameUtils->getLanguageName( $sourceLang, $sourceLang ),
				'percent' => 1.0,
				'exists' => 1
			];
		}

		if ( $currentLanguage === '' ) {
			$currentLanguage = $sourceLang;
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'isEligible' => 1,
			'title' => $baseTitle->getPrefixedText(),
			'currentLanguage' => $currentLanguage,
			'sourceLanguage' => $sourceLang,
			'languages' => array_values( $languages )
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
