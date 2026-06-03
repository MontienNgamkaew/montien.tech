<?php
$purchaseRequested = ($old['fuel_purchase_requested'] ?? '0') === '1';
$requesterPos = !empty($user['primary_position']) ? $user['primary_position'] : ($user['position_title'] ?? '');
ob_start();
?>
<div class="page-heading">
    <h1 class="h3 mb-1">ยื่นคำขอใช้รถยนต์ / สั่งซื้อน้ำมัน</h1>
    <p class="text-secondary mb-0 small">กรอกข้อมูลให้ครบ ระบบจะออก Tracking ID หลังส่งคำขอ</p>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-danger py-2 px-3 mb-2 small">กรุณาตรวจสอบข้อมูลที่ยังไม่สมบูรณ์</div>
<?php endif; ?>

<form method="post" action="<?= e(config('app')['base_path']) ?>/request" class="d-grid gap-2 form-compact" data-confirm data-confirm-title="ยืนยันการส่งคำขอ" data-confirm-text="กรุณาตรวจสอบข้อมูลให้ถูกต้องก่อนส่งคำขอ" data-confirm-button="ส่งคำขอ">

    <!-- ผู้ขอ (auto-fill จาก SSO) — แถบสรุปบางๆ -->
    <div class="form-section">
        <div class="requester-info">
            <div><span class="ri-label">ผู้ขออนุญาต</span><span class="ri-value"><?= e($user['full_name']) ?></span></div>
            <div><span class="ri-label">ตำแหน่ง</span><span class="ri-value"><?= e($requesterPos) ?></span></div>
        </div>
        <input type="hidden" name="requester_name" value="<?= e($user['full_name']) ?>">
        <input type="hidden" name="requester_position" value="<?= e($requesterPos) ?>">
    </div>

    <section class="form-section">
        <div class="section-title">รายละเอียดการเดินทาง</div>
        <div class="row g-2">
            <div class="col-12">
                <label class="form-label required" for="destination">สถานที่เดินทางไปราชการ</label>
                <input class="form-control <?= isset($errors['destination']) ? 'is-invalid' : '' ?>" id="destination" name="destination" value="<?= e($old['destination'] ?? '') ?>">
                <div class="invalid-feedback"><?= e($errors['destination'] ?? '') ?></div>
            </div>
            <div class="col-4">
                <label class="form-label" for="destination_subdistrict">ตำบล</label>
                <input class="form-control" id="destination_subdistrict" name="destination_subdistrict" value="<?= e($old['destination_subdistrict'] ?? '') ?>">
            </div>
            <div class="col-4">
                <label class="form-label" for="destination_district">อำเภอ</label>
                <input class="form-control" id="destination_district" name="destination_district" value="<?= e($old['destination_district'] ?? '') ?>">
            </div>
            <div class="col-4">
                <label class="form-label" for="destination_province">จังหวัด</label>
                <input class="form-control" id="destination_province" name="destination_province" value="<?= e($old['destination_province'] ?? '') ?>">
            </div>
            <div class="col-6">
                <label class="form-label" for="distance_km">ระยะทางไป-กลับ (กม.)</label>
                <input class="form-control <?= isset($errors['distance_km']) ? 'is-invalid' : '' ?>" id="distance_km" name="distance_km" value="<?= e($old['distance_km'] ?? '') ?>" inputmode="decimal">
                <div class="invalid-feedback"><?= e($errors['distance_km'] ?? '') ?></div>
            </div>
            <div class="col-6">
                <label class="form-label" for="requested_vehicle_id">รถที่ต้องการ</label>
                <select class="form-select" id="requested_vehicle_id" name="requested_vehicle_id">
                    <option value="">ให้พัสดุพิจารณา</option>
                    <?php foreach ($vehicles as $vehicle): ?>
                        <?php $selected = (string) ($old['requested_vehicle_id'] ?? '') === (string) $vehicle['id']; ?>
                        <option value="<?= e((string) $vehicle['id']) ?>" <?= $selected ? 'selected' : '' ?>>
                            <?= e($vehicle['vehicle_name'] . ' - ' . $vehicle['license_plate']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label required" for="travel_start_at">วันเวลาออกเดินทาง</label>
                <input type="datetime-local" class="form-control <?= isset($errors['travel_start_at']) ? 'is-invalid' : '' ?>" id="travel_start_at" name="travel_start_at" value="<?= e($old['travel_start_at'] ?? '') ?>">
                <div class="invalid-feedback"><?= e($errors['travel_start_at'] ?? '') ?></div>
            </div>
            <div class="col-12 col-sm-6">
                <label class="form-label required" for="travel_end_at">วันเวลากลับ</label>
                <input type="datetime-local" class="form-control <?= isset($errors['travel_end_at']) ? 'is-invalid' : '' ?>" id="travel_end_at" name="travel_end_at" value="<?= e($old['travel_end_at'] ?? '') ?>">
                <div class="invalid-feedback"><?= e($errors['travel_end_at'] ?? '') ?></div>
            </div>
            <div class="col-12">
                <label class="form-label required" for="purpose">เรื่อง/ภารกิจ</label>
                <textarea class="form-control <?= isset($errors['purpose']) ? 'is-invalid' : '' ?>" id="purpose" name="purpose" rows="2"><?= e($old['purpose'] ?? '') ?></textarea>
                <div class="invalid-feedback"><?= e($errors['purpose'] ?? '') ?></div>
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="section-title">ผู้ร่วมเดินทาง / สั่งซื้อน้ำมัน</div>
        <div class="row g-2">
            <div class="col-4">
                <label class="form-label" for="passenger_count">ผู้ร่วมเดินทาง (คน)</label>
                <input class="form-control <?= isset($errors['passenger_count']) ? 'is-invalid' : '' ?>" id="passenger_count" name="passenger_count" value="<?= e($old['passenger_count'] ?? '0') ?>" inputmode="numeric">
                <div class="invalid-feedback"><?= e($errors['passenger_count'] ?? '') ?></div>
            </div>
            <div class="col-8">
                <label class="form-label" for="passenger_names">รายชื่อผู้ร่วมเดินทาง</label>
                <input class="form-control" id="passenger_names" name="passenger_names" value="<?= e($old['passenger_names'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label d-block mb-1">ต้องการสั่งซื้อน้ำมันเชื้อเพลิงหรือไม่</label>
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="fuel_purchase_no" name="fuel_purchase_requested" value="0" <?= !$purchaseRequested ? 'checked' : '' ?>>
                        <label class="form-check-label" for="fuel_purchase_no">ไม่สั่งซื้อ</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" id="fuel_purchase_yes" name="fuel_purchase_requested" value="1" <?= $purchaseRequested ? 'checked' : '' ?>>
                        <label class="form-check-label" for="fuel_purchase_yes">สั่งซื้อ</label>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6" id="fuel_purchase_type_group">
                <label class="form-label required" for="fuel_type">ชนิดน้ำมันเชื้อเพลิง</label>
                <select class="form-select <?= isset($errors['fuel_type']) ? 'is-invalid' : '' ?>" id="fuel_type" name="fuel_type">
                    <option value="">เลือกชนิดน้ำมัน</option>
                    <?php foreach ($fuelTypeOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($old['fuel_type'] ?? '') === $value ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback"><?= e($errors['fuel_type'] ?? '') ?></div>
            </div>
            <div class="col-12 col-sm-6" id="fuel_purchase_amount_group">
                <label class="form-label required" for="fuel_total_amount">จำนวนเงิน (บาท)</label>
                <input class="form-control <?= isset($errors['fuel_total_amount']) ? 'is-invalid' : '' ?>" id="fuel_total_amount" name="fuel_total_amount" value="<?= e($old['fuel_total_amount'] ?? '') ?>" inputmode="decimal">
                <div class="invalid-feedback"><?= e($errors['fuel_total_amount'] ?? '') ?></div>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <a class="btn btn-outline-secondary" href="<?= e(config('app')['base_path']) ?>/">ยกเลิก</a>
        <button class="btn btn-primary" type="submit">ส่งคำขอ</button>
    </div>
</form>

<script>
    const purchaseRadios = document.querySelectorAll('input[name="fuel_purchase_requested"]');
    const fuelGroups = [
        { group: 'fuel_purchase_type_group',   input: 'fuel_type' },
        { group: 'fuel_purchase_amount_group', input: 'fuel_total_amount' },
    ].map((o) => ({ group: document.getElementById(o.group), input: document.getElementById(o.input) }));

    function toggleFuelAmount() {
        const shouldShow = document.getElementById('fuel_purchase_yes').checked;
        fuelGroups.forEach(({ group, input }) => {
            if (!group || !input) return;
            group.classList.toggle('d-none', !shouldShow);
            input.disabled = !shouldShow;
            if (!shouldShow) {
                input.value = '';
            }
        });
    }

    purchaseRadios.forEach((radio) => radio.addEventListener('change', toggleFuelAmount));
    toggleFuelAmount();
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
