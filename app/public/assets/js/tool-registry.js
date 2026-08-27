(function (global) {
  'use strict';
  global.ArchitexToolRegistry = Object.freeze({ reconcile(local, remote) {
    const byId = new Map((remote || []).map(item => [item.id, item]));
    return Object.freeze(Object.keys(local).map(id => Object.freeze({ id, ...local[id], deliveryStatus:byId.get(id)?.status || 'unknown' })));
  }});
})(window);
