<?php
$userName = $user['display_name'] ?? '';
?>
<header class="topbar">
    <div>
        <span class="eyebrow">Ritterlager Manager</span>
        <h1><?= e($title) ?></h1>
    </div>
    <div class="topbar-actions">
        <span class="day-chip"><?= e($topbarDayChip) ?></span>
        <span class="user-chip"><?= e($userName) ?></span>
    </div>
</header>
