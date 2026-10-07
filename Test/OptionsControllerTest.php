<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;
use Kanboard\Plugin\BattleLobby\Controller\OptionsController;
use Kanboard\Plugin\BattleLobby\Model\OptionsModel;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Core\Controller\PageNotFoundException;
use Kanboard\Model\UserModel;

class OptionsControllerTest extends Base
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->container['battleLobbyOptions'] = fn ($c) => new OptionsModel($c);
    }

    /** 1.2.47 has no sessionStorage service; UserSession reads $_SESSION['user'] via session_get(). */
    private function asUser(int $id, string $role): void
    {
        $_SESSION['user'] = ['id' => $id, 'role' => $role, 'username' => 'u' . $id];
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user']);
        parent::tearDown();
    }

    public function testAdminSaveRequiresAdmin(): void
    {
        $uid = (new UserModel($this->container))->create(['username' => 'plain']);
        $this->asUser($uid, 'app-user');
        $this->expectException(AccessForbiddenException::class);
        (new OptionsController($this->container))->show();
    }

    /**
     * Core BaseController::getUser() answers another user's id with a 404
     * (PageNotFoundException) so user ids can't be enumerated — 1.2.47 behaviour.
     */
    public function testUserCannotEditAnotherUsersOptions(): void
    {
        $users = new UserModel($this->container);
        $a = $users->create(['username' => 'a']);
        $b = $users->create(['username' => 'b']);
        $this->asUser($a, 'app-user');
        $this->container['request'] = new \Kanboard\Core\Http\Request($this->container, [], ['user_id' => $b]);
        $this->expectException(PageNotFoundException::class);
        (new OptionsController($this->container))->user();
    }

    public function testFieldsTemplateCoversEverySchemaKey(): void
    {
        $src = file_get_contents(dirname(__DIR__) . '/Template/options/_fields.php');
        foreach (array_keys((new OptionsModel($this->container))->schema()) as $key) {
            $this->assertStringContainsString("'$key'", $src, "form missing option '$key'");
        }
        $this->assertStringContainsString('0.75', $src, 'tint warning threshold');
    }

    public function testTemplatesAreCspSafeAndCsrfProtected(): void
    {
        foreach (['config/options.php', 'user/options.php'] as $t) {
            $src = file_get_contents(dirname(__DIR__) . '/Template/' . $t);
            $this->assertStringContainsString('form->csrf()', $src, "$t must carry CSRF");
            $this->assertStringNotContainsString('<script', $src);
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*["\']/i', $src);
        }
    }
}
