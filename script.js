/* -------------------------------------------------------------
 * JAVASCRIPT LOGIC: PNP UNIVERSAL PORTAL
 * Features: Dynamic Greeting, Live Clock, Particle Background, 
 *           Theme Toggle, JWT Authentication, and Admin CRUD Panel
 * ------------------------------------------------------------- */

document.addEventListener('DOMContentLoaded', () => {
    
    // ==========================================
    // 1. DYNAMIC TIME-OF-DAY GREETING (ภาษาไทย)
    // ==========================================
    const greetingElement = document.getElementById('dynamic-greeting');
    const welcomeNameElement = document.getElementById('user-welcome-name');
    let currentUserProfile = null; // เก็บข้อมูลผู้ใช้ปัจจุบันหลังยืนยันตัวตน
    let allUsersListCache = []; // แคชรายชื่อสมาชิกสำหรับแก้ไขใน CRUD
    
    function getGreeting() {
        const now = new Date();
        const hours = now.getHours();
        if (hours >= 5 && hours < 12) return 'สวัสดีตอนเช้าครับ ☀️';
        if (hours >= 12 && hours < 17) return 'สวัสดีตอนบ่ายครับ 🌤️';
        if (hours >= 17 && hours < 21) return 'สวัสดีตอนเย็นครับ 🌅';
        return 'สวัสดีตอนค่ำครับ 🌙';
    }

    function updateGreeting() {
        if (!greetingElement) return;
        const baseGreeting = getGreeting();
        
        if (currentUserProfile) {
            greetingElement.textContent = `${baseGreeting} `;
            welcomeNameElement.textContent = `${currentUserProfile.title || ''}${currentUserProfile.first_name} ${currentUserProfile.last_name}`;
        } else {
            greetingElement.textContent = 'ยินดีต้อนรับสู่ระบบพอร์ทัลกลาง';
            welcomeNameElement.textContent = '';
        }
    }

    // ==========================================
    // 2. LIVE DIGITAL CLOCK
    // ==========================================
    const timeElement = document.getElementById('live-time');
    
    function updateClock() {
        if (!timeElement) return;
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        timeElement.textContent = `${hours}:${minutes}:${seconds}`;
    }

    setInterval(updateClock, 1000);
    updateClock(); // Initial call
    updateGreeting(); // Initial call

    // ==========================================
    // 3. PERSISTENT THEME SWITCHER (DARK/LIGHT)
    // ==========================================
    const themeToggle = document.getElementById('theme-toggle');
    const body = document.body;

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
    // 4. CANVAS PARTICLE SYSTEM (BACKGROUND)
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
    // 5. JWT AUTHENTICATION LOGIC (SSO PORTAL)
    // ==========================================
    const btnLoginTrigger = document.getElementById('btn-login-trigger');
    const userProfileMenu = document.getElementById('user-profile-menu');
    const usernameDisplay = document.getElementById('username-display');
    const profileBadgeBtn = document.getElementById('profile-badge-btn');
    const profileDropdown = document.getElementById('profile-dropdown');
    const btnAdminPanel = document.getElementById('btn-admin-panel');
    const btnLogout = document.getElementById('btn-logout');
    
    const loginModalOverlay = document.getElementById('login-modal-overlay');
    const btnCloseLogin = document.getElementById('btn-close-login');
    const loginForm = document.getElementById('login-form');
    const loginUsernameInput = document.getElementById('login-username');
    const loginPasswordInput = document.getElementById('login-password');
    const loginErrorMsg = document.getElementById('login-error-msg');
    const errorText = document.getElementById('error-text');
    const btnSubmitLogin = document.getElementById('btn-submit-login');

    // ลิงก์ระบบย่อยต้นฉบับดึงจาก Git
    const appLinks = {
        'pnp-go': { link: document.getElementById('app-link-pnp-go'), pill: document.getElementById('role-pill-pnp-go'), base: 'https://pnp-go.montien.tech' },
        'pnp-man': { link: document.getElementById('app-link-pnp-man'), pill: document.getElementById('role-pill-pnp-man'), base: './pnpman/' },
        'pnp-academic': { link: document.getElementById('app-link-pnp-academic'), pill: document.getElementById('role-pill-pnp-academic'), base: 'https://pnp-edu.montien.tech' }
    };

    // เปิด / ปิด ดรอปดาวน์โปรไฟล์
    if (profileBadgeBtn) {
        profileBadgeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
        });
    }

    document.addEventListener('click', () => {
        if (profileDropdown) profileDropdown.classList.add('hidden');
    });

    // เปิด Modal ล็อกอิน
    if (btnLoginTrigger) {
        btnLoginTrigger.addEventListener('click', () => {
            openLoginModal();
        });
    }

    // ปิด Modal ล็อกอิน
    if (btnCloseLogin) {
        btnCloseLogin.addEventListener('click', () => {
            closeLoginModal();
        });
    }

    function openLoginModal() {
        loginModalOverlay.classList.remove('hidden');
        loginErrorMsg.classList.add('hidden');
        loginForm.reset();
        loginUsernameInput.focus();
    }

    function closeLoginModal() {
        loginModalOverlay.classList.add('hidden');
    }

    // จัดการการส่งฟอร์มล็อกอิน (POST JSON)
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const username = loginUsernameInput.value.trim();
            const password = loginPasswordInput.value;
            
            btnSubmitLogin.disabled = true;
            btnSubmitLogin.style.opacity = '0.7';
            btnSubmitLogin.innerHTML = '⚡ กำลังตรวจสอบความปลอดภัย...';
            loginErrorMsg.classList.add('hidden');

            try {
                const response = await fetch('api/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'การเชื่อมต่อผิดพลาด');
                }

                localStorage.setItem('pnp-token', data.token);
                
                currentUserProfile = data.user;
                
                // หากเป็นผู้ดูแลระบบ (Portal Admin) ให้เข้าหน้า Admin Panel ทันทีเพื่อความสะดวกรวดเร็ว
                if (parseInt(data.user.is_portal_admin) === 1) {
                    window.location.href = 'admin.html';
                    return;
                }
                
                closeLoginModal();
                applyUserSession(data.token);
                
            } catch (err) {
                errorText.textContent = err.message;
                loginErrorMsg.classList.remove('hidden');
            } finally {
                btnSubmitLogin.disabled = false;
                btnSubmitLogin.style.opacity = '1';
                btnSubmitLogin.innerHTML = 'เข้าสู่ระบบการทำงาน';
            }
        });
    }

    // จัดการออกจากระบบ (Logout)
    if (btnLogout) {
        btnLogout.addEventListener('click', () => {
            localStorage.removeItem('pnp-token');
            currentUserProfile = null;
            applyUserSession(null);
            
            adminPanelOverlay.classList.add('hidden');
        });
    }

    // ฟังก์ชันประยุกต์ใช้เซสชัน
    function applyUserSession(token) {
        if (token && currentUserProfile) {
            btnLoginTrigger.classList.add('hidden');
            userProfileMenu.classList.remove('hidden');
            usernameDisplay.textContent = `${currentUserProfile.first_name} ${currentUserProfile.last_name[0]}.`;
            
            updateGreeting();

            if (parseInt(currentUserProfile.is_portal_admin) === 1) {
                btnAdminPanel.classList.remove('hidden');
            } else {
                btnAdminPanel.classList.add('hidden');
            }

            updateAppCards(currentUserProfile.roles, token);
        } else {
            btnLoginTrigger.classList.remove('hidden');
            userProfileMenu.classList.add('hidden');
            
            updateGreeting();
            updateAppCards(null, null);
        }
    }

    // อัปเดตการแสดงผลของแอปย่อย และแนบสัญญาสิทธิ์ JWT
    function updateAppCards(roles, token) {
        for (const [appId, card] of Object.entries(appLinks)) {
            if (!roles) {
                card.pill.className = 'role-pill role-none';
                card.pill.textContent = 'ต้องล็อกอิน';
                card.link.className = 'app-link disabled';
                card.link.href = '#';
                card.link.querySelector('span:first-child').textContent = 'ต้องล็อกอินก่อนเข้าใช้งาน';
                continue;
            }

            const role = roles[appId] || 'none';

            card.pill.className = 'role-pill';

            if (role === 'none') {
                card.pill.classList.add('role-none');
                card.pill.textContent = 'ไม่มีสิทธิ์เข้าใช้';
                card.link.className = 'app-link disabled';
                card.link.href = '#';
                card.link.querySelector('span:first-child').textContent = 'ไม่มีสิทธิ์เข้าใช้งานระบบนี้';
            } else {
                card.pill.classList.add(`role-${role}`);
                
                let roleThai = 'ผู้ใช้ทั่วไป';
                if (role === 'admin') roleThai = 'ผู้ดูแลระบบ';
                if (role === 'teacher') roleThai = 'คุณครูผู้สอน';
                if (role === 'driver') roleThai = 'พนักงานขับรถ';

                card.pill.textContent = roleThai;
                card.link.className = 'app-link';
                
                // เชื่อมโยงข้าม Subdomain ของลูกค้าตัวจริงด้วย SSO JWT
                card.link.href = `${card.base}?token=${encodeURIComponent(token)}`;
                card.link.querySelector('span:first-child').textContent = 'เข้าใช้งานระบบ';
            }
        }
    }

    // ตรวจสอบความถูกต้องของ Token ตอนเปิดหน้าเว็บ (Auto Login)
    async function checkAutoLogin() {
        const token = localStorage.getItem('pnp-token');
        if (!token) {
            applyUserSession(null);
            return;
        }

        try {
            const response = await fetch('api/verify.php', {
                method: 'GET',
                headers: { 'Authorization': `Bearer ${token}` }
            });

            const data = await response.json();
            if (response.ok && data.valid) {
                currentUserProfile = data.user;
                
                // หากเป็นผู้ดูแลระบบ (Portal Admin) ให้เปลี่ยนทางไปหน้าแอดมินบอร์ดหลักทันที
                if (parseInt(data.user.is_portal_admin) === 1) {
                    window.location.href = 'admin.html';
                    return;
                }
                
                applyUserSession(token);
            } else {
                localStorage.removeItem('pnp-token');
                applyUserSession(null);
            }
        } catch (err) {
            console.error('การเชื่อมต่อล้มเหลว:', err);
            applyUserSession(null);
        }
    }

    checkAutoLogin();

    // ==========================================
    // 6. ADMIN USER & ROLE MANAGEMENT PANEL (CRUD OVERLAY)
    // ==========================================
    const adminPanelOverlay = document.getElementById('admin-panel-overlay');
    const btnCloseAdmin = document.getElementById('btn-close-admin');
    const adminUsersList = document.getElementById('admin-users-list');
    
    // CRUD Modal DOM Elements
    const btnAddUserTrigger = document.getElementById('btn-add-user-trigger');
    const crudModalOverlay = document.getElementById('crud-modal-overlay');
    const btnCloseCrud = document.getElementById('btn-close-crud');
    const btnCancelCrud = document.getElementById('btn-cancel-crud');
    const crudForm = document.getElementById('crud-form');
    
    const crudModalTitle = document.getElementById('crud-modal-title');
    const crudUserIdInput = document.getElementById('crud-user-id');
    const crudUsernameInput = document.getElementById('crud-username');
    const crudPasswordInput = document.getElementById('crud-password');
    const crudPasswordGroup = document.getElementById('crud-password-group');
    const crudFirstNameInput = document.getElementById('crud-first-name');
    const crudLastNameInput = document.getElementById('crud-last-name');
    const crudEmailInput = document.getElementById('crud-email');
    const crudPrimaryPosSelect = document.getElementById('crud-primary-position');
    const crudOrgPosSelect = document.getElementById('crud-org-position');
    const crudDeptSelect = document.getElementById('crud-department');
    const crudJobSelect = document.getElementById('crud-job');
    const crudIsPortalAdminCheckbox = document.getElementById('crud-is-portal-admin');
    
    const crudErrorMsg = document.getElementById('crud-error-msg');
    const crudErrorText = document.getElementById('crud-error-text');
    const btnSubmitCrud = document.getElementById('btn-submit-crud');

    // เปิดหน้าแดชบอร์ดผู้ดูแลระบบหลัก (จัดการสิทธิ์สมาชิก)
    if (btnAdminPanel) {
        btnAdminPanel.addEventListener('click', (e) => {
            e.stopPropagation();
            window.location.href = 'admin.html';
        });
    }

    // ปิดตารางแอดมิน
    if (btnCloseAdmin) {
        btnCloseAdmin.addEventListener('click', () => {
            adminPanelOverlay.classList.add('hidden');
        });
    }

    // โหลดตารางรายชื่อผู้ใช้จาก SQLite database
    async function loadAdminUsersTable() {
        const token = localStorage.getItem('pnp-token');
        if (!token) return;

        adminUsersList.innerHTML = `
            <tr>
                <td colspan="7" class="table-loading">⚡ กำลังค้นหาบัญชีจากฐานข้อมูล SQLite...</td>
            </tr>
        `;

        try {
            const response = await fetch('api/admin_users.php', {
                method: 'GET',
                headers: { 'Authorization': `Bearer ${token}` }
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'เกิดข้อผิดพลาดในการโหลดตาราง');
            }

            allUsersListCache = data.users;
            renderUsersTable(data.users);

        } catch (err) {
            adminUsersList.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align: center; color: #ff4d4d; padding: 20px;">
                        ❌ ${err.message}
                    </td>
                </tr>
            `;
        }
    }

    // สร้างตารางข้อมูลผู้ใช้งานแบบจัดกลุ่ม Multi-line กระชับสวยงาม
    function renderUsersTable(users) {
        if (!users || users.length === 0) {
            adminUsersList.innerHTML = '<tr><td colspan="7" class="table-loading">ไม่มีผู้ใช้ในระบบ</td></tr>';
            return;
        }

        adminUsersList.innerHTML = '';

        users.forEach(user => {
            const tr = document.createElement('tr');
            
            // 1. คอลัมน์ ข้อมูลผู้ใช้หลัก
            const tdInfo = document.createElement('td');
            tdInfo.innerHTML = `
                <div class="user-meta-cell">
                    <span class="user-main-name">${user.first_name} ${user.last_name}</span>
                    <span class="user-sub-email">👤 <code>${user.username}</code> | ✉️ ${user.email}</span>
                </div>
            `;
            tr.appendChild(tdInfo);

            // 2. คอลัมน์ ตำแหน่งหลัก
            const tdPos = document.createElement('td');
            tdPos.innerHTML = `
                <div class="user-pos-cell">
                    <span class="user-pos-primary">📌 ${user.primary_position || 'ไม่ระบุ'}</span>
                </div>
            `;
            tr.appendChild(tdPos);

            // 3. คอลัมน์ แอดมินพอร์ทัลหลัก
            const tdPortalAdmin = document.createElement('td');
            tdPortalAdmin.className = 'text-center';
            
            const isSelf = currentUserProfile && currentUserProfile.username === user.username;
            const isSuperAdmin = user.username === 'admin';
            
            tdPortalAdmin.innerHTML = `
                <label class="switch">
                    <input type="checkbox" ${user.is_portal_admin === 1 ? 'checked' : ''} 
                           ${isSelf || isSuperAdmin ? 'disabled' : ''} data-userid="${user.id}">
                    <span class="slider"></span>
                </label>
            `;
            
            if (!isSelf && !isSuperAdmin) {
                const checkbox = tdPortalAdmin.querySelector('input');
                checkbox.addEventListener('change', () => {
                    togglePortalAdminStatus(user.id);
                });
            }
            tr.appendChild(tdPortalAdmin);

            // 4. บทบาทแอป PNP Go
            const tdGo = document.createElement('td');
            tdGo.appendChild(createRoleSelector(user.id, 'pnp-go', user.roles['pnp-go']));
            tr.appendChild(tdGo);

            // 5. บทบาทแอป PNP academix
            const tdAcademic = document.createElement('td');
            tdAcademic.appendChild(createRoleSelector(user.id, 'pnp-academic', user.roles['pnp-academic']));
            tr.appendChild(tdAcademic);

            // 6. บทบาทแอป PNP man
            const tdMan = document.createElement('td');
            tdMan.appendChild(createRoleSelector(user.id, 'pnp-man', user.roles['pnp-man']));
            tr.appendChild(tdMan);

            // 7. จัดการสมาชิก
            const tdManage = document.createElement('td');
            tdManage.className = 'text-center';
            
            tdManage.innerHTML = `
                <div class="table-actions">
                    <button class="btn-edit-user" title="แก้ไขข้อมูลสมาชิก" data-userid="${user.id}">✏️</button>
                    <button class="btn-delete-user" title="ลบสมาชิกออกจากระบบ" 
                            ${isSelf || isSuperAdmin ? 'disabled style="opacity:0.3; cursor:not-allowed;"' : ''} 
                            data-userid="${user.id}" data-username="${user.username}">🗑️</button>
                </div>
            `;
            
            tdManage.querySelector('.btn-edit-user').addEventListener('click', () => {
                openCrudModal('edit', user.id);
            });

            if (!isSelf && !isSuperAdmin) {
                tdManage.querySelector('.btn-delete-user').addEventListener('click', () => {
                    deleteUserAccount(user.id, user.username);
                });
            }

            tr.appendChild(tdManage);

            adminUsersList.appendChild(tr);
        });
    }

    // สร้างเมนูดรอปดาวน์เลือกบทบาทในตาราง
    function createRoleSelector(userId, appId, currentRole) {
        const select = document.createElement('select');
        select.className = 'admin-role-selector';
        
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
            if (r.val === currentRole) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });

        select.addEventListener('change', () => {
            updateUserAppRole(userId, appId, select.value);
        });

        return select;
    }

    // ==========================================
    // CRUD OPERATION UI INTERACTION & API CALLS
    // ==========================================
    
    // เปิด/ปิดหน้าต่าง CRUD
    if (btnAddUserTrigger) {
        btnAddUserTrigger.addEventListener('click', () => {
            openCrudModal('create');
        });
    }

    if (btnCloseCrud) btnCloseCrud.addEventListener('click', closeCrudModal);
    if (btnCancelCrud) btnCancelCrud.addEventListener('click', closeCrudModal);

    // ==========================================
    // 6.1 DYNAMIC ORGANIZATIONAL HIERARCHY & CONSTRAINT LOGIC
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
        const prefix = primaryPosSelect.id === 'crud-primary-position' ? 'crud' : 'form';
        
        // Reset disabled states
        orgPosSelect.disabled = false;
        deptSelect.disabled = false;
        jobSelect.disabled = false;
        
        // Reset opacities
        document.getElementById(`${prefix}-org-position-group`).style.opacity = '1';
        document.getElementById(`${prefix}-department-group`).style.opacity = '1';
        document.getElementById(`${prefix}-job-group`).style.opacity = '1';

        if (primaryPos === 'ผู้อำนวยการ') {
            orgPosSelect.value = '';
            deptSelect.value = '';
            jobSelect.value = '';
            
            orgPosSelect.disabled = true;
            deptSelect.disabled = true;
            jobSelect.disabled = true;
            
            document.getElementById(`${prefix}-org-position-group`).style.opacity = '0.4';
            document.getElementById(`${prefix}-department-group`).style.opacity = '0.4';
            document.getElementById(`${prefix}-job-group`).style.opacity = '0.4';
            
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
            
            document.getElementById(`${prefix}-org-position-group`).style.opacity = '0.4';
            document.getElementById(`${prefix}-job-group`).style.opacity = '0.4';
            
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
            
            document.getElementById(`${prefix}-org-position-group`).style.opacity = '0.4';
            
            orgPosSelect.setAttribute('required', 'required');
            deptSelect.setAttribute('required', 'required');
            jobSelect.setAttribute('required', 'required');
        }
    }

    // Bind event listeners for dynamic constraints
    if (crudPrimaryPosSelect) {
        crudPrimaryPosSelect.addEventListener('change', () => {
            handlePositionConstraints(crudPrimaryPosSelect, crudOrgPosSelect, crudDeptSelect, crudJobSelect);
        });
    }

    if (crudDeptSelect) {
        crudDeptSelect.addEventListener('change', () => {
            updateJobDropdown(crudDeptSelect, crudJobSelect);
        });
    }

    if (crudUsernameInput && crudPasswordInput) {
        crudUsernameInput.addEventListener('input', () => {
            if (crudPrimaryPosSelect && crudPrimaryPosSelect.value !== 'ผู้ดูแลระบบ') {
                // Keep only digits
                crudUsernameInput.value = crudUsernameInput.value.replace(/\D/g, '');
            }
            // Auto copy ID card number to password in create mode
            if (!crudUserIdInput.value) {
                crudPasswordInput.value = crudUsernameInput.value;
            }
        });

        crudPasswordInput.addEventListener('input', () => {
            if (crudPrimaryPosSelect && crudPrimaryPosSelect.value !== 'ผู้ดูแลระบบ') {
                // Keep only digits
                crudPasswordInput.value = crudPasswordInput.value.replace(/\D/g, '');
            }
        });
    }

    function openCrudModal(mode, userId = null) {
        crudModalOverlay.classList.remove('hidden');
        crudErrorMsg.classList.add('hidden');
        crudForm.reset();

        if (mode === 'create') {
            crudModalTitle.textContent = '➕ เพิ่มบัญชีผู้ใช้งานใหม่';
            crudUserIdInput.value = '';
            crudUsernameInput.disabled = false;
            crudPasswordInput.required = true;
            crudPasswordGroup.classList.remove('hidden');
            
            handlePositionConstraints(crudPrimaryPosSelect, crudOrgPosSelect, crudDeptSelect, crudJobSelect);
            crudUsernameInput.focus();
        } 
        
        else if (mode === 'edit' && userId !== null) {
            crudModalTitle.textContent = '✏️ แก้ไขข้อมูลสมาชิก';
            crudUserIdInput.value = userId;
            
            const user = allUsersListCache.find(u => u.id === userId);
            if (user) {
                crudUsernameInput.value = user.username;
                crudUsernameInput.disabled = true;
                
                crudPasswordInput.required = false;
                crudPasswordGroup.classList.add('hidden');
                
                crudFirstNameInput.value = user.first_name;
                crudLastNameInput.value = user.last_name;
                crudEmailInput.value = user.email;
                crudIsPortalAdminCheckbox.checked = user.is_portal_admin === 1;
                
                const isSelf = currentUserProfile && currentUserProfile.username === user.username;
                crudIsPortalAdminCheckbox.disabled = isSelf;
                
                // Load constraints dynamically with saved values
                crudPrimaryPosSelect.value = user.primary_position || '';
                handlePositionConstraints(crudPrimaryPosSelect, crudOrgPosSelect, crudDeptSelect, crudJobSelect, user.org_position, user.department, user.job);
                
                crudFirstNameInput.focus();
            }
        }
    }

    function closeCrudModal() {
        crudModalOverlay.classList.add('hidden');
    }

    // บันทึกฟอร์มสมาชิก CRUD
    if (crudForm) {
        crudForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const token = localStorage.getItem('pnp-token');
            if (!token) return;

            const userId = crudUserIdInput.value;
            const username = crudUsernameInput.value.trim();
            const password = crudPasswordInput.value;
            const firstName = crudFirstNameInput.value.trim();
            const lastName = crudLastNameInput.value.trim();
            const email = crudEmailInput.value.trim();
            const primaryPos = crudPrimaryPosSelect.value;
            const orgPos = crudOrgPosSelect.value;
            const dept = crudDeptSelect.value;
            const job = crudJobSelect.value;
            const isPortalAdmin = crudIsPortalAdminCheckbox.checked ? 1 : 0;

            // ตรวจสอบความถูกต้องของเลขบัตรประชาชน 13 หลัก
            if (primaryPos !== 'ผู้ดูแลระบบ') {
                const numericRegex = /^\d{13}$/;
                if (!numericRegex.test(username)) {
                    crudErrorText.textContent = 'เลขประจำตัวประชาชน (Username) ต้องเป็นตัวเลข 13 หลักเท่านั้น';
                    crudErrorMsg.classList.remove('hidden');
                    crudUsernameInput.focus();
                    return;
                }
                if (userId === '' && !numericRegex.test(password)) {
                    crudErrorText.textContent = 'รหัสผ่านเริ่มต้นต้องเป็นเลขประจำตัวประชาชน 13 หลักเท่านั้น';
                    crudErrorMsg.classList.remove('hidden');
                    crudPasswordInput.focus();
                    return;
                }
            }

            btnSubmitCrud.disabled = true;
            btnSubmitCrud.innerHTML = '⚡ กำลังดำเนินการ...';
            crudErrorMsg.classList.add('hidden');

            const isEditMode = userId !== '';
            const action = isEditMode ? 'update_user_details' : 'create_user';

            const payload = {
                action,
                first_name: firstName,
                last_name: lastName,
                email,
                primary_position: primaryPos,
                org_position: orgPos,
                department: dept,
                job: job,
                is_portal_admin: isPortalAdmin
            };

            if (isEditMode) {
                payload.user_id = parseInt(userId);
            } else {
                payload.username = username;
                payload.password = password;
            }

            try {
                const response = await fetch('api/admin_users.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'เกิดข้อผิดพลาดในการบันทึกข้อมูล');
                }

                closeCrudModal();
                loadAdminUsersTable();

                if (isEditMode && currentUserProfile && currentUserProfile.username === username) {
                    currentUserProfile.first_name = firstName;
                    currentUserProfile.last_name = lastName;
                    currentUserProfile.email = email;
                    currentUserProfile.primary_position = primaryPos;
                    currentUserProfile.org_position = orgPos;
                    currentUserProfile.department = dept;
                    currentUserProfile.job = job;
                    currentUserProfile.is_portal_admin = isPortalAdmin;
                    applyUserSession(token);
                }

            } catch (err) {
                crudErrorText.textContent = err.message;
                crudErrorMsg.classList.remove('hidden');
            } finally {
                btnSubmitCrud.disabled = false;
                btnSubmitCrud.innerHTML = 'บันทึกข้อมูลสมาชิก';
            }
        });
    }

    // ลบบัญชีผู้ใช้งานกลาง
    async function deleteUserAccount(userId, username) {
        const token = localStorage.getItem('pnp-token');
        if (!token) return;

        const confirmMsg = `⚠️ คุณต้องการลบบัญชีผู้ใช้งาน "${username}" และบทบาทการเข้าถึงระบบย่อยทั้งหมดออกจากฐานข้อมูล SQLite ใช่หรือไม่?\nการลบนี้จะไม่สามารถเรียกคืนข้อมูลได้!`;
        
        if (!confirm(confirmMsg)) return;

        try {
            const response = await fetch('api/admin_users.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({
                    action: 'delete_user',
                    user_id: userId
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'ล้มเหลวในการลบบัญชี');
            }

            console.log('ลบผู้ใช้สำเร็จ:', data.message);
            loadAdminUsersTable();

        } catch (err) {
            alert('❌ เกิดข้อผิดพลาด: ' + err.message);
        }
    }

    // API ปรับปรุงสิทธิ์รายระบบ (ดรอปดาวน์)
    async function updateUserAppRole(userId, appId, role) {
        const token = localStorage.getItem('pnp-token');
        if (!token) return;

        try {
            const response = await fetch('api/admin_users.php', {
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
            
            console.log('อัปเดตสิทธิ์สำเร็จ:', data.message);
            
            if (currentUserProfile && currentUserProfile.user_id === userId) {
                currentUserProfile.roles[appId] = role;
                applyUserSession(token);
            }

        } catch (err) {
            alert('❌ เกิดข้อผิดพลาด: ' + err.message);
            loadAdminUsersTable();
        }
    }

    // API สลับสิทธิ์ผู้ดูแลระบบหลัก
    async function togglePortalAdminStatus(userId) {
        const token = localStorage.getItem('pnp-token');
        if (!token) return;

        try {
            const response = await fetch('api/admin_users.php', {
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

            console.log('สลับสถานะแอดมินสำเร็จ:', data.message);

        } catch (err) {
            alert('❌ เกิดข้อผิดพลาด: ' + err.message);
            loadAdminUsersTable();
        }
    }
});
