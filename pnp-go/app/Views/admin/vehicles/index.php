<?php ob_start(); ?>

<?php if ($flash['success']): ?>
    <div class="alert alert-success"><?= e($flash['success']) ?></div>
<?php endif; ?>
<?php if ($flash['error']): ?>
    <div class="alert alert-danger"><?= e($flash['error']) ?></div>
<?php endif; ?>

<div class="page-heading d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">จัดการรถยนต์</h1>
        <p class="text-secondary mb-0">เพิ่ม แก้ไข หรือลบรถยนต์ในระบบ</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        + เพิ่มรถยนต์
    </button>
</div>

<div class="form-section">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>ชื่อรถ</th>
                    <th>ทะเบียน</th>
                    <th>ประเภท</th>
                    <th>น้ำมัน</th>
                    <th>คนขับประจำ</th>
                    <th class="text-center">สถานะ</th>
                    <th class="text-end">จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($vehicles as $i => $v): ?>
                <tr class="<?= !$v['is_active'] ? 'table-secondary opacity-60' : '' ?>">
                    <td class="text-muted"><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= e($v['vehicle_name']) ?></td>
                    <td><span class="badge bg-secondary"><?= e($v['license_plate']) ?></span></td>
                    <td><?= e($v['vehicle_type'] ?? '-') ?></td>
                    <td><?= e($fuelOptions[$v['fuel_type']] ?? $v['fuel_type']) ?></td>
                    <td class="text-muted"><?= e($v['default_driver_name'] ?? '-') ?></td>
                    <td class="text-center">
                        <?php if ($v['is_active']): ?>
                            <span class="badge bg-success">ใช้งาน</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">ปิดใช้งาน</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary me-1"
                            onclick="openEdit(
                                <?= (int)$v['id'] ?>,
                                <?= htmlspecialchars(json_encode($v['vehicle_name']), ENT_QUOTES) ?>,
                                <?= htmlspecialchars(json_encode($v['license_plate']), ENT_QUOTES) ?>,
                                <?= htmlspecialchars(json_encode($v['vehicle_type'] ?? ''), ENT_QUOTES) ?>,
                                <?= htmlspecialchars(json_encode($v['fuel_type']), ENT_QUOTES) ?>,
                                <?= htmlspecialchars(json_encode($v['default_driver_name'] ?? ''), ENT_QUOTES) ?>,
                                <?= htmlspecialchars(json_encode($v['notes'] ?? ''), ENT_QUOTES) ?>,
                                <?= $v['is_active'] ?>
                            )">แก้ไข</button>
                        <form method="post" action="<?= e(config('app')['base_path']) ?>/manage/vehicles/delete" class="d-inline"
                              onsubmit="return confirm('ลบรถ <?= e(addslashes($v['vehicle_name'])) ?> ออกจากระบบ?')">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger">ลบ</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($vehicles)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">ยังไม่มีรถในระบบ</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal เพิ่มรถ -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="<?= e(config('app')['base_path']) ?>/manage/vehicles" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="modal-header">
                <h5 class="modal-title">เพิ่มรถยนต์ใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label required">ชื่อรถ</label>
                    <input type="text" name="vehicle_name" class="form-control" required placeholder="เช่น โตโยต้า รถตู้">
                </div>
                <div class="col-md-6">
                    <label class="form-label required">ทะเบียนรถ</label>
                    <input type="text" name="license_plate" class="form-control" required placeholder="เช่น 7093 กทม">
                </div>
                <div class="col-md-6">
                    <label class="form-label">ประเภทรถ</label>
                    <input type="text" name="vehicle_type" class="form-control" placeholder="รถตู้ / รถหกล้อ / รถเก๋ง">
                </div>
                <div class="col-md-6">
                    <label class="form-label">ประเภทน้ำมัน</label>
                    <select name="fuel_type" class="form-select">
                        <?php foreach ($fuelOptions as $val => $label): ?>
                            <option value="<?= e($val) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">คนขับประจำ</label>
                    <input type="text" name="default_driver_name" class="form-control" placeholder="ชื่อ-นามสกุล">
                </div>
                <div class="col-12">
                    <label class="form-label">หมายเหตุ</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="ข้อมูลเพิ่มเติม..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal แก้ไขรถ -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="<?= e(config('app')['base_path']) ?>/manage/vehicles/update" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header">
                <h5 class="modal-title">แก้ไขข้อมูลรถ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label required">ชื่อรถ</label>
                    <input type="text" name="vehicle_name" id="edit_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">ทะเบียนรถ</label>
                    <input type="text" name="license_plate" id="edit_plate" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">ประเภทรถ</label>
                    <input type="text" name="vehicle_type" id="edit_type" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">ประเภทน้ำมัน</label>
                    <select name="fuel_type" id="edit_fuel" class="form-select">
                        <?php foreach ($fuelOptions as $val => $label): ?>
                            <option value="<?= e($val) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">คนขับประจำ</label>
                    <input type="text" name="default_driver_name" id="edit_driver" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">หมายเหตุ</label>
                    <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="edit_active" class="form-check-input" value="1">
                        <label class="form-check-label" for="edit_active">เปิดใช้งาน</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(id, name, plate, type, fuel, driver, notes, isActive) {
    document.getElementById('edit_id').value     = id;
    document.getElementById('edit_name').value   = name;
    document.getElementById('edit_plate').value  = plate;
    document.getElementById('edit_type').value   = type;
    document.getElementById('edit_driver').value = driver;
    document.getElementById('edit_notes').value  = notes;
    document.getElementById('edit_active').checked = !!isActive;
    const sel = document.getElementById('edit_fuel');
    for (let o of sel.options) o.selected = (o.value === fuel);
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php
$content = ob_get_clean();
require dirname(dirname(__DIR__)) . '/layout.php';
