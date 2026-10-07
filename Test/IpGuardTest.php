<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;

class IpGuardTest extends Base
{
    private const FORBIDDEN = ['fortnite', 'epic games', 'burbank', 'battle bus', 'tilted', 'v-bucks', 'victory royale'];
    private const SKIP_DIRS = ['/.git/', '/.claude/', '/prototype/', '/research_notes/', '/docs/', '/testing/', '/Test/', '/local/', '/tools/'];

    /** @return iterable<string> */
    private function shippableFiles(): iterable
    {
        $root = dirname(__DIR__);
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $path = $f->getPathname();
            $rel = '/' . ltrim(substr($path, strlen($root)), '/');
            foreach (self::SKIP_DIRS as $skip) {
                if (str_starts_with($rel, $skip)) {
                    continue 2;
                }
            }
            if (preg_match('/\.(php|js|css|json|md|yml|txt|html)$/', $path)) {
                yield $rel => $path;
            }
        }
    }

    public function testNoEpicMarksInShippableFiles(): void
    {
        foreach ($this->shippableFiles() as $rel => $path) {
            $src = strtolower(file_get_contents($path));
            if ($rel === '/README.md') {
                // The README may name the game only inside the required disclaimer paragraph.
                $src = preg_replace('/<!-- disclaimer -->.*<!-- \/disclaimer -->/s', '', $src);
            }
            foreach (self::FORBIDDEN as $word) {
                $this->assertStringNotContainsString($word, $src, "$rel contains forbidden mark '$word'");
            }
        }
    }

    public function testNoPiiInShippableFiles(): void
    {
        foreach ($this->shippableFiles() as $rel => $path) {
            $src = file_get_contents($path);
            $this->assertDoesNotMatchRegularExpression('/[A-Za-z0-9._%+-]+@carmelosantana\.com/', $src, "$rel contains a personal email");
            $this->assertStringNotContainsStringIgnoringCase('newburgh', $src, "$rel contains a location");
        }
    }

    public function testNoRuntimeGoogleFonts(): void
    {
        foreach ($this->shippableFiles() as $rel => $path) {
            $src = file_get_contents($path);
            $this->assertStringNotContainsString('fonts.googleapis.com', $src, "$rel loads Google Fonts at runtime");
            $this->assertStringNotContainsString('fonts.gstatic.com', $src, "$rel loads Google Fonts at runtime");
        }
    }
}
