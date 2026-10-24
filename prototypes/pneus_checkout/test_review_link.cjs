const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const page = fs.readFileSync(__dirname + '/review_page.php', 'utf8');
const source = page.slice(page.indexOf('let opening=false;'), page.indexOf('\n</script></html>'));
const flush = () => new Promise(resolve => setImmediate(resolve));
function environment(hash, fetcher = async () => ({ok:true})) {
  const state = {requests:[], redirects:[], handlers:{}, status:{textContent:''}};
  const location = {hash, pathname:'/pneus-essai.php', replace:path=>state.redirects.push(path)};
  const context = {location, URLSearchParams,
    history:{replaceState:()=>{location.hash='';}},
    document:{documentElement:{dataset:{}}, getElementById:()=>state.status},
    window:{addEventListener:(name, callback)=>{state.handlers[name]=callback;}},
    fetch:async (url, options)=>{state.requests.push(options);return fetcher(url,options);}};
  vm.runInNewContext(source, context);
  return {state,context,location};
}
(async()=>{
  let e=environment('');await flush();assert.equal(e.state.requests.length,0);
  e=environment('#'+'a'.repeat(64));await flush();
  assert.equal(e.context.document.documentElement.dataset.opening,'1');
  assert.equal(e.state.requests.length,1);assert.equal(e.state.redirects.length,1);
  assert.equal(e.state.requests[0].body.get('action'),'open');
  e=environment('#invalid');await flush();assert.equal(e.state.requests.length,0);
  assert.match(e.state.status.textContent,/pas valide/);
  assert.equal(e.context.document.documentElement.dataset.opening,'1');
  e=environment('#'+'a'.repeat(64),async()=>({ok:false}));await flush();
  assert.equal(e.state.redirects.length,0);assert.match(e.state.status.textContent,/expiré/);
  e=environment('#'+'a'.repeat(64),async()=>{throw Error('offline');});await flush();
  assert.equal(e.state.redirects.length,0);assert.match(e.state.status.textContent,/réessayer/);
  e=environment('');e.location.hash='#'+'b'.repeat(64);await e.state.handlers.hashchange();
  assert.equal(e.state.requests[0].body.get('key'),'b'.repeat(64));
  let resolveFirst;
  e=environment('#'+'a'.repeat(64),()=>new Promise(resolve=>{resolveFirst=resolve;}));
  e.location.hash='#'+'b'.repeat(64);await e.state.handlers.hashchange();
  resolveFirst({ok:true});await flush();
  assert.equal(e.state.requests.length,2);
  assert.equal(e.state.requests[1].body.get('key'),'b'.repeat(64));
  resolveFirst({ok:true});await flush();assert.equal(e.state.redirects.length,1);
  assert.match(page,/\[data-opening="1"\] #quote-view\{display:none\}/);
  assert.ok(page.indexOf("document.documentElement.dataset.opening=location.hash")<page.indexOf('<main'));
  console.log('REVIEW_LINK_TEST_OK cases=7');
})().catch(error=>{console.error(error);process.exitCode=1;});
