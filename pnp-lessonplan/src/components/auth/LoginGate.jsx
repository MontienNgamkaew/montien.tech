import React, { useEffect, useMemo, useState } from 'react';
import { BookOpenCheck, ExternalLink, Loader2, Lock, ShieldAlert } from 'lucide-react';

const APP_ID = 'pnp-lesson-plan';
const TOKEN_KEY = 'pnp_lesson_plan_token';
const USER_KEY = 'pnp_lesson_plan_user';
const VERIFY_URL = import.meta.env?.VITE_PORTAL_VERIFY_URL || '../../api/verify.php';
const PORTAL_URL = import.meta.env?.VITE_PORTAL_URL || '../../';

function readStoredUser() {
  try {
    return JSON.parse(localStorage.getItem(USER_KEY) || 'null');
  } catch {
    return null;
  }
}

function roleCanAccess(user) {
  if (!user) return false;
  if (Number(user.is_portal_admin) === 1) return true;
  const role = user.roles?.[APP_ID] || 'none';
  return role !== 'none';
}

function consumeTokenFromUrl() {
  const url = new URL(window.location.href);
  const token = url.searchParams.get('token');
  if (!token) return localStorage.getItem(TOKEN_KEY) || '';

  localStorage.setItem(TOKEN_KEY, token);
  url.searchParams.delete('token');
  window.history.replaceState({}, document.title, `${url.pathname}${url.search}${url.hash}`);
  return token;
}

export function clearLoginSession() {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
  window.location.href = PORTAL_URL;
}

export default function LoginGate({ children }) {
  const [status, setStatus] = useState('checking');
  const [user, setUser] = useState(() => readStoredUser());
  const [message, setMessage] = useState('');

  useEffect(() => {
    let isMounted = true;

    async function verifySession() {
      const token = consumeTokenFromUrl();
      if (!token) {
        setStatus('missing');
        setMessage('กรุณาเข้าสู่ระบบผ่าน PNP Portal ก่อนเข้าใช้งานระบบแผนการสอน');
        return;
      }

      try {
        const response = await fetch(VERIFY_URL, {
          method: 'GET',
          headers: { Authorization: `Bearer ${token}` },
        });
        const data = await response.json().catch(() => null);

        if (!isMounted) return;

        if (!response.ok || !data?.valid || !data?.user) {
          localStorage.removeItem(TOKEN_KEY);
          localStorage.removeItem(USER_KEY);
          setStatus('invalid');
          setMessage(data?.error || 'เซสชันหมดอายุหรือ token ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่ผ่าน Portal');
          return;
        }

        if (!roleCanAccess(data.user)) {
          localStorage.removeItem(USER_KEY);
          setStatus('forbidden');
          setMessage('บัญชีนี้ยังไม่มีสิทธิ์เข้าใช้งาน PNP Lesson Plan');
          return;
        }

        localStorage.setItem(USER_KEY, JSON.stringify(data.user));
        setUser(data.user);
        setStatus('authenticated');
      } catch {
        if (!isMounted) return;
        setStatus('error');
        setMessage('ไม่สามารถติดต่อ Portal เพื่อตรวจสอบสิทธิ์ได้');
      }
    }

    verifySession();
    return () => {
      isMounted = false;
    };
  }, []);

  const displayName = useMemo(() => {
    if (!user) return '';
    return `${user.title || ''}${user.first_name || ''} ${user.last_name || ''}`.trim() || user.username || '';
  }, [user]);

  if (status === 'authenticated') return children;

  const isChecking = status === 'checking';

  return (
    <div className="min-h-screen flex items-center justify-center px-4 py-8 relative overflow-hidden">
      <div className="absolute inset-0 bg-[linear-gradient(135deg,rgba(15,42,74,0.96),rgba(29,78,216,0.88)_48%,rgba(14,165,233,0.78))]" />
      <div className="absolute inset-x-0 top-0 h-40 bg-white/10 blur-3xl" />
      <div className="relative w-full max-w-4xl grid lg:grid-cols-[1.05fr_0.95fr] gap-6 items-stretch">
        <div className="hidden lg:flex rounded-xl border border-white/15 bg-white/10 backdrop-blur-xl p-8 text-white flex-col justify-between shadow-2xl">
          <div>
            <div className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-sky-100">
              <BookOpenCheck size={14} /> PNP Single Sign-On
            </div>
            <h1 className="mt-8 text-4xl font-extrabold leading-tight">
              PNP<br />Lesson Plan
            </h1>
            <p className="mt-4 max-w-md text-sm leading-7 text-blue-50">
              ระบบสร้างแผนการจัดการเรียนรู้ใช้บัญชีเดียวกับ PNP Portal เพื่อจัดการสิทธิ์และข้อมูลผู้ใช้งานจากศูนย์กลาง
            </p>
          </div>
          <div className="grid grid-cols-3 gap-3 text-xs">
            {['Portal Login', 'JWT Verify', 'Role Access'].map((item) => (
              <div key={item} className="rounded-lg border border-white/15 bg-white/10 p-3 text-blue-50">
                {item}
              </div>
            ))}
          </div>
        </div>

        <div className="pnp-shell-card rounded-xl p-6 sm:p-7 self-center">
          <div className="flex items-center gap-3 mb-6">
            <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-700 to-sky-500 text-white flex items-center justify-center shadow-lg shadow-blue-700/20">
              {isChecking ? <Loader2 className="animate-spin" size={25} /> : <Lock size={25} />}
            </div>
            <div className="min-w-0">
              <h1 className="text-xl font-bold text-slate-950 leading-tight">PNP Lesson Plan</h1>
              <p className="text-xs text-slate-500 leading-snug">
                {isChecking ? 'กำลังตรวจสอบสิทธิ์จาก Portal' : 'เข้าใช้งานผ่าน PNP Portal เท่านั้น'}
              </p>
            </div>
          </div>

          {displayName && (
            <div className="mb-4 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-sm text-blue-800">
              ผู้ใช้: <span className="font-semibold">{displayName}</span>
            </div>
          )}

          {!isChecking && (
            <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-800 flex items-start gap-2">
              <ShieldAlert size={18} className="shrink-0 mt-0.5" />
              <span>{message}</span>
            </div>
          )}

          <div className="mt-5 flex flex-col sm:flex-row gap-3">
            <a
              href={PORTAL_URL}
              className="inline-flex items-center justify-center gap-2 h-11 rounded-lg pnp-btn-primary font-semibold px-4"
            >
              กลับไป PNP Portal <ExternalLink size={16} />
            </a>
            {!isChecking && (
              <button
                type="button"
                onClick={() => window.location.reload()}
                className="inline-flex items-center justify-center h-11 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
              >
                ตรวจสอบอีกครั้ง
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
