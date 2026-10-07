<li <?= $this->app->checkMenuSelection('OptionsController', 'user', 'BattleLobby') ?>>
    <?= $this->url->link(t('Battle Lobby'), 'OptionsController', 'user', ['plugin' => 'BattleLobby', 'user_id' => $user['id']]) ?>
</li>
