<?php

namespace Kanboard\Plugin\BattleLobby;

use Kanboard\Core\Plugin\Base;

/**
 * Battle Lobby — a battle-royale lobby theme for Kanboard.
 *
 * Hooks + external assets only (no template overrides). Options resolve
 * user → admin → built-in via OptionsModel; per-task presentation lives in
 * LobbyHelper; JS reads the data-bl-* payload (CSP-safe).
 */
class Plugin extends Base
{
    public function initialize(): void
    {
        $this->container['battleLobbyOptions'] = function ($c) {
            return new \Kanboard\Plugin\BattleLobby\Model\OptionsModel($c);
        };
        $this->container['battleLobbyReadyUp'] = function ($c) {
            return new \Kanboard\Plugin\BattleLobby\Model\ReadyUpModel($c);
        };
        $this->container['battleLobbyFeed'] = function ($c) {
            return new \Kanboard\Plugin\BattleLobby\Model\FeedModel($c);
        };

        $this->helper->register('battleLobby', \Kanboard\Plugin\BattleLobby\Helper\LobbyHelper::class);

        $this->hook->on('template:layout:css', ['template' => 'plugins/BattleLobby/Assets/css/battle-lobby.css']);
        $this->hook->on('template:layout:js', ['template' => 'plugins/BattleLobby/Assets/js/battle-lobby.js']);
        $this->template->hook->attach('template:layout:head', 'BattleLobby:layout/head');
        $this->template->hook->attach('template:layout:top', 'BattleLobby:layout/conflict');
        $this->route->addRoute('battlelobby/config', 'OptionsController', 'show', 'BattleLobby');
        $this->route->addRoute('battlelobby/config/save', 'OptionsController', 'save', 'BattleLobby');
        $this->route->addRoute('battlelobby/user/:user_id', 'OptionsController', 'user', 'BattleLobby');
        $this->route->addRoute('battlelobby/user/:user_id/save', 'OptionsController', 'userSave', 'BattleLobby');
        $this->route->addRoute('battlelobby/user/:user_id/reset', 'OptionsController', 'userReset', 'BattleLobby');
        $this->route->addRoute('battlelobby/ready-up', 'ReadyUpController', 'start', 'BattleLobby');
        $this->route->addRoute('battlelobby/feed', 'FeedController', 'recent', 'BattleLobby');
        $this->route->addRoute('battlelobby/thanks', 'FeedController', 'thanks', 'BattleLobby');
        $this->template->hook->attach('template:config:sidebar', 'BattleLobby:config/sidebar');
        $this->template->hook->attach('template:user:sidebar:actions', 'BattleLobby:user/sidebar');
        $this->template->hook->attach('template:board:private:task:before-title', 'BattleLobby:board/tier');
        $this->template->hook->attach('template:board:public:task:before-title', 'BattleLobby:board/tier');
        $this->template->hook->attach('template:board:task:footer', 'BattleLobby:board/storm');
        $this->template->hook->attach('template:layout:bottom', 'BattleLobby:layout/stage');
        $this->template->hook->attach('template:auth:login-form:before', 'BattleLobby:auth/hero');
    }

    public function isPhpCompatible(?int $versionId = null): bool
    {
        return ($versionId ?? PHP_VERSION_ID) >= 80400;
    }

    public function getPluginName(): string
    {
        return 'Battle Lobby';
    }

    public function getPluginDescription(): string
    {
        return t('Battle-royale lobby theme: original characters over painted backgrounds, rarity-tier cards, storm due dates, READY UP and a victory screen.');
    }

    public function getPluginAuthor(): string
    {
        return 'Carmelo Santana';
    }

    public function getPluginVersion(): string
    {
        return '1.0.0';
    }

    public function getCompatibleVersion(): string
    {
        return '>=1.2.47';
    }

    public function getPluginHomepage(): string
    {
        return 'https://github.com/carmelosantana/kanboard-battle-lobby';
    }
}
