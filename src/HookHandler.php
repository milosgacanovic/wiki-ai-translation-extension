<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\AiTranslationExtension;

use ContentHandler;
use MediaWiki\MediaWikiServices;
use Title;

class HookHandler {
	public static function onSkinBuildSidebar( $skin, &$bar ): bool {
		if ( $skin->getRequest()->getCheck( 'debugsidebar' ) ) {
			$bar['navigation'] = $bar['navigation'] ?? [];
			array_unshift( $bar['navigation'], [
				'text' => 'AiTranslationExtension hook active',
				'href' => '#',
			] );
		}

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
		$selectedCode = null;
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
						$selectedCode = $code;
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
		if ( $skin->getRequest()->getCheck( 'debugsidebar' ) ) {
			$debugItem = [
				'text' => "Sidebar lang={$lang} candidates=" . implode( ',', $candidates ) .
					" selected={$selectedCode} textlen=" . strlen( $text ),
				'href' => '#',
			];
			if ( isset( $bar['navigation'] ) && is_array( $bar['navigation'] ) ) {
				array_unshift( $bar['navigation'], $debugItem );
			} else {
				$bar['navigation'] = [ $debugItem ];
			}
		}
		return false;
	}
}
