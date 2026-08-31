<?php
// Assets-section bottom navigation (assets.php + gallery.php).
$_enav_cur = basename($_SERVER['PHP_SELF']);
$_enav_items = [
    ['dashboard.php', 'fa-home',    'Home'],
    ['assets.php',    'fa-boxes',   'Assets'],
    ['gallery.php',   'fa-images',  'Gallery'],
    ['leave.php',     'fa-calendar-alt', 'Leave'],
    ['profile.php',   'fa-user',    'Profile'],
];
?>
<div class="bottom-nav fixed bottom-0 left-0 right-0 bg-white border-t border-gray-100 md:hidden shadow-lg z-20">
    <div class="flex justify-around py-1.5">
        <?php foreach ($_enav_items as [$href, $icon, $label]):
            $active = ($_enav_cur === $href);
        ?>
        <a href="<?php echo $href; ?>"
           class="flex flex-col items-center py-1.5 px-3 transition-colors <?php echo $active ? 'text-indigo-600' : 'text-gray-400 hover:text-gray-600'; ?>">
            <i class="fas <?php echo $icon; ?> text-xl"></i>
            <span class="text-xs mt-0.5 font-medium"><?php echo $label; ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
