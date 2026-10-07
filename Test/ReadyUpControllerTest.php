<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;
use Kanboard\Plugin\BattleLobby\Controller\ReadyUpController;
use Kanboard\Plugin\BattleLobby\Model\ReadyUpModel;
use Kanboard\Core\Http\Request;
use Kanboard\Core\Http\Response;
use Kanboard\Core\Security\Role;
use Kanboard\Model\TaskCreationModel;
use Kanboard\Model\TaskFinderModel;
use Kanboard\Model\UserModel;

class ReadyUpControllerTest extends Base
{
    private int $pid;
    private int $uid;
    private array $cols;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container['battleLobbyReadyUp'] = fn ($c) => new ReadyUpModel($c);
        $this->uid = (new UserModel($this->container))->create(['username' => 'runner']);
        $this->pid = $this->container['projectModel']->create(['name' => 'Squad']);
        // Default columns: Backlog, Ready, Work in progress, Done
        $this->cols = array_column($this->container['columnModel']->getAll($this->pid), 'id', 'title');
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user']);
        parent::tearDown();
    }

    private function task(string $col, int $priority, int $owner): int
    {
        return (new TaskCreationModel($this->container))->create([
            'project_id' => $this->pid, 'title' => "t-$col-$priority", 'column_id' => $this->cols[$col],
            'priority' => $priority, 'owner_id' => $owner,
        ]);
    }

    private function c(): ReadyUpController
    {
        return new ReadyUpController($this->container);
    }

    private function columnOf(int $taskId): int
    {
        return (int) (new TaskFinderModel($this->container))->getById($taskId)['column_id'];
    }

    // ---- start(): the HTTP endpoint (CSRF + move permission) ----

    /** Log in as $this->uid with $projectRole on the project; returns a valid reusable CSRF token. */
    private function loginAs(?string $projectRole): string
    {
        $_SESSION['user'] = ['id' => $this->uid, 'role' => Role::APP_USER, 'username' => 'runner'];
        if ($projectRole !== null) {
            $this->container['projectUserRoleModel']->addUser($this->pid, $this->uid, $projectRole);
        }
        return $this->container['token']->getReusableCSRFToken();
    }

    /** POST to start() and return the decoded JSON (Response::json is captured, not sent). */
    private function post(array $post): array
    {
        $captured = new \ArrayObject();
        $this->container['request'] = new Request($this->container, ['REQUEST_METHOD' => 'POST'], ['x' => '1'], $post);
        $this->container['response'] = new class ($this->container, $captured) extends Response {
            public function __construct($c, private \ArrayObject $out)
            {
                parent::__construct($c);
            }

            public function json(array $data, $statusCode = 200)
            {
                $this->out['status'] = $statusCode;
                $this->out['body'] = $data;
            }
        };
        $this->c()->start();
        $this->assertSame(200, $captured['status']);
        return $captured['body'];
    }

    public function testStartMovesForAProjectMember(): void
    {
        $id = $this->task('Ready', 2, $this->uid);
        $csrf = $this->loginAs(Role::PROJECT_MEMBER);
        $res = $this->post(['project_id' => (string) $this->pid, 'csrf_token' => $csrf]);
        $this->assertSame(['ok' => true, 'task_id' => $id, 'from_column_id' => (int) $this->cols['Ready'],
            'to_column_id' => (int) $this->cols['Work in progress']], $res);
        $this->assertSame((int) $this->cols['Work in progress'], $this->columnOf($id));
    }

    public function testStartRejectsBadCsrf(): void
    {
        $id = $this->task('Ready', 2, $this->uid);
        $this->loginAs(Role::PROJECT_MEMBER);
        $res = $this->post(['project_id' => (string) $this->pid, 'csrf_token' => 'nope']);
        $this->assertSame(['ok' => false, 'error' => 'csrf'], $res);
        $this->assertSame((int) $this->cols['Ready'], $this->columnOf($id));
    }

    public function testStartForbidsAProjectViewer(): void
    {
        $id = $this->task('Ready', 2, $this->uid);
        $csrf = $this->loginAs(Role::PROJECT_VIEWER);
        $res = $this->post(['project_id' => (string) $this->pid, 'csrf_token' => $csrf]);
        $this->assertSame(['ok' => false, 'error' => 'forbidden'], $res);
        $this->assertSame((int) $this->cols['Ready'], $this->columnOf($id));
    }

    public function testStartForbidsANonMember(): void
    {
        $id = $this->task('Ready', 2, $this->uid);
        $csrf = $this->loginAs(null);
        $res = $this->post(['project_id' => (string) $this->pid, 'csrf_token' => $csrf]);
        $this->assertSame(['ok' => false, 'error' => 'forbidden'], $res);
        $this->assertSame((int) $this->cols['Ready'], $this->columnOf($id));
    }

    public function testStartReturnsCleanErrorWhenNothingIsEligible(): void
    {
        $id = $this->task('Ready', 2, 0);
        $csrf = $this->loginAs(Role::PROJECT_MEMBER);
        $res = $this->post(['project_id' => (string) $this->pid, 'csrf_token' => $csrf]);
        $this->assertSame(['ok' => false, 'error' => 'nothing_ready'], $res);
        $this->assertSame((int) $this->cols['Ready'], $this->columnOf($id));
    }
}
