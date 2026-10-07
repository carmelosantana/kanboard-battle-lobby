<div class="page-header"><h2><?= t('Battle Lobby') ?></h2></div>
<form method="post" action="<?= $this->url->href('OptionsController', 'userSave', ['plugin' => 'BattleLobby', 'user_id' => $user['id']]) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>
    <?= $this->render('BattleLobby:options/_fields', ['values' => $values, 'schema' => $schema]) ?>
    <div class="form-actions"><button type="submit" class="btn btn-blue"><?= t('Save') ?></button></div>
</form>
<form method="post" action="<?= $this->url->href('OptionsController', 'userReset', ['plugin' => 'BattleLobby', 'user_id' => $user['id']]) ?>">
    <?= $this->form->csrf() ?>
    <button type="submit" class="btn"><?= t('Use admin defaults') ?></button>
</form>
