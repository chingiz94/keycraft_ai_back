const fs=require('fs');
const path=require('path');
const {execFileSync}=require('child_process');
const root=path.resolve(__dirname,'..');
const spec=JSON.parse(fs.readFileSync(path.join(root,'docs/openapi.json'),'utf8'));
const errors=[];
const ids=new Set();
let count=0;
function walk(value,where) {
 if(!value||typeof value!=='object')return;
 if(value.$ref) {
  const target=value.$ref.split('/').slice(1).reduce((a,b)=>a?.[b],spec);
  if(!target)errors.push('Broken reference '+where+' '+value.$ref);
 }
 for(const [key,v]of Object.entries(value))walk(v,where+'/'+key);
}
walk(spec,'root');
for(const [url,methods]of Object.entries(spec.paths))for(const [method,op]of Object.entries(methods)) {
 count++;
 if(ids.has(op.operationId))errors.push('Duplicate operationId '+op.operationId);ids.add(op.operationId);
 for(const part of url.match(/\{\w+\}/g)||[])if(!op.parameters?.some(p=>p.in==='path'&&p.name===part.slice(1,-1)&&p.required))errors.push('Missing path parameter '+url);
 if(!op.responses[200]&&!op.responses[201]&&!op.responses[204])errors.push('No success '+url);
}
const routes=JSON.parse(execFileSync(process.env.PHP_BINARY || 'php',['artisan','route:list','--path=api','--json','--no-interaction'],{cwd:root,encoding:'utf8'}));
let routeCount=0;
for(const route of routes.filter(route=>route.uri.startsWith('api/v1/')))for(const method of route.method.split('|').filter(m=>m!=='HEAD')) {
 const url='/'+route.uri.replace('api/v1/','');routeCount++;
 if(!spec.paths[url]?.[method.toLowerCase()])errors.push('Undocumented route '+method+' '+url);
}
console.log(JSON.stringify({openapiVersion:spec.openapi,documentedOperations:count,actualOperations:routeCount,errors},null,2));
if(errors.length)process.exit(1);

