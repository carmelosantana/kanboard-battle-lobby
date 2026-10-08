<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;
use Kanboard\Plugin\BattleLobby\Model\FeedModel;
use Kanboard\Core\Security\Role;
use Kanboard\Model\TaskCreationModel;
use Kanboard\Model\TaskFinderModel;
use Kanboard\Model\TaskModel;
use Kanboard\Model\UserModel;

class FeedModelTest extends Base
{
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

    // ---- events() ----

    public function testEventsAreLimitedPlainAndNewestFirst(): void
    {
        $uid = (new UserModel($this->container))->create(['username' => 'alex', 'name' => '<b>Alex</b> & co']);
        $pid = $this->container['projectModel']->create(['name' => 'Feed']);
        $_SESSION['user'] = ['id' => $uid, 'role' => Role::APP_ADMIN, 'username' => 'alex'];
        $ids = $this->seedActivity($pid, $uid, 7);

        $events = (new FeedModel($this->container))->events($pid);

        $this->assertCount(5, $events);
        foreach ($events as $e) {
            $this->assertSame(['id', 'title', 'date'], array_keys($e));
            $this->assertIsInt($e['id']);
            $this->assertIsInt($e['date']);
            $this->assertStringNotContainsString('<', $e['title']);
            $this->assertStringNotContainsString('&amp;', $e['title']);
            $this->assertStringContainsString('Alex & co', $e['title']);
        }
        $this->assertSame(array_slice(array_reverse($ids), 0, 5), array_column($events, 'id'));
        $dates = array_column($events, 'date');
        $sorted = $dates;
        rsort($sorted);
        $this->assertSame($sorted, $dates);
    }

    // ---- addThanks() ----

    public function testThanksOncePerUserPerTask(): void
    {
        $uid = (new UserModel($this->container))->create(['username' => 'gg']);
        $other = (new UserModel($this->container))->create(['username' => 'gg2']);
        $pid = $this->container['projectModel']->create(['name' => 'T']);
        $tid = (new TaskCreationModel($this->container))->create(['project_id' => $pid, 'title' => 'ship it']);
        $c = new FeedModel($this->container);
        $this->assertIsInt($c->addThanks($tid, $uid));
        $this->assertSame('already_thanked', $c->addThanks($tid, $uid));
        $this->assertIsInt($c->addThanks($tid, $other)); // per user, not per task
        $this->assertCount(2, $this->container['commentModel']->getAll($tid));
    }

    public function testThanksCommentKeepsTheEmojiInSqlite(): void
    {
        $uid = (new UserModel($this->container))->create(['username' => 'gg']);
        $pid = $this->container['projectModel']->create(['name' => 'T']);
        $tid = (new TaskCreationModel($this->container))->create(['project_id' => $pid, 'title' => 'ship it']);
        $cid = (new FeedModel($this->container))->addThanks($tid, $uid);
        $comment = $this->container['commentModel']->getById($cid);
        $this->assertSame('🙏 GG — thanks for shipping this!', $comment['comment']);
        $this->assertSame($uid, (int) $comment['user_id']);
    }
}
