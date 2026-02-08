# AiTranaslationExtension

Adds a server-side API module `action=markfortranslation` for MediaWiki Translate.

This is required by `https://github.com/milosgacanovic/wiki-ai-translation` to mark pages for translation via the API.
MediaWiki 1.42 + Translate does not expose `markfortranslation` by default, so the bot cannot automate page marking without this extension.

## Install

1. Copy this extension into `extensions/AiTranaslationExtension`.
2. Add to `LocalSettings.php`:

```
wfLoadExtension( 'AiTranaslationExtension' );
```

Optional if you prefer explicit registration:

```
$wgAutoloadClasses['MediaWiki\\Extension\\LocalPageTranslationApi\\ApiMarkForTranslation'] =
	"$IP/extensions/AiTranaslationExtension/src/ApiMarkForTranslation.php";
$wgAPIModules['markfortranslation'] =
	'MediaWiki\\Extension\\LocalPageTranslationApi\\ApiMarkForTranslation';
```

## Usage (API)

```
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
