'use strict';
(() => {
 const search=document.getElementById('ac-search'),type=document.getElementById('ac-type-filter'),status=document.getElementById('ac-status-filter');
 const courses=[...document.querySelectorAll('[data-course]')];
 function filter(){
  if(!search||!type||!status)return;
  const term=search.value.trim().toLocaleLowerCase('th-TH');let visible=0;
  for(const course of courses){
   const cells=[...course.querySelectorAll('[data-document]')];
   const chosen=cells.filter(cell=>type.value==='all'||cell.dataset.type===type.value);
   const matches=chosen.some(cell=>status.value==='all'||(status.value==='late'?cell.dataset.late==='1':cell.dataset.status===status.value));
   course.hidden=!course.dataset.search.toLocaleLowerCase('th-TH').includes(term)||!matches;
   if(!course.hidden)visible++;
   cells.forEach(cell=>{cell.hidden=type.value!=='all'&&cell.dataset.type!==type.value;});
   course.querySelector('.ac-document-grid').classList.toggle('ac-single-type',type.value!=='all');
  }
  document.getElementById('ac-result-count').textContent=`แสดง ${visible} จาก ${courses.length} รายวิชา`;
  document.getElementById('ac-no-results').hidden=visible!==0;
 }
 if(search){search.addEventListener('input',filter);type.addEventListener('change',filter);status.addEventListener('change',filter);document.getElementById('ac-clear-filters').addEventListener('click',()=>{search.value='';type.value='all';status.value='all';filter();search.focus();});}
 document.querySelectorAll('[data-open-course]').forEach(link=>link.addEventListener('click',event=>{const section=document.getElementById('add-course');if(!section)return;event.preventDefault();section.open=true;document.getElementById('course_code_input').focus();}));
})();
