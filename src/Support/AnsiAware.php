<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

namespace SoloTerm\Solo\Support;

class AnsiAware
{
    public static function mb_strlen($string): int
    {
        // Return length of the plain string
        return mb_strlen(static::plain($string));
    }

    public static function plain($string): string
    {
        // Regular expression to match ANSI escape sequences
        $ansiEscapeSequence = '/\x1b\[[0-9;]*[A-Za-z]/';

        // Remove ANSI escape sequences
        return preg_replace($ansiEscapeSequence, '', $string);
    }

    public static function substr($string, $start, $length = null): string
    {
        $ansiEscapeSequence = '/(\x1b\[[0-9;]*[mGK])/';
        $parts = preg_split($ansiEscapeSequence, $string, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $currentPos = 0; // Position in printable characters.
        $substringParts = []; // Parts of the substring.
        $openAnsiCodes = []; // Array of open ANSI codes.
        $collecting = false; // Whether we are collecting substring parts.
        $startPos = $start; // Starting position.

        // If $length is null, we need to go till the end of the string.
        $endPos = is_null($length) ? PHP_INT_MAX : $start + $length;

        foreach ($parts as $part) {
            if (preg_match($ansiEscapeSequence, $part)) {
                // It's an ANSI code.
                // Update $openAnsiCodes accordingly.
                if (str_contains($part, 'm')) {
                    // It's an SGR code.
                    $sgrParams = substr($part, 2, -1); // Remove "\e[" and "m"
                    $sgrCodes = explode(';', $sgrParams);

                    foreach ($sgrCodes as $code) {
                        $code = intval($code);
                        if ($code == 0) {
                            // Reset all attributes.
                            $openAnsiCodes = [];
                        } else {
                            if (($code >= 30 && $code <= 37) || ($code >= 90 && $code <= 97)) {
                                // Set foreground color.
                                // Remove any existing foreground color codes.
                                $openAnsiCodes = array_filter($openAnsiCodes, function ($c) {
                                    return !(($c >= 30 && $c <= 37) || ($c >= 90 && $c <= 97));
                                });
                            } else {
                                if (($code >= 40 && $code <= 47) || ($code >= 100 && $code <= 107)) {
                                    // Set background color.
                                    // Remove any existing background color codes.
                                    $openAnsiCodes = array_filter($openAnsiCodes, function ($c) {
                                        return !(($c >= 40 && $c <= 47) || ($c >= 100 && $c <= 107));
                                    });
                                }
                            }
                            $openAnsiCodes[] = $code;
                        }
                    }
                }
                // If we are collecting, we need to include this ANSI code.
                if ($collecting) {
                    $substringParts[] = $part;
                }

                continue;
            }

            // It's a printable text part.
            $partLength = mb_strlen($part);

            if ($currentPos + $partLength <= $startPos) {
                // This part is entirely before the start position.
                $currentPos += $partLength;

                continue;
            }

            if ($currentPos >= $endPos) {
                // We have already reached or passed the end position.
                break;
            }

            // Now, part of this $part is within the desired range.
            $partStart = 0;
            $partEnd = $partLength;

            if ($currentPos < $startPos) {
                // The desired start position is within this part.
                $partStart = $startPos - $currentPos;
            }

            if ($currentPos + $partLength > $endPos) {
                // The desired end position is within this part.
                $partEnd = $endPos - $currentPos;
            }

            // Extract the substring from this part.
            $substring = mb_substr($part, $partStart, $partEnd - $partStart);

            // If we are just starting to collect, prepend open ANSI codes.
            if (!$collecting) {
                $collecting = true;
                if (!empty($openAnsiCodes)) {
                    // Build the ANSI code string.
                    $ansiCodeString = "\e[" . implode(';', $openAnsiCodes) . 'm';
                    $substringParts[] = $ansiCodeString;
                }
            }

            $substringParts[] = $substring;

            $currentPos += $partLength;
        }

        // Close any open ANSI codes.
        if ($collecting && !empty($openAnsiCodes)) {
            // Append reset code.
            $substringParts[] = "\e[0m";
        }

        return implode('', $substringParts);
    }

    public static function wordwrap($string, $width = 75, $break = PHP_EOL, $cut = false): string
    {
        $ansiEscapeSequence = '/(\x1b\[[0-9;]*[mGK])/';
        $wordsPattern = '/(\S+\s+)/';

        // Split the string into an array of printable characters and ANSI codes
        $parts = preg_split($ansiEscapeSequence, $string, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $lines = [];
        $currentLine = '';
        $currentLength = 0;
        $openAnsiCodes = '';

        foreach ($parts as $part) {
            if (preg_match($ansiEscapeSequence, $part)) {
                // ANSI code, append without affecting length
                $currentLine .= $part;

                // Update the openAnsiCodes
                if (str_contains($part, 'm')) { // SGR (Select Graphic Rendition) codes
                    if ($part == "\e[0m") {
                        // Reset code, clear openAnsiCodes
                        $openAnsiCodes = '';
                    } else {
                        // Add to openAnsiCodes
                        $openAnsiCodes .= $part;
                    }
                }

                continue;
            }

            // Split the part into words or characters based on $cut
            $wordsOrChars = $cut
                // Cut the string at exact length
                ? mb_str_split($part)
                // Split the part into words
                : preg_split($wordsPattern, $part, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

            foreach ($wordsOrChars as $wordOrChar) {
                $length = mb_strlen($wordOrChar);

                if ($currentLength + $length > $width) {
                    // Exceeds the width, wrap to the next line
                    if ($currentLine !== '') {
                        // Close any open ANSI codes
                        if ($openAnsiCodes !== '') {
                            $currentLine .= "\e[0m";
                        }
                        $lines[] = $currentLine;
                    }
                    // Start new line with open ANSI codes
                    $currentLine = $openAnsiCodes . $wordOrChar;
                    $currentLength = $length;
                } else {
                    // Append the character to the current line
                    $currentLine .= $wordOrChar;
                    $currentLength += $length;
                }
            }
        }

        // Append any remaining text
        if ($currentLine !== '') {
            // Close any open ANSI codes
            if ($openAnsiCodes !== '') {
                $currentLine .= "\e[0m";
            }

            $lines[] = $currentLine;
        }

        return implode($break, $lines);
    }
}
