# feature-implementation.md — wiki-ai-translation-extension: Translation Status + UI + Bot Locking

## Target repository

This feature must be implemented in:

- https://github.com/milosgacanovic/wiki-ai-translation-extension

This is a MediaWiki extension (server-side) that will support:

- Setting and reading translation metadata (PageProps)
- Providing helper API endpoints if needed
- Coordinating with the external bot (`wiki-ai-translation`) which performs MT and writes translations

The UI (banner + status indicator) should be shipped by this extension as a ResourceLoader module (not via Common.js), so it is deployable, versioned, and consistent.

The bot repo should be updated only where necessary to respect the new status properties and stop injecting the old disclaimer text into page content.


======================================================================
PART A — SUMMARY (WHAT WE ARE BUILDING)
======================================================================

We are replacing the current approach:
- visible disclaimer paragraph inserted into translated page content (bad for SEO)

with a structured system:
1) Non-visible `Template:Translation_status` on every translated page
2) PageProps storing translation status + baseline source revision
3) A hard bot rule: never overwrite human-reviewed translations; mark them outdated on source change
4) JS UI:
   - Banner above content for machine/outdated (dismissable globally for machine only)
   - Persistent dot indicator near language selector (3 colors)
   - Hover tooltip with details (status, revision info, reviewer info)


======================================================================
PART B — TERMINOLOGY AND STATES
======================================================================

### Status values (minimal)
- machine   (yellow)
- reviewed  (green)
- outdated  (half yellow / half green)

### Key decisions (fixed)
- No "approved" state
- Lock rule: reviewed/outdated pages are never overwritten by the bot
- Source changes:
  - machine pages get retranslated automatically
  - reviewed pages become outdated (metadata-only), translation not overwritten


======================================================================
PART C — PAGEPROPS (ai_ PREFIX)
======================================================================

All translation metadata must use `ai_` prefix (NOT `dr_`).

Store PageProps on the TRANSLATED page (e.g., `Title/sr`), not the English source page.

## Required PageProps
- ai_translation_status : "machine" | "reviewed" | "outdated"
- ai_translation_source_rev : integer
  - Baseline source revision ID used when the translation was last generated (machine), or last confirmed (reviewed).

## Optional (recommended)
- ai_translation_reviewed_by : string
- ai_translation_reviewed_at : YYYY-MM-DD
- ai_translation_outdated_source_rev : integer
  - The source revision ID that caused transition reviewed -> outdated
- ai_translation_source_title : string (optional)
- ai_translation_source_lang : string (optional, default "en")

These must be readable through the MediaWiki API pageprops query:
- action=query&prop=pageprops&titles=...

This extension may also provide a dedicated API endpoint for easier consumption by JS and the bot (recommended; see Part F).


======================================================================
PART D — MANDATORY CHANGE: REMOVE CURRENT DISCLAIMER INJECTION
======================================================================

The current system inserts a visible disclaimer paragraph into translated page content.

This must be removed.

Rules:
- No visible disclaimer text may be written into translated page wikitext.
- No disclaimer paragraphs inside translation segments.
- Any old disclaimer placement configuration/anchors must no longer produce wikitext output.

Replacement:
- Use `Template:Translation_status` + JS UI shipped by this extension.


======================================================================
PART E — TEMPLATE:Translation_status
======================================================================

## Template location
- Template namespace: `Template:Translation_status`

## Template usage (must be at TOP of every translated page)
Machine translation default:
- {{Translation_status|status=machine}}

Human reviewed:
- {{Translation_status|status=reviewed|reviewed_by=Username|reviewed_at=2026-02-11}}

Marked outdated by bot:
- {{Translation_status|status=outdated}}

## Requirements
- Outputs NO visible text.
- Sets PageProps (ai_translation_*) according to parameters.
- Safe to exist on every translated page, at the top.

## Editor ergonomics
Editors should only need to:
- Change `status=machine` -> `status=reviewed` when they finish reviewing
- Change `status=outdated` -> `status=reviewed` after updating a stale translation

Everything else (revision IDs) can be written/updated by the bot/extension.


======================================================================
PART F — EXTENSION SERVER-SIDE IMPLEMENTATION (wiki-ai-translation-extension)
======================================================================

This extension must provide:

## F1) A reliable way to SET PageProps
Because templates cannot always set pageprops unless specific functionality exists, implement server-side support.

Two acceptable implementations:

### Option 1 (recommended): API module to set translation status + props
Add an API endpoint, for example:

- action=aitranslationstatus (example name; final name can vary)
Parameters:
- title (required)             : translated page title (e.g., "Foo/sr")
- status (required)            : machine|reviewed|outdated
- source_rev (optional int)    : baseline source rev
- reviewed_by (optional)       : string
- reviewed_at (optional)       : YYYY-MM-DD
- outdated_source_rev (optional int)
- token (csrf required)

Behavior:
- Writes/updates PageProps for the target page:
  - ai_translation_status
  - ai_translation_source_rev (if provided)
  - ai_translation_reviewed_by / ai_translation_reviewed_at (if provided)
  - ai_translation_outdated_source_rev (if provided)

Permissions:
- Require normal edit rights + CSRF token (treat as write action).
- Optionally restrict status transitions to privileged users (not required if your wiki already has edit approval moderation).

### Option 2: Parser hook / pageprops setter that the template can call
If you prefer template-driven prop setting, implement a parser function like:
- {{#aitranslationprop:key=value}}
This is more fragile and harder to validate. Option 1 is preferred.

## F2) Optional convenience API endpoint for JS
Provide read endpoint (or reuse action=query&prop=pageprops).

A dedicated endpoint can return normalized fields:
- status, source_rev, reviewed_by, reviewed_at, outdated_source_rev
This reduces JS complexity and avoids parsing pageprops structure.

Example:
- action=aitranslationinfo&title=Current_Page

## F3) Ensure the template exists and is correct
The extension may ship documentation and/or automated creation guidance,
but the actual template content will still live in wiki content.
(If you want the extension to auto-create it on install, specify that explicitly; default: documentation only.)


======================================================================
PART G — BOT BEHAVIOR (EXTERNAL BOT MUST RESPECT ai_ PROPS)
======================================================================

The external translation bot (wiki-ai-translation) must be updated to:

## G1) STOP writing visible disclaimer text
(See Part D.)

## G2) Enforce the lock rule using ai_translation_status
Before writing a translation for Title/<lang>:

1) Fetch ai_translation_status (either via:
   - action=query&prop=pageprops
   OR
   - action=aitranslationinfo if implemented)

2) Apply rules:

IF status == reviewed:
- Do NOT translate (no content write).
- Set status -> outdated (metadata-only):
  - ai_translation_status = outdated
  - ai_translation_outdated_source_rev = current source revision
- Leave translation content untouched.

IF status == outdated:
- Do NOT translate (no content write).

IF status == machine OR missing:
- Proceed with translation normally.
- After successful write, set:
  - ai_translation_status = machine
  - ai_translation_source_rev = current source revision

## G3) Rebuild-only mode
If the bot supports rebuild-only:
- Skip pages where status is reviewed or outdated.

## G4) Template enforcement
If translated page does not include {{Translation_status...}}:
- Insert it at the top (status=machine).
- (Optional) Set ai_translation_source_rev immediately via API module.


======================================================================
PART H — UI IMPLEMENTATION (ResourceLoader MODULE IN EXTENSION)
======================================================================

The extension must ship the UI via ResourceLoader:
- JS module (e.g., ext.aiTranslationStatus)
- CSS module (same)

The UI must be injected at runtime (no wikitext changes; no SEO boilerplate).

## H1) Status Indicator Dot (left of language selector)
Always visible on translated pages.

Colors:
- machine  : yellow
- reviewed : green
- outdated : half yellow / half green

Placement:
- Insert immediately left of language selector control in the page header.

Robust selector strategy (try in order; fail gracefully):
1) `.mw-interlanguage-selector`
2) `#p-lang-btn`
3) `#p-lang`
4) Any element likely representing language selector (fallback heuristic)

If not found:
- Do not render the dot (fail silently)
- Continue with banner injection

Hover/focus:
- Show tooltip/popover with details (see H3).

## H2) Banner Above Main Content
Shown for:
- machine
- outdated

Not shown for:
- reviewed

Banner includes:
- Message + CTA links
- X close button (upper right)

Dismiss rules:
- machine banner is dismissible "permanently" for this browser:
  - localStorage key: `ai_hide_machine_translation_banner` = "1"
  - If set, machine banner never appears again on any machine page
- outdated banner MUST ignore that flag and ALWAYS show
  - Outdated is actionable; do not allow permanent hide

CTA links:
- Edit this translation (edit current page)
- Optional: Open English source (derive by stripping /<lang> suffix)

Suggested text:
- machine:
  - Title: "Machine translation"
  - Body: "This page was translated automatically. Help improve it by reviewing."
  - CTA: "Edit this translation"
- outdated:
  - Title: "Translation outdated"
  - Body: "The English page changed. This translation may be behind."
  - CTA: "Edit to update"
  - Optional link: "Open English source"

Placement:
- Insert at top of main content container.
Try in order:
1) `#mw-content-text` (prepend)
2) `.mw-parser-output` (prepend)
3) `.mw-body-content` (prepend)

If none found: do nothing.

## H3) Tooltip / Popover Content
On hover/focus of the dot, show:

- Status: Machine / Human reviewed / Outdated
- Source baseline revision: ai_translation_source_rev
- If outdated: ai_translation_outdated_source_rev
- Reviewed by: ai_translation_reviewed_by (if present)
- Reviewed at: ai_translation_reviewed_at (if present)

Optional links inside tooltip:
- Edit this translation
- Open English source

Accessibility:
- Dot must be keyboard-focusable.
- Tooltip should appear on focus as well as hover.

## H4) Data retrieval strategy
JS should read status using:
- Preferred: extension endpoint `action=aitranslationinfo` (if implemented)
- Fallback: `action=query&prop=pageprops&titles=...`

If data fetch fails:
- Do not render UI (fail silently; no errors thrown)

Performance:
- Only one request per page load.
- Cache response in memory.

## H5) CSS requirements
- Banner: readable, not huge, minimal layout shift, subtle border/background.
- X: clear affordance, small, upper-right.
- Dot: 12–14px circle; outdated uses half/half split or gradient.
- Tooltip: max width ~320px, readable labels, not obstructive.


======================================================================
PART I — MIGRATION PLAN
======================================================================

1) Deploy the extension changes (API modules + RL UI modules).
2) Remove disclaimer injection from the bot and deploy bot update.
3) Create Template:Translation_status on-wiki.
4) Run a one-time migration script:
   - For each translated page:
     - Add {{Translation_status|status=machine}} at the top if missing
     - Set ai_translation_status=machine
     - Set ai_translation_source_rev to current English source rev
5) Verify:
   - machine pages show banner + yellow dot
   - reviewed pages show green dot only
   - reviewed -> outdated transition works when source changes


======================================================================
PART J — ACCEPTANCE CRITERIA
======================================================================

## Data + bot
- No visible disclaimer text is written into translated page wikitext anymore.
- Every translated page contains {{Translation_status|...}} at top.
- PageProps include ai_translation_status and ai_translation_source_rev on translated pages.
- Bot never overwrites translations when status is reviewed or outdated.
- When source changes, reviewed pages become outdated (metadata-only).

## UI
- Machine pages:
  - Banner visible above content unless dismissed
  - Yellow dot appears left of language selector
  - Tooltip shows status + baseline rev
- Dismiss behavior:
  - Clicking X on machine banner sets localStorage `ai_hide_machine_translation_banner=1`
  - Machine banner stays hidden globally for all machine pages in that browser
- Reviewed pages:
  - No banner
  - Green dot visible
  - Tooltip shows reviewed_by/reviewed_at if present
- Outdated pages:
  - Banner always visible (ignores hide flag)
  - Half dot visible
  - Tooltip includes outdated source rev

## SEO
- No repeated disclaimer paragraphs at top of content.
- All messaging is JS-injected.

## Resilience
- If API fails or selectors missing, page still works; UI fails silently.


======================================================================
PART K — CODEX TASK LIST (IMPLEMENTATION ORDER)
======================================================================

1) Extension:
   - Add write API module to set ai_* PageProps for a title (csrf protected).
   - (Optional) Add read API module to return normalized ai_* fields.
   - Add RL module JS/CSS implementing banner + dot + tooltip UI.

2) Bot:
   - Remove disclaimer injection into wikitext.
   - Before writing translation, read ai_translation_status and enforce skip/mark-outdated logic.
   - On machine translation write, update ai_translation_source_rev and ai_translation_status=machine.

3) Wiki content:
   - Create Template:Translation_status (no visible output) and document usage.

4) Migration:
   - Backfill template and ai_* pageprops on existing translated pages.

5) Verification:
   - Test pages in each state (machine/reviewed/outdated) and confirm behavior end-to-end.
