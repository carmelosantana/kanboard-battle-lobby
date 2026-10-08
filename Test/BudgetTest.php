<?php

require_once 'tests/units/Base.php';

use KanboardTests\units\Base;

class BudgetTest extends Base
{
    public function testShippableTreeUnderEightMegabytes(): void
    {
        $root = dirname(__DIR__);
        $skip = '#/(\.git|\.github|\.claude|Test|testing|docs|tools|prototype|research_notes|local|reports)(/|$)#';
        $total = 0;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (! preg_match($skip, substr($f->getPathname(), strlen($root)))) {
                $total += $f->getSize();
            }
        }
        $this->assertLessThanOrEqual(8 * 1024 * 1024, $total, sprintf('shippable tree %.2f MB > 8 MB', $total / 1048576));
    }

    public function testContrastAuditPassed(): void
    {
        $report = dirname(__DIR__) . '/docs/contrast-audit.json';
        $this->assertFileExists($report, 'run testing/contrast_audit.py first');
        foreach (json_decode(file_get_contents($report), true) as $bg => $r) {
            $this->assertGreaterThanOrEqual(4.5, $r['body_ratio'], "$bg body text below AA");
            $this->assertGreaterThanOrEqual(3.0, $r['heading_ratio'], "$bg headings below 3:1");
        }
    }
}
