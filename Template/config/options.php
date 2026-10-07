<div class="page-header"><h2><?= t('Battle Lobby') ?></h2></div>
<p class="form-help"><?= t('Defaults for everyone on this instance. Each user can override them in their profile.') ?></p>
<form method="post" action="<?= $this->url->href('OptionsController', 'save', ['plugin' => 'BattleLobby']) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>
    <?= $this->render('BattleLobby:options/_fields', ['values' => $values, 'schema' => $schema]) ?>
    <div class="form-actions"><button type="submit" class="btn btn-blue"><?= t('Save') ?></button></div>
</form>
<p class="form-help"><?= t('Fan-made theme. Not affiliated with or endorsed by any game publisher.') ?></p>
