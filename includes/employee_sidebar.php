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

$_ic = [
    'fa-home'                => ['#93c5fd','rgba(147,197,253,.18)'],
    'fa-clock'               => ['#67e8f9','rgba(103,232,249,.18)'],
    'fa-calendar-check'      => ['#6ee7b7','rgba(110,231,183,.18)'],
    'fa-receipt'             => ['#fcd34d','rgba(252,211,77,.18)'],
    'fa-images'              => ['#f9a8d4','rgba(249,168,212,.18)'],
    'fa-boxes'               => ['#fdba74','rgba(253,186,116,.18)'],
    'fa-briefcase'           => ['#a5b4fc','rgba(165,180,252,.18)'],
    'fa-file-invoice-dollar' => ['#86efac','rgba(134,239,172,.18)'],
    'fa-calendar-alt'        => ['#fda4af','rgba(253,164,175,.18)'],
    'fa-user-circle'         => ['#c4b5fd','rgba(196,181,253,.18)'],
];
$_ps = '';
?>
<style>
/* ── Page background ──────────────────────────────────── */
body {
    background-color: #f0f5fb !important;
    background-image:
        radial-gradient(ellipse 65% 40% at 8% -5%,  rgba(14,165,233,.07) 0%, transparent 55%),
        radial-gradient(ellipse 55% 35% at 90% 108%, rgba(6,182,212,.05)  0%, transparent 50%) !important;
    min-height: 100vh;
}
:not(#esb) ::-webkit-scrollbar { width:5px; height:5px; }
:not(#esb) ::-webkit-scrollbar-track { background:transparent; }
:not(#esb) ::-webkit-scrollbar-thumb { background:#d1d5db; border-radius:4px; }
:not(#esb) ::-webkit-scrollbar-thumb:hover { background:#9ca3af; }
body.esb-open { overflow:hidden; }

/* ── Employee Sidebar ─────────────────────────────────── */
#esb {
    background: linear-gradient(175deg, #0b1e3d 0%, #0d2447 45%, #091b35 100%);
    background-image:
        radial-gradient(ellipse 200% 50% at 50% -8%, rgba(14,165,233,.42) 0%, transparent 55%),
        radial-gradient(ellipse 100% 50% at 95% 85%, rgba(6,182,212,.18)  0%, transparent 50%),
        linear-gradient(175deg, #0b1e3d 0%, #0d2447 45%, #091b35 100%);
    border-right: 1px solid rgba(255,255,255,.1);
}
#esb::-webkit-scrollbar { width:3px; }
#esb::-webkit-scrollbar-track { background:transparent; }
#esb::-webkit-scrollbar-thumb { background:rgba(125,211,252,.35); border-radius:3px; }

.esb-link {
    display:flex; align-items:center; gap:.7rem;
    padding:.72rem .85rem; border-radius:.75rem;
    font-size:.825rem; font-weight:500; line-height:1;
    text-decoration:none; transition:all .17s ease;
    color:rgba(255,255,255,.55); border:1px solid transparent;
    min-height:2.75rem;
}
.esb-link:hover { background:rgba(255,255,255,.1); color:rgba(255,255,255,.9); }
.esb-link.on {
    background:rgba(14,165,233,.28);
    border-color:rgba(125,211,252,.35);
    color:#e0f2fe;
    box-shadow:0 2px 20px rgba(14,165,233,.3), inset 0 1px 0 rgba(255,255,255,.12);
}
.esb-link.on .esb-ico { background:rgba(255,255,255,.2) !important; color:#e0f2fe !important; }
.esb-ico {
    width:2rem; height:2rem; border-radius:.55rem;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0; font-size:.75rem; transition:all .17s;
}
.esb-sec { display:flex; align-items:center; gap:.6rem; padding:.15rem .85rem .1rem; margin-top:.9rem; }
.esb-sec:first-child { margin-top:.25rem; }
.esb-sec-lbl { font-size:.62rem; font-weight:700; letter-spacing:.15em; text-transform:uppercase; color:rgba(255,255,255,.3); white-space:nowrap; }
.esb-sec-line { flex:1; height:1px; background:rgba(255,255,255,.1); }
.esb-close { width:2rem; height:2rem; border-radius:.5rem; border:none; cursor:pointer; background:none; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,.4); transition:all .15s; flex-shrink:0; }
.esb-close:hover { background:rgba(255,255,255,.12); color:rgba(255,255,255,.85); }
</style>

<div id="esb" class="fixed top-0 left-0 h-full z-50 -translate-x-full transition-[transform] duration-300 ease-out flex flex-col overflow-hidden" style="width:min(280px,85vw);font-family:'Inter',sans-serif;box-shadow:6px 0 40px rgba(0,0,0,.55)">

    <!-- Brand -->
    <div class="flex items-center gap-3 px-4 shrink-0" style="padding-top:20px;padding-bottom:18px;border-bottom:1px solid rgba(255,255,255,.1)">
        <div class="flex items-center justify-center shrink-0" style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#38bdf8,#0ea5e9);box-shadow:0 4px 18px rgba(14,165,233,.55)">
            <span style="color:#fff;font-weight:900;font-size:.75rem;letter-spacing:-.02em">IN</span>
        </div>
        <div class="flex-1 min-w-0">
            <p style="color:#fff;font-weight:700;font-size:.85rem;letter-spacing:.01em;line-height:1">IPINFRA HRM</p>
            <p style="font-size:.62rem;letter-spacing:.18em;color:#38bdf8;font-weight:700;text-transform:uppercase;margin-top:4px">Employee Portal</p>
        </div>
        <button class="esb-close" onclick="esbToggle()"><i class="fas fa-times" style="font-size:.8rem"></i></button>
    </div>

    <!-- User card -->
    <div class="mx-3 mt-3.5 mb-1 px-3 shrink-0" style="padding-top:11px;padding-bottom:11px;border-radius:12px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)">
        <div class="flex items-center gap-3">
            <div class="flex items-center justify-center shrink-0" style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#38bdf8,#0ea5e9);color:#fff;font-weight:700;font-size:.9rem;box-shadow:0 3px 12px rgba(14,165,233,.42)">
                <?php echo strtoupper(substr($_SESSION['user_name'],0,1)); ?>
            </div>
            <div class="flex-1 min-w-0">
                <p style="color:#fff;font-weight:600;font-size:.85rem;line-height:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                <p style="font-size:.7rem;color:rgba(255,255,255,.45);margin-top:4px"><?php echo htmlspecialchars($_SESSION['employee_id'] ?? 'Employee'); ?></p>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <span style="width:8px;height:8px;border-radius:50%;background:#4ade80;box-shadow:0 0 8px rgba(74,222,128,.7)"></span>
            </div>
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
            [$ic,$ib] = $_ic[$icon] ?? ['#cbd5e1','rgba(203,213,225,.18)'];
        ?>
        <a href="<?php echo $href ?>" class="esb-link <?php echo $on?'on':'' ?>">
            <span class="esb-ico" style="color:<?php echo $ic ?>;background:<?php echo $ib ?>">
                <i class="fas <?php echo $icon ?>"></i>
            </span>
            <span class="flex-1"><?php echo $label ?></span>
            <?php if($on): ?><span style="width:6px;height:6px;border-radius:50%;background:rgba(186,230,253,.7);flex-shrink:0"></span><?php endif ?>
        </a>
        <?php endforeach ?>
    </nav>

    <!-- Footer -->
    <div class="px-2.5 pb-6 pt-2.5 shrink-0" style="border-top:1px solid rgba(255,255,255,.08)">
        <a href="../logout.php" class="esb-link"
           style="color:rgba(255,255,255,.55);background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.2)"
           onmouseover="this.style.background='rgba(239,68,68,.25)';this.style.color='#fca5a5';this.style.borderColor='rgba(239,68,68,.4)'"
           onmouseout="this.style.background='rgba(239,68,68,.12)';this.style.color='rgba(255,255,255,.55)';this.style.borderColor='rgba(239,68,68,.2)'">
            <span class="esb-ico" style="color:#fca5a5;background:rgba(239,68,68,.2)"><i class="fas fa-sign-out-alt"></i></span>
            <span class="flex-1">Sign Out</span>
        </a>
    </div>
</div>

<div id="esb-ov" class="fixed inset-0 z-40 hidden" style="background:rgba(0,0,0,.65);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px)" onclick="esbToggle()"></div>

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
    var isOpen=!sb.classList.contains('-translate-x-full');
    if(isOpen&&dx<-55)esbClose();
    if(!isOpen&&_esbTx<22&&dx>65)esbToggle();
},{passive:true});
</script>
