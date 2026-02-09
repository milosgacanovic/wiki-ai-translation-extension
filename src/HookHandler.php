<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\AiTranslationExtension;

use ContentHandler;
use MediaWiki\MediaWikiServices;
use Title;

class HookHandler {
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
			return true;
		}

		$bar = [];
		$skin->addToSidebarPlain( $bar, $text );
		return false;
	}
}
