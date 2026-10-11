(function () {
  'use strict';
  // Exhaustive production workspace icon contract (all 49 tool IDs).
  // The clone assembles 47 tools; drawing compliance and valuations are
  // production-only entries, explicitly mapped without inventing clone tools.
  // Each mapping resolves compiled canonical geometry and records its SVG source.
  var MAP = {
    "admin_review": "admin_review",
    "approvals_queue": "approvals_queue",
    "bim_ifc": "bimQuantities",
    "bom": "bom",
    "compliance_hub": "compliance_hub",
    "contract_admin": "contract_admin",
    "contractor_compliance": "contractor_compliance",
    "council_navigator": "council_navigator",
    "cpd_learning": "cpd_learning",
    "dispute_resolution": "dispute_resolution",
    "documents_drawings": "documents_drawings",
    "drawing_compliance_navigator": "council_navigator",
    "eia_workspace": "eiaWorkspace",
    "engineering_calc": "engineering_hub",
    "environmental_heritage": "environmental_heritage",
    "fee_proposal": "fee_proposal",
    "feedback": "feedback",
    "fm_bridge": "fm_bridge",
    "forms": "formSystem",
    "iconography_registry": "iconography_registry",
    "inbox_action": "action_centre",
    "insurance_register": "insurance_register",
    "issues_rfis": "issues_rfis",
    "itp": "itp",
    "market_insights": "market_insights",
    "meetings": "meetings",
    "municipal": "municipal",
    "municipal_tracker": "municipal_tracker",
    "ncr_manager": "ncr_manager",
    "nhbrc_enrolment": "nhbrc_enrolment",
    "payments_escrow": "payments_escrow",
    "planning": "planning",
    "practice": "practiceManagement",
    "professional_directory": "professional_directory",
    "project_explorer": "project_explorer",
    "project_passport": "project_passport",
    "refuse_calculator": "municipalRefuseCalculator",
    "remote_desktop": "remoteDesktop",
    "rfq_marketplace": "rfqMarketplace",
    "safety": "safety",
    "site_instructions": "site_instructions",
    "snag_manager": "snag_manager",
    "specforge": "specification",
    "supplier_catalog": "supplier_catalog",
    "survey_geomatics": "survey_geomatics",
    "team_workspace": "team_workspace",
    "valuations": "valuation",
    "wingman": "wingman",
    "xa": "xa"
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
      tool.iconSource = P.meta[key].file;
    });

    window.__architexIconMap = {
      applied: true,
      engine: 'architex_svg_icon_pack-2 compiled into the v1.html .ax-icon role model',
      toolIcons: MAP,
      mapped: Object.keys(MAP).filter(function(id){return !!T[id]}),
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
