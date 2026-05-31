import React, { useState } from 'react';
import { Droppable, Draggable } from '@hello-pangea/dnd';
import { User, Filter, Search, X } from 'lucide-react';
import { BASE_URL } from '../utils/api';

// Title badge color mapping
const TITLE_COLORS = {
  'ผู้อำนวยการ': 'bg-amber-100 text-amber-800 border-amber-200',
  'รองผู้อำนวยการ': 'bg-blue-100 text-blue-800 border-blue-200',
  'ข้าราชการครู': 'bg-emerald-100 text-emerald-800 border-emerald-200',
  'พนักงานราชการครู': 'bg-teal-100 text-teal-800 border-teal-200',
  'ครูพิเศษสอน': 'bg-violet-100 text-violet-800 border-violet-200',
  'เจ้าหน้าที่': 'bg-rose-100 text-rose-800 border-rose-200',
};

const TITLE_OPTIONS = ['ผู้อำนวยการ', 'รองผู้อำนวยการ', 'ข้าราชการครู', 'พนักงานราชการครู', 'ครูพิเศษสอน', 'เจ้าหน้าที่'];

function getMainTitle(person) {
  const t = (person.main_title || '').trim();
  return t || 'ไม่ระบุ';
}

const StaffPool = ({ personnel, assignments, onPersonClick, isAdmin, editMode }) => {
  const [filterTitle, setFilterTitle] = useState('All');
  const [searchName, setSearchName] = useState('');

  const filteredPersonnel = personnel
    .filter(p => {
      if (filterTitle !== 'All' && getMainTitle(p) !== filterTitle) return false;
      if (searchName.trim() && !p.name.includes(searchName.trim())) return false;
      return true;
    })
    .sort((a, b) => {
      const idxA = TITLE_OPTIONS.indexOf(getMainTitle(a));
      const idxB = TITLE_OPTIONS.indexOf(getMainTitle(b));
      return (idxA === -1 ? 999 : idxA) - (idxB === -1 ? 999 : idxB);
    });

  // Build filter options from the fixed list + any extra titles found in data
  const existingTitles = [...new Set(personnel.map(p => getMainTitle(p)))];
  const allTitles = [...new Set([...TITLE_OPTIONS, ...existingTitles])];

  return (
    <div className="flex flex-col h-full bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div className="p-4 border-b border-gray-100 bg-gradient-to-r from-rose-50 to-white rounded-t-xl z-10 shrink-0">
        <h2 className="text-lg font-bold text-gray-800 flex items-center gap-2">
          <span className="w-8 h-8 rounded-lg bg-gradient-to-br from-rose-700 to-red-900 text-white flex items-center justify-center shadow-sm">
            <User size={16} />
          </span>
          คลังบุคลากร 
          <span className="bg-rose-100 text-rose-800 text-xs px-2.5 py-1 rounded-full font-bold ml-auto">
            {filteredPersonnel.length} คน
          </span>
        </h2>

        {/* Search by name */}
        <div className="mt-3 flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-3 py-2 transition-all focus-within:ring-2 focus-within:ring-rose-500 focus-within:border-rose-500 shadow-sm">
          <Search size={16} className="text-gray-400 shrink-0" />
          <input
            type="text"
            value={searchName}
            onChange={(e) => setSearchName(e.target.value)}
            placeholder="ค้นหาชื่อบุคลากร..."
            className="w-full bg-transparent text-sm text-gray-700 outline-none placeholder-gray-400"
          />
          {searchName && (
            <button onClick={() => setSearchName('')} className="text-gray-400 hover:text-gray-600 transition-colors shrink-0">
              <X size={14} />
            </button>
          )}
        </div>
        
        {/* Filter by title */}
        <div className="mt-2 flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-3 py-2 transition-all focus-within:ring-2 focus-within:ring-rose-500 focus-within:border-rose-500 shadow-sm">
          <Filter size={16} className="text-gray-400" />
          <select 
            className="w-full bg-transparent text-sm text-gray-700 outline-none"
            value={filterTitle}
            onChange={(e) => setFilterTitle(e.target.value)}
          >
            <option value="All">แสดงทั้งหมด</option>
            {allTitles.map(title => (
              <option key={title} value={title}>{title}</option>
            ))}
          </select>
        </div>
      </div>

      <Droppable droppableId="staff-pool" isDropDisabled={!editMode}>
        {(provided, snapshot) => (
          <div
            ref={provided.innerRef}
            {...provided.droppableProps}
            className={`flex-1 overflow-y-auto p-3 transition-colors duration-300 staff-pool-scroll
              ${snapshot.isDraggingOver ? 'bg-rose-50/50' : 'bg-gray-50/30'}`}
          >
            {filteredPersonnel.map((person, index) => {
              const personAssignments = assignments.filter(a => a.personnel_id === person.id);
              const headRoles = ['หัวหน้างาน', 'หัวหน้าแผนกวิชา'];
              const directorRoles = ['ผู้อำนวยการวิทยาลัย', 'รองผู้อำนวยการฝ่าย'];
              const assistantRoles = ['ผู้ช่วยหัวหน้างาน'];
              const staffRoles = ['เจ้าหน้าที่งาน'];
              const headCount = personAssignments.filter(a => headRoles.includes(a.role)).length;
              const directorCount = personAssignments.filter(a => directorRoles.includes(a.role)).length;
              const assistantCount = personAssignments.filter(a => assistantRoles.includes(a.role)).length;
              const staffCount = personAssignments.filter(a => staffRoles.includes(a.role)).length;

              const displayTitle = getMainTitle(person);
              const titleColor = TITLE_COLORS[displayTitle] || 'bg-gray-100 text-gray-600 border-gray-200';

              return (
                <Draggable key={`person-${person.id}`} draggableId={`person-${person.id}`} index={index} isDragDisabled={!editMode}>
                  {(provided, snapshot) => (
                    <div
                      ref={provided.innerRef}
                      {...provided.draggableProps}
                      {...provided.dragHandleProps}
                      onClick={() => onPersonClick(person)}
                      className={`p-3 mb-2.5 bg-white border rounded-xl shadow-sm flex flex-col gap-1.5 transition-all duration-200
                        ${editMode ? 'cursor-grab active:cursor-grabbing hover:-translate-y-0.5' : 'cursor-pointer'}
                        ${snapshot.isDragging ? 'shadow-2xl ring-2 ring-rose-500 z-50 scale-105 rotate-1' : 'hover:border-rose-200 hover:shadow-md border-gray-100'}
                      `}
                    >
                      <div className="flex items-center gap-2.5">
                        {person.photo_path ? (
                          <img
                            src={`${BASE_URL}${person.photo_path}`}
                            className="w-10 h-10 rounded-full object-cover border border-gray-200 shadow-sm shrink-0"
                            alt={person.name}
                          />
                        ) : (
                          <div className={`w-10 h-10 rounded-full border flex items-center justify-center shadow-sm shrink-0 ${titleColor}`}>
                            <User size={16} />
                          </div>
                        )}
                        <div className="flex-1 min-w-0">
                          <p className="font-bold text-sm text-gray-800 truncate leading-tight">{person.name}</p>
                          <span className={`inline-block ${titleColor} px-2 py-0.5 rounded-full text-[11px] font-bold mt-1 border`}>
                            {displayTitle}
                          </span>
                        </div>
                      </div>
                      
                      {(directorCount > 0 || headCount > 0 || assistantCount > 0 || staffCount > 0) && (
                        <div className="flex flex-wrap gap-1 mt-0.5 pl-10">
                          {directorCount > 0 && (
                            <span className="bg-amber-50 text-amber-700 border border-amber-200 text-[9px] px-1.5 py-0.5 rounded font-bold">
                              ผู้บริหาร {directorCount}
                            </span>
                          )}
                          {headCount > 0 && (
                            <span className="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[9px] px-1.5 py-0.5 rounded font-bold">
                              หัวหน้า {headCount}
                            </span>
                          )}
                          {assistantCount > 0 && (
                            <span className="bg-sky-50 text-sky-700 border border-sky-200 text-[9px] px-1.5 py-0.5 rounded font-bold">
                              ผู้ช่วย {assistantCount}
                            </span>
                          )}
                          {staffCount > 0 && (
                            <span className="bg-fuchsia-50 text-fuchsia-700 border border-fuchsia-200 text-[9px] px-1.5 py-0.5 rounded font-bold">
                              จนท. {staffCount}
                            </span>
                          )}
                        </div>
                      )}
                    </div>
                  )}
                </Draggable>
              );
            })}
            {provided.placeholder}
          </div>
        )}
      </Droppable>
    </div>
  );
};

export default StaffPool;
