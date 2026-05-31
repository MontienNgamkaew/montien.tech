import React, { useState } from 'react';
import { 
  X, BarChart3, Users, Briefcase, Award, Sparkles, 
  AlertTriangle, CheckCircle2, Info, ChevronRight, Activity, Shell
} from 'lucide-react';

const AdvancedAnalyticsModal = ({ 
  personnel = [], 
  assignments = [], 
  jobs = [], 
  departments = [], 
  academicYear = 2569, 
  onClose 
}) => {
  const [selectedDeptId, setSelectedDeptId] = useState(null);

  // 1. Total Stats
  const totalPersonnel = personnel.length;
  const adminJobs = jobs.filter(j => !(j.id >= 900 && j.id <= 904));
  const totalJobsCount = adminJobs.length;

  // 2. Main Title Distribution Calculations
  const titleCounts = {};
  personnel.forEach(p => {
    const title = p.main_title || 'ไม่ระบุประเภท';
    titleCounts[title] = (titleCounts[title] || 0) + 1;
  });

  const totalPeople = personnel.length;
  const titleDataRaw = Object.entries(titleCounts).map(([title, count]) => ({
    title,
    count,
    percentage: totalPeople > 0 ? ((count / totalPeople) * 100).toFixed(1) : 0
  }));

  // Define premium colors for Donut slices
  const COLORS_MAP = {
    'ผู้อำนวยการ': { stroke: '#ef4444', text: 'text-red-500', bg: 'bg-red-50', border: 'border-red-100', dot: 'bg-red-500' },
    'รองผู้อำนวยการ': { stroke: '#6366f1', text: 'text-indigo-500', bg: 'bg-indigo-50', border: 'border-indigo-100', dot: 'bg-indigo-500' },
    'ข้าราชการ': { stroke: '#10b981', text: 'text-emerald-500', bg: 'bg-emerald-50', border: 'border-emerald-100', dot: 'bg-emerald-500' },
    'พนักงานราชการ': { stroke: '#3b82f6', text: 'text-blue-500', bg: 'bg-blue-50', border: 'border-blue-100', dot: 'bg-blue-500' },
    'ครูพิเศษสอน': { stroke: '#f59e0b', text: 'text-amber-500', bg: 'bg-amber-50', border: 'border-amber-100', dot: 'bg-amber-500' },
    'เจ้าหน้าที่': { stroke: '#8b5cf6', text: 'text-purple-500', bg: 'bg-purple-50', border: 'border-purple-100', dot: 'bg-purple-500' },
  };

  let accumulatedPercent = 0;
  const slices = titleDataRaw.map((data) => {
    const percent = data.count / totalPeople;
    const strokeDasharray = `${(percent * 314.159).toFixed(2)} 314.159`;
    const strokeDashoffset = (accumulatedPercent * 314.159).toFixed(2);
    accumulatedPercent += percent;
    
    const theme = COLORS_MAP[data.title] || { stroke: '#64748b', text: 'text-slate-500', bg: 'bg-slate-50', border: 'border-slate-100', dot: 'bg-slate-500' };

    return {
      ...data,
      strokeDasharray,
      strokeDashoffset: `-${strokeDashoffset}`,
      theme,
    };
  });

  // 3. Workload Tier Calculation
  const personWorkload = {};
  personnel.forEach(p => {
    personWorkload[p.id] = 0;
  });
  assignments.forEach(a => {
    if (personWorkload[a.personnel_id] !== undefined) {
      personWorkload[a.personnel_id] += 1;
    }
  });

  const tiers = {
    heavy: [],      // 3+ jobs
    moderate: [],   // 2 jobs
    normal: [],     // 1 job
    unassigned: [], // 0 jobs
  };

  personnel.forEach(p => {
    const count = personWorkload[p.id];
    if (count === 0) tiers.unassigned.push(p);
    else if (count === 1) tiers.normal.push(p);
    else if (count === 2) tiers.moderate.push(p);
    else tiers.heavy.push(p);
  });

  const heavyPct = totalPersonnel > 0 ? ((tiers.heavy.length / totalPersonnel) * 100).toFixed(0) : 0;
  const moderatePct = totalPersonnel > 0 ? ((tiers.moderate.length / totalPersonnel) * 100).toFixed(0) : 0;
  const normalPct = totalPersonnel > 0 ? ((tiers.normal.length / totalPersonnel) * 100).toFixed(0) : 0;
  const unassignedPct = totalPersonnel > 0 ? ((tiers.unassigned.length / totalPersonnel) * 100).toFixed(0) : 0;

  // 4. Busiest Staff Members (Top 5)
  const busiestStaff = [...personnel]
    .map(p => {
      const workloadCount = personWorkload[p.id];
      const pAssignments = assignments.filter(a => a.personnel_id === p.id);
      const rolesInfo = pAssignments.map(a => {
        const job = jobs.find(j => j.id === a.job_id);
        return {
          jobName: job ? job.name : 'ไม่ระบุภารกิจ',
          role: a.role
        };
      });
      return {
        ...p,
        workloadCount,
        rolesInfo
      };
    })
    .filter(p => p.workloadCount > 0)
    .sort((a, b) => b.workloadCount - a.workloadCount)
    .slice(0, 5);

  // 5. Departmental Vacancy Status
  const deptAnalytics = departments.map(d => {
    const deptJobs = jobs.filter(j => j.department_id === d.id && !(j.id >= 900 && j.id <= 904));
    
    // Vacant jobs list: Jobs in this department with no supervisor ('หัวหน้างาน' หรือ 'หัวหน้าแผนกวิชา') assigned
    const vacantJobs = deptJobs.filter(j => {
      const hasHead = assignments.some(a => a.job_id === j.id && (a.role === 'หัวหน้างาน' || a.role === 'หัวหน้าแผนกวิชา'));
      return !hasHead;
    });

    return {
      id: d.id,
      name: d.name,
      totalJobs: deptJobs.length,
      vacantCount: vacantJobs.length,
      assignedCount: deptJobs.length - vacantJobs.length,
      vacancyPct: deptJobs.length > 0 ? ((vacantJobs.length / deptJobs.length) * 100).toFixed(0) : 0,
      vacantJobs
    };
  });

  const selectedDept = deptAnalytics.find(da => da.id === selectedDeptId) || null;

  // 6. Dynamic Insights (Automated summary messages)
  const insights = [];
  
  if (tiers.heavy.length > 0) {
    insights.push({
      type: 'warning',
      text: `ตรวจพบครู/บุคลากรจำนวน ${tiers.heavy.length} คน ที่มีภาระงานสูงผิดปกติ (รับผิดชอบตั้งแต่ 3 บทบาท/หน้าที่ขึ้นไป) แนะนำให้พิจารณากระจายงานไปยังผู้ที่มีภาระงานเบาบางกว่า เพื่อลดโอกาสการเหนื่อยล้าเกินขีดจำกัด`,
      icon: AlertTriangle,
      color: 'border-rose-200 bg-rose-50 text-rose-800'
    });
  } else {
    insights.push({
      type: 'success',
      text: `สัดส่วนภาระงานสมดุลยอดเยี่ยม! ไม่มีบุคลากรคนใดที่ได้รับมอบหมายเกิน 3 หน้าที่หลักขึ้นไปในรอบปีการศึกษานี้`,
      icon: CheckCircle2,
      color: 'border-emerald-200 bg-emerald-50 text-emerald-800'
    });
  }

  if (tiers.unassigned.length > 0) {
    insights.push({
      type: 'info',
      text: `มีบุคลากรในทะเบียนจำนวน ${tiers.unassigned.length} คน ที่ยังไม่ถูกจัดสรรลงตำแหน่งงานใดๆ ของปีการศึกษา ${academicYear} (สามารถลากจัดวางเพิ่มเติมจากแถบรายชื่อด้านขวาของหน้าต่างหลัก)`,
      icon: Info,
      color: 'border-amber-200 bg-amber-50 text-amber-800'
    });
  }

  const highVacancyDept = deptAnalytics.find(da => parseFloat(da.vacancyPct) >= 25);
  if (highVacancyDept) {
    insights.push({
      type: 'danger',
      text: `ฝ่าย "${highVacancyDept.name}" มีอัตราร้อยละตำแหน่งงานว่างระดับหัวหน้างานค่อนข้างสูง (${highVacancyDept.vacancyPct}%) ซึ่งมีตำแหน่งงานยังไม่ได้รับการแต่งตั้งหัวหน้าบริหารอยู่ถึง ${highVacancyDept.vacantCount} งาน`,
      icon: AlertTriangle,
      color: 'border-red-200 bg-red-50 text-red-800'
    });
  } else {
    insights.push({
      type: 'success',
      text: `ทุกฝ่ายในสถานศึกษาได้รับการบรรจุตำแหน่งงานบริหารระดับหัวหน้างานอย่างครอบคลุม อัตราเฉลี่ยตำแหน่งงานว่างอยู่ในระดับปลอดภัยต่ำกว่า 25%`,
      icon: CheckCircle2,
      color: 'border-emerald-200 bg-emerald-50 text-emerald-800'
    });
  }

  return (
    <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-md flex items-center justify-center z-[150] p-4 animate-fade-in" onClick={onClose}>
      <div 
        className="bg-slate-50 rounded-3xl shadow-2xl w-full max-w-6xl overflow-hidden border border-white transition-all transform scale-100 flex flex-col h-[90vh]" 
        onClick={e => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex justify-between items-center px-6 py-4 bg-gradient-to-r from-indigo-900 to-slate-900 text-white shrink-0">
          <div className="flex items-center gap-3">
            <div className="bg-white/10 p-2 rounded-xl backdrop-blur-sm border border-white/10">
              <BarChart3 size={22} className="text-indigo-300" />
            </div>
            <div>
              <h2 className="text-lg font-black tracking-tight">ระบบแดชบอร์ดและการวิเคราะห์ข้อมูลเชิงลึก</h2>
              <p className="text-xs text-indigo-200 font-medium">โครงสร้างบุคลากรและการจัดสรรงาน — ปีการศึกษา {academicYear}</p>
            </div>
          </div>
          <button 
            onClick={onClose} 
            className="text-white/60 hover:text-white bg-white/10 hover:bg-white/20 p-2 rounded-full transition-all border border-white/5"
          >
            <X size={18} />
          </button>
        </div>

        {/* Modal Main Body */}
        <div className="p-6 overflow-y-auto flex-1 space-y-6">
          
          {/* Quick Stats Grid */}
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div className="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm flex items-center gap-4">
              <div className="bg-indigo-50 text-indigo-600 p-3 rounded-xl">
                <Users size={22} />
              </div>
              <div>
                <span className="text-[11px] text-gray-400 font-bold block uppercase tracking-wider">บุคลากรทั้งหมด</span>
                <span className="text-2xl font-black text-slate-800 leading-tight">{totalPersonnel} <span className="text-xs font-semibold opacity-60">คน</span></span>
              </div>
            </div>

            <div className="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm flex items-center gap-4">
              <div className="bg-emerald-50 text-emerald-600 p-3 rounded-xl">
                <Briefcase size={22} />
              </div>
              <div>
                <span className="text-[11px] text-gray-400 font-bold block uppercase tracking-wider">ตำแหน่ง/ภารกิจบริหาร</span>
                <span className="text-2xl font-black text-slate-800 leading-tight">{totalJobsCount} <span className="text-xs font-semibold opacity-60">ตำแหน่ง</span></span>
              </div>
            </div>

            <div className="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm flex items-center gap-4">
              <div className="bg-amber-50 text-amber-600 p-3 rounded-xl">
                <Activity size={22} />
              </div>
              <div>
                <span className="text-[11px] text-gray-400 font-bold block uppercase tracking-wider">สัดส่วนจัดวางงานแล้ว</span>
                <span className="text-2xl font-black text-slate-800 leading-tight">
                  {totalPersonnel > 0 ? (((totalPersonnel - tiers.unassigned.length) / totalPersonnel) * 100).toFixed(0) : 0}%
                </span>
              </div>
            </div>

            <div className="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm flex items-center gap-4">
              <div className="bg-rose-50 text-rose-600 p-3 rounded-xl">
                <Award size={22} />
              </div>
              <div>
                <span className="text-[11px] text-gray-400 font-bold block uppercase tracking-wider">ภาระงานสูงสุดในระบบ</span>
                <span className="text-2xl font-black text-slate-800 leading-tight">
                  {busiestStaff.length > 0 ? busiestStaff[0].workloadCount : 0} <span className="text-xs font-semibold opacity-60">งาน/คน</span>
                </span>
              </div>
            </div>
          </div>

          {/* Charts Row */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            {/* Donut Chart Segment */}
            <div className="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col justify-between">
              <div className="flex items-center gap-2 mb-4 border-b border-slate-50 pb-3">
                <Award size={18} className="text-indigo-500" />
                <h3 className="font-bold text-gray-800 text-sm">สัดส่วนประเภทครูและบุคลากรทางการศึกษา (Main Title)</h3>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-12 items-center gap-6">
                {/* SVG Visualizer */}
                <div className="md:col-span-5 flex justify-center relative items-center">
                  <div className="w-36 h-36 relative">
                    <svg width="100%" height="100%" viewBox="0 0 120 120" className="transform -rotate-90">
                      <circle cx="60" cy="60" r="50" fill="transparent" stroke="#f1f5f9" strokeWidth="12" />
                      {slices.map((slice, i) => (
                        <circle
                          key={i}
                          cx="60"
                          cy="60"
                          r="50"
                          fill="transparent"
                          stroke={slice.theme.stroke}
                          strokeWidth="12"
                          strokeDasharray={slice.strokeDasharray}
                          strokeDashoffset={slice.strokeDashoffset}
                          className="transition-all duration-350 hover:stroke-[15] cursor-pointer"
                        />
                      ))}
                    </svg>
                    
                    {/* Inner Donut Text */}
                    <div className="absolute inset-0 flex flex-col justify-center items-center pointer-events-none">
                      <span className="text-3xl font-black text-slate-800 leading-none">{totalPersonnel}</span>
                      <span className="text-[10px] text-gray-400 font-bold tracking-widest mt-0.5">บุคลากรรวม</span>
                    </div>
                  </div>
                </div>

                {/* Legends */}
                <div className="md:col-span-7 space-y-2">
                  {slices.length === 0 ? (
                    <p className="text-xs text-gray-400 text-center">ไม่มีข้อมูลบุคลากร</p>
                  ) : (
                    slices.map((slice, i) => (
                      <div key={i} className="flex justify-between items-center text-xs p-1.5 hover:bg-slate-50 rounded-lg transition-colors border border-transparent hover:border-slate-100">
                        <div className="flex items-center gap-2 font-medium text-slate-700">
                          <span className={`w-2.5 h-2.5 rounded-full ${slice.theme.dot} shrink-0`} />
                          <span>{slice.title}</span>
                        </div>
                        <div className="font-bold text-slate-900 flex gap-2 items-center">
                          <span>{slice.count} คน</span>
                          <span className="px-1.5 py-0.5 rounded bg-slate-100 text-[10px] text-gray-500">{slice.percentage}%</span>
                        </div>
                      </div>
                    ))
                  )}
                </div>
              </div>
            </div>

            {/* Workload Tiers Segment */}
            <div className="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col justify-between">
              <div className="flex items-center gap-2 mb-4 border-b border-slate-50 pb-3">
                <Activity size={18} className="text-emerald-500" />
                <h3 className="font-bold text-gray-800 text-sm">การจัดสมดุลและระดับภารกิจ (Workload Balanced Level)</h3>
              </div>

              <div className="space-y-4 flex-1 flex flex-col justify-center">
                
                {/* 1. Heavy */}
                <div>
                  <div className="flex justify-between text-xs font-bold mb-1.5">
                    <span className="text-rose-600 flex items-center gap-1.5">
                      <span className="w-2.5 h-2.5 rounded-full bg-rose-500 shrink-0 animate-ping" />
                      ภาระงานสูงหนาแน่น (3 งานขึ้นไป)
                    </span>
                    <span className="text-slate-700">{tiers.heavy.length} คน ({heavyPct}%)</span>
                  </div>
                  <div className="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                    <div className="bg-gradient-to-r from-rose-500 to-red-600 h-full rounded-full transition-all" style={{ width: `${heavyPct}%` }}></div>
                  </div>
                </div>

                {/* 2. Moderate */}
                <div>
                  <div className="flex justify-between text-xs font-bold mb-1.5">
                    <span className="text-amber-600 flex items-center gap-1.5">
                      <span className="w-2.5 h-2.5 rounded-full bg-amber-500 shrink-0" />
                      ภาระงานปานกลาง (2 งาน)
                    </span>
                    <span className="text-slate-700">{tiers.moderate.length} คน ({moderatePct}%)</span>
                  </div>
                  <div className="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                    <div className="bg-gradient-to-r from-amber-400 to-orange-500 h-full rounded-full transition-all" style={{ width: `${moderatePct}%` }}></div>
                  </div>
                </div>

                {/* 3. Normal */}
                <div>
                  <div className="flex justify-between text-xs font-bold mb-1.5">
                    <span className="text-emerald-600 flex items-center gap-1.5">
                      <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0" />
                      ภาระงานปกติที่เหมาะสม (1 งาน)
                    </span>
                    <span className="text-slate-700">{tiers.normal.length} คน ({normalPct}%)</span>
                  </div>
                  <div className="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                    <div className="bg-gradient-to-r from-emerald-400 to-green-500 h-full rounded-full transition-all" style={{ width: `${normalPct}%` }}></div>
                  </div>
                </div>

                {/* 4. Unassigned */}
                <div>
                  <div className="flex justify-between text-xs font-bold mb-1.5">
                    <span className="text-slate-500 flex items-center gap-1.5">
                      <span className="w-2.5 h-2.5 rounded-full bg-slate-400 shrink-0" />
                      ยังไม่ได้รับการจัดสรรงาน (0 งาน)
                    </span>
                    <span className="text-slate-700">{tiers.unassigned.length} คน ({unassignedPct}%)</span>
                  </div>
                  <div className="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                    <div className="bg-gradient-to-r from-slate-400 to-slate-500 h-full rounded-full transition-all" style={{ width: `${unassignedPct}%` }}></div>
                  </div>
                </div>

              </div>
            </div>

          </div>

          {/* Busiest Personnel & Department Vacancies Row */}
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            {/* Top 5 Busiest Personnel */}
            <div className="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm lg:col-span-7 flex flex-col justify-between">
              <div>
                <div className="flex items-center gap-2 mb-4 border-b border-slate-50 pb-3">
                  <Users size={18} className="text-rose-500" />
                  <h3 className="font-bold text-gray-800 text-sm">ทำเนียบ 5 อันดับแรกผู้รับผิดชอบภารกิจหนาแน่นที่สุด</h3>
                </div>

                <div className="space-y-3.5">
                  {busiestStaff.length === 0 ? (
                    <p className="text-xs text-gray-400 text-center py-8">ไม่มีข้อมูลภาระงานบุคลากร</p>
                  ) : (
                    busiestStaff.map((p, index) => {
                      const colors = [
                        'bg-red-500 border-red-500 text-white',
                        'bg-orange-500 border-orange-500 text-white',
                        'bg-amber-500 border-amber-500 text-white',
                        'bg-yellow-500 border-yellow-500 text-slate-800',
                        'bg-slate-500 border-slate-500 text-white'
                      ];
                      return (
                        <div key={p.id} className="p-3 border border-slate-100 rounded-2xl hover:bg-slate-50 transition-all flex flex-col md:flex-row md:items-center justify-between gap-3">
                          <div className="flex items-center gap-3">
                            <span className={`w-6 h-6 rounded-full text-xs font-bold flex items-center justify-center shrink-0 border ${colors[index] || 'bg-gray-100 text-gray-600'}`}>
                              {index + 1}
                            </span>
                            <div>
                              <span className="font-bold text-slate-800 text-sm block leading-snug">{p.name}</span>
                              <span className="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded font-bold">{p.main_title}</span>
                            </div>
                          </div>

                          <div className="flex items-center gap-4 self-end md:self-auto">
                            {/* Role List Pills */}
                            <div className="flex flex-wrap gap-1 justify-end max-w-xs md:max-w-md">
                              {p.rolesInfo.slice(0, 2).map((r, i) => (
                                <span key={i} className="text-[10px] bg-indigo-50 text-indigo-700 font-medium px-2 py-0.5 rounded-md border border-indigo-100">
                                  {r.jobName.substring(0, 15)}{r.jobName.length > 15 && '...'} ({r.role})
                                </span>
                              ))}
                              {p.rolesInfo.length > 2 && (
                                <span className="text-[9px] bg-indigo-600 text-white font-bold px-1.5 py-0.5 rounded-md">
                                  +{p.rolesInfo.length - 2} งาน
                                </span>
                              )}
                            </div>

                            <span className="px-3 py-1 bg-red-100 border border-red-200 text-red-700 rounded-full font-black text-xs shrink-0 flex items-center gap-1 shadow-sm animate-pulse">
                              🔥 {p.workloadCount} งาน
                            </span>
                          </div>
                        </div>
                      );
                    })
                  )}
                </div>
              </div>
            </div>

            {/* Department Vacancies & Job List */}
            <div className="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm lg:col-span-5 flex flex-col">
              <div className="flex items-center gap-2 mb-4 border-b border-slate-50 pb-3 shrink-0">
                <Briefcase size={18} className="text-amber-500" />
                <h3 className="font-bold text-gray-800 text-sm">อัตราและรายชื่อตำแหน่งว่าง (หัวหน้างาน) แยกตามฝ่าย</h3>
              </div>

              {/* Department Selector */}
              <div className="grid grid-cols-2 gap-2 mb-4 shrink-0">
                {deptAnalytics.map(da => (
                  <button
                    key={da.id}
                    onClick={() => setSelectedDeptId(selectedDeptId === da.id ? null : da.id)}
                    className={`p-2.5 rounded-xl border text-xs font-bold transition-all text-left flex flex-col justify-between ${
                      selectedDeptId === da.id
                        ? 'border-indigo-500 bg-indigo-50 text-indigo-900 shadow-sm'
                        : 'border-slate-100 bg-slate-50 text-slate-600 hover:border-slate-200'
                    }`}
                  >
                    <span className="block truncate">{da.name.replace('ฝ่าย', '')}</span>
                    <span className="flex items-center justify-between mt-1 text-[10px] opacity-75">
                      <span>ว่าง {da.vacantCount} งาน</span>
                      <span className={`px-1.5 py-0.5 rounded text-[8px] font-black ${parseFloat(da.vacancyPct) >= 25 ? 'bg-red-200 text-red-800' : 'bg-slate-200 text-slate-700'}`}>
                        {da.vacancyPct}%
                      </span>
                    </span>
                  </button>
                ))}
              </div>

              {/* Selected Department Details or Total Summary */}
              <div className="flex-1 overflow-y-auto max-h-[220px] pr-1.5">
                {selectedDept ? (
                  <div className="space-y-2.5">
                    <div className="flex justify-between items-center text-xs font-bold text-slate-700 border-b border-dashed border-slate-100 pb-1.5">
                      <span>ตำแหน่งงานที่ไม่มีหัวหน้าบริหาร ({selectedDept.name})</span>
                      <button 
                        onClick={() => setSelectedDeptId(null)} 
                        className="text-[10px] text-indigo-600 hover:underline"
                      >
                        ย้อนกลับ
                      </button>
                    </div>

                    {selectedDept.vacantJobs.length === 0 ? (
                      <div className="text-center py-6 text-xs text-gray-400">
                        🎉 ครบครัน! ฝ่ายนี้ไม่มีตำแหน่งหัวหน้างานว่าง
                      </div>
                    ) : (
                      selectedDept.vacantJobs.map(j => (
                        <div key={j.id} className="p-2 border border-dashed border-amber-200 bg-amber-50/30 rounded-xl text-xs flex justify-between items-center hover:bg-amber-50/50 transition-all">
                          <span className="font-semibold text-slate-800">{j.name}</span>
                          <span className="text-[9px] bg-amber-100 border border-amber-200 text-amber-700 font-bold px-2 py-0.5 rounded">
                            ยังไม่มีผู้รับงาน
                          </span>
                        </div>
                      ))
                    )}
                  </div>
                ) : (
                  <div className="space-y-3.5">
                    <span className="text-xs font-bold text-slate-400 block border-b border-slate-50 pb-1.5">สรุปงานบริหารที่ยังไม่มีหัวหน้าแต่งตั้ง</span>
                    
                    {deptAnalytics.filter(da => da.vacantCount > 0).length === 0 ? (
                      <div className="text-center py-8 text-xs text-gray-400">
                        🎉 ยอดเยี่ยม! แต่งตั้งหัวหน้างานครบถ้วนในทุกฝ่ายบริหาร
                      </div>
                    ) : (
                      deptAnalytics.map(da => (
                        <div key={da.id} className="flex items-center justify-between text-xs bg-slate-50 hover:bg-slate-100 p-2.5 rounded-xl border border-slate-100 transition-colors">
                          <div className="flex items-center gap-2">
                            <span className="w-2 h-2 rounded-full bg-indigo-500" />
                            <span className="font-bold text-slate-700">{da.name}</span>
                          </div>
                          <button
                            onClick={() => setSelectedDeptId(da.id)}
                            className="font-bold text-[10px] text-indigo-600 hover:text-indigo-800 flex items-center gap-0.5 bg-white border border-slate-150 px-2 py-1 rounded-lg shadow-sm"
                          >
                            <span>ดู {da.vacantCount} งานที่ว่าง</span>
                            <ChevronRight size={10} />
                          </button>
                        </div>
                      ))
                    )}
                  </div>
                )}
              </div>

            </div>

          </div>

          {/* AI Executive Summary Segment */}
          <div className="bg-gradient-to-br from-indigo-950 to-slate-900 text-white rounded-3xl p-6 shadow-xl relative overflow-hidden shrink-0 border border-indigo-800">
            <div className="absolute top-0 right-0 w-32 h-32 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none" />
            <div className="absolute bottom-0 left-10 w-24 h-24 bg-rose-500/10 rounded-full blur-2xl pointer-events-none" />
            
            <div className="flex items-center gap-2 mb-4 border-b border-indigo-900 pb-3">
              <Sparkles size={18} className="text-amber-400" />
              <h3 className="font-black text-sm tracking-wide">บทวิเคราะห์และการประเมินสถิติระบบบริหาร (AI Executive Summary)</h3>
            </div>

            <div className="space-y-3">
              {insights.map((ins, i) => {
                const Icon = ins.icon;
                return (
                  <div key={i} className="flex gap-3 items-start p-3 bg-white/5 border border-white/10 rounded-2xl text-xs backdrop-blur-sm leading-relaxed hover:bg-white/10 transition-all">
                    <Icon size={18} className="text-amber-400 shrink-0 mt-0.5" />
                    <div>
                      {ins.text}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

        </div>

        {/* Footer */}
        <div className="px-6 py-4 bg-slate-100 border-t border-slate-200 flex justify-between items-center text-xs text-gray-500 shrink-0">
          <span className="font-semibold flex items-center gap-1">
            <Shell size={12} className="animate-spin text-indigo-600" /> 
            ข้อมูลคำนวณแบบ Real-time ณ ปัจจุบัน
          </span>
          <span>© ระบบผังโครงสร้างวิทยาลัยการอาชีพพนมไพร</span>
        </div>
      </div>
    </div>
  );
};

export default AdvancedAnalyticsModal;
