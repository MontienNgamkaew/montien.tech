import React, { useState } from 'react';
import { Droppable, Draggable } from '@hello-pangea/dnd';
import JobZone, { RoleZone } from './JobZone';
import DeptSectionBox from './DeptSectionBox';

const OrgChart = ({ departments, jobs, assignments, personnel, onPersonClick, isAdmin, editMode, onRemoveAssignment, highlightPersonId, onRefresh, academicYear }) => {
  const [activeDept, setActiveDept] = useState(departments[0]?.id);
  const activeDeptId = activeDept || departments[0]?.id;

  if (!departments.length) return <div>Loading...</div>;

  const directorJob = jobs.find(j => j.id === 900);
  const deputyJob = jobs.find(j => j.department_id === activeDeptId && (j.id >= 901 && j.id <= 904));
  const sectionJobs = jobs.filter(j => j.department_id === activeDeptId && !(j.id >= 900 && j.id <= 904) && j.name.startsWith('แผนกวิชา'));
  const currentJobs = jobs.filter(j => j.department_id === activeDeptId && !(j.id >= 900 && j.id <= 904) && !j.name.startsWith('แผนกวิชา'));

  return (
    <div className="flex flex-col h-full">
      <h2 className="text-xl font-bold mb-3 border-b pb-2 shrink-0 text-gray-800">โครงสร้างการบริหารงาน</h2>

      {/* Department Tabs */}
      <div className="flex gap-2 mb-3 overflow-x-auto pb-1 shrink-0">
        {departments.map(dept => (
          <button
            key={dept.id}
            onClick={() => setActiveDept(dept.id)}
            className={`px-4 py-2 whitespace-nowrap rounded-lg font-medium transition-all duration-200 text-sm ${
              activeDeptId === dept.id 
                ? 'bg-gradient-to-r from-rose-800 to-red-900 text-white shadow-md shadow-rose-200' 
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200 hover:shadow-sm'
            }`}
          >
            {dept.name}
          </button>
        ))}
      </div>

      {/* Scrollable content */}
      <div className="flex-1 overflow-y-auto pr-2">
        {/* Director Zone */}
        {directorJob && (
          <div className="mb-4 bg-gradient-to-br from-amber-50 to-yellow-50 border-amber-300 border rounded-xl px-4 py-3 shadow-sm mx-auto w-full max-w-xs">
            <h3 className="font-bold text-sm text-amber-800 text-center mb-2 flex items-center justify-center gap-2">
              <span className="w-2 h-2 bg-amber-500 rounded-full"></span>
              {directorJob.name}
            </h3>
            <RoleZone 
              job={directorJob} roleName="ผู้อำนวยการวิทยาลัย" limit={1} 
              assignments={assignments.filter(a => a.job_id === directorJob.id)} 
              personnel={personnel} onPersonClick={onPersonClick} isAdmin={isAdmin}
              editMode={editMode} onRemoveAssignment={onRemoveAssignment}
              highlightPersonId={highlightPersonId}
              onRefresh={onRefresh} academicYear={academicYear}
            />
          </div>
        )}

        {/* Deputy Director Zone */}
        {deputyJob && (
          <div className="mb-4 bg-gradient-to-br from-blue-50 to-indigo-50 border-blue-300 border rounded-xl px-4 py-3 shadow-sm mx-auto w-full max-w-xs">
            <h3 className="font-bold text-sm text-blue-800 text-center mb-2 flex items-center justify-center gap-2">
              <span className="w-2 h-2 bg-blue-500 rounded-full"></span>
              {deputyJob.name}
            </h3>
            <RoleZone 
              job={deputyJob} roleName="รองผู้อำนวยการฝ่าย" limit={1} 
              assignments={assignments.filter(a => a.job_id === deputyJob.id)} 
              personnel={personnel} onPersonClick={onPersonClick} isAdmin={isAdmin}
              editMode={editMode} onRemoveAssignment={onRemoveAssignment}
              highlightPersonId={highlightPersonId}
              onRefresh={onRefresh} academicYear={academicYear}
            />
          </div>
        )}

        {/* Jobs Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {sectionJobs.length > 0 && (
            <DeptSectionBox
              jobs={sectionJobs} assignments={assignments} personnel={personnel}
              onPersonClick={onPersonClick} isAdmin={isAdmin} editMode={editMode}
              onRemoveAssignment={onRemoveAssignment}
              highlightPersonId={highlightPersonId}
            />
          )}
          {currentJobs.map(job => (
            <JobZone 
              key={job.id} job={job} 
              assignments={assignments.filter(a => a.job_id === job.id)} 
              personnel={personnel} onPersonClick={onPersonClick} isAdmin={isAdmin}
              editMode={editMode} onRemoveAssignment={onRemoveAssignment}
              highlightPersonId={highlightPersonId}
              onRefresh={onRefresh} academicYear={academicYear}
            />
          ))}
        </div>
      </div>
    </div>
  );
};

export default OrgChart;
