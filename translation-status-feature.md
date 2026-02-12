# Translation Status Banner Feature (for AiTranslationExtension)

## Purpose
This feature adds a **client-side translation status banner** for translated wiki pages.

It is designed to work with the bot's new metadata model:
- `{{Translation_status|status=machine|...}}` in translated page content (segment 1)
- Optional PageProps keys (if/when available):
  - `dr_translation_status`
  - `dr_source_rev_at_translation`
  - `dr_reviewed_at`
  - `dr_reviewed_by`
  - `dr_outdated_source_rev`

The banner must be rendered via JavaScript, not via visible disclaimer text inside article content.

---

## Behavior
1. Run only on translated content pages (title suffix like `/de`, `/sr`, etc.).
2. Read status from `pageprops` first (`dr_translation_status`).
3. If missing, fallback to parsing `{{Translation_status|status=...}}` from page wikitext.
4. Render one of:
- `machine`: `Machine translation. Help review this page.`
- `reviewed`: `Human reviewed translation.`
- `outdated`: `Translation is outdated compared to the English source. Update needed.`
5. Include links:
- Edit current page
- Open source (English) page

---

## Notes for Implementation
- Inject this JS from the extension (preferred) instead of editing `MediaWiki:Common.js`.
- Keep the script idempotent (do not insert multiple banners).
- Keep fallback to template parsing because some installs may not expose custom `pageprops` yet.
- Namespace guard: only main namespace (`wgNamespaceNumber === 0`) unless you intentionally expand.

---

## JavaScript (ready to use)

```js
/* DanceResource translation status banner
 * - Only on translated subpages like /de, /sr, /fr...
 * - Reads status from pageprops (dr_translation_status)
 * - Fallback: parses {{Translation_status|status=...}} from page wikitext
 * - Renders banner above article content
 */
(function () {
  if (mw.config.get("wgNamespaceNumber") !== 0) return;

  var pageName = mw.config.get("wgPageName") || "";
  var langMatch = pageName.match(/\/([a-z]{2,3}(?:-[a-z0-9]+)?)$/i);
  if (!langMatch) return;

  var sourcePage = pageName.replace(/\/[a-z]{2,3}(?:-[a-z0-9]+)?$/i, "");
  var api = new mw.Api();

  function parseStatusFromTemplate(wikitext) {
    var m = wikitext.match(/\{\{\s*Translation_status\s*\|([^}]+)\}\}/i);
    if (!m) return null;

    var params = {};
    m[1].split("|").forEach(function (part) {
      var idx = part.indexOf("=");
      if (idx === -1) return;
      var key = part.slice(0, idx).trim();
      var value = part.slice(idx + 1).trim();
      params[key] = value;
    });

    return params.status || null;
  }

  function bannerText(status) {
    if (status === "reviewed") {
      return "Human reviewed translation.";
    }
    if (status === "outdated") {
      return "Translation is outdated compared to the English source. Update needed.";
    }
    return "Machine translation. Help review this page.";
  }

  function render(status) {
    var s = status || "machine";
    var editUrl = mw.util.getUrl(pageName, { action: "edit" });
    var sourceUrl = mw.util.getUrl(sourcePage);

    // Prevent duplicate banners
    if (document.querySelector(".dr-translation-status")) return;

    var box = document.createElement("div");
    box.className = "dr-translation-status dr-translation-status-" + s;
    box.style.cssText = "border:1px solid #c8ccd1;padding:10px 12px;margin:8px 0;background:#f8f9fa;";

    box.innerHTML =
      "<strong>" + mw.html.escape(bannerText(s)) + "</strong>" +
      ' <a href="' + editUrl + '">Edit</a>' +
      ' · <a href="' + sourceUrl + '">Source</a>';

    var content = document.getElementById("mw-content-text");
    if (content && content.parentNode) {
      content.parentNode.insertBefore(box, content);
    }
  }

  api.get({
    action: "query",
    prop: "pageprops|revisions",
    rvprop: "content",
    rvslots: "main",
    titles: pageName
  }).done(function (data) {
    var pages = data && data.query && data.query.pages;
    if (!pages) return;

    var page = pages[Object.keys(pages)[0]];
    var status = page.pageprops && page.pageprops.dr_translation_status;

    if (!status) {
      var rev = page.revisions && page.revisions[0];
      var text = (rev && rev.slots && rev.slots.main && rev.slots.main.content) || "";
      status = parseStatusFromTemplate(text) || "machine";
    }

    render(status);
  });
})();
```

---

## Template Requirement
Ensure this template exists and renders no visible output:

```wikitext
<includeonly><!-- Translation_status: status={{{status|machine}}};source_rev_at_translation={{{source_rev_at_translation|}}};reviewed_at={{{reviewed_at|}}};reviewed_by={{{reviewed_by|}}};outdated_source_rev={{{outdated_source_rev|}}} --></includeonly><noinclude>
Internal metadata template for translation status.
</noinclude>
```

---

## Acceptance Checklist
- No visible disclaimer paragraph inside translated article body.
- Banner appears on translated pages.
- Banner text changes by `machine/reviewed/outdated`.
- If `pageprops` missing, fallback parsing from template still works.
- No duplicate banner insertion.
