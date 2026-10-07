<?php
$labels = [
    'background' => t('Background'), 'characters' => t('Character set'), 'placement' => t('Character placement'),
    'motion' => t('Animations'), 'glass' => t('Frosted columns'), 'blur' => t('Blur strength (px)'),
    'tint' => t('Panel tint (0.60–0.95)'), 'loading' => t('Loading screen'), 'victory' => t('Victory screen'),
    'storm' => t('Storm due dates'), 'readyup' => t('READY UP button'), 'feed' => t('Activity feed toasts'),
    'thanks' => t('Thanks button on finished tasks'),
];
$choices = [
    'background' => ['map' => t('Island map'), 'storm' => t('Storm'), 'island' => t('Drop'), 'none' => t('None')],
    'characters' => ['mixed' => t('Mixed squad'), 'brawl' => t('Brawl'), 'wild' => t('Wildlands'), 'night' => t('Nightfall'), 'none' => t('None')],
    'placement' => ['auto' => t('Automatic'), 'lineup' => t('Lineup'), 'mascot' => t('Mascot'), 'off' => t('Off')],
    'motion' => ['auto' => t('Follow system setting'), 'on' => t('On'), 'off' => t('Off')],
];
?>
<fieldset class="bl-options">
<?php foreach (['background', 'characters', 'placement', 'motion'] as $key): ?>
    <?= $this->form->label($labels[$key], $key) ?>
    <?= $this->form->select($key, $choices[$key], $values) ?>
<?php endforeach ?>
<?= $this->form->label($labels['blur'], 'blur') ?>
<?= $this->form->number('blur', $values, [], ['min="0"', 'max="24"', 'step="1"']) ?>
<?= $this->form->label($labels['tint'], 'tint') ?>
<?= $this->form->text('tint', $values, [], ['inputmode="decimal"']) ?>
<p class="form-help"><?= t('Below 0.75 the background art starts to fight the text. Blur alone does not fix contrast.') ?></p>
<?php foreach (['glass', 'loading', 'victory', 'storm', 'readyup', 'feed', 'thanks'] as $key): ?>
    <?= $this->form->checkbox($key, $labels[$key], 1, (bool) $values[$key]) ?>
<?php endforeach ?>
</fieldset>
