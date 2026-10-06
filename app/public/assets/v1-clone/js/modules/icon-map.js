(function () {
  'use strict';
  // Tool icon mapping — makes the supplied icon pack the source of art for the
  // module families the pack was drawn for.
  //
  // The pack (architex_svg_icon_pack-2) was produced to fill the gaps called out in
  // ARCHITEX_ICONOGRAPHY_MISSING_SERVICE_ICONS_ADDENDUM.md: Practice Management,
  // Integrated Form System, BIM/IFC quantities, RFQ marketplace, EIA workspace,
  // Remote Desktop and the municipal refuse calculator previously had no dedicated
  // icon and fell back to a generic glyph. Here each of those tools is pointed at
  // its real pack icon, so the navigation, the tool registry, the datum cards and the
  // registry catalogue all render the same shape.
  //
  // Everything else keeps the v1.html icon registry untouched: this is an additive
  // mapping, not a replacement of the design reference.
  var MAP = {
    practice: 'practiceManagement',
    forms: 'formSystem',
    bim_ifc: 'bimQuantities',
    rfq_marketplace: 'rfqMarketplace',
    eia_workspace: 'eiaWorkspace',
    remote_desktop: 'remoteDesktop',
    refuse_calculator: 'municipalRefuseCalculator'
  };

  function apply() {
    var T = (typeof TOOLS !== 'undefined' && TOOLS) || window.TOOLS || null;
    var P = window.ARCHITEX_ICON_PACK;
    if (!T || !P || !P.shapes) return false;

    var changed = [];
    Object.keys(MAP).forEach(function (id) {
      var tool = T[id], key = MAP[id];
      if (!tool || !P.shapes[key]) return;
      if (tool.icon !== key) {
        changed.push(id + ': ' + tool.icon + ' -> ' + key);
        tool.icon = key;
      }
      tool.iconSource = 'architex_svg_icon_pack-2';
    });

    window.__architexIconMap = {
      applied: true,
      engine: 'architex_svg_icon_pack-2 compiled into the v1.html .ax-icon role model',
      mapped: Object.keys(MAP),
      changed: changed
    };

    // If the shell already painted before this file ran, repaint so the new icons
    // show. During a normal load this file executes before the runtime bootstraps,
    // so no repaint is needed.
    var st = null;
    try {
      var doc = (typeof document !== 'undefined') ? document : (window.document || null);
      st = doc && doc.body ? doc.body.getAttribute('data-v1-clone-state') : null;
    } catch (e) { st = null; }
    if (changed.length && st && st !== 'loading' && typeof window.renderAll === 'function') {
      try { window.renderAll(); } catch (e) { /* runtime owns the paint; ignore */ }
    }
    return true;
  }

  if (!apply()) {
    var n = 0, t = setInterval(function () { if (apply() || ++n > 200) clearInterval(t); }, 25);
  }
})();
