<?php
$opts = $this->battleLobby->options($this->user->getId() ?: null);
$storm = $this->battleLobby->storm((int) ($task['date_due'] ?? 0), time());
?>
<?php if ($opts['storm'] && $storm['state'] !== 'none'): ?>
<span class="bl-storm bl-storm-<?= $storm['state'] ?>" aria-label="<?= $this->text->e(t('Due') . ': ' . $storm['label']) ?>">
    <i class="fa <?= $storm['state'] === 'far' ? 'fa-clock-o' : ($storm['state'] === 'soon' ? 'fa-bolt' : 'fa-exclamation-triangle') ?>" aria-hidden="true"></i>
    <?= $this->text->e($storm['label']) ?>
</span>
<?php endif ?>
