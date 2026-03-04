# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is the production **DanceResource.org MediaWiki 1.42.3** instance. The primary development work happens in the custom **AiTranslationExtension** and the **DanceResourceTimeless** skin, both of which are git repositories within the MediaWiki installation.

- **Wiki root:** `/var/www/wiki.danceresource.org/public_html`
- **AiTranslationExtension:** `/var/www/wiki.danceresource.org/public_html/extensions/AiTranslationExtension`
- **Custom skin:** `/var/www/wiki.danceresource.org/public_html/skins/DanceResourceTimeless`
- **Main config:** `/var/www/wiki.danceresource.org/public_html/LocalSettings.php`

## Key Architecture Rule

**Only settings variables belong in `LocalSettings.php`.** All hook registrations and logic live in:
```
extensions/AiTranslationExtension/local-hooks.php
```
This file is `require_once`'d by `LocalSettings.php`.

## AiTranslationExtension Structure

```
extensions/AiTranslationExtension/
├── extension.json              # Extension manifest, config schema, ResourceLoader modules, API registrations
├── local-hooks.php             # All hook wiring (included by LocalSettings.php)
├── src/
│   ├── ApiMarkForTranslation.php    # action=markfortranslation (required by bot)
│   ├── ApiLanguageStatus.php        # action=danceresource-languagestatus
│   ├── ApiTranslationInfo.php       # action=aitranslationinfo
│   ├── ApiTranslationStatus.php     # action=aitranslationstatus
│   ├── HookHandler.php              # SkinBuildSidebar, PageSaveComplete, Translate hooks
│   └── Hooks/UnifiedLangSwitcherHooks.php
├── resources/
│   ├── ext.danceresource.unifiedLangSwitcher.js/.css   # Language switcher UI
│   ├── ext.aitranslation.statusUi.js/.css              # Translation status banner + dot indicator
│   └── ext.danceresource.common.js/.css
└── i18n/                       # Translation strings (en.json, etc.)
```

## Development Commands

```bash
# PHP linting
cd /var/www/wiki.danceresource.org/public_html && composer lint .

# Code style check
composer phpcs .

# Auto-fix code style
composer fix

# Run all tests
composer test

# PHPUnit (unit tests)
composer phpunit:unit

# PHPUnit (integration)
composer phpunit:integration

# Purge a page's cache (in browser)
?action=purge
```

After ResourceLoader JS/CSS changes, restart PHP-FPM:
```bash
sudo systemctl restart php8.1-fpm
```

## MediaWiki Configuration Flags (AiTranslationExtension)

Set in `LocalSettings.php`:
```php
$wgDRUnifiedLangSwitcherEnabled = true;
$wgDRUnifiedLangSwitcherNamespaces = [ NS_MAIN, NS_PROJECT ];
$wgDRUnifiedLangSwitcherPosition = 'header';          // sidebar | header | personal
$wgDRUnifiedLangSwitcherFallbackBehavior = 'stay_and_notify'; // or navigate_anyway
$wgDRUnifiedLangSwitcherPreferAvailableOnly = true;
$wgDRUnifiedLangSwitcherUILanguageMode = 'uls_cookie'; // or user_preference_only
$wgAiTranslationExtensionLanguageSidebar = true;       // MediaWiki:Sidebar/<lang> support
```

## Debug Parameters (URL Query Strings)

| Parameter | Effect |
|-----------|--------|
| `?debugsidebar=1` | Show sidebar language selection debug info |
| `?action=purge` | Purge page cache |

Remove `?debuguls=1` from code when done troubleshooting — it is a temporary debug flag.

## Authentication

- SSO via **Keycloak** (PluggableAuth + OpenIDConnect extensions)
- Emergency local login: `Special:UserLogin?local=1`
- Client secret is in environment variable `MW_OIDC_WIKI_CLIENT_SECRET`

## Translation System

- Translation metadata uses `ai_` prefix in PageProps (NOT `dr_`), stored on translated pages (e.g., `Title/sr`)
- Key PageProps: `ai_translation_status` (machine|reviewed|outdated), `ai_translation_source_rev`
- Serbian shown as single **Srpski** entry; link uses `?uselang=sr-el`
- Language-specific sidebars via `MediaWiki:Sidebar/<lang>` pages

## Git Workflow

- **Do not push to `origin` unless explicitly asked.**
- Commit locally as needed; push only on explicit request.
- The main wiki root (`/var/www/wiki.danceresource.org/public_html`) is NOT a git repo.
- Git repos: `AiTranslationExtension/`, `skins/DanceResourceTimeless/`, `extensions/GTag/`
