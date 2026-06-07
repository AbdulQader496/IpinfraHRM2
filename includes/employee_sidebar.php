<?php
$_cur = basename($_SERVER['PHP_SELF']);

$_emp_nav = [
    ['dashboard.php',  'fa-home',                'Dashboard',  'overview'],
    ['clock.php',      'fa-clock',               'Attendance', 'work'],
    ['leave.php',      'fa-calendar-check',      'Leave',      'work'],
    ['claim.php',      'fa-receipt',             'Claims',     'work'],
    ['gallery.php',    'fa-images',              'Gallery',    'company'],
    ['assets.php',     'fa-boxes',               'Assets',     'company'],
    ['management.php', 'fa-briefcase',           'Management', 'company'],
    ['payslip.php',    'fa-file-invoice-dollar', 'Payslip',    'finance'],
    ['calendar.php',   'fa-calendar-alt',        'Calendar',   'finance'],
    ['profile.php',    'fa-user-circle',         'Profile',    'account'],
];

$_sections = ['overview'=>'Overview','work'=>'Work','company'=>'Company','finance'=>'Finance','account'=>'Account'];

// [icon-color, icon-bg] for light background
$_ic = [
    'fa-home'                => ['#0284c7','#e0f2fe'],
    'fa-clock'               => ['#0891b2','#cffafe'],
    'fa-calendar-check'      => ['#059669','#d1fae5'],
    'fa-receipt'             => ['#d97706','#fef3c7'],
    'fa-images'              => ['#db2777','#fce7f3'],
    'fa-boxes'               => ['#ea580c','#ffedd5'],
    'fa-briefcase'           => ['#4f46e5','#e0e7ff'],
    'fa-file-invoice-dollar' => ['#16a34a','#dcfce7'],
    'fa-calendar-alt'        => ['#e11d48','#ffe4e6'],
    'fa-user-circle'         => ['#7c3aed','#ede9fe'],
];
$_ps = '';
?>
<style>
/* ── Page background ─────────────────────────────────── */
body {
    background-color: #f3f6fb !important;
    min-height: 100vh;
}
:not(#esb) ::-webkit-scrollbar { width:5px; height:5px; }
:not(#esb) ::-webkit-scrollbar-track { background:transparent; }
:not(#esb) ::-webkit-scrollbar-thumb { background:#d1d5db; border-radius:4px; }
body.esb-open { overflow:hidden; }

/* ── Employee Sidebar ────────────────────────────────── */
#esb {
    background: #ffffff;
    border-right: 1px solid #e5e7eb;
}
#esb::-webkit-scrollbar { width:3px; }
#esb::-webkit-scrollbar-track { background:transparent; }
#esb::-webkit-scrollbar-thumb { background:#bae6fd; border-radius:3px; }

.esb-link {
    display:flex; align-items:center; gap:.65rem;
    padding:.65rem .8rem; border-radius:.65rem;
    font-size:.82rem; font-weight:500; line-height:1;
    text-decoration:none !important;
    transition:background-color .15s ease, box-shadow .15s ease;
    color:#1f2937 !important; border:1px solid transparent;
    min-height:2.75rem;
}
.esb-link:hover { background:#e0f2fe !important; color:#0c4a6e !important; }
.esb-link.on {
    background:#0284c7 !important;
    color:#ffffff !important;
    font-weight:700 !important;
    border-color:#0369a1 !important;
    box-shadow:inset 3px 0 0 #7dd3fc;
}
.esb-link.on .esb-ico { background:rgba(255,255,255,.22) !important; color:#ffffff !important; }
.esb-ico {
    width:1.9rem; height:1.9rem; border-radius:.5rem;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0; font-size:.72rem; transition:all .15s;
}
.esb-sec { display:flex; align-items:center; gap:.5rem; padding:.1rem .8rem; margin-top:.85rem; }
.esb-sec:first-child { margin-top:.15rem; }
.esb-sec-lbl { font-size:.6rem; font-weight:700; letter-spacing:.14em; text-transform:uppercase; color:#9ca3af; white-space:nowrap; }
.esb-sec-line { flex:1; height:1px; background:#f3f4f6; }
.esb-close {
    width:2rem; height:2rem; border-radius:.5rem; border:none; cursor:pointer;
    background:none; display:flex; align-items:center; justify-content:center;
    color:rgba(255,255,255,.65); transition:all .15s; flex-shrink:0;
}
.esb-close:hover { background:rgba(255,255,255,.2); color:#fff; }
</style>

<div id="esb" class="fixed top-0 left-0 h-full z-50 -translate-x-full transition-[transform] duration-300 ease-out flex flex-col overflow-hidden"
     style="width:min(260px,78vw);font-family:'Inter',sans-serif;box-shadow:4px 0 24px rgba(0,0,0,.13)">

    <!-- Brand strip -->
    <div class="flex items-center gap-3 px-4 shrink-0"
         style="padding-top:18px;padding-bottom:16px;background:linear-gradient(135deg,#0284c7,#0ea5e9);flex-shrink:0">
        <div class="flex items-center justify-center shrink-0"
             style="width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.2)">
            <span style="color:#fff;font-weight:900;font-size:.75rem">IN</span>
        </div>
        <div class="flex-1 min-w-0">
            <p style="color:#fff;font-weight:700;font-size:.82rem;line-height:1">IPINFRA HRM</p>
            <p style="font-size:.6rem;color:rgba(255,255,255,.7);font-weight:600;letter-spacing:.15em;text-transform:uppercase;margin-top:3px">Employee Portal</p>
        </div>
        <button class="esb-close" onclick="esbToggle()">
            <i class="fas fa-times" style="font-size:.8rem"></i>
        </button>
    </div>

    <!-- User card -->
    <div class="mx-3 mt-3 mb-1 px-3 shrink-0"
         style="padding-top:10px;padding-bottom:10px;border-radius:10px;background:#e0f2fe;border:1px solid #bae6fd">
        <div class="flex items-center gap-2.5">
            <div class="flex items-center justify-center shrink-0"
                 style="width:38px;height:38px;border-radius:9px;background:linear-gradient(135deg,#0284c7,#0ea5e9);color:#fff;font-weight:700;font-size:.85rem">
                <?php echo strtoupper(substr($_SESSION['user_name'],0,1)); ?>
            </div>
            <div class="flex-1 min-w-0">
                <p style="color:#0c4a6e;font-weight:600;font-size:.82rem;line-height:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                <p style="font-size:.68rem;color:#0369a1;margin-top:3px"><?php echo htmlspecialchars($_SESSION['employee_id'] ?? 'Employee'); ?></p>
            </div>
            <span style="width:8px;height:8px;border-radius:50%;background:#22c55e;flex-shrink:0"></span>
        </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto px-2.5 pb-2 pt-1">
        <?php foreach($_emp_nav as [$href,$icon,$label,$sec]):
            if($sec !== $_ps): $_ps = $sec; ?>
        <div class="esb-sec">
            <span class="esb-sec-lbl"><?php echo $_sections[$sec]??$sec ?></span>
            <span class="esb-sec-line"></span>
        </div>
        <?php endif;
            $on = ($_cur === $href);
            [$ic,$ib] = $_ic[$icon] ?? ['#6b7280','#f3f4f6'];
        ?>
        <a href="<?php echo $href ?>" class="esb-link <?php echo $on?'on':'' ?>">
            <span class="esb-ico" style="color:<?php echo $ic ?>;background:<?php echo $ib ?>">
                <i class="fas <?php echo $icon ?>"></i>
            </span>
            <span class="flex-1"><?php echo $label ?></span>
        </a>
        <?php endforeach ?>
    </nav>

    <!-- Footer -->
    <div class="px-2.5 pb-6 pt-2 shrink-0" style="border-top:1px solid #f3f4f6">
        <a href="../logout.php" class="esb-link"
           style="color:#dc2626;background:#fef2f2;border-color:#fecaca"
           onmouseover="this.style.background='#fee2e2';this.style.borderColor='#fca5a5'"
           onmouseout="this.style.background='#fef2f2';this.style.borderColor='#fecaca'">
            <span class="esb-ico" style="color:#dc2626;background:#fee2e2"><i class="fas fa-sign-out-alt"></i></span>
            <span class="flex-1">Sign Out</span>
        </a>
    </div>
</div>

<div id="esb-ov" class="fixed inset-0 z-40 hidden"
     style="background:rgba(0,0,0,.45);backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px)"
     onclick="esbToggle()"></div>

<script>
function toggleSidebar(){esbToggle()}
var _esbTx=0;
function esbToggle(){
    var s=document.getElementById('esb'),o=document.getElementById('esb-ov');
    var opening=s.classList.contains('-translate-x-full');
    s.classList.toggle('-translate-x-full');
    o.classList.toggle('hidden');
    document.body.classList.toggle('esb-open',opening);
}
function esbClose(){
    document.getElementById('esb').classList.add('-translate-x-full');
    document.getElementById('esb-ov').classList.add('hidden');
    document.body.classList.remove('esb-open');
}
document.addEventListener('keydown',function(e){if(e.key==='Escape')esbClose()});
document.addEventListener('touchstart',function(e){_esbTx=e.touches[0].clientX},{passive:true});
document.addEventListener('touchend',function(e){
    var dx=e.changedTouches[0].clientX-_esbTx;
    var sb=document.getElementById('esb');
    var open=!sb.classList.contains('-translate-x-full');
    if(open&&dx<-50)esbClose();
    if(!open&&_esbTx<20&&dx>60)esbToggle();
},{passive:true});
</script>
