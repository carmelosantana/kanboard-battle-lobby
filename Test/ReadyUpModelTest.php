<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;
use Kanboard\Plugin\BattleLobby\Model\ReadyUpModel;
use Kanboard\Core\Security\Role;
use Kanboard\Core\Translator;
use Kanboard\Model\TaskCreationModel;
use Kanboard\Model\TaskFinderModel;
use Kanboard\Model\UserModel;

class ReadyUpModelTest extends Base
{
    private int $pid;
    private int $uid;
    private array $cols;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uid = (new UserModel($this->container))->create(['username' => 'runner']);
        $this->pid = $this->container['projectModel']->create(['name' => 'Squad']);
        // Default columns: Backlog, Ready, Work in progress, Done
        $this->cols = array_column($this->container['columnModel']->getAll($this->pid), 'id', 'title');
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user']);
        Translator::unload();
        parent::tearDown();
    }

    private function task(string $col, int $priority, int $owner, ?int $pid = null, ?array $cols = null): int
    {
        return (new TaskCreationModel($this->container))->create([
            'project_id' => $pid ?? $this->pid, 'title' => "t-$col-$priority", 'column_id' => ($cols ?? $this->cols)[$col],
            'priority' => $priority, 'owner_id' => $owner,
        ]);
    }

    private function m(): ReadyUpModel
    {
        return new ReadyUpModel($this->container);
    }

    private function loginAs(?string $projectRole): void
    {
        $_SESSION['user'] = ['id' => $this->uid, 'role' => Role::APP_USER, 'username' => 'runner'];
        if ($projectRole !== null) {
            $this->container['projectUserRoleModel']->addUser($this->pid, $this->uid, $projectRole);
        }
    }

    public function testPicksHighestPriorityOwnedReadyTaskAndTargetsNextColumn(): void
    {
        $this->task('Ready', 1, $this->uid);
        $top = $this->task('Ready', 3, $this->uid);
        $this->task('Ready', 3, 0);            // unassigned: ignored
        $this->task('Backlog', 3, $this->uid); // wrong column: ignored
        $r = $this->m()->pick($this->pid, $this->uid);
        $this->assertSame($top, (int) $r['task']['id']);
        $this->assertSame((int) $this->cols['Ready'], $r['from']);
        $this->assertSame((int) $this->cols['Work in progress'], $r['to']);
    }

    public function testNothingReady(): void
    {
        $this->task('Ready', 2, 0);
        $this->assertSame('nothing_ready', $this->m()->pick($this->pid, $this->uid)['error']);
    }

    public function testNoReadyColumn(): void
    {
        $this->container['columnModel']->update($this->cols['Ready'], 'Queue');
        $this->assertSame('no_ready_column', $this->m()->pick($this->pid, $this->uid)['error']);
    }

    public function testReadyIsLastColumn(): void
    {
        foreach (['Work in progress', 'Done'] as $t) {
            $this->container['columnModel']->remove($this->cols[$t]);
        }
        $this->task('Ready', 2, $this->uid);
        $this->assertSame('ready_is_last', $this->m()->pick($this->pid, $this->uid)['error']);
    }

    public function testMatchesTheLocalizedReadyColumn(): void
    {
        Translator::load('fr_FR');
        $pid = $this->container['projectModel']->create(['name' => 'Escouade']);
        $cols = array_column($this->container['columnModel']->getAll($pid), 'id', 'title');
        $this->assertArrayHasKey('Prêt', $cols);
        $id = $this->task('Prêt', 2, $this->uid, $pid, $cols);
        $r = $this->m()->pick($pid, $this->uid);
        $this->assertSame($id, (int) $r['task']['id']);
        $this->assertSame((int) $cols['Prêt'], $r['from']);
    }

    public function testMoveActuallyMovesTheTask(): void
    {
        $id = $this->task('Ready', 2, $this->uid);
        $r = $this->m()->pick($this->pid, $this->uid);
        $this->assertTrue($this->m()->move($this->pid, $r));
        $this->assertSame((int) $this->cols['Work in progress'], (int) (new TaskFinderModel($this->container))->getById($id)['column_id']);
    }

    public function testEligibleForAMemberWithAReadyTask(): void
    {
        $id = $this->task('Ready', 2, $this->uid);
        $this->loginAs(Role::PROJECT_MEMBER);
        $this->assertSame($id, (int) $this->m()->eligible($this->pid, $this->uid)['task']['id']);
    }

    public function testEligibleIsForbiddenForAViewerAndANonMember(): void
    {
        $this->task('Ready', 2, $this->uid);
        $this->loginAs(Role::PROJECT_VIEWER);
        $this->assertSame(['error' => 'forbidden'], $this->m()->eligible($this->pid, $this->uid));
        unset($_SESSION['user']);
        $this->assertSame(['error' => 'forbidden'], $this->m()->eligible($this->pid, $this->uid));
    }

    public function testEligiblePassesPickErrorsThrough(): void
    {
        $this->loginAs(Role::PROJECT_MEMBER);
        $this->assertSame(['error' => 'nothing_ready'], $this->m()->eligible($this->pid, $this->uid));
    }
}
