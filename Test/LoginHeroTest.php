<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;
use Kanboard\Plugin\BattleLobby\Plugin;

class LoginHeroTest extends Base
{
    public function testHeroCarriesLoginPayloadAndStage(): void
    {
        (new Plugin($this->container))->initialize();
        $html = $this->container['template']->render('BattleLobby:auth/hero', []);
        $this->assertStringContainsString('class="bl-hero"', $html);
        $this->assertStringContainsString('data-bl-context="login"', $html);
        $this->assertStringContainsString('class="bl-hero-stage"', $html);
        $src = file_get_contents(dirname(__DIR__) . '/Template/auth/hero.php');
        $this->assertStringNotContainsString('<script', $src);
    }

    public function testLoginHookRegistered(): void
    {
        (new Plugin($this->container))->initialize();
        // Kanboard 1.2.47: HookHelper::attach() stores ['template' => …] listeners on the core Hook; no getHooks().
        $templates = array_column($this->container['hook']->getListeners('template:auth:login-form:before'), 'template');
        $this->assertContains('BattleLobby:auth/hero', $templates);
    }

    public function testRealLoginPageRendersHeroAndAssetsWithNoLayout(): void
    {
        (new Plugin($this->container))->initialize();
        $html = $this->container['helper']->layout->app('auth/index', [
            'captcha' => false, 'errors' => [], 'values' => [], 'no_layout' => true, 'title' => 'Login',
        ]);
        $this->assertStringContainsString('plugins/BattleLobby/Assets/css/battle-lobby.css', $html);
        $this->assertStringContainsString('plugins/BattleLobby/Assets/js/battle-lobby.js', $html);
        $this->assertSame(1, substr_count($html, 'data-bl-assets='), 'only the hero carries a payload (no #bl-stage with no_layout)');
        $this->assertStringNotContainsString('id="bl-stage"', $html);
        $this->assertMatchesRegularExpression('/<div class="form-login">\s*<div class="bl-hero" [^>]*data-bl-context="login"/', $html);
    }

    /** @return array<int,array{0:string,1:string}> [selector, body] for every flat rule in the plugin CSS */
    private function cssRules(): array
    {
        $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(dirname(__DIR__) . '/Assets/css/battle-lobby.css'));
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER);
        return array_map(fn ($r) => [trim($r[1]), $r[2]], $m);
    }

    public function testLoginCardNeverBecomesAContainingBlockForTheFixedMascot(): void
    {
        // backdrop-filter / filter / transform on .form-login would trap the position:fixed mascot inside the card.
        $offending = [];
        foreach ($this->cssRules() as [$selector, $body]) {
            foreach (array_map('trim', explode(',', $selector)) as $sel) {
                if (preg_match('/\.form-login$/', $sel) && preg_match('/(^|[;\s])(-webkit-)?(backdrop-filter|filter|transform|perspective|contain|will-change)\s*:/', $body)) {
                    $offending[] = $sel;
                }
            }
        }
        $this->assertSame([], $offending);
        $this->assertNotEmpty(array_filter($this->cssRules(), fn ($r) => str_contains($r[0], '.form-login::before') && str_contains($r[1], 'backdrop-filter')), 'glass lives on .form-login::before');
    }

    public function testHeroStageIgnoresPointerAndTitleScales(): void
    {
        $stage = array_filter($this->cssRules(), fn ($r) => $r[0] === '.bl-hero-stage' && preg_match('/pointer-events:\s*none/', $r[1]));
        $this->assertNotEmpty($stage, '.bl-hero-stage { pointer-events: none }');
        $behind = array_filter($this->cssRules(), fn ($r) => $r[0] === '.bl-hero-stage .bl-mascot' && preg_match('/z-index:\s*-2/', $r[1]));
        $this->assertNotEmpty($behind, 'mascot paints behind the card glass (::before is z-index -1), never over the form');
        $title = array_values(array_filter($this->cssRules(), fn ($r) => $r[0] === '.bl-hero-title'));
        $this->assertNotEmpty($title);
        $this->assertStringContainsString('clamp(', $title[0][1]);
    }
}
