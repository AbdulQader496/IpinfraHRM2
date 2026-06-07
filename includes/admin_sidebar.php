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
    'fa-home'                => ['#7dd3fc','rgba(125,211,252,.14)'],
    'fa-users'               => ['#c4b5fd','rgba(196,181,253,.14)'],
    'fa-calendar-check'      => ['#6ee7b7','rgba(110,231,183,.14)'],
    'fa-receipt'             => ['#fcd34d','rgba(252,211,77,.14)'],
    'fa-fingerprint'         => ['#67e8f9','rgba(103,232,249,.14)'],
    'fa-boxes'               => ['#fdba74','rgba(253,186,116,.14)'],
    'fa-images'              => ['#f9a8d4','rgba(249,168,212,.14)'],
    'fa-briefcase'           => ['#a5b4fc','rgba(165,180,252,.14)'],
    'fa-file-invoice-dollar' => ['#86efac','rgba(134,239,172,.14)'],
    'fa-calendar-alt'        => ['#fda4af','rgba(253,164,175,.14)'],
    'fa-shield-alt'          => ['#94a3b8','rgba(148,163,184,.14)'],
];
$_ps = '';
?>
<style>
/* ── Page background ──────────────────────────────────────── */
body {
    background-color: #f1f5fb !important;
    background-image:
        radial-gradient(ellipse 65% 40% at 8% -5%,  rgba(99,102,241,.07) 0%, transparent 55%),
        radial-gradient(ellipse 55% 35% at 90% 108%, rgba(139,92,246,.05) 0%, transparent 50%) !important;
    min-height: 100vh;
}
/* Global content scrollbar */
:not(#asb) ::-webkit-scrollbar { width: 5px; height: 5px; }
:not(#asb) ::-webkit-scrollbar-track { background: transparent; }
:not(#asb) ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
:not(#asb) ::-webkit-scrollbar-thumb:hover { background: #9ca3af; }

/* ── Admin Sidebar ─────────────────────────────────────────── */
#asb {
    background: #060912;
    background-image:
        radial-gradient(ellipse 180% 52% at 50% -6%,  rgba(99,102,241,.28) 0%, transparent 60%),
        radial-gradient(ellipse 100% 45% at 95%  80%,  rgba(139,92,246,.12) 0%, transparent 55%);
    border-right: 1px solid rgba(255,255,255,.055);
}
#asb::-webkit-scrollbar { width: 3px; }
#asb::-webkit-scrollbar-track { background: transparent; }
#asb::-webkit-scrollbar-thumb { background: rgba(99,102,241,.3); border-radius: 3px; }

.asb-link {
    display: flex; align-items: center; gap: .65rem;
    padding: .5rem .75rem; border-radius: .7rem;
    font-size: .8rem; font-weight: 500; line-height: 1;
    text-decoration: none; transition: all .16s ease;
    color: rgba(255,255,255,.42); border: 1px solid transparent;
}
.asb-link:hover { background: rgba(255,255,255,.06); color: rgba(255,255,255,.72); }
.asb-link.on {
    background: rgba(99,102,241,.2);
    border-color: rgba(129,140,248,.28);
    color: #c7d2fe;
    box-shadow: 0 2px 22px rgba(99,102,241,.2), inset 0 1px 0 rgba(255,255,255,.07);
}
.asb-link.on .asb-ico { background: rgba(255,255,255,.16) !important; color: #c7d2fe !important; }
.asb-ico {
    width: 1.85rem; height: 1.85rem; border-radius: .5rem;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: .7rem; transition: all .16s;
}
.asb-sec { display: flex; align-items: center; gap: .55rem; padding: .15rem .75rem .1rem; margin-top: .85rem; }
.asb-sec:first-child { margin-top: .2rem; }
.asb-sec-lbl { font-size: .6rem; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: rgba(255,255,255,.22); white-space: nowrap; }
.asb-sec-line { flex: 1; height: 1px; background: rgba(255,255,255,.055); }
.asb-close { width: 1.75rem; height: 1.75rem; border-radius: .45rem; border: none; cursor: pointer; background: none; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,.28); transition: all .15s; flex-shrink: 0; }
.asb-close:hover { background: rgba(255,255,255,.09); color: rgba(255,255,255,.65); }
</style>

<div id="asb" class="fixed top-0 left-0 h-full w-[265px] z-50 -translate-x-full transition-[transform] duration-300 ease-out flex flex-col overflow-hidden" style="font-family:'Inter',sans-serif;box-shadow:4px 0 50px rgba(0,0,0,.8)">

    <!-- Brand -->
    <div class="flex items-center gap-3 px-4 shrink-0" style="padding-top:18px;padding-bottom:16px;border-bottom:1px solid rgba(255,255,255,.055)">
        <div class="flex items-center justify-center shrink-0" style="width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#7c3aed);box-shadow:0 4px 18px rgba(99,102,241,.55)">
            <span style="color:#fff;font-weight:900;font-size:.73rem;letter-spacing:-.02em">IN</span>
        </div>
        <div class="flex-1 min-w-0">
            <p style="color:#fff;font-weight:700;font-size:.8rem;letter-spacing:.015em;line-height:1">IPINFRA HRM</p>
            <p style="font-size:.6rem;letter-spacing:.2em;color:#4338ca;font-weight:700;text-transform:uppercase;margin-top:4px;opacity:.9">Admin Portal</p>
        </div>
        <button class="asb-close" onclick="asbToggle()"><i class="fas fa-times" style="font-size:.75rem"></i></button>
    </div>

    <!-- User card -->
    <div class="mx-3 mt-3 mb-1 px-3 shrink-0" style="padding-top:10px;padding-bottom:10px;border-radius:10px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07)">
        <div class="flex items-center gap-2.5">
            <div class="flex items-center justify-center shrink-0" style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#7c3aed);color:#fff;font-weight:700;font-size:.85rem;box-shadow:0 3px 12px rgba(99,102,241,.4)">
                <?php echo strtoupper(substr($_SESSION['user_name'],0,1)); ?>
            </div>
            <div class="flex-1 min-w-0">
                <p style="color:rgba(255,255,255,.9);font-weight:600;font-size:.8rem;line-height:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                <p style="font-size:.68rem;color:rgba(255,255,255,.32);margin-top:4px">Administrator</p>
            </div>
            <span style="width:8px;height:8px;border-radius:50%;background:#34d399;box-shadow:0 0 8px rgba(52,211,153,.7);flex-shrink:0"></span>
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
            [$ic,$ib] = $_ic[$icon] ?? ['#94a3b8','rgba(148,163,184,.14)'];
        ?>
        <a href="<?php echo $href ?>" class="asb-link <?php echo $on ? 'on' : '' ?>">
            <span class="asb-ico" style="color:<?php echo $ic ?>;background:<?php echo $ib ?>">
                <i class="fas <?php echo $icon ?>"></i>
            </span>
            <span class="flex-1"><?php echo $label ?></span>
            <?php if($on): ?><span style="width:6px;height:6px;border-radius:50%;background:rgba(165,180,252,.6);flex-shrink:0"></span><?php endif ?>
        </a>
        <?php endforeach ?>
    </nav>

    <!-- Footer -->
    <div class="px-2.5 pb-5 pt-2.5 shrink-0" style="border-top:1px solid rgba(255,255,255,.045)">
        <a href="../logout.php" class="asb-link"
           style="color:rgba(255,255,255,.38);background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.15)"
           onmouseover="this.style.background='rgba(239,68,68,.16)';this.style.color='#fca5a5';this.style.borderColor='rgba(239,68,68,.32)'"
           onmouseout="this.style.background='rgba(239,68,68,.08)';this.style.color='rgba(255,255,255,.38)';this.style.borderColor='rgba(239,68,68,.15)'">
            <span class="asb-ico" style="color:#f87171;background:rgba(239,68,68,.15)"><i class="fas fa-sign-out-alt"></i></span>
            <span class="flex-1">Sign Out</span>
        </a>
    </div>
</div>

<div id="asb-ov" class="fixed inset-0 z-40 hidden" style="background:rgba(0,0,0,.78);backdrop-filter:blur(3px)" onclick="asbToggle()"></div>

<script>
function toggleSidebar(){asbToggle()}
function asbToggle(){
    var s=document.getElementById('asb'),o=document.getElementById('asb-ov');
    s.classList.toggle('-translate-x-full');o.classList.toggle('hidden');
}
document.addEventListener('keydown',function(e){if(e.key==='Escape'){document.getElementById('asb').classList.add('-translate-x-full');document.getElementById('asb-ov').classList.add('hidden')}});
</script>
