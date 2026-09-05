import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import {test} from 'node:test';

const shell=readFileSync(new URL('../../app/public/assets/js/shell.js',import.meta.url),'utf8');
const start=shell.indexOf('  function cpdAwardLabel(');
const end=shell.indexOf('  function configureCpdApi(',start);
test('CPD award formatter exists',()=>assert.ok(start>=0&&end>start));
const context=vm.createContext({});
if(start>=0&&end>start)vm.runInContext(shell.slice(start,end),context);
const label=(...args)=>{assert.equal(typeof context.cpdAwardLabel,'function');return context.cpdAwardLabel(...args);};
test('catalogue uses configured hours, points and credits',()=>{
  for(const [body,unit] of [['SACQSP','hours'],['SACPLAN','points'],['SACAP','credits']])
    assert.equal(label('1.5',body,[{bodyCode:body,unitLabel:unit}]),`1.5 ${unit}`);
});
test('issued records retain their snapshot unit when live rules change',()=>{
  assert.equal(label('3','SACPLAN',[{bodyCode:'SACPLAN',unitLabel:'changed'}],{unitLabel:'points'}),'3 points');
});
test('manual compliance does not invent a numerical award',()=>{
  assert.equal(label('0','SACPVP',[{bodyCode:'SACPVP',unitLabel:'compliance record',calculationPolicy:'manual_compliance_only'}]),'Compliance record');
});
test('missing rule does not guess credits',()=>{
  assert.equal(label('2','UNKNOWN',[]),'2 (unit pending confirmation)');
});
