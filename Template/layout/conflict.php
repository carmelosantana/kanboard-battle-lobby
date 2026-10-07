<?php if ($this->battleLobby->isConflictActive() && $this->user->isAdmin()): ?>
<div class="bl-conflict" role="alert"><?= t('Battle Lobby and ShadcnTheme are both active. They restyle the same pages: disable one of them.') ?></div>
<?php endif ?>
