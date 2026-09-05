import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import {test} from 'node:test';

const shell=readFileSync(new URL('../../app/public/assets/js/shell.js',import.meta.url),'utf8');
const start=shell.indexOf('  function normaliseCpdQuestionBank(');
const end=shell.indexOf('  function configureCpdApi(',start);
test('question-bank normaliser exists',()=>assert.ok(start>=0&&end>start));
const context=vm.createContext({});
if(start>=0&&end>start)vm.runInContext(shell.slice(start,end),context);
const normalise=questions=>{assert.equal(typeof context.normaliseCpdQuestionBank,'function');return context.normaliseCpdQuestionBank(questions);};
const source={explanation:'Reasoned feedback',learningOutcome:'Apply professional responsibility',sourceReference:'Owned transcript section 4',difficulty:'medium',modelAnswer:'Registered professional'};

test('serialises all supported question types without inventing options',()=>{
  const result=normalise([
    {...source,type:'single_choice',prompt:'Who signs off?',points:'1',options:[{text:'Registered professional',correct:true},{text:'AI',correct:false}]},
    {...source,type:'multiple_choice',prompt:'Select duties',points:'2.5',options:[{text:'Review',correct:true},{text:'Keep evidence',correct:true},{text:'Delegate responsibility',correct:false}]},
    {...source,type:'scenario',prompt:'What is the next step?',points:3,options:[{text:'Escalate evidence gap',correct:true},{text:'Ignore it',correct:false}]},
    {...source,type:'true_false',prompt:'A professional may delegate final responsibility to an AI system.',points:1,options:[{text:'True',correct:false},{text:'False',correct:true}]},
    {...source,type:'text_review',prompt:'Explain accountable review',points:4,options:[{text:'must be ignored',correct:true}]},
  ]);
  assert.deepEqual(result.map(question=>question.type),['single_choice','multiple_choice','scenario','true_false','text_review']);
  assert.equal(result[4].options.length,0);
  assert.equal(result[1].points,2.5);
});

test('rejects incomplete choice questions before posting them',()=>{
  assert.throws(()=>normalise([{...source,type:'single_choice',prompt:'Q',points:1,options:[{text:'Only option',correct:true}]}]),/at least two options/i);
  assert.throws(()=>normalise([{...source,type:'single_choice',prompt:'Q',points:1,options:[{text:'A',correct:true},{text:'B',correct:true}]}]),/exactly one correct/i);
  assert.throws(()=>normalise([{...source,type:'multiple_choice',prompt:'Q',points:1,options:[{text:'A',correct:false},{text:'B',correct:false}]}]),/correct option/i);
});

test('rejects missing governed question fields and invalid points',()=>{
  assert.throws(()=>normalise([{...source,type:'text_review',prompt:'',points:1,options:[]}]),/prompt/i);
  assert.throws(()=>normalise([{...source,type:'text_review',prompt:'Q',points:0,options:[]}]),/points/i);
  assert.throws(()=>normalise([{...source,type:'text_review',prompt:'Q',points:1,sourceReference:'',options:[]}]),/source reference/i);
});
