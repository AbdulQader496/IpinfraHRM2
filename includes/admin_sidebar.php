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

// [icon-color, icon-bg] for light background
$_ic = [
    'fa-home'                => ['#2563eb','#dbeafe'],
    'fa-users'               => ['#7c3aed','#ede9fe'],
    'fa-calendar-check'      => ['#059669','#d1fae5'],
    'fa-receipt'             => ['#d97706','#fef3c7'],
    'fa-fingerprint'         => ['#0891b2','#cffafe'],
    'fa-boxes'               => ['#ea580c','#ffedd5'],
    'fa-images'              => ['#db2777','#fce7f3'],
    'fa-briefcase'           => ['#4f46e5','#e0e7ff'],
    'fa-file-invoice-dollar' => ['#16a34a','#dcfce7'],
    'fa-calendar-alt'        => ['#e11d48','#ffe4e6'],
    'fa-shield-alt'          => ['#475569','#f1f5f9'],
];
$_ps = '';
?>
<style>
/* ── Page background ─────────────────────────────────── */
body {
    background-color: #f3f6fb !important;
    min-height: 100vh;
}
:not(#asb) ::-webkit-scrollbar { width:5px; height:5px; }
:not(#asb) ::-webkit-scrollbar-track { background:transparent; }
:not(#asb) ::-webkit-scrollbar-thumb { background:#d1d5db; border-radius:4px; }
body.asb-open { overflow:hidden; }

/* ── Admin Sidebar ───────────────────────────────────── */
#asb {
    background: #ffffff;
    border-right: 1px solid #e5e7eb;
}
#asb::-webkit-scrollbar { width:3px; }
#asb::-webkit-scrollbar-track { background:transparent; }
#asb::-webkit-scrollbar-thumb { background:#c7d2fe; border-radius:3px; }

.asb-link {
    display:flex; align-items:center; gap:.65rem;
    padding:.65rem .8rem; border-radius:.65rem;
    font-size:.82rem; font-weight:500; line-height:1;
    text-decoration:none; transition:all .15s ease;
    color:#4b5563; border:1px solid transparent;
    min-height:2.75rem;
}
.asb-link:hover { background:#eef2ff; color:#3730a3; }
.asb-link.on {
    background:#e0e7ff;
    color:#3730a3;
    font-weight:600;
    border-color:#c7d2fe;
    box-shadow:inset 3px 0 0 #4f46e5;
}
.asb-link.on .asb-ico { background:#c7d2fe !important; color:#4338ca !important; }
.asb-ico {
    width:1.9rem; height:1.9rem; border-radius:.5rem;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0; font-size:.72rem; transition:all .15s;
}
.asb-sec { display:flex; align-items:center; gap:.5rem; padding:.1rem .8rem; margin-top:.85rem; }
.asb-sec:first-child { margin-top:.15rem; }
.asb-sec-lbl { font-size:.6rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:#9ca3af; white-space:nowrap; }
.asb-sec-line { flex:1; height:1px; background:#f3f4f6; }
.asb-close {
    width:2rem; height:2rem; border-radius:.5rem; border:none; cursor:pointer;
    background:none; display:flex; align-items:center; justify-content:center;
    color:#9ca3af; transition:all .15s; flex-shrink:0;
}
.asb-close:hover { background:#f3f4f6; color:#374151; }
</style>

<div id="asb" class="fixed top-0 left-0 h-full z-50 -translate-x-full transition-[transform] duration-300 ease-out flex flex-col overflow-hidden"
     style="width:min(260px,78vw);font-family:'Inter',sans-serif;box-shadow:4px 0 24px rgba(0,0,0,.13)">

    <!-- Brand strip -->
    <div class="flex items-center gap-3 px-4 shrink-0"
         style="padding-top:18px;padding-bottom:16px;background:linear-gradient(135deg,#4f46e5,#6366f1);flex-shrink:0">
        <div class="flex items-center justify-center shrink-0"
             style="width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.2)">
            <span style="color:#fff;font-weight:900;font-size:.75rem">IN</span>
        </div>
        <div class="flex-1 min-w-0">
            <p style="color:#fff;font-weight:700;font-size:.82rem;line-height:1">IPINFRA HRM</p>
            <p style="font-size:.6rem;color:rgba(255,255,255,.7);font-weight:600;letter-spacing:.15em;text-transform:uppercase;margin-top:3px">Admin Portal</p>
        </div>
        <button class="asb-close" onclick="asbToggle()"
                style="color:rgba(255,255,255,.65)" onmouseover="this.style.color='#fff';this.style.background='rgba(255,255,255,.15)'" onmouseout="this.style.color='rgba(255,255,255,.65)';this.style.background='none'">
            <i class="fas fa-times" style="font-size:.8rem"></i>
        </button>
    </div>

    <!-- User card -->
    <div class="mx-3 mt-3 mb-1 px-3 shrink-0"
         style="padding-top:10px;padding-bottom:10px;border-radius:10px;background:#eef2ff;border:1px solid #e0e7ff">
        <div class="flex items-center gap-2.5">
            <div class="flex items-center justify-center shrink-0"
                 style="width:38px;height:38px;border-radius:9px;background:linear-gradient(135deg,#4f46e5,#6366f1);color:#fff;font-weight:700;font-size:.85rem">
                <?php echo strtoupper(substr($_SESSION['user_name'],0,1)); ?>
            </div>
            <div class="flex-1 min-w-0">
                <p style="color:#111827;font-weight:600;font-size:.82rem;line-height:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                <p style="font-size:.68rem;color:#6b7280;margin-top:3px">Administrator</p>
            </div>
            <span style="width:8px;height:8px;border-radius:50%;background:#22c55e;flex-shrink:0"></span>
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
            [$ic,$ib] = $_ic[$icon] ?? ['#6b7280','#f3f4f6'];
        ?>
        <a href="<?php echo $href ?>" class="asb-link <?php echo $on?'on':'' ?>">
            <span class="asb-ico" style="color:<?php echo $ic ?>;background:<?php echo $ib ?>">
                <i class="fas <?php echo $icon ?>"></i>
            </span>
            <span class="flex-1"><?php echo $label ?></span>
        </a>
        <?php endforeach ?>
    </nav>

    <!-- Footer -->
    <div class="px-2.5 pb-6 pt-2 shrink-0" style="border-top:1px solid #f3f4f6">
        <a href="../logout.php" class="asb-link"
           style="color:#dc2626;background:#fef2f2;border-color:#fecaca"
           onmouseover="this.style.background='#fee2e2';this.style.borderColor='#fca5a5'"
           onmouseout="this.style.background='#fef2f2';this.style.borderColor='#fecaca'">
            <span class="asb-ico" style="color:#dc2626;background:#fee2e2"><i class="fas fa-sign-out-alt"></i></span>
            <span class="flex-1">Sign Out</span>
        </a>
    </div>
</div>

<div id="asb-ov" class="fixed inset-0 z-40 hidden"
     style="background:rgba(0,0,0,.45);backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px)"
     onclick="asbToggle()"></div>

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
document.addEventListener('keydown',function(e){if(e.key==='Escape')asbClose()});
document.addEventListener('touchstart',function(e){_asbTx=e.touches[0].clientX},{passive:true});
document.addEventListener('touchend',function(e){
    var dx=e.changedTouches[0].clientX-_asbTx;
    var sb=document.getElementById('asb');
    var open=!sb.classList.contains('-translate-x-full');
    if(open&&dx<-50)asbClose();
    if(!open&&_asbTx<20&&dx>60)asbToggle();
},{passive:true});
</script>
