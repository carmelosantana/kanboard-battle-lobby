<?php

namespace Kanboard\Plugin\BattleLobby\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;

/**
 * Admin defaults (Settings → Battle Lobby) and per-user overrides (profile → Battle Lobby).
 *
 * Per-user actions go through core BaseController::getUser(), which already lets a
 * user reach only their own record (admins reach anyone) and answers others with a 404.
 */
class OptionsController extends BaseController
{
    public function show(): void
    {
        $this->requireAdmin();
        $this->response->html($this->helper->layout->config('BattleLobby:config/options', [
            'title' => t('Settings') . ' &gt; ' . t('Battle Lobby'),
            'values' => array_replace($this->options()->defaults(), $this->options()->adminValues()),
            'schema' => $this->options()->schema(),
        ]));
    }

    public function save(): void
    {
        $this->requireAdmin();
        $this->checkCSRFForm();
        $this->options()->saveAdmin($this->checkboxes($this->request->getValues()))
            ? $this->flash->success(t('Settings saved successfully.'))
            : $this->flash->failure(t('Unable to save your settings.'));
        $this->response->redirect($this->helper->url->to('OptionsController', 'show', ['plugin' => 'BattleLobby']));
    }

    public function user(): void
    {
        $user = $this->getUser();
        $this->response->html($this->helper->layout->user('BattleLobby:user/options', [
            'title' => t('Battle Lobby'),
            'user' => $user,
            'values' => $this->options()->resolve((int) $user['id']),
            'schema' => $this->options()->schema(),
        ]));
    }

    public function userSave(): void
    {
        $user = $this->getUser();
        $this->checkCSRFForm();
        $this->options()->saveUser((int) $user['id'], $this->checkboxes($this->request->getValues()))
            ? $this->flash->success(t('Settings saved successfully.'))
            : $this->flash->failure(t('Unable to save your settings.'));
        $this->response->redirect($this->helper->url->to('OptionsController', 'user', ['plugin' => 'BattleLobby', 'user_id' => $user['id']]));
    }

    public function userReset(): void
    {
        $user = $this->getUser();
        $this->checkCSRFForm();
        $this->options()->resetUser((int) $user['id']);
        $this->flash->success(t('Your Battle Lobby options now follow the admin defaults.'));
        $this->response->redirect($this->helper->url->to('OptionsController', 'user', ['plugin' => 'BattleLobby', 'user_id' => $user['id']]));
    }

    private function options()
    {
        return $this->container['battleLobbyOptions'];
    }

    private function requireAdmin(): void
    {
        if (! $this->userSession->isAdmin()) {
            throw new AccessForbiddenException();
        }
    }

    /** Unchecked checkboxes are absent from POST: write them as false explicitly. */
    private function checkboxes(array $values): array
    {
        foreach ($this->options()->schema() as $key => $s) {
            if ($s['type'] === 'bool' && ! array_key_exists($key, $values)) {
                $values[$key] = false;
            }
        }
        return $values;
    }
}
