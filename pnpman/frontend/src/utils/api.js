// Determine the root URL of the application dynamically
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
