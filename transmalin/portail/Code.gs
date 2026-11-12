// Portail existant repris le 12/11/2026. Pas encore déployé.
const CONFIG={SHEET_NAME:'Réponses chauffeurs',MISSION_SHEET:'Courses ATS',SESSION_TTL_SECONDS:3600};
function doGet(){return HtmlService.createTemplateFromFile('Index').evaluate().setTitle('Trans Malin — Chauffeurs').addMetaTag('viewport','width=device-width, initial-scale=1');}
function include(filename){return HtmlService.createHtmlOutputFromFile(filename).getContent();}
function props_(){return PropertiesService.getScriptProperties();}
function db_(){const id=props_().getProperty('SPREADSHEET_ID');if(!id)throw Error('Configuration manquante.');return SpreadsheetApp.openById(id);}
function users_(){return JSON.parse(props_().getProperty('DRIVER_ACCOUNTS_JSON')||'{}');}
function digest_(s){return Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256,s).map(x=>('0'+((x+256)%256).toString(16)).slice(-2)).join('');}
function safeEqual_(a,b){a=String(a);b=String(b);let d=a.length^b.length;for(let i=0;i<Math.max(a.length,b.length);i++)d|=(a.charCodeAt(i)||0)^(b.charCodeAt(i)||0);return d===0;}
function login(email,password){
 email=String(email||'').trim().toLowerCase();password=String(password||'');
 if(email.length>254||password.length>256)throw Error('Connexion refusée.');
 const lock=LockService.getScriptLock();lock.waitLock(15000);
 try{
  const key='attempt:'+digest_(email),p=props_(),now=Date.now();let a=JSON.parse(p.getProperty(key)||'{"count":0,"until":0}');
  if(a.until<now)a={count:0,until:now+900000};
  if(a.count>=5)throw Error('Réessayez dans quinze minutes.');
  a.count++;p.setProperty(key,JSON.stringify(a));
  const u=users_()[email];const calculated=digest_((u?u.salt:'invalid')+':'+password);
  if(!u||!u.active||password.length<24||!safeEqual_(calculated,u.hash))throw Error('Connexion refusée.');
  p.deleteProperty(key);const token=Utilities.getUuid()+Utilities.getUuid();
  CacheService.getScriptCache().put('session:'+digest_(token),JSON.stringify({email,version:u.version}),CONFIG.SESSION_TTL_SECONDS);
  return {token,driver:u.label};
 }finally{lock.releaseLock();}
}
function validateSession_(token){
 const raw=CacheService.getScriptCache().get('session:'+digest_(String(token||'')));if(!raw)throw Error('Reconnectez-vous.');
 const s=JSON.parse(raw),u=users_()[s.email];if(!u||!u.active||u.version!==s.version)throw Error('Reconnectez-vous.');return u;
}
function logout(token){CacheService.getScriptCache().remove('session:'+digest_(String(token||'')));}
function sheet_(name){const s=db_().getSheetByName(name);if(!s)throw Error('Feuille non configurée.');return s;}
function missionRows_(){const s=sheet_(CONFIG.MISSION_SHEET);return s.getLastRow()<5?[]:s.getRange(5,1,s.getLastRow()-4,26).getDisplayValues();}
function owned_(u,id){const all=missionRows_().filter(r=>r[16]===id);if(all.length!==1||all[0][2]!==u.missionLabel||all[0][20]!=='Active')throw Error('Mission indisponible pour ce compte.');return all[0];}
function listMissions(token){const u=validateSession_(token);return missionRows_().filter(r=>r[2]===u.missionLabel&&r[20]==='Active'&&r[16]).map(r=>({id:r[16],date:r[0],time:r[17],from:r[4],to:r[5]}));}
function history(token){const u=validateSession_(token),s=sheet_(CONFIG.SHEET_NAME);if(s.getLastRow()<2)return [];return s.getRange(2,1,s.getLastRow()-1,25).getDisplayValues().filter(r=>r[1]===u.id).slice(-50).reverse().map(r=>({date:r[3],mission:r[22],start:r[20],end:r[21],km:r[15],incident:r[10]}));}
function clean_(x,max){const s=String(x||'').trim();if(s.length>max)throw Error('Texte trop long.');return /^[=+@\-]/.test(s)?"'"+s:s;}
function num_(x,max){if(x===''||x===null||x===undefined)throw Error('Valeur requise.');const n=Number(x);if(!Number.isFinite(n)||n<0||n>max)throw Error('Valeur numérique invalide.');return n;}
function date_(x){if(!/^\d{4}-\d{2}-\d{2}$/.test(x)||new Date(x+'T12:00:00Z').toISOString().slice(0,10)!==x)throw Error('Date invalide.');return x;}
function time_(x){if(!/^\d{2}:\d{2}$/.test(x)||+x.slice(0,2)>23||+x.slice(3)>59)throw Error('Horaire invalide.');return x;}
function validate_(p,m){
 date_(p.routeDate);time_(p.start);time_(p.end);date_(p.endDate);
 if(p.routeDate!==m[0])throw Error('Date différente de la mission attribuée.');
 const start=Date.parse(p.routeDate+'T'+p.start+':00Z'),end=Date.parse(p.endDate+'T'+p.end+':00Z');
 if(end<start||end-start>86400000)throw Error('Durée invalide.');
 const kmStart=num_(p.kmStart,2000000),kmEnd=num_(p.kmEnd,2000000);if(kmEnd<kmStart||kmEnd-kmStart>3000)throw Error('Kilométrage incohérent.');
 return {kmStart,kmEnd,litres:num_(p.litres||0,1000),fuel:num_(p.fuel||0,3000)};
}
function submitMission(p){
 p=p||{};const u=validateSession_(p.token);if(!/^[a-zA-Z0-9-]{16,80}$/.test(p.requestId||''))throw Error('Identifiant de saisie invalide.');
 const lock=LockService.getScriptLock();lock.waitLock(20000);
 try{
  const m=owned_(u,p.missionId),v=validate_(p,m),s=sheet_(CONFIG.SHEET_NAME),headers=s.getRange(1,1,1,25).getDisplayValues()[0];
  if(headers[1]!=='Identifiant chauffeur'||headers[22]!=='Mission / référence'||headers[24]!=='ID soumission Jotform')throw Error('Structure du tableau incompatible.');
  const rows=s.getLastRow()>1?s.getRange(2,1,s.getLastRow()-1,25).getDisplayValues():[];
  const old=rows.find(r=>r[1]===u.id&&(r[24]===p.requestId||r[22]===p.missionId));
  if(old)return {ok:true,recordId:old[24],duplicate:true};
  const proof=saveProof_(p.proof,u.id,p.requestId);
  s.appendRow([new Date(),u.id,clean_(u.label,80),p.routeDate,clean_(m[4]+' → '+m[5],240),'','','',v.kmStart,v.kmEnd,p.incident?'Oui':'Non',clean_(p.comment,1000),v.litres,v.fuel,proof,v.kmEnd-v.kmStart,v.litres?v.fuel/v.litres:'',proof?'À vérifier':'Non fourni','ATS / STERNE',clean_(m[3],40),p.routeDate+' '+p.start,p.endDate+' '+p.end,p.missionId,clean_(m[4]+' → '+m[5],240),p.requestId]);
  SpreadsheetApp.flush();return {ok:true,recordId:p.requestId};
 }finally{lock.releaseLock();}
}
function saveProof_(proof,driver,id){
 if(!proof||!proof.data)return '';
 const folderId=props_().getProperty('PROOF_FOLDER_ID');if(!folderId)throw Error('Dossier de justificatifs non configuré.');
 const mime=String(proof.mime);if(!/^image\/(jpeg|png|webp)$/.test(mime)||String(proof.data).length>7100000)throw Error('Justificatif refusé.');
 const bytes=Utilities.base64Decode(String(proof.data).split(',').pop());if(bytes.length>5242880)throw Error('Maximum 5 Mo.');
 const magic=bytes.slice(0,12).map(b=>(b+256)%256);
 const valid=mime==='image/jpeg'?magic[0]===255&&magic[1]===216:mime==='image/png'?magic.slice(0,8).join(',')==='137,80,78,71,13,10,26,10':String.fromCharCode(...magic.slice(0,4))==='RIFF'&&String.fromCharCode(...magic.slice(8,12))==='WEBP';
 if(!valid)throw Error('Format du fichier incohérent.');
 const folder=DriveApp.getFolderById(folderId);if(folder.getSharingAccess()!==DriveApp.Access.PRIVATE)throw Error('Dossier des justificatifs non privé.');
 const name='tm-'+driver+'-'+id+'.'+mime.split('/')[1];const files=folder.getFilesByName(name);if(files.hasNext())return files.next().getUrl();
 const file=folder.createFile(Utilities.newBlob(bytes,mime,name));file.setSharing(DriveApp.Access.PRIVATE,DriveApp.Permission.NONE);return file.getUrl();
}
