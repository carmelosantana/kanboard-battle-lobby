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

        $this->helper->register('battleLobby', \Kanboard\Plugin\BattleLobby\Helper\LobbyHelper::class);
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
