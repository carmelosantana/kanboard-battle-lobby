<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;
use Kanboard\Plugin\BattleLobby\Helper\LobbyHelper;
use Kanboard\Plugin\BattleLobby\Model\OptionsModel;
use Kanboard\Plugin\BattleLobby\Model\ReadyUpModel;
use Kanboard\Core\Http\Request;
use Kanboard\Core\Security\Role;
use Kanboard\Model\TaskCreationModel;
use Kanboard\Model\UserModel;

class LobbyHelperTest extends Base
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->container['battleLobbyOptions'] = fn ($c) => new OptionsModel($c);
        $this->container['battleLobbyReadyUp'] = fn ($c) => new ReadyUpModel($c);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user']);
        parent::tearDown();
    }

    private function h(): LobbyHelper
    {
        return new LobbyHelper($this->container);
    }

    public function testTierDefaultRangeZeroToThree(): void
    {
        $p = ['priority_start' => 0, 'priority_end' => 3];
        $this->assertSame([0, 1, 2, 3], array_map(fn ($pr) => $this->h()->tier(['priority' => $pr], $p), [0, 1, 2, 3]));
    }

    public function testTierUsesProjectRangeWideRangeReachesLegendary(): void
    {
        $p = ['priority_start' => 1, 'priority_end' => 5];
        $this->assertSame([0, 1, 2, 3, 4], array_map(fn ($pr) => $this->h()->tier(['priority' => $pr], $p), [1, 2, 3, 4, 5]));
    }

    public function testTierUsesProjectRangeNarrowRange(): void
    {
        $p = ['priority_start' => 0, 'priority_end' => 1];
        $this->assertSame(0, $this->h()->tier(['priority' => 0], $p));
        $this->assertSame(3, $this->h()->tier(['priority' => 1], $p)); // top of a short range = EPIC, never LEGENDARY
    }

    public function testTierUsesProjectRangeOnlyTopIsLegendary(): void
    {
        $p = ['priority_start' => 0, 'priority_end' => 8];
        $this->assertSame(4, $this->h()->tier(['priority' => 8], $p));
        $this->assertSame(3, $this->h()->tier(['priority' => 7], $p));
        $this->assertSame(3, $this->h()->tier(['priority' => 9], ['priority_start' => 0, 'priority_end' => 10]));
        $this->assertSame(4, $this->h()->tier(['priority' => 10], ['priority_start' => 0, 'priority_end' => 10]));
    }

    public function testTierClampsOutOfRangeAndMissing(): void
    {
        $p = ['priority_start' => 0, 'priority_end' => 3];
        $this->assertSame(0, $this->h()->tier(['priority' => -5], $p));
        $this->assertSame(3, $this->h()->tier(['priority' => 99], $p));
        $this->assertSame(0, $this->h()->tier([], []));
    }

    public function testTierLabels(): void
    {
        $this->assertSame(['COMMON', 'UNCOMMON', 'RARE', 'EPIC', 'LEGENDARY'], array_map(fn ($t) => $this->h()->tierLabel($t), [0, 1, 2, 3, 4]));
    }

    public function testStormStates(): void
    {
        $now = 1_800_000_000;
        $this->assertSame(['state' => 'none', 'label' => ''], $this->h()->storm(0, $now));
        $this->assertSame(['state' => 'far', 'label' => '3d'], $this->h()->storm($now + 3 * 86400 + 10, $now));
        $this->assertSame(['state' => 'soon', 'label' => '5h'], $this->h()->storm($now + 5 * 3600, $now));
        $this->assertSame(['state' => 'soon', 'label' => '<1h'], $this->h()->storm($now + 600, $now));
        $this->assertSame(['state' => 'late', 'label' => '2d late'], $this->h()->storm($now - 2 * 86400 - 5, $now));
        $this->assertSame(['state' => 'late', 'label' => 'late'], $this->h()->storm($now - 60, $now));
    }

    public function testPayloadAttributesCarryOptionsAndEndpoints(): void
    {
        $this->container['battleLobbyOptions']->saveAdmin(['characters' => 'night', 'blur' => 4]);
        $attrs = $this->h()->payloadAttributes(null, 'board');
        $this->assertStringContainsString('data-bl-context="board"', $attrs);
        $this->assertStringContainsString('data-bl-characters="night"', $attrs);
        $this->assertStringContainsString('data-bl-blur="4"', $attrs);
        $this->assertStringContainsString('data-bl-glass="1"', $attrs);
        $this->assertStringContainsString('data-bl-feed="0"', $attrs);
        $this->assertStringContainsString('data-bl-assets="', $attrs);
        $this->assertStringContainsString('plugins/BattleLobby/Assets/', $attrs);
        $this->assertStringContainsString('data-bl-readyup-url="', $attrs);
        $this->assertStringContainsString('data-bl-csrf="', $attrs);
        $this->assertStringNotContainsString('<', $attrs);
    }

    public function testRejectsUnknownContext(): void
    {
        $this->assertStringContainsString('data-bl-context="page"', $this->h()->payloadAttributes(null, '"><script>'));
    }

    public function testProjectIsCachedAndOptionsPassThrough(): void
    {
        $pid = $this->container['projectModel']->create(['name' => 'cache']);
        $h = $this->h();
        $this->assertSame('cache', $h->project($pid)['name']);
        $this->container['projectModel']->update(['id' => $pid, 'name' => 'renamed']);
        $this->assertSame('cache', $h->project($pid)['name']);
        $this->assertSame([], $h->project(0));
        $this->assertSame('mixed', $h->options(null)['characters']);
    }

    /** Board page for a logged-in user with $role on a project; $owned = a task of theirs sits in Ready. */
    private function boardPayload(string $role, bool $owned): string
    {
        $uid = (new UserModel($this->container))->create(['username' => 'runner']);
        $pid = $this->container['projectModel']->create(['name' => 'Squad']);
        $ready = array_column($this->container['columnModel']->getAll($pid), 'id', 'title')['Ready'];
        (new TaskCreationModel($this->container))->create(['project_id' => $pid, 'title' => 't', 'column_id' => $ready, 'owner_id' => $owned ? $uid : 0]);
        $this->container['projectUserRoleModel']->addUser($pid, $uid, $role);
        $_SESSION['user'] = ['id' => $uid, 'role' => Role::APP_USER, 'username' => 'runner'];
        $this->container['request'] = new Request($this->container, ['REQUEST_METHOD' => 'GET'], ['project_id' => (string) $pid], []);
        return $this->h()->payloadAttributes($uid, 'page');
    }

    public function testReadyupReadyFlagWhenEligible(): void
    {
        $this->assertStringContainsString('data-bl-readyup-ready="1"', $this->boardPayload(Role::PROJECT_MEMBER, true));
    }

    public function testReadyupReadyFlagOffWhenNothingReady(): void
    {
        $this->assertStringNotContainsString('data-bl-readyup-ready="1"', $this->boardPayload(Role::PROJECT_MEMBER, false));
    }

    public function testReadyupReadyFlagOffForAViewer(): void
    {
        $this->assertStringNotContainsString('data-bl-readyup-ready="1"', $this->boardPayload(Role::PROJECT_VIEWER, true));
    }

    public function testReadyupReadyFlagOffWithoutAProject(): void
    {
        $this->assertStringNotContainsString('data-bl-readyup-ready="1"', $this->h()->payloadAttributes(null, 'page'));
    }
}
