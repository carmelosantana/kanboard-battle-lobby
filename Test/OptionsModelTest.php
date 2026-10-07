<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;
use Kanboard\Plugin\BattleLobby\Model\OptionsModel;
use Kanboard\Model\UserModel;

class OptionsModelTest extends Base
{
    private function model(): OptionsModel
    {
        return new OptionsModel($this->container);
    }

    public function testDefaults(): void
    {
        $d = $this->model()->defaults();
        $this->assertSame([
            'background' => 'map', 'characters' => 'mixed', 'placement' => 'auto', 'motion' => 'auto',
            'glass' => true, 'blur' => 12, 'tint' => 0.78,
            'loading' => true, 'victory' => true, 'storm' => true, 'readyup' => true,
            'feed' => false, 'thanks' => false,
        ], $d);
    }

    public function testValidateClampsAndDrops(): void
    {
        $v = $this->model()->validate([
            'background' => 'lava',        // invalid enum → dropped
            'characters' => 'night',
            'blur' => '99',                // clamp to 24
            'tint' => '0.1',               // clamp to 0.60
            'glass' => '0',                // bool coercion
            'feed' => 'on',
            'evil' => 'x',                 // unknown → dropped
        ]);
        // validate() emits keys in schema order
        $this->assertSame(['characters' => 'night', 'glass' => false, 'blur' => 24, 'tint' => 0.6, 'feed' => true], $v);
    }

    public function testResolveLayersUserOverAdminOverDefaults(): void
    {
        $m = $this->model();
        $userId = (new UserModel($this->container))->create(['username' => 'squad1']);
        $this->assertTrue($m->saveAdmin(['background' => 'storm', 'characters' => 'brawl']));
        $this->assertTrue($m->saveUser($userId, ['characters' => 'night']));

        $r = $m->resolve($userId);
        $this->assertSame('storm', $r['background']);   // admin
        $this->assertSame('night', $r['characters']);   // user wins
        $this->assertSame(12, $r['blur']);              // built-in
        $this->assertSame('brawl', $m->resolve(null)['characters']); // anonymous → admin
    }

    public function testResetUserFallsBackToAdmin(): void
    {
        $m = $this->model();
        $userId = (new UserModel($this->container))->create(['username' => 'squad2']);
        $m->saveAdmin(['motion' => 'off']);
        $m->saveUser($userId, ['motion' => 'on']);
        $this->assertTrue($m->resetUser($userId));
        $this->assertSame('off', $m->resolve($userId)['motion']);
    }

    public function testResolveIgnoresCorruptJson(): void
    {
        $userId = (new UserModel($this->container))->create(['username' => 'squad3']);
        $this->container['configModel']->save([OptionsModel::CONFIG_KEY => '{not json']);
        $this->container['userMetadataModel']->save($userId, [OptionsModel::META_KEY => '["list","not","object"]']);
        $r = $this->model()->resolve($userId);
        $this->assertSame($this->model()->defaults(), $r);
    }
}
