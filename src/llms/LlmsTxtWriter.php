<?php

namespace iti\craftitiplugin\llms;

/**
 * The plugin's section always lives between two HTML-comment marker lines, so it can be
 * replaced or removed later without touching anything a site owner wrote around it.
 */
final class LlmsTxtWriter
{
    public const BEGIN_MARKER = '<!-- BEGIN ITI Digital llms.txt -->';
    public const END_MARKER = '<!-- END ITI Digital llms.txt -->';

    public const STATUS_CREATED = 'created';
    public const STATUS_UPDATED = 'updated';
    public const STATUS_APPENDED = 'appended';
    public const STATUS_CONFLICT = 'conflict';
    public const STATUS_UNWRITABLE = 'unwritable';

    public static function wrap(string $content): string
    {
        return self::BEGIN_MARKER . "\n" . $content . "\n" . self::END_MARKER;
    }

    public static function hasBlock(string $content): bool
    {
        return self::markers($content) !== null;
    }

    /**
     * Returns the new file content, or null when the existing file isn't ours and
     * appending isn't allowed (the caller must leave it untouched).
     */
    public static function merge(?string $existing, string $block, bool $appendToExisting): ?string
    {
        if ($existing === null || trim($existing) === '') {
            return $block;
        }

        if (self::hasBlock($existing)) {
            [$before, $after] = self::split($existing);

            return $before . $block . $after;
        }

        if (!$appendToExisting) {
            return null;
        }

        return rtrim($existing) . "\n\n" . $block . "\n";
    }

    public static function remove(string $existing): string
    {
        if (!self::hasBlock($existing)) {
            return $existing;
        }

        [$before, $rawAfter] = self::split($existing);
        $before = rtrim($before, "\r\n");
        $after = ltrim($rawAfter, "\r\n");

        if ($before === '' && $after === '') {
            return '';
        }

        if ($before === '') {
            return $after;
        }

        if ($after === '') {
            // Nothing but line breaks followed the block: keep a trailing newline only
            // if there was actually something (even just blank lines) to end.
            return $before . ($rawAfter !== '' ? "\n" : '');
        }

        return $before . "\n\n" . $after;
    }

    public static function writeFile(string $path, string $block, bool $appendToExisting): string
    {
        $existing = is_file($path) ? @file_get_contents($path) : null;

        if ($existing === false) {
            return self::STATUS_UNWRITABLE;
        }

        $merged = self::merge($existing, $block, $appendToExisting);

        if ($merged === null) {
            return self::STATUS_CONFLICT;
        }

        $isBlank = $existing === null || trim($existing) === '';

        if ($isBlank) {
            $merged .= "\n";
        }

        $writable = $existing === null ? is_writable(dirname($path)) : is_writable($path);

        if (!$writable || @file_put_contents($path, $merged, LOCK_EX) === false) {
            return self::STATUS_UNWRITABLE;
        }

        if ($isBlank) {
            return self::STATUS_CREATED;
        }

        return self::hasBlock($existing) ? self::STATUS_UPDATED : self::STATUS_APPENDED;
    }

    /**
     * Returns true when, afterwards, no file holding our block remains: there was no
     * file to begin with, the file wasn't ours, our block was stripped out, or the file
     * (which held only our block) was deleted. Returns false only when an unlink or a
     * write actually failed, leaving our block in place.
     */
    public static function removeFromFile(string $path): bool
    {
        if (!is_file($path)) {
            return true;
        }

        $existing = @file_get_contents($path);

        if ($existing === false) {
            return false;
        }

        if (!self::hasBlock($existing)) {
            return true;
        }

        $remaining = self::remove($existing);

        if ($remaining === '') {
            return @unlink($path);
        }

        return @file_put_contents($path, $remaining, LOCK_EX) !== false;
    }

    /**
     * Locates our block as [beginPos, posRightAfterEndMarker], anchored on the last
     * BEGIN marker that precedes the first END marker. That keeps an orphaned BEGIN left
     * over earlier in the file (from some unrelated edit) from swallowing foreign text
     * into what we treat as our block.
     */
    private static function markers(string $content): ?array
    {
        $firstBegin = strpos($content, self::BEGIN_MARKER);

        if ($firstBegin === false) {
            return null;
        }

        // Ignore any END that appears before the first BEGIN (a stray leftover from
        // something else) so it can't be mistaken for closing our block.
        $end = strpos($content, self::END_MARKER, $firstBegin);

        if ($end === false) {
            return null;
        }

        $begin = strrpos(substr($content, 0, $end), self::BEGIN_MARKER);

        if ($begin === false) {
            return null;
        }

        return [$begin, $end + strlen(self::END_MARKER)];
    }

    /**
     * Splits around our block: [text before BEGIN, text after END].
     */
    private static function split(string $content): array
    {
        [$begin, $end] = self::markers($content);

        return [substr($content, 0, $begin), substr($content, $end)];
    }
}
