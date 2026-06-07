<?php
// Auto-detect current page for active state
$_cur = basename($_SERVER['PHP_SELF']);

// Nav definition: [href, icon, label, section]
$_admin_nav = [
    ['dashboard.php',    'fa-home',                'Dashboard',       'overview'],
    ['employees.php',    'fa-users',               'Employees',       'people'],
    ['manage_leave.php', 'fa-calendar-check',      'Leave',           'people'],
    ['manage_claim.php', 'fa-receipt',             'Claims',          'people'],
    ['attendance.php',   'fa-fingerprint',         'Attendance',      'people'],
    ['manage_assets.php','fa-boxes',               'Assets',          'resources'],
    ['manage_gallery.php','fa-images',             'Gallery',         'resources'],
    ['management.php',   'fa-briefcase',           'Management',      'operations'],
    ['payroll.php',      'fa-file-invoice-dollar', 'Payroll',         'operations'],
    ['holidays.php',     'fa-calendar-alt',        'Holidays',        'operations'],
    ['audit_log.php',    'fa-shield-alt',          'Audit Log',       'system'],
];

$_section_labels = [
    'overview'   => 'Overview',
    'people'     => 'People',
    'resources'  => 'Resources',
    'operations' => 'Operations',
    'system'     => 'System',
];

$_icon_colors = [
    'fa-home'                => 'text-sky-400    bg-sky-500/15',
    'fa-users'               => 'text-violet-400 bg-violet-500/15',
    'fa-calendar-check'      => 'text-emerald-400 bg-emerald-500/15',
    'fa-receipt'             => 'text-amber-400  bg-amber-500/15',
    'fa-fingerprint'         => 'text-cyan-400   bg-cyan-500/15',
    'fa-boxes'               => 'text-orange-400 bg-orange-500/15',
    'fa-images'              => 'text-pink-400   bg-pink-500/15',
    'fa-briefcase'           => 'text-indigo-400 bg-indigo-500/15',
    'fa-file-invoice-dollar' => 'text-green-400  bg-green-500/15',
    'fa-calendar-alt'        => 'text-rose-400   bg-rose-500/15',
    'fa-shield-alt'          => 'text-slate-400  bg-slate-500/15',
];

$_prev_section = '';
?>

<style>
#admin-sidebar { background: linear-gradient(160deg,#0d1117 0%,#0f172a 50%,#1a1040 100%); }
#admin-sidebar::-webkit-scrollbar { width: 4px; }
#admin-sidebar::-webkit-scrollbar-track { background: transparent; }
#admin-sidebar::-webkit-scrollbar-thumb { background: rgba(99,102,241,.25); border-radius: 4px; }
.sidebar-nav-item { display:flex; align-items:center; gap:.75rem; padding:.6rem .85rem; border-radius:.875rem; font-size:.8rem; font-weight:500; transition:all .18s; cursor:pointer; text-decoration:none; }
.sidebar-nav-item.active { background:linear-gradient(135deg,#4f46e5,#2563eb); color:#fff; box-shadow:0 4px 18px rgba(79,70,229,.35); }
.sidebar-nav-item:not(.active) { color:#94a3b8; }
.sidebar-nav-item:not(.active):hover { background:rgba(255,255,255,.06); color:#e2e8f0; }
.sidebar-nav-item.active .nav-icon { background:rgba(255,255,255,.2) !important; color:#fff !important; }
.sidebar-section-label { font-size:.65rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#334155; padding:.5rem .85rem .35rem; }
</style>

<!-- Sidebar -->
<div id="admin-sidebar" class="fixed top-0 left-0 h-full w-[272px] z-50 transform -translate-x-full transition-all duration-300 shadow-2xl flex flex-col overflow-hidden" style="font-family:'Inter',sans-serif;">

    <!-- Brand header -->
    <div class="flex items-center gap-3 px-5 py-5 border-b border-white/[.06] shrink-0">
        <div class="w-9 h-9 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-600/40 shrink-0">
            <span class="text-white font-black text-sm tracking-tight">IN</span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-white font-bold text-sm leading-none">IPINFRA HRM</p>
            <p class="text-indigo-400 text-[9px] font-semibold tracking-[.18em] uppercase mt-1">Admin Portal</p>
        </div>
        <button onclick="toggleSidebar()" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-500 hover:text-white hover:bg-white/10 transition">
            <i class="fas fa-times text-sm"></i>
        </button>
    </div>

    <!-- User card -->
    <div class="mx-3 mt-3 mb-1 px-3 py-3 rounded-2xl shrink-0" style="background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.07);">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 flex items-center justify-center text-white font-bold text-sm shadow-md shrink-0">
                <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-white font-semibold text-sm leading-tight truncate"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                <div class="flex items-center gap-1.5 mt-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-sm shadow-emerald-400/60"></span>
                    <span class="text-[10px] text-emerald-400 font-medium">Online</span>
                </div>
            </div>
            <span class="shrink-0 text-[9px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/25 px-2 py-0.5 rounded-full tracking-wide uppercase">Admin</span>
        </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto px-3 py-2 space-y-0.5">
        <?php foreach ($_admin_nav as [$href, $icon, $label, $section]):
            if ($section !== $_prev_section):
                $_prev_section = $section;
        ?>
        <p class="sidebar-section-label <?php echo $_prev_section !== 'overview' ? 'mt-3' : ''; ?>">
            <?php echo $_section_labels[$section] ?? $section; ?>
        </p>
        <?php endif;
            $is_active  = ($_cur === $href);
            $icon_cls   = $_icon_colors[$icon] ?? 'text-slate-400 bg-slate-500/15';
        ?>
        <a href="<?php echo $href; ?>" class="sidebar-nav-item <?php echo $is_active ? 'active' : ''; ?>">
            <span class="nav-icon w-7 h-7 rounded-lg flex items-center justify-center shrink-0 <?php echo $icon_cls; ?>" style="font-size:.75rem;">
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
           class="sidebar-nav-item w-full mt-2" style="background:rgba(239,68,68,.12); color:#fca5a5; border:1px solid rgba(239,68,68,.18);"
           onmouseover="this.style.background='rgba(239,68,68,.22)'" onmouseout="this.style.background='rgba(239,68,68,.12)'">
            <span class="nav-icon w-7 h-7 rounded-lg flex items-center justify-center shrink-0 bg-red-500/15 text-red-400" style="font-size:.75rem;">
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
    const sb = document.getElementById('admin-sidebar');
    const ov = document.getElementById('overlay');
    sb.classList.toggle('-translate-x-full');
    ov.classList.toggle('hidden');
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') { document.getElementById('admin-sidebar').classList.add('-translate-x-full'); document.getElementById('overlay').classList.add('hidden'); } });
</script>
