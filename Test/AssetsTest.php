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

    public function testCssRemapsCoreTextColoursForDarkSurfaces(): void
    {
        // e2e on v1.2.52 (kanboard/kanboard:latest): core light.min.css paints h1, column-title links, card "#id" and card links #333/#000 on dark glass
        $css = file_get_contents($this->root() . '/Assets/css/battle-lobby.css');
        $this->assertMatchesRegularExpression('/:root\s*\{[^}]*--color-primary:\s*var\(--bl-text\)/', $css);
        $this->assertMatchesRegularExpression('/\.task-board a\s*\{[^}]*color:\s*var\(--bl-text\)/', $css);
        // remapping --color-primary to light text needs core's white menus/tooltips dark too
        foreach (['--dropdown-background-color', '--tooltip-background-color', '--draggable-placeholder-background-color'] as $v) {
            $this->assertMatchesRegularExpression('/:root\\s*\\{[^}]*' . $v . ':\\s*var\\(--bl-/', $css, $v);
        }
        $this->assertMatchesRegularExpression('/#suggest-menu\\s*\\{[^}]*background:\\s*var\\(--bl-surface\\)/', $css);
        // core literal colours the tokens cannot reach (due dates #000/#b94a48, list counts #767676, details #555)
        $this->assertMatchesRegularExpression('/\\.task-date\\s*\\{[^}]*color:\\s*var\\(--bl-text\\)/', $css);
        $this->assertMatchesRegularExpression('/span\\.task-date-overdue\\s*\\{[^}]*color:\\s*var\\(--bl-danger\\)/', $css);
        $this->assertMatchesRegularExpression('/\\.table-list-header \\.table-list-header-count[^{]*\\{[^}]*color:\\s*var\\(--bl-text-muted\\)/', $css);
        // the task summary keeps the task's pastel colour, so it keeps dark text
        $this->assertMatchesRegularExpression('/\\.task-summary-container\\s*\\{[^}]*--color-primary:\\s*#/', $css);
        // core .markdown pre is #fbfbfb with var(--color-medium) text → needs a dark surface once the token is light
        $this->assertMatchesRegularExpression('/\\.markdown pre\\s*\\{[^}]*background:\\s*var\\(--bl-surface-2\\)/', $css);
        // settings/profile content (.sidebar-content) sat bare on the art; core .form-help is brown (#a52a2a)
        $this->assertMatchesRegularExpression('/\.sidebar-content[^{]*\{[^}]*background:\s*var\(--bl-surface\)/', $css);
        $this->assertMatchesRegularExpression('/\.form-help\s*\{[^}]*color:\s*var\(--bl-text-muted\)/', $css);
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
