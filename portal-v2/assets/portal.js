'use strict';
(() => {
  const API = new URL('../api/v2/index.php', document.baseURI);
  const state = { data: null, users: [], departments: [], requests: [], audit: [], page: 'home', search: '', filter: 'all', selectedUser: null, selectedApp: 'pnp-academic' };
  const rights = { view: 'ดูข้อมูล', create: 'เพิ่มข้อมูล', edit: 'แก้ไขข้อมูล', approve: 'อนุมัติ', export: 'ส่งออก', manage_users: 'จัดการผู้ใช้ในระบบย่อย', manage_settings: 'ตั้งค่าระบบย่อย' };
  const roles = { none: 'ไม่มีสิทธิ์', user: 'สมาชิกทั่วไป', teacher: 'ครูผู้สอน', department_head: 'บทบาทหัวหน้าเดิม', driver: 'พนักงานขับรถ', supply_head: 'ผู้อนุมัติระดับ 1', deputy_director: 'ผู้อนุมัติระดับ 2', director: 'ผู้อนุมัติระดับ 3', admin: 'ผู้ดูแลระบบย่อย' };
  const appRoles = { 'pnp-academic': ['none', 'user', 'teacher', 'department_head', 'admin'], 'pnp-go': ['none', 'user', 'driver', 'supply_head', 'deputy_director', 'director', 'admin'], 'pnp-man': ['none', 'user', 'admin'], 'pnp-lesson-plan': ['none', 'user', 'admin'] };
  const statusText = { active: 'เปิดใช้งาน', disabled: 'ปิดปรับปรุง', coming_soon: 'ยังไม่เปิดบริการ', pending: 'รออนุมัติ', suspended: 'ระงับบัญชี', archived: 'เก็บเข้าคลัง' };
  const symbols = { 'pnp-academic': '▤', 'pnp-go': '⇄', 'pnp-man': '♧', 'pnp-lesson-plan': '▧' };
  const positions = ['ผู้ดูแลระบบ', 'ผู้อำนวยการ', 'รองผู้อำนวยการ', 'ข้าราชการครู', 'พนักงานราชการครู', 'ครูพิเศษสอน', 'เจ้าหน้าที่', 'แม่บ้าน', 'พนักงานขับรถ'];
  const $ = selector => document.querySelector(selector);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
  const name = user => `${user.title || ''}${user.first_name} ${user.last_name}`;
  const master = () => state.data?.user?.is_portal_admin === 1;
  const memberState = user => user.archived_at ? 'archived' : user.approval_status === 'pending' ? 'pending' : user.status;
  const badge = (value, text) => `<span class="badge ${esc(value)}">${esc(text || statusText[value] || value)}</span>`;
  const field = (label, name, value = '', type = 'text', extra = '') => `<label>${esc(label)}<input name="${name}" type="${type}" value="${esc(value)}" ${extra}></label>`;
  const options = (values, selected) => values.map(value => `<option value="${esc(value)}"${value === selected ? ' selected' : ''}>${esc(roles[value] || value)}</option>`).join('');
  const positionField = (value, signup = false) => `<label>ตำแหน่งหลัก<select name="primary_position">${(signup ? positions.filter(p => p !== 'ผู้ดูแลระบบ') : positions).map(p => `<option${p === value ? ' selected' : ''}>${esc(p)}</option>`).join('')}</select></label>`;
  function notify(message, error = false) { const box = $('#notification'); box.textContent = message; box.classList.toggle('error', error); box.hidden = false; clearTimeout(notify.timer); notify.timer = setTimeout(() => { box.hidden = true; }, 6500); }
  async function request(action, data) {
    const url = new URL(API); url.searchParams.set('action', action);
    const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...(data === undefined ? {} : { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': state.data?.csrf || '' }, body: JSON.stringify(data) }) });
    const result = await response.json().catch(() => ({ error: 'เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง' }));
    if (!response.ok || result.ok === false) { const error = new Error(result.error || 'ทำรายการไม่สำเร็จ'); error.status = response.status; error.kind = result.kind; throw error; }
    return result;
  }
  async function bootstrap() {
    state.data = await request('bootstrap');
    document.body.classList.toggle('preview-mode', !!state.data.preview);
    let preview = document.getElementById('preview-banner');
    if (state.data.preview && !preview) { preview = document.createElement('div'); preview.id = 'preview-banner'; preview.className = 'preview-banner'; preview.textContent = 'ระบบทดสอบ · ใช้ข้อมูลจำลอง · ยังไม่ใช่เว็บไซต์จริง'; document.body.prepend(preview); }
    $('#brand-name').textContent = state.data.settings.portal_name;
    $('#organization-name').textContent = state.data.settings.organization;
    document.title = `${state.data.settings.portal_name} | ${state.data.settings.organization}`;
  }
  async function refreshMembers() { const result = await request('members'); state.users = result.users; state.departments = result.departments; state.requests = result.reset_requests; state.total = result.total; }
  function heading(eyebrow, title, description, action = '') { return `<div class="page-heading"><div><div class="eyebrow">${esc(eyebrow)}</div><h1>${esc(title)}</h1><p>${esc(description)}</p></div>${action}</div>`; }
  function authView() {
    const page = location.hash.slice(1);
    $('#top-actions').innerHTML = '<span class="badge neutral">บัญชีสมาชิกกลาง</span>';
    $('#page-shell').className = '';
    const intro = `<section class="auth-intro"><img src="../logo.png" alt="ตราวิทยาลัย"><div class="eyebrow">Digital services</div><h1>ศูนย์บริการดิจิทัล<br>สำหรับบุคลากร</h1><p>บัญชีเดียวสำหรับทุกระบบงาน<br>เข้าถึงบริการตามสิทธิ์ที่ได้รับมอบหมาย</p><div class="auth-points"><span><b>01</b> เข้าสู่ระบบผ่าน Portal เพียงครั้งเดียว</span><span><b>02</b> ใช้ข้อมูลบัญชีและสิทธิ์จากส่วนกลาง</span><span><b>03</b> ดูแลข้อมูลและประวัติงานอย่างต่อเนื่อง</span></div></section>`;
    let content;
    if (page === 'register') content = `<section class="auth-form wide"><div class="eyebrow">Member registration</div><h2>สมัครสมาชิก</h2><p>บัญชีใหม่ต้องได้รับการตรวจสอบและอนุมัติจากผู้ดูแลก่อนใช้งาน</p><form data-form="register"><div data-error hidden></div><div class="form-grid">${field('ชื่อบัญชี', 'username', '', 'text', 'required pattern="[A-Za-z0-9._-]{3,50}" autocomplete="username" maxlength="50"')}${field('อีเมล', 'email', '', 'email', 'required autocomplete="email" maxlength="100"')}${field('ชื่อ', 'first_name', '', 'text', 'required autocomplete="given-name"')}${field('นามสกุล', 'last_name', '', 'text', 'required autocomplete="family-name"')}${positionField('เจ้าหน้าที่', true)}${field('แผนก / หน่วยงาน', 'department')}${field('รหัสผ่าน (อย่างน้อย 10 ตัว)', 'password', '', 'password', 'required minlength="10" maxlength="72" autocomplete="new-password"')}${field('ยืนยันรหัสผ่าน', 'password_confirm', '', 'password', 'required autocomplete="new-password"')}</div><button class="primary" type="submit">ส่งใบสมัครสมาชิก</button></form><div class="auth-links"><a href="#login">กลับไปเข้าสู่ระบบ</a></div></section>`;
    else if (page === 'forgot') content = `${intro}<section class="auth-form"><h2>ขอรีเซ็ตรหัสผ่าน</h2><p>ผู้ดูแลจะตรวจสอบคำขอและติดต่อกลับผ่านช่องทางที่ยืนยันแล้ว ระบบยังไม่ส่งอีเมลอัตโนมัติ</p><form data-form="forgot_password"><div data-error hidden></div>${field('ชื่อบัญชีหรืออีเมล', 'identifier', '', 'text', 'required autocomplete="username"')}<button class="primary" type="submit">ส่งคำขอให้ผู้ดูแล</button></form><div class="auth-links"><a href="#login">กลับไปเข้าสู่ระบบ</a></div></section>`;
    else content = `${intro}<section class="auth-form"><div class="eyebrow">Welcome back</div><h2>เข้าสู่ระบบ</h2><p>ใช้บัญชีสมาชิกกลางของหน่วยงาน</p><form data-form="login"><div data-error hidden></div>${field('ชื่อบัญชี', 'username', '', 'text', 'required autocomplete="username"')}${field('รหัสผ่าน', 'password', '', 'password', 'required autocomplete="current-password"')}<button class="primary" type="submit">เข้าสู่ระบบ Portal</button></form><div class="auth-links"><a href="#forgot">ลืมรหัสผ่าน?</a><a href="#register">สมัครสมาชิกใหม่</a></div><p class="readiness">${esc(state.data.settings.support_text)}</p></section>`;
    $('#page-shell').innerHTML = `<main id="main-content" tabindex="-1"><div class="auth-layout">${content}</div><footer class="portal-footer">${esc(state.data.settings.organization)} · ศูนย์บริการดิจิทัล</footer></main>`;
  }
  function shell() {
    const u = state.data.user;
    $('#top-actions').innerHTML = `<button class="mobile-menu quiet" data-action="menu" aria-label="เปิดเมนู" aria-expanded="false">☰</button><div class="user-chip"><span class="avatar">${esc(u.first_name.slice(0, 1))}</span><span>${esc(name(u))}<small>${master() ? 'ผู้ดูแลสูงสุด' : 'บัญชีสมาชิกกลาง'}</small></span></div><button class="quiet" data-action="logout">ออกจากระบบ</button>`;
    const nav = [['home', '▦', master() ? 'ภาพรวม' : 'ระบบของฉัน'], ...(master() ? [['members', '♧', 'บุคลากรและบัญชี'], ['systems', '▤', 'ระบบย่อย'], ['permissions', '◇', 'ผู้ดูแลและสิทธิ์'], ['audit', '≡', 'ประวัติการดำเนินการ'], ['settings', '⚙', 'ตั้งค่า Portal']] : []), ['account', '○', 'บัญชีของฉัน']];
    $('#page-shell').className = 'authenticated';
    $('#page-shell').innerHTML = `<aside class="sidebar" aria-label="เมนูหลัก"><div class="sidebar-caption">${master() ? 'ADMINISTRATION' : 'WORKSPACE'}</div>${nav.map(([id, symbol, label]) => `<a class="nav-link ${state.page === id ? 'active' : ''}" href="#${id}" ${state.page === id ? 'aria-current="page"' : ''}><span class="nav-symbol" aria-hidden="true">${symbol}</span>${label}</a>`).join('')}<div class="sidebar-foot">PNP e-Service<br>บัญชีเดียว · ทุกระบบงาน</div></aside><main id="main-content" tabindex="-1"></main>`;
  }
  function systemCards(admin = false) {
    return `<div class="systems-grid">${state.data.systems.map(system => {
      const allowed = !system.locked && system.access?.rights?.includes('view') && (system.status === 'active' || master()) && !state.data.user.must_change_password;
      return `<section class="panel system-card"><div class="system-card-top"><span class="system-mark" aria-hidden="true">${symbols[system.id]}</span>${badge(system.status)}</div><div><h3>${esc(system.name)}</h3><p>${esc(system.description)}</p></div><div class="system-card-bottom"><span class="small muted">${master() ? 'ผู้ดูแลสูงสุด' : esc(roles[system.access?.role] || 'ยังไม่ได้รับสิทธิ์')}</span>${admin ? `<button data-action="system" data-id="${system.id}" ${system.locked ? 'disabled title="พักบริการระหว่างปรับปรุงสิทธิ์"' : ''}>${system.locked ? 'พักบริการชั่วคราว' : 'ตั้งค่าบริการ'}</button>` : allowed ? `<a class="button primary" href="${esc(API.href)}?action=launch&amp;app=${system.id}">${system.status === 'active' ? 'เข้าสู่ระบบ' : 'เข้าเพื่อตรวจสอบ'} <span aria-hidden="true">&nbsp;→</span></a>` : '<button disabled>ยังไม่สามารถเข้าใช้ได้</button>'}</div></section>`;
    }).join('')}</div>`;
  }
  function homeView() {
    const u = state.data.user;
    const stats = master() ? `<div class="stats"><div class="stat"><div class="label">บัญชีสมาชิกทั้งหมด</div><div class="value">${state.total ?? state.users.length}</div><div class="detail">ทะเบียนสมาชิกกลาง</div></div><div class="stat"><div class="label">รออนุมัติสมาชิก</div><div class="value">${state.users.filter(u => memberState(u) === 'pending').length}</div><div class="detail">ตรวจสอบก่อนเปิดใช้งาน</div></div><div class="stat"><div class="label">ระบบที่เปิดบริการ</div><div class="value">${state.data.systems.filter(s => s.status === 'active').length}<span class="small muted"> / ${state.data.systems.length}</span></div><div class="detail">ควบคุมจากส่วนกลาง</div></div><div class="stat"><div class="label">คำขอรีเซ็ตรหัสผ่าน</div><div class="value">${state.requests.length}</div><div class="detail">รอผู้ดูแลตรวจสอบ</div></div></div>` : '';
    const reset = master() && state.requests.length ? `<section class="panel"><div class="panel-head"><h2>คำขอรีเซ็ตรหัสผ่าน</h2><span class="badge pending">${state.requests.length} รายการ</span></div><div class="panel-body">${state.requests.map(r => `<div class="request-row"><div><strong>${esc(r.first_name)} ${esc(r.last_name)}</strong><div class="small muted">${esc(r.username)} · ${esc(r.created_at)}</div></div><button data-action="reset" data-id="${Number(r.user_id)}">ตรวจสอบและตั้งรหัสผ่าน</button></div>`).join('')}</div></section>` : '';
    return `${heading('Workspace', master() ? 'ภาพรวมศูนย์บริหาร' : 'ระบบงานของฉัน', master() ? 'บริหารบัญชี สิทธิ์ และสถานะบริการจากส่วนกลาง' : 'เลือกใช้บริการตามสิทธิ์ที่ได้รับมอบหมาย')}<section class="welcome"><div><div class="eyebrow">PNP digital services</div><h2>สวัสดี ${esc(u.first_name)}</h2><p>${master() ? 'ตรวจสอบสมาชิกใหม่และสถานะระบบ ก่อนเริ่มงานประจำวัน' : 'เข้าสู่ระบบแล้ว สามารถเปิดระบบย่อยได้โดยไม่ต้องล็อกอินซ้ำ'}</p></div><span class="badge neutral">${master() ? 'ผู้ดูแลสูงสุด' : esc(u.department || 'สมาชิก')}</span></section>${state.data.settings.notice ? `<div class="notice">${esc(state.data.settings.notice)}</div>` : ''}${stats}<div class="section-title"><h2>ระบบบริการดิจิทัล</h2><small>บัญชีเดียวสำหรับทุกระบบ</small></div>${systemCards()}${reset}`;
  }
  function filteredUsers() { const q = state.search.toLowerCase(); return state.users.filter(u => (!q || [name(u), u.username, u.email, u.department].join(' ').toLowerCase().includes(q)) && (state.filter === 'all' || memberState(u) === state.filter)); }
  function memberRows() {
    const users = filteredUsers();
    return users.length ? users.map(u => `<tr><td><strong>${esc(name(u))}</strong><small>${esc(u.username)}${u.is_portal_admin ? ' · ผู้ดูแลสูงสุด' : ''}</small></td><td>${esc(u.department || '—')}<small>${esc(u.primary_position)}</small></td><td>${esc(u.email)}</td><td>${badge(memberState(u), memberState(u) === 'active' ? 'ใช้งานอยู่' : undefined)}${u.must_change_password ? '<small>ต้องเปลี่ยนรหัสผ่าน</small>' : ''}</td><td><div class="row-actions"><button data-action="member" data-id="${u.id}">แก้ไข</button><button data-action="permission" data-id="${u.id}">สิทธิ์</button><button data-action="reset" data-id="${u.id}" ${u.id === state.data.user.id ? 'disabled' : ''}>รหัสผ่าน</button>${memberState(u) === 'pending' ? `<button data-action="state" data-id="${u.id}" data-next="approve">อนุมัติ</button>` : ''}${u.archived_at ? `<button data-action="state" data-id="${u.id}" data-next="restore">กู้คืนบัญชี</button>` : `${u.approval_status === 'approved' ? `<button data-action="state" data-id="${u.id}" data-next="${u.status === 'active' ? 'suspended' : 'active'}" ${u.id === state.data.user.id ? 'disabled' : ''}>${u.status === 'active' ? 'ระงับ' : 'เปิดบัญชี'}</button>` : ''}<button class="danger" data-action="state" data-id="${u.id}" data-next="archive" ${u.id === state.data.user.id ? 'disabled' : ''}>เก็บเข้าคลัง</button>`}</div></td></tr>`).join('') : '<tr><td colspan="5" class="empty">ไม่พบบัญชีที่ตรงกับเงื่อนไข</td></tr>';
  }
  function membersView() { return `${heading('Member directory', 'บุคลากรและบัญชี', 'แยกข้อมูลบุคลากร สถานะบัญชี และสิทธิ์การใช้งาน', '<button class="primary" data-action="member">+ เพิ่มสมาชิก</button>')}<section class="panel"><div class="toolbar"><input id="member-search" type="search" aria-label="ค้นหาสมาชิก" placeholder="ค้นหาชื่อ บัญชี อีเมล หรือแผนก" value="${esc(state.search)}"><select id="member-filter" aria-label="กรองสถานะ">${[['all','ทุกสถานะ'],['active','ใช้งานอยู่'],['pending','รออนุมัติ'],['suspended','ระงับบัญชี'],['archived','เก็บเข้าคลัง']].map(([v,l]) => `<option value="${v}" ${state.filter === v ? 'selected' : ''}>${l}</option>`).join('')}</select><span class="total">แสดงสูงสุด 500 บัญชี · ทั้งหมด ${state.total ?? state.users.length}</span></div><div class="table-wrap"><table><thead><tr><th>สมาชิก</th><th>แผนก / ตำแหน่ง</th><th>อีเมล</th><th>สถานะ</th><th>จัดการบัญชี</th></tr></thead><tbody id="member-rows">${memberRows()}</tbody></table></div></section><p class="readiness">การเก็บเข้าคลังจะระงับบัญชี โดยรักษาประวัติงานและสามารถกู้คืนได้</p>`; }
  function permissionView() {
    const available = state.users.filter(u => !u.archived_at);
    const user = available.find(u => u.id === state.selectedUser) || available.find(u => !u.is_portal_admin) || available[0];
    if (!user) return heading('Access management', 'ผู้ดูแลและสิทธิ์', 'เพิ่มสมาชิกก่อนกำหนดสิทธิ์');
    state.selectedUser = user.id;
    const app = state.data.systems.find(s => s.id === state.selectedApp) || state.data.systems[0]; state.selectedApp = app.id;
    const grant = user.grants[app.id];
    return `${heading('Access management', 'ผู้ดูแลและสิทธิ์', 'กำหนดสิทธิ์ตามงาน โดยแยกจากตำแหน่งบุคลากร')}<div class="permission-layout"><section class="panel permission-person"><label>เลือกสมาชิก<select id="permission-user">${available.map(u => `<option value="${u.id}" ${u.id === user.id ? 'selected' : ''}>${esc(name(u))} (${esc(u.username)})</option>`).join('')}</select></label><h3>${esc(name(user))}</h3><p>${esc(user.department || 'ยังไม่ระบุแผนก')}</p><p>${esc(user.email)}</p><hr>${badge(memberState(user), memberState(user) === 'active' ? 'ใช้งานอยู่' : undefined)}<p class="readiness">ผู้ดูแลระบบย่อยไม่สามารถเพิ่มสิทธิ์ให้ตนเองหรือแต่งตั้งผู้ดูแลคนอื่นได้</p></section><section class="panel"><div class="panel-head"><h2>สิทธิ์ระบบย่อย</h2><span class="badge neutral">ผู้ดูแลสูงสุดกำหนด</span></div><div class="panel-body"><label>ระบบย่อย<select id="permission-app">${state.data.systems.map(s => `<option value="${s.id}" ${s.id === app.id ? 'selected' : ''}>${esc(s.name)}</option>`).join('')}</select></label>${user.is_portal_admin ? '<div class="notice" style="margin-top:20px">บัญชีนี้เป็นผู้ดูแลสูงสุด จึงมีสิทธิ์บริหารทุกระบบ หากต้องการจำกัดสิทธิ์ ให้ทบทวนระดับบัญชีก่อน</div>' : `<form data-form="save_grant" id="grant-form"><div data-error hidden></div><input type="hidden" name="user_id" value="${user.id}"><input type="hidden" name="app_id" value="${app.id}"><input type="hidden" name="revision" value="${grant.revision}"><label style="margin-top:18px">บทบาทในระบบนี้<select name="role" id="grant-role">${options(appRoles[app.id], grant.role)}</select></label><div class="section-title"><h3>งานที่อนุญาต</h3><button type="button" class="quiet" data-action="view-only">ตั้งเป็นดูอย่างเดียว</button></div><div class="rights-grid">${Object.entries(rights).map(([key,label]) => `<label class="right-option"><input type="checkbox" name="right" value="${key}" ${grant.rights.includes(key) ? 'checked' : ''}>${label}</label>`).join('')}</div><div class="scope-box"><label>ขอบเขตข้อมูล<select name="scope" id="grant-scope"><option value="all" ${grant.scope === 'all' ? 'selected' : ''}>ทั้งองค์กร</option><option value="department" ${grant.scope === 'department' ? 'selected' : ''}>เฉพาะแผนกในทะเบียนกลาง</option></select></label><label>แผนกที่รับผิดชอบ<select name="scope_value" id="grant-department"><option value="">เลือกแผนก</option>${state.departments.map(d => `<option ${d === grant.scope_value ? 'selected' : ''}>${esc(d)}</option>`).join('')}</select></label></div><div class="permission-review" id="grant-summary" aria-live="polite"></div><p class="readiness">การลบถาวรและการแต่งตั้งผู้ดูแลสูงสุดไม่รวมอยู่ในสิทธิ์เหล่านี้ · PNP Go ยังคงตรวจลำดับอนุมัติตามบทบาท</p><div class="form-actions"><button class="primary" type="submit">ตรวจและบันทึกสิทธิ์</button></div></form>`}</div></section></div>`;
  }
  function auditView() {
    const labels = { 'member.register': 'สมัครสมาชิก', 'member.create': 'สร้างสมาชิก', 'member.update': 'แก้ข้อมูลสมาชิก', 'member.approve': 'อนุมัติสมาชิก', 'member.active': 'เปิดบัญชี', 'member.suspended': 'ระงับบัญชี', 'member.archive': 'เก็บบัญชีเข้าคลัง', 'member.restore': 'กู้คืนบัญชี', 'password.reset': 'รีเซ็ตรหัสผ่าน', 'password.change': 'เปลี่ยนรหัสผ่าน', 'access.change': 'เปลี่ยนสิทธิ์', 'system.status': 'เปลี่ยนสถานะระบบ', 'portal.settings': 'ตั้งค่า Portal', 'session.login': 'เข้าสู่ระบบ', 'session.logout': 'ออกจากระบบ', 'profile.update': 'แก้ข้อมูลติดต่อ' };
    return `${heading('Activity log', 'ประวัติการดำเนินการ', 'ตรวจสอบผู้ดำเนินการ เป้าหมาย และข้อมูลก่อน–หลัง 200 รายการล่าสุด')}<section class="panel"><div class="table-wrap"><table><thead><tr><th>วันเวลา</th><th>การดำเนินการ</th><th>ผู้ดำเนินการ</th><th>บัญชีเป้าหมาย</th><th>รายละเอียด</th></tr></thead><tbody>${state.audit.map(event => `<tr><td class="small">${esc(event.created_at)} UTC</td><td>${esc(labels[event.action] || event.action)}</td><td>${esc(event.actor_name || 'ผู้สมัคร / ระบบ')}</td><td>${esc(event.target_name || '—')}</td><td><details><summary>ดูการเปลี่ยนแปลง</summary><pre>${esc(JSON.stringify({ ก่อน: JSON.parse(event.before_json), หลัง: JSON.parse(event.after_json) }, null, 2))}</pre></details></td></tr>`).join('') || '<tr><td colspan="5" class="empty">ยังไม่มีประวัติ</td></tr>'}</tbody></table></div></section>`;
  }
  function settingsView() { const s = state.data.settings; return `${heading('Portal settings', 'ตั้งค่า Portal', 'ข้อมูลหน่วยงานและประกาศบนหน้าส่วนกลาง')}<section class="panel"><div class="panel-body"><form data-form="save_settings"><div data-error hidden></div><div class="form-grid">${field('ชื่อ Portal', 'portal_name', s.portal_name, 'text', 'required')}${field('ชื่อหน่วยงาน', 'organization', s.organization, 'text', 'required')}<label class="full">ประกาศถึงสมาชิก<textarea name="notice" maxlength="2000">${esc(s.notice)}</textarea></label><label class="full">ข้อความติดต่อผู้ดูแล<textarea name="support_text" maxlength="2000">${esc(s.support_text)}</textarea></label></div><div class="form-actions"><button class="primary" type="submit">บันทึกข้อมูล Portal</button></div></form></div></section>`; }
  function passwordForm() { return `<form data-form="change_password"><div data-error hidden></div>${field('รหัสผ่านปัจจุบัน / ชั่วคราว', 'old_password', '', 'password', 'required autocomplete="current-password"')}<div style="height:14px"></div>${field('รหัสผ่านใหม่ (อย่างน้อย 10 ตัว)', 'password', '', 'password', 'required minlength="10" maxlength="72" autocomplete="new-password"')}<div style="height:14px"></div>${field('ยืนยันรหัสผ่านใหม่', 'password_confirm', '', 'password', 'required autocomplete="new-password"')}<div class="form-actions"><button class="primary" type="submit">เปลี่ยนรหัสผ่าน</button></div></form>`; }
  function accountView() { const u = state.data.user; return `${heading('My account', 'บัญชีของฉัน', 'จัดการข้อมูลติดต่อและรหัสผ่านจาก Portal เพียงแห่งเดียว')}${u.must_change_password ? '<div class="notice warning">คุณกำลังใช้รหัสผ่านชั่วคราว ต้องเปลี่ยนรหัสผ่านก่อนเข้าใช้งานระบบย่อย</div>' : ''}<div class="profile-layout"><section class="panel"><div class="panel-head"><h2>ข้อมูลสมาชิก</h2></div><div class="panel-body"><div class="label-value"><span>ชื่อ–นามสกุล</span><strong>${esc(name(u))}</strong></div><div class="label-value"><span>บัญชี / ตำแหน่ง</span><strong>${esc(u.username)} · ${esc(u.primary_position)}</strong></div><div class="label-value"><span>แผนก / หน่วยงาน</span><strong>${esc(u.department || '—')}</strong></div>${!u.must_change_password ? `<form data-form="update_profile" style="margin-top:20px"><div data-error hidden></div>${field('อีเมล', 'email', u.email, 'email', 'required')}<div style="height:14px"></div>${field('หมายเลขโทรศัพท์', 'phone', u.phone, 'tel', 'maxlength="20"')}<div class="form-actions"><button type="submit">บันทึกข้อมูลติดต่อ</button></div></form>` : ''}</div></section><section class="panel"><div class="panel-head"><h2>เปลี่ยนรหัสผ่าน</h2></div><div class="panel-body">${passwordForm()}<p class="readiness">เมื่อเปลี่ยนรหัสผ่าน เซสชันเดิมในอุปกรณ์อื่นจะสิ้นสุดลง</p></div></section></div>`; }
  function grantSummary() {
    const form = $('#grant-form'); if (!form) return;
    const selected = [...form.querySelectorAll('[name=right]:checked')].map(i => rights[i.value]);
    const scope = form.elements.namedItem('scope').value === 'department' ? `เฉพาะ ${form.elements.namedItem('scope_value').value || 'ยังไม่เลือกแผนก'}` : 'ทั้งองค์กร';
    $('#grant-summary').textContent = `บทบาท: ${roles[form.elements.namedItem('role').value]} · อนุญาต ${selected.length} งาน · ${scope}${selected.length ? '\n' + selected.join(' / ') : ''}`;
    form.elements.namedItem('scope_value').disabled = form.elements.namedItem('scope').value !== 'department';
  }
  async function render() {
    if (!state.data?.user) { authView(); return; }
    let page = location.hash.slice(1) || 'home'; if (state.data.user.must_change_password) page = 'account'; if (page === 'password') page = 'account';
    const allowed = master() ? ['home','members','systems','permissions','audit','settings','account'] : ['home','account'];state.page = allowed.includes(page) ? page : 'home';
    if (master() && !state.users.length) await refreshMembers();
    if (state.page === 'audit') state.audit = (await request('audit')).events;
    shell();
    const views = { home: homeView, members: membersView, systems: () => heading('System services', 'ระบบย่อย', 'เปิด–ปิดบริการโดยไม่ลบข้อมูลหรือประวัติเดิม') + systemCards(true), permissions: permissionView, audit: auditView, settings: settingsView, account: accountView };
    $('#main-content').innerHTML = views[state.page]() + `<footer class="portal-footer">${esc(state.data.settings.organization)} · PNP e-Service</footer>`;
    grantSummary();
    if (location.hash === '#logout' && !$('#editor').open) dialog('ออกจากระบบทุกระบบ', '<p>ต้องการสิ้นสุดเซสชัน Portal และระบบย่อยในเบราว์เซอร์นี้หรือไม่?</p><form data-form="logout"><div data-error hidden></div><div class="form-actions"><button type="button" data-action="close">ยกเลิก</button><button type="submit" class="primary">ออกจากระบบทั้งหมด</button></div></form>');
  }
  function dialog(title, content) { $('#editor-content').innerHTML = `<div class="dialog-head"><h2 id="editor-title">${esc(title)}</h2><button type="button" data-action="close" aria-label="ปิดหน้าต่าง">×</button></div><div class="dialog-body">${content}</div>`; $('#editor').showModal(); }
  function memberDialog(id) {
    const u = state.users.find(u => u.id === id) || {};
    dialog(id ? 'แก้ไขข้อมูลสมาชิก' : 'เพิ่มบัญชีสมาชิก', `<form data-form="save_member"><div data-error hidden></div><input type="hidden" name="id" value="${id || 0}"><div class="form-grid">${field('ชื่อบัญชี', 'username', u.username || '', 'text', id ? 'readonly' : 'required pattern="[A-Za-z0-9._-]{3,50}" maxlength="50"')}${field('อีเมล', 'email', u.email || '', 'email', 'required maxlength="100"')}${field('ชื่อ', 'first_name', u.first_name || '', 'text', 'required')}${field('นามสกุล', 'last_name', u.last_name || '', 'text', 'required')}${field('แผนก / หน่วยงาน', 'department', u.department || '')}${positionField(u.primary_position || 'เจ้าหน้าที่')}${field('หมายเลขโทรศัพท์', 'phone', u.phone || '', 'tel', 'maxlength="20"')}${!id ? field('รหัสผ่านชั่วคราว (อย่างน้อย 10 ตัว)', 'password', '', 'password', 'required minlength="10" maxlength="72" autocomplete="new-password"') : ''}<label class="check-label full"><input type="checkbox" name="is_portal_admin" ${u.is_portal_admin ? 'checked' : ''}>แต่งตั้งเป็นผู้ดูแลสูงสุดของ Portal</label></div><p class="readiness">${id ? 'การแก้ข้อมูลส่วนตัวไม่เปลี่ยนสิทธิ์ระบบย่อยที่ได้รับไว้' : 'บัญชีใหม่ต้องเปลี่ยนรหัสผ่านชั่วคราว และยังไม่มีสิทธิ์เข้าระบบย่อยจนกว่าจะกำหนดให้'}</p><div class="form-actions"><button type="button" data-action="close">ยกเลิก</button><button class="primary" type="submit">บันทึกสมาชิก</button></div></form>`);
  }
  function resetDialog(id) {
    const u = state.users.find(u => u.id === id);if (!u) return;
    dialog('ตั้งรหัสผ่านชั่วคราว', `<p class="small muted" style="margin-bottom:18px">${esc(name(u))} · ${esc(u.username)}</p><form data-form="reset_password"><div data-error hidden></div><input type="hidden" name="id" value="${id}">${field('รหัสผ่านชั่วคราว', 'password', '', 'password', 'required minlength="10" maxlength="72" autocomplete="new-password"')}<div style="height:14px"></div>${field('ยืนยันรหัสผ่านชั่วคราว', 'password_confirm', '', 'password', 'required autocomplete="new-password"')}<div class="notice warning" style="margin-top:18px">เซสชันเดิมจะถูกยกเลิก สมาชิกต้องเปลี่ยนรหัสผ่านก่อนเข้าระบบย่อย กรุณาส่งรหัสผ่านผ่านช่องทางที่ยืนยันเจ้าของบัญชีแล้ว</div><div class="form-actions"><button type="button" data-action="close">ยกเลิก</button><button class="primary" type="submit">ยืนยันรีเซ็ตรหัสผ่าน</button></div></form>`);
  }
  function systemDialog(id) {
    const system = state.data.systems.find(s => s.id === id);if (!system) return;
    dialog('สถานะบริการ · ' + system.name, `<form data-form="system_status"><div data-error hidden></div><input type="hidden" name="app_id" value="${id}"><input type="hidden" name="expected_status" value="${system.status}"><label>สถานะระบบ<select name="status">${['active','disabled','coming_soon'].map(s => `<option value="${s}" ${system.status === s ? 'selected' : ''}>${statusText[s]}</option>`).join('')}</select></label><div class="notice" style="margin-top:20px">การปิดบริการไม่ลบข้อมูล ผู้ใช้ทั่วไปและผู้ดูแลระบบย่อยจะเข้าไม่ได้ ผู้ดูแลสูงสุดยังเข้าตรวจสอบได้</div><div class="form-actions"><button type="button" data-action="close">ยกเลิก</button><button class="primary" type="submit">บันทึกสถานะบริการ</button></div></form>`);
  }
  document.addEventListener('click', async event => {
    const button = event.target.closest('button[data-action]'); if (!button || button.disabled) return;
    const action = button.dataset.action, id = Number(button.dataset.id);
    try {
      if (action === 'close') $('#editor').close();
      if (action === 'menu') { const sidebar = $('.sidebar'); sidebar.classList.toggle('open'); button.setAttribute('aria-expanded', String(sidebar.classList.contains('open'))); }
      if (action === 'member') memberDialog(id);
      if (action === 'reset') resetDialog(id);
      if (action === 'system') systemDialog(button.dataset.id);
      if (action === 'permission') { state.selectedUser = id; if (location.hash === '#permissions') await render(); else location.hash = 'permissions'; }
      if (action === 'state') {
        const u = state.users.find(u => u.id === id), next = button.dataset.next;
        const titles = { approve: 'อนุมัติสมาชิก', active: 'เปิดใช้งานบัญชี', suspended: 'ระงับบัญชี', archive: 'เก็บบัญชีเข้าคลัง', restore: 'กู้คืนบัญชีจากคลัง' };
        dialog(titles[next], `<p>${esc(name(u))} (${esc(u.username)})</p><p class="muted small" style="margin-top:12px">${next === 'archive' ? 'บัญชีจะถูกระงับและซ่อนจากการใช้งาน โดยเก็บประวัติและเอกสารไว้ครบ สามารถกู้คืนภายหลังได้' : next === 'restore' ? 'บัญชีจะกลับจากคลังในสถานะระงับ ต้องเปิดบัญชีอีกครั้งก่อนใช้งาน' : 'การเปลี่ยนสถานะมีผลกับการเข้าถึงระบบย่อย และมีบันทึกผู้ดำเนินการ'}</p><form data-form="member_state"><input type="hidden" name="id" value="${id}"><input type="hidden" name="state" value="${next}"><div data-error hidden></div><div class="form-actions"><button type="button" data-action="close">ยกเลิก</button><button type="submit" class="${next === 'archive' ? 'danger' : 'primary'}">ยืนยัน${titles[next]}</button></div></form>`);
      }
      if (action === 'logout') { await request('logout', {}); state.users = []; await bootstrap(); location.hash = 'login'; await render(); }
      if (action === 'view-only') { const form = $('#grant-form'); form.querySelectorAll('[name=right]').forEach(input => { input.checked = input.value === 'view'; }); if (form.elements.namedItem('role').value === 'none') form.elements.namedItem('role').value = 'user'; grantSummary(); }
    } catch (error) { notify(error.message, true); }
  });
  document.addEventListener('input', event => { if (event.target.id === 'member-search') { state.search = event.target.value; $('#member-rows').innerHTML = memberRows(); } });
  document.addEventListener('change', async event => {
    const target = event.target;
    if (target.id === 'member-filter') { state.filter = target.value; $('#member-rows').innerHTML = memberRows(); }
    if (target.id === 'permission-user') { state.selectedUser = Number(target.value); await render(); }
    if (target.id === 'permission-app') { state.selectedApp = target.value; await render(); }
    if (target.closest('#grant-form')) {
      const form = $('#grant-form');
      if (target.name === 'right' && target.checked && target.value !== 'view') form.querySelector('[name=right][value=view]').checked = true;
      if (target.name === 'right' && !target.checked && target.value === 'view') form.querySelectorAll('[name=right]').forEach(i => { i.checked = false; });
      if (target.name === 'role' && target.value === 'none') form.querySelectorAll('[name=right]').forEach(i => { i.checked = false; });
      grantSummary();
    }
  });
  document.addEventListener('submit', async event => {
    const form = event.target.closest('form[data-form]'); if (!form) return; event.preventDefault();
    if (form.dataset.busy === '1') return;
    const action = form.dataset.form, data = Object.fromEntries(new FormData(form));
    const errorBox = form.querySelector('[data-error]'); errorBox.hidden = true;
    const buttons = [...form.querySelectorAll('button[type=submit]')];
    try {
      if (data.password_confirm !== undefined && data.password !== data.password_confirm) throw new Error('รหัสผ่านยืนยันไม่ตรงกัน');
      delete data.password_confirm;
      if (action === 'save_member') data.is_portal_admin = !!form.querySelector('[name=is_portal_admin]:checked');
      if (action === 'save_grant') data.rights = [...form.querySelectorAll('[name=right]:checked')].map(i => i.value);
      if (action === 'login') data.return_to = new URLSearchParams(location.search).get('returnTo') || '';
      form.dataset.busy = '1'; buttons.forEach(b => { b.disabled = true; });
      const result = await request(action, data);
      if ($('#editor').open && form.closest('#editor')) $('#editor').close();
      if (action === 'register') { location.hash = 'login'; notify(result.message); await render(); return; }
      if (action === 'forgot_password') { form.reset(); notify(result.message); return; }
      await bootstrap();
      if (action === 'login' && result.return_to && !result.return_to.includes('/portal-v2/')) { location.assign(result.return_to); return; }
      if (master()) await refreshMembers();
      if (action === 'login') { location.hash = state.data.user.must_change_password ? 'password' : 'home'; }
      await render(); notify(result.message || 'ทำรายการสำเร็จ');
    } catch (error) { errorBox.textContent = error.message; errorBox.className = 'form-error'; errorBox.hidden = false; }
    finally { form.dataset.busy = '0'; buttons.forEach(b => { b.disabled = false; }); }
  });
  window.addEventListener('hashchange', () => render().catch(error => notify(error.message, true)));
  bootstrap().then(render).catch(error => { $('#main-content').innerHTML = `<section class="panel"><div class="panel-body"><h1>Portal ยังไม่พร้อมใช้งาน</h1><p class="muted" style="margin-top:12px">${esc(error.message)}</p><p class="readiness">หน้านี้ไม่เปลี่ยนข้อมูลของระบบเดิม กรุณาติดต่อผู้ดูแลเพื่อตรวจการตั้งค่าพื้นที่ทดสอบ</p></div></section>`; });
  setInterval(async () => {
    if (!state.data?.user || document.hidden || $('#editor').open) return;
    try { const previous = state.data.user.id; await bootstrap(); if (!state.data.user || state.data.user.id !== previous || state.data.user.must_change_password) { state.users = []; await render(); } }
    catch (_) { /* Keep the current view; APIs still enforce access on each request. */ }
  }, 30000);
})();
