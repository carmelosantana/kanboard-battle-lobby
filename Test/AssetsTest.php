<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;
use Kanboard\Plugin\BattleLobby\Plugin;

class AssetsTest extends Base
{
    private function root(): string
    {
        return dirname(__DIR__);
    }

    public function testFontsAndLicenseShip(): void
    {
        foreach (['BarlowCondensed-Black.woff2', 'BarlowCondensed-BlackItalic.woff2', 'OFL.txt'] as $f) {
            $this->assertFileExists($this->root() . '/Assets/fonts/barlowcondensed/' . $f);
        }
        $this->assertSame('wOF2', substr(file_get_contents($this->root() . '/Assets/fonts/barlowcondensed/BarlowCondensed-Black.woff2'), 0, 4));
        $this->assertStringContainsString('SIL Open Font License', file_get_contents($this->root() . '/Assets/fonts/barlowcondensed/OFL.txt'));
    }

    public function testCssDeclaresSelfHostedFontAndTokens(): void
    {
        $css = file_get_contents($this->root() . '/Assets/css/battle-lobby.css');
        $this->assertStringContainsString("url('../fonts/barlowcondensed/BarlowCondensed-Black.woff2')", $css);
        $this->assertStringContainsString('font-display: swap', $css);
        foreach (['--bl-bg', '--bl-glass', '--bl-cta', '--bl-tier-4-in', '--bl-storm', '--bl-font-display'] as $token) {
            $this->assertStringContainsString($token . ':', $css);
        }
        $this->assertStringContainsString('prefers-reduced-motion', $css);
        $this->assertStringContainsString('prefers-reduced-transparency', $css);
        $this->assertStringContainsString('@supports not (backdrop-filter', $css);
    }

    public function testCssKeepsTaskCardsOpaque(): void
    {
        $css = file_get_contents($this->root() . '/Assets/css/battle-lobby.css');
        $this->assertDoesNotMatchRegularExpression('/\.task-board[^{]*\{[^}]*backdrop-filter/s', $css, 'cards must never use backdrop-filter');
    }

    public function testBackgroundsWithinBudget(): void
    {
        foreach (['map', 'storm', 'island', 'victory'] as $k) {
            $f = $this->root() . "/Assets/backgrounds/$k.webp";
            $this->assertFileExists($f);
            $this->assertLessThanOrEqual(400 * 1024, filesize($f), "$k.webp over 400 KB");
        }
    }

    public function testAssetHooksRegistered(): void
    {
        (new Plugin($this->container))->initialize();
        $css = $this->container['hook']->getListeners('template:layout:css');
        $js = $this->container['hook']->getListeners('template:layout:js');
        $this->assertContains(['template' => 'plugins/BattleLobby/Assets/css/battle-lobby.css'], $css);
        $this->assertContains(['template' => 'plugins/BattleLobby/Assets/js/battle-lobby.js'], $js);
    }

    public function testHeadAndConflictTemplatesAreCspSafe(): void
    {
        foreach (['layout/head.php', 'layout/conflict.php'] as $t) {
            $src = file_get_contents($this->root() . '/Template/' . $t);
            $this->assertStringNotContainsString('<script', $src);
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*["\']/i', $src);
        }
    }

    public function testConflictBannerRendersOnlyForAdminsWhenConflictActive(): void
    {
        $src = file_get_contents($this->root() . '/Template/layout/conflict.php');
        $this->assertStringContainsString('isConflictActive()', $src);
        $this->assertStringContainsString('isAdmin()', $src);
    }
}
