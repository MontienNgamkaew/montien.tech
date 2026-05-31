export const TITLE_PRIORITY = {
  'ผู้อำนวยการ': 1,
  'รองผู้อำนวยการ': 2,
  'ข้าราชการครู': 3,
  'ข้าราชการ': 3,
  'พนักงานราชการครู': 4,
  'พนักงานราชการ': 4,
  'ครูพิเศษสอน': 5,
  'เจ้าหน้าที่': 6
};

export const sortAssignments = (list, personnel) => {
  return [...list].sort((a, b) => {
    // 1. Sort by custom sort_order first
    const orderA = a.sort_order || 0;
    const orderB = b.sort_order || 0;

    // Treat 0 as "unset" (placed at the end of manually ordered ones)
    if (orderA === 0 && orderB !== 0) return 1;
    if (orderB === 0 && orderA !== 0) return -1;

    if (orderA !== orderB) {
      return orderA - orderB;
    }

    // 2. Fallback to main_title priority
    const pA = personnel.find(p => p.id === a.personnel_id);
    const pB = personnel.find(p => p.id === b.personnel_id);
    const titleA = pA ? (pA.main_title || '').trim() : '';
    const titleB = pB ? (pB.main_title || '').trim() : '';

    const prioA = TITLE_PRIORITY[titleA] || 99;
    const prioB = TITLE_PRIORITY[titleB] || 99;
    if (prioA !== prioB) {
      return prioA - prioB;
    }

    // 3. Fallback to personnel name
    return (pA?.name || '').localeCompare(pB?.name || '', 'th');
  });
};

