<?php
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$base = config('app')['base_path'];
?>

<div class="page-heading mb-3">
    <h1>จัดการร้านน้ำมันเชื้อเพลิง</h1>
    <p>ร้านที่ตั้งเป็นค่าเริ่มต้น (Default) จะถูกใช้ในใบสั่งซื้อ PDF อัตโนมัติ</p>
</div>

<?php if ($flash_success): ?>
    <div class="alert alert-success"><?= e($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
    <div class="alert alert-danger"><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="row g-3">
    <!-- รายการร้าน -->
    <div class="col-lg-8">
        <div class="form-section">
            <div class="section-title">รายการร้านน้ำมัน</div>
            <?php if (empty($vendors)): ?>
                <p class="text-muted">ยังไม่มีร้านน้ำมัน</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ชื่อร้าน</th>
                                <th>ที่อยู่</th>
                                <th>โทรศัพท์</th>
                                <th class="text-center">Default</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($vendors as $v): ?>
                            <tr>
                                <td class="fw-semibold">
                                    <?= e($v['name']) ?>
                                    <?php if ($v['is_default']): ?>
                                        <span class="badge bg-primary ms-1">Default</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= e($v['address']) ?></td>
                                <td><?= e($v['phone']) ?></td>
                                <td class="text-center">
                                    <?= $v['is_default'] ? '✅' : '' ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary"
                                        onclick="openEdit(<?= (int)$v['id'] ?>, <?= htmlspecialchars(json_encode($v['name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($v['address']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($v['phone']), ENT_QUOTES) ?>, <?= $v['is_default'] ?>)">
                                        แก้ไข
                                    </button>
                                    <?php if (!$v['is_default']): ?>
                                    <form method="post" action="<?= e($base) ?>/vendors/delete" class="d-inline"
                                          onsubmit="return confirm('ลบร้านนี้?')">
                                        <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger">ลบ</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- เพิ่มร้านใหม่ -->
    <div class="col-lg-4">
        <div class="form-section">
            <div class="section-title">เพิ่มร้านใหม่</div>
            <form method="post" action="<?= e($base) ?>/vendors">
                <div class="mb-3">
                    <label class="form-label required">ชื่อร้าน</label>
                    <input type="text" name="name" class="form-control" required placeholder="เช่น หจก.โพลีพัฒนกิจ">
                </div>
                <div class="mb-3">
                    <label class="form-label">ที่อยู่</label>
                    <input type="text" name="address" class="form-control" placeholder="91 ม.1 ต.สระแก้ว...">
                </div>
                <div class="mb-3">
                    <label class="form-label">โทรศัพท์</label>
                    <input type="text" name="phone" class="form-control" placeholder="043-XXXXXX">
                </div>
                <button type="submit" class="btn btn-primary w-100">เพิ่มร้านน้ำมัน</button>
            </form>
        </div>
    </div>
</div>

<!-- Modal แก้ไข -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="<?= e($base) ?>/vendors/update" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">แก้ไขข้อมูลร้าน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="edit_id">
                <div class="mb-3">
                    <label class="form-label required">ชื่อร้าน</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">ที่อยู่</label>
                    <input type="text" name="address" id="edit_address" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">โทรศัพท์</label>
                    <input type="text" name="phone" id="edit_phone" class="form-control">
                </div>
                <div class="form-check">
                    <input type="checkbox" name="is_default" id="edit_default" class="form-check-input" value="1">
                    <label class="form-check-label" for="edit_default">ตั้งเป็น Default (ใช้ในใบสั่งซื้อ PDF)</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(id, name, address, phone, isDefault) {
    document.getElementById('edit_id').value      = id;
    document.getElementById('edit_name').value    = name;
    document.getElementById('edit_address').value = address;
    document.getElementById('edit_phone').value   = phone;
    document.getElementById('edit_default').checked = !!isDefault;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
