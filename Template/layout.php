<?php

use Kanboard\Plugin\BattleLobby\Core\LayoutChain;

$battleLobbyBufferLevel = ob_get_level();
ob_start();
try {
    include LayoutChain::previous($this);
} catch (\Throwable $exception) {
    while (ob_get_level() > $battleLobbyBufferLevel) {
        if (! ob_end_clean()) {
            break;
        }
    }
    throw $exception;
}
$battleLobbyHtml = ob_get_clean();

echo $this->battleLobby->isConflictActive()
    ? $battleLobbyHtml
    : LayoutChain::withRootAttributes($battleLobbyHtml, $this->battleLobby->rootAttributes($this->user->getId() ?: null));
