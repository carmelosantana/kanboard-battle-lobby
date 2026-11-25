<?php

if (isset($fixtureFailure)) {
    echo 'partial layout';
    ob_start();
    echo 'nested partial layout';
    throw $fixtureFailure;
}

ob_start();
include $fixturePreviousLayout;
$fixtureHtml = ob_get_clean();

if ($fixtureDecoratesRoot) {
    $fixtureHtml = preg_replace(
        '/<html(?=\s|>)/i',
        <<<'HTML'
        <html class="prior" data-note="> style='ignored'" style='--prior-accent:&quot;keep&quot;;'
        HTML,
        $fixtureHtml,
        1
    );
}
echo $fixtureHtml, '<!-- prior layout rendered -->';
