'use strict';
(() => {
 const form=document.getElementById('ac-admin-filters');if(!form)return;
 const search=document.getElementById('ac-admin-search'),dept=document.getElementById('ac-admin-department'),type=document.getElementById('ac-admin-type'),status=document.getElementById('ac-admin-status');
 const teachers=[...document.querySelectorAll('[data-admin-teacher]')];
 function filter(){let visible=0;const term=search.value.trim().toLocaleLowerCase('th-TH');
  for(const teacher of teachers){let matches=false;
   for(const cell of teacher.querySelectorAll('[data-admin-document]')){
    cell.hidden=type.value!=='all'&&cell.dataset.type!==type.value;
    const state=status.value==='all'||cell.dataset.statuses.split(' ').includes(status.value);
    if(!cell.hidden&&state)matches=true;
    for(const item of cell.querySelectorAll('[data-admin-submission]'))item.hidden=status.value!=='all'&&(status.value==='late'?item.dataset.late!=='1':item.dataset.status!==status.value);
    const list=cell.querySelector('[data-work-list]');if(list)list.open=status.value!=='all'&&state;
   }
   teacher.querySelector('.ac-document-grid').classList.toggle('ac-single-type',type.value!=='all');
   teacher.hidden=!teacher.dataset.search.toLocaleLowerCase('th-TH').includes(term)||(dept.value!=='all'&&teacher.dataset.department!==dept.value)||!matches;if(!teacher.hidden)visible++;
  }
  document.getElementById('ac-admin-result-count').textContent=`แสดง ${visible} จาก ${teachers.length} คน`;document.getElementById('ac-admin-empty').hidden=visible!==0;
 }
 form.addEventListener('submit',event=>event.preventDefault());search.addEventListener('input',filter);[dept,type,status].forEach(el=>el.addEventListener('change',filter));
 document.getElementById('ac-admin-clear').addEventListener('click',()=>{form.reset();teachers.forEach(t=>t.querySelectorAll('[data-work-list]').forEach(d=>d.open=false));filter();search.focus();});
 document.querySelectorAll('[data-queue-type]').forEach(button=>button.addEventListener('click',()=>{search.value='';dept.value='all';type.value=button.dataset.queueType;status.value='pending';filter();form.scrollIntoView({block:'start'});status.focus();}));
})();
