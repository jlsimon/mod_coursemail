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

/**
 * Instance settings form for mod_coursemail.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * The instance settings form.
 */
class mod_coursemail_mod_form extends moodleform_mod {
    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        // General section.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // Coursemail-specific settings.
        $mform->addElement('header', 'coursemailsettings', get_string('settings', 'coursemail'));

        $mform->addElement('advcheckbox', 'requireresponsedefault', get_string('requireresponsedefault', 'coursemail'));
        $mform->addHelpButton('requireresponsedefault', 'requireresponsedefault', 'coursemail');
        $mform->setDefault('requireresponsedefault', 0);

        $mform->addElement('advcheckbox', 'requiremanualcompletedefault', get_string('requiremanualcompletedefault', 'coursemail'));
        $mform->addHelpButton('requiremanualcompletedefault', 'requiremanualcompletedefault', 'coursemail');
        $mform->setDefault('requiremanualcompletedefault', 0);

        $mform->addElement('advcheckbox', 'completionrequireread', get_string('completionrequireread', 'coursemail'));
        $mform->addHelpButton('completionrequireread', 'completionrequireread', 'coursemail');
        $mform->setDefault('completionrequireread', 1);

        // Standard course module elements (visibility, groups, completion, etc.).
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Adds the custom completion rules for this module.
     *
     * @return array Names of the added form elements.
     */
    public function add_completion_rules() {
        $mform = $this->_form;

        $mform->addElement('checkbox', 'completionmail', '', get_string('completionmail', 'coursemail'));
        $mform->addHelpButton('completionmail', 'completionmail', 'coursemail');

        return ['completionmail'];
    }

    /**
     * Reports whether any custom completion rule is enabled.
     *
     * @param array $data Submitted form data.
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionmail']);
    }

    /**
     * Preprocesses form data before display when editing an existing instance.
     *
     * @param array $defaultvalues Reference to the default values.
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        // The completion checkbox is only ticked when its stored flag is set.
        $defaultvalues['completionmail'] = !empty($defaultvalues['completionmail']) ? 1 : 0;
    }
}
