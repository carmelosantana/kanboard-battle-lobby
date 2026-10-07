<?php

namespace Kanboard\Plugin\BattleLobby\Model;

use Kanboard\Core\Base;

/**
 * Theme options: one schema, validated input, layered resolution
 * (built-in defaults ← admin app config ← per-user metadata).
 */
class OptionsModel extends Base
{
    public const CONFIG_KEY = 'battlelobby_options';
    public const META_KEY = 'battlelobby_options';
    /** memoryCache key for resolve() results, keyed by user id (0 = anonymous). */
    private const MEMO_KEY = 'battlelobby:resolved';

    public function schema(): array
    {
        return [
            'background' => ['type' => 'enum', 'values' => ['map', 'storm', 'island', 'none'], 'default' => 'map'],
            'characters' => ['type' => 'enum', 'values' => ['mixed', 'brawl', 'wild', 'night', 'none'], 'default' => 'mixed'],
            'placement'  => ['type' => 'enum', 'values' => ['auto', 'lineup', 'mascot', 'off'], 'default' => 'auto'],
            'motion'     => ['type' => 'enum', 'values' => ['auto', 'on', 'off'], 'default' => 'auto'],
            'glass'      => ['type' => 'bool', 'default' => true],
            'blur'       => ['type' => 'int', 'min' => 0, 'max' => 24, 'default' => 12],
            'tint'       => ['type' => 'float', 'min' => 0.60, 'max' => 0.95, 'default' => 0.78],
            'loading'    => ['type' => 'bool', 'default' => true],
            'victory'    => ['type' => 'bool', 'default' => true],
            'storm'      => ['type' => 'bool', 'default' => true],
            'readyup'    => ['type' => 'bool', 'default' => true],
            'feed'       => ['type' => 'bool', 'default' => false],
            'thanks'     => ['type' => 'bool', 'default' => false],
        ];
    }

    public function defaults(): array
    {
        return array_map(fn (array $s) => $s['default'], $this->schema());
    }

    public function validate(array $input): array
    {
        $out = [];
        foreach ($this->schema() as $key => $s) {
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $raw = $input[$key];
            switch ($s['type']) {
                case 'enum':
                    if (is_string($raw) && in_array($raw, $s['values'], true)) {
                        $out[$key] = $raw;
                    }
                    break;
                case 'bool':
                    $out[$key] = filter_var($raw, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'int':
                    if (is_numeric($raw)) {
                        $out[$key] = max($s['min'], min($s['max'], (int) $raw));
                    }
                    break;
                case 'float':
                    if (is_numeric($raw)) {
                        $out[$key] = round(max($s['min'], min($s['max'], (float) $raw)), 2);
                    }
                    break;
            }
        }
        return $out;
    }

    public function adminValues(): array
    {
        return $this->decode($this->configModel->get(self::CONFIG_KEY, ''));
    }

    public function saveAdmin(array $input): bool
    {
        $saved = (bool) $this->configModel->save([self::CONFIG_KEY => json_encode($this->validate($input))]);
        // ConfigModel::get() reads through memoryCache->proxy(); evict it so same-request reads see the save.
        $this->memoryCache->remove('proxy:' . get_class($this->configModel) . ':getAll');
        $this->memoryCache->remove(self::MEMO_KEY);
        return $saved;
    }

    public function userValues(int $userId): array
    {
        return $this->decode($this->userMetadataModel->get($userId, self::META_KEY, ''));
    }

    public function saveUser(int $userId, array $input): bool
    {
        $this->forget($userId);
        return (bool) $this->userMetadataModel->save($userId, [self::META_KEY => json_encode($this->validate($input))]);
    }

    public function resetUser(int $userId): bool
    {
        $this->forget($userId);
        return (bool) $this->userMetadataModel->save($userId, [self::META_KEY => '{}']);
    }

    /**
     * Memoized per user for the request (the board renders this once per card).
     * The memo lives in the container's memoryCache, not on this instance, so a
     * save through any OptionsModel instance invalidates it.
     */
    public function resolve(?int $userId): array
    {
        $uid = (int) $userId;
        $memo = $this->memoryCache->get(self::MEMO_KEY) ?? [];
        if (! isset($memo[$uid])) {
            $values = array_replace($this->defaults(), $this->adminValues());
            if ($uid) {
                $values = array_replace($values, $this->userValues($uid));
            }
            $memo[$uid] = $values;
            $this->memoryCache->set(self::MEMO_KEY, $memo);
        }
        return $memo[$uid];
    }

    private function forget(int $userId): void
    {
        $memo = $this->memoryCache->get(self::MEMO_KEY) ?? [];
        unset($memo[$userId]);
        $this->memoryCache->set(self::MEMO_KEY, $memo);
    }

    /** Decode stored JSON and re-validate it; anything malformed yields []. */
    private function decode(string $json): array
    {
        $data = json_decode($json, true);
        if (! is_array($data) || array_is_list($data)) {
            return [];
        }
        return $this->validate($data);
    }
}
