<?php

namespace Kanboard\Plugin\BattleLobby\Helper;

use Kanboard\Core\Base;

/**
 * Presentation helpers for templates: priority tiers, storm due-date state,
 * and the data-bl-* payload external JS reads (CSP forbids inline script).
 */
class LobbyHelper extends Base
{
    private const LABELS = ['COMMON', 'UNCOMMON', 'RARE', 'EPIC', 'LEGENDARY'];
    private const CONTEXTS = ['board', 'page', 'login'];

    /**
     * Map a task priority onto tiers 0..4 using the project's own range.
     * Ranges with ≤ 4 values map onto 0..3 (LEGENDARY needs a range wider than 4).
     */
    public function tier(array $task, array $project): int
    {
        $start = (int) ($project['priority_start'] ?? 0);
        $end = (int) ($project['priority_end'] ?? 3);
        if ($end <= $start) {
            return 0;
        }
        $priority = max($start, min($end, (int) ($task['priority'] ?? $start)));
        $span = $end - $start;
        $top = $span + 1 > 4 ? 4 : 3;
        return (int) round(($priority - $start) / $span * $top);
    }

    public function tierLabel(int $tier): string
    {
        return self::LABELS[max(0, min(4, $tier))];
    }

    /** @return array{state:string,label:string} */
    public function storm(int $dateDue, int $now): array
    {
        if ($dateDue <= 0) {
            return ['state' => 'none', 'label' => ''];
        }
        $delta = $dateDue - $now;
        if ($delta < 0) {
            $days = intdiv(-$delta, 86400);
            return ['state' => 'late', 'label' => $days > 0 ? $days . 'd late' : 'late'];
        }
        if ($delta < 86400) {
            $hours = intdiv($delta, 3600);
            return ['state' => 'soon', 'label' => $hours > 0 ? $hours . 'h' : '<1h'];
        }
        return ['state' => 'far', 'label' => intdiv($delta, 86400) . 'd'];
    }

    public function payloadAttributes(?int $userId, string $context): string
    {
        $context = in_array($context, self::CONTEXTS, true) ? $context : 'page';
        $attrs = [
            'context' => $context,
            'assets' => $this->helper->url->dir() . 'plugins/BattleLobby/Assets/',
            'readyup-url' => $this->helper->url->to('ReadyUpController', 'start', ['plugin' => 'BattleLobby']),
            'feed-url' => $this->helper->url->to('FeedController', 'recent', ['plugin' => 'BattleLobby']),
            'thanks-url' => $this->helper->url->to('FeedController', 'thanks', ['plugin' => 'BattleLobby']),
            'csrf' => $this->token->getReusableCSRFToken(),
        ];
        foreach ($this->container['battleLobbyOptions']->resolve($userId) as $key => $value) {
            $attrs[$key] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }
        $out = [];
        foreach ($attrs as $key => $value) {
            $out[] = sprintf('data-bl-%s="%s"', $key, htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
        }
        return implode(' ', $out);
    }

    public function isConflictActive(): bool
    {
        return class_exists('Kanboard\Plugin\ShadcnTheme\Plugin', false);
    }
}
