// Determine the root URL of the application dynamically
import axios from 'axios';
const getBaseUrl = () => {
  // If in development mode (running via Vite dev server)
  if (import.meta.env.DEV) {
    return 'http://localhost/pnpman/';
  }

  // If in production, calculate base path based on location
  const pathname = window.location.pathname; // e.g., "/pnpman/frontend/dist/" or "/pnpman/" or "/"
  
  // Case A: served from the original subfolders (e.g. /pnpman/frontend/dist/)
  const distIndex = pathname.indexOf('/frontend/dist');
  if (distIndex !== -1) {
    const rootPath = pathname.substring(0, distIndex + 1); // returns "/pnpman/"
    return window.location.origin + rootPath;
  }

  // Case B: flattened to root or subfolder directly (e.g. /pnpman/index.html or /index.html)
  const lastSlash = pathname.lastIndexOf('/');
  const rootPath = pathname.substring(0, lastSlash + 1); // returns "/pnpman/" or "/"
  return window.location.origin + rootPath;
};

export const BASE_URL = getBaseUrl();
export const API_URL = `${BASE_URL}api/`;

const portalBase = new URL('../', BASE_URL);
const portalV2 = new URL('portal-v2/', portalBase);
const bootstrapUrl = new URL('api/v2/index.php?action=bootstrap', portalBase);
axios.interceptors.request.use(async config => {
  if (!['post','put','patch','delete'].includes((config.method || 'get').toLowerCase()) || !String(config.url || '').startsWith(API_URL)) return config;
  const response = await fetch(bootstrapUrl, { credentials: 'same-origin', cache: 'no-store' });
  if (response.status === 404) return config;
  if (response.status === 503 && (await response.clone().json().catch(() => ({}))).kind === 'v2_disabled') return config;
  if (!response.ok) throw new Error('ไม่สามารถตรวจบัญชีส่วนกลางได้');
  const data = await response.json();
  if (!data.user) { window.location.assign(portalV2 + '?returnTo=' + encodeURIComponent(window.location.pathname)); throw new Error('กรุณาเข้าสู่ระบบผ่าน Portal'); }
  config.headers = config.headers || {}; config.headers['X-CSRF-Token'] = data.csrf;
  return config;
});
axios.interceptors.response.use(response => response, error => {
  if (error.response?.data?.portal_url && (error.response.status === 401 || error.response.data.kind === 'app_disabled')) {
    window.location.assign(error.response.data.portal_url + '?returnTo=' + encodeURIComponent(window.location.pathname));
  }
  return Promise.reject(error);
});
