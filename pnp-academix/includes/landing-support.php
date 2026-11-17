<?php
declare(strict_types=1);
/** Database DATETIME uses the application's Asia/Bangkok timezone. */
function ac_deadline_epoch(?string $value):?int{
 if(!$value)return null;
 $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('Asia/Bangkok'));
 $errors=DateTimeImmutable::getLastErrors();
 if(!$date||($errors&&($errors['warning_count']||$errors['error_count']))||$date->format('Y-m-d H:i:s')!==$value)return null;
 return $date->getTimestamp()*1000;
}
/** Original inline SVG; each instance has unique gradient IDs and local CSS animation. */
function ac_working_cat(string $id):void{
 $id=htmlspecialchars($id,ENT_QUOTES,'UTF-8');
 ?>
 <svg class="nl-cat-scene" viewBox="0 0 560 410" role="img" aria-label="แมวนั่งทำงานที่โต๊ะคอมพิวเตอร์">
 <defs>
  <linearGradient id="<?= $id ?>-fur" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#ffd797"/><stop offset="1" stop-color="#e99d52"/></linearGradient>
  <linearGradient id="<?= $id ?>-desk" x1="0" x2="1"><stop stop-color="#8473f1"/><stop offset=".55" stop-color="#526dc9"/><stop offset="1" stop-color="#40c8c9"/></linearGradient>
  <linearGradient id="<?= $id ?>-screen" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#182746"/><stop offset="1" stop-color="#102c3d"/></linearGradient>
 </defs>
 <ellipse cx="285" cy="385" rx="221" ry="17" fill="#050b1f" opacity=".6"/>
 <g class="cat-room" fill="none" stroke="#8174de" opacity=".26"><rect x="82" y="33" width="397" height="296" rx="29"/><path d="M109 62h62m249 0h30M109 85h25" stroke-linecap="round" stroke-width="3"/><circle cx="454" cy="83" r="4"/></g>
 <g class="cat-chair"><rect x="131" y="187" width="123" height="149" rx="40" fill="#46426d" stroke="#6a6298" stroke-width="3"/><path d="M185 330v39m-28 8 28-8 28 8" stroke="#726c9b" stroke-width="9" stroke-linecap="round"/></g>
 <path class="cat-tail" d="M168 280C108 305 77 271 91 244C100 226 122 238 108 251" fill="none" stroke="#e5a85d" stroke-width="20" stroke-linecap="round"/>
 <g class="cat-body"><path d="M166 199Q209 174 247 207L263 301H151Z" fill="url(#<?= $id ?>-fur)" stroke="#cf8b52" stroke-width="2"/><ellipse cx="207" cy="251" rx="38" ry="47" fill="#ffe5b6"/><path d="m174 209 13 11m52-13-12 12" stroke="#d48e4b" stroke-width="6" stroke-linecap="round"/></g>
 <g class="cat-head">
  <path d="M148 140Q142 114 155 96L150 59Q151 49 162 57L190 80Q211 76 231 83L259 61Q269 56 267 70L262 111Q272 129 268 150Q269 190 208 195Q151 191 148 154Z" fill="url(#<?= $id ?>-fur)" stroke="#dba05e" stroke-width="2.5"/>
  <path d="m160 74 5 28 20-13Zm79 20 17-20-2 30Z" fill="#e9969a"/>
  <path d="m195 84 3 16m13-19 1 16m12-13-1 16" stroke="#c98d4a" stroke-width="5" stroke-linecap="round"/>
  <ellipse cx="210" cy="159" rx="39" ry="25" fill="#ffe8c8"/>
  <g class="cat-eyes-work"><g class="cat-blink"><ellipse cx="181" cy="134" rx="7" ry="9" fill="#443450"/><ellipse cx="239" cy="134" rx="7" ry="9" fill="#443450"/><circle cx="183" cy="131" r="2" fill="#fff4dc"/><circle cx="241" cy="131" r="2" fill="#fff4dc"/></g></g>
  <g class="cat-eyes-dizzy" fill="none" stroke="#59415c" stroke-width="3" stroke-linecap="round"><path d="M188 134c0-12-20-12-20 0s16 12 16 2-11-8-11-1 7 5 7 1m66-2c0-12-20-12-20 0s16 12 16 2-11-8-11-1 7 5 7 1"/></g>
  <g class="cat-eyes-rest" fill="none" stroke="#59415c" stroke-width="3" stroke-linecap="round"><path d="M173 135q8 8 16 0m42 0q8 8 16 0"/></g>
  <path d="m204 151 12 0-6 7Z" fill="#bc7183"/><path d="M210 158v7m-9 0q5 7 9 0 5 7 10 0" fill="none" stroke="#9b6d72" stroke-width="2.5" stroke-linecap="round"/>
  <g stroke="#ffeacf" stroke-width="2.5" stroke-linecap="round"><path d="m172 155-29-6m29 14-31 3m103-10 30-6m-30 15 29 5"/></g>
  <g class="cat-sweat"><path d="M264 107q-13 17 0 20 13-3 0-20Z" fill="#7de9f6"/></g>
 </g>
 <g class="cat-dizzy-stars" fill="#f6cd84"><path d="m175 39 3-7 3 7 8 1-6 5 1 8-6-4-7 4 2-8-6-5Z"/><path d="m236 28 2-6 3 6 6 1-5 4 1 6-5-3-5 3 1-6-5-4Z"/><path d="M278 49h7m-3-4v8" stroke="#c0a2ff" stroke-width="3" stroke-linecap="round"/><ellipse cx="220" cy="43" rx="62" ry="17" fill="none" stroke="#bb9cf4" stroke-width="2" stroke-dasharray="8 11"/></g>
 <g class="cat-monitor"><rect x="300" y="104" width="179" height="139" rx="13" fill="#63728f" stroke="#93a5c3" stroke-width="2"/><rect x="308" y="112" width="163" height="118" rx="7" fill="url(#<?= $id ?>-screen)"/><path d="M374 244v29m-23 3h49" stroke="#8094b1" stroke-width="9" stroke-linecap="round"/>
  <g class="cat-code" fill="none" stroke-width="5" stroke-linecap="round"><path d="M326 141h35m-35 14h17m12 0h55m-84 14h63m-51 14h52m-64 14h18" stroke="#91a5ef"/><path d="M374 141h26m-42 56h40m-60 14h56" stroke="#67d7c8"/></g><circle class="cat-cursor" cx="412" cy="211" r="3" fill="#b39bff"/><text class="cat-screen-label" x="444" y="129" text-anchor="end" fill="#8d9ebd" font-size="7" font-family="Tahoma,sans-serif">ACADEMIX</text>
 </g>
 <g class="cat-desk"><path d="M104 320v58m340-58v58" stroke="#665d96" stroke-width="12" stroke-linecap="round"/><rect x="77" y="301" width="431" height="21" rx="8" fill="#3b395e" stroke="#7d79a4" stroke-width="2"/><path d="M85 304h415" stroke="url(#<?= $id ?>-desk)" stroke-width="4" stroke-linecap="round"/>
 <rect x="269" y="282" width="157" height="17" rx="6" fill="#8a8fab" stroke="#c0c6de" stroke-width="1.5"/><path d="M281 288h131m-119 6h106" stroke="#c1c7e1" stroke-width="2" stroke-dasharray="5 4"/><path d="M112 282h44v14h-44Z" fill="#b3aed3"/><path d="M117 278h43v14h-43Z" fill="#d5ccef"/>
 <path d="M451 267h22v25q0 9-11 9t-11-9Z" fill="#bd94be"/><path d="M473 271q18-1 9 15h-9" fill="none" stroke="#d4b5da" stroke-width="4"/><g class="cat-steam" fill="none" stroke="#cab0ed" stroke-width="2" stroke-linecap="round"><path d="M457 257q-6-6 0-12m10 12q6-6 0-12"/></g></g>
 <g class="cat-paw-left"><path d="M177 222Q202 251 293 278" fill="none" stroke="#eeb46e" stroke-width="20" stroke-linecap="round"/><ellipse cx="294" cy="278" rx="17" ry="10" fill="#ffd797"/><path d="m291 281 1 4m6-4v4" stroke="#cf9357" stroke-width="1.5" stroke-linecap="round"/></g>
 <g class="cat-paw-right"><path d="M237 221Q274 243 333 277" fill="none" stroke="#e9ab65" stroke-width="19" stroke-linecap="round"/><ellipse cx="335" cy="278" rx="17" ry="10" fill="#ffda9f"/><path d="m333 281v4m6-4v4" stroke="#cf9357" stroke-width="1.5" stroke-linecap="round"/></g>
 </svg>
 <?php
}
