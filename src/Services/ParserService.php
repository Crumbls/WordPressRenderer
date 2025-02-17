<?php

namespace Crumbls\WordPressRenderer\Services;

use DOMDocument;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * ParserService - Converts WordPress shortcodes to Laravel Blade components
 *
 * This service class handles the transformation of WordPress shortcodes into Laravel Blade
 * components (x-components). It maintains the hierarchical structure of nested shortcodes
 * and properly handles their attributes.
 *
 * @package Crumbls\WordPressRenderer\Services
 */
class ParserService
{
    /**
     * Convert WordPress shortcodes in content to Blade components
     *
     * Takes WordPress content containing shortcodes and converts them to equivalent
     * Laravel Blade x-components while preserving attributes and nesting structure.
     *
     * @param string $content The WordPress content containing shortcodes
     * @return string The transformed content with Blade components
     */
    public function parse(string $content): string
    {
        if (empty($content)) {
            return $content;
        }

        // Extract and transform all shortcodes
        $shortcodes = $this->getFullShortcodes($content);

        // Get unique non-self-closing tags for replacement
        $uniqueNonSelfClosingTags = $this->getUniqueNonSelfClosingTags($shortcodes);

        // Replace each shortcode with its Blade component equivalent
        foreach ($shortcodes as $shortcode) {
            $content = str_replace($shortcode['raw'], $shortcode['newRaw'], $content);
        }

        // Replace closing tags
        $content = str_replace(
            array_keys($uniqueNonSelfClosingTags),
            array_values($uniqueNonSelfClosingTags),
            $content
        );

        return $content;
    }

    /**
     * Extract and parse all shortcodes from content
     *
     * Identifies all shortcodes in the content, including nested ones, and converts
     * them to their Blade component equivalents while maintaining proper structure.
     *
     * @param string $content The content to parse
     * @return array Array of parsed shortcode data
     */
    protected function getFullShortcodes(string $content): array
    {
        $shortcodes = [];
        $stack = [];

        // Matches both opening and closing shortcode tags
        $regex = '/\[(\/?)([\w_-]+)([^\]]*?)(\/)?\]/';

        if (preg_match_all($regex, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                // Extract shortcode components
                $isClosing = !empty($match[1][0]);
                $originalTag = $match[2][0];
                $kebabTag = Str::kebab(str_replace('_', '-', $originalTag));
                $attributes = trim($match[3][0]);
                $isSelfClosing = !empty($match[4][0]);
                $position = $match[0][1];
                $raw = $match[0][0];

                // Parse and format attributes
                $attributesParsed = $this->getParsedAttributes($attributes);
                $attributes = $this->getAttributeString($attributesParsed);

                if ($isClosing) {
                    $this->handleClosingTag($shortcodes, $stack, $raw, $kebabTag, $position, $attributesParsed, $originalTag);
                } else {
                    $this->handleOpeningTag($shortcodes, $stack, $raw, $kebabTag, $attributes, $isSelfClosing, $position, $originalTag, $attributesParsed);
                }
            }

            $this->closeUnclosedTags($shortcodes, $stack, $content);
        }

        return $shortcodes;
    }

    /**
     * Handle processing of a closing shortcode tag
     *
     * @param array &$shortcodes Reference to shortcodes array
     * @param array &$stack Reference to processing stack
     * @param string $raw Raw shortcode
     * @param string $kebabTag Kebab-case tag name
     * @param int $position Position in content
     * @param array $attributesParsed Parsed attributes
     * @param string $originalTag Original shortcode tag
     */
    protected function handleClosingTag(array &$shortcodes, array &$stack, string $raw, string $kebabTag, int $position, array $attributesParsed, string $originalTag): void
    {
        if (!empty($stack)) {
            $openIndex = array_pop($stack);
            $shortcodes[$openIndex]['hasClosingTag'] = true;
            $shortcodes[$openIndex]['closingTag'] = [
                'raw' => $raw,
                'newRaw' => "</x-{$kebabTag}>",
                'position' => $position,
                'attributes' => $attributesParsed,
                'tag' => $originalTag
            ];
        }
    }

    /**
     * Handle processing of an opening shortcode tag
     *
     * @param array &$shortcodes Reference to shortcodes array
     * @param array &$stack Reference to processing stack
     * @param string $raw Raw shortcode
     * @param string $kebabTag Kebab-case tag name
     * @param string $attributes Formatted attribute string
     * @param bool $isSelfClosing Whether tag is self-closing
     * @param int $position Position in content
     * @param string $originalTag Original shortcode tag
     * @param array $attributesParsed Parsed attributes array
     */
    protected function handleOpeningTag(array &$shortcodes, array &$stack, string $raw, string $kebabTag, string $attributes, bool $isSelfClosing, int $position, string $originalTag, array $attributesParsed): void
    {
        $newRaw = $isSelfClosing
            ? "<x-{$kebabTag} {$attributes} />"
            : "<x-{$kebabTag} {$attributes}>";

        $shortcode = [
            'tag' => $originalTag,
            'kebabTag' => $kebabTag,
            'raw' => $raw,
            'newRaw' => $newRaw,
            'selfClosing' => $isSelfClosing,
            'position' => $position,
            'hasClosingTag' => false,
            'attributes' => $attributesParsed,
            'level' => count($stack)
        ];

        if (!$isSelfClosing) {
            array_push($stack, count($shortcodes));
        }

        $shortcodes[] = $shortcode;
    }

    /**
     * Close any remaining unclosed tags
     *
     * @param array &$shortcodes Reference to shortcodes array
     * @param array &$stack Reference to processing stack
     * @param string $content Original content string
     */
    protected function closeUnclosedTags(array &$shortcodes, array &$stack, string $content): void
    {
        while (!empty($stack)) {
            $index = array_pop($stack);
            $tag = $shortcodes[$index]['kebabTag'];

            if (!$shortcodes[$index]['selfClosing']
                && !$shortcodes[$index]['hasClosingTag']
                && !Str::endsWith($shortcodes[$index]['newRaw'], '/>')
            ) {
                $shortcodes[$index]['newRaw'] = trim(substr($shortcodes[$index]['newRaw'], 0, -1)) . ' />';
                $shortcodes[$index]['selfClosing'] = true;
            } else if (!$shortcodes[$index]['hasClosingTag']) {
                $shortcodes[$index]['hasClosingTag'] = true;
                $shortcodes[$index]['closingTag'] = [
                    'raw' => "[/{$shortcodes[$index]['tag']}]",
                    'newRaw' => "</x-{$tag}>",
                    'position' => strlen($content),
                    'attributes' => [],
                    'tag' => $shortcodes[$index]['tag']
                ];
            }
        }
    }

    /**
     * Extract non-self-closing tags from shortcodes array
     *
     * @param array $shortcodes Array of parsed shortcodes
     * @return array Associative array of closing tags
     */
    protected function getUniqueNonSelfClosingTags(array $shortcodes): array
    {
        return array_column(
            array_column(
                array_filter($shortcodes, fn($tag) => $tag['hasClosingTag']),
                'closingTag'
            ),
            'newRaw',
            'raw'
        );
    }

    /**
     * Parse shortcode attributes into an associative array
     *
     * Handles various attribute formats including quoted values,
     * unquoted values, and special character handling.
     *
     * @param string $attributes Raw attribute string
     * @return array Associative array of parsed attributes
     */
    protected function getParsedAttributes(string $attributes): array
    {
        $pairs = [];

        // Match attributes with quoted or unquoted values
        $pattern = '/([a-zA-Z0-9_]+)\s*=\s*([^"\'\s][^\s]*|"[^"]*"|\'[^\']*\')/';

        preg_match_all($pattern, $attributes, $matches);

        for ($i = 0; $i < count($matches[1]); $i++) {
            $key = $matches[1][$i];
            $value = $matches[2][$i];
            $value = $this->stripWrappingCharacters($value);
            $pairs[$key] = $value;
        }

        return $pairs;
    }

    /**
     * Clean up attribute values by removing wrapping characters and handling special encodings
     *
     * Handles various quote types and special character encodings,
     * particularly for compatibility with Divi shortcodes.
     *
     * @param string|null $value The attribute value to clean
     * @return string|null Cleaned attribute value
     */
    protected function stripWrappingCharacters($value)
    {
        if (empty($value)) {
            return $value;
        }

        $value = mb_convert_encoding($value, 'UTF-8', 'auto');

        $lead = substr($value, 0, 1);
        $tail = substr($value, -1);

        $leadOrd = ord($lead);
        $tailOrd = ord($tail);

        // Handle standard quote wrapping
        if ($lead === $tail && in_array($lead, ['"', "'"])) {
            return substr($value, 1, -1);
        }

        // Handle special character encoding (e.g., Divi shortcodes)
        if ($leadOrd === 226) {
            $cleaned = preg_replace('/^\xe2\x80[\x9d\xb3]|\xe2\x80[\x9d\xb3]$/', '', $value);

            if (in_array($tailOrd, [157, 179])) {
                return $cleaned;
            }

            return $cleaned;
        }

        return $value;
    }

    /**
     * Convert an array of attributes to an HTML attribute string
     *
     * Converts array key-value pairs to HTML attributes,
     * properly escaped and formatted.
     *
     * @param array $input Array of attributes
     * @return string Formatted HTML attribute string
     */
    protected function getAttributeString(array $input): string
    {
        $attributes = array_map(
            function ($key, $value) {
                $camelKey = Str::camel($key);
                $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                return sprintf('%s="%s"', $camelKey, $escapedValue);
            },
            array_keys($input),
            array_values($input)
        );

        return implode(' ', $attributes);
    }
}
