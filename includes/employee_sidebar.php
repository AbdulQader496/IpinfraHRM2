<?php
// Auto-detect current page for active state
$_cur = basename($_SERVER['PHP_SELF']);

// Nav definition: [href, icon, label, section]
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

$_emp_section_labels = [
    'overview' => 'Overview',
    'work'     => 'Work',
    'company'  => 'Company',
    'finance'  => 'Finance',
    'account'  => 'Account',
];

$_emp_icon_colors = [
    'fa-home'                => 'text-sky-400    bg-sky-500/15',
    'fa-clock'               => 'text-cyan-400   bg-cyan-500/15',
    'fa-calendar-check'      => 'text-emerald-400 bg-emerald-500/15',
    'fa-receipt'             => 'text-amber-400  bg-amber-500/15',
    'fa-images'              => 'text-pink-400   bg-pink-500/15',
    'fa-boxes'               => 'text-orange-400 bg-orange-500/15',
    'fa-briefcase'           => 'text-indigo-400 bg-indigo-500/15',
    'fa-file-invoice-dollar' => 'text-green-400  bg-green-500/15',
    'fa-calendar-alt'        => 'text-rose-400   bg-rose-500/15',
    'fa-user-circle'         => 'text-violet-400 bg-violet-500/15',
];

$_emp_prev_section = '';
?>

<style>
#employee-sidebar { background: linear-gradient(160deg,#0d1117 0%,#0f172a 50%,#0a1628 100%); }
#employee-sidebar::-webkit-scrollbar { width: 4px; }
#employee-sidebar::-webkit-scrollbar-track { background: transparent; }
#employee-sidebar::-webkit-scrollbar-thumb { background: rgba(56,189,248,.22); border-radius: 4px; }
.emp-nav-item { display:flex; align-items:center; gap:.75rem; padding:.6rem .85rem; border-radius:.875rem; font-size:.8rem; font-weight:500; transition:all .18s; cursor:pointer; text-decoration:none; }
.emp-nav-item.active { background:linear-gradient(135deg,#0ea5e9,#2563eb); color:#fff; box-shadow:0 4px 18px rgba(14,165,233,.32); }
.emp-nav-item:not(.active) { color:#94a3b8; }
.emp-nav-item:not(.active):hover { background:rgba(255,255,255,.06); color:#e2e8f0; }
.emp-nav-item.active .emp-nav-icon { background:rgba(255,255,255,.2) !important; color:#fff !important; }
.emp-section-label { font-size:.65rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#334155; padding:.5rem .85rem .35rem; }
</style>

<!-- Employee Sidebar -->
<div id="employee-sidebar" class="fixed top-0 left-0 h-full w-[272px] z-50 transform -translate-x-full transition-all duration-300 shadow-2xl flex flex-col overflow-hidden" style="font-family:'Inter',sans-serif;">

    <!-- Brand header -->
    <div class="flex items-center gap-3 px-5 py-5 border-b border-white/[.06] shrink-0">
        <div class="w-9 h-9 bg-gradient-to-br from-sky-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-sky-600/40 shrink-0">
            <span class="text-white font-black text-sm tracking-tight">IN</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-white font-bold text-sm leading-none">IPINFRA HRM</p>
            <p class="text-sky-400 text-[9px] font-semibold tracking-[.18em] uppercase mt-1">Employee Portal</p>
        </div>
        <button onclick="toggleSidebar()" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-500 hover:text-white hover:bg-white/10 transition">
            <i class="fas fa-times text-sm"></i>
        </button>
    </div>

    <!-- User card -->
    <div class="mx-3 mt-3 mb-1 px-3 py-3 rounded-2xl shrink-0" style="background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.07);">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 flex items-center justify-center text-white font-bold text-sm shadow-md shrink-0">
                <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-white font-semibold text-sm leading-tight truncate"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                <div class="flex items-center gap-1.5 mt-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-sm shadow-emerald-400/60"></span>
                    <span class="text-[10px] text-emerald-400 font-medium">Online</span>
                </div>
            </div>
            <span class="shrink-0 text-[9px] font-bold bg-sky-500/20 text-sky-300 border border-sky-500/25 px-2 py-0.5 rounded-full tracking-wide uppercase">Staff</span>
        </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto px-3 py-2 space-y-0.5">
        <?php foreach ($_emp_nav as [$href, $icon, $label, $section]):
            if ($section !== $_emp_prev_section):
                $_emp_prev_section = $section;
        ?>
        <p class="emp-section-label <?php echo $_emp_prev_section !== 'overview' ? 'mt-3' : ''; ?>">
            <?php echo $_emp_section_labels[$section] ?? $section; ?>
        </p>
        <?php endif;
            $is_active  = ($_cur === $href);
            $icon_cls   = $_emp_icon_colors[$icon] ?? 'text-slate-400 bg-slate-500/15';
        ?>
        <a href="<?php echo $href; ?>" class="emp-nav-item <?php echo $is_active ? 'active' : ''; ?>">
            <span class="emp-nav-icon w-7 h-7 rounded-lg flex items-center justify-center shrink-0 <?php echo $icon_cls; ?>" style="font-size:.75rem;">
                <i class="fas <?php echo $icon; ?>"></i>
            </span>
            <span class="flex-1"><?php echo $label; ?></span>
            <?php if ($is_active): ?>
            <span class="w-1.5 h-1.5 rounded-full bg-white/60"></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Footer -->
    <div class="px-3 pb-5 pt-2 shrink-0 border-t border-white/[.06]">
        <a href="../logout.php"
           class="emp-nav-item w-full mt-2" style="background:rgba(239,68,68,.12); color:#fca5a5; border:1px solid rgba(239,68,68,.18);"
           onmouseover="this.style.background='rgba(239,68,68,.22)'" onmouseout="this.style.background='rgba(239,68,68,.12)'">
            <span class="emp-nav-icon w-7 h-7 rounded-lg flex items-center justify-center shrink-0 bg-red-500/15 text-red-400" style="font-size:.75rem;">
                <i class="fas fa-sign-out-alt"></i>
            </span>
            <span class="flex-1">Logout</span>
        </a>
    </div>
</div>

<!-- Overlay -->
<div id="overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden" onclick="toggleSidebar()"></div>

<script>
function toggleSidebar() {
    const sb = document.getElementById('employee-sidebar');
    const ov = document.getElementById('overlay');
    sb.classList.toggle('-translate-x-full');
    ov.classList.toggle('hidden');
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') { document.getElementById('employee-sidebar').classList.add('-translate-x-full'); document.getElementById('overlay').classList.add('hidden'); } });
</script>
