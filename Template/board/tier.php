<?php
$project ??= $this->battleLobby->project((int) ($task['project_id'] ?? 0));
$tier = $this->battleLobby->tier($task, $project);
?>
<span class="bl-tier" data-tier="<?= $tier ?>"><?= $this->battleLobby->tierLabel($tier) ?></span>
