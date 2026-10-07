<?php

namespace Kanboard\Plugin\BattleLobby\Model;

use Kanboard\Core\Base;
use Kanboard\Model\TaskModel;

/**
 * READY UP selection, permission and move: the single source of truth shared by
 * ReadyUpController (the POST endpoint) and LobbyHelper (whether to show the CTA).
 */
class ReadyUpModel extends Base
{
    /**
     * pick() plus the logged-in user's right to move it: the same checks as core
     * board drag-and-drop (BoardAjaxController::save is PROJECT_MEMBER+, and
     * canMoveTask applies custom-role column restrictions). Viewers get 'forbidden'.
     *
     * @return array{task?:array,from?:int,to?:int,error?:string}
     */
    public function eligible(int $projectId, int $userId): array
    {
        if (! $projectId || ! $this->helper->user->hasProjectAccess('BoardAjaxController', 'save', $projectId)) {
            return ['error' => 'forbidden'];
        }
        $pick = $this->pick($projectId, $userId);
        if (isset($pick['error'])) {
            return $pick;
        }
        if (! $this->helper->projectRole->canMoveTask($projectId, $pick['from'], $pick['to'])) {
            return ['error' => 'forbidden'];
        }
        return $pick;
    }

    /** @return array{task?:array,from?:int,to?:int,error?:string} */
    public function pick(int $projectId, int $userId): array
    {
        $columns = array_values($this->columnModel->getAll($projectId)); // ordered by position
        $names = array_unique(array_map('mb_strtolower', ['Ready', t('Ready')]));
        $readyIdx = null;
        foreach ($columns as $i => $c) {
            if (in_array(mb_strtolower(trim($c['title'])), $names, true)) {
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
