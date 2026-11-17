/* Read-only countdown: no requests, writes, or changes to submission permissions. */
'use strict';
const AcademixCountdown = (() => {
 const DAY=86400000;
 function state(deadline,open,now,configured=true){
  const valid=typeof deadline==='number'&&Number.isFinite(deadline);
  if(!configured)return {kind:'unconfigured',mood:'rest',speed:1.8,remaining:null};
  if(!open)return {kind:'closed',mood:valid&&deadline<=now?'dizzy':'rest',speed:1.8,remaining:null};
  if(!valid)return {kind:'undated',mood:'rest',speed:1.8,remaining:null};
  const remaining=Math.max(0,deadline-now);
  if(remaining===0)return {kind:'expired',mood:'dizzy',speed:1.8,remaining:0};
  const speed=.26+1.54*Math.pow(Math.min(1,remaining/(7*DAY)),.55);
  return {kind:'active',mood:remaining<=DAY?'rush':'working',speed,remaining};
 }
 function parts(remaining){
  if(remaining===null)return {days:'—',hours:'—',minutes:'—',seconds:'—'};
  const seconds=Math.ceil(Math.max(0,remaining)/1000);
  return {days:String(Math.floor(seconds/86400)).padStart(2,'0'),hours:String(Math.floor(seconds/3600)%24).padStart(2,'0'),minutes:String(Math.floor(seconds/60)%60).padStart(2,'0'),seconds:String(seconds%60).padStart(2,'0')};
 }
 function mission(entries,now){
  const classified=entries.map(e=>({...e,state:state(e.deadline,e.open,now,e.configured)}));
  return classified.filter(e=>e.state.kind==='active').sort((a,b)=>a.deadline-b.deadline)[0]
   ||classified.find(e=>e.state.kind==='undated')
   ||classified.filter(e=>e.state.kind==='expired').sort((a,b)=>b.deadline-a.deadline)[0]
   ||classified.find(e=>e.state.mood==='dizzy')||classified[0]||null;
 }
 function message(s){
  if(s.kind==='unconfigured')return 'ยังไม่เปิดรอบส่งงาน';
  if(s.kind==='closed')return 'ปิดรับงานตามการตั้งค่าของผู้ดูแล';
  if(s.kind==='undated')return 'เปิดรับงาน · ยังไม่กำหนดเวลา';
  if(s.kind==='expired')return 'ครบกำหนดส่งแล้ว · เจ้าเหมียวมึนหัว';
  if(s.remaining<=3600000)return 'โค้งสุดท้าย! เจ้าเหมียวเร่งมือเต็มที่';
  if(s.remaining<=DAY)return 'เหลือไม่ถึง 24 ชั่วโมง · เร่งอีกนิด';
  if(s.remaining<=3*DAY)return 'ใกล้กำหนดแล้ว · เริ่มเก็บงานกัน';
  return 'ยังมีเวลา · ค่อย ๆ ทำให้ครบ';
 }
 return {state,parts,mission,message};
})();
if(typeof module!=='undefined'&&module.exports)module.exports=AcademixCountdown;
if(typeof document!=='undefined')(() => {
 const root=document.getElementById('ac-main');if(!root||!root.dataset.serverNow)return;
 const base=Number(root.dataset.serverNow);if(!Number.isFinite(base)||base<=0)return;
 const perfStart=performance.now(),wallStart=Date.now();
 const cards=[...root.querySelectorAll('[data-deadline-card]')];
 const entries=cards.map(card=>({card,key:card.dataset.key,title:card.dataset.title,deadline:card.dataset.deadline?Number(card.dataset.deadline):null,open:card.dataset.open==='1',configured:card.dataset.configured==='1'}));
 const mainCat=document.getElementById('nl-main-cat');
 function text(node,value){if(node&&node.textContent!==value)node.textContent=value;}
 function cat(node,s){if(!node)return;if(node.dataset.mood!==s.mood)node.dataset.mood=s.mood;const speed=s.speed.toFixed(3)+'s';if(node.style.getPropertyValue('--cat-speed')!==speed)node.style.setProperty('--cat-speed',speed);}
 function update(){
  const now=base+(performance.now()-perfStart);
  for(const entry of entries){
   const s=AcademixCountdown.state(entry.deadline,entry.open,now,entry.configured),numbers=AcademixCountdown.parts(s.remaining);
   entry.card.dataset.phase=s.kind;for(const unit of ['days','hours','minutes','seconds'])text(entry.card.querySelector(`[data-unit="${unit}"]`),numbers[unit]);
   text(entry.card.querySelector('[data-timer-status]'),AcademixCountdown.message(s));
   entry.card.querySelector('[data-expiry-note]').hidden=s.kind!=='expired';cat(entry.card.querySelector('[data-mascot]'),s);
  }
  const chosen=AcademixCountdown.mission(entries,now);if(!chosen)return;
  cat(mainCat,chosen.state);root.dataset.mission=chosen.key;
  text(document.getElementById('nl-mission-title'),chosen.state.kind==='active'?'กำหนดส่งถัดไป · '+chosen.title:chosen.state.kind==='expired'?'ครบกำหนดส่ง · '+chosen.title:chosen.state.kind==='undated'?'พร้อมทำงาน · '+chosen.title:'เจ้าเหมียวพักรอรอบส่งงาน');
  text(document.getElementById('nl-mission-note'),AcademixCountdown.message(chosen.state));
  text(document.getElementById('nl-cat-mood'),chosen.state.mood==='dizzy'?'มึนแล้ว… ขอพักแป๊บ':chosen.state.mood==='rush'?'เร่งมือแล้วนะ เมี้ยว!':chosen.state.mood==='working'?'กำลังปั่นงานอย่างตั้งใจ':'พักแป๊บ แล้วค่อยลุยกัน');
 }
 const motion=document.getElementById('nl-motion-toggle'),reduced=window.matchMedia('(prefers-reduced-motion: reduce)');
 function motionPreference(){motion.disabled=reduced.matches;text(motion,reduced.matches?'ลดการเคลื่อนไหวตามอุปกรณ์':document.body.classList.contains('nl-motion-paused')?'เล่นแอนิเมชัน':'พักแอนิเมชัน');motion.setAttribute('aria-pressed',String(reduced.matches||document.body.classList.contains('nl-motion-paused')));}
 motion.addEventListener('click',()=>{document.body.classList.toggle('nl-motion-paused');motionPreference();});reduced.addEventListener?.('change',motionPreference);motionPreference();
 document.addEventListener('visibilitychange',()=>{
  document.body.classList.toggle('nl-animations-hidden',document.hidden);
  // Reload the read-only landing page after a system sleep/clock jump; never reuse a stale timer.
  if(!document.hidden&&Math.abs((Date.now()-wallStart)-(performance.now()-perfStart))>5000){window.location.reload();return;}
  if(!document.hidden)update();
 });
 window.addEventListener('pageshow',event=>{if(event.persisted)window.location.reload();});
 update();window.setInterval(update,1000);
})();
