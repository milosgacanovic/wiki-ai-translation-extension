<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\AiTranslationExtension;

use ApiBase;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

class ApiTranslationStatus extends ApiBase {
	private const STATUS_MACHINE = 'machine';
	private const STATUS_REVIEWED = 'reviewed';
	private const STATUS_OUTDATED = 'outdated';

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

		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		if ( !$permissionManager->quickUserCan( 'edit', $this->getUser(), $title ) ) {
			$this->dieWithError( [ 'badaccess-group0' ], 'permissiondenied' );
		}

		$status = strtolower( trim( $params['status'] ) );
		if ( !in_array( $status, [ self::STATUS_MACHINE, self::STATUS_REVIEWED, self::STATUS_OUTDATED ], true ) ) {
			$this->dieWithError( 'Invalid status value.', 'invalidstatus' );
		}

		$reviewedAt = trim( (string)$params['reviewed_at'] );
		if ( $reviewedAt !== '' && !preg_match( '/^\d{4}-\d{2}-\d{2}$/', $reviewedAt ) ) {
			$this->dieWithError( 'reviewed_at must be YYYY-MM-DD.', 'invalid-reviewed-at' );
		}

		$pageId = $title->getArticleID();
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

		$values = [
			self::PROP_STATUS => $status,
		];
		if ( $params['source_rev'] !== null ) {
			$values[self::PROP_SOURCE_REV] = (string)(int)$params['source_rev'];
		}
		if ( trim( (string)$params['reviewed_by'] ) !== '' ) {
			$values[self::PROP_REVIEWED_BY] = trim( (string)$params['reviewed_by'] );
		}
		if ( $reviewedAt !== '' ) {
			$values[self::PROP_REVIEWED_AT] = $reviewedAt;
		}
		if ( $params['outdated_source_rev'] !== null ) {
			$values[self::PROP_OUTDATED_SOURCE_REV] = (string)(int)$params['outdated_source_rev'];
		}
		if ( trim( (string)$params['source_title'] ) !== '' ) {
			$values[self::PROP_SOURCE_TITLE] = trim( (string)$params['source_title'] );
		}
		if ( trim( (string)$params['source_lang'] ) !== '' ) {
			$values[self::PROP_SOURCE_LANG] = trim( (string)$params['source_lang'] );
		}

		$dbw->startAtomic( __METHOD__ );
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'page_props' )
			->where( [
				'pp_page' => $pageId,
				'pp_propname' => array_keys( $values ),
			] )
			->caller( __METHOD__ )
			->execute();

		$rows = [];
		foreach ( $values as $name => $value ) {
			$sortKey = is_numeric( $value ) ? (float)$value : null;
			$rows[] = [
				'pp_page' => $pageId,
				'pp_propname' => $name,
				'pp_value' => $value,
				'pp_sortkey' => $sortKey,
			];
		}

		$dbw->newInsertQueryBuilder()
			->insertInto( 'page_props' )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
		$dbw->endAtomic( __METHOD__ );

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'result' => 'ok',
			'title' => $title->getPrefixedText(),
			'status' => $status,
			'updated' => array_keys( $values ),
		] );
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
			'status' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'source_rev' => [
				ParamValidator::PARAM_TYPE => 'integer',
			],
			'reviewed_by' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => '',
			],
			'reviewed_at' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => '',
			],
			'outdated_source_rev' => [
				ParamValidator::PARAM_TYPE => 'integer',
			],
			'source_title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => '',
			],
			'source_lang' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => '',
			],
			'token' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}

