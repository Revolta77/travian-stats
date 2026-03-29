<?php

namespace App\Services;

use RuntimeException;

/**
 * Vykoná veľký SQL dump po častiach pod max. veľkosť paketu (MySQL max_allowed_packet).
 * Delenie na príkazy rešpektuje reťazce ', ", `, komentáre a UTF-8 (hranice sú na bajtoch, ; je ASCII).
 * Veľké INSERT … VALUES (…),(…) rozdelí na viac INSERTov po riadkoch (tuple), tiež s rešpektom k reťazcom.
 */
final class MapSqlUnpreparedBatcher
{
    /** Bezpečná rezerva pod 8 MiB limit hostingu. */
    public const DEFAULT_MAX_BYTES = 7 * 1024 * 1024;

    /**
     * @param  callable(string): void  $execute  napr. fn (string $q) => DB::unprepared($q)
     */
    public static function execute(string $sql, callable $execute, ?int $maxBytes = null): void
    {
        $max = $maxBytes ?? self::DEFAULT_MAX_BYTES;
        $statements = self::splitStatements($sql);
        $batch = '';

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') {
                continue;
            }

            $pieces = strlen($stmt) > $max
                ? self::splitOversizedStatement($stmt, $max)
                : [$stmt];

            foreach ($pieces as $piece) {
                $piece = trim($piece);
                if ($piece === '') {
                    continue;
                }
                $piece = rtrim($piece, ';');
                $candidate = $batch === '' ? $piece : $batch.';'.$piece;
                if (strlen($candidate) > $max) {
                    if ($batch !== '') {
                        $execute($batch.';');
                    }
                    if (strlen($piece) > $max) {
                        throw new RuntimeException(
                            'SQL stále prekračuje limit '.$max.' B aj po rozdelení INSERT — jeden riadok x_world je príliš veľký alebo server má menší max_allowed_packet.'
                        );
                    }
                    $batch = $piece;
                } else {
                    $batch = $candidate;
                }
            }
        }

        if ($batch !== '') {
            $execute($batch.';');
        }
    }

    /**
     * @return list<string>
     */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $len = strlen($sql);
        $start = 0;
        $i = 0;
        $inLineComment = false;
        $inBlockComment = false;
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;

        while ($i < $len) {
            $c = $sql[$i];

            if ($inLineComment) {
                if ($c === "\n" || $c === "\r") {
                    $inLineComment = false;
                }
                $i++;

                continue;
            }

            if ($inBlockComment) {
                if ($c === '*' && $i + 1 < $len && $sql[$i + 1] === '/') {
                    $inBlockComment = false;
                    $i += 2;

                    continue;
                }
                $i++;

                continue;
            }

            if ($inSingle) {
                if ($c === '\\' && $i + 1 < $len) {
                    $i += 2;

                    continue;
                }
                if ($c === "'" && $i + 1 < $len && $sql[$i + 1] === "'") {
                    $i += 2;

                    continue;
                }
                if ($c === "'") {
                    $inSingle = false;
                }
                $i++;

                continue;
            }

            if ($inDouble) {
                if ($c === '\\' && $i + 1 < $len) {
                    $i += 2;

                    continue;
                }
                if ($c === '"' && $i + 1 < $len && $sql[$i + 1] === '"') {
                    $i += 2;

                    continue;
                }
                if ($c === '"') {
                    $inDouble = false;
                }
                $i++;

                continue;
            }

            if ($inBacktick) {
                if ($c === '`' && $i + 1 < $len && $sql[$i + 1] === '`') {
                    $i += 2;

                    continue;
                }
                if ($c === '`') {
                    $inBacktick = false;
                }
                $i++;

                continue;
            }

            if ($c === "'") {
                $inSingle = true;
                $i++;

                continue;
            }
            if ($c === '"') {
                $inDouble = true;
                $i++;

                continue;
            }
            if ($c === '`') {
                $inBacktick = true;
                $i++;

                continue;
            }
            if ($c === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
                $inLineComment = true;
                $i += 2;

                continue;
            }
            if ($c === '#') {
                $inLineComment = true;
                $i++;

                continue;
            }
            if ($c === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
                $inBlockComment = true;
                $i += 2;

                continue;
            }

            if ($c === ';') {
                $stmt = trim(substr($sql, $start, $i - $start));
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $start = $i + 1;
            }
            $i++;
        }

        $tail = trim(substr($sql, $start));
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }

    /**
     * @return list<string>
     */
    private static function splitOversizedStatement(string $stmt, int $maxBytes): array
    {
        if (preg_match('/\bINSERT\b/i', $stmt) === 1 && preg_match('/\bVALUES\s*\(/i', $stmt) === 1) {
            return self::splitLargeInsert($stmt, $maxBytes);
        }

        throw new RuntimeException(
            'Jeden SQL príkaz je väčší ako '.$maxBytes.' B a neviem ho bezpečne rozdeliť (očakávaný tvar INSERT … VALUES). Skráť export, zvýš max_allowed_packet, alebo rozdeľ súbor ručne.'
        );
    }

    /**
     * @return list<string>
     */
    private static function splitLargeInsert(string $stmt, int $maxBytes): array
    {
        $stmt = rtrim(trim($stmt), ';');
        $valuesPos = stripos($stmt, 'VALUES');
        if ($valuesPos === false) {
            throw new RuntimeException('INSERT bez VALUES — neviem rozdeliť.');
        }

        $open = strpos($stmt, '(', $valuesPos);
        if ($open === false) {
            throw new RuntimeException('INSERT VALUES bez zátvorky — neviem rozdeliť.');
        }

        $prefix = substr($stmt, 0, $open);
        $valuePart = substr($stmt, $open);
        $rows = self::splitValueTuples($valuePart);

        if ($rows === []) {
            throw new RuntimeException('Nepodarilo sa rozparsovať riadky VALUES.');
        }

        if (count($rows) === 1 && strlen($stmt) > $maxBytes) {
            throw new RuntimeException(
                'Jeden riadok INSERT (jedna dedina) je väčší ako '.$maxBytes.' B — zníž limit dát alebo zvýš max_allowed_packet.'
            );
        }

        $chunks = [];
        $bucket = [];

        foreach ($rows as $row) {
            $tryBucket = array_merge($bucket, [$row]);
            $candidate = $prefix.implode(',', $tryBucket).';';
            if (strlen($candidate) > $maxBytes) {
                if ($bucket === []) {
                    throw new RuntimeException(
                        'Jeden riadok x_world je väčší ako '.$maxBytes.' B — server nepovoľuje taký veľký paket.'
                    );
                }
                $chunks[] = $prefix.implode(',', $bucket).';';
                $bucket = [$row];
                $one = $prefix.$row.';';
                if (strlen($one) > $maxBytes) {
                    throw new RuntimeException(
                        'Jeden riadok x_world je väčší ako '.$maxBytes.' B.'
                    );
                }
            } else {
                $bucket = $tryBucket;
            }
        }

        if ($bucket !== []) {
            $chunks[] = $prefix.implode(',', $bucket).';';
        }

        return $chunks;
    }

    /**
     * Rozparsuje "(a,b),(c,d)" na ["(a,b)","(c,d)"] s rešpektom k obsahu v zátvorkách a reťazcoch.
     *
     * @return list<string>
     */
    public static function splitValueTuples(string $s): array
    {
        $s = trim($s);
        $len = strlen($s);
        $rows = [];
        $i = 0;

        while ($i < $len && ctype_space($s[$i])) {
            $i++;
        }

        if ($i >= $len || $s[$i] !== '(') {
            return $s !== '' ? [$s] : [];
        }

        while ($i < $len) {
            while ($i < $len && ctype_space($s[$i])) {
                $i++;
            }
            if ($i >= $len) {
                break;
            }
            if ($s[$i] !== '(') {
                break;
            }

            $rowStart = $i;
            $depth = 0;
            $inSingle = false;
            $inDouble = false;
            $inBacktick = false;

            for (; $i < $len; $i++) {
                $c = $s[$i];

                if ($inSingle) {
                    if ($c === '\\' && $i + 1 < $len) {
                        $i++;

                        continue;
                    }
                    if ($c === "'" && $i + 1 < $len && $s[$i + 1] === "'") {
                        $i++;

                        continue;
                    }
                    if ($c === "'") {
                        $inSingle = false;
                    }

                    continue;
                }

                if ($inDouble) {
                    if ($c === '\\' && $i + 1 < $len) {
                        $i++;

                        continue;
                    }
                    if ($c === '"' && $i + 1 < $len && $s[$i + 1] === '"') {
                        $i++;

                        continue;
                    }
                    if ($c === '"') {
                        $inDouble = false;
                    }

                    continue;
                }

                if ($inBacktick) {
                    if ($c === '`' && $i + 1 < $len && $s[$i + 1] === '`') {
                        $i++;

                        continue;
                    }
                    if ($c === '`') {
                        $inBacktick = false;
                    }

                    continue;
                }

                if ($c === "'") {
                    $inSingle = true;

                    continue;
                }
                if ($c === '"') {
                    $inDouble = true;

                    continue;
                }
                if ($c === '`') {
                    $inBacktick = true;

                    continue;
                }

                if ($c === '(') {
                    $depth++;

                    continue;
                }

                if ($c === ')') {
                    $depth--;
                    if ($depth === 0) {
                        $rows[] = substr($s, $rowStart, $i - $rowStart + 1);
                        $i++;

                        break;
                    }
                }
            }

            while ($i < $len && ctype_space($s[$i])) {
                $i++;
            }
            if ($i < $len && $s[$i] === ',') {
                $i++;

                continue;
            }

            break;
        }

        return $rows;
    }
}
