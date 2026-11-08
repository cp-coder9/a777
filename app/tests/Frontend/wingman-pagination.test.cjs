'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../../public/assets/v1-clone/js/adapters/wingman.js'), 'utf8');
const conversationId = 'a'.repeat(32);
const cursor = 'b'.repeat(32);

test('older pages use scoped and validated conversation cursors', async () => {
  const window = {};
  vm.runInNewContext(source, { window });
  const calls = [];
  const context = {
    getState: () => ({ready: true, organisationId: 'org', session: {userId: 'user'}, selectedProjectId: null}),
    async read(url) {
      calls.push(url);
      if (url.includes('/bootstrap')) return {context: {projectId: null}, conversations: [{id: conversationId, projectId: null}]};
      return {id: conversationId, projectId: null, messages: []};
    },
  };
  const adapter = window.V1CloneWingmanAdapter.create({context});
  await assert.rejects(adapter.load(conversationId, cursor), /scope cannot be verified/);
  await adapter.bootstrap();
  await adapter.load(conversationId, cursor);
  assert.equal(calls[1], `/api/v1/wingman/conversations/${conversationId}?before=${cursor}`);
  await assert.rejects(adapter.load(conversationId, 'not-an-id'), /Invalid conversation cursor/);
  assert.equal(calls.length, 2);
});
