import React from 'react';
import { Users, Briefcase, AlertCircle, CheckCircle, UserX, BarChart3 } from 'lucide-react';

const DashboardStats = ({ personnel, assignments, jobs, onOpenAnalytics }) => {
  const totalPersonnel = personnel.length;
  const totalJobs = jobs.filter(j => !(j.id >= 900 && j.id <= 904)).length;

  // People with at least 1 assignment
  const assignedPeopleIds = [...new Set(assignments.map(a => a.personnel_id))];
  const assignedCount = assignedPeopleIds.length;
  const unassignedCount = totalPersonnel - assignedCount;

  // Vacant head positions (หัวหน้างาน หรือ หัวหน้าแผนกวิชา with 0 assignments)
  const headJobs = jobs.filter(j => !(j.id >= 900 && j.id <= 904));
  const vacantHeadCount = headJobs.filter(j => {
    const headAssignments = assignments.filter(a => a.job_id === j.id && (a.role === 'หัวหน้างาน' || a.role === 'หัวหน้าแผนกวิชา'));
    return headAssignments.length === 0;
  }).length;

  // Total assignment count
  const totalAssignments = assignments.length;

  // Average assignments per person
  const avgAssignments = totalPersonnel > 0 ? (totalAssignments / totalPersonnel).toFixed(1) : 0;

  const stats = [
    { label: 'บุคลากรทั้งหมด', value: totalPersonnel, unit: 'คน', icon: Users, color: 'from-rose-700 to-red-900', bg: 'bg-rose-50', text: 'text-rose-800' },
    { label: 'ได้รับมอบหมาย', value: assignedCount, unit: 'คน', icon: CheckCircle, color: 'from-emerald-500 to-green-600', bg: 'bg-emerald-50', text: 'text-emerald-700' },
    { label: 'ยังไม่ได้มอบหมาย', value: unassignedCount, unit: 'คน', icon: UserX, color: unassignedCount > 0 ? 'from-amber-500 to-orange-600' : 'from-gray-400 to-gray-500', bg: unassignedCount > 0 ? 'bg-amber-50' : 'bg-gray-50', text: unassignedCount > 0 ? 'text-amber-700' : 'text-gray-500' },
    { label: 'ตำแหน่งว่าง (หน.)', value: vacantHeadCount, unit: `/${totalJobs}`, icon: AlertCircle, color: vacantHeadCount > 0 ? 'from-red-500 to-rose-600' : 'from-gray-400 to-gray-500', bg: vacantHeadCount > 0 ? 'bg-red-50' : 'bg-gray-50', text: vacantHeadCount > 0 ? 'text-red-700' : 'text-gray-500' },
    { label: 'งาน/คน (เฉลี่ย)', value: avgAssignments, unit: 'งาน', icon: BarChart3, color: 'from-violet-500 to-purple-600', bg: 'bg-violet-50', text: 'text-violet-700' },
    { label: 'ตำแหน่งทั้งหมด', value: totalAssignments, unit: 'ตำแหน่ง', icon: Briefcase, color: 'from-cyan-500 to-teal-600', bg: 'bg-cyan-50', text: 'text-cyan-700' },
  ];

  return (
    <div className="flex flex-col md:flex-row gap-3 mb-5 items-stretch">
      {/* Stats Cards Grid */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 flex-1">
        {stats.map((s, i) => (
          <div key={i} className={`${s.bg} rounded-2xl p-3.5 border border-gray-150 shadow-sm hover:shadow-md transition-all duration-300 group flex flex-col justify-between`}>
            <div className="flex items-center gap-2 mb-2">
              <div className={`bg-gradient-to-br ${s.color} p-1.5 rounded-lg shadow-sm group-hover:scale-105 transition-transform`}>
                <s.icon size={13} className="text-white" />
              </div>
              <span className="text-[11px] text-gray-500 font-bold leading-tight">{s.label}</span>
            </div>
            <div className={`text-2xl font-black ${s.text} leading-none mt-1`}>
              {s.value}<span className="text-xs font-semibold opacity-60 ml-0.5">{s.unit}</span>
            </div>
          </div>
        ))}
      </div>

      {/* Premium Analytics Button Card */}
      {onOpenAnalytics && (
        <button
          onClick={onOpenAnalytics}
          className="bg-gradient-to-br from-indigo-950 via-slate-900 to-indigo-900 text-white rounded-2xl p-3.5 border border-indigo-950/20 shadow-md hover:shadow-lg transition-all duration-300 group flex flex-col justify-between items-start shrink-0 w-full md:w-44 cursor-pointer relative overflow-hidden"
        >
          <div className="absolute top-0 right-0 w-16 h-16 bg-white/5 rounded-full blur-xl pointer-events-none transition-transform group-hover:scale-125" />
          
          <div className="flex items-center gap-2 mb-2 w-full">
            <div className="bg-indigo-500/20 p-1.5 rounded-lg border border-indigo-500/10">
              <BarChart3 size={13} className="text-indigo-300 group-hover:rotate-12 transition-transform" />
            </div>
            <span className="text-[9px] text-indigo-300 font-black tracking-widest uppercase">Analytics Suite</span>
          </div>

          <div className="text-left mt-2">
            <span className="text-sm font-black block group-hover:translate-x-1 transition-transform flex items-center gap-1">
              วิเคราะห์เชิงลึก 📊
            </span>
            <span className="text-[9px] text-indigo-200/80 font-bold block mt-0.5">
              ภาระงาน & อัตรากำลังพล
            </span>
          </div>
        </button>
      )}
    </div>
  );
};

export default DashboardStats;
