<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;

class PluginMetaTest extends Base
{
    private function json(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__) . '/plugin.json'), true);
    }

    public function testNameVersionCompatLicense(): void
    {
        $j = $this->json();
        $this->assertSame('BattleLobby', $j['name']);
        $this->assertSame('1.0.0', $j['version']);
        $this->assertSame('>=1.2.47', $j['kanboard_version']);
        $this->assertSame('>=8.4', $j['php_version']);
        $this->assertSame('MIT', $j['license']);
        $this->assertSame('https://github.com/carmelosantana/kanboard-battle-lobby', $j['homepage']);
    }

    public function testVersionAgreesAcrossFiles(): void
    {
        $version = $this->json()['version'];
        $plugin = file_get_contents(dirname(__DIR__) . '/Plugin.php');
        $this->assertMatchesRegularExpression("/getPluginVersion\(\)[^}]*return '" . preg_quote($version, '/') . "'/s", $plugin);
        $this->assertStringContainsString('## ' . $version . ' — ', file_get_contents(dirname(__DIR__) . '/CHANGELOG.md'));
    }

    public function testRecommendsTimeReportAndConflictsShadcn(): void
    {
        $j = $this->json();
        $this->assertArrayNotHasKey('requires', $j);
        $this->assertSame(['TimeReport'], array_column($j['recommends'], 'plugin'));
        $this->assertSame('1.5.0', $j['recommends'][0]['min_version']);
        $this->assertSame(['ShadcnTheme'], $j['conflicts']);
    }
}
