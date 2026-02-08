<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\AiTranslationExtension;

use ApiBase;
use IDBAccessObject;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePageMarkException;
use MediaWiki\Extension\Translate\PageTranslation\TranslatablePageSettings;
use MediaWiki\Extension\Translate\Services as TranslateServices;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

class ApiMarkForTranslation extends ApiBase {
	public function execute(): void {
		$params = $this->extractRequestParams();
		$user = $this->getUser();

		if ( !$user->isAllowed( 'pagetranslation' ) ) {
			$this->dieWithError( [ 'badaccess-group0' ], 'permissiondenied' );
		}

		$title = Title::newFromText( $params['title'] );
		if ( !$title ) {
			$this->dieWithError( [ 'apierror-invalidtitle', $params['title'] ], 'invalidtitle' );
		}
		if ( !$title->exists() ) {
			$this->dieWithError( [ 'apierror-missingtitle', $params['title'] ], 'missingtitle' );
		}

		$marker = TranslateServices::getInstance()->getTranslatablePageMarker();

		try {
			$operation = $marker->getMarkOperation(
				$title->toPageRecord( IDBAccessObject::READ_LATEST ),
				$params['revision'] ?? null,
				(bool)$params['translatetitle']
			);
		} catch ( TranslatablePageMarkException $e ) {
			$this->dieWithError( $e->getMessageObject(), 'markfortranslation-failed' );
		}

		$priorityLanguages = $this->parsePriorityLanguages( $params['prioritylangs'] ?? '' );
		$noFuzzyUnits = $this->parseListParam( $params['nofuzzy'] ?? [] );

		$settings = new TranslatablePageSettings(
			$priorityLanguages,
			(bool)$params['forcelimit'],
			(string)$params['priorityreason'],
			$noFuzzyUnits,
			(bool)$params['translatetitle'],
			(bool)$params['use_latest_syntax'],
			(bool)$params['transclusion']
		);

		try {
			$unitCount = $marker->markForTranslation( $operation, $settings, $user );
		} catch ( TranslatablePageMarkException $e ) {
			$this->dieWithError( $e->getMessageObject(), 'markfortranslation-failed' );
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'result' => 'ok',
			'title' => $title->getPrefixedText(),
			'units' => $unitCount,
			'firstmark' => $operation->isFirstMark(),
		] );
	}

	private function parsePriorityLanguages( string $raw ): array {
		$raw = rtrim( trim( $raw ), ',' );
		$raw = str_replace( "\n", ',', $raw );
		$parts = array_map( 'trim', explode( ',', $raw ) );
		return array_values( array_unique( array_filter( $parts ) ) );
	}

	private function parseListParam( $value ): array {
		if ( is_array( $value ) ) {
			return array_values( array_unique( array_filter( $value, 'strlen' ) ) );
		}
		if ( is_string( $value ) && $value !== '' ) {
			$parts = array_map( 'trim', explode( ',', $value ) );
			return array_values( array_unique( array_filter( $parts ) ) );
		}
		return [];
	}

	public function isWriteMode(): bool {
		return true;
	}

	public function needsToken(): string {
		return 'csrf';
	}

	protected function getAllowedParams(): array {
		return [
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'revision' => [
				ParamValidator::PARAM_TYPE => 'integer',
			],
			'translatetitle' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false,
			],
			'prioritylangs' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => '',
			],
			'forcelimit' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false,
			],
			'priorityreason' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => '',
			],
			'nofuzzy' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_ISMULTI => true,
			],
			'use_latest_syntax' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false,
			],
			'transclusion' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false,
			],
			'token' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
