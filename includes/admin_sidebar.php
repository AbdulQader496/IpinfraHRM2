<?php
$_cur = basename($_SERVER['PHP_SELF']);

$_admin_nav = [
    ['dashboard.php',     'fa-home',                'Dashboard',  'overview'],
    ['employees.php',     'fa-users',               'Employees',  'people'],
    ['manage_leave.php',  'fa-calendar-check',      'Leave',      'people'],
    ['manage_claim.php',  'fa-receipt',             'Claims',     'people'],
    ['attendance.php',    'fa-fingerprint',         'Attendance', 'people'],
    ['manage_assets.php', 'fa-boxes',               'Assets',     'resources'],
    ['manage_gallery.php','fa-images',              'Gallery',    'resources'],
    ['management.php',    'fa-briefcase',           'Management', 'ops'],
    ['payroll.php',       'fa-file-invoice-dollar', 'Payroll',    'ops'],
    ['holidays.php',      'fa-calendar-alt',        'Holidays',   'ops'],
    ['audit_log.php',     'fa-shield-alt',          'Audit Log',  'system'],
];

$_sections = ['overview'=>'Overview','people'=>'People','resources'=>'Resources','ops'=>'Operations','system'=>'System'];

$_ic = [
    'fa-home'                => ['#93c5fd','rgba(147,197,253,.18)'],
    'fa-users'               => ['#c4b5fd','rgba(196,181,253,.18)'],
    'fa-calendar-check'      => ['#6ee7b7','rgba(110,231,183,.18)'],
    'fa-receipt'             => ['#fcd34d','rgba(252,211,77,.18)'],
    'fa-fingerprint'         => ['#67e8f9','rgba(103,232,249,.18)'],
    'fa-boxes'               => ['#fdba74','rgba(253,186,116,.18)'],
    'fa-images'              => ['#f9a8d4','rgba(249,168,212,.18)'],
    'fa-briefcase'           => ['#a5b4fc','rgba(165,180,252,.18)'],
    'fa-file-invoice-dollar' => ['#86efac','rgba(134,239,172,.18)'],
    'fa-calendar-alt'        => ['#fda4af','rgba(253,164,175,.18)'],
    'fa-shield-alt'          => ['#cbd5e1','rgba(203,213,225,.18)'],
];
$_ps = '';
?>
<style>
/* ── Page background ──────────────────────────────────── */
body {
    background-color: #f1f5fb !important;
    background-image:
        radial-gradient(ellipse 65% 40% at 8% -5%,  rgba(99,102,241,.07) 0%, transparent 55%),
        radial-gradient(ellipse 55% 35% at 90% 108%, rgba(139,92,246,.05) 0%, transparent 50%) !important;
    min-height: 100vh;
}
:not(#asb) ::-webkit-scrollbar { width:5px; height:5px; }
:not(#asb) ::-webkit-scrollbar-track { background:transparent; }
:not(#asb) ::-webkit-scrollbar-thumb { background:#d1d5db; border-radius:4px; }
:not(#asb) ::-webkit-scrollbar-thumb:hover { background:#9ca3af; }
body.asb-open { overflow:hidden; }

/* ── Admin Sidebar ─────────────────────────────────────── */
#asb {
    background: linear-gradient(175deg, #0e1f4a 0%, #11255a 45%, #0d1c45 100%);
    background-image:
        radial-gradient(ellipse 200% 50% at 50% -8%, rgba(99,102,241,.45) 0%, transparent 55%),
        radial-gradient(ellipse 100% 50% at 95% 85%, rgba(139,92,246,.2)  0%, transparent 50%),
        linear-gradient(175deg, #0e1f4a 0%, #11255a 45%, #0d1c45 100%);
    border-right: 1px solid rgba(255,255,255,.1);
}
#asb::-webkit-scrollbar { width:3px; }
#asb::-webkit-scrollbar-track { background:transparent; }
#asb::-webkit-scrollbar-thumb { background:rgba(165,180,252,.35); border-radius:3px; }

.asb-link {
    display:flex; align-items:center; gap:.7rem;
    padding:.72rem .85rem; border-radius:.75rem;
    font-size:.825rem; font-weight:500; line-height:1;
    text-decoration:none; transition:all .17s ease;
    color:rgba(255,255,255,.55); border:1px solid transparent;
    min-height:2.75rem;
}
.asb-link:hover { background:rgba(255,255,255,.1); color:rgba(255,255,255,.9); }
.asb-link.on {
    background:rgba(99,102,241,.3);
    border-color:rgba(165,180,252,.35);
    color:#e0e7ff;
    box-shadow:0 2px 20px rgba(99,102,241,.3), inset 0 1px 0 rgba(255,255,255,.12);
}
.asb-link.on .asb-ico { background:rgba(255,255,255,.2) !important; color:#e0e7ff !important; }
.asb-ico {
    width:2rem; height:2rem; border-radius:.55rem;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0; font-size:.75rem; transition:all .17s;
}
.asb-sec { display:flex; align-items:center; gap:.6rem; padding:.15rem .85rem .1rem; margin-top:.9rem; }
.asb-sec:first-child { margin-top:.25rem; }
.asb-sec-lbl { font-size:.62rem; font-weight:700; letter-spacing:.15em; text-transform:uppercase; color:rgba(255,255,255,.3); white-space:nowrap; }
.asb-sec-line { flex:1; height:1px; background:rgba(255,255,255,.1); }
.asb-close { width:2rem; height:2rem; border-radius:.5rem; border:none; cursor:pointer; background:none; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,.4); transition:all .15s; flex-shrink:0; }
.asb-close:hover { background:rgba(255,255,255,.12); color:rgba(255,255,255,.85); }
</style>

<div id="asb" class="fixed top-0 left-0 h-full z-50 -translate-x-full transition-[transform] duration-300 ease-out flex flex-col overflow-hidden" style="width:min(280px,85vw);font-family:'Inter',sans-serif;box-shadow:6px 0 40px rgba(0,0,0,.55)">

    <!-- Brand -->
    <div class="flex items-center gap-3 px-4 shrink-0" style="padding-top:20px;padding-bottom:18px;border-bottom:1px solid rgba(255,255,255,.1)">
        <div class="flex items-center justify-center shrink-0" style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#818cf8,#6366f1);box-shadow:0 4px 18px rgba(99,102,241,.55)">
            <span style="color:#fff;font-weight:900;font-size:.75rem;letter-spacing:-.02em">IN</span>
        </div>
        <div class="flex-1 min-w-0">
            <p style="color:#fff;font-weight:700;font-size:.85rem;letter-spacing:.01em;line-height:1">IPINFRA HRM</p>
            <p style="font-size:.62rem;letter-spacing:.18em;color:#818cf8;font-weight:700;text-transform:uppercase;margin-top:4px">Admin Portal</p>
        </div>
        <button class="asb-close" onclick="asbToggle()"><i class="fas fa-times" style="font-size:.8rem"></i></button>
    </div>

    <!-- User card -->
    <div class="mx-3 mt-3.5 mb-1 px-3 shrink-0" style="padding-top:11px;padding-bottom:11px;border-radius:12px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)">
        <div class="flex items-center gap-3">
            <div class="flex items-center justify-center shrink-0" style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#818cf8,#6366f1);color:#fff;font-weight:700;font-size:.9rem;box-shadow:0 3px 12px rgba(99,102,241,.45)">
                <?php echo strtoupper(substr($_SESSION['user_name'],0,1)); ?>
            </div>
            <div class="flex-1 min-w-0">
                <p style="color:#fff;font-weight:600;font-size:.85rem;line-height:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                <p style="font-size:.7rem;color:rgba(255,255,255,.45);margin-top:4px">Administrator</p>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <span style="width:8px;height:8px;border-radius:50%;background:#4ade80;box-shadow:0 0 8px rgba(74,222,128,.7)"></span>
            </div>
        </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto px-2.5 pb-2 pt-1">
        <?php foreach($_admin_nav as [$href,$icon,$label,$sec]):
            if($sec !== $_ps): $_ps = $sec; ?>
        <div class="asb-sec">
            <span class="asb-sec-lbl"><?php echo $_sections[$sec]??$sec ?></span>
            <span class="asb-sec-line"></span>
        </div>
        <?php endif;
            $on = ($_cur === $href);
            [$ic,$ib] = $_ic[$icon] ?? ['#cbd5e1','rgba(203,213,225,.18)'];
        ?>
        <a href="<?php echo $href ?>" class="asb-link <?php echo $on?'on':'' ?>">
            <span class="asb-ico" style="color:<?php echo $ic ?>;background:<?php echo $ib ?>">
                <i class="fas <?php echo $icon ?>"></i>
            </span>
            <span class="flex-1"><?php echo $label ?></span>
            <?php if($on): ?><span style="width:6px;height:6px;border-radius:50%;background:rgba(199,210,254,.7);flex-shrink:0"></span><?php endif ?>
        </a>
        <?php endforeach ?>
    </nav>

    <!-- Footer -->
    <div class="px-2.5 pb-6 pt-2.5 shrink-0" style="border-top:1px solid rgba(255,255,255,.08)">
        <a href="../logout.php" class="asb-link"
           style="color:rgba(255,255,255,.55);background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.2)"
           onmouseover="this.style.background='rgba(239,68,68,.25)';this.style.color='#fca5a5';this.style.borderColor='rgba(239,68,68,.4)'"
           onmouseout="this.style.background='rgba(239,68,68,.12)';this.style.color='rgba(255,255,255,.55)';this.style.borderColor='rgba(239,68,68,.2)'">
            <span class="asb-ico" style="color:#fca5a5;background:rgba(239,68,68,.2)"><i class="fas fa-sign-out-alt"></i></span>
            <span class="flex-1">Sign Out</span>
        </a>
    </div>
</div>

<div id="asb-ov" class="fixed inset-0 z-40 hidden" style="background:rgba(0,0,0,.65);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px)" onclick="asbToggle()"></div>

<script>
function toggleSidebar(){asbToggle()}
var _asbTx=0;
function asbToggle(){
    var s=document.getElementById('asb'),o=document.getElementById('asb-ov');
    var opening=s.classList.contains('-translate-x-full');
    s.classList.toggle('-translate-x-full');
    o.classList.toggle('hidden');
    document.body.classList.toggle('asb-open',opening);
}
function asbClose(){
    document.getElementById('asb').classList.add('-translate-x-full');
    document.getElementById('asb-ov').classList.add('hidden');
    document.body.classList.remove('asb-open');
}
// Escape key
document.addEventListener('keydown',function(e){if(e.key==='Escape')asbClose()});
// Swipe left to close, swipe right from edge to open
document.addEventListener('touchstart',function(e){_asbTx=e.touches[0].clientX},{passive:true});
document.addEventListener('touchend',function(e){
    var dx=e.changedTouches[0].clientX-_asbTx;
    var dy=Math.abs(e.changedTouches[0].clientY-(e.changedTouches[0].clientY));
    var sb=document.getElementById('asb');
    var isOpen=!sb.classList.contains('-translate-x-full');
    if(isOpen&&dx<-55)asbClose();
    if(!isOpen&&_asbTx<22&&dx>65)asbToggle();
},{passive:true});
</script>
