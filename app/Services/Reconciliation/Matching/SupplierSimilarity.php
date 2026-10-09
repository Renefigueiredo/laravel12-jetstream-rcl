<?php

namespace App\Services\Reconciliation\Matching;

final class SupplierSimilarity
{
    /**
     * Connectives say nothing about who the supplier is.
     *
     * @var list<string>
     */
    private const CONNECTIVES = ['DE', 'DA', 'DO', 'DAS', 'DOS', 'E', 'EM'];

    /**
     * A word this long may carry one typing mistake and still be taken as the same word.
     */
    private const TYPO_TOLERANT_WORD_LENGTH = 5;

    /**
     * What a word matched despite a typing mistake is worth, against 100 for an identical one.
     */
    private const TYPO_MATCH_WEIGHT = 85;

    /**
     * The most two names may score on overall likeness while a word of one is missing from the other.
     */
    private const UNCONFIRMED_LIKENESS_CEILING = 89;

    /**
     * @var array<string, int>
     */
    private array $cache = [];

    /**
     * Compatibility between two normalized supplier names, from 0 to 100.
     *
     * The higher of two measures: how alike the whole names are, and how much of the
     * shorter name is contained in the longer one. The result is the same in both directions.
     */
    public function between(string $first, string $second): int
    {
        if ($first === '' || $second === '') {
            return 0;
        }

        if ($first === $second) {
            return 100;
        }

        [$first, $second] = strcmp($first, $second) <= 0 ? [$first, $second] : [$second, $first];

        return $this->cache[$first."\n".$second] ??= $this->measure($first, $second);
    }

    /**
     * How alike the whole names are only earns an automatic match when every word of the shorter
     * name has a counterpart in the other. "FN COMERCIO DE ALIMENTOS" and "JR COMERCIO DE
     * ALIMENTOS" are two letters apart and two different companies.
     */
    private function measure(string $first, string $second): int
    {
        [$matched, $words, $everyWordFound] = $this->sharedWords($first, $second);

        $likeness = $this->likeness($first, $second);

        if (! $everyWordFound) {
            $likeness = min($likeness, self::UNCONFIRMED_LIKENESS_CEILING);
        }

        return max($likeness, $words < 2 ? 0 : intdiv($matched, $words));
    }

    private function likeness(string $first, string $second): int
    {
        $longest = max(strlen($first), strlen($second));

        if ($longest > 255) {
            similar_text($first, $second, $percent);

            return (int) floor($percent);
        }

        return (int) floor(100 * (1 - $this->typingDistance($first, $second) / $longest));
    }

    /**
     * Edits needed to turn one name into the other, counting two swapped neighbouring letters
     * ("LAVANDEIRA" for "LAVANDERIA") as a single typing mistake.
     */
    private function typingDistance(string $first, string $second): int
    {
        $plain = levenshtein($first, $second);

        if ($plain < 2 || $plain * 2 > max(strlen($first), strlen($second)) || ! $this->hasSwappedNeighbours($first, $second)) {
            return $plain;
        }

        $firstLength = strlen($first);
        $secondLength = strlen($second);
        $beforePrevious = [];
        $previous = range(0, $secondLength);

        for ($i = 1; $i <= $firstLength; $i++) {
            $current = [$i];

            for ($j = 1; $j <= $secondLength; $j++) {
                $cost = $first[$i - 1] === $second[$j - 1] ? 0 : 1;
                $current[$j] = min($previous[$j] + 1, $current[$j - 1] + 1, $previous[$j - 1] + $cost);

                if ($i > 1 && $j > 1 && $first[$i - 1] === $second[$j - 2] && $first[$i - 2] === $second[$j - 1]) {
                    $current[$j] = min($current[$j], $beforePrevious[$j - 2] + 1);
                }
            }

            $beforePrevious = $previous;
            $previous = $current;
        }

        return $previous[$secondLength];
    }

    /**
     * Whether two neighbouring letters of one name appear in the opposite order in the other.
     * Without that, no swap can shorten the distance and the plain one is already the answer.
     */
    private function hasSwappedNeighbours(string $first, string $second): bool
    {
        $pairs = [];

        for ($index = strlen($second) - 2; $index >= 0; $index--) {
            $pairs[$second[$index + 1].$second[$index]] = true;
        }

        for ($index = strlen($first) - 2; $index >= 0; $index--) {
            if ($first[$index] !== $first[$index + 1] && isset($pairs[$first[$index].$first[$index + 1]])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match the words of the shorter name against the longer one. A word with a single typing
     * mistake ("ALIMENTO" for "ALIMENTOS") still counts, for a little less than an identical word.
     *
     * @return array{0: int, 1: int, 2: bool} Points earned (100 per identical word), words in the
     *                                        shorter name, and whether every one of them was found
     */
    private function sharedWords(string $first, string $second): array
    {
        $firstWords = $this->words($first);
        $secondWords = $this->words($second);

        [$shorter, $longer] = count($firstWords) <= count($secondWords) ? [$firstWords, $secondWords] : [$secondWords, $firstWords];

        $available = array_count_values($longer);
        $unmatched = [];
        $matched = 0;
        $found = 0;

        foreach ($shorter as $word) {
            if (($available[$word] ?? 0) > 0) {
                $available[$word]--;
                $matched += 100;
                $found++;
            } else {
                $unmatched[] = $word;
            }
        }

        foreach ($unmatched as $word) {
            foreach ($available as $candidate => $left) {
                if ($left > 0 && $this->sameWordMistyped($word, (string) $candidate)) {
                    $available[$candidate]--;
                    $matched += self::TYPO_MATCH_WEIGHT;
                    $found++;

                    break;
                }
            }
        }

        return [$matched, count($shorter), $shorter !== [] && $found === count($shorter)];
    }

    private function sameWordMistyped(string $first, string $second): bool
    {
        return min(strlen($first), strlen($second)) >= self::TYPO_TOLERANT_WORD_LENGTH
            && abs(strlen($first) - strlen($second)) <= 1
            && $this->typingDistance($first, $second) <= 1;
    }

    /**
     * @return list<string>
     */
    private function words(string $name): array
    {
        return array_values(array_filter(
            explode(' ', $name),
            fn (string $word): bool => $word !== '' && ! in_array($word, self::CONNECTIVES, true),
        ));
    }
}
