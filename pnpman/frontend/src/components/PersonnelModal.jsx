import React, { useState } from 'react';
import { X, Building2, UserCircle, Plus, Trash2, MessageSquare, Pencil } from 'lucide-react';
import axios from 'axios';
import Swal from 'sweetalert2';
import { BASE_URL, API_URL } from '../utils/api';

const PersonnelModal = ({ person, assignments, jobs, departments, onClose, onRefresh, isAdmin, editMode, academicYear }) => {
  const [assignDept, setAssignDept] = useState('');
  const [assignJob, setAssignJob] = useState('');
  const [assignRole, setAssignRole] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const personAssignments = assignments;

  const grouped = personAssignments.reduce((acc, curr) => {
    const job = jobs.find(j => j.id === curr.job_id);
    if (!job) return acc;
    const dept = departments.find(d => d.id === job.department_id);
    const deptName = dept ? dept.name : 'ผู้บริหารสถานศึกษา';
    if (!acc[deptName]) acc[deptName] = [];
    acc[deptName].push({ job: job.name, role: curr.role, assignment_id: curr.id, job_id: job.id, comment: curr.comment || '' });
    return acc;
  }, {});

  const handleRemove = async (jobId, role) => {
    const result = await Swal.fire({
      title: 'ยืนยันการลบ',
      text: `ลบตำแหน่ง "${role}" ออกจาก ${person.name}?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'ลบเลย',
      cancelButtonText: 'ยกเลิก',
    });
    if (result.isConfirmed) {
      try {
        await axios.post(`${API_URL}remove_assignment.php`, {
          personnel_id: person.id, job_id: jobId, role, academic_year: academicYear,
        });
        if (onRefresh) onRefresh();
        Swal.fire({ title: 'ลบสำเร็จ!', icon: 'success', timer: 1200, showConfirmButton: false });
      } catch (error) {
        Swal.fire('ผิดพลาด', error.response?.data?.message || 'Error', 'error');
      }
    }
  };

  const handleEditComment = async (roleItem) => {
    const supportsComment = ['ผู้ช่วยหัวหน้างาน', 'เจ้าหน้าที่งาน'].includes(roleItem.role);
    if (!supportsComment) return;

    const { value: comment } = await Swal.fire({
      title: 'หมายเหตุ / หน้าที่เฉพาะ',
      html: `<p class="text-sm text-gray-500 mb-2"><strong>${person.name}</strong> — ${roleItem.role} (${roleItem.job})</p>`,
      input: 'textarea',
      inputLabel: 'ระบุหมายเหตุหรือหน้าที่เฉพาะเจาะจง',
      inputPlaceholder: 'เช่น ดูแลงานการเงิน, รับผิดชอบด้านพัสดุ...',
      inputValue: roleItem.comment || '',
      showCancelButton: true,
      confirmButtonText: 'บันทึก',
      cancelButtonText: 'ยกเลิก',
      confirmButtonColor: '#6366f1',
      cancelButtonColor: '#6b7280',
      inputAttributes: {
        maxlength: 255,
        style: 'font-size: 14px; min-height: 80px;',
      },
    });

    if (comment !== undefined) {
      try {
        await axios.post(`${API_URL}update_comment.php`, {
          personnel_id: person.id,
          job_id: roleItem.job_id,
          role: roleItem.role,
          academic_year: academicYear,
          comment: comment || null,
        });
        if (onRefresh) onRefresh();
        Swal.fire({ title: 'บันทึกหมายเหตุแล้ว!', icon: 'success', timer: 1200, showConfirmButton: false });
      } catch (error) {
        Swal.fire('ผิดพลาด', 'ไม่สามารถบันทึกหมายเหตุได้', 'error');
      }
    }
  };

  const handleAssign = async (e) => {
    e.preventDefault();
    if (!assignJob || !assignRole) return Swal.fire('แจ้งเตือน', 'กรุณาเลือกงานและบทบาท', 'warning');
    
    setIsSubmitting(true);
    try {
      const res = await axios.post(`${API_URL}assign_job.php`, {
        personnel_id: person.id, job_id: parseInt(assignJob), role: assignRole, academic_year: academicYear,
      });
      if (res.data.status === 'success') {
        if (onRefresh) onRefresh();
        setAssignJob(''); setAssignRole('');
        Swal.fire({ title: 'มอบหมายสำเร็จ!', icon: 'success', timer: 1200, showConfirmButton: false });
      }
    } catch (error) {
      Swal.fire('ผิดพลาด', error.response?.data?.message || 'Error', 'error');
    } finally {
      setIsSubmitting(false);
    }
  };

  const filteredJobs = jobs.filter(j => 
    assignDept === 'exec' 
      ? (j.id >= 900 && j.id <= 904) 
      : (j.department_id === parseInt(assignDept) && !(j.id >= 900 && j.id <= 904))
  );

  const showForm = isAdmin && editMode;
  const supportsCommentRole = (role) => ['ผู้ช่วยหัวหน้างาน', 'เจ้าหน้าที่งาน'].includes(role);

  return (
    <div className="fixed inset-0 bg-gray-900/40 backdrop-blur-sm flex items-center justify-center z-[100] p-4" onClick={onClose}>
      <div className={`bg-white rounded-2xl shadow-2xl w-full ${showForm ? 'max-w-3xl' : 'max-w-xl'} max-h-[90vh] flex flex-col overflow-hidden`} onClick={e => e.stopPropagation()}>
        
        <div className="flex items-center justify-between p-6 border-b bg-gradient-to-r from-indigo-50 to-white">
          <div className="flex items-center gap-4">
            {person.photo_path ? (
              <img
                src={`${BASE_URL}${person.photo_path}`}
                className="w-14 h-14 rounded-full object-cover border-2 border-indigo-200 shadow-md shrink-0"
                alt={person.name}
              />
            ) : (
              <div className="bg-gradient-to-br from-indigo-500 to-blue-600 p-3 rounded-xl shadow-md">
                <UserCircle size={28} className="text-white" />
              </div>
            )}
            <div>
              <h2 className="text-xl font-bold text-gray-800">{person.name}</h2>
              <p className="text-gray-500 font-medium text-sm">{person.main_title}</p>
            </div>
          </div>
          <button onClick={onClose} className="p-2 hover:bg-gray-200 rounded-full transition-colors text-gray-400 hover:text-gray-600">
            <X size={22} />
          </button>
        </div>

        <div className="overflow-y-auto flex-1 flex flex-col md:flex-row divide-y md:divide-y-0 md:divide-x">
          <div className={`p-6 bg-white ${showForm ? 'md:w-1/2' : 'w-full'}`}>
            <h3 className="text-base font-bold mb-4 text-gray-800 flex items-center gap-2">
              <Building2 size={18} className="text-indigo-500" />
              หน้าที่รับผิดชอบ (ปี {academicYear})
              <span className="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-full ml-auto font-bold">
                {personAssignments.length}
              </span>
            </h3>
            
            {personAssignments.length === 0 ? (
              <div className="text-center py-10 text-gray-400 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                <p className="font-medium">ยังไม่มีตำแหน่งงาน</p>
              </div>
            ) : (
              <div className="space-y-4">
                {Object.entries(grouped).map(([deptName, roles]) => (
                  <div key={deptName} className="bg-gray-50 rounded-xl p-3 border border-gray-100">
                    <div className="text-xs font-bold text-indigo-800 mb-2 border-b border-indigo-100 pb-1.5">{deptName}</div>
                    <div className="space-y-1.5">
                      {roles.map((role, idx) => (
                        <div key={idx} className="flex items-start justify-between bg-white p-2.5 rounded-lg border shadow-sm group">
                          <div className="flex-1 min-w-0">
                            <div className="font-semibold text-gray-800 text-sm">{role.job}</div>
                            <div className="text-xs text-gray-500">{role.role}</div>
                            {role.comment && (
                              <div className="text-xs text-amber-600 font-medium mt-1 flex items-center gap-1">
                                <MessageSquare size={10} className="shrink-0" />
                                <span className="truncate">{role.comment}</span>
                              </div>
                            )}
                          </div>
                          <div className="flex items-center gap-0.5 shrink-0 ml-2">
                            {isAdmin && editMode && supportsCommentRole(role.role) && (
                              <button 
                                onClick={() => handleEditComment(role)}
                                className={`${role.comment ? 'text-amber-500 hover:bg-amber-50' : 'text-gray-400 hover:bg-gray-100'} hover:text-amber-600 p-1.5 rounded-md transition-colors opacity-0 group-hover:opacity-100`}
                                title="แก้ไขหมายเหตุ"
                              >
                                <Pencil size={13} />
                              </button>
                            )}
                            {isAdmin && editMode && (
                              <button 
                                onClick={() => handleRemove(role.job_id, role.role)}
                                className="text-gray-400 hover:text-red-500 hover:bg-red-50 p-1.5 rounded-md transition-colors opacity-0 group-hover:opacity-100"
                              >
                                <Trash2 size={14} />
                              </button>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {showForm && (
            <div className="p-6 md:w-1/2 bg-gray-50/50">
              <h3 className="text-base font-bold mb-4 text-gray-800 flex items-center gap-2">
                <Plus size={18} className="text-green-500" />
                มอบหมายหน้าที่เพิ่มเติม
              </h3>
              
              <form onSubmit={handleAssign} className="space-y-3">
                <div>
                  <label className="block text-xs font-bold text-gray-600 mb-1">ฝ่ายงาน</label>
                  <select className="w-full rounded-lg shadow-sm text-sm p-2.5 border border-gray-300 bg-white outline-none focus:ring-2 focus:ring-indigo-500"
                    value={assignDept} onChange={(e) => { setAssignDept(e.target.value); setAssignJob(''); }}>
                    <option value="">-- เลือกฝ่ายงาน --</option>
                    <option value="exec">ผู้บริหารสถานศึกษา</option>
                    {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-bold text-gray-600 mb-1">งาน / แผนก</label>
                  <select className="w-full rounded-lg shadow-sm text-sm p-2.5 border border-gray-300 bg-white disabled:bg-gray-100 outline-none focus:ring-2 focus:ring-indigo-500"
                    value={assignJob} onChange={(e) => setAssignJob(e.target.value)} disabled={!assignDept}>
                    <option value="">-- เลือกงาน --</option>
                    {filteredJobs.map(j => <option key={j.id} value={j.id}>{j.name}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-bold text-gray-600 mb-1">บทบาทหน้าที่</label>
                  <select className="w-full rounded-lg shadow-sm text-sm p-2.5 border border-gray-300 bg-white disabled:bg-gray-100 outline-none focus:ring-2 focus:ring-indigo-500"
                    value={assignRole} onChange={(e) => setAssignRole(e.target.value)} disabled={!assignJob}>
                    <option value="">-- เลือกบทบาท --</option>
                    <option value="ผู้อำนวยการวิทยาลัย">ผู้อำนวยการวิทยาลัย</option>
                    <option value="รองผู้อำนวยการฝ่าย">รองผู้อำนวยการฝ่าย</option>
                    <option value="หัวหน้างาน">หัวหน้างาน</option>
                    <option value="หัวหน้าแผนกวิชา">หัวหน้าแผนกวิชา</option>
                    <option value="ครูในแผนกวิชา">ครูในแผนกวิชา</option>
                    <option value="ผู้ช่วยหัวหน้างาน">ผู้ช่วยหัวหน้างาน</option>
                    <option value="เจ้าหน้าที่งาน">เจ้าหน้าที่งาน</option>
                  </select>
                </div>
                <button type="submit" disabled={!assignJob || !assignRole || isSubmitting}
                  className="w-full bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white font-bold py-2.5 px-4 rounded-lg shadow-md transition-all disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2 mt-2">
                  {isSubmitting ? 'กำลังบันทึก...' : <><Plus size={16} /> บันทึกการมอบหมาย</>}
                </button>
              </form>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default PersonnelModal;
