<?php
/** @var array{message: string, type: string}|null $flash */
if (!empty($flash)):
    $flashType = in_array($flash['type'] ?? '', ['success', 'info'], true) ? $flash['type'] : 'error';
    $flashIcon = ['error' => 'circle-exclamation', 'success' => 'circle-check', 'info' => 'circle-info'][$flashType];
?>
    <div class="alert alert--<?= $flashType ?>" role="<?= $flashType === 'error' ? 'alert' : 'status' ?>">
        <i class="fas fa-<?= $flashIcon ?>" aria-hidden="true"></i>
        <p><?= htmlspecialchars($flash['message']) ?></p>
    </div>
<?php endif; ?>
