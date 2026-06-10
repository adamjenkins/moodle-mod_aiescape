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

namespace mod_aiescape\admin;

/**
 * Read-only admin setting that displays the active AI provider configuration.
 *
 * @package    mod_aiescape
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_info_setting extends \admin_setting {
    /**
     * Constructor.
     */
    public function __construct() {
        $this->nosave = true;
        parent::__construct('mod_aiescape/aiinfodisplay', '', '', '');
    }

    /**
     * Returns the setting value (always true; this setting is read-only and stores nothing).
     *
     * @return true
     */
    public function get_setting() {
        return true;
    }

    /**
     * Performs no write operation; this setting displays info only.
     *
     * @param mixed $data Ignored.
     * @return string Empty string.
     */
    public function write_setting($data) {
        return '';
    }

    /**
     * Renders the AI provider info table.
     *
     * @param mixed  $data  Ignored.
     * @param string $query Admin search query for highlighting.
     * @return string HTML
     */
    public function output_html($data, $query = '') {
        global $DB, $OUTPUT;

        $providers = $DB->get_records('ai_providers', ['enabled' => 1], 'id ASC');

        if (empty($providers)) {
            $html = $OUTPUT->notification(
                get_string('aiinfo_noproviders', 'mod_aiescape'),
                \core\output\notification::NOTIFY_WARNING
            );
            return \html_writer::div($html, 'form-item row mb-3');
        }

        $rows = [];
        foreach ($providers as $provider) {
            $config       = json_decode($provider->config ?? '', true) ?? [];
            $actionconfig = json_decode($provider->actionconfig ?? '', true) ?? [];

            // Provider type from class name, e.g. "aiprovider_ollama\provider" → "Ollama".
            $providertype = '';
            if (preg_match('/^aiprovider_([^\\\\_]+)/i', $provider->provider, $m)) {
                $providertype = ucfirst($m[1]);
            }

            $model = '';
            $key   = 'core_ai\\aiactions\\generate_text';
            if (isset($actionconfig[$key]['settings']['model'])) {
                $model = $actionconfig[$key]['settings']['model'];
            }

            $endpoint = $config['endpoint'] ?? '';

            $rows[] = \html_writer::tag(
                'tr',
                \html_writer::tag('td', s($provider->name)) .
                \html_writer::tag('td', s($providertype)) .
                \html_writer::tag('td', \html_writer::tag('code', s($model))) .
                \html_writer::tag('td', s($endpoint))
            );
        }

        $thead = \html_writer::tag(
            'tr',
            \html_writer::tag('th', get_string('name')) .
            \html_writer::tag('th', get_string('aiinfo_provider', 'mod_aiescape')) .
            \html_writer::tag('th', get_string('aiinfo_model', 'mod_aiescape')) .
            \html_writer::tag('th', get_string('aiinfo_endpoint', 'mod_aiescape'))
        );

        $table = \html_writer::tag(
            'table',
            \html_writer::tag('thead', $thead) .
            \html_writer::tag('tbody', implode('', $rows)),
            ['class' => 'generaltable table-sm w-auto mb-0']
        );

        return \html_writer::div(
            \html_writer::div($table, 'form-setting'),
            'form-item row mb-3'
        );
    }
}
