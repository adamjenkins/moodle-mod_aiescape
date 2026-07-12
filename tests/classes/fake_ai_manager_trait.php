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

namespace mod_aiescape;

/**
 * Shared test helper: a fake \core_ai\manager for DI injection.
 *
 * @package    mod_aiescape
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait fake_ai_manager_trait {
    /**
     * Builds a fake \core_ai\manager that returns the given generatedcontent strings
     * in order on successive process_action() calls, and counts how many were made.
     *
     * @param string[] $responses Raw AI text to return, one per expected call
     * @param int      $callcount Receives the number of process_action() calls made
     * @return \core_ai\manager
     */
    private function fake_ai_manager(array $responses, int &$callcount): \core_ai\manager {
        $callcount = 0;
        return new class ($responses, $callcount) extends \core_ai\manager {
            /** @var string[] */
            private array $responses;
            /** @var int */
            private int $calls = 0;
            /** @var int Running count of process_action() calls, by reference to the caller's variable */
            private int $callcountref;

            /**
             * Constructor.
             *
             * @param string[] $responses
             * @param int      $callcount Receives the running call count by reference
             */
            public function __construct(array $responses, int &$callcount) {
                $this->responses = $responses;
                $this->callcountref = &$callcount;
            }

            #[\Override]
            public function process_action(\core_ai\aiactions\base $action): \core_ai\aiactions\responses\response_base {
                $text = $this->responses[$this->calls] ?? end($this->responses);
                $this->calls++;
                $this->callcountref = $this->calls;
                $response = new \core_ai\aiactions\responses\response_generate_text(success: true);
                $response->set_response_data(['generatedcontent' => $text]);
                return $response;
            }
        };
    }

    /**
     * Builds a fake \core_ai\manager whose process_action() always reports failure,
     * mimicking an unreachable/erroring AI provider (which makes run_ai_turn throw
     * error:aifailed).
     *
     * The failure is expressed by overriding get_success() rather than by passing
     * the response's error constructor arguments, whose names/order differ between
     * Moodle 5.0 and later — run_ai_turn only inspects get_success() on this path.
     *
     * @return \core_ai\manager
     */
    private function fake_failing_ai_manager(): \core_ai\manager {
        return new class extends \core_ai\manager {
            /**
             * Constructor (bypasses the parent's dependencies; the fake needs none).
             */
            public function __construct() {
            }

            #[\Override]
            public function process_action(\core_ai\aiactions\base $action): \core_ai\aiactions\responses\response_base {
                return new class extends \core_ai\aiactions\responses\response_generate_text {
                    /**
                     * Constructor. Builds a nominally-successful response (positional
                     * success only, for cross-version compatibility); get_success()
                     * is overridden below to report failure.
                     */
                    public function __construct() {
                        parent::__construct(true);
                    }

                    #[\Override]
                    public function get_success(): bool {
                        return false;
                    }
                };
            }
        };
    }
}
