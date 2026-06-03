<?php ob_start(); ?>
<div class="page-heading">
    <h1 class="h3 mb-1">📝 รายงานการใช้รถหลังเดินทาง</h1>
    <p class="text-secondary mb-0">บันทึกเลขไมล์และแนบภาพถ่ายหลังเสร็จสิ้นภารกิจ</p>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger">กรุณาตรวจสอบข้อมูลที่ยังไม่สมบูรณ์</div>
<?php endif; ?>

<div class="row g-3">
    <!-- ข้อมูลคำขอสรุปเพื่ออ้างอิง -->
    <div class="col-lg-4">
        <section class="form-section h-100">
            <h2 class="section-title">สรุปข้อมูลการเดินทาง</h2>
            <div class="mb-3">
                <span class="text-secondary small d-block">Tracking ID</span>
                <span class="fw-bold"><?= e($requisition['tracking_id']) ?></span>
            </div>
            <div class="mb-3">
                <span class="text-secondary small d-block">ผู้ขอ/ตำแหน่ง</span>
                <span><?= e($requisition['requester_name']) ?> (<?= e($requisition['requester_position']) ?>)</span>
            </div>
            <div class="mb-3">
                <span class="text-secondary small d-block">สถานที่ไปราชการ</span>
                <span><?= e($requisition['destination']) ?></span>
            </div>
            <div class="mb-3">
                <span class="text-secondary small d-block">รถยนต์ที่ใช้</span>
                <span class="fw-semibold text-primary"><?= e($requisition['vehicle_name']) ?> - <?= e($requisition['license_plate']) ?></span>
            </div>
            <div class="mb-3">
                <span class="text-secondary small d-block">พนักงานขับรถ</span>
                <span><?= e($requisition['assigned_driver_name'] ?: 'เดินทางเอง') ?></span>
            </div>
        </section>
    </div>

    <!-- ฟอร์มรายงานเลขไมล์และแนบภาพ -->
    <div class="col-lg-8">
        <section class="form-section">
            <h2 class="section-title">ข้อมูลเลขไมล์และหลักฐานรูปภาพ</h2>
            <form method="post" action="<?= e(config('app')['base_path']) ?>/report/submit" enctype="multipart/form-data" class="d-grid gap-3" data-confirm data-confirm-title="ยืนยันการส่งรายงาน" data-confirm-text="กรุณาตรวจสอบข้อมูลเลขไมล์ให้ถูกต้องก่อนยืนยัน" data-confirm-button="ส่งรายงาน">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= e($requisition['id']) ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required" for="odometer_before">เลขไมล์ก่อนเดินทาง (Odometer Start)</label>
                        <input type="number" class="form-control form-control-lg <?= isset($errors['odometer_before']) ? 'is-invalid' : '' ?>" id="odometer_before" name="odometer_before" value="<?= e($old['odometer_before'] ?? '') ?>" placeholder="ตัวเลข เช่น 120500" required inputmode="numeric">
                        <div class="invalid-feedback"><?= e($errors['odometer_before'] ?? '') ?></div>
                        <span class="text-secondary small">ตรวจสอบตัวเลขเลขไมล์เมื่อเริ่มรับกุญแจรถยนต์</span>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required" for="odometer_after">เลขไมล์หลังเดินทาง (Odometer End)</label>
                        <input type="number" class="form-control form-control-lg <?= isset($errors['odometer_after']) ? 'is-invalid' : '' ?>" id="odometer_after" name="odometer_after" value="<?= e($old['odometer_after'] ?? '') ?>" placeholder="ตัวเลข เช่น 120650" required inputmode="numeric">
                        <div class="invalid-feedback"><?= e($errors['odometer_after'] ?? '') ?></div>
                        <span class="text-secondary small">ระบุตัวเลขเลขไมล์เมื่อเดินทางกลับถึงวิทยาลัย</span>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label required" for="report_photo">📸 รูปภาพที่ 1 (หน้าปัดไมล์ / สภาพรถ)</label>
                        <input type="file" class="form-control <?= isset($errors['report_photo']) ? 'is-invalid' : '' ?>" id="report_photo" name="report_photo" accept="image/png, image/jpeg, image/jpg" required>
                        <div class="invalid-feedback"><?= e($errors['report_photo'] ?? '') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="report_photo_2">📸 รูปภาพที่ 2 <span class="text-muted fw-normal">(ถ้ามี)</span></label>
                        <input type="file" class="form-control <?= isset($errors['report_photo_2']) ? 'is-invalid' : '' ?>" id="report_photo_2" name="report_photo_2" accept="image/png, image/jpeg, image/jpg">
                        <div class="invalid-feedback"><?= e($errors['report_photo_2'] ?? '') ?></div>
                    </div>
                    <div class="col-12">
                        <span class="text-secondary small">แนบได้ 1-2 รูป • รองรับ .png, .jpg, .jpeg ขนาดสูงสุด 5MB ต่อรูป</span>
                    </div>
                </div>

                <hr class="my-2">

                <div class="d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary" href="<?= e(config('app')['base_path']) ?>/status?id=<?= urlencode($requisition['id']) ?>">ยกเลิก</a>
                    <button class="btn btn-primary px-4" type="submit">ส่งรายงานผล</button>
                </div>
            </form>
        </section>
    </div>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
