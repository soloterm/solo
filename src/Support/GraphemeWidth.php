<?php

/** @noinspection PhpComposerExtensionStubsInspection */

/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace SoloTerm\Solo\Support;

use Normalizer;

class GraphemeWidth
{
    public static $cache = [];

    // Pre-compiled regex patterns for better performance
    private static $maybeNeedsNormalizationPattern = '/[\p{M}\x{0300}-\x{036F}\x{1AB0}-\x{1AFF}\x{1DC0}-\x{1DFF}\x{20D0}-\x{20FF}]/u';
    private static $specialCharsPattern = '/[\x{200B}\x{200C}\x{200D}\x{FEFF}\x{2060}-\x{2064}\x{034F}\x{061C}\x{202A}-\x{202E}]|[\p{M}\x{0300}-\x{036F}\x{1AB0}-\x{1AFF}\x{1DC0}-\x{1DFF}\x{20D0}-\x{20FF}]|[\x{FE0E}\x{FE0F}]|[\x{1F000}-\x{1FFFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]|[\x{1100}-\x{11FF}\x{3000}-\x{303F}\x{3130}-\x{318F}\x{AC00}-\x{D7AF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{F900}-\x{FAFF}\x{FF00}-\x{FFEF}]/u';
    private static $variationSelectorsPattern = '/[\x{FE0E}\x{FE0F}]/u';
    private static $emojiPattern = '/[\x{1F000}-\x{1FFFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
    private static $eastAsianPattern = '/[\x{1100}-\x{11FF}\x{3000}-\x{303F}\x{3130}-\x{318F}\x{AC00}-\x{D7AF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{F900}-\x{FAFF}\x{FF00}-\x{FFEF}]/u';
    private static $textStyleEmojiPattern = '/^[\x{2600}-\x{26FF}\x{2700}-\x{27BF}]$/u';
    private static $flagSequencePattern = '/\p{Regional_Indicator}{2}|\x{1F3F4}[\x{E0060}-\x{E007F}]+/u';
    private static $zwjSequencePattern = '/[\x{200B}\x{200C}\x{200D}\x{FEFF}\x{2060}-\x{2064}\x{034F}\x{061C}\x{202A}-\x{202E}]+/u';
    private static $combiningMarksPattern = '/[\p{M}\x{0300}-\x{036F}\x{1AB0}-\x{1AFF}\x{1DC0}-\x{1DFF}\x{20D0}-\x{20FF}]+/u';

    // Zero-width characters lookup for O(1) check
    private static $zeroWidthChars = [
        "\u{200B}" => true, // Zero-width space
        "\u{200C}" => true, // Zero-width non-joiner
        "\u{200D}" => true, // Zero-width joiner
        "\u{FEFF}" => true, // Zero-width non-breaking space
        "\u{2060}" => true, // Word joiner
        "\u{2061}" => true, // Function application
        "\u{2062}" => true, // Invisible times
        "\u{2063}" => true, // Invisible separator
        "\u{2064}" => true, // Invisible plus
        "\u{034F}" => true, // Combining Grapheme Joiner
        "\u{061C}" => true, // Arabic Letter Mark
        "\u{202A}" => true, // Left-to-Right Embedding
        "\u{202B}" => true, // Right-to-Left Embedding
        "\u{202C}" => true, // Pop Directional Formatting
        "\u{202D}" => true, // Left-to-Right Override
        "\u{202E}" => true, // Right-to-Left Override
    ];

    public static function getGraphemeDisplayWidth(string $grapheme): int
    {
        // Check cache first (fastest path)
        if (isset(static::$cache[$grapheme])) {
            return static::$cache[$grapheme];
        }

        // Fast path for pure ASCII: If strlen == mb_strlen, it's single-byte only → width 1
        if (strlen($grapheme) === mb_strlen($grapheme)) {
            return static::$cache[$grapheme] = 1;
        }

        // Fast path: zero-width character check for single characters
        if (isset(static::$zeroWidthChars[$grapheme])) {
            return static::$cache[$grapheme] = 0;
        }

        // Handle ASCII + Zero Width sequences (like 'a‍')
        if (preg_match('/^[\x00-\x7F][\x{200B}\x{200C}\x{200D}\x{FEFF}\x{2060}-\x{2064}]+$/u', $grapheme)) {
            return static::$cache[$grapheme] = 1;
        }

        // Check for special flag sequence patterns (Scotland, England, etc.)
        if (preg_match(static::$flagSequencePattern, $grapheme)) {
            return static::$cache[$grapheme] = 2;
        }

        // Devanagari conjuncts and other complex scripts
        if (preg_match('/\p{Devanagari}/u', $grapheme)) {
            return static::$cache[$grapheme] = 1;
        }

        // Only normalize if there's a chance of combining marks
        if (preg_match(static::$maybeNeedsNormalizationPattern, $grapheme)) {
            $grapheme = Normalizer::normalize($grapheme, Normalizer::NFC);
        }

        // Special cases for characters followed by ZWJ/ZWNJ
        if (mb_strpos($grapheme, "\u{200D}") !== false || mb_strpos($grapheme, "\u{200C}") !== false) {
            // Check if it's a single character + ZWJ sequence
            if (mb_strlen(preg_replace(static::$zwjSequencePattern, '', $grapheme)) === 1) {
                // If it's an emoji + ZWJ, it should be width 2
                if (preg_match('/^[\x{1F300}-\x{1F6FF}][\x{200B}\x{200C}\x{200D}\x{FEFF}\x{2060}-\x{2064}]+$/u',
                    $grapheme)) {
                    return static::$cache[$grapheme] = 2;
                }

                // If it's a CJK/wide char + ZWJ, it should be width 2
                if (preg_match(static::$eastAsianPattern, mb_substr($grapheme, 0, 1))) {
                    return static::$cache[$grapheme] = 2;
                }

                // Otherwise, it should be width 1 (ASCII, Latin, etc. + ZWJ)
                return static::$cache[$grapheme] = 1;
            }

            // If it's an emoji ZWJ sequence
            if (preg_match('/[\x{1F300}-\x{1F6FF}]/u', $grapheme)) {
                return static::$cache[$grapheme] = 2;
            }
        }

        // Handle variation selectors
        if (preg_match(static::$variationSelectorsPattern, $grapheme)) {
            $baseChar = preg_replace(static::$variationSelectorsPattern, '', $grapheme);

            // Text style variation selector for emoji-capable symbols
            if (mb_strpos($grapheme, "\u{FE0E}") !== false) {
                // Check if it's an emoji-capable character
                if (preg_match(static::$textStyleEmojiPattern, $baseChar)) {
                    return static::$cache[$grapheme] = 1;
                }
            }

            // Check if emoji with variation selector
            if (preg_match(static::$emojiPattern, $baseChar)) {
                return static::$cache[$grapheme] = 2;
            }

            // Check if East Asian character with variation selector
            if (preg_match(static::$eastAsianPattern, $baseChar)) {
                return static::$cache[$grapheme] = 2;
            }

            // Otherwise, measure the base character
            $width = mb_strwidth($baseChar, 'UTF-8');
            return static::$cache[$grapheme] = ($width > 0) ? $width : 1;
        }

        // Check if the grapheme contains any zero-width characters
        $hasZeroWidth = false;
        foreach (static::$zeroWidthChars as $zwChar => $_) {
            if (mb_strpos($grapheme, $zwChar) !== false) {
                $hasZeroWidth = true;
                break;
            }
        }

        // If it has zero-width characters, we need special handling
        if ($hasZeroWidth) {
            // First, handle text with just formatting characters
            $filtered = preg_replace(static::$zwjSequencePattern, '', $grapheme);

            // If nothing is left after removing zero-width chars, or only combining marks left
            if ($filtered === '' || preg_match('/^' . static::$combiningMarksPattern . '$/u', $filtered)) {
                return static::$cache[$grapheme] = 0;
            }

            // Handle base char + combining marks + ZWJ
            if (preg_match('/^\p{L}' . static::$combiningMarksPattern . static::$zwjSequencePattern . '$/u',
                $grapheme)) {
                return static::$cache[$grapheme] = 1;
            }

            // If it's a single character + zero-width chars
            if (mb_strlen($filtered) === 1) {
                if (preg_match(static::$eastAsianPattern, $filtered)) {
                    return static::$cache[$grapheme] = 2;
                }
                return static::$cache[$grapheme] = 1;
            }
        }

        // Check for special characters - if none, do direct width calculation
        if (!preg_match(static::$specialCharsPattern, $grapheme)) {
            return static::$cache[$grapheme] = mb_strwidth($grapheme, 'UTF-8');
        }

        // Single letter followed by combining marks
        if (preg_match('/^\p{L}\p{M}+$/u', $grapheme)) {
            return static::$cache[$grapheme] = 1;
        }

        // Handle skin tones or flags (single grapheme)
        if (grapheme_strlen($grapheme) === 1) {
            if (preg_match('/[\x{1F3FB}-\x{1F3FF}]/u', $grapheme)) {
                return static::$cache[$grapheme] = 2;
            }
            if (preg_match('/^[\x{1F1E6}-\x{1F1FF}]{2}$/u', $grapheme)) {
                return static::$cache[$grapheme] = 2;
            }
        }

        // Specific check for wheelchair symbol and similar symbols
        if (mb_strpos($grapheme, "\u{267F}") !== false && mb_strpos($grapheme, "\u{FE0F}") === false) {
            return static::$cache[$grapheme] = 1;
        }

        // Default fallback to mb_strwidth, carefully filtering zero-width characters
        $filtered = preg_replace(static::$zwjSequencePattern, '', $grapheme);
        $width = mb_strwidth($filtered, 'UTF-8');

        return static::$cache[$grapheme] = ($width > 0) ? $width : 1;
    }
}