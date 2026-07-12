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

declare(strict_types=1);

namespace mod_aiescape;

use advanced_testcase;
use mod_aiescape\ai\response_parser;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for \mod_aiescape\ai\response_parser.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(response_parser::class)]
final class response_parser_test extends advanced_testcase {
    /**
     * Well-formed JSON parses cleanly and choices are split by type.
     */
    public function test_parse_well_formed_multichoice_response(): void {
        $parser = new response_parser();
        $json = json_encode([
            'narrative'  => 'You step forward.',
            'completed'  => false,
            'stepchange' => 1,
            'choices'    => [
                ['label' => 'Go left', 'type' => 'good'],
                ['label' => 'Wait', 'type' => 'neutral'],
                ['label' => 'Run away', 'type' => 'bad'],
            ],
        ]);

        $result = $parser->parse($json, 'multichoice', 1, 1, 1);

        $this->assertSame('You step forward.', $result['narrative']);
        $this->assertFalse($result['completed']);
        $this->assertSame(1, $result['stepchange']);
        $this->assertCount(3, $result['choices']);
        $this->assertTrue($parser->choices_match_expected($result['choices'], 1, 1, 1));
    }

    /**
     * Markdown code fences around the JSON payload are stripped before decoding.
     */
    public function test_parse_strips_markdown_code_fences(): void {
        $parser = new response_parser();
        $fence = str_repeat(chr(96), 3);
        $json = $fence . "json\n" . json_encode([
            'narrative'  => 'Fenced response.',
            'completed'  => false,
            'stepchange' => 0,
        ]) . "\n" . $fence;

        $result = $parser->parse($json, 'freetext');

        $this->assertSame('Fenced response.', $result['narrative']);
    }

    /**
     * Malformed JSON with a recoverable "narrative" key falls back to a regex extraction.
     */
    public function test_parse_malformed_json_recovers_narrative_via_regex(): void {
        $parser = new response_parser();
        $broken = '{"narrative": "Partial text before truncation"';

        $result = $parser->parse($broken, 'freetext');

        $this->assertSame('Partial text before truncation', $result['narrative']);
        $this->assertFalse($result['completed']);
        $this->assertSame(0, $result['stepchange']);
        $this->assertSame([], $result['choices']);
    }

    /**
     * Completely unparseable text falls back to using the raw text as the narrative.
     */
    public function test_parse_totally_malformed_response_uses_raw_text_fallback(): void {
        $parser = new response_parser();
        $garbage = 'not json at all';

        $result = $parser->parse($garbage, 'freetext');

        $this->assertSame($garbage, $result['narrative']);
        $this->assertFalse($result['completed']);
        $this->assertSame(0, $result['stepchange']);
    }

    /**
     * An out-of-range stepchange value is clamped to 0 rather than trusted verbatim.
     */
    public function test_parse_rejects_invalid_stepchange(): void {
        $parser = new response_parser();
        $json = json_encode(['narrative' => 'x', 'stepchange' => 99]);

        $result = $parser->parse($json, 'freetext');

        $this->assertSame(0, $result['stepchange']);
    }

    /**
     * When the AI returns fewer choices of a type than required, the choices array
     * is discarded entirely (left for the caller to retry or fall back), not
     * silently padded with invented placeholder choices.
     */
    public function test_parse_discards_choices_when_counts_insufficient(): void {
        $parser = new response_parser();
        $json = json_encode([
            'narrative' => 'x',
            'choices'   => [
                ['label' => 'Only good one', 'type' => 'good'],
            ],
        ]);

        $result = $parser->parse($json, 'multichoice', 1, 1, 1);

        $this->assertSame([], $result['choices']);
        $this->assertFalse($parser->choices_match_expected($result['choices'], 1, 1, 1));
    }

    /**
     * Extra choices of a type beyond what's needed are truncated, not rejected.
     */
    public function test_parse_truncates_excess_choices_of_one_type(): void {
        $parser = new response_parser();
        $json = json_encode([
            'narrative' => 'x',
            'choices'   => [
                ['label' => 'Good 1', 'type' => 'good'],
                ['label' => 'Good 2', 'type' => 'good'],
                ['label' => 'Neutral 1', 'type' => 'neutral'],
                ['label' => 'Bad 1', 'type' => 'bad'],
            ],
        ]);

        $result = $parser->parse($json, 'multichoice', 1, 1, 1);

        $this->assertCount(3, $result['choices']);
        $this->assertTrue($parser->choices_match_expected($result['choices'], 1, 1, 1));
    }

    /**
     * A choice set containing duplicate labels is discarded entirely: the label a
     * student submits must unambiguously identify a single offered choice, because
     * the server resolves the choice's type from the label alone.
     */
    public function test_parse_discards_choices_when_labels_are_ambiguous(): void {
        $parser = new response_parser();
        $json = json_encode([
            'narrative' => 'x',
            'choices'   => [
                ['label' => 'Open the door', 'type' => 'good'],
                ['label' => 'Open the door', 'type' => 'bad'],
                ['label' => 'Wait', 'type' => 'neutral'],
            ],
        ]);

        $result = $parser->parse($json, 'multichoice', 1, 1, 1);

        $this->assertSame([], $result['choices']);
        $this->assertFalse($parser->choices_match_expected($result['choices'], 1, 1, 1));
    }

    /**
     * Choices are only parsed for multichoice/combo modes; freetext mode ignores them.
     */
    public function test_parse_ignores_choices_in_freetext_mode(): void {
        $parser = new response_parser();
        $json = json_encode([
            'narrative' => 'x',
            'choices'   => [['label' => 'Should be ignored', 'type' => 'good']],
        ]);

        $result = $parser->parse($json, 'freetext');

        $this->assertSame([], $result['choices']);
    }

    /**
     * Choices with an unrecognised type are dropped rather than miscounted.
     */
    public function test_parse_drops_choices_with_invalid_type(): void {
        $parser = new response_parser();
        $json = json_encode([
            'narrative' => 'x',
            'choices'   => [
                ['label' => 'Weird', 'type' => 'excellent'],
                ['label' => 'Good', 'type' => 'good'],
            ],
        ]);

        $result = $parser->parse($json, 'multichoice', 1, 0, 0);

        $this->assertCount(1, $result['choices']);
        $this->assertSame('good', $result['choices'][0]['type']);
    }
}
