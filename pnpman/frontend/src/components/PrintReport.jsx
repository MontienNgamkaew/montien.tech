import React from 'react';
import { sortAssignments } from '../utils/sorting';

const PrintReport = ({ personnel, jobs, departments, assignments, academicYear, collegeSettings }) => {
  const getPersonName = (id) => personnel.find(p => p.id === id)?.name || '';
  const getPersonTitle = (id) => personnel.find(p => p.id === id)?.main_title || '';

  const formatWithComment = (a) => {
    const name = getPersonName(a.personnel_id);
    return a.comment ? `${name} (${a.comment})` : name;
  };

  // Executive data
  const directorJob = jobs.find(j => j.id === 900);
  const directorAssignment = directorJob ? assignments.find(a => a.job_id === 900) : null;
  const directorName = directorAssignment ? getPersonName(directorAssignment.personnel_id) : '- ว่าง -';
  const directorTitle = directorAssignment ? getPersonTitle(directorAssignment.personnel_id) : '';

  // Build department data
  const deptData = departments.map(dept => {
    const deputyJob = jobs.find(j => j.department_id === dept.id && (j.id >= 901 && j.id <= 904));
    const deputyAssignment = deputyJob ? assignments.find(a => a.job_id === deputyJob.id) : null;
    const deputyName = deputyAssignment ? getPersonName(deputyAssignment.personnel_id) : '- ว่าง -';
    const deputyTitle = deputyAssignment ? getPersonTitle(deputyAssignment.personnel_id) : '';

    const deptJobs = jobs.filter(j => j.department_id === dept.id && !(j.id >= 900 && j.id <= 904));
    const regularJobs = deptJobs.filter(j => !j.name.startsWith('แผนกวิชา'));
    const sectionJobs = deptJobs.filter(j => j.name.startsWith('แผนกวิชา'));

    const buildJobData = (job) => {
      const jobAssignments = assignments.filter(a => a.job_id === job.id);
      const head = jobAssignments.find(a => ['หัวหน้างาน', 'หัวหน้าแผนกวิชา'].includes(a.role));
      const assistants = sortAssignments(jobAssignments.filter(a => a.role === 'ผู้ช่วยหัวหน้างาน'), personnel);
      const staff = sortAssignments(jobAssignments.filter(a => a.role === 'เจ้าหน้าที่งาน'), personnel);
      const teachers = sortAssignments(jobAssignments.filter(a => a.role === 'ครูในแผนกวิชา'), personnel);
      return {
        name: job.name,
        headName: head ? getPersonName(head.personnel_id) : '- ว่าง -',
        headTitle: head ? getPersonTitle(head.personnel_id) : '',
        assistants: assistants.map(a => ({ name: formatWithComment(a), title: getPersonTitle(a.personnel_id) })),
        staff: staff.map(a => ({ name: formatWithComment(a), title: getPersonTitle(a.personnel_id) })),
        teachers: teachers.map(a => ({ name: getPersonName(a.personnel_id), title: getPersonTitle(a.personnel_id) })),
      };
    };

    return {
      dept,
      deputyJobName: deputyJob ? deputyJob.name : '',
      deputyName,
      deputyTitle,
      regularJobs: regularJobs.map(buildJobData),
      sectionJobs: sectionJobs.map(buildJobData),
    };
  });

  const totalPages = deptData.length + 1;

  const collegeName = collegeSettings?.college_name || 'วิทยาลัยการอาชีพพนมไพร';

  return (
    <div id="printable-report" className="hidden print:block absolute top-0 left-0 w-full bg-white text-black font-sans">

      {/* ===== Page 1: Overview — Director + all Deputies ===== */}
      <div className="print-page" style={{ padding: '15mm 10mm 8mm' }}>
        <div className="text-center mb-4">
          <h1 className="text-xl font-bold mb-0">ผังโครงสร้างการบริหารงาน</h1>
          <h2 className="text-base">{collegeName} ปีการศึกษา {academicYear}</h2>
        </div>

        {/* Director Box */}
        <div className="flex justify-center mb-0">
          <div className="org-box org-box-director">
            <div className="org-box-role">ผู้อำนวยการวิทยาลัย</div>
            <div className="org-box-name">{directorName}</div>
            {directorTitle && <div className="org-box-title">{directorTitle}</div>}
          </div>
        </div>

        {/* Vertical line from director */}
        <div className="flex justify-center"><div className="org-vline" style={{height: '16px'}}></div></div>

        {/* Horizontal connector line */}
        <div className="flex justify-center">
          <div className="org-hline" style={{ width: `${Math.min(departments.length * 200, 800)}px` }}></div>
        </div>

        {/* Deputy Directors */}
        <div className="flex justify-center gap-3 flex-wrap">
          {deptData.map((d, idx) => (
            <div key={idx} className="flex flex-col items-center">
              <div className="org-vline" style={{height: '16px'}}></div>
              <div className="org-box org-box-deputy">
                <div className="org-box-role">รองผู้อำนวยการ</div>
                <div className="org-box-name" style={{fontSize: '11.5px'}}>{d.deputyName}</div>
                {d.deputyTitle && <div className="org-box-title">{d.deputyTitle}</div>}
                <div className="org-box-dept">{d.dept.name}</div>
               </div>
            </div>
          ))}
        </div>

        <div className="mt-4 text-center text-[9px] text-gray-400">
          * รายละเอียดโครงสร้างของแต่ละฝ่ายจะแสดงในหน้าถัดไป
        </div>
      </div>

      {/* ===== Department Pages ===== */}
      {deptData.map((d, dIdx) => {
        const isLastPage = dIdx === deptData.length - 1;
        return (
        <div key={dIdx} className={isLastPage ? '' : 'print-page'} style={{ padding: '8mm 8mm 5mm' }}>
          {/* Header */}
          <div className="text-center mb-2 border-b border-gray-800 pb-1">
            <h2 className="text-base font-bold leading-tight">ผังโครงสร้างบุคลากร — {d.dept.name}</h2>
            <p className="text-xs text-gray-500">{collegeName} ปีการศึกษา {academicYear}</p>
          </div>

          {/* Director at top (small) */}
          <div className="flex justify-center">
            <div className="org-box-sm org-box-director">
              <div className="org-box-role">ผู้อำนวยการวิทยาลัย</div>
              <div className="org-box-name" style={{fontSize: '11px'}}>{directorName}</div>
            </div>
          </div>
          <div className="flex justify-center"><div className="org-vline" style={{height: '10px'}}></div></div>

          {/* Deputy Director */}
          <div className="flex justify-center">
            <div className="org-box org-box-deputy" style={{minWidth: '120px'}}>
              <div className="org-box-role" style={{fontSize: '9.5px'}}>{d.deputyJobName || 'รองผู้อำนวยการ'}</div>
              <div className="org-box-name" style={{fontSize: '11.5px'}}>{d.deputyName}</div>
            </div>
          </div>
          <div className="flex justify-center"><div className="org-vline" style={{height: '10px'}}></div></div>

          {/* Horizontal connector */}
          {d.regularJobs.length > 1 && (
            <div className="flex justify-center">
              <div className="org-hline" style={{ width: `${Math.min(d.regularJobs.length * 140, 820)}px` }}></div>
            </div>
          )}

          {/* Jobs Grid */}
          <div className="flex justify-center gap-1.5 flex-wrap">
            {d.regularJobs.map((job, jIdx) => (
              <div key={jIdx} className="flex flex-col items-center" style={{ width: `${Math.max(100, Math.min(145, 820 / d.regularJobs.length - 8))}px` }}>
                {d.regularJobs.length > 1 && <div className="org-vline" style={{height: '10px'}}></div>}
                <div className="org-job-card">
                  <div className="org-job-title">{job.name}</div>

                  {/* Head */}
                  <div className="org-person-row org-person-head">
                    <span className="org-role-label bg-emerald-100 text-emerald-800">หน.</span>
                    <span className="org-person-name">{job.headName}</span>
                  </div>

                  {/* Assistants */}
                  {job.assistants.length > 0 && (
                    <div className="org-person-section">
                      <span className="org-role-label bg-sky-100 text-sky-800">ผช.</span>
                      {job.assistants.map((a, aIdx) => (
                        <span key={aIdx} className="org-person-name">{a.name}</span>
                      ))}
                    </div>
                  )}

                  {/* Staff */}
                  {job.staff.length > 0 && (
                    <div className="org-person-section">
                      <span className="org-role-label bg-fuchsia-100 text-fuchsia-800">จนท.</span>
                      {job.staff.map((s, sIdx) => (
                        <span key={sIdx} className="org-person-name">{s.name}</span>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>

          {/* Section Jobs (แผนกวิชา) */}
          {d.sectionJobs.length > 0 && (
            <div className="mt-3">
              <div className="text-center mb-1">
                <span className="inline-block bg-emerald-100 text-emerald-800 text-[10px] px-2.5 py-0.5 rounded-full font-bold border border-emerald-200">
                  แผนกวิชา — หัวหน้าแผนกวิชาและครูผู้สอน
                </span>
              </div>
              <table className="w-full border-collapse" style={{fontSize: '11px'}}>
                <thead>
                  <tr className="bg-gray-100">
                    <th className="border border-gray-300 p-1.5 text-left" style={{width: '50%'}}>แผนกวิชา</th>
                    <th className="border border-gray-300 p-1.5 text-left" style={{width: '30%'}}>หัวหน้าแผนกวิชา</th>
                    <th className="border border-gray-300 p-1.5 text-left" style={{width: '20%'}}>ตำแหน่งหลัก</th>
                  </tr>
                </thead>
                <tbody>
                  {d.sectionJobs.map((sj, sjIdx) => (
                    <tr key={sjIdx}>
                      <td className="border border-gray-300 p-1.5 font-medium">
                        <div className="text-[11.5px] font-bold">{sj.name}</div>
                        {sj.teachers && sj.teachers.length > 0 && (
                          <div className="text-[9.5px] text-gray-500 mt-1 font-normal leading-normal">
                            <span className="font-bold text-emerald-800 bg-emerald-50 px-1 py-0.2 rounded border border-emerald-100/50 mr-1">ครูผู้สอน:</span>
                            {sj.teachers.map(t => t.name).join(', ')}
                          </div>
                        )}
                      </td>
                      <td className="border border-gray-300 p-1.5">{sj.headName}</td>
                      <td className="border border-gray-300 p-1.5 text-gray-600 font-medium">{sj.headTitle || '-'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}


        </div>
        );
      })}
    </div>
  );
};

export default PrintReport;
