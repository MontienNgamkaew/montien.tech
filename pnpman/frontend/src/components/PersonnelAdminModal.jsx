import React, { useState, useRef } from 'react';
import axios from 'axios';
import Swal from 'sweetalert2';
import { X, Plus, Trash2, Edit2, Check, Upload, Download, FileSpreadsheet, User } from 'lucide-react';
import { BASE_URL, API_URL } from '../utils/api';

const PersonnelAdminModal = ({ personnel, onClose, onRefresh }) => {
  const [editingId, setEditingId] = useState(null);
  const [editName, setEditName] = useState('');
  const [editTitle, setEditTitle] = useState('');
  const [newName, setNewName] = useState('');
  const [newTitle, setNewTitle] = useState('');
  const [isAdding, setIsAdding] = useState(false);
  const [isImporting, setIsImporting] = useState(false);
  const [filterCategory, setFilterCategory] = useState('All');
  
  const fileInputRef = useRef(null);
  const addFileInputRef = useRef(null);
  const editFileInputRef = useRef(null);

  const [addFile, setAddFile] = useState(null);
  const [addPhotoPreview, setAddPhotoPreview] = useState(null);
  const [editFile, setEditFile] = useState(null);
  const [editPhotoPreview, setEditPhotoPreview] = useState(null);
  const [deleteEditPhoto, setDeleteEditPhoto] = useState(false);

  const TITLE_OPTIONS = ['ผู้อำนวยการ', 'รองผู้อำนวยการ', 'ข้าราชการครู', 'พนักงานราชการครู', 'ครูพิเศษสอน', 'เจ้าหน้าที่'];

  const handleAddFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      if (file.size > 2 * 1024 * 1024) {
        Swal.fire('แจ้งเตือน', 'ขนาดไฟล์รูปภาพห้ามเกิน 2MB', 'warning');
        return;
      }
      setAddFile(file);
      const reader = new FileReader();
      reader.onloadend = () => {
        setAddPhotoPreview(reader.result);
      };
      reader.readAsDataURL(file);
    }
  };

  const handleEditFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      if (file.size > 2 * 1024 * 1024) {
        Swal.fire('แจ้งเตือน', 'ขนาดไฟล์รูปภาพห้ามเกิน 2MB', 'warning');
        return;
      }
      setEditFile(file);
      setDeleteEditPhoto(false);
      const reader = new FileReader();
      reader.onloadend = () => {
        setEditPhotoPreview(reader.result);
      };
      reader.readAsDataURL(file);
    }
  };

  const handleAdd = async () => {
    if (!newName || !newTitle) return Swal.fire('แจ้งเตือน', 'กรุณากรอกข้อมูลให้ครบถ้วน', 'warning');
    try {
      const formData = new FormData();
      formData.append('action', 'add');
      formData.append('name', newName);
      formData.append('main_title', newTitle);
      if (addFile) {
        formData.append('photo', addFile);
      }
      await axios.post(`${API_URL}manage_personnel.php`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      setNewName('');
      setNewTitle('');
      setAddFile(null);
      setAddPhotoPreview(null);
      setIsAdding(false);
      onRefresh();
      Swal.fire({ title: 'เพิ่มสำเร็จ!', icon: 'success', timer: 1200, showConfirmButton: false });
    } catch (err) {
      Swal.fire('ผิดพลาด', err.response?.data?.message || 'Error', 'error');
    }
  };

  const handleUpdate = async (id) => {
    try {
      const formData = new FormData();
      formData.append('action', 'update');
      formData.append('id', id);
      formData.append('name', editName);
      formData.append('main_title', editTitle);
      if (editFile) {
        formData.append('photo', editFile);
      }
      if (deleteEditPhoto) {
        formData.append('delete_photo', 'true');
      }
      await axios.post(`${API_URL}manage_personnel.php`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      setEditingId(null);
      setEditFile(null);
      setEditPhotoPreview(null);
      setDeleteEditPhoto(false);
      onRefresh();
      Swal.fire({ title: 'แก้ไขสำเร็จ!', icon: 'success', timer: 1200, showConfirmButton: false });
    } catch (err) {
      Swal.fire('ผิดพลาด', err.response?.data?.message || 'Error', 'error');
    }
  };

  const handleDelete = async (id, name) => {
    const result = await Swal.fire({
      title: 'ยืนยันการลบ',
      html: `ลบ <strong>${name}</strong> ออกจากระบบ?<br/><span class="text-sm text-gray-500">งานที่ได้รับมอบหมายทั้งหมดจะถูกลบด้วย</span>`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'ลบเลย',
      cancelButtonText: 'ยกเลิก',
    });
    if (result.isConfirmed) {
      try {
        await axios.post(`${API_URL}manage_personnel.php`, { action: 'delete', id });
        onRefresh();
        Swal.fire({ title: 'ลบสำเร็จ!', icon: 'success', timer: 1200, showConfirmButton: false });
      } catch (err) {
        Swal.fire('ผิดพลาด', err.response?.data?.message || 'Error', 'error');
      }
    }
  };

  const handleCsvImport = async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    // Validate file extension
    if (!file.name.toLowerCase().endsWith('.csv')) {
      Swal.fire('ผิดพลาด', 'กรุณาเลือกไฟล์ .csv เท่านั้น', 'error');
      fileInputRef.current.value = '';
      return;
    }

    const result = await Swal.fire({
      title: 'นำเข้าข้อมูลจาก CSV',
      html: `
        <div style="text-align:left; font-size:14px;">
          <p><strong>ไฟล์:</strong> ${file.name}</p>
          <p><strong>ขนาด:</strong> ${(file.size / 1024).toFixed(1)} KB</p>
          <br/>
          <p class="text-gray-500">ระบบจะเพิ่มบุคลากรใหม่จากไฟล์ CSV</p>
        </div>
      `,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#6366f1',
      cancelButtonColor: '#6b7280',
      confirmButtonText: 'นำเข้าเลย',
      cancelButtonText: 'ยกเลิก',
    });

    if (!result.isConfirmed) {
      fileInputRef.current.value = '';
      return;
    }

    setIsImporting(true);
    const formData = new FormData();
    formData.append('csv_file', file);

    try {
      const res = await axios.post(`${API_URL}import_csv.php`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      onRefresh();

      let html = `<p style="font-size:16px; font-weight:bold; color:#059669;">เพิ่มสำเร็จ ${res.data.added} คน</p>`;
      if (res.data.skipped > 0) {
        html += `<p style="color:#d97706; margin-top:8px;">ข้าม ${res.data.skipped} รายการ</p>`;
      }
      if (res.data.errors && res.data.errors.length > 0) {
        html += `<div style="text-align:left; margin-top:12px; background:#fef2f2; padding:12px; border-radius:8px; font-size:12px; max-height:150px; overflow-y:auto;">`;
        res.data.errors.forEach(err => {
          html += `<p style="color:#dc2626; margin:2px 0;">• ${err}</p>`;
        });
        html += `</div>`;
      }

      Swal.fire({ title: 'นำเข้าเสร็จสิ้น', html, icon: 'success' });
    } catch (err) {
      Swal.fire('ผิดพลาด', err.response?.data?.message || 'ไม่สามารถนำเข้าได้', 'error');
    } finally {
      setIsImporting(false);
      fileInputRef.current.value = '';
    }
  };

  const handleDownloadTemplate = () => {
    window.open(`${API_URL}csv_template.php`, '_blank');
  };

  const startEdit = (p) => {
    setEditingId(p.id);
    setEditName(p.name);
    setEditTitle(p.main_title || '');
    setEditFile(null);
    setEditPhotoPreview(null);
    setDeleteEditPhoto(false);
  };

  // Sort & filter personnel by title order
  const sortedPersonnel = [...personnel]
    .filter(p => filterCategory === 'All' || (p.main_title || '').trim() === filterCategory || (filterCategory === 'ไม่ระบุ' && !(p.main_title || '').trim()))
    .sort((a, b) => {
      const titleA = (a.main_title || '').trim();
      const titleB = (b.main_title || '').trim();
      const idxA = TITLE_OPTIONS.indexOf(titleA);
      const idxB = TITLE_OPTIONS.indexOf(titleB);
      return (idxA === -1 ? 999 : idxA) - (idxB === -1 ? 999 : idxB);
    });

  // Group by title for group headers
  const getGroupTitle = (person, index) => {
    const title = (person.main_title || '').trim() || 'ไม่ระบุ';
    if (index === 0) return title;
    const prevTitle = (sortedPersonnel[index - 1].main_title || '').trim() || 'ไม่ระบุ';
    return title !== prevTitle ? title : null;
  };

  const TITLE_COLORS_MAP = {
    'ผู้อำนวยการ': 'bg-amber-100 text-amber-800 border-amber-200',
    'รองผู้อำนวยการ': 'bg-blue-100 text-blue-800 border-blue-200',
    'ข้าราชการครู': 'bg-emerald-100 text-emerald-800 border-emerald-200',
    'พนักงานราชการครู': 'bg-teal-100 text-teal-800 border-teal-200',
    'ครูพิเศษสอน': 'bg-violet-100 text-violet-800 border-violet-200',
    'เจ้าหน้าที่': 'bg-rose-100 text-rose-800 border-rose-200',
    'ไม่ระบุ': 'bg-gray-100 text-gray-600 border-gray-300',
  };

  // Count per category
  const titleCounts = TITLE_OPTIONS.reduce((acc, t) => {
    acc[t] = personnel.filter(p => (p.main_title || '').trim() === t).length;
    return acc;
  }, {});
  const unspecifiedCount = personnel.filter(p => !(p.main_title || '').trim()).length;

  return (
    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-[150] p-4" onClick={onClose}>
      <div className="bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden" onClick={e => e.stopPropagation()}>
        <div className="flex justify-between items-center p-5 border-b bg-gradient-to-r from-blue-50 to-white">
          <h2 className="text-lg font-bold text-gray-800">จัดการข้อมูลบุคลากร</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600 p-1 hover:bg-gray-100 rounded-full"><X size={22} /></button>
        </div>

        <div className="p-5 overflow-y-auto flex-1">
          {/* Add + Import Section */}
          <div className="mb-5 bg-white p-4 rounded-xl border shadow-sm">
            <div className="flex justify-between items-center mb-3 flex-wrap gap-2">
              <h3 className="font-bold text-gray-700 text-sm">เพิ่มบุคลากร</h3>
              <div className="flex gap-2 flex-wrap">
                {!isAdding && (
                  <button onClick={() => setIsAdding(true)} className="bg-emerald-100 text-emerald-700 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 hover:bg-emerald-200 transition-colors">
                    <Plus size={14} /> เพิ่มทีละคน
                  </button>
                )}
                <button onClick={handleDownloadTemplate} className="bg-blue-100 text-blue-700 px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 hover:bg-blue-200 transition-colors">
                  <Download size={14} /> ดาวน์โหลดตัวอย่าง CSV
                </button>
                <label className={`px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 transition-colors cursor-pointer ${isImporting ? 'bg-gray-200 text-gray-500 cursor-wait' : 'bg-violet-100 text-violet-700 hover:bg-violet-200'}`}>
                  <Upload size={14} />
                  {isImporting ? 'กำลังนำเข้า...' : 'นำเข้า CSV'}
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept=".csv"
                    className="hidden"
                    onChange={handleCsvImport}
                    disabled={isImporting}
                  />
                </label>
              </div>
            </div>

            {/* CSV Format Help */}
            <div className="bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-100 rounded-lg p-3 mb-3">
              <div className="flex items-start gap-2">
                <FileSpreadsheet size={16} className="text-indigo-500 mt-0.5 shrink-0" />
                <div className="text-xs text-gray-600">
                  <p className="font-bold text-indigo-800 mb-1">รูปแบบไฟล์ CSV</p>
                  <p>คอลัมน์: <code className="bg-white px-1.5 py-0.5 rounded border text-indigo-700 font-bold">ชื่อ-นามสกุล</code> , <code className="bg-white px-1.5 py-0.5 rounded border text-indigo-700 font-bold">ตำแหน่งหลัก</code></p>
                  <p className="mt-1">ตำแหน่งหลัก: {TITLE_OPTIONS.map((t, i) => (
                    <span key={t}><code className="bg-white px-1 py-0.5 rounded border text-gray-700">{t}</code>{i < TITLE_OPTIONS.length - 1 ? ' , ' : ''}</span>
                  ))}</p>
                </div>
              </div>
            </div>

            {/* Manual Add Form */}
            {isAdding && (
              <div className="flex gap-4 items-center bg-gray-55/50 p-4 rounded-xl border border-dashed border-indigo-200">
                {/* Round Photo Uploader */}
                <div className="relative group shrink-0 cursor-pointer" onClick={() => addFileInputRef.current?.click()} title="เลือกรูปภาพประจำตัว">
                  <div className="w-16 h-16 rounded-full border-2 border-dashed border-gray-300 flex flex-col items-center justify-center bg-white overflow-hidden hover:border-indigo-400 hover:bg-indigo-50/20 transition-all shadow-sm">
                    {addPhotoPreview ? (
                      <img src={addPhotoPreview} className="w-full h-full object-cover rounded-full" alt="Preview" />
                    ) : (
                      <>
                        <Upload size={16} className="text-gray-400 mb-0.5" />
                        <span className="text-[9px] font-bold text-gray-400 uppercase">รูปถ่าย</span>
                      </>
                    )}
                  </div>
                  {addPhotoPreview && (
                    <button
                      onClick={(e) => {
                        e.stopPropagation();
                        setAddFile(null);
                        setAddPhotoPreview(null);
                      }}
                      className="absolute -top-1 -right-1 bg-rose-500 hover:bg-rose-600 text-white rounded-full p-0.5 shadow-md transition-colors"
                      title="ลบรูปภาพออก"
                    >
                      <X size={10} />
                    </button>
                  )}
                  <input
                    ref={addFileInputRef}
                    type="file"
                    accept="image/*"
                    className="hidden"
                    onChange={handleAddFileChange}
                  />
                </div>

                <div className="flex-1 grid grid-cols-1 md:grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-bold text-gray-600 mb-1">ชื่อ-นามสกุล</label>
                    <input type="text" value={newName} onChange={e => setNewName(e.target.value)} className="w-full border rounded-lg p-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500 bg-white" placeholder="เช่น นายสมชาย ใจดี" />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-gray-600 mb-1">ตำแหน่งหลัก</label>
                    <select value={newTitle} onChange={e => setNewTitle(e.target.value)} className="w-full border rounded-lg p-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                      <option value="">-- เลือก --</option>
                      {TITLE_OPTIONS.map(t => <option key={t} value={t}>{t}</option>)}
                    </select>
                  </div>
                </div>
                <div className="flex gap-2 shrink-0 self-end">
                  <button onClick={handleAdd} className="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-indigo-700 shadow-sm transition-colors">บันทึก</button>
                  <button onClick={() => { setIsAdding(false); setAddFile(null); setAddPhotoPreview(null); }} className="bg-gray-250 text-gray-700 px-4 py-2 rounded-lg text-sm font-bold hover:bg-gray-300 transition-colors border">ยกเลิก</button>
                </div>
              </div>
            )}
          </div>

          {/* Personnel Table */}
          <div className="bg-white rounded-xl border shadow-sm overflow-hidden">
            <div className="bg-gray-50 border-b px-4 py-2.5 flex items-center justify-between flex-wrap gap-2">
              <span className="text-xs font-bold text-gray-600">รายชื่อบุคลากร</span>
              <span className="bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full text-xs font-bold">{sortedPersonnel.length}/{personnel.length} คน</span>
            </div>
            {/* Filter Tabs */}
            <div className="px-3 py-2 bg-gray-50/50 border-b flex gap-1.5 overflow-x-auto">
              <button onClick={() => setFilterCategory('All')}
                className={`px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-all ${
                  filterCategory === 'All' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-gray-600 border hover:bg-gray-100'}`}>
                ทั้งหมด <span className="opacity-70">({personnel.length})</span>
              </button>
              {TITLE_OPTIONS.map(t => (
                titleCounts[t] > 0 && (
                  <button key={t} onClick={() => setFilterCategory(t)}
                    className={`px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-all ${
                      filterCategory === t ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-gray-600 border hover:bg-gray-100'}`}>
                    {t} <span className="opacity-70">({titleCounts[t]})</span>
                  </button>
                )
              ))}
              {unspecifiedCount > 0 && (
                <button onClick={() => setFilterCategory('ไม่ระบุ')}
                  className={`px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-all ${
                    filterCategory === 'ไม่ระบุ' ? 'bg-red-500 text-white shadow-sm' : 'bg-white text-red-500 border border-red-200 hover:bg-red-50'}`}>
                  ⚠ ไม่ระบุ <span className="opacity-70">({unspecifiedCount})</span>
                </button>
              )}
            </div>
            <table className="w-full text-left text-sm">
              <thead className="bg-gray-50/50 border-b">
                <tr>
                  <th className="px-4 py-3 font-bold text-gray-600">ชื่อ-นามสกุล</th>
                  <th className="px-4 py-3 font-bold text-gray-600">ตำแหน่งหลัก</th>
                  <th className="px-4 py-3 font-bold text-gray-600 text-center w-28">จัดการ</th>
                </tr>
              </thead>
              <tbody className="divide-y">
                {sortedPersonnel.length === 0 && (
                  <tr>
                    <td colSpan={3} className="text-center py-10 text-gray-400">
                      <FileSpreadsheet size={32} className="mx-auto mb-2 text-gray-300" />
                      <p className="font-medium">{personnel.length === 0 ? 'ยังไม่มีบุคลากร' : 'ไม่พบบุคลากรในหมวดนี้'}</p>
                      {personnel.length === 0 && <p className="text-xs mt-1">กดปุ่ม "เพิ่มทีละคน" หรือ "นำเข้า CSV" ด้านบน</p>}
                    </td>
                  </tr>
                )}
                {sortedPersonnel.map((p, idx) => {
                  const groupHeader = filterCategory === 'All' ? getGroupTitle(p, idx) : null;
                  return (
                  <React.Fragment key={p.id}>
                    {groupHeader && (
                      <tr>
                        <td colSpan={3} className={`px-4 py-2 text-xs font-bold border-t-2 ${TITLE_COLORS_MAP[groupHeader] || 'bg-gray-100 text-gray-600'}`}>
                          {groupHeader} ({sortedPersonnel.filter((pp) => ((pp.main_title || '').trim() || 'ไม่ระบุ') === groupHeader).length} คน)
                        </td>
                      </tr>
                    )}
                  <tr className="hover:bg-gray-50/50 transition-colors">
                    <td className="px-4 py-2.5">
                      <div className="flex items-center gap-3">
                        {editingId === p.id ? (
                          <div className="relative group shrink-0 cursor-pointer" onClick={() => editFileInputRef.current?.click()} title="คลิกเพื่อเปลี่ยนรูปภาพ">
                            <div className="w-10 h-10 rounded-full border-2 border-indigo-300 overflow-hidden relative shadow-sm">
                              {editPhotoPreview ? (
                                <img src={editPhotoPreview} className="w-full h-full object-cover rounded-full" alt="Preview" />
                              ) : p.photo_path && !deleteEditPhoto ? (
                                <img src={`${BASE_URL}${p.photo_path}`} className="w-full h-full object-cover rounded-full" alt="Current" />
                              ) : (
                                <div className="w-full h-full bg-gray-100 flex items-center justify-center text-gray-400">
                                  <Upload size={14} />
                                </div>
                              )}
                              <div className="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                <Upload size={12} className="text-white" />
                              </div>
                            </div>
                            {(editPhotoPreview || (p.photo_path && !deleteEditPhoto)) && (
                              <button
                                onClick={(e) => {
                                  e.stopPropagation();
                                  setEditFile(null);
                                  setEditPhotoPreview(null);
                                  setDeleteEditPhoto(true);
                                }}
                                className="absolute -top-1 -right-1 bg-rose-500 hover:bg-rose-600 text-white rounded-full p-0.5 shadow-md transition-colors z-10"
                                title="ลบรูปประจำตัว"
                              >
                                <X size={8} />
                              </button>
                            )}
                            <input
                              ref={editFileInputRef}
                              type="file"
                              accept="image/*"
                              className="hidden"
                              onChange={handleEditFileChange}
                            />
                          </div>
                        ) : (
                          p.photo_path ? (
                            <img src={`${BASE_URL}${p.photo_path}`} className="w-10 h-10 rounded-full object-cover border border-gray-200 shadow-sm shrink-0" alt={p.name} />
                          ) : (
                            <div className={`w-10 h-10 rounded-full border flex items-center justify-center shadow-sm shrink-0 ${TITLE_COLORS_MAP[p.main_title || 'ไม่ระบุ'] || 'bg-gray-100 border-gray-200 text-gray-500'}`}>
                              <User size={16} />
                            </div>
                          )
                        )}
                        
                        {editingId === p.id ? (
                          <input type="text" value={editName} onChange={e => setEditName(e.target.value)} className="w-full border rounded p-1.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 bg-white" />
                        ) : (
                          <span className="font-semibold text-gray-800">{p.name}</span>
                        )}
                      </div>
                    </td>
                    <td className="px-4 py-2.5">
                      {editingId === p.id ? (
                        <select value={editTitle} onChange={e => setEditTitle(e.target.value)} className="w-full border rounded p-1 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                          <option value="">-- เลือกตำแหน่ง --</option>
                          {TITLE_OPTIONS.map(t => <option key={t} value={t}>{t}</option>)}
                        </select>
                      ) : (
                        (p.main_title || '').trim() ? (
                          <span className="text-gray-600 bg-gray-100 px-2 py-0.5 rounded text-xs font-medium">{p.main_title}</span>
                        ) : (
                          <span className="text-red-500 bg-red-50 px-2 py-0.5 rounded text-xs font-bold border border-red-200">⚠ ไม่ระบุ</span>
                        )
                      )}
                    </td>
                    <td className="px-4 py-2.5">
                      <div className="flex items-center justify-center gap-1.5">
                        {editingId === p.id ? (
                          <>
                            <button onClick={() => handleUpdate(p.id)} className="text-green-600 hover:bg-green-50 p-1.5 rounded" title="บันทึก"><Check size={14} /></button>
                            <button onClick={() => setEditingId(null)} className="text-gray-400 hover:bg-gray-100 p-1.5 rounded" title="ยกเลิก"><X size={14} /></button>
                          </>
                        ) : (
                          <>
                            <button onClick={() => startEdit(p)} className="text-blue-600 hover:bg-blue-50 p-1.5 rounded" title="แก้ไข"><Edit2 size={14} /></button>
                            <button onClick={() => handleDelete(p.id, p.name)} className="text-red-500 hover:bg-red-50 p-1.5 rounded" title="ลบ"><Trash2 size={14} /></button>
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                  </React.Fragment>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PersonnelAdminModal;
