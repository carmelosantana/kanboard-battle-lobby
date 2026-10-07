<?php

namespace Kanboard\Plugin\BattleLobby\Controller;

use Kanboard\Controller\BaseController;

/**
 * READY UP: move the current user's highest-priority task in the "Ready" column
 * into the next column. Errors are expected outcomes returned as JSON (HTTP 200).
 * Selection, permission and move live in ReadyUpModel (container: battleLobbyReadyUp).
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
        $readyUp = $this->container['battleLobbyReadyUp'];
        $pick = $readyUp->eligible($projectId, (int) $this->userSession->getId());
        if (isset($pick['error'])) {
            $this->response->json(['ok' => false, 'error' => $pick['error']]);
            return;
        }
        $this->response->json($readyUp->move($projectId, $pick)
            ? ['ok' => true, 'task_id' => (int) $pick['task']['id'], 'from_column_id' => $pick['from'], 'to_column_id' => $pick['to']]
            : ['ok' => false, 'error' => 'move_failed']);
    }
}
