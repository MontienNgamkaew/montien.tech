import React, { useState, useEffect } from 'react';
import axios from 'axios';
import Swal from 'sweetalert2';
import { 
  X, KeyRound, Eye, EyeOff, Shield, Copy, CalendarRange, 
  AlertTriangle, RefreshCw, Paintbrush, Building2, Briefcase, 
  Plus, Trash2, Edit3, ArrowUp, ArrowDown, Upload, Check, Image
} from 'lucide-react';
import { BASE_URL, API_URL } from '../utils/api';

const themeOptions = [
  { id: 'rose', name: 'Rose Gold', primaryColor: '#8b1a2b', accentColor: '#e8a0a0', label: 'แดง/ชมพู (ต้นฉบับ)' },
  { id: 'emerald', name: 'Emerald Green', primaryColor: '#047857', accentColor: '#a7f3d0', label: 'เขียวมรกต' },
  { id: 'sky', name: 'Ocean Blue', primaryColor: '#0284c7', accentColor: '#bae6fd', label: 'น้ำเงินมหาสมุทร' },
  { id: 'indigo', name: 'Royal Violet', primaryColor: '#4f46e5', accentColor: '#c7d2fe', label: 'ม่วงหลวง/คราม' },
  { id: 'amber', name: 'Amber Bronze', primaryColor: '#d97706', accentColor: '#fde68a', label: 'ทองอำพัน' },
  { id: 'slate', name: 'Midnight Charcoal', primaryColor: '#334155', accentColor: '#cbd5e1', label: 'เทาชาโคล' },
];

const SettingsModal = ({ 
  onClose, 
  years = [2569, 2570, 2571, 2572, 2573], 
  currentYear = 2569, 
  onRefresh, 
  departments = [], 
  jobs = [], 
  collegeSettings = {},
  token
}) => {
  const [activeTab, setActiveTab] = useState('college'); // 'college', 'depts', 'jobs', 'copy_year', 'password'
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Tab 1: College Settings & Theme
  const [collegeName, setCollegeName] = useState(collegeSettings.college_name || '');
  const [selectedTheme, setSelectedTheme] = useState(collegeSettings.theme_preset || 'rose');
  const [logoFile, setLogoFile] = useState(null);
  const [logoPreview, setLogoPreview] = useState(
    collegeSettings.logo_path ? `${BASE_URL}${collegeSettings.logo_path}` : ''
  );
  const [deleteLogo, setDeleteLogo] = useState(false);

  // Tab 2: Manage Departments
  const [newDeptName, setNewDeptName] = useState('');
  const [editingDeptId, setEditingDeptId] = useState(null);
  const [editingDeptName, setEditingDeptName] = useState('');

  // Tab 3: Manage Jobs
  const [selectedDeptId, setSelectedDeptId] = useState('');
  const [newJobName, setNewJobName] = useState('');
  const [editingJobId, setEditingJobId] = useState(null);
  const [editingJobName, setEditingJobName] = useState('');
  const [editingJobDeptId, setEditingJobDeptId] = useState('');

  // Tab 4: Copy Year
  const defaultFrom = years.find(y => y === currentYear - 1) || (years[0] !== currentYear ? years[0] : years[1] || 2569);
  const [fromYear, setFromYear] = useState(defaultFrom);
  const [toYear, setToYear] = useState(currentYear);

  // Tab 5: Change Password
  const [currentPw, setCurrentPw] = useState('');
  const [newPw, setNewPw] = useState('');
  const [confirmPw, setConfirmPw] = useState('');
  const [showCurrent, setShowCurrent] = useState(false);
  const [showNew, setShowNew] = useState(false);

  // Initialize selected department on load
  useEffect(() => {
    if (departments.length > 0 && !selectedDeptId) {
      setSelectedDeptId(departments[0].id);
    }
  }, [departments]);

  // Sync settings when collegeSettings prop changes
  useEffect(() => {
    if (collegeSettings) {
      setCollegeName(collegeSettings.college_name || '');
      setSelectedTheme(collegeSettings.theme_preset || 'rose');
      setLogoPreview(collegeSettings.logo_path ? `${BASE_URL}${collegeSettings.logo_path}` : '');
    }
  }, [collegeSettings]);

  // ==========================================
  // TAB 1: COLLEGE SETTINGS ACTIONS
  // ==========================================
  const handleLogoChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      if (file.size > 2 * 1024 * 1024) {
        return Swal.fire('แจ้งเตือน', 'ขนาดไฟล์ภาพห้ามเกิน 2MB', 'warning');
      }
      setLogoFile(file);
      setLogoPreview(URL.createObjectURL(file));
      setDeleteLogo(false);
    }
  };

  const handleRemoveLogo = () => {
    setLogoFile(null);
    setLogoPreview('');
    setDeleteLogo(true);
  };

  const handleSaveCollegeSettings = async (e) => {
    e.preventDefault();
    if (!collegeName.trim()) {
      return Swal.fire('แจ้งเตือน', 'กรุณาระบุชื่อวิทยาลัย', 'warning');
    }

    setIsSubmitting(true);
    try {
      const formData = new FormData();
      formData.append('college_name', collegeName.trim());
      formData.append('theme_preset', selectedTheme);
      formData.append('delete_logo', deleteLogo ? 'true' : 'false');
      if (logoFile) {
        formData.append('logo', logoFile);
      }

      const res = await axios.post(`${API_URL}update_settings.php`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      });

      if (res.data.status === 'success') {
        Swal.fire({ title: 'บันทึกสำเร็จ!', text: 'ปรับปรุงชื่อวิทยาลัย, โลโก้ และธีมสีแล้ว', icon: 'success', timer: 1200, showConfirmButton: false });
        if (onRefresh) onRefresh();
      }
    } catch (err) {
      Swal.fire('ผิดพลาด', err.response?.data?.message || 'ไม่สามารถบันทึกค่าได้', 'error');
    } finally {
      setIsSubmitting(false);
    }
  };

  // ==========================================
  // TAB 2: MANAGE DEPARTMENTS ACTIONS
  // ==========================================
  const handleAddDept = async (e) => {
    e.preventDefault();
    if (!newDeptName.trim()) return;

    try {
      const res = await axios.post(`${API_URL}manage_structure.php`, {
        action: 'add_dept',
        name: newDeptName.trim()
      });
      setNewDeptName('');
      if (onRefresh) onRefresh();
      Swal.fire({ title: 'สำเร็จ!', text: res.data.message, icon: 'success', timer: 1000, showConfirmButton: false });
    } catch (err) {
      Swal.fire('ผิดพลาด', err.response?.data?.message || 'ไม่สามารถเพิ่มฝ่ายงานได้', 'error');
    }
  };

  const handleStartEditDept = (dept) => {
    setEditingDeptId(dept.id);
    setEditingDeptName(dept.name);
  };

  const handleSaveDeptName = async (id) => {
    if (!editingDeptName.trim()) return;
    try {
      await axios.post(`${API_URL}manage_structure.php`, {
        action: 'edit_dept',
        id,
        name: editingDeptName.trim()
      });
      setEditingDeptId(null);
      if (onRefresh) onRefresh();
      Swal.fire({ title: 'แก้ไขสำเร็จ!', icon: 'success', timer: 800, showConfirmButton: false });
    } catch (err) {
      Swal.fire('ผิดพลาด', err.response?.data?.message || 'ไม่สามารถแก้ไขได้', 'error');
    }
  };

  const handleMoveDept = async (index, direction) => {
    const list = [...departments];
    const targetIndex = direction === 'up' ? index - 1 : index + 1;
    if (targetIndex < 0 || targetIndex >= list.length) return;

    // Swap locally
    const temp = list[index];
    list[index] = list[targetIndex];
    list[targetIndex] = temp;

    try {
      await axios.post(`${API_URL}manage_structure.php`, {
        action: 'reorder_depts',
        ids: list.map(d => d.id)
      });
      if (onRefresh) onRefresh();
    } catch (err) {
      Swal.fire('ผิดพลาด', 'ไม่สามารถจัดเรียงได้', 'error');
    }
  };

  const handleDeleteDept = async (dept) => {
    const deptJobs = jobs.filter(j => j.department_id === dept.id);
    
    const result = await Swal.fire({
      title: 'ยืนยันลบฝ่ายงาน?',
      html: `
        <div class="text-left text-sm space-y-2">
          <p>คุณต้องการลบฝ่ายงาน <strong>"${dept.name}"</strong> ใช่หรือไม่?</p>
          <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 text-xs">
            ⚠️ <strong>คำเตือน:</strong> การลบฝ่ายงานนี้จะลบตำแหน่งงานทั้งหมดภายในฝ่าย (${deptJobs.length} ตำแหน่ง) และยกเลิกการจัดวางครูทั้งหมดภายใต้ตำแหน่งงานดังกล่าวโดยทันที!
          </div>
        </div>
      `,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'ลบถาวร',
      cancelButtonText: 'ยกเลิก',
      reverseButtons: true
    });

    if (result.isConfirmed) {
      try {
        const res = await axios.post(`${API_URL}manage_structure.php`, {
          action: 'delete_dept',
          id: dept.id
        });
        if (onRefresh) onRefresh();
        Swal.fire({ title: 'ลบสำเร็จ!', text: res.data.message, icon: 'success', timer: 1000, showConfirmButton: false });
      } catch (err) {
        Swal.fire('ผิดพลาด', err.response?.data?.message || 'ไม่สามารถลบได้', 'error');
      }
    }
  };

  // ==========================================
  // TAB 3: MANAGE JOBS ACTIONS
  // ==========================================
  const filteredJobs = jobs.filter(j => j.department_id === parseInt(selectedDeptId));

  const handleAddJob = async (e) => {
    e.preventDefault();
    if (!newJobName.trim() || !selectedDeptId) return;

    try {
      const res = await axios.post(`${API_URL}manage_structure.php`, {
        action: 'add_job',
        department_id: selectedDeptId,
        name: newJobName.trim()
      });
      setNewJobName('');
      if (onRefresh) onRefresh();
      Swal.fire({ title: 'สำเร็จ!', text: res.data.message, icon: 'success', timer: 1000, showConfirmButton: false });
    } catch (err) {
      Swal.fire('ผิดพลาด', err.response?.data?.message || 'ไม่สามารถเพิ่มตำแหน่งงานได้', 'error');
    }
  };

  const handleStartEditJob = (job) => {
    setEditingJobId(job.id);
    setEditingJobName(job.name);
    setEditingJobDeptId(job.department_id);
  };

  const handleSaveJob = async (id) => {
    if (!editingJobName.trim()) return;
    try {
      await axios.post(`${API_URL}manage_structure.php`, {
        action: 'edit_job',
        id,
        name: editingJobName.trim(),
        department_id: editingJobDeptId
      });
      setEditingJobId(null);
      if (onRefresh) onRefresh();
      Swal.fire({ title: 'แก้ไขสำเร็จ!', icon: 'success', timer: 800, showConfirmButton: false });
    } catch (err) {
      Swal.fire('ผิดพลาด', err.response?.data?.message || 'ไม่สามารถแก้ไขได้', 'error');
    }
  };

  const handleMoveJob = async (index, direction) => {
    const list = [...filteredJobs];
    const targetIndex = direction === 'up' ? index - 1 : index + 1;
    if (targetIndex < 0 || targetIndex >= list.length) return;

    // Swap
    const temp = list[index];
    list[index] = list[targetIndex];
    list[targetIndex] = temp;

    try {
      await axios.post(`${API_URL}manage_structure.php`, {
        action: 'reorder_jobs',
        ids: list.map(j => j.id)
      });
      if (onRefresh) onRefresh();
    } catch (err) {
      Swal.fire('ผิดพลาด', 'ไม่สามารถจัดลำดับตำแหน่งได้', 'error');
    }
  };

  const handleDeleteJob = async (job) => {
    if (job.id >= 900 && job.id <= 904) {
      return Swal.fire('ข้อห้าม', 'ไม่สามารถลบตำแหน่งบริหารแกนหลัก (ผู้อำนวยการ/รองผู้อำนวยการ) ได้', 'error');
    }

    const result = await Swal.fire({
      title: 'ยืนยันลบตำแหน่งงาน?',
      html: `
        <div class="text-left text-sm space-y-2">
          <p>คุณต้องการลบตำแหน่ง <strong>"${job.name}"</strong> ใช่หรือไม่?</p>
          <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-3 text-xs">
            ⚠️ <strong>คำเตือน:</strong> การลบนี้จะยกเลิกการมอบหมายงานครูทั้งหมดในตำแหน่งนี้ออกถาวร!
          </div>
        </div>
      `,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'ลบตำแหน่ง',
      cancelButtonText: 'ยกเลิก',
      reverseButtons: true
    });

    if (result.isConfirmed) {
      try {
        const res = await axios.post(`${API_URL}manage_structure.php`, {
          action: 'delete_job',
          id: job.id
        });
        if (onRefresh) onRefresh();
        Swal.fire({ title: 'ลบสำเร็จ!', text: res.data.message, icon: 'success', timer: 1000, showConfirmButton: false });
      } catch (err) {
        Swal.fire('ผิดพลาด', err.response?.data?.message || 'ไม่สามารถลบได้', 'error');
      }
    }
  };

  // ==========================================
  // TAB 4: COPY YEAR ACTIONS
  // ==========================================
  const handleCopyYearData = async (e) => {
    e.preventDefault();
    if (fromYear === toYear) {
      return Swal.fire('แจ้งเตือน', 'กรุณาระบุปีต้นทางและปีปลายทางที่แตกต่างกัน', 'warning');
    }

    const result = await Swal.fire({
      title: 'ยืนยันการคัดลอกโครงสร้าง?',
      html: `
        <div class="text-left text-sm space-y-2">
          <p>คุณกำลังจะคัดลอกข้อมูลการจัดวางทั้งหมดจาก:</p>
          <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-3 my-2 text-center">
            <span class="font-black text-xl text-indigo-700">ปีการศึกษา ${fromYear} &rarr; ปีการศึกษา ${toYear}</span>
          </div>
          <p class="text-red-500 font-bold flex gap-1 items-start mt-2">
            <span class="shrink-0 mt-0.5">⚠️</span> 
            <span>คำเตือน: ข้อมูลการจัดวางของปีการศึกษา ${toYear} ที่มีอยู่เดิมจะถูกแทนที่เขียนทับด้วยข้อมูลปี ${fromYear} ทั้งหมด!</span>
          </p>
        </div>
      `,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'ใช่, คัดลอกและเขียนทับ',
      cancelButtonText: 'ยกเลิก',
      reverseButtons: true
    });

    if (result.isConfirmed) {
      setIsSubmitting(true);
      try {
        const res = await axios.post(`${API_URL}copy_year.php`, {
          from_year: fromYear,
          to_year: toYear,
        });
        if (res.data.status === 'success') {
          Swal.fire({ title: 'คัดลอกสำเร็จ!', text: res.data.message, icon: 'success' });
          if (onRefresh) onRefresh();
        }
      } catch (err) {
        Swal.fire('ผิดพลาด', err.response?.data?.message || 'ไม่สามารถคัดลอกได้', 'error');
      } finally {
        setIsSubmitting(false);
      }
    }
  };

  // ==========================================
  // TAB 5: CHANGE PASSWORD ACTIONS
  // ==========================================
  const handleChangePassword = async (e) => {
    e.preventDefault();
    if (!currentPw || !newPw || !confirmPw) {
      return Swal.fire('แจ้งเตือน', 'กรุณากรอกข้อมูลให้ครบทุกช่อง', 'warning');
    }
    if (newPw.length < 4) {
      return Swal.fire('แจ้งเตือน', 'รหัสผ่านใหม่ต้องมีอย่างน้อย 4 ตัวอักษร', 'warning');
    }
    if (newPw !== confirmPw) {
      return Swal.fire('แจ้งเตือน', 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน', 'warning');
    }

    setIsSubmitting(true);
    try {
      await axios.post(`${API_URL}change_password.php`, {
        current_password: currentPw,
        new_password: newPw,
      });
      setCurrentPw(''); setNewPw(''); setConfirmPw('');
      Swal.fire({ title: 'เปลี่ยนรหัสผ่านสำเร็จ!', text: 'กรุณาใช้รหัสผ่านใหม่ในการเข้าสู่ระบบครั้งถัดไป', icon: 'success' });
    } catch (err) {
      Swal.fire('ผิดพลาด', err.response?.data?.message || 'ไม่สามารถเปลี่ยนรหัสผ่านได้', 'error');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-[150] p-4 animate-fade-in" onClick={onClose}>
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden border border-gray-100 transition-all transform scale-100 flex flex-col max-h-[92vh]" onClick={e => e.stopPropagation()}>
        
        {/* Header */}
        <div className="flex justify-between items-center p-5 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
          <h2 className="text-lg font-bold text-gray-800 flex items-center gap-2">
            <Shield size={20} className="text-indigo-600" />
            แผงควบคุมการตั้งค่า & โครงสร้าง
          </h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 p-1.5 hover:bg-gray-100 rounded-full transition-all">
            <X size={20} />
          </button>
        </div>

        {/* Tab Menu */}
        <div className="flex border-b border-gray-100 bg-slate-50/50 overflow-x-auto shrink-0 scrollbar-none">
          <button type="button" onClick={() => setActiveTab('college')}
            className={`py-3 px-4 text-xs font-bold border-b-2 transition-all flex items-center gap-1.5 shrink-0 ${
              activeTab === 'college'
                ? 'border-indigo-600 text-indigo-600 bg-white shadow-sm'
                : 'border-transparent text-gray-500 hover:text-gray-800 hover:bg-gray-100/50'
            }`}
          >
            <Building2 size={15} /> ข้อมูลวิทยาลัย & ธีม
          </button>
          <button type="button" onClick={() => setActiveTab('depts')}
            className={`py-3 px-4 text-xs font-bold border-b-2 transition-all flex items-center gap-1.5 shrink-0 ${
              activeTab === 'depts'
                ? 'border-indigo-600 text-indigo-600 bg-white shadow-sm'
                : 'border-transparent text-gray-500 hover:text-gray-800 hover:bg-gray-100/50'
            }`}
          >
            <Paintbrush size={15} /> โครงสร้างฝ่ายงาน
          </button>
          <button type="button" onClick={() => setActiveTab('jobs')}
            className={`py-3 px-4 text-xs font-bold border-b-2 transition-all flex items-center gap-1.5 shrink-0 ${
              activeTab === 'jobs'
                ? 'border-indigo-600 text-indigo-600 bg-white shadow-sm'
                : 'border-transparent text-gray-500 hover:text-gray-800 hover:bg-gray-100/50'
            }`}
          >
            <Briefcase size={15} /> โครงสร้างตำแหน่งงาน
          </button>
          <button type="button" onClick={() => setActiveTab('copy_year')}
            className={`py-3 px-4 text-xs font-bold border-b-2 transition-all flex items-center gap-1.5 shrink-0 ${
              activeTab === 'copy_year'
                ? 'border-indigo-600 text-indigo-600 bg-white shadow-sm'
                : 'border-transparent text-gray-500 hover:text-gray-800 hover:bg-gray-100/50'
            }`}
          >
            <Copy size={15} /> คัดลอกปีการศึกษา
          </button>
          <button type="button" onClick={() => setActiveTab('password')}
            className={`py-3 px-4 text-xs font-bold border-b-2 transition-all flex items-center gap-1.5 shrink-0 ${
              activeTab === 'password'
                ? 'border-indigo-600 text-indigo-600 bg-white shadow-sm'
                : 'border-transparent text-gray-500 hover:text-gray-800 hover:bg-gray-100/50'
            }`}
          >
            <KeyRound size={15} /> เปลี่ยนรหัสผ่าน
          </button>
        </div>

        {/* Modal Content */}
        <div className="p-6 overflow-y-auto flex-1 bg-slate-50/30">
          
          {/* ========================================== */}
          {/* TAB 1: COLLEGE SETTINGS & THEME            */}
          {/* ========================================== */}
          {activeTab === 'college' && (
            <form onSubmit={handleSaveCollegeSettings} className="space-y-6 animate-fade-in">
              <div className="flex items-center gap-3">
                <div className="bg-gradient-to-br from-indigo-100 to-purple-100 p-3 rounded-xl border border-indigo-200">
                  <Building2 size={20} className="text-indigo-600" />
                </div>
                <div>
                  <h3 className="font-bold text-gray-800 text-sm">ข้อมูลวิทยาลัยและธีมหน้าเว็บ</h3>
                  <p className="text-xs text-gray-500">ปรับแต่งชื่อสถาบัน โลโก้ และชุดสีพรีเมียมสำหรับการสร้างแบรนด์</p>
                </div>
              </div>

              <div className="space-y-4 bg-white p-4 rounded-xl border shadow-sm">
                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1.5">ชื่อวิทยาลัย / สถาบัน</label>
                  <input
                    type="text"
                    value={collegeName}
                    onChange={e => setCollegeName(e.target.value)}
                    className="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all font-medium"
                    placeholder="ระบุชื่อสถาบันการศึกษา"
                  />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                  <div>
                    <label className="block text-xs font-bold text-gray-700 mb-1.5">ตราสัญลักษณ์สถาบัน (Logo)</label>
                    <div className="flex items-center gap-3">
                      <div className="w-16 h-16 rounded-full border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden bg-slate-50 shrink-0">
                        {logoPreview ? (
                          <img src={logoPreview} alt="Preview" className="w-full h-full object-cover" />
                        ) : (
                          <Image size={24} className="text-gray-400" />
                        )}
                      </div>
                      <div className="space-y-1.5">
                        <label className="bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 text-xs font-bold px-3 py-1.5 rounded-lg cursor-pointer transition-colors inline-flex items-center gap-1">
                          <Upload size={13} />
                          อัปโหลดรูป
                          <input type="file" accept="image/png,image/jpeg,image/jpg,image/svg+xml" onChange={handleLogoChange} className="hidden" />
                        </label>
                        {logoPreview && (
                          <button type="button" onClick={handleRemoveLogo} className="text-red-500 hover:text-red-700 font-bold text-xs block px-1">
                            ลบรูปภาพ
                          </button>
                        )}
                      </div>
                    </div>
                    <p className="text-[10px] text-gray-400 mt-2">รองรับไฟล์ PNG, JPG, JPEG, SVG ขนาดสูงสุดไม่เกิน 2MB</p>
                  </div>
                </div>
              </div>

              {/* Theme Selector */}
              <div className="space-y-3">
                <label className="block text-xs font-bold text-gray-700">เลือกธีมและโทนสีพรีเมียม (Premium Theme Preset)</label>
                <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                  {themeOptions.map((opt) => {
                    const isSelected = selectedTheme === opt.id;
                    return (
                      <button
                        key={opt.id}
                        type="button"
                        onClick={() => setSelectedTheme(opt.id)}
                        className={`p-3 rounded-xl border text-left transition-all relative flex flex-col justify-between h-20 ${
                          isSelected
                            ? 'border-indigo-600 bg-white ring-2 ring-indigo-500/20 shadow-md'
                            : 'border-gray-200 bg-white hover:border-gray-300 hover:shadow-sm'
                        }`}
                      >
                        <div className="flex items-center justify-between w-full">
                          <span className="text-xs font-bold text-gray-800">{opt.name}</span>
                          {isSelected && <span className="bg-indigo-600 text-white p-0.5 rounded-full"><Check size={10} /></span>}
                        </div>
                        <div className="flex items-center justify-between mt-2">
                          <span className="text-[10px] text-gray-400 font-medium">{opt.label}</span>
                          <div className="flex gap-1">
                            <span className="w-4 h-4 rounded-full border shadow-sm" style={{ backgroundColor: opt.primaryColor }} />
                            <span className="w-4 h-4 rounded-full border shadow-sm" style={{ backgroundColor: opt.accentColor }} />
                          </div>
                        </div>
                      </button>
                    );
                  })}
                </div>
              </div>

              <button
                type="submit"
                disabled={isSubmitting || !collegeName.trim()}
                className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-4 rounded-lg shadow-md transition-all disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                {isSubmitting ? (
                  <><RefreshCw className="animate-spin" size={16} /> กำลังจัดเก็บ...</>
                ) : (
                  <><Check size={16} /> บันทึกการตั้งค่าทั้งหมด</>
                )}
              </button>
            </form>
          )}

          {/* ========================================== */}
          {/* TAB 2: MANAGE DEPARTMENTS                  */}
          {/* ========================================== */}
          {activeTab === 'depts' && (
            <div className="space-y-5 animate-fade-in">
              <div className="flex items-center gap-3">
                <div className="bg-gradient-to-br from-emerald-100 to-teal-100 p-3 rounded-xl border border-emerald-200">
                  <Paintbrush size={20} className="text-emerald-600" />
                </div>
                <div>
                  <h3 className="font-bold text-gray-800 text-sm">จัดการฝ่ายบริหารงาน</h3>
                  <p className="text-xs text-gray-500">จัดการ เพิ่ม ลบ แก้ไขชื่อฝ่ายงาน และสลับลำดับการแสดงผลบนโครงสร้างบอร์ด</p>
                </div>
              </div>

              {/* Add Dept Form */}
              <form onSubmit={handleAddDept} className="flex gap-2 bg-white p-3 rounded-xl border shadow-sm">
                <input
                  type="text"
                  value={newDeptName}
                  onChange={e => setNewDeptName(e.target.value)}
                  placeholder="ชื่อฝ่ายงานใหม่ (เช่น ฝ่ายวิชาการ)"
                  className="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                />
                <button type="submit" disabled={!newDeptName.trim()}
                  className="bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 text-white font-bold px-4 py-2 rounded-lg text-xs flex items-center gap-1 shadow-sm transition-colors">
                  <Plus size={15} /> เพิ่มฝ่ายงาน
                </button>
              </form>

              {/* Depts List */}
              <div className="bg-white rounded-xl border shadow-sm divide-y">
                {departments.map((dept, index) => (
                  <div key={dept.id} className="p-3.5 flex items-center justify-between gap-4 hover:bg-slate-50/50 transition-colors">
                    {editingDeptId === dept.id ? (
                      <div className="flex-1 flex gap-2 items-center">
                        <input
                          type="text"
                          value={editingDeptName}
                          onChange={e => setEditingDeptName(e.target.value)}
                          className="flex-1 border border-indigo-300 rounded px-2.5 py-1 text-sm outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <button onClick={() => handleSaveDeptName(dept.id)} className="bg-indigo-600 text-white text-xs font-bold px-3 py-1.5 rounded">
                          บันทึก
                        </button>
                        <button onClick={() => setEditingDeptId(null)} className="text-gray-500 hover:text-gray-700 text-xs px-2">
                          ยกเลิก
                        </button>
                      </div>
                    ) : (
                      <>
                        <div className="flex-1">
                          <span className="font-bold text-gray-800 text-sm">{dept.name}</span>
                          <span className="text-[10px] text-gray-400 ml-2 font-medium bg-slate-100 px-2 py-0.5 rounded-full">
                            ลำดับที่ {dept.sort_order}
                          </span>
                        </div>
                        <div className="flex items-center gap-1.5 shrink-0">
                          {/* Reordering */}
                          <button type="button" onClick={() => handleMoveDept(index, 'up')} disabled={index === 0}
                            className="p-1 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded disabled:opacity-20 transition-all">
                            <ArrowUp size={15} />
                          </button>
                          <button type="button" onClick={() => handleMoveDept(index, 'down')} disabled={index === departments.length - 1}
                            className="p-1 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded disabled:opacity-20 transition-all">
                            <ArrowDown size={15} />
                          </button>

                          {/* Edit / Delete */}
                          <button type="button" onClick={() => handleStartEditDept(dept)}
                            className="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded transition-all">
                            <Edit3 size={14} />
                          </button>
                          <button type="button" onClick={() => handleDeleteDept(dept)}
                            className="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded transition-all">
                            <Trash2 size={14} />
                          </button>
                        </div>
                      </>
                    )}
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* ========================================== */}
          {/* TAB 3: MANAGE JOBS                         */}
          {/* ========================================== */}
          {activeTab === 'jobs' && (
            <div className="space-y-5 animate-fade-in">
              <div className="flex items-center gap-3">
                <div className="bg-gradient-to-br from-blue-100 to-sky-100 p-3 rounded-xl border border-blue-200">
                  <Briefcase size={20} className="text-blue-600" />
                </div>
                <div>
                  <h3 className="font-bold text-gray-800 text-sm">จัดการตำแหน่งงาน / งานบริหาร</h3>
                  <p className="text-xs text-gray-500">เลือกฝ่ายงานเพื่อเพิ่มตำแหน่งงาน ลบ จัดลำดับ หรือย้ายฝ่ายงาน</p>
                </div>
              </div>

              {/* Department Selector */}
              <div className="bg-white p-3 rounded-xl border shadow-sm flex items-center justify-between gap-4">
                <label className="text-xs font-bold text-gray-700 shrink-0">เลือกฝ่ายงานเป้าหมาย:</label>
                <select
                  value={selectedDeptId}
                  onChange={e => { setSelectedDeptId(e.target.value); setEditingJobId(null); }}
                  className="border border-gray-300 rounded-lg p-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500 bg-white font-bold text-indigo-700 flex-1 max-w-md"
                >
                  {departments.map(d => (
                    <option key={d.id} value={d.id}>{d.name}</option>
                  ))}
                </select>
              </div>

              {/* Add Job Form */}
              {selectedDeptId && (
                <form onSubmit={handleAddJob} className="flex gap-2 bg-white p-3 rounded-xl border shadow-sm">
                  <input
                    type="text"
                    value={newJobName}
                    onChange={e => setNewJobName(e.target.value)}
                    placeholder="ชื่อตำแหน่งงานใหม่ (เช่น งานหลักสูตรการเรียนการสอน)"
                    className="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500"
                  />
                  <button type="submit" disabled={!newJobName.trim()}
                    className="bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white font-bold px-4 py-2 rounded-lg text-xs flex items-center gap-1 shadow-sm transition-colors shrink-0">
                    <Plus size={15} /> เพิ่มตำแหน่ง
                  </button>
                </form>
              )}

              {/* Jobs List */}
              <div className="bg-white rounded-xl border shadow-sm divide-y">
                {filteredJobs.length === 0 ? (
                  <div className="p-8 text-center text-gray-400 text-xs">
                    ยังไม่มีการเพิ่มตำแหน่งงานในฝ่ายนี้
                  </div>
                ) : (
                  filteredJobs.map((job, index) => (
                    <div key={job.id} className="p-3 flex items-center justify-between gap-4 hover:bg-slate-50/50 transition-colors">
                      {editingJobId === job.id ? (
                        <div className="flex-1 flex flex-col md:flex-row gap-2 items-stretch md:items-center">
                          <input
                            type="text"
                            value={editingJobName}
                            onChange={e => setEditingJobName(e.target.value)}
                            className="flex-1 border border-indigo-300 rounded px-2 py-1 text-sm outline-none focus:ring-2 focus:ring-indigo-500"
                          />
                          <select
                            value={editingJobDeptId}
                            onChange={e => setEditingJobDeptId(e.target.value)}
                            className="border border-gray-300 rounded p-1 text-xs outline-none focus:ring-2 focus:ring-indigo-500"
                          >
                            {departments.map(d => (
                              <option key={d.id} value={d.id}>{d.name}</option>
                            ))}
                          </select>
                          <div className="flex gap-2 justify-end">
                            <button onClick={() => handleSaveJob(job.id)} className="bg-indigo-600 text-white text-xs font-bold px-3 py-1 rounded">
                              บันทึก
                            </button>
                            <button onClick={() => setEditingJobId(null)} className="text-gray-500 text-xs px-2">
                              ยกเลิก
                            </button>
                          </div>
                        </div>
                      ) : (
                        <>
                          <div className="flex-1">
                            <span className="font-bold text-gray-800 text-sm leading-snug">{job.name}</span>
                            {(job.id >= 900 && job.id <= 904) && (
                              <span className="text-[9px] text-amber-700 bg-amber-50 border border-amber-200 ml-2 px-1.5 py-0.2 rounded font-black">
                                ตำแหน่งบริหารหลัก
                              </span>
                            )}
                          </div>
                          <div className="flex items-center gap-1 shrink-0">
                            {/* Reordering */}
                            <button type="button" onClick={() => handleMoveJob(index, 'up')} disabled={index === 0}
                              className="p-1 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded disabled:opacity-20 transition-all">
                              <ArrowUp size={14} />
                            </button>
                            <button type="button" onClick={() => handleMoveJob(index, 'down')} disabled={index === filteredJobs.length - 1}
                              className="p-1 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded disabled:opacity-20 transition-all">
                              <ArrowDown size={14} />
                            </button>

                            {/* Edit / Delete */}
                            <button type="button" onClick={() => handleStartEditJob(job)}
                              className="p-1 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded transition-all">
                              <Edit3 size={13} />
                            </button>
                            <button type="button" onClick={() => handleDeleteJob(job)} disabled={job.id >= 900 && job.id <= 904}
                              className="p-1 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded disabled:opacity-20 transition-all">
                              <Trash2 size={13} />
                            </button>
                          </div>
                        </>
                      )}
                    </div>
                  ))
                )}
              </div>
            </div>
          )}

          {/* ========================================== */}
          {/* TAB 4: COPY YEAR DATA                      */}
          {/* ========================================== */}
          {activeTab === 'copy_year' && (
            <div className="space-y-5 animate-fade-in">
              <div className="flex items-center gap-3">
                <div className="bg-gradient-to-br from-indigo-100 to-blue-100 p-3 rounded-xl border border-indigo-200">
                  <CalendarRange size={20} className="text-indigo-600" />
                </div>
                <div>
                  <h3 className="font-bold text-gray-800 text-sm">คัดลอกโครงสร้างข้ามปีการศึกษา (One-Click Year Copy)</h3>
                  <p className="text-xs text-gray-500">คัดลอกตำแหน่งงาน การจัดวางบุคลากร และบันทึกข้อคิดเห็นข้ามปีการศึกษา</p>
                </div>
              </div>

              <form onSubmit={handleCopyYearData} className="space-y-5">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-bold text-gray-600 mb-1.5">จากปีการศึกษา (ต้นทาง)</label>
                    <select
                      value={fromYear}
                      onChange={e => setFromYear(parseInt(e.target.value))}
                      className="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white font-medium"
                    >
                      {years.map(y => (
                        <option key={y} value={y}>ปีการศึกษา {y}</option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-gray-600 mb-1.5">ไปยังปีการศึกษา (ปลายทาง)</label>
                    <select
                      value={toYear}
                      onChange={e => setToYear(parseInt(e.target.value))}
                      className="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white font-medium"
                    >
                      {years.map(y => (
                        <option key={y} value={y}>ปีการศึกษา {y}</option>
                      ))}
                    </select>
                  </div>
                </div>

                {fromYear === toYear && (
                  <p className="text-red-500 text-xs font-bold flex gap-1 items-center">
                    <span>⚠️</span>
                    <span>กรุณาเลือกปีการศึกษาต้นทางและปลายทางให้แตกต่างกัน</span>
                  </p>
                )}

                {/* Warning Card */}
                <div className="bg-red-50 border border-red-200 rounded-xl p-4 flex gap-3 text-red-800 text-xs leading-relaxed shadow-sm">
                  <AlertTriangle size={22} className="text-red-500 shrink-0 mt-0.5" />
                  <div>
                    <span className="font-bold block mb-0.5 text-red-950">🚨 คำเตือนความปลอดภัยสูงสุด:</span>
                    การดำเนินการนี้จะดึงข้อมูลโครงสร้างการบริหารและผู้ครองตำแหน่งของ <strong className="text-indigo-950">ปีการศึกษา {fromYear}</strong> นำมาเขียนทับปี <strong className="text-indigo-950">ปีการศึกษา {toYear}</strong>
                    <div className="mt-1.5 font-bold text-red-700 bg-white/60 p-2 rounded border border-red-100">
                      ⚠️ ข้อมูลผังการจัดวางทั้งหมดที่มีใน ปีการศึกษา {toYear} จะถูกลบถาวรและกู้คืนไม่ได้!
                    </div>
                  </div>
                </div>

                <button
                  type="submit"
                  disabled={isSubmitting || fromYear === toYear}
                  className="w-full bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white font-bold py-2.5 px-4 rounded-lg shadow-md transition-all disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                >
                  {isSubmitting ? (
                    <><RefreshCw size={16} className="animate-spin" /> กำลังคัดลอกข้อมูล...</>
                  ) : (
                    <><Copy size={16} /> คัดลอกโครงสร้างและเขียนทับทันที</>
                  )}
                </button>
              </form>
            </div>
          )}

          {/* ========================================== */}
          {/* TAB 5: CHANGE PASSWORD                     */}
          {/* ========================================== */}
          {activeTab === 'password' && (
            <div className="space-y-5">
              <div className="flex items-center gap-3">
                <div className="bg-gradient-to-br from-amber-100 to-orange-100 p-3 rounded-xl border border-amber-200">
                  <KeyRound size={20} className="text-amber-600" />
                </div>
                <div>
                  <h3 className="font-bold text-gray-800 text-sm">เปลี่ยนรหัสผ่านผู้ดูแลระบบ</h3>
                  <p className="text-xs text-gray-500">เปลี่ยนรหัสลับสำหรับการล็อกอินเข้าควบคุมบอร์ดจัดการโครงสร้าง</p>
                </div>
              </div>

              <form onSubmit={handleChangePassword} className="space-y-4 bg-white p-4 rounded-xl border shadow-sm">
                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">รหัสผ่านปัจจุบัน</label>
                  <div className="relative">
                    <input
                      type={showCurrent ? 'text' : 'password'}
                      value={currentPw}
                      onChange={e => setCurrentPw(e.target.value)}
                      className="w-full border border-gray-300 rounded-lg p-2.5 pr-10 text-sm outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                      placeholder="••••••••"
                    />
                    <button type="button" onClick={() => setShowCurrent(!showCurrent)}
                      className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                      {showCurrent ? <EyeOff size={16} /> : <Eye size={16} />}
                    </button>
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">รหัสผ่านใหม่</label>
                  <div className="relative">
                    <input
                      type={showNew ? 'text' : 'password'}
                      value={newPw}
                      onChange={e => setNewPw(e.target.value)}
                      className="w-full border border-gray-300 rounded-lg p-2.5 pr-10 text-sm outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                      placeholder="อย่างน้อย 4 ตัวอักษร"
                    />
                    <button type="button" onClick={() => setShowNew(!showNew)}
                      className="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                      {showNew ? <EyeOff size={16} /> : <Eye size={16} />}
                    </button>
                  </div>
                  {newPw && newPw.length < 4 && (
                    <p className="text-red-500 text-[10px] mt-1 font-bold">รหัสผ่านใหม่ต้องยาวอย่างน้อย 4 ตัวอักษร</p>
                  )}
                </div>

                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">ยืนยันรหัสผ่านใหม่</label>
                  <input
                    type="password"
                    value={confirmPw}
                    onChange={e => setConfirmPw(e.target.value)}
                    className={`w-full border rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 transition-all ${
                      confirmPw && confirmPw !== newPw ? 'border-red-400 bg-red-50 focus:ring-red-400' : 'border-gray-300'
                    }`}
                    placeholder="พิมพ์ยืนยันรหัสผ่านใหม่อีกครั้ง"
                  />
                  {confirmPw && confirmPw !== newPw && (
                    <p className="text-red-500 text-[10px] mt-1 font-bold">รหัสผ่านที่ป้อนไม่ตรงกัน</p>
                  )}
                </div>

                <button
                  type="submit"
                  disabled={isSubmitting || !currentPw || !newPw || !confirmPw || newPw !== confirmPw || newPw.length < 4}
                  className="w-full bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white font-bold py-2.5 px-4 rounded-lg shadow-md transition-all disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                >
                  {isSubmitting ? (
                    <><RefreshCw size={16} className="animate-spin" /> กำลังประมวลผล...</>
                  ) : (
                    <><KeyRound size={16} /> อัปเดตรหัสผ่านใหม่</>
                  )}
                </button>
              </form>
            </div>
          )}

        </div>
      </div>
    </div>
  );
};

export default SettingsModal;
