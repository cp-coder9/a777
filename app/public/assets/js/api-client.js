(function (global) {
  'use strict';
  class ApiError extends Error {
    constructor(status, payload) { super(payload?.error?.message || 'Request failed.'); this.status = status; this.code = payload?.error?.code || 'request_failed'; }
  }
  class ApiClient {
    constructor(base = '/api/v1') { this.base = base; this.organisationId = null; this.csrfToken = null; }
    setOrganisation(id) { this.organisationId = id || null; }
    setCsrf(token) { this.csrfToken = token || null; }
    async request(method, path, body) {
      const headers = { Accept: 'application/json' };
      if (this.organisationId) headers['X-Organisation-Id'] = this.organisationId;
      if (body !== undefined) headers['Content-Type'] = 'application/json';
      if (!['GET', 'HEAD', 'OPTIONS'].includes(method) && this.csrfToken) headers['X-CSRF-Token'] = this.csrfToken;
      const response = await fetch(this.base + path, { method, headers, credentials: 'same-origin', body: body === undefined ? undefined : JSON.stringify(body) });
      const payload = await response.json().catch(() => null);
      if (!response.ok) throw new ApiError(response.status, payload);
      if (!payload || !Object.prototype.hasOwnProperty.call(payload, 'data')) {
        throw new ApiError(response.status, { error: { code: 'invalid_api_response', message: 'The server returned an invalid API response.' } });
      }
      return payload.data;
    }
    get(path) { return this.request('GET', path); }
    post(path, body) { return this.request('POST', path, body); }
  }
  global.ArchitexApi = Object.freeze({ ApiClient, ApiError });
})(window);
