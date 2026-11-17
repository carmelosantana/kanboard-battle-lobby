<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;

/**
 * 1.1.0: everything sits square (no skew, rotation, slanted clip-paths or italic display type),
 * and the card/stage layout bugs the slanted 1.0.0 hid are fixed.
 */
class SquareLayoutTest extends Base
{
    private function css(): string
    {
        return file_get_contents(dirname(__DIR__) . '/Assets/css/battle-lobby.css');
    }

    /** Body of the first rule whose selector list is exactly $selector (top level). */
    private function rule(string $selector): string
    {
        $this->assertSame(1, preg_match('/(?:^|\})\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/m', $this->css(), $m), "rule $selector");
        return $m[1];
    }

    public function testNothingIsSlanted(): void
    {
        $root = dirname(__DIR__);
        foreach (['Assets/css/battle-lobby.css', 'Assets/js/battle-lobby.js', 'Template/layout/head.php'] as $f) {
            $src = file_get_contents("$root/$f");
            $this->assertDoesNotMatchRegularExpression('/skew|rotate|polygon\(|font-style:\s*italic|\bitalic\b|--bl-cr\b|Italic\.woff2/i', $src, "$f still slants something");
        }
        $this->assertFileDoesNotExist("$root/Assets/fonts/barlowcondensed/BarlowCondensed-BlackItalic.woff2", 'unused italic face should not ship');
    }

    public function testStormChipClearsTheTierEdge(): void
    {
        // 1.0.0: the 6px inset tier edge painted over the storm chip, and overflow:hidden clipped it
        $this->assertSame(1, preg_match('/inset 0 -(\d+)px 0 var\(--bl-tier-0-edge\)/', $this->css(), $edge));
        $this->assertSame(1, preg_match('/padding-bottom:\s*(\d+)px/', $this->rule('.task-board'), $pad), '.task-board needs a padding-bottom');
        $this->assertGreaterThanOrEqual((int) $edge[1] + 4, (int) $pad[1]);
    }

    public function testPhonesHideTheLineupSoItDoesNotCoverTheBoard(): void
    {
        $this->assertMatchesRegularExpression('/@media \(max-width:\s*640px\)\s*\{[^@]*\.bl-lineup\s*\{\s*display:\s*none/s', $this->css());
        $this->assertMatchesRegularExpression('/@media \(max-width:\s*640px\)\s*\{[^@]*#board-container\s*\{\s*padding-bottom:/s', $this->css());
    }

    public function testVictoryTitleScalesToTheViewport(): void
    {
        // 1.0.0: a fixed 96px title wrapped "#1 VICTORY" onto two lines on a 390px phone
        $this->assertMatchesRegularExpression('/font:\s*900 clamp\(/', $this->rule('.bl-victory-title'));
    }
}
