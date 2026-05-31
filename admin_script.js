/* -------------------------------------------------------------
 * JAVASCRIPT LOGIC: PNP ADMIN DASHBOARD
 * Features: Auth Guard, CRUD User Management, Theme Toggle,
 *           Particle System, Live Clock, Toast Notifications
 * ------------------------------------------------------------- */

document.addEventListener('DOMContentLoaded', () => {

    // ==========================================
    // GLOBAL STATE
    // ==========================================
    let currentAdminProfile = null;
    let allUsersData = [];
    let pendingConfirmAction = null;

    const API_BASE = 'api/admin_users.php';
    const VERIFY_URL = 'api/verify.php';

    // ==========================================
    // DOM REFERENCES
    // ==========================================
    const body = document.body;

    // Header
    const timeElement = document.getElementById('live-time');
    const themeToggle = document.getElementById('theme-toggle');
    const profileBadgeBtn = document.getElementById('profile-badge-btn');
    const profileDropdown = document.getElementById('profile-dropdown');
    const usernameDisplay = document.getElementById('username-display');
    const dropdownFullname = document.getElementById('dropdown-fullname');
    const btnLogout = document.getElementById('btn-logout');

    // Stats
    const statTotal = document.getElementById('stat-total');
    const statActive = document.getElementById('stat-active');
    const statSuspended = document.getElementById('stat-suspended');
    const statDepartments = document.getElementById('stat-departments');

    // Toolbar
    const searchInput = document.getElementById('search-input');
    const filterDepartment = document.getElementById('filter-department');
    const filterStatus = document.getElementById('filter-status');
    const btnAddUser = document.getElementById('btn-add-user');

    // Table
    const tableBody = document.getElementById('admin-users-table-body');

    // User Modal (Basic Account Info)
    const userModalOverlay = document.getElementById('user-modal-overlay');
    const btnCloseModal = document.getElementById('btn-close-modal');
    const modalTitle = document.getElementById('modal-title');
    const userForm = document.getElementById('user-form');
    const formUserId = document.getElementById('form-user-id');
    const formUsername = document.getElementById('form-username');
    const formTitle = document.getElementById('form-title');
    const formFirstname = document.getElementById('form-firstname');
    const formLastname = document.getElementById('form-lastname');
    const formEmail = document.getElementById('form-email');
    const formPhone = document.getElementById('form-phone');
    const formAvatar = document.getElementById('form-avatar');
    const formPortalAdmin = document.getElementById('form-portal-admin');
    const formPrimaryPos = document.getElementById('form-primary-position');
    const formPrimaryPosGroup = document.getElementById('form-primary-position-group');
    const btnSubmitForm = document.getElementById('btn-submit-form');
    const formErrorMsg = document.getElementById('form-error-msg');
    const formErrorText = document.getElementById('form-error-text');
    const avatarPreview = document.getElementById('avatar-preview');

    // Position & Responsibility Modal
    const positionModalOverlay = document.getElementById('position-modal-overlay');
    const btnClosePositionModal = document.getElementById('btn-close-position-modal');
    const positionForm = document.getElementById('position-form');
    const positionUserId = document.getElementById('position-user-id');
    const positionUserDisplay = document.getElementById('position-user-display');
    const positionPrimaryPos = document.getElementById('position-primary-position');
    const positionOrgPos = document.getElementById('position-org-position');
    const positionDepartment = document.getElementById('position-department');
    const positionJob = document.getElementById('position-job');
    const positionRoleGo = document.getElementById('position-role-go');
    const positionRoleAcademic = document.getElementById('position-role-academic');
    const positionRoleMan = document.getElementById('position-role-man');
    const btnSubmitPositionForm = document.getElementById('btn-submit-position-form');
    const positionErrorMsg = document.getElementById('position-error-msg');
    const positionErrorText = document.getElementById('position-error-text');

    // Confirm Modal
    const confirmModalOverlay = document.getElementById('confirm-modal-overlay');
    const confirmMessage = document.getElementById('confirm-message');
    const confirmIcon = document.getElementById('confirm-icon');
    const btnConfirmAction = document.getElementById('btn-confirm-action');
    const btnCancelAction = document.getElementById('btn-cancel-action');

    // Toast
    const toastNotification = document.getElementById('toast-notification');
    const toastIcon = document.getElementById('toast-icon');
    const toastMessage = document.getElementById('toast-message');

    // ==========================================
    // 1. AUTH GUARD — Verify admin on page load
    // ==========================================
    async function authGuard() {
        const token = localStorage.getItem('pnp-token');
        if (!token) {
            window.location.href = 'index.html';
            return;
        }

        try {
            const response = await fetch(VERIFY_URL, {
                method: 'GET',
                headers: { 'Authorization': `Bearer ${token}` }
            });

            const data = await response.json();

            if (!response.ok || !data.valid) {
                localStorage.removeItem('pnp-token');
                window.location.href = 'index.html';
                return;
            }

            // Check if user is portal admin
            if (parseInt(data.user.is_portal_admin) !== 1) {
                window.location.href = 'index.html';
                return;
            }

            currentAdminProfile = data.user;
            applyAdminProfile();
            loadUsersTable();

        } catch (err) {
            console.error('Auth verification failed:', err);
            window.location.href = 'index.html';
        }
    }

    function applyAdminProfile() {
        if (!currentAdminProfile) return;

        if (usernameDisplay) {
            usernameDisplay.textContent = `${currentAdminProfile.first_name} ${currentAdminProfile.last_name[0]}.`;
        }
        if (dropdownFullname) {
            dropdownFullname.textContent = `${currentAdminProfile.first_name} ${currentAdminProfile.last_name}`;
        }
    }

    // ==========================================
    // 2. THEME TOGGLE & PERSISTENCE
    // ==========================================
    const savedTheme = localStorage.getItem('pnp-portal-theme') || 'dark-theme';
    body.className = savedTheme;

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            if (body.classList.contains('dark-theme')) {
                body.classList.remove('dark-theme');
                body.classList.add('light-theme');
                localStorage.setItem('pnp-portal-theme', 'light-theme');
            } else {
                body.classList.remove('light-theme');
                body.classList.add('dark-theme');
                localStorage.setItem('pnp-portal-theme', 'dark-theme');
            }
        });
    }

    // ==========================================
    // 3. LIVE DIGITAL CLOCK
    // ==========================================
    function updateClock() {
        if (!timeElement) return;
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        timeElement.textContent = `${hours}:${minutes}:${seconds}`;
    }

    setInterval(updateClock, 1000);
    updateClock();

    // ==========================================
    // 4. CANVAS PARTICLE SYSTEM
    // ==========================================
    const canvas = document.getElementById('particle-canvas');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        let particlesArray = [];
        const numberOfParticles = 35;

        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }

        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        class Particle {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.size = Math.random() * 3 + 1;
                this.speedX = Math.random() * 0.4 - 0.2;
                this.speedY = Math.random() * 0.4 - 0.2;
                this.alpha = Math.random() * 0.5 + 0.1;
                this.alphaChange = Math.random() * 0.005 + 0.002;
            }

            update() {
                this.x += this.speedX;
                this.y += this.speedY;

                if (this.x < 0) this.x = canvas.width;
                if (this.x > canvas.width) this.x = 0;
                if (this.y < 0) this.y = canvas.height;
                if (this.y > canvas.height) this.y = 0;

                this.alpha += this.alphaChange;
                if (this.alpha > 0.7 || this.alpha < 0.1) {
                    this.alphaChange = -this.alphaChange;
                }
            }

            draw() {
                const isDark = body.classList.contains('dark-theme');
                const hue = isDark ? 200 : 260;
                ctx.save();
                ctx.globalAlpha = this.alpha;
                ctx.fillStyle = `hsl(${hue}, 100%, 75%)`;
                ctx.shadowBlur = isDark ? 10 : 4;
                ctx.shadowColor = `hsl(${hue}, 100%, 75%)`;
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
                ctx.restore();
            }
        }

        function initParticles() {
            particlesArray = [];
            for (let i = 0; i < numberOfParticles; i++) {
                particlesArray.push(new Particle());
            }
        }

        function animateParticles() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            for (let i = 0; i < particlesArray.length; i++) {
                particlesArray[i].update();
                particlesArray[i].draw();
            }
            requestAnimationFrame(animateParticles);
        }

        initParticles();
        animateParticles();
    }

    // ==========================================
    // 5. PROFILE DROPDOWN
    // ==========================================
    if (profileBadgeBtn) {
        profileBadgeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
        });
    }

    document.addEventListener('click', () => {
        if (profileDropdown) profileDropdown.classList.add('hidden');
    });

    // Logout
    if (btnLogout) {
        btnLogout.addEventListener('click', () => {
            localStorage.removeItem('pnp-token');
            currentAdminProfile = null;
            window.location.href = 'index.html';
        });
    }

    // ==========================================
    // 6. LOAD USERS TABLE
    // ==========================================
    function getAuthHeaders() {
        const token = localStorage.getItem('pnp-token');
        return {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        };
    }

    async function loadUsersTable() {
        const token = localStorage.getItem('pnp-token');
        if (!token) return;

        tableBody.innerHTML = `
            <tr>
                <td colspan="9" class="table-loading">⚡ กำลังค้นหาบัญชีจากฐานข้อมูล...</td>
            </tr>
        `;

        try {
            const response = await fetch(API_BASE, {
                method: 'GET',
                headers: { 'Authorization': `Bearer ${token}` }
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'เกิดข้อผิดพลาดในการโหลดข้อมูล');
            }

            allUsersData = data.users || [];
            updateStats();
            renderTable(allUsersData);

        } catch (err) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="13" style="text-align: center; color: #ff4d4d; padding: 30px;">
                        ❌ ${err.message}
                    </td>
                </tr>
            `;
        }
    }

    // ==========================================
    // 7. UPDATE STATS CARDS
    // ==========================================
    function updateStats() {
        const nonAdminUsers = allUsersData.filter(u => u.username !== 'admin' && u.primary_position !== 'ผู้ดูแลระบบ');
        const total = nonAdminUsers.length;
        const active = nonAdminUsers.filter(u => u.status === 'active').length;
        const suspended = nonAdminUsers.filter(u => u.status === 'suspended').length;
        const departments = new Set(nonAdminUsers.map(u => u.department).filter(Boolean));

        animateCounter(statTotal, total);
        animateCounter(statActive, active);
        animateCounter(statSuspended, suspended);
        animateCounter(statDepartments, departments.size);
    }

    function animateCounter(element, target) {
        if (!element) return;
        const duration = 600;
        const start = parseInt(element.textContent) || 0;
        const diff = target - start;
        if (diff === 0) {
            element.textContent = target;
            return;
        }
        const startTime = performance.now();

        function step(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3); // easeOutCubic
            element.textContent = Math.round(start + diff * eased);
            if (progress < 1) {
                requestAnimationFrame(step);
            }
        }

        requestAnimationFrame(step);
    }

    // ==========================================
    // 8. RENDER TABLE ROWS
    // ==========================================
    const avatarColors = [
        '#00f2fe', '#8a4fff', '#ffaa00', '#2ecc71', '#e74c3c',
        '#3498db', '#e67e22', '#1abc9c', '#9b59b6', '#f39c12'
    ];

    function getAvatarColor(name) {
        let hash = 0;
        for (let i = 0; i < name.length; i++) {
            hash = name.charCodeAt(i) + ((hash << 5) - hash);
        }
        return avatarColors[Math.abs(hash) % avatarColors.length];
    }

    function renderTable(users) {
        // กรองบัญชีผู้ดูแลระบบ (admin) และตำแหน่ง 'ผู้ดูแลระบบ' ออกจากการแสดงผลทั้งหมด
        const displayUsers = users.filter(u => u.username !== 'admin' && u.primary_position !== 'ผู้ดูแลระบบ');

        if (!displayUsers || displayUsers.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="10" class="table-loading">ไม่พบข้อมูลสมาชิกตามเงื่อนไข</td></tr>';
            return;
        }

        tableBody.innerHTML = '';

        displayUsers.forEach(user => {
            const tr = document.createElement('tr');
            const isActive = user.status === 'active';
            const fullName = `${user.title || ''}${user.first_name || ''} ${user.last_name || ''}`.trim();
            const isSelf = currentAdminProfile && (currentAdminProfile.user_id === user.id || currentAdminProfile.id === user.id);

            // Store user data on the row for easy access
            tr.dataset.userId = user.id;
            tr.dataset.userData = JSON.stringify(user);

            // 1. Avatar
            const tdAvatar = document.createElement('td');
            if (user.avatar) {
                tdAvatar.innerHTML = `<img src="${user.avatar}" class="table-avatar" alt="${fullName}">`;
            } else {
                const letter = (user.first_name || user.username || '?')[0].toUpperCase();
                const color = getAvatarColor(user.username || 'default');
                tdAvatar.innerHTML = `<div class="table-avatar-letter" style="background: ${color}">${letter}</div>`;
            }
            tr.appendChild(tdAvatar);

            // 2. Username
            const tdUsername = document.createElement('td');
            tdUsername.innerHTML = `<code>${user.username}</code>`;
            tr.appendChild(tdUsername);

            // 3. Full Name
            const tdName = document.createElement('td');
            tdName.textContent = fullName || '-';
            tr.appendChild(tdName);

            // 4. Position (ตำแหน่งหลัก)
            const tdPosition = document.createElement('td');
            tdPosition.innerHTML = `
                <div class="user-pos-cell">
                    <span class="user-pos-primary" style="font-weight: 500; display: block;">📌 ${user.primary_position || 'ไม่ระบุ'}</span>
                </div>
            `;
            tr.appendChild(tdPosition);

            // 6. Status Badge
            const tdStatus = document.createElement('td');
            if (isActive) {
                tdStatus.innerHTML = `<span class="status-badge status-badge-active">เปิดใช้งาน</span>`;
            } else {
                tdStatus.innerHTML = `<span class="status-badge status-badge-suspended">ถูกระงับ</span>`;
            }
            tr.appendChild(tdStatus);

            // 7. PNP Go Role
            const tdGo = document.createElement('td');
            const roles = user.roles || {};
            tdGo.appendChild(createInlineRoleSelector(user.id, 'pnp-go', roles['pnp-go'] || 'none'));
            tr.appendChild(tdGo);

            // 8. PNP EDU Smart Role
            const tdAcademic = document.createElement('td');
            tdAcademic.appendChild(createInlineRoleSelector(user.id, 'pnp-academic', roles['pnp-academic'] || 'none'));
            tr.appendChild(tdAcademic);

            // 9. PNP Man Role
            const tdMan = document.createElement('td');
            tdMan.appendChild(createInlineRoleSelector(user.id, 'pnp-man', roles['pnp-man'] || 'none'));
            tr.appendChild(tdMan);

            // 10. Actions
            const tdActions = document.createElement('td');
            tdActions.innerHTML = `
                <div class="action-btns">
                    <button class="action-btn action-btn-edit" data-userid="${user.id}" title="แก้ไขข้อมูลส่วนบุคคล">✏️</button>
                    <button class="action-btn action-btn-position" data-userid="${user.id}" title="จัดการตำแหน่งและหน้าที่รับผิดชอบ">💼</button>
                    <button class="action-btn action-btn-suspend" data-userid="${user.id}" data-active="${isActive ? 1 : 0}" title="${isActive ? 'ระงับ' : 'เปิดใช้งาน'}">
                        ${isActive ? '⏸️' : '▶️'}
                    </button>
                    ${!isSelf ? `<button class="action-btn action-btn-delete" data-userid="${user.id}" title="ลบ">🗑️</button>` : ''}
                </div>
            `;
            tr.appendChild(tdActions);

            tableBody.appendChild(tr);
        });

        // Attach event listeners for action buttons
        attachTableEventListeners();
    }

    // ==========================================
    // 9. INLINE ROLE SELECTOR (IN TABLE)
    // ==========================================
    function createInlineRoleSelector(userId, appId, currentRole) {
        const select = document.createElement('select');
        select.className = 'admin-role-selector';
        select.dataset.userid = userId;
        select.dataset.appid = appId;

        const rolesConfig = [
            { val: 'none', label: '❌ ไม่มีสิทธิ์' },
            { val: 'user', label: '👤 สมาชิกทั่วไป' }
        ];

        if (appId === 'pnp-go') {
            rolesConfig.push({ val: 'driver', label: '🚗 คนขับรถ' });
            rolesConfig.push({ val: 'admin', label: '👑 ผู้ควบคุมรถ' });
        } else if (appId === 'pnp-academic') {
            rolesConfig.push({ val: 'teacher', label: '📘 ครูผู้สอน' });
            rolesConfig.push({ val: 'admin', label: '👑 หัวหน้าแผนก' });
        } else if (appId === 'pnp-man') {
            rolesConfig.push({ val: 'admin', label: '👑 แอดมินบุคลากร' });
        }

        rolesConfig.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.val;
            opt.textContent = r.label;
            if (r.val === currentRole) opt.selected = true;
            select.appendChild(opt);
        });

        select.addEventListener('change', () => {
            updateUserAppRole(userId, appId, select.value);
        });

        return select;
    }

    // ==========================================
    // 10. TABLE EVENT LISTENERS
    // ==========================================
    function attachTableEventListeners() {
        // Portal Admin toggle
        tableBody.querySelectorAll('input[data-action="toggle-admin"]').forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                const userId = parseInt(checkbox.dataset.userid);
                togglePortalAdmin(userId);
            });
        });

        // Edit buttons
        tableBody.querySelectorAll('.action-btn-edit').forEach(btn => {
            btn.addEventListener('click', () => {
                const userId = parseInt(btn.dataset.userid);
                openEditModal(userId);
            });
        });

        // Position & Roles buttons
        tableBody.querySelectorAll('.action-btn-position').forEach(btn => {
            btn.addEventListener('click', () => {
                const userId = parseInt(btn.dataset.userid);
                const user = allUsersData.find(u => u.id === userId || parseInt(u.id) === userId);
                if (user) {
                    openPositionModal(user);
                }
            });
        });

        // Suspend/Activate buttons
        tableBody.querySelectorAll('.action-btn-suspend').forEach(btn => {
            btn.addEventListener('click', () => {
                const userId = parseInt(btn.dataset.userid);
                const isActive = parseInt(btn.dataset.active) === 1;
                openSuspendConfirm(userId, isActive);
            });
        });

        // Delete buttons
        tableBody.querySelectorAll('.action-btn-delete').forEach(btn => {
            btn.addEventListener('click', () => {
                const userId = parseInt(btn.dataset.userid);
                openDeleteConfirm(userId);
            });
        });
    }

    // ==========================================
    // 11. SEARCH & FILTER
    // ==========================================
    function applyFilters() {
        const searchText = searchInput.value.trim().toLowerCase();
        const deptFilter = filterDepartment.value;
        const statusFilter = filterStatus.value;

        // กรองบัญชีผู้ดูแลระบบ (admin) และตำแหน่ง 'ผู้ดูแลระบบ' ออกก่อนทำตัวกรองอื่นๆ
        const nonAdminUsers = allUsersData.filter(u => u.username !== 'admin' && u.primary_position !== 'ผู้ดูแลระบบ');

        const filtered = nonAdminUsers.filter(user => {
            // Text search
            const matchText = !searchText ||
                (user.username && user.username.toLowerCase().includes(searchText)) ||
                (user.first_name && user.first_name.toLowerCase().includes(searchText)) ||
                (user.last_name && user.last_name.toLowerCase().includes(searchText)) ||
                (user.email && user.email.toLowerCase().includes(searchText)) ||
                (`${user.first_name} ${user.last_name}`.toLowerCase().includes(searchText));

            // Department filter
            const matchDept = !deptFilter || user.department === deptFilter;

            // Status filter
            let matchStatus = true;
            if (statusFilter === 'active') matchStatus = user.status === 'active';
            if (statusFilter === 'suspended') matchStatus = user.status === 'suspended';

            return matchText && matchDept && matchStatus;
        });

        renderTable(filtered);
    }

    if (searchInput) searchInput.addEventListener('input', debounce(applyFilters, 250));
    if (filterDepartment) filterDepartment.addEventListener('change', applyFilters);
    if (filterStatus) filterStatus.addEventListener('change', applyFilters);

    function debounce(fn, delay) {
        let timeout;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn(...args), delay);
        };
    }

    // ==========================================
    // 11.5 DYNAMIC ORGANIZATIONAL HIERARCHY & CONSTRAINT LOGIC
    // ==========================================
    const jobsByDept = {
        'ฝ่ายบริหารทรัพยากร': [
            'งานบริหารงานทั่วไป',
            'งานบริหารและพัฒนาทรัพยากรบุคคล',
            'งานการเงิน',
            'งานการบัญชี',
            'งานพัสดุ',
            'งานอาคารสถานที่',
            'งานทะเบียน'
        ],
        'ฝ่ายยุทธศาสตร์และแผนงาน': [
            'งานพัฒนายุทธศาสตร์ แผนงาน และงบประมาณ',
            'งานมาตรฐานและประกันคุณภาพ',
            'งานศูนย์ดิจิทัลและสื่อสารองค์กร',
            'งานส่งเสริมการวิจัย นวัตกรรม และสิ่งประดิษฐ์',
            'งานส่งเสริมธุรกิจและการเป็นผู้ประกอบการ',
            'งานติดตามและประเมินผลการอาชีวศึกษา'
        ],
        'ฝ่ายกิจการนักเรียน นักศึกษา': [
            'งานกิจกรรมนักเรียนนักศึกษา',
            'งานครูที่ปรึกษาและการแนะแนว',
            'งานปกครองและความปลอดภัยนักเรียนนักศึกษา',
            'งานโครงการพิเศษและการบริการ',
            'งานสวัสดิการนักเรียน นักศึกษา'
        ],
        'ฝ่ายวิชาการ': [
            'แผนกวิชาช่างกลโรงงานและเทคนิคพื้นฐาน',
            'แผนกวิชาช่างยนต์',
            'แผนกวิชาช่างไฟฟ้ากำลัง',
            'แผนกวิชาช่างอิเล็กทรอนิกส์',
            'แผนกวิชาการบัญชี',
            'แผนกวิชาเทคโนโลยีธุรกิจดิจิทัล',
            'แผนกวิชาสามัญสัมพันธ์',
            'แผนกวิชาชีพระยะสั้น',
            'งานพัฒนาหลักสูตรและการจัดการเรียนรู้',
            'งานวัดผลและประเมินผล',
            'งานอาชีวศึกษาระบบทวิภาคีและความร่วมมือ',
            'งานวิทยบริการและเทคโนโลยีการศึกษา',
            'งานการศึกษาพิเศษและความเสมอภาคทางการศึกษา'
        ]
    };

    function getNormalizedDept(dept) {
        if (!dept) return '';
        if (dept.includes('วิชาการ')) return 'ฝ่ายวิชาการ';
        if (dept.includes('บริหาร')) return 'ฝ่ายบริหารทรัพยากร';
        if (dept.includes('แผนงาน') || dept.includes('ยุทธศาสตร์')) return 'ฝ่ายยุทธศาสตร์และแผนงาน';
        if (dept.includes('กิจการ') || dept.includes('นักเรียน')) return 'ฝ่ายกิจการนักเรียน นักศึกษา';
        return dept;
    }

    function updateJobDropdown(deptSelect, jobSelect, selectedJob = '') {
        const dept = getNormalizedDept(deptSelect.value);
        jobSelect.innerHTML = '';
        
        if (!dept || !jobsByDept[dept]) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = '-- กรุณาเลือกฝ่ายงานก่อน --';
            jobSelect.appendChild(opt);
            return;
        }

        const optDefault = document.createElement('option');
        optDefault.value = '';
        optDefault.textContent = '-- เลือกงาน/แผนกย่อย --';
        jobSelect.appendChild(optDefault);

        jobsByDept[dept].forEach(job => {
            const opt = document.createElement('option');
            opt.value = job;
            opt.textContent = job;
            if (job === selectedJob) {
                opt.selected = true;
            }
            jobSelect.appendChild(opt);
        });
    }

    function handlePositionConstraints(primaryPosSelect, orgPosSelect, deptSelect, jobSelect, orgPosVal = '', deptVal = '', jobVal = '') {
        const primaryPos = primaryPosSelect.value;
        const prefix = primaryPosSelect.id && primaryPosSelect.id.startsWith('position') ? 'position' : 'form';
        
        // ฟังก์ชันช่วยในการตั้งค่าความโปร่งแสงอย่างปลอดภัยเพื่อป้องกันความผิดพลาดหากไม่มี Element ในหน้าเว็บ
        const setOpacity = (suffix, val) => {
            const el = document.getElementById(`${prefix}-${suffix}`);
            if (el) el.style.opacity = val;
        };

        // Reset disabled states
        orgPosSelect.disabled = false;
        deptSelect.disabled = false;
        jobSelect.disabled = false;
        
        // Reset opacities
        setOpacity('org-position-group', '1');
        setOpacity('department-group', '1');
        setOpacity('job-group', '1');

        if (primaryPos === 'ผู้อำนวยการ') {
            orgPosSelect.value = '';
            deptSelect.value = '';
            jobSelect.value = '';
            
            orgPosSelect.disabled = true;
            deptSelect.disabled = true;
            jobSelect.disabled = true;
            
            setOpacity('org-position-group', '0.4');
            setOpacity('department-group', '0.4');
            setOpacity('job-group', '0.4');
            
            orgPosSelect.removeAttribute('required');
            deptSelect.removeAttribute('required');
            jobSelect.removeAttribute('required');
        } 
        
        else if (primaryPos === 'รองผู้อำนวยการ') {
            orgPosSelect.innerHTML = `<option value="รองผู้อำนวยการ" selected>รองผู้อำนวยการ</option>`;
            orgPosSelect.value = 'รองผู้อำนวยการ';
            orgPosSelect.disabled = true;
            
            jobSelect.value = '';
            jobSelect.disabled = true;
            
            deptSelect.value = deptVal;
            
            setOpacity('org-position-group', '0.4');
            setOpacity('job-group', '0.4');
            
            orgPosSelect.setAttribute('required', 'required');
            deptSelect.setAttribute('required', 'required');
            jobSelect.removeAttribute('required');
        } 
        
        else if (['ข้าราชการครู', 'พนักงานราชการครู', 'ครูพิเศษสอน'].includes(primaryPos)) {
            orgPosSelect.innerHTML = `
                <option value="">-- เลือกตำแหน่งงาน --</option>
                <option value="หัวหน้างาน">หัวหน้างาน</option>
                <option value="ผู้ช่วยหัวหน้างาน">ผู้ช่วยหัวหน้างาน</option>
                <option value="เจ้าหน้าที่">เจ้าหน้าที่</option>
            `;
            orgPosSelect.value = orgPosVal;
            deptSelect.value = deptVal;
            updateJobDropdown(deptSelect, jobSelect, jobVal);
            
            orgPosSelect.setAttribute('required', 'required');
            deptSelect.setAttribute('required', 'required');
            jobSelect.setAttribute('required', 'required');
        } 
        
        else {
            // เจ้าหน้าที่, พนักงานขับรถ, ภารโรง, แม่บ้าน เป็นได้แค่ "เจ้าหน้าที่" ตามโครงสร้างงาน
            orgPosSelect.innerHTML = `<option value="เจ้าหน้าที่" selected>เจ้าหน้าที่</option>`;
            orgPosSelect.value = 'เจ้าหน้าที่';
            orgPosSelect.disabled = true;
            
            deptSelect.value = deptVal;
            updateJobDropdown(deptSelect, jobSelect, jobVal);
            
            setOpacity('org-position-group', '0.4');
            
            orgPosSelect.setAttribute('required', 'required');
            deptSelect.setAttribute('required', 'required');
            jobSelect.setAttribute('required', 'required');
        }
    }

    // ==========================================
    // 12. ADD USER MODAL
    // ==========================================
    if (btnAddUser) {
        btnAddUser.addEventListener('click', () => {
            openAddModal();
        });
    }

    function openAddModal() {
        modalTitle.textContent = 'เพิ่มผู้ใช้งานใหม่';
        userForm.reset();
        formUserId.value = '';
        formErrorMsg.classList.add('hidden');
        resetAvatarPreview();
        
        // แสดงฟิลด์ตำแหน่งหลัก และทำให้ต้องกรอก (required) เมื่อเพิ่มผู้ใช้งานใหม่
        if (formPrimaryPosGroup) formPrimaryPosGroup.style.display = 'block';
        if (formPrimaryPos) {
            formPrimaryPos.value = '';
            formPrimaryPos.required = true;
        }

        btnSubmitForm.querySelector('.btn-text').textContent = '💾 บันทึกข้อมูลสมาชิก';
        userModalOverlay.classList.remove('hidden');
        
        setTimeout(() => formUsername.focus(), 300);
    }

    // ==========================================
    // 13. EDIT USER MODAL
    // ==========================================
    function openEditModal(userId) {
        const user = allUsersData.find(u => u.id === userId || parseInt(u.id) === userId);
        if (!user) return;

        modalTitle.textContent = 'แก้ไขข้อมูลผู้ใช้งานเบื้องต้น';
        formUserId.value = user.id;
        formUsername.value = user.username || '';
        formTitle.value = user.title || '';
        formFirstname.value = user.first_name || '';
        formLastname.value = user.last_name || '';
        formEmail.value = (user.email && !user.email.endsWith('@pnp.ac.th')) ? user.email : '';
        formPhone.value = user.phone || '';
        formPortalAdmin.checked = parseInt(user.is_portal_admin) === 1;

        // ซ่อนฟิลด์ตำแหน่งหลัก และไม่จำเป็นต้องกรอกเมื่อแก้ไข (เนื่องจากสามารถจัดการผ่านปุ่ม 💼 แยกต่างหาก)
        if (formPrimaryPosGroup) formPrimaryPosGroup.style.display = 'none';
        if (formPrimaryPos) {
            formPrimaryPos.value = user.primary_position || '';
            formPrimaryPos.required = false;
        }

        formErrorMsg.classList.add('hidden');

        // Avatar preview
        if (user.avatar) {
            avatarPreview.innerHTML = `<img src="${user.avatar}" alt="avatar">`;
        } else {
            resetAvatarPreview();
        }

        btnSubmitForm.querySelector('.btn-text').textContent = '💾 อัปเดตข้อมูลสมาชิก';
        userModalOverlay.classList.remove('hidden');
    }

    function resetAvatarPreview() {
        avatarPreview.innerHTML = '<span class="avatar-placeholder">👤</span>';
    }

    // Avatar file preview
    if (formAvatar) {
        formAvatar.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 2 * 1024 * 1024) {
                    showToast('ขนาดไฟล์ต้องไม่เกิน 2MB', 'error');
                    formAvatar.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = (ev) => {
                    avatarPreview.innerHTML = `<img src="${ev.target.result}" alt="preview">`;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Close modal
    if (btnCloseModal) {
        btnCloseModal.addEventListener('click', () => {
            userModalOverlay.classList.add('hidden');
        });
    }

    // Click overlay to close
    if (userModalOverlay) {
        userModalOverlay.addEventListener('click', (e) => {
            if (e.target === userModalOverlay) {
                userModalOverlay.classList.add('hidden');
            }
        });
    }

    // ==========================================
    // 14. FORM SUBMISSION (Create / Update)
    // ==========================================
    if (userForm) {
        userForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            formErrorMsg.classList.add('hidden');

            const userId = formUserId.value;
            const isEditing = !!userId;
            const action = isEditing ? 'update_user_details' : 'create_user';

            const username = formUsername.value.trim();
            const title = formTitle.value.trim();
            const firstName = formFirstname.value.trim();
            const lastName = formLastname.value.trim();
            const email = formEmail.value.trim();
            const phone = formPhone.value.trim();
            const isPortalAdmin = formPortalAdmin.checked ? 1 : 0;

            // Validate
            if (!username) {
                showFormError('กรุณากรอกชื่อผู้ใช้ (เลขบัตรประชาชน)');
                return;
            }
            if (!title) {
                showFormError('กรุณากรอกคำนำหน้าชื่อ (เช่น นาย / นาง / นางสาว / ดร.)');
                return;
            }
            if (!firstName) {
                showFormError('กรุณากรอกชื่อจริง');
                return;
            }
            if (!lastName) {
                showFormError('กรุณากรอกนามสกุล');
                return;
            }

            // ตรวจสอบตำแหน่งหลักเมื่อลงทะเบียนครั้งแรก
            if (!isEditing && (!formPrimaryPos || !formPrimaryPos.value)) {
                showFormError('กรุณาเลือกตำแหน่งหลักของบุคลากร');
                return;
            }

            // ตรวจสอบเลขบัตรประชาชน 13 หลัก
            if (username !== 'admin') {
                const numericRegex = /^\d{13}$/;
                if (!numericRegex.test(username)) {
                    showFormError('เลขประจำตัวประชาชน (Username) ต้องเป็นตัวเลข 13 หลักเท่านั้น');
                    return;
                }
            }

            // Build payload
            const payload = {
                action: action,
                title: title,
                first_name: firstName,
                last_name: lastName,
                email: email,
                phone: phone,
                is_portal_admin: isPortalAdmin
            };

            if (isEditing) {
                payload.user_id = parseInt(userId);
            } else {
                payload.username = username;
                payload.primary_position = formPrimaryPos.value;
                // รหัสผ่านเริ่มต้นและอีเมลจะทำงานอัตโนมัติบนเซิร์ฟเวอร์
            }

            // Disable submit
            btnSubmitForm.disabled = true;
            btnSubmitForm.style.opacity = '0.7';
            btnSubmitForm.querySelector('.btn-text').textContent = '⚡ กำลังบันทึก...';

            try {
                const token = localStorage.getItem('pnp-token');
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'เกิดข้อผิดพลาด');
                }

                userModalOverlay.classList.add('hidden');
                showToast(isEditing ? 'อัปเดตข้อมูลบัญชีสำเร็จ' : 'เพิ่มผู้ใช้งานใหม่สำเร็จ (รหัสผ่านคือเลขบัตรประชาชน)', 'success');
                loadUsersTable();

            } catch (err) {
                showFormError(err.message);
            } finally {
                btnSubmitForm.disabled = false;
                btnSubmitForm.style.opacity = '1';
                const isEditing2 = !!formUserId.value;
                btnSubmitForm.querySelector('.btn-text').textContent = isEditing2 ? '💾 อัปเดตข้อมูลบัญชี' : '💾 บันทึกข้อมูลสมาชิก';
            }
        });
    }

    // ==========================================
    // 14.2 POSITION FORM SUBMISSION & LOGIC
    // ==========================================
    function openPositionModal(user) {
        positionErrorMsg.classList.add('hidden');
        positionForm.reset();
        
        positionUserId.value = user.id;
        positionUserDisplay.textContent = `${user.title || ''}${user.first_name} ${user.last_name} (${user.username})`;
        
        positionPrimaryPos.value = user.primary_position || '';
        
        // โหลดข้อจำกัดระดับตำแหน่งและฝ่าย
        handlePositionConstraints(
            positionPrimaryPos, 
            positionOrgPos, 
            positionDepartment, 
            positionJob, 
            user.org_position, 
            user.department, 
            user.job
        );

        // โหลดสิทธิ์
        const roles = user.roles || {};
        positionRoleGo.value = roles['pnp-go'] || 'none';
        positionRoleAcademic.value = roles['pnp-academic'] || 'none';
        positionRoleMan.value = roles['pnp-man'] || 'none';
        
        positionModalOverlay.classList.remove('hidden');
    }

    if (btnClosePositionModal) {
        btnClosePositionModal.addEventListener('click', () => {
            positionModalOverlay.classList.add('hidden');
        });
    }

    if (positionModalOverlay) {
        positionModalOverlay.addEventListener('click', (e) => {
            if (e.target === positionModalOverlay) {
                positionModalOverlay.classList.add('hidden');
            }
        });
    }

    if (positionForm) {
        positionForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            positionErrorMsg.classList.add('hidden');

            const userId = parseInt(positionUserId.value);
            const primaryPos = positionPrimaryPos.value;
            const orgPos = positionOrgPos.value;
            const dept = positionDepartment.value;
            const job = positionJob.value;

            const payload = {
                action: 'update_user_positions',
                user_id: userId,
                primary_position: primaryPos,
                org_position: orgPos,
                department: dept,
                job: job,
                roles: {
                    'pnp-go': positionRoleGo.value,
                    'pnp-academic': positionRoleAcademic.value,
                    'pnp-man': positionRoleMan.value
                }
            };

            btnSubmitPositionForm.disabled = true;
            btnSubmitPositionForm.style.opacity = '0.7';
            btnSubmitPositionForm.querySelector('.btn-text').textContent = '⚡ กำลังบันทึก...';

            try {
                const token = localStorage.getItem('pnp-token');
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'เกิดข้อผิดพลาด');
                }

                positionModalOverlay.classList.add('hidden');
                showToast('บันทึกตำแหน่งหน้าที่และสิทธิ์ย่อยเรียบร้อยแล้ว', 'success');
                loadUsersTable();

            } catch (err) {
                positionErrorMsg.classList.remove('hidden');
                positionErrorText.textContent = err.message;
            } finally {
                btnSubmitPositionForm.disabled = false;
                btnSubmitPositionForm.style.opacity = '1';
                btnSubmitPositionForm.querySelector('.btn-text').textContent = '💼 บันทึกตำแหน่งและหน้าที่ความรับผิดชอบ';
            }
        });
    }

    function showFormError(message) {
        formErrorMsg.classList.remove('hidden');
        formErrorText.textContent = message;
    }

    // ==========================================
    // 15. TOGGLE PORTAL ADMIN
    // ==========================================
    async function togglePortalAdmin(userId) {
        try {
            const token = localStorage.getItem('pnp-token');
            const response = await fetch(API_BASE, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({
                    user_id: userId,
                    action: 'toggle_portal_admin'
                })
            });

            const data = await response.json();
            if (!response.ok) throw new Error(data.error);

            showToast('อัปเดตสถานะแอดมินสำเร็จ', 'success');

            // Update local data
            const user = allUsersData.find(u => u.id === userId || parseInt(u.id) === userId);
            if (user) {
                user.is_portal_admin = parseInt(user.is_portal_admin) === 1 ? 0 : 1;
            }

        } catch (err) {
            showToast('เกิดข้อผิดพลาด: ' + err.message, 'error');
            loadUsersTable();
        }
    }

    // ==========================================
    // 16. UPDATE APP ROLE
    // ==========================================
    async function updateUserAppRole(userId, appId, role) {
        try {
            const token = localStorage.getItem('pnp-token');
            const response = await fetch(API_BASE, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({
                    user_id: userId,
                    action: 'update_app_role',
                    app_id: appId,
                    role: role
                })
            });

            const data = await response.json();
            if (!response.ok) throw new Error(data.error);

            showToast('อัปเดตสิทธิ์สำเร็จ', 'success');

            // Update local data
            const user = allUsersData.find(u => u.id === userId || parseInt(u.id) === userId);
            if (user && user.roles) {
                user.roles[appId] = role;
            }

        } catch (err) {
            showToast('เกิดข้อผิดพลาด: ' + err.message, 'error');
            loadUsersTable();
        }
    }

    // ==========================================
    // 17. SUSPEND / ACTIVATE CONFIRM
    // ==========================================
    function openSuspendConfirm(userId, isCurrentlyActive) {
        const user = allUsersData.find(u => u.id === userId || parseInt(u.id) === userId);
        const name = user ? `${user.first_name} ${user.last_name}` : 'สมาชิก';

        if (isCurrentlyActive) {
            confirmIcon.textContent = '⏸️';
            confirmMessage.innerHTML = `คุณต้องการ<strong>ระงับการใช้งาน</strong>ของ<br><strong>${name}</strong> หรือไม่?<br><span style="font-size:12px;color:var(--text-muted)">สมาชิกจะไม่สามารถเข้าสู่ระบบได้ชั่วคราว</span>`;
            btnConfirmAction.className = 'btn-confirm-action action-warning';
            btnConfirmAction.textContent = '⏸️ ระงับการใช้งาน';
        } else {
            confirmIcon.textContent = '▶️';
            confirmMessage.innerHTML = `คุณต้องการ<strong>เปิดใช้งาน</strong>ให้<br><strong>${name}</strong> อีกครั้งหรือไม่?`;
            btnConfirmAction.className = 'btn-confirm-action';
            btnConfirmAction.style.background = 'linear-gradient(135deg, #2ecc71, #27ae60)';
            btnConfirmAction.style.boxShadow = '0 4px 15px rgba(46, 204, 113, 0.25)';
            btnConfirmAction.textContent = '▶️ เปิดใช้งาน';
        }

        pendingConfirmAction = () => toggleUserStatus(userId);
        confirmModalOverlay.classList.remove('hidden');
    }

    // ==========================================
    // 18. DELETE CONFIRM
    // ==========================================
    function openDeleteConfirm(userId) {
        const user = allUsersData.find(u => u.id === userId || parseInt(u.id) === userId);
        const name = user ? `${user.first_name} ${user.last_name}` : 'สมาชิก';

        confirmIcon.textContent = '🗑️';
        confirmMessage.innerHTML = `คุณต้องการ<strong>ลบบัญชี</strong>ของ<br><strong>${name}</strong> ออกจากระบบหรือไม่?<br><span style="font-size:12px;color:#ff4d4d">⚠️ การดำเนินการนี้ไม่สามารถย้อนกลับได้</span>`;
        btnConfirmAction.className = 'btn-confirm-action';
        btnConfirmAction.style.background = '';
        btnConfirmAction.style.boxShadow = '';
        btnConfirmAction.textContent = '🗑️ ลบบัญชี';

        pendingConfirmAction = () => deleteUser(userId);
        confirmModalOverlay.classList.remove('hidden');
    }

    // Confirm action handlers
    if (btnConfirmAction) {
        btnConfirmAction.addEventListener('click', () => {
            if (pendingConfirmAction) {
                pendingConfirmAction();
            }
            confirmModalOverlay.classList.add('hidden');
            pendingConfirmAction = null;
        });
    }

    if (btnCancelAction) {
        btnCancelAction.addEventListener('click', () => {
            confirmModalOverlay.classList.add('hidden');
            pendingConfirmAction = null;
        });
    }

    if (confirmModalOverlay) {
        confirmModalOverlay.addEventListener('click', (e) => {
            if (e.target === confirmModalOverlay) {
                confirmModalOverlay.classList.add('hidden');
                pendingConfirmAction = null;
            }
        });
    }

    // ==========================================
    // 19. TOGGLE USER STATUS (Suspend/Activate)
    // ==========================================
    async function toggleUserStatus(userId) {
        try {
            const token = localStorage.getItem('pnp-token');
            const response = await fetch(API_BASE, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({
                    user_id: userId,
                    action: 'toggle_user_status'
                })
            });

            const data = await response.json();
            if (!response.ok) throw new Error(data.error);

            showToast('อัปเดตสถานะสมาชิกสำเร็จ', 'success');
            loadUsersTable();

        } catch (err) {
            showToast('เกิดข้อผิดพลาด: ' + err.message, 'error');
        }
    }

    // ==========================================
    // 20. DELETE USER
    // ==========================================
    async function deleteUser(userId) {
        try {
            const token = localStorage.getItem('pnp-token');
            const response = await fetch(API_BASE, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({
                    user_id: userId,
                    action: 'delete_user'
                })
            });

            const data = await response.json();
            if (!response.ok) throw new Error(data.error);

            showToast('ลบบัญชีสมาชิกสำเร็จ', 'success');
            loadUsersTable();

        } catch (err) {
            showToast('เกิดข้อผิดพลาด: ' + err.message, 'error');
        }
    }

    // ==========================================
    // 21. TOAST NOTIFICATION
    // ==========================================
    let toastTimer = null;

    function showToast(message, type = 'success') {
        if (!toastNotification) return;

        // Clear previous timer
        if (toastTimer) clearTimeout(toastTimer);

        // Set content
        toastMessage.textContent = message;
        toastIcon.textContent = type === 'success' ? '✅' : '❌';

        // Set style
        toastNotification.classList.remove('toast-success', 'toast-error', 'hidden');
        toastNotification.classList.add(type === 'success' ? 'toast-success' : 'toast-error');

        // Trigger show with slight delay for animation
        requestAnimationFrame(() => {
            toastNotification.classList.add('toast-show');
        });

        // Auto-hide after 3.5 seconds
        toastTimer = setTimeout(() => {
            toastNotification.classList.remove('toast-show');
            setTimeout(() => {
                toastNotification.classList.add('hidden');
            }, 500);
        }, 3500);
    }

    // ==========================================
    // 22. KEYBOARD SHORTCUTS
    // ==========================================
    document.addEventListener('keydown', (e) => {
        // ESC to close modals
        if (e.key === 'Escape') {
            if (!userModalOverlay.classList.contains('hidden')) {
                userModalOverlay.classList.add('hidden');
            }
            if (!confirmModalOverlay.classList.contains('hidden')) {
                confirmModalOverlay.classList.add('hidden');
                pendingConfirmAction = null;
            }
        }

        // Ctrl+K to focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            searchInput.focus();
        }
    });

    // Bind dynamic constraints for position modal
    if (positionPrimaryPos) {
        positionPrimaryPos.addEventListener('change', () => {
            handlePositionConstraints(positionPrimaryPos, positionOrgPos, positionDepartment, positionJob);
        });
    }

    if (positionDepartment) {
        positionDepartment.addEventListener('change', () => {
            updateJobDropdown(positionDepartment, positionJob);
        });
    }

    if (formUsername) {
        formUsername.addEventListener('input', () => {
            if (formUsername.value !== 'admin') {
                formUsername.value = formUsername.value.replace(/\D/g, '');
            }
        });
    }

    // ==========================================
    // INIT — Start auth guard
    // ==========================================
    authGuard();
});
