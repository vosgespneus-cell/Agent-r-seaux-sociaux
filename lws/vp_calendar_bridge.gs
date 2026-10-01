// LWS prepares the jobs. This bridge only imports approved, complete appointments.
const VP_ENDPOINT = 'https://agents.vosgespneus.com/calendar.php';
const VP_CALENDAR = 'vosgespneus@gmail.com';

function vpRequest_(data) {
  const key = PropertiesService.getScriptProperties().getProperty('VP_BRIDGE_SECRET');
  if (!key) throw new Error('Connexion LWS manquante');
  const body = JSON.stringify(data), ts = String(Math.floor(Date.now()/1000));
  const signature = Utilities.computeHmacSha256Signature(ts+'.'+body,key,Utilities.Charset.UTF_8)
    .map(b => ('0'+((b+256)%256).toString(16)).slice(-2)).join('');
  const response = UrlFetchApp.fetch(VP_ENDPOINT,{method:'post',contentType:'application/json',payload:body,
    headers:{'X-VP-Timestamp':ts,'X-VP-Signature':signature},muteHttpExceptions:true,followRedirects:false});
  if(response.getResponseCode()!==200)throw new Error('LWS HTTP '+response.getResponseCode());
  return JSON.parse(response.getContentText());
}
function vpCheck() {
  const calendar=CalendarApp.getCalendarById(VP_CALENDAR);
  if(!calendar)throw new Error('Agenda Vosges Pneus inaccessible');
  const result=vpRequest_({action:'pull'});
  console.log('Connexion réussie ; rendez-vous en attente : '+result.jobs.length);
}
function vpSync() {
  const lock=LockService.getScriptLock();if(!lock.tryLock(1000))return;
  try {
    const calendar=CalendarApp.getCalendarById(VP_CALENDAR);
    if(!calendar)throw new Error('Agenda Vosges Pneus inaccessible');
    const result=vpRequest_({action:'pull'});
    for(const job of result.jobs) {
      if(job.calendar_id!==VP_CALENDAR||!/^[a-f0-9]{64}$/.test(job.job_id))throw new Error('Job invalide');
      const start=new Date(job.start),end=new Date(job.end);
      if(!Number.isFinite(start.getTime())||!Number.isFinite(end.getTime())||end<=start||start<=new Date()||end-start>14400000)throw new Error('Dates invalides');
      const marker='[VP-LWS:'+job.job_id+']';
      const events=calendar.getEvents(new Date(start.getTime()-86400000),new Date(end.getTime()+86400000));
      let event=events.find(e=>(e.getDescription()||'').includes(marker));
      // Preserve any earlier manual entry for the same Allopneus order and exact start.
      const order=String(job.summary).match(/\b\d{6,20}\b/);
      if(!event&&order)event=events.find(e=>e.getStartTime().getTime()===start.getTime()&&
        /allopneus/i.test(e.getTitle())&&(e.getTitle()+' '+e.getDescription()).includes(order[0]));
      if(!event)event=calendar.createEvent(job.summary,start,end,{description:job.description+'\n'+marker,location:job.location,sendInvites:false});
      vpRequest_({action:'ack',job_id:job.job_id,event_id:event.getId()});
    }
    console.log('Synchronisation terminée ; '+result.jobs.length+' rendez-vous traités');
  } finally {lock.releaseLock();}
}
function vpInstall() {
  vpCheck();
  if(!ScriptApp.getProjectTriggers().some(t=>t.getHandlerFunction()==='vpSync'))
    ScriptApp.newTrigger('vpSync').timeBased().everyMinutes(5).create();
  console.log('Synchronisation programmée toutes les 5 minutes');
}
