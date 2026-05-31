import React from 'react';
import { Droppable, Draggable } from '@hello-pangea/dnd';
import { User, X, BookOpen, GraduationCap } from 'lucide-react';
import { sortAssignments } from '../utils/sorting';
import { BASE_URL } from '../utils/api';

const SectionRow = ({ job, assignments, personnel, onPersonClick, isAdmin, editMode, onRemoveAssignment, highlightPersonId }) => {
  const droppableHeadId = `job|${job.id}|หัวหน้าแผนกวิชา`;
  const droppableTeacherId = `job|${job.id}|ครูในแผนกวิชา`;

  const assignedHead = assignments.filter(a => a.role === 'หัวหน้าแผนกวิชา');
  const assignedTeacher = assignments.filter(a => a.role === 'ครูในแผนกวิชา');

  const isHeadFull = assignedHead.length >= 1;

  return (
    <div className="flex flex-col md:flex-row md:items-center gap-3 py-3 border-b last:border-b-0 hover:bg-gray-50/40 px-2 rounded-lg transition-colors">
      {/* Column 1: Department Name */}
      <div className="w-full md:w-44 shrink-0 flex items-center gap-2">
        <BookOpen size={14} className="text-emerald-600 shrink-0" />
        <span className="text-xs font-bold text-gray-800 tracking-wide">{job.name}</span>
      </div>

      {/* Column 2: Head of Department (Limit = 1) */}
      <div className="w-full md:w-64 shrink-0">
        <Droppable droppableId={droppableHeadId} isDropDisabled={isHeadFull || !isAdmin || !editMode}>
          {(provided, snapshot) => (
            <div
              ref={provided.innerRef}
              {...provided.droppableProps}
              className={`min-h-[38px] rounded-lg border px-2 py-1 flex items-center gap-2 transition-all w-full
                ${snapshot.isDraggingOver && !isHeadFull && editMode ? 'bg-emerald-50 border-emerald-300 ring-2 ring-emerald-100' : 'bg-gray-50 border-gray-200'}
                ${isHeadFull ? 'bg-emerald-50/20' : ''}`}
            >
              {assignedHead.map((assignment, index) => {
                const person = personnel.find(p => p.id === assignment.personnel_id);
                if (!person) return null;
                return (
                  <Draggable key={`assignment-${assignment.id}`} draggableId={`assignment-${assignment.id}`} index={index} isDragDisabled={!editMode || !isAdmin}>
                    {(provided) => (
                      <div
                        ref={provided.innerRef}
                        {...provided.draggableProps}
                        {...provided.dragHandleProps}
                        onClick={() => onPersonClick(person)}
                        className={`flex items-center gap-1.5 bg-emerald-50 border border-emerald-200 rounded-lg px-2.5 py-1 cursor-pointer hover:bg-emerald-100 hover:border-emerald-300 shadow-sm group w-full justify-between transition-all
                          ${highlightPersonId === person.id ? 'ring-3 ring-amber-400 shadow-lg shadow-amber-200 animate-pulse scale-[1.02]' : ''}`}
                      >
                        <div className="flex items-center gap-1.5 min-w-0">
                          {person.photo_path ? (
                            <img
                              src={`${BASE_URL}${person.photo_path}`}
                              className="w-7 h-7 rounded-full object-cover border border-emerald-100 shadow-sm shrink-0"
                              alt={person.name}
                            />
                          ) : (
                            <div className="w-7 h-7 rounded-full border border-emerald-200 bg-white flex items-center justify-center shadow-sm shrink-0 text-emerald-700">
                              <GraduationCap size={13} className="shrink-0" />
                            </div>
                          )}
                          <div className="min-w-0">
                            <span className="text-xs font-bold text-emerald-900 truncate block max-w-[160px]">{person.name}</span>
                            <span className="text-[9px] text-emerald-600/70 font-medium truncate block">{(person.main_title || '').trim() || 'ไม่ระบุ'}</span>
                          </div>
                        </div>
                        {isAdmin && editMode && onRemoveAssignment && (
                          <button
                            onClick={(e) => { e.stopPropagation(); onRemoveAssignment(assignment.personnel_id, assignment.job_id, assignment.role); }}
                            className="opacity-0 group-hover:opacity-100 bg-red-100 hover:bg-red-200 text-red-600 p-0.5 rounded transition-all shrink-0"
                          >
                            <X size={10} />
                          </button>
                        )}
                      </div>
                    )}
                  </Draggable>
                );
              })}
              {assignedHead.length === 0 && editMode && (
                <span className="text-xs text-gray-400 italic px-1.5 py-1">ลากหัวหน้ามาวางที่นี่</span>
              )}
              {assignedHead.length === 0 && !editMode && (
                <span className="text-xs text-gray-350 px-1.5 py-1">- ว่าง -</span>
              )}
              {provided.placeholder}
            </div>
          )}
        </Droppable>
      </div>

      {/* Column 3: Teachers in Department (Unlimited) */}
      <div className="w-full flex-1 min-w-0">
        <Droppable droppableId={droppableTeacherId} isDropDisabled={!isAdmin || !editMode}>
          {(provided, snapshot) => (
            <div
              ref={provided.innerRef}
              {...provided.droppableProps}
              className={`min-h-[38px] rounded-lg border px-2 py-1 flex flex-wrap items-center gap-1.5 transition-all w-full
                ${snapshot.isDraggingOver && editMode ? 'bg-sky-50 border-sky-300 ring-2 ring-sky-100' : 'bg-gray-50 border-gray-200'}`}
            >
              {sortAssignments(assignedTeacher, personnel).map((assignment, index) => {
                const person = personnel.find(p => p.id === assignment.personnel_id);
                if (!person) return null;
                return (
                  <Draggable key={`assignment-${assignment.id}`} draggableId={`assignment-${assignment.id}`} index={index} isDragDisabled={!editMode || !isAdmin}>
                    {(provided) => (
                      <div
                        ref={provided.innerRef}
                        {...provided.draggableProps}
                        {...provided.dragHandleProps}
                        onClick={() => onPersonClick(person)}
                        className={`flex items-center gap-1 bg-sky-50/80 border border-sky-150 rounded-lg px-2 py-0.5 cursor-pointer hover:bg-sky-100 hover:border-sky-200 shadow-sm group transition-all
                          ${highlightPersonId === person.id ? 'ring-3 ring-amber-400 shadow-lg shadow-amber-200 animate-pulse scale-[1.02]' : ''}`}
                      >
                        {person.photo_path ? (
                          <img
                            src={`${BASE_URL}${person.photo_path}`}
                            className="w-5 h-5 rounded-full object-cover border border-sky-100 shadow-sm shrink-0"
                            alt={person.name}
                          />
                        ) : (
                          <div className="w-5 h-5 rounded-full border border-sky-200 bg-white flex items-center justify-center shadow-sm shrink-0 text-sky-600">
                            <User size={9} className="shrink-0" />
                          </div>
                        )}
                        <div className="min-w-0">
                          <span className="text-xs font-semibold text-sky-900 truncate block max-w-[120px]">{person.name}</span>
                        </div>
                        {isAdmin && editMode && onRemoveAssignment && (
                          <button
                            onClick={(e) => { e.stopPropagation(); onRemoveAssignment(assignment.personnel_id, assignment.job_id, assignment.role); }}
                            className="opacity-0 group-hover:opacity-100 bg-red-50 hover:bg-red-100 text-red-500 p-0.5 rounded transition-all shrink-0 ml-1"
                          >
                            <X size={10} />
                          </button>
                        )}
                      </div>
                    )}
                  </Draggable>
                );
              })}
              {assignedTeacher.length === 0 && editMode && (
                <span className="text-xs text-gray-400 italic px-1.5 py-1">ลากครูในแผนกมาวางที่นี่</span>
              )}
              {assignedTeacher.length === 0 && !editMode && (
                <span className="text-xs text-gray-350 px-1.5 py-1">- ไม่มีครูในแผนกระบุ -</span>
              )}
              {provided.placeholder}
            </div>
          )}
        </Droppable>
      </div>

      {/* Column 4: Badge Count (Only tracks Head of Department) */}
      <span className="hidden md:block text-xs font-bold text-gray-400 w-10 text-right shrink-0 pr-1 select-none">
        {assignedHead.length}/1
      </span>
    </div>
  );
};

const DeptSectionBox = ({ jobs, assignments, personnel, onPersonClick, isAdmin, editMode, onRemoveAssignment, highlightPersonId }) => {
  return (
    <div className="bg-white border rounded-xl shadow-sm p-4 col-span-full hover:shadow-md transition-shadow">
      <h3 className="font-bold text-gray-800 text-sm mb-3 pb-2 border-b flex items-center gap-2">
        <span className="bg-emerald-100 text-emerald-800 text-xs px-2.5 py-0.5 rounded-full font-bold">แผนกวิชา</span>
        การจัดวางแผนกวิชาและครูผู้สอน
      </h3>
      
      {/* Table Headers for medium+ screens */}
      <div className="hidden md:flex items-center gap-3 mb-2 px-2">
        <div className="w-44 shrink-0 text-xs font-semibold text-gray-500">แผนกวิชา</div>
        <div className="w-64 shrink-0 text-xs font-semibold text-gray-500">หัวหน้าแผนกวิชา (1 คน)</div>
        <div className="flex-1 text-xs font-semibold text-gray-500">ครูในแผนกวิชา (ไม่จำกัดจำนวน)</div>
        <div className="w-10 text-right text-xs font-semibold text-gray-400 shrink-0 pr-1">Badge</div>
      </div>

      <div className="space-y-1">
        {jobs.map(job => (
          <SectionRow
            key={job.id} job={job}
            assignments={assignments.filter(a => a.job_id === job.id)}
            personnel={personnel} onPersonClick={onPersonClick}
            isAdmin={isAdmin} editMode={editMode} onRemoveAssignment={onRemoveAssignment}
            highlightPersonId={highlightPersonId}
          />
        ))}
      </div>
    </div>
  );
};

export default DeptSectionBox;
