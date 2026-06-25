<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_aiescape\ai;

/**
 * Parses and validates the JSON response from the AI.
 *
 * Falls back gracefully when the AI returns malformed JSON.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_parser {
    /** @var array Valid step change values. */
    const VALID_STEPCHANGES = [-1, 0, 1];

    /** @var array Valid choice types. */
    const VALID_CHOICE_TYPES = ['good', 'neutral', 'bad'];

    /**
     * Parses an AI response string into a structured result array.
     *
     * @param string $responsetext Raw text returned by the AI
     * @param string $gamemode     The current game mode
     * @param int    $choicesgood    Expected number of good choices
     * @param int    $choicesneutral Expected number of neutral choices
     * @param int    $choicesbad     Expected number of bad choices
     * @return array {narrative, completed, stepchange, choices}
     */
    public function parse(
        string $responsetext,
        string $gamemode,
        int $choicesgood = 1,
        int $choicesneutral = 1,
        int $choicesbad = 1
    ): array {
        $responsetext = trim($responsetext);

        // Strip markdown code fences if the AI wrapped the JSON.
        // phpcs:disable moodle.Strings.ForbiddenStrings.Found
        $responsetext = preg_replace('/^```(?:json)?\s*/i', '', $responsetext);
        $responsetext = preg_replace('/\s*```$/', '', $responsetext);
        // phpcs:enable moodle.Strings.ForbiddenStrings.Found

        $data = json_decode($responsetext, true);

        if (!is_array($data)) {
            // JSON is malformed; try to extract narrative with regex before giving up.
            if (preg_match('/"narrative"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $responsetext, $m)) {
                return $this->fallback(stripslashes($m[1]));
            }
            return $this->fallback($responsetext);
        }

        $narrative  = isset($data['narrative']) ? (string) $data['narrative'] : $responsetext;
        $completed  = !empty($data['completed']);
        $stepchange = isset($data['stepchange']) ? (int) $data['stepchange'] : 0;

        if (!in_array($stepchange, self::VALID_STEPCHANGES, true)) {
            $stepchange = 0;
        }

        $result = [
            'narrative'  => $narrative,
            'completed'  => $completed,
            'stepchange' => $stepchange,
            'choices'    => [],
        ];

        if ($gamemode === 'multichoice' || $gamemode === 'combo') {
            $result['choices'] = $this->parse_choices(
                $data['choices'] ?? [],
                $choicesgood,
                $choicesneutral,
                $choicesbad
            );
        }

        return $result;
    }

    /**
     * Returns true when the choices array matches the expected counts.
     *
     * @param array $choices       Parsed choices array
     * @param int   $needgood
     * @param int   $needneutral
     * @param int   $needbad
     * @return bool
     */
    public function choices_match_expected(
        array $choices,
        int $needgood,
        int $needneutral,
        int $needbad
    ): bool {
        $counts = ['good' => 0, 'neutral' => 0, 'bad' => 0];
        foreach ($choices as $c) {
            $type = $c['type'] ?? '';
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
        }
        return $counts['good'] === $needgood
            && $counts['neutral'] === $needneutral
            && $counts['bad'] === $needbad;
    }

    /**
     * Parses and validates the choices array from the AI response.
     *
     * Accepts multiple choices of each type; takes exactly the needed count of each.
     *
     * @param mixed $raw          Raw value from decoded JSON
     * @param int   $needgood
     * @param int   $needneutral
     * @param int   $needbad
     * @return array Array of validated ['label' => string, 'type' => string] items
     */
    private function parse_choices($raw, int $needgood, int $needneutral, int $needbad): array {
        if (!is_array($raw)) {
            return [];
        }

        $bygood    = [];
        $byneutral = [];
        $bybad     = [];

        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ''));
            $type  = strtolower(trim((string) ($item['type'] ?? '')));

            if ($label === '' || !in_array($type, self::VALID_CHOICE_TYPES, true)) {
                continue;
            }

            match ($type) {
                'good'    => $bygood[]    = ['label' => $label, 'type' => 'good'],
                'neutral' => $byneutral[] = ['label' => $label, 'type' => 'neutral'],
                'bad'     => $bybad[]     = ['label' => $label, 'type' => 'bad'],
            };
        }

        if (count($bygood) < $needgood || count($byneutral) < $needneutral || count($bybad) < $needbad) {
            return [];
        }

        $choices = [];
        foreach (array_slice($bygood, 0, $needgood) as $c) {
            $choices[] = $c;
        }
        foreach (array_slice($byneutral, 0, $needneutral) as $c) {
            $choices[] = $c;
        }
        foreach (array_slice($bybad, 0, $needbad) as $c) {
            $choices[] = $c;
        }

        return $choices;
    }

    /**
     * Produces a safe fallback result when JSON parsing fails entirely.
     *
     * @param string $text The raw text to use as the narrative
     * @return array
     */
    private function fallback(string $text): array {
        return [
            'narrative'  => $text,
            'completed'  => false,
            'stepchange' => 0,
            'choices'    => [],
        ];
    }
}
