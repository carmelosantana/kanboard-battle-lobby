<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;
use Kanboard\Plugin\BattleLobby\Controller\FeedController;
use Kanboard\Plugin\BattleLobby\Model\FeedModel;
use Kanboard\Core\Http\Request;
use Kanboard\Core\Http\Response;
use Kanboard\Core\Security\Role;
use Kanboard\Model\TaskCreationModel;
use Kanboard\Model\TaskFinderModel;
use Kanboard\Model\TaskModel;
use Kanboard\Model\UserModel;

class FeedControllerTest extends Base
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->container['battleLobbyFeed'] = fn ($c) => new FeedModel($c);
    }

    /** Every public method on a controller is a routable action; helpers with arguments 500 via the Runner. */
    public function testOnlyTheEndpointsArePublicActions(): void
    {
        $own = array_filter(
            (new \ReflectionClass(FeedController::class))->getMethods(\ReflectionMethod::IS_PUBLIC),
            fn ($m) => $m->class === FeedController::class && ! $m->isConstructor()
        );
        $names = array_map(fn ($m) => $m->getName(), $own);
        sort($names);
        $this->assertSame(['recent', 'thanks'], $names);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user']);
        parent::tearDown();
    }

    /** Unit tests register no event subscribers, so record project activity explicitly. */
    private function seedActivity(int $pid, int $creatorId, int $count): array
    {
        $tc = new TaskCreationModel($this->container);
        $tf = new TaskFinderModel($this->container);
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $tid = $tc->create(['project_id' => $pid, 'title' => "<b>task $i</b>"]);
            $this->assertTrue($this->container['projectActivityModel']->createEvent(
                $pid, $tid, $creatorId, TaskModel::EVENT_CREATE, ['task' => $tf->getById($tid)]
            ));
            $ids[] = (int) $this->container['db']->getLastId(); // createEvent() returns bool
        }
        return $ids;
    }

    private function loginAs(int $uid, ?int $pid, ?string $projectRole): string
    {
        $_SESSION['user'] = ['id' => $uid, 'role' => Role::APP_USER, 'username' => 'u' . $uid];
        if ($projectRole !== null) {
            $this->container['projectUserRoleModel']->addUser($pid, $uid, $projectRole);
        }
        return $this->container['token']->getReusableCSRFToken();
    }

    /** Call $action with GET params / POST body; returns the decoded JSON (Response::json captured, not sent). */
    private function call(string $action, array $get, array $post = []): array
    {
        $captured = new \ArrayObject();
        $this->container['request'] = new Request($this->container, ['REQUEST_METHOD' => $post ? 'POST' : 'GET'], $get, $post);
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
        (new FeedController($this->container))->$action();
        $this->assertSame(200, $captured['status']);
        return $captured['body'];
    }

    // ---- recent(): GET endpoint ----

    public function testRecentForAProjectViewer(): void
    {
        $uid = (new UserModel($this->container))->create(['username' => 'watcher']);
        $pid = $this->container['projectModel']->create(['name' => 'Feed']);
        $this->seedActivity($pid, $uid, 2);
        $this->loginAs($uid, $pid, Role::PROJECT_VIEWER);
        $res = $this->call('recent', ['project_id' => (string) $pid]);
        $this->assertTrue($res['ok']);
        $this->assertCount(2, $res['events']);
    }

    public function testRecentForbidsANonMember(): void
    {
        $uid = (new UserModel($this->container))->create(['username' => 'stranger']);
        $pid = $this->container['projectModel']->create(['name' => 'Feed']);
        $this->seedActivity($pid, $uid, 2);
        $this->loginAs($uid, null, null);
        $this->assertSame(['ok' => false, 'error' => 'forbidden'], $this->call('recent', ['project_id' => (string) $pid]));
    }

    // ---- thanks(): POST endpoint (CSRF + comment-create permission) ----

    private function thanksSetup(?string $role): array
    {
        $uid = (new UserModel($this->container))->create(['username' => 'fan']);
        $pid = $this->container['projectModel']->create(['name' => 'T']);
        $tid = (new TaskCreationModel($this->container))->create(['project_id' => $pid, 'title' => 'ship it']);
        $csrf = $this->loginAs($uid, $pid, $role);
        return [$tid, $csrf];
    }

    public function testThanksEndpointForAProjectMember(): void
    {
        [$tid, $csrf] = $this->thanksSetup(Role::PROJECT_MEMBER);
        $res = $this->call('thanks', ['x' => '1'], ['task_id' => (string) $tid, 'csrf_token' => $csrf]);
        $this->assertTrue($res['ok']);
        $this->assertIsInt($res['comment_id']);
        $again = $this->call('thanks', ['x' => '1'], ['task_id' => (string) $tid, 'csrf_token' => $csrf]);
        $this->assertSame(['ok' => false, 'error' => 'already_thanked'], $again);
        $this->assertCount(1, $this->container['commentModel']->getAll($tid));
    }

    public function testThanksEndpointRejectsBadCsrf(): void
    {
        [$tid] = $this->thanksSetup(Role::PROJECT_MEMBER);
        $res = $this->call('thanks', ['x' => '1'], ['task_id' => (string) $tid, 'csrf_token' => 'nope']);
        $this->assertSame(['ok' => false, 'error' => 'csrf'], $res);
        $this->assertCount(0, $this->container['commentModel']->getAll($tid));
    }

    public function testThanksEndpointForbidsAProjectViewer(): void
    {
        [$tid, $csrf] = $this->thanksSetup(Role::PROJECT_VIEWER);
        $res = $this->call('thanks', ['x' => '1'], ['task_id' => (string) $tid, 'csrf_token' => $csrf]);
        $this->assertSame(['ok' => false, 'error' => 'forbidden'], $res);
        $this->assertCount(0, $this->container['commentModel']->getAll($tid));
    }

    public function testThanksEndpointForbidsANonMemberAndUnknownTask(): void
    {
        [$tid, $csrf] = $this->thanksSetup(null);
        $this->assertSame(['ok' => false, 'error' => 'forbidden'],
            $this->call('thanks', ['x' => '1'], ['task_id' => (string) $tid, 'csrf_token' => $csrf]));
        $this->assertSame(['ok' => false, 'error' => 'forbidden'],
            $this->call('thanks', ['x' => '1'], ['task_id' => '9999', 'csrf_token' => $csrf]));
        $this->assertCount(0, $this->container['commentModel']->getAll($tid));
    }
}
