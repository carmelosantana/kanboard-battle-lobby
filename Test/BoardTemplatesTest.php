<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;
use Kanboard\Plugin\BattleLobby\Plugin;
use Kanboard\Plugin\BattleLobby\Model\OptionsModel;

class BoardTemplatesTest extends Base
{
    protected function setUp(): void
    {
        parent::setUp();
        (new Plugin($this->container))->initialize();
    }

    private function render(string $tpl, array $params): string
    {
        return $this->container['template']->render($tpl, $params);
    }

    public function testTierLabelUsesProjectRange(): void
    {
        $html = $this->render('BattleLobby:board/tier', [
            'task' => ['priority' => 5, 'project_id' => 1],
            'project' => ['priority_start' => 1, 'priority_end' => 5],
        ]);
        $this->assertStringContainsString('class="bl-tier"', $html);
        $this->assertStringContainsString('data-tier="4"', $html);
        $this->assertStringContainsString('LEGENDARY', $html);
    }

    public function testTierFallsBackToProjectLookupWhenNotPassed(): void
    {
        $pid = $this->container['projectModel']->create(['name' => 'p']);
        $html = $this->render('BattleLobby:board/tier', ['task' => ['priority' => 3, 'project_id' => $pid]]);
        $this->assertStringContainsString('data-tier="3"', $html);
        $this->assertStringContainsString('EPIC', $html);
    }

    public function testStormChipStatesAndToggle(): void
    {
        $soon = $this->render('BattleLobby:board/storm', ['task' => ['date_due' => time() + 3600 * 5]]);
        $this->assertStringContainsString('bl-storm-soon', $soon);
        $this->assertStringContainsString('aria-label=', $soon);
        $this->assertSame('', trim($this->render('BattleLobby:board/storm', ['task' => ['date_due' => 0]])));

        (new OptionsModel($this->container))->saveAdmin(['storm' => false]);
        $this->assertSame('', trim($this->render('BattleLobby:board/storm', ['task' => ['date_due' => time() + 600]])));
    }

    public function testStageCarriesPayloadAndIsCspSafe(): void
    {
        $html = $this->render('BattleLobby:layout/stage', []);
        $this->assertStringContainsString('id="bl-stage"', $html);
        $this->assertStringContainsString('data-bl-characters=', $html);
        foreach (['board/tier.php', 'board/storm.php', 'layout/stage.php'] as $t) {
            $src = file_get_contents(dirname(__DIR__) . '/Template/' . $t);
            $this->assertStringNotContainsString('<script', $src);
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*["\']/i', $src);
        }
    }

    public function testCssFramesCardsByTierAndKeepsThemOpaque(): void
    {
        $css = file_get_contents(dirname(__DIR__) . '/Assets/css/battle-lobby.css');
        for ($t = 0; $t <= 4; $t++) {
            $this->assertStringContainsString('.task-board:has(.bl-tier[data-tier="' . $t . '"])', $css);
        }
        $this->assertStringContainsString('.bl-storm-soon', $css);
        $this->assertStringContainsString('.views', $css);
    }
}
