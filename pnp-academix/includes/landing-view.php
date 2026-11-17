<?php
declare(strict_types=1);
require_once __DIR__.'/landing-support.php';
$canSelf=!pnp_portal_v2()||pnp_portal_can('pnp-academic','view','self');
$canAdmin=pnp_portal_v2()?pnp_portal_can('pnp-academic','view','admin'):current_user_role()==='admin';
ac_start('Academix · พื้นที่งานวิชาการ',$branding,false,true);ac_header($branding,'home');
?>
<main class="ac-main nl-main" id="ac-main" data-server-now="<?= (int)round(microtime(true)*1000) ?>">
 <section class="nl-hero" aria-labelledby="nl-title">
  <div class="nl-hero-copy"><span class="nl-eyebrow"><i aria-hidden="true"></i> PNP ACADEMIX · YOUR ACADEMIC SPACE</span><span class="nl-term">ภาคเรียน <?= ac_h($semester['semester_name']??'ยังไม่เปิดภาคเรียน') ?></span><h1 id="nl-title">เปลี่ยนทุกงานส่ง<br>ให้เป็น<span>ความก้าวหน้า</span></h1><p class="nl-intro">โครงการสอน แผนการสอน และสื่อการสอน<br>วางแผนให้ทัน แล้วให้เจ้าเหมียวลุยไปด้วยกัน</p>
   <div class="nl-hero-actions"><?php if($canSelf): ?><a class="nl-button primary" href="teacher/dashboard.php">เข้าสู่งานของฉัน <span aria-hidden="true">→</span></a><?php endif; ?><?php if($canAdmin): ?><a class="nl-button secondary" href="admin/overview.php">Dashboard ผู้ดูแล ↗</a><?php endif; ?></div>
   <div class="nl-next-mission"><span class="nl-next-dot" aria-hidden="true"></span><div><span>ภารกิจของเจ้าเหมียว</span><strong id="nl-mission-title">กำลังตรวจสอบกำหนดส่ง</strong><p id="nl-mission-note" aria-live="polite">เวลาอ้างอิงจากระบบ · เขตเวลาไทย</p></div></div>
  </div>
  <div class="nl-hero-scene"><span class="nl-scene-tag">ACADEMIX NIGHT SHIFT <i aria-hidden="true"></i></span><div class="nl-orbit orbit-one" aria-hidden="true"></div><div class="nl-orbit orbit-two" aria-hidden="true"></div><div class="nl-mascot nl-mascot-main" id="nl-main-cat" data-mascot data-mood="rest"><?php ac_working_cat('nl-main'); ?></div><div class="nl-cat-caption"><span id="nl-cat-mood">พร้อมลุยงานไปด้วยกัน</span><button type="button" id="nl-motion-toggle" aria-pressed="false">พักแอนิเมชัน</button></div></div>
 </section>
 <section class="nl-deadlines" aria-labelledby="nl-deadlines-title"><div class="nl-section-heading"><div><p class="nl-eyebrow">THE COUNTDOWN IS ON</p><h2 id="nl-deadlines-title">เช็กเวลา แล้วลุยให้ทัน</h2></div><p>ยิ่งใกล้กำหนดส่ง เจ้าเหมียวยิ่งเร่งมือ</p></div>
 <div class="nl-deadline-grid">
 <?php foreach(AC_DOCUMENT_TYPES as $key=>$type):$setting=$systemSettings[$key]??null;$deadline=ac_deadline_epoch($setting['deadline_date']??null);$open=$setting&&(int)$setting['is_open']===1; ?>
  <article class="nl-deadline-card nl-type-<?= $key ?>" data-deadline-card data-key="<?= $key ?>" data-title="<?= ac_h($type['title']) ?>" data-deadline="<?= $deadline??'' ?>" data-open="<?= $open?'1':'0' ?>" data-configured="<?= $setting?'1':'0' ?>">
   <header><span class="nl-document-number">MISSION <?= $type['number'] ?></span><span class="nl-admission <?= $open?'open':'closed' ?>"><?= $setting?($open?'เปิดรับงาน':'ปิดรับงาน'):'ยังไม่เปิดรอบส่ง' ?></span></header><h3><?= $type['title'] ?></h3><p class="nl-rule"><?= $type['rule'] ?></p>
   <div class="nl-countdown" role="timer" aria-label="เวลาที่เหลือสำหรับ<?= ac_h($type['title']) ?>"><div><strong data-unit="days">—</strong><span>วัน</span></div><b aria-hidden="true">:</b><div><strong data-unit="hours">—</strong><span>ชั่วโมง</span></div><b aria-hidden="true">:</b><div><strong data-unit="minutes">—</strong><span>นาที</span></div><b aria-hidden="true">:</b><div><strong data-unit="seconds">—</strong><span>วินาที</span></div></div>
   <div class="nl-deadline-bottom"><div><p class="nl-due-label">กำหนดส่ง · เวลาไทย</p><time <?= $deadline!==null?'datetime="'.ac_h((new DateTimeImmutable('@'.(int)($deadline/1000)))->setTimezone(new DateTimeZone('Asia/Bangkok'))->format(DATE_ATOM)).'"':'' ?>><?= $deadline!==null?ac_h(ac_date($setting['deadline_date'])):'ยังไม่กำหนดเวลา' ?></time><p class="nl-timer-status" data-timer-status aria-live="polite">กำลังคำนวณเวลาที่เหลือ</p></div><div class="nl-mascot nl-mascot-small" data-mascot data-mood="rest"><?php ac_working_cat('nl-'.$key); ?></div></div>
   <p class="nl-expiry-note" data-expiry-note hidden>เลยกำหนดแล้ว การรับงานล่าช้าเป็นไปตามการตั้งค่าเดิมของระบบ</p>
  </article>
 <?php endforeach; ?>
 </div><p class="nl-countdown-note">นับถอยหลังจากกำหนดส่งเดิมของระบบ การเปิด–ปิดรับงานและสิทธิ์ส่งเอกสารยังเป็นไปตามเงื่อนไขเดิม</p><noscript><p class="nl-countdown-note">เปิด JavaScript เพื่อแสดงเวลาถอยหลังและแอนิเมชัน กำหนดส่งด้านบนและลิงก์พื้นที่ทำงานยังใช้งานได้</p></noscript>
 </section>
 <section class="nl-workspace"><div class="nl-workspace-icon" aria-hidden="true">✦</div><div><h2>งานของคุณ อยู่ในพื้นที่เดียว</h2><p>ดูสถานะทั้ง 3 ประเภท เปิด Preview และติดตามข้อเสนอแนะจากผู้ตรวจ</p></div><?php if($canSelf): ?><a class="nl-button secondary" href="teacher/dashboard.php">ดูสถานะการส่งงาน →</a><?php elseif($canAdmin): ?><a class="nl-button secondary" href="admin/overview.php">ดูภาพรวมการส่งงาน →</a><?php endif; ?></section>
</main><?php ac_end($branding); ?>
