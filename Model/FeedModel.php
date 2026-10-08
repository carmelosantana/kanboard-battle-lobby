<?php

namespace Kanboard\Plugin\BattleLobby\Model;

use Kanboard\Core\Base;

/**
 * Activity feed events and the one-click "thanks" comment, shared by FeedController
 * (container: battleLobbyFeed). Kept off the controller so they are not routable actions.
 */
class FeedModel extends Base
{
    public const THANKS = '🙏 GG — thanks for shipping this!';

    /**
     * Newest five project events as plain text (core's event_title is built for HTML output).
     *
     * @return list<array{id:int,title:string,date:int}>
     */
    public function events(int $projectId): array
    {
        $out = [];
        foreach ($this->helper->projectActivity->getProjectEvents($projectId, 5) as $e) {
            $out[] = [
                'id' => (int) $e['id'],
                'title' => trim(strip_tags(html_entity_decode((string) ($e['event_title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))),
                'date' => (int) $e['date_creation'],
            ];
        }
        usort($out, fn ($a, $b) => [$b['date'], $b['id']] <=> [$a['date'], $a['id']]);
        return array_slice($out, 0, 5);
    }

    /** @return int|string comment id, or 'already_thanked' */
    public function addThanks(int $taskId, int $userId)
    {
        foreach ($this->commentModel->getAll($taskId) as $c) {
            if ((int) $c['user_id'] === $userId && $c['comment'] === self::THANKS) {
                return 'already_thanked';
            }
        }
        return (int) $this->commentModel->create(['task_id' => $taskId, 'user_id' => $userId, 'comment' => self::THANKS]);
    }
}
