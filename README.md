# AiTranslationExtension

Adds API modules and UI helpers for MediaWiki Translate.

This is required by `https://github.com/milosgacanovic/wiki-ai-translation` to mark pages for translation via the API.
MediaWiki 1.42 + Translate does not expose `markfortranslation` by default, so the bot cannot automate page marking without this extension.

## Requirements

1. MediaWiki 1.42+
2. Translate extension with PageTranslation enabled
3. UniversalLanguageSelector (for UI language switching)

## Install

1. Copy this extension into `extensions/AiTranslationExtension`.
2. Add to `LocalSettings.php`:

```php
wfLoadExtension( 'AiTranslationExtension' );
```

Optional if you prefer explicit registration:

```php
$wgAutoloadClasses['MediaWiki\\Extension\\AiTranslationExtension\\ApiMarkForTranslation'] =
	"$IP/extensions/AiTranslationExtension/src/ApiMarkForTranslation.php";
$wgAPIModules['markfortranslation'] =
	'MediaWiki\\Extension\\AiTranslationExtension\\ApiMarkForTranslation';
```

## Unified Language Switcher (optional)

Provides a Wikipedia-like language selector that switches both the page translation URL
and the UI language. Default is off.

Enable in `LocalSettings.php`:

```php
$wgDRUnifiedLangSwitcherEnabled = true;
$wgDRUnifiedLangSwitcherNamespaces = [ NS_MAIN, NS_PROJECT ];
$wgDRUnifiedLangSwitcherPosition = 'header';
$wgDRUnifiedLangSwitcherFallbackBehavior = 'stay_and_notify';
$wgDRUnifiedLangSwitcherPreferAvailableOnly = true;
$wgDRUnifiedLangSwitcherUILanguageMode = 'uls_cookie';
```

Config options:

- `DRUnifiedLangSwitcherEnabled` (bool) Enable the feature.
- `DRUnifiedLangSwitcherNamespaces` (array) Namespace IDs where it runs.
- `DRUnifiedLangSwitcherPosition` (string) `sidebar`, `header`, or `personal`.
- `DRUnifiedLangSwitcherFallbackBehavior` (string) `stay_and_notify` or `navigate_anyway`.
- `DRUnifiedLangSwitcherPreferAvailableOnly` (bool) Show only existing translations.
- `DRUnifiedLangSwitcherUILanguageMode` (string) `uls_cookie` or `user_preference_only`.

Behavior notes:

- Language links use `?uselang=` so the UI switches along with the content page.
- On translatable pages, selecting language `L` navigates to `/Title/L` (or stays if missing).
- Serbian is shown as a single entry labeled **Srpski** (no `sr-ec`/`sr-el` entries). It links to
  `/Title/sr?uselang=sr-el` to default UI to Serbian Latin.
- Special pages, edit pages, and non-configured namespaces are unaffected.

## Language-Specific Sidebar (optional)

Off by default. When enabled, the sidebar uses `MediaWiki:Sidebar/<lang>` based on the
user's interface language (e.g. `sr-el`, `sr-ec`, `sr`).

Add to `LocalSettings.php`:

```php
$wgAiTranslationExtensionLanguageSidebar = true;
$wgAutoloadClasses['MediaWiki\\Extension\\AiTranslationExtension\\HookHandler'] =
	"$IP/extensions/AiTranslationExtension/src/HookHandler.php";
$wgHooks['SkinBuildSidebar'][] = static function ( $skin, &$bar ) {
	return \MediaWiki\Extension\AiTranslationExtension\HookHandler::onSkinBuildSidebar( $skin, $bar );
};
```

Debug helper:

```
?debugsidebar=1
```

Example pages:

- `MediaWiki:Sidebar/sr-el`
- `MediaWiki:Sidebar/sr-ec`
- `MediaWiki:Sidebar/sr`

## Usage (API)

```text
action=markfortranslation
title=Page title
token=CSRF_TOKEN
```

Optional parameters:

- `revision` (int)
- `translatetitle` (bool)
- `prioritylangs` (comma-separated)
- `forcelimit` (bool)
- `priorityreason` (string)
- `nofuzzy` (multi or comma-separated)
- `use_latest_syntax` (bool)
- `transclusion` (bool)

## Language Status API

```text
action=danceresource-languagestatus
title=Page title
```

Returns:

- `title` (base page title)
- `sourceLanguage`
- `currentLanguage`
- `languages` (available translations)

## Troubleshooting

1. Purge the page with `?action=purge` and hard refresh.
2. Ensure `Translate` and `UniversalLanguageSelector` are enabled.
3. Check that the page is marked for translation and translations exist.
4. Restart PHP-FPM/Apache if ResourceLoader changes are not visible.

## Acceptance checklist

1. Anonymous user changes language and both UI + content switch.
2. Logged-in user changes language and preference persists (ULS).
3. No redirect loops when re-selecting current language.
4. Special pages are unaffected.
5. Works on a page with translations and on a page without translations.
