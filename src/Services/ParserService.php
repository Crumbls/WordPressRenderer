<?php

namespace WPSC\Services;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class ParserService {
    protected function getFullShortcodes(string $content) : array {
        $shortcodes = [];
        $stack = [];

        // Match both opening and closing shortcodes
        $regex = '/\[(\/?)([\w_-]+)([^\]]*?)(\/)?\]/';

        if (preg_match_all($regex, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $is_closing = !empty($match[1][0]);  // Has a leading slash
                $original_tag = $match[2][0];        // The original tag name
                $kebabTag = Str::kebab(str_replace('_','-',$original_tag), '-'); // Convert to camel case
                $attributes = trim($match[3][0]);    // Any attributes
                $is_self_closing = !empty($match[4][0]); // Has a trailing slash
                $position = $match[0][1];            // Position in content
                $raw = $match[0][0];                 // Raw shortcode text

                // Create the new tag format
                if ($is_closing) {
                    $new_raw = "</x-{$kebabTag}>";
                } else {
                    $new_raw = $is_self_closing ?
                        "[{$kebabTag} {$attributes}/]" :
                        "<x-{$kebabTag} {$attributes}>";
                }

                // Handle self-closing tags (explicit or implicit)
                $is_self_closing = $is_self_closing ||
                    empty($attributes) || // Empty tags like [br]
                    in_array($original_tag, ['br']); // Known self-closing tags

                $attributesParsed = $this->getParsedAttributes($attributes);

                if (!$is_closing) {
                    $shortcode = [
                        'tag' => $original_tag,
                        'camel_tag' => $kebabTag,
                        'raw' => $raw,
                        'new_raw' => $new_raw,
                        'self_closing' => $is_self_closing,
                        'position' => $position,
                        'has_closing_tag' => false,
                        'attributes' => $attributesParsed,
                    ];

                    if (!$is_self_closing) {
                        array_push($stack, count($shortcodes));
                    }

                    $shortcodes[] = $shortcode;
                } else {
                    // Found a closing tag
                    if (!empty($stack)) {
                        $open_index = array_pop($stack);
                        $shortcodes[$open_index]['has_closing_tag'] = true;
                        $shortcodes[$open_index]['closing_tag'] = [
                            'raw' => $raw,
                            'new_raw' => $new_raw,
                            'position' => $position,
                            'attributes' => $attributesParsed,

                        ];
                    }
                }
            }

            // Any remaining tags in stack are implicitly self-closing
            while (!empty($stack)) {
                $index = array_pop($stack);
                $shortcodes[$index]['self_closing'] = true;
            }
        }

        return $shortcodes;
    }

    protected function getUniqueNonSelfClosingTags(array $shortcodes): array
    {
        return array_column(array_column(array_filter($shortcodes, function($tag) {
            return $tag['has_closing_tag'];
            return $tag['closing_tag'];
        }), 'closing_tag'), 'new_raw', 'raw');
    }

    public function convert(string $content): string
    {
        $shortcodes = $this->getFullShortcodes($content);
        $uniqueNonSelfClosingTags = $this->getUniqueNonSelfClosingTags($shortcodes);

        foreach($shortcodes as $shortcode) {
            $content = str_replace($shortcode['raw'], $shortcode['new_raw'], $content);
        };

        $content = str_replace(array_keys($uniqueNonSelfClosingTags), array_values($uniqueNonSelfClosingTags), $content);

        return str_replace('>', '>'.PHP_EOL, $content);

        return $content;
    }

    protected function getParsedAttributes(string $attributes): array
    {
        $parsed = [];

        if (empty($attributes)) {
            return $parsed;
        }

        $currentChar = 0;
        $length = strlen($attributes);

        while ($currentChar < $length) {
            // Skip whitespace
            while ($currentChar < $length && ctype_space($attributes[$currentChar])) {
                $currentChar++;
            }

            if ($currentChar >= $length) break;

            // Get attribute name
            $nameStart = $currentChar;
            while ($currentChar < $length && $attributes[$currentChar] !== '=') {
                $currentChar++;
            }
            $name = trim(substr($attributes, $nameStart, $currentChar - $nameStart));

            if ($currentChar >= $length) break;

            // Skip the equals sign
            $currentChar++;

            // Skip any whitespace after equals
            while ($currentChar < $length && ctype_space($attributes[$currentChar])) {
                $currentChar++;
            }

            if ($currentChar >= $length) break;

            // Get the raw value - find the end of the value
            $valueStart = $currentChar;

            if ($attributes[$currentChar] === '"' || $attributes[$currentChar] === "'" ||
                $attributes[$currentChar] === '"' || $attributes[$currentChar] === '"' ||
                $attributes[$currentChar] === '″' || $attributes[$currentChar] === '″') {
//            if ($attributes[$currentChar] === '"' || $attributes[$currentChar] === "'") {
                // Quoted value
                $quote = $attributes[$currentChar];
                $currentChar++;
                $valueStart = $currentChar;
                while ($currentChar < $length && $attributes[$currentChar] !== $quote) {
                    $currentChar++;
                }
                $value = substr($attributes, $valueStart, $currentChar - $valueStart);
                $currentChar++; // Skip closing quote
            } else {
                // Unquoted value - read until whitespace or end
                while ($currentChar < $length && !ctype_space($attributes[$currentChar])) {
                    $currentChar++;
                }
                $value = substr($attributes, $valueStart, $currentChar - $valueStart);
            }

            if (!empty($name)) {
                $parsed[$name] = $value;
            }
        }

        return $parsed;
    }
}
