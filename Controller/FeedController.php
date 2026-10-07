<?php

namespace Kanboard\Plugin\BattleLobby\Controller;

use Kanboard\Controller\BaseController;

/**
 * Activity feed toasts (recent project events) and the one-click "thanks" comment.
 * Both features ship default-off (options `feed`, `thanks`).
 */
class FeedController extends BaseController
{
    public const THANKS = '🙏 GG — thanks for shipping this!';

    /** GET battlelobby/feed?project_id=N — anyone who may view the board (PROJECT_VIEWER+). */
    public function recent(): void
    {
        $projectId = $this->request->getIntegerParam('project_id');
        if (! $projectId || ! $this->helper->user->hasProjectAccess('BoardViewController', 'show', $projectId)) {
            $this->response->json(['ok' => false, 'error' => 'forbidden']);
            return;
        }
        $this->response->json(['ok' => true, 'events' => $this->events($projectId)]);
    }

    /** POST battlelobby/thanks — a comment write, so the same right as core CommentController::save (PROJECT_MEMBER+). */
    public function thanks(): void
    {
        if (! $this->token->validateReusableCSRFToken($this->request->getRawValue('csrf_token'))) {
            $this->response->json(['ok' => false, 'error' => 'csrf']);
            return;
        }
        $task = $this->taskFinderModel->getById((int) $this->request->getRawValue('task_id'));
        if (! $task || ! $this->helper->user->hasProjectAccess('CommentController', 'save', (int) $task['project_id'])) {
            $this->response->json(['ok' => false, 'error' => 'forbidden']);
            return;
        }
        $r = $this->addThanks((int) $task['id'], (int) $this->userSession->getId());
        $this->response->json(is_int($r) ? ['ok' => true, 'comment_id' => $r] : ['ok' => false, 'error' => $r]);
    }

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
