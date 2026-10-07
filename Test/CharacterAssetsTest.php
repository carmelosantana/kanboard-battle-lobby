<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;

class CharacterAssetsTest extends Base
{
    private function manifest(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__) . '/Assets/characters/manifest.json'), true);
    }

    public function testEveryCharacterHasIdleAndVictoryWithAlpha(): void
    {
        foreach ($this->manifest()['characters'] as $slug => $c) {
            foreach (['idle', 'victory'] as $pose) {
                $f = dirname(__DIR__) . "/Assets/characters/$slug-$pose.webp";
                $this->assertFileExists($f);
                $head = file_get_contents($f, false, null, 0, 64);
                $this->assertSame('RIFF', substr($head, 0, 4));
                $this->assertStringContainsString('VP8X', $head, "$slug-$pose.webp must be extended WebP (alpha)");
            }
            $this->assertIsInt($c['seed'] ?? null, "$slug missing seed");
            $this->assertIsString($c['victory_prompt_id'] ?? null, "$slug missing victory_prompt_id");
            $this->assertNotSame(
                md5_file(dirname(__DIR__) . "/Assets/characters/$slug-idle.webp"),
                md5_file(dirname(__DIR__) . "/Assets/characters/$slug-victory.webp"),
                "$slug victory pose is still a copy of idle"
            );
        }
    }

    public function testSetsReferenceKnownCharactersAndBudgetHolds(): void
    {
        $m = $this->manifest();
        foreach ($m['sets'] as $set => $ids) {
            foreach ($ids as $id) {
                $this->assertArrayHasKey($id, $m['characters'], "set $set references unknown $id");
            }
        }
        $total = 0;
        foreach (glob(dirname(__DIR__) . '/Assets/characters/*.webp') as $f) {
            $total += filesize($f);
        }
        $this->assertLessThanOrEqual(4 * 1024 * 1024, $total, 'characters over 4 MB');
        $this->assertCount(24, glob(dirname(__DIR__) . '/Assets/characters/*.webp'));
    }
}
