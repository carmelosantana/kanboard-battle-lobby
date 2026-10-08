<?php

namespace Kanboard\Plugin\BattleLobby\Controller;

use Kanboard\Controller\BaseController;

/**
 * Activity feed toasts (recent project events) and the one-click "thanks" comment.
 * Both features ship default-off (options `feed`, `thanks`). Data access lives in
 * FeedModel (container: battleLobbyFeed); only the two endpoints are public actions.
 */
class FeedController extends BaseController
{
    /** GET battlelobby/feed?project_id=N — anyone who may view the board (PROJECT_VIEWER+). */
    public function recent(): void
    {
        $projectId = $this->request->getIntegerParam('project_id');
        if (! $projectId || ! $this->helper->user->hasProjectAccess('BoardViewController', 'show', $projectId)) {
            $this->response->json(['ok' => false, 'error' => 'forbidden']);
            return;
        }
        $this->response->json(['ok' => true, 'events' => $this->container['battleLobbyFeed']->events($projectId)]);
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
        $r = $this->container['battleLobbyFeed']->addThanks((int) $task['id'], (int) $this->userSession->getId());
        $this->response->json(is_int($r) ? ['ok' => true, 'comment_id' => $r] : ['ok' => false, 'error' => $r]);
    }
}
