<?php

use App\Support\Flash;

$messages = Flash::all();
?>
<?php if ($messages !== []): ?>
    <div class="flash-stack" aria-live="polite">
        <?php foreach ($messages as $message): ?>
            <div class="flash flash--<?= e($message['type'] ?? 'info') ?>">
                <?= e($message['message'] ?? '') ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
