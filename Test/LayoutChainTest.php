<?php

require_once 'tests/units/Base.php';

use Kanboard\Core\Http\Request;
use Kanboard\Plugin\BattleLobby\Plugin;
use KanboardTests\units\Base;

class LayoutChainTest extends Base
{
    private const FIXTURE = 'BattleLobby:../Test/fixtures/prior-layout';

    protected function tearDown(): void
    {
        unset($_SESSION['user']);
        parent::tearDown();
    }

    public function testRootOptionsRenderBeforeTheFirstStylesheet(): void
    {
        $this->container['request'] = new Request($this->container, [
            'REQUEST_METHOD' => 'GET', 'PHP_SELF' => '/kanboard/index.php',
        ], [], []);
        (new Plugin($this->container))->initialize();
        $userId = $this->container['userModel']->create(['username' => 'layout-test']);
        $_SESSION['user'] = $this->container['userModel']->getById($userId);
        $options = $this->container['battleLobbyOptions'];
        $options->saveAdmin(['background' => 'none']);
        $options->saveUser($userId, ['background' => 'storm', 'motion' => 'on', 'glass' => false, 'blur' => 7, 'tint' => 0.84]);

        $html = $this->renderLayout();
        $this->assertMatchesRegularExpression('/<html\b[^>]*>\s*<head>.*?<link\b/s', $html);
        $root = $this->root($html);
        $this->assertSame('on', $root->getAttribute('data-bl-motion'));
        $this->assertSame('0', $root->getAttribute('data-bl-glass'));
        $this->assertSame('storm', $root->getAttribute('data-bl-bg'));
        $style = $root->getAttribute('style');
        $this->assertStringContainsString('--bl-tint:0.84', $style);
        $this->assertStringContainsString('--bl-blur:7px', $style);
        $this->assertStringContainsString('--bl-bg-image:url("/kanboard/plugins/BattleLobby/Assets/backgrounds/storm.webp")', $style);
    }

    public function testAnonymousLayoutKeepsTheMarkerWhenBackgroundIsDisabled(): void
    {
        (new Plugin($this->container))->initialize();
        $this->container['battleLobbyOptions']->saveAdmin(['background' => 'none']);

        $root = $this->root($this->renderLayout());
        $this->assertSame('auto', $root->getAttribute('data-bl-motion'));
        $this->assertSame('1', $root->getAttribute('data-bl-glass'));
        $this->assertSame('none', $root->getAttribute('data-bl-bg'));
        $this->assertStringContainsString('--bl-bg-image:none', $root->getAttribute('style'));
    }

    /** @dataProvider wrapperLoadOrders */
    public function testWrapperLoadOrderKeepsTheOtherLayoutAndItsAttributes(bool $priorFirst): void
    {
        $template = $this->container['template'];
        $plugin = new Plugin($this->container);
        if (! $priorFirst) {
            $plugin->initialize();
        }
        $fixturePreviousLayout = $template->getTemplateFile('layout');
        $template->setTemplateOverride('layout', self::FIXTURE);
        if ($priorFirst) {
            $plugin->initialize();
        }

        $html = $this->renderLayout([
            'fixturePreviousLayout' => $fixturePreviousLayout,
            'fixtureDecoratesRoot' => $priorFirst,
        ]);
        $this->assertSame(1, substr_count($html, '<html '));
        $this->assertSame(1, substr_count($html, 'data-bl-bg='));
        $this->assertSame(1, substr_count($html, '<!-- prior layout rendered -->'));
        $this->assertStringContainsString('<main>Layout content</main>', $html);
        $root = $this->root($html);
        $this->assertStringContainsString('--bl-tint:0.78', $root->getAttribute('style'));
        if ($priorFirst) {
            $this->assertSame('prior', $root->getAttribute('class'));
            $this->assertSame("> style='ignored'", $root->getAttribute('data-note'));
            $this->assertStringContainsString('--prior-accent:"keep";', $root->getAttribute('style'));
        }
    }

    public static function wrapperLoadOrders(): array
    {
        return ['fixture first' => [true], 'BattleLobby first' => [false]];
    }

    public function testWrapperDiscardsPartialOutputAndRethrowsTheOriginalException(): void
    {
        $template = $this->container['template'];
        $template->setTemplateOverride('layout', self::FIXTURE);
        (new Plugin($this->container))->initialize();
        $fixtureFailure = new \RuntimeException('layout failed');
        $includeLayout = function () use ($fixtureFailure): void {
            include $this->getTemplateFile('layout');
        };

        ob_start();
        $level = ob_get_level();
        echo 'caller output';
        try {
            $includeLayout->call($template);
            $this->fail('Expected the wrapped layout to throw');
        } catch (\RuntimeException $caught) {
            $this->assertSame($fixtureFailure, $caught);
            $this->assertSame($level, ob_get_level());
            $this->assertSame('caller output', ob_get_contents());
        } finally {
            while (ob_get_level() >= $level) {
                ob_end_clean();
            }
        }
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testLoadedConflictLeavesTheRootUnmarked(): void
    {
        (new Plugin($this->container))->initialize();
        class_alias(self::class, 'Kanboard\\Plugin\\Harbor\\Plugin');

        $root = $this->root($this->renderLayout());
        $this->assertFalse($root->hasAttribute('data-bl-bg'));
        $this->assertStringNotContainsString('--bl-', $root->getAttribute('style'));
    }

    private function renderLayout(array $args = []): string
    {
        return $this->container['template']->render('layout', $args + [
            'no_layout' => true,
            'content_for_layout' => '<main>Layout content</main>',
        ]);
    }

    private function root(string $html): \DOMElement
    {
        $document = new \DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR);
        return $document->documentElement;
    }
}
