# AGENTS.md — wiki.danceresource.org

## Purpose
Central notes for ongoing work on the DanceResource MediaWiki instance and its custom extension.
Keep this file updated when workflows, hook wiring, or behaviors change.

## Project Layout
- Wiki root: `/var/www/wiki.danceresource.org/public_html`
- Extension repo: `/var/www/wiki.danceresource.org/public_html/extensions/AiTranslationExtension`
- Local hooks file (included by LocalSettings):
  - `/var/www/wiki.danceresource.org/public_html/extensions/AiTranslationExtension/local-hooks.php`

## LocalSettings.php
Only **settings variables** should live in `LocalSettings.php`.
All hook wiring and code lives in `extensions/AiTranslationExtension/local-hooks.php`.

LocalSettings includes:
```php
require_once "$IP/extensions/AiTranslationExtension/local-hooks.php";
```

## AiTranslationExtension Features

### 1) API modules
- `action=markfortranslation` (required for the bot)
- `action=danceresource-languagestatus` (language availability + metadata)

### 2) Unified Language Switcher (ULS + Translate)
- ResourceLoader module: `ext.danceresource.unifiedLangSwitcher`
- Injected into header (Timeless) and styled to match Variants
- Uses `?uselang=` links for UI switching
- For Serbian:
  - Only one entry labeled **Srpski** (no `sr-ec`/`sr-el` entries)
  - Link uses `?uselang=sr-el`
- Anonymous auto UI switch:
  - On `/.../sr` pages, auto-sets ULS language cookie to `sr-el` and reloads

Config flags (LocalSettings):
- `$wgDRUnifiedLangSwitcherEnabled = true;`
- `$wgDRUnifiedLangSwitcherNamespaces = [ NS_MAIN, NS_PROJECT ];`
- `$wgDRUnifiedLangSwitcherPosition = 'header';`

### 3) Language-specific Sidebar
- Controlled by `$wgAiTranslationExtensionLanguageSidebar = true;`
- Uses `MediaWiki:Sidebar/<lang>` based on UI language
- Debug with `?debugsidebar=1`

## Debug Flags
- `?debugsidebar=1` — sidebar selection debug line
- `?debuguls=1` — (temporarily used during troubleshooting; should be removed from code when done)

## Important Notes
- **Do not push to `origin` unless explicitly asked.**
- Avoid adding code directly to `LocalSettings.php`; keep logic in `local-hooks.php`.

## Commit/Push Workflow
- Commit locally as needed.
- Push only when user explicitly requests.
