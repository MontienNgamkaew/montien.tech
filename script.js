/* -------------------------------------------------------------
 * JAVASCRIPT LOGIC: PNP UNIVERSAL PORTAL
 * Features: Dynamic Greeting, Live Clock, Particle Background, Theme Toggle
 * ------------------------------------------------------------- */

document.addEventListener('DOMContentLoaded', () => {
    
    // ==========================================
    // 1. DYNAMIC TIME-OF-DAY GREETING (ภาษาไทย)
    // ==========================================
    const greetingElement = document.getElementById('dynamic-greeting');
    
    function updateGreeting() {
        const now = new Date();
        const hours = now.getHours();
        let greetingText = '';

        if (hours >= 5 && hours < 12) {
            greetingText = 'สวัสดีตอนเช้าครับ ☀️';
        } else if (hours >= 12 && hours < 17) {
            greetingText = 'สวัสดีตอนบ่ายครับ 🌤️';
        } else if (hours >= 17 && hours < 21) {
            greetingText = 'สวัสดีตอนเย็นครับ 🌅';
        } else {
            greetingText = 'สวัสดีตอนค่ำครับ 🌙';
        }

        if (greetingElement) {
            greetingElement.textContent = greetingText;
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

    // Load saved theme or default to dark-theme
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
        const numberOfParticles = 35; // Lower count for buttery-smooth performance
        
        // Resize canvas to full screen
        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        // Particle constructor
        class Particle {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.size = Math.random() * 3 + 1; // 1px to 4px
                this.speedX = Math.random() * 0.4 - 0.2; // Very slow drift
                this.speedY = Math.random() * 0.4 - 0.2;
                this.alpha = Math.random() * 0.5 + 0.1;
                this.alphaChange = Math.random() * 0.005 + 0.002;
            }

            update() {
                this.x += this.speedX;
                this.y += this.speedY;

                // Infinite wrapping around screen
                if (this.x < 0) this.x = canvas.width;
                if (this.x > canvas.width) this.x = 0;
                if (this.y < 0) this.y = canvas.height;
                if (this.y > canvas.height) this.y = 0;

                // Dynamic fading in and out
                this.alpha += this.alphaChange;
                if (this.alpha > 0.7 || this.alpha < 0.1) {
                    this.alphaChange = -this.alphaChange;
                }
            }

            draw() {
                // Adjust colors dynamically based on light/dark theme
                const isDark = body.classList.contains('dark-theme');
                const hue = isDark ? 200 : 260; // Cyber blue particles in dark, purple in light
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
});
