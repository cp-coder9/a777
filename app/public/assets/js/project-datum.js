(function (global) {
  'use strict';
  const phaseLabels = Object.freeze({ onboarding:'Brief', feasibility:'Brief', appointment:'Appoint', concept_design:'Design', design_development:'Design', municipal_submission:'Comply', tender_procurement:'Procure', construction_execution:'Build', closeout:'Close' });
  global.ArchitexDatum = Object.freeze({
    apply(target, source) {
      if (!source) return target;
      Object.assign(target, { id:source.id, name:source.name, client:source.clientName, municipality:source.municipality, location:source.propertyReference, professional:source.leadProfessionalRole, stage:phaseLabels[source.phase] || 'Brief', revision:'v' + source.version, progress:source.mayAdvance ? 100 : Math.max(10, 100 - (source.missingRecords?.length || 0) * 20) });
      return target;
    }
  });
})(window);
