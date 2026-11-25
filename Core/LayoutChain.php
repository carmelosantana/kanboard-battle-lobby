<?php

namespace Kanboard\Plugin\BattleLobby\Core;

use Kanboard\Core\Template;
use WeakMap;

final class LayoutChain
{
    /** @var WeakMap<Template, string>|null */
    private static ?WeakMap $previous = null;

    public static function register(Template $template, string $wrapper): void
    {
        self::$previous ??= new WeakMap();
        if (isset(self::$previous[$template])) {
            return;
        }

        self::$previous[$template] = $template->getTemplateFile('layout');
        $template->setTemplateOverride('layout', $wrapper);
    }

    public static function previous(Template $template): string
    {
        return self::$previous[$template];
    }

    public static function withRootAttributes(string $html, array $attributes): string
    {
        // Rewrite only the document's opening tag and keep the rest of the rendered HTML verbatim.
        $rootPattern = <<<'REGEX'
        ~\A(?:\s|<!doctype[^>]*>|<!--.*?-->)*\K(<html)(?=[\s>])((?:[^>"']++|"[^"]*"|'[^']*')*)>~is
        REGEX;
        $attributePattern = <<<'REGEX'
        ~\s+([^\s=/>"']+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+)))?~
        REGEX;

        return preg_replace_callback($rootPattern, static function (array $root) use ($attributes, $attributePattern): string {
            $existingStyle = '';
            $tag = preg_replace_callback($attributePattern, static function (array $attribute) use ($attributes, &$existingStyle): string {
                $name = strtolower($attribute[1]);
                if ($name === 'style') {
                    $value = $attribute[2] ?? $attribute[3] ?? $attribute[4] ?? '';
                    $existingStyle = rtrim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }
                return isset($attributes[$name]) ? '' : $attribute[0];
            }, $root[2], flags: PREG_UNMATCHED_AS_NULL);

            if ($existingStyle !== '') {
                $separator = str_ends_with($existingStyle, ';') ? '' : ';';
                $attributes['style'] = $existingStyle . $separator . $attributes['style'];
            }
            foreach ($attributes as $name => $value) {
                $tag .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }

            return $root[1] . $tag . '>';
        }, $html, 1) ?? $html;
    }
}
