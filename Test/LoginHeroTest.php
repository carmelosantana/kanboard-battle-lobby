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
}
