/* Any JavaScript here will be loaded for all users on every page load. */
/* DanceResource: move subpages breadcrumb into the header links row */
mw.hook('wikipage.content').add(function () {
  var subpages = document.querySelector('#mw-content-subtitle .subpages');
  var headerLinks = document.getElementById('mw-page-header-links');
  var namespaces = document.getElementById('p-namespaces');
  var views = document.getElementById('p-views');

//  if (!headerLinks || !subpages || !namespaces || !views) return;
  if (!headerLinks || !namespaces || !views) return;

  // Create a single row container (only once)
  var row = document.getElementById('dr-header-row');
  if (!row) {
    row = document.createElement('div');
    row.id = 'dr-header-row';
    headerLinks.appendChild(row);
  }

  // Move breadcrumb first (always visible)
  if (subpages && subpages.parentNode) row.appendChild(subpages);

  // Then move Page/Discussion + actions next to it (revealed on hover via CSS)
  row.appendChild(namespaces);
  row.appendChild(views);
});
