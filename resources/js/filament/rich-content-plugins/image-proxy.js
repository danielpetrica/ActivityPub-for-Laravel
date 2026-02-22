/**
 * Filament admin RichEditor image proxy helper.
 * Rewrites Hetzner Object Storage image URLs to a same-origin proxy in admin,
 * avoiding CORS errors while editing posts.
 */
(function () {
  const HETZNER_PREFIX = '/danielpetrica_com/files/';
  const ADMIN_PROXY = '/admin/media-proxy?path=';

  /**
   * Extracts the object key relative to the bucket prefix.
   * Returns null if it can't parse.
   */
  function extractKey(url) {
    try {
      const u = new URL(url, window.location.href);
      const path = u.pathname; // e.g. /grozav-dev-object-storage/danielpetrica_com/files/media/feature/2026/...png
      const idx = path.indexOf(HETZNER_PREFIX);
      if (idx === -1) return null;
      return path.substring(idx + HETZNER_PREFIX.length);
    } catch (_) {
      return null;
    }
  }

  function rewriteImg(img) {
    if (!img || !img.getAttribute) return;
    const src = img.getAttribute('src');
    if (!src) return;

    // Only rewrite if it looks like a Hetzner object URL containing the known prefix.
    if (src.includes(HETZNER_PREFIX)) {
      const key = extractKey(src);
      if (key) {
        img.setAttribute('src', ADMIN_PROXY + encodeURIComponent(key));
      }
    }
  }

  function scan(root) {
    root.querySelectorAll('img').forEach(rewriteImg);
  }

  function isEditorRoot(node) {
    if (!(node instanceof HTMLElement)) return false;
    // Common rich editor containers in Filament/TipTap
    return node.classList.contains('tiptap') || node.classList.contains('ProseMirror') || node.closest('.fi-fo-rich-editor');
  }

  // Initial pass once DOM is ready.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => scan(document));
  } else {
    scan(document);
  }

  // Observe future mutations within editor areas.
  const mo = new MutationObserver((mutations) => {
    for (const m of mutations) {
      if (m.type === 'childList') {
        m.addedNodes.forEach((n) => {
          if (n.nodeType === 1) {
            const el = /** @type {HTMLElement} */ (n);
            if (isEditorRoot(el)) {
              scan(el);
            } else if (el.matches && el.matches('img')) {
              rewriteImg(el);
            } else if (el.querySelector) {
              scan(el);
            }
          }
        });
      } else if (m.type === 'attributes' && m.target && m.target.matches && m.target.matches('img') && m.attributeName === 'src') {
        rewriteImg(/** @type {HTMLElement} */ (m.target));
      }
    }
  });

  mo.observe(document.documentElement, { subtree: true, childList: true, attributes: true, attributeFilter: ['src'] });
})();
