<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;
use Kanboard\Plugin\BattleLobby\Plugin;

class PluginTest extends Base
{
    public function testMetadata(): void
    {
        $plugin = new Plugin($this->container);
        $this->assertSame('Battle Lobby', $plugin->getPluginName());
        $this->assertSame('1.0.0', $plugin->getPluginVersion());
        $this->assertSame('Carmelo Santana', $plugin->getPluginAuthor());
        $this->assertSame('>=1.2.47', $plugin->getCompatibleVersion());
        $this->assertStringContainsString('kanboard-battle-lobby', $plugin->getPluginHomepage());
        $this->assertStringContainsString('lobby', strtolower($plugin->getPluginDescription()));
    }

    public function testPhpGate(): void
    {
        $plugin = new Plugin($this->container);
        $this->assertTrue($plugin->isPhpCompatible(80400));
        $this->assertFalse($plugin->isPhpCompatible(80399));
    }

    public function testInitializeRuns(): void
    {
        $plugin = new Plugin($this->container);
        $plugin->initialize();
        $this->assertTrue(true);
    }
}
