<?php

namespace Kanboard\Plugin\BattleLobby\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Model\TaskModel;

/**
 * READY UP: move the current user's highest-priority task in the "Ready" column
 * into the next column. Errors are expected outcomes returned as JSON.
 */
class ReadyUpController extends BaseController
{
    public function start(): void
    {
        if (! $this->token->validateReusableCSRFToken((string) $this->request->getRawValue('csrf_token'))) {
            $this->response->json(['ok' => false, 'error' => 'csrf']);
            return;
        }
        $projectId = (int) ($this->request->getRawValue('project_id') ?: $this->request->getIntegerParam('project_id'));
        // Board drag-and-drop permission (PROJECT_MEMBER+), not board view: viewers must not move tasks.
        if (! $projectId || ! $this->helper->user->hasProjectAccess('BoardAjaxController', 'save', $projectId)) {
            $this->response->json(['ok' => false, 'error' => 'forbidden']);
            return;
        }
        $pick = $this->pick($projectId, (int) $this->userSession->getId());
        if (isset($pick['error'])) {
            $this->response->json(['ok' => false, 'error' => $pick['error']]);
            return;
        }
        // Custom project roles may restrict column-to-column moves (same check as BoardAjaxController::save).
        if (! $this->helper->projectRole->canMoveTask($projectId, $pick['from'], $pick['to'])) {
            $this->response->json(['ok' => false, 'error' => 'forbidden']);
            return;
        }
        $this->response->json($this->move($projectId, $pick)
            ? ['ok' => true, 'task_id' => (int) $pick['task']['id'], 'from_column_id' => $pick['from'], 'to_column_id' => $pick['to']]
            : ['ok' => false, 'error' => 'move_failed']);
    }

    /** @return array{task?:array,from?:int,to?:int,error?:string} */
    public function pick(int $projectId, int $userId): array
    {
        $columns = array_values($this->columnModel->getAll($projectId)); // ordered by position
        $readyIdx = null;
        foreach ($columns as $i => $c) {
            if (strcasecmp(trim($c['title']), 'Ready') === 0) {
                $readyIdx = $i;
                break;
            }
        }
        if ($readyIdx === null) {
            return ['error' => 'no_ready_column'];
        }
        if (! isset($columns[$readyIdx + 1])) {
            return ['error' => 'ready_is_last'];
        }
        $from = (int) $columns[$readyIdx]['id'];
        $tasks = array_filter(
            $this->taskFinderModel->getAll($projectId, TaskModel::STATUS_OPEN),
            fn (array $t) => (int) $t['column_id'] === $from && (int) $t['owner_id'] === $userId
        );
        if (! $tasks) {
            return ['error' => 'nothing_ready'];
        }
        usort($tasks, fn ($a, $b) => [(int) $b['priority'], (int) $a['position']] <=> [(int) $a['priority'], (int) $b['position']]);
        return ['task' => $tasks[0], 'from' => $from, 'to' => (int) $columns[$readyIdx + 1]['id']];
    }

    public function move(int $projectId, array $pick): bool
    {
        return (bool) $this->taskPositionModel->movePosition(
            $projectId, (int) $pick['task']['id'], $pick['to'], 1, (int) $pick['task']['swimlane_id']
        );
    }
}
