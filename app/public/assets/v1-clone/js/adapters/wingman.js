(function (global) {
  'use strict';
  const identifier = value => typeof value === 'string' && /^[a-f0-9]{32}$/i.test(value);
  function create({context}) {
    let busy = false, disposed = false;
    const scopes = new Map(), owners = new Map();
    const scopeKey = () => { const s = context.getState(); return JSON.stringify([s.organisationId,s.session?.userId,s.selectedProjectId || null]); };
    function current() {
      if (disposed || !context.getState().ready) throw Error('Wingman context is not ready or is disposed.');
      const key = scopeKey();
      if (!scopes.has(key)) scopes.set(key,{conversationId:null,pending:null,conversations:[],availability:null});
      return {key, data:scopes.get(key), projectId:context.getState().selectedProjectId || null};
    }
    function check(key) { if (disposed || key !== scopeKey()) throw Error('Wingman context changed. The previous result was not shown.'); }
    function conversationPath(id) {
      if (!identifier(id)) throw Error('Conversation response could not be verified: invalid identifier.');
      return '/api/v1/wingman/conversations/' + id;
    }
    async function verify(saved, scope) {
      const record = await context.read(conversationPath(saved.conversationId)); check(scope.key);
      if (!Object.hasOwn(record || {},'projectId') || record.projectId !== scope.projectId) throw Error('Conversation project scope did not match the selected project.');
      const message = record?.messages?.find(m => m.id === saved.message?.id);
      if (record?.id !== saved.conversationId || !message || message.role !== 'assistant' || message.status !== 'complete' || !message.content || message.content !== saved.message.content) {
        throw Error('The provider reply could not be verified against the persisted conversation. Retry to read back without resending.');
      }
      scope.data.conversationId = record.id; scope.data.pending = null; owners.set(record.id,scope.key);
      if (!scope.data.conversations.some(c => c.id === record.id)) scope.data.conversations.unshift({id:record.id,title:record.messages.find(m=>m.role==='user')?.content?.slice(0,80) || 'Conversation'});
      return record;
    }
    async function bootstrap() {
      const scope = current();
      const result = await context.read('/api/v1/wingman/bootstrap?route=wingman' + (scope.projectId ? '&projectId=' + encodeURIComponent(scope.projectId) : ''));
      check(scope.key);
      if (!Object.hasOwn(result?.context || {},'projectId') || result.context.projectId !== scope.projectId) throw Error('Wingman bootstrap project scope did not match the selected project.');
      const history = Array.isArray(result.conversations) ? result.conversations : [];
      const verified = history.filter(c=>identifier(c?.id) && Object.hasOwn(c,'projectId') && c.projectId === scope.projectId);
      for (const conversation of verified) owners.set(conversation.id,scope.key);
      scope.data.conversations=verified;
      scope.data.availability=result.availability || null;
      return {...result,conversations:verified.slice(),withheldConversations:history.length-verified.length};
    }
    async function send({prompt,capability = ''}) {
      const scope = current();
      if (busy) throw Error('A Wingman request is pending.');
      if (scope.data.availability?.configured === false) throw Error('configuration_required: Wingman AI is not configured on this server. Your draft has been kept.');
      prompt = String(prompt || '').trim();
      const prepared = capability ? capability + ':\n' + prompt : prompt;
      if (!prompt || [...prepared].length > 4000) throw Error('Enter between 1 and 4,000 characters including the capability.');
      busy = true;
      try {
        if (scope.data.pending) {
          if (scope.data.pending.prompt !== prepared) throw Error('The previous request is unresolved. Restore the original prompt to verify it, or explicitly start a new conversation. Your changed draft has not been sent.');
          if (scope.data.pending.saved) return await verify(scope.data.pending.saved,scope);
          throw Error('The previous request outcome is uncertain. Start a new conversation explicitly before sending again; the previous prompt may already be stored.');
        }
        scope.data.pending = {saved:null,prompt:prepared};
        let saved;
        try {
          saved = await context.write('/api/v1/wingman/messages',{
            prompt:prepared,route:'wingman',projectId:scope.projectId,
            ...(scope.data.conversationId ? {conversationId:scope.data.conversationId} : {}),
          },{projectScoped:!!scope.projectId,readBackPath:response=>{
            if (scope.data.pending) scope.data.pending.saved = response;
            return conversationPath(response.conversationId);
          }});
        } catch (error) {
          // HTTP status alone cannot prove non-acceptance: even 400/403/429 can follow prompt persistence.
          throw Error((error.code ? error.code + ': ' : '') + (error.message || 'Wingman request failed.') + ' Your draft has been kept.');
        }
        check(scope.key);scope.data.pending.saved = saved;
        return await verify(saved,scope);
      } finally { busy = false; }
    }
    async function load(id) {
      const scope = current();
      if (busy) throw Error('A Wingman request is pending.');
      if (owners.get(id) !== scope.key) throw Error('Conversation project scope cannot be verified in this session.');
      busy=true;
      try {
        const record = await context.read(conversationPath(id));check(scope.key);
        if (!Object.hasOwn(record || {},'projectId') || record.projectId !== scope.projectId) throw Error('Conversation project scope did not match the selected project.');
        if (record?.id !== id || !Array.isArray(record.messages)) throw Error('Conversation readback did not match.');
        scope.data.conversationId = id;return record;
      } finally { busy=false; }
    }
    function newConversation() {
      if (busy) throw Error('A Wingman request is pending.');
      const scope = current();scope.data.conversationId = null;scope.data.pending = null;
    }
    return {bootstrap,send,load,newConversation,scopeKey,isBusy:()=>busy,dispose(){disposed=true;scopes.clear();owners.clear();}};
  }
  global.V1CloneWingmanAdapter = {create};
})(window);
