<?php ob_start(); ?>
<div class="page-heading d-flex flex-wrap gap-2 justify-content-between align-items-start">
    <div>
        <h1 class="h3 mb-1">ตั้งค่าระบบ (System Settings)</h1>
        <p class="text-secondary mb-0">ปรับแต่งชื่อระบบและโทนสีของแอปพลิเคชันให้เป็นเนื้อเดียวกัน</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="<?= e(config('app')['base_path']) ?>/dashboard">🏠 กลับแดชบอร์ดหลัก</a>
    </div>
</div>

<?php if (isset($_GET['saved']) && $_GET['saved'] === 'success'): ?>
    <div class="alert alert-success border border-success-subtle rounded-3 mb-4 p-3 shadow-sm d-flex align-items-center gap-2" role="alert">
        <span style="font-size: 20px;">✅</span>
        <div>
            <strong>บันทึกข้อมูลสำเร็จ!</strong> โทนสีและชื่อระบบขอรถได้รับการอัปเดตใหม่เรียบร้อยแล้ว
        </div>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <section class="form-section shadow-sm">
            <h2 class="section-title mb-4">🔧 แผงควบคุมดีไซน์และข้อมูลองค์กร</h2>
            
            <form method="post" action="<?= e(config('app')['base_path']) ?>/dashboard/settings">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                
                <!-- 1. System Name Setting -->
                <div class="mb-4">
                    <label for="system_name" class="form-label fw-semibold">ชื่อระบบยานพาหนะย่อย (Sub-system Name)</label>
                    <input type="text" class="form-control form-control-lg <?= isset($errors['system_name']) ? 'is-invalid' : '' ?>" id="system_name" name="system_name" value="<?= e($settings['system_name'] ?? 'PNP Go') ?>" placeholder="เช่น PNP Go หรือ ระบบขอใช้รถยนต์ส่วนกลาง">
                    <?php if (isset($errors['system_name'])): ?>
                        <div class="invalid-feedback"><?= e($errors['system_name']) ?></div>
                    <?php endif; ?>
                    <div class="form-text text-muted">ชื่อนี้จะปรากฏที่โลโก้แถบนำทาง (Navbar) และหัวเรื่องแบนเนอร์หน้าแรกของระบบขอจองรถยนต์</div>
                </div>

                <!-- 2. Theme Picker Settings -->
                <div class="mb-5">
                    <label class="form-label fw-semibold mb-3">ธีมโทนสีของแอปพลิเคชัน (Color Theme Preset)</label>
                    
                    <div class="row g-3">
                        <?php
                        $themes = [
                            'rose' => ['name' => 'Rose (แดงกุหลาบ)', 'color1' => '#8b1a2b', 'color2' => '#e8a0a0', 'desc' => 'ธีมกุหลาบแดงดั้งเดิมตามแบบฉบับ PNP Man'],
                            'indigo' => ['name' => 'Indigo (ครามไพล)', 'color1' => '#4f46e5', 'color2' => '#c7d2fe', 'desc' => 'สีน้ำเงินครามเข้ม หรูหรา มั่นคงเป็นระเบียบ'],
                            'emerald' => ['name' => 'Emerald (มรกต)', 'color1' => '#059669', 'color2' => '#a7f3d0', 'desc' => 'สีเขียวมรกตสุขุม มอบความสบายตาระดับสากล'],
                            'sky' => ['name' => 'Sky (ฟ้าสว่าง)', 'color1' => '#0284c7', 'color2' => '#bae6fd', 'desc' => 'สีฟ้าสว่างใส ปลุกความสดใหม่ ทันสมัย เข้าถึงง่าย'],
                            'amber' => ['name' => 'Amber (อำพันทอง)', 'color1' => '#d97706', 'color2' => '#fde68a', 'desc' => 'สีเหลืองอำพันประกายทอง หรูหรา สง่างาม พรีเมียม'],
                            'slate' => ['name' => 'Slate (เทาสุภาพ)', 'color1' => '#475569', 'color2' => '#cbd5e1', 'desc' => 'สีเทาโมเดิร์น สุภาพ สุขุม เรียบง่ายแบบทางการ']
                        ];
                        
                        $activeTheme = $settings['theme_color'] ?? 'rose';
                        
                        foreach ($themes as $key => $t):
                            $checked = ($key === $activeTheme) ? 'checked' : '';
                        ?>
                            <div class="col-md-6">
                                <label class="card h-100 p-3 shadow-none border rounded-3 style-choice-card" style="cursor: pointer; position: relative; transition: all 0.2s ease;">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <input class="form-check-input" type="radio" name="theme_color" value="<?= e($key) ?>" <?= $checked ?> style="width: 20px; height: 20px;">
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <span class="fw-bold text-dark"><?= e($t['name']) ?></span>
                                                <div class="d-flex gap-1">
                                                    <span class="d-inline-block rounded-circle" style="width: 14px; height: 14px; background-color: <?= $t['color1'] ?>;"></span>
                                                    <span class="d-inline-block rounded-circle" style="width: 14px; height: 14px; background-color: <?= $t['color2'] ?>;"></span>
                                                </div>
                                            </div>
                                            <small class="text-muted d-block" style="font-size: 11.5px; line-height: 1.3;"><?= e($t['desc']) ?></small>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <style>
                .style-choice-card:hover {
                    border-color: var(--theme-primary) !important;
                    background-color: rgba(255, 255, 255, 0.95);
                    transform: translateY(-2px);
                    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.04) !important;
                }
                .style-choice-card:has(input:checked) {
                    border-color: var(--theme-primary) !important;
                    background-color: rgba(var(--theme-primary), 0.03);
                    box-shadow: 0 4px 12px rgba(var(--theme-primary), 0.05) !important;
                }
                </style>

                <!-- Submit Button -->
                <div class="border-top pt-4 text-end">
                    <button type="submit" class="btn btn-lg btn-primary btn-theme px-5 py-2.5 rounded-3 fw-bold shadow-sm" style="font-size: 16px;">
                        💾 บันทึกการตั้งค่าระบบย่อย
                    </button>
                </div>
            </form>
        </section>
    </div>
</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
?>
