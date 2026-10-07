<li <?= $this->app->checkMenuSelection('OptionsController', 'show', 'BattleLobby') ?>>
    <?= $this->url->link(t('Battle Lobby'), 'OptionsController', 'show', ['plugin' => 'BattleLobby']) ?>
</li>
