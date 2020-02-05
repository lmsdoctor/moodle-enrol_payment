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
 * Payment enrolment plugin.
 *
 * This plugin allows you to set up paid courses.
 *
 * @package    enrol_payment
 * @copyright  2018 Seth Yoder, LMS Doctor
 * @copyright  based on work by 2010 Eugene Venter (originally for enrol_paypal)
 * @author     Seth Yoder <seth.a.yoder@gmail.com> - based on code by Eugene Venter, Martin Dougiamas and others
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/classes/util.php');
require_once(dirname(__FILE__) . '/currencycodes.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

use enrol_payment\helper;
use enrol_payment\output\main;

/**
 * Payment enrolment plugin implementation.
 *
 * @copyright  2018 Seth Yoder
 * @copyright  based on work by 2010 Eugene Venter (originally for enrol_paypal)
 * @author     Seth Yoder - based on code by Eugene Venter, Martin Dougiamas and others
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_payment_plugin extends enrol_plugin {

    /**
     * Return currencies.
     *
     * See https://www.paypal.com/cgi-bin/webscr?cmd=p/sell/mc/mc_intro-outside,
     * 3-character ISO-4217: https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_currency_codes
     *
     * @return array
     */
    public function get_currencies() {
        $codes = [
            'AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'ILS', 'JPY',
            'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'RUB', 'SEK', 'SGD', 'THB', 'TRY', 'TWD', 'USD'
        ];
        $currencies = [];
        foreach ($codes as $c) {
            $currencies[$c] = new lang_string($c, 'core_currencies');
        }

        return $currencies;
    }

    /**
     * Returns optional enrolment information icons.
     *
     * This is used in course list for quick overview of enrolment options.
     *
     * We are not using single instance parameter because sometimes
     * we might want to prevent icon repetition when multiple instances
     * of one type exist. One instance may also produce several icons.
     *
     * @param array $instances all enrol instances of this type in one course
     * @return array of pix_icon
     */
    public function get_info_icons(array $instances) {
        $found = false;
        foreach ($instances as $instance) {
            if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
                continue;
            }
            if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
                continue;
            }
            $found = true;
            break;
        }
        if ($found) {
            return array(new pix_icon('icon', get_string('pluginname', 'enrol_payment'), 'enrol_payment'));
        }
        return array();
    }

    /**
     * Users with role assign cap may tweak the roles later.
     *
     * @return bool
     */
    public function roles_protected() {
        return false;
    }

    /**
     * Users with unenrol cap may unenrol other users manually - requires enrol/payment:unenrol.
     *
     * @param  stdClass $instance
     * @return bool
     */
    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    /**
     * Users with manage cap may tweak period and status - requires enrol/payment:manage.
     * @param  stdClass $instance [description]
     * @return bool
     */
    public function allow_manage(stdClass $instance) {
        return true;
    }

    /**
     * Show enrol me link.
     *
     * @param  stdClass $instance
     * @return bool
     */
    public function show_enrolme_link(stdClass $instance) {
        return ($instance->status == ENROL_INSTANCE_ENABLED);
    }

    /**
     * Returns true if the user can add a new instance in this course.
     *
     * @param int $courseid
     * @return bool
     */
    public function can_add_instance($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/payment:config', $context)) {
            return false;
        }

        // Multiple instances supported - different cost for different roles.
        return true;
    }

    /**
     * We are a good plugin and don't invent our own UI/validation code path.
     *
     * @return bool
     */
    public function use_standard_editing_ui() {
        return true;
    }

    /**
     * Add new instance of enrol plugin.
     *
     * @param stdClass $course
     * @param array $fields instance fields
     * @return int id of new instance, null if can not be created
     */
    public function add_instance($course, array $fields = null) {
        if ($fields && !empty($fields['cost'])) {
            $fields['cost'] = unformat_float($fields['cost']);
        }
        return parent::add_instance($course, $fields);
    }

    /**
     * Update instance of enrol plugin.
     *
     * @param stdClass $instance
     * @param stdClass $data modified instance fields
     * @return bool
     */
    public function update_instance($instance, $data) {
        if ($data) {
            $data->cost = unformat_float($data->cost);
        }
        return parent::update_instance($instance, $data);
    }

    /**
     * Get the "from" contact which the email will be sent from.
     *
     * @param int $sendoption send email from constant ENROL_SEND_EMAIL_FROM_*
     * @param $context context where the user will be fetched
     * @return mixed|stdClass the contact user object.
     */
    private function get_welcome_email_contact($sendoption, $context) {
        global $CFG;

        $contact = null;
        // Send as the first user assigned as the course contact.
        if ($sendoption == ENROL_SEND_EMAIL_FROM_COURSE_CONTACT) {
            $rusers = array();
            if (!empty($CFG->coursecontact)) {
                $croles = explode(',', $CFG->coursecontact);
                list($sort, $sortparams) = users_order_by_sql('u');
                // We only use the first user.
                $counter = 0;
                do {
                    $allnames = get_all_user_name_fields(true, 'u');
                    $rusers = get_role_users($croles[$counter], $context, true, 'u.id,  u.confirmed, u.username, '. $allnames . ',
                    u.email, r.sortorder, ra.id', 'r.sortorder, ra.id ASC, ' . $sort, null, '', '', '', '', $sortparams);
                    $counter++;
                } while (empty($rusers) && !empty($croles[$counter]));
            }
            if ($rusers) {
                $contact = array_values($rusers)[0];
            }
        } else if ($sendoption == ENROL_SEND_EMAIL_FROM_KEY_HOLDER) {
            // Send as the first user with enrol/self:holdkey capability assigned in the course.
            list($sort) = users_order_by_sql('u');
            $keyholders = get_users_by_capability($context, 'enrol/payment:holdkey', 'u.*', $sort);
            if (!empty($keyholders)) {
                $contact = array_values($keyholders)[0];
            }
        }

        // If send welcome email option is set to no reply or if none of the previous options have
        // returned a contact send welcome message as noreplyuser.
        if ($sendoption == ENROL_SEND_EMAIL_FROM_NOREPLY || empty($contact)) {
            $contact = core_user::get_noreply_user();
        }

        return $contact;
    }

    /**
     * Send welcome email to specified user.
     *
     * @param stdClass $instance
     * @param stdClass $user user record
     * @return void
     */
    public function email_welcome_message($instance, $user) {
        global $CFG, $DB;

        $course = $DB->get_record('course', array('id' => $instance->courseid));
        $context = context_course::instance($course->id);

        $a = new stdClass();
        $a->coursename = $course->fullname;
        $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id&course=$course->id";

        if (trim($instance->customtext1) !== '') {
            $message = $instance->customtext1;
            $key = array('{$a->coursename}', '{$a->profileurl}', '{$a->fullname}', '{$a->email}');
            $value = array($a->coursename, $a->profileurl, fullname($user), $user->email);
            $message = str_replace($key, $value, $message);
            if (strpos($message, '<') === false) {
                // Plain text only.
                $messagetext = $message;
                $messagehtml = text_to_html($messagetext, null, false, true);
            } else {
                // This is most probably the tag/newline soup known as FORMAT_MOODLE.
                $messagehtml = format_text($message, FORMAT_MOODLE,
                    ['context' => $context, 'para' => false, 'newlines' => true, 'filter' => true]
                );
                $messagetext = html_to_text($messagehtml);
            }
        } else {
            $messagetext = get_string('welcometocoursetext', 'enrol_self', $a);
            $messagehtml = text_to_html($messagetext, null, false, true);
        }

        $subject = get_string('welcometocourse', 'enrol_self', $course->fullname);

        $sendoption = $instance->customint1;
        $contact = $this->get_welcome_email_contact($sendoption, $context);

        // Directly emailing welcome message rather than using messaging.
        email_to_user($user, $contact, $subject, $messagetext, $messagehtml);
    }

    /**
     * Outputs transfer instructions.
     *
     * @param  int $cost
     * @param  string $coursefullname
     * @param  string $courseshortname
     * @return void
     */
    public function output_transfer_instructions($cost, $coursefullname, $courseshortname) {
        if ($this->get_config("allowbanktransfer")) {
            $instructions = $this->get_config("transferinstructions");
            $instructions = str_replace("{{AMOUNT}}", "<span id=\"banktransfer-cost\">$cost</span>", $instructions);
            $instructions = str_replace("{{COURSESHORTNAME}}", $courseshortname, $instructions);
            $instructions = str_replace("{{COURSEFULLNAME}}", $coursefullname, $instructions);
            echo '<span id="interac-text">';
            echo $instructions;
            echo '</span>';
        }
    }

    /**
     * Return the tax amount.
     *
     * @param  string $tax
     * @param  string $userfield
     *
     * @return array
     */
    private function get_tax_amount(string $tax, string $userfield) {
        $pieces = explode(":", $tax);
        if (count($pieces) != 2) {
            debugging('Incorrect tax definition format.');
        }

        $taxregion = strtolower(trim($pieces[0]));
        $taxrate = trim($pieces[1]);

        // If the user country and the tax country does not match, return with empty values.
        if ($taxregion != strtolower(trim($userfield))) {
            return [
                'taxpercent' => 0,
                'taxstring'  => ''
            ];
        }

        if (!is_numeric($taxrate)) {
            debugging('Encountered non-numeric tax value.');
        }

        try {
            $floattaxrate = floatval($taxrate);
            return [
                'taxpercent' => $floattaxrate,
                'taxstring'  => '(' . floor($floattaxrate * 100) . '% tax)'
            ];

        } catch (Exception $e) {
            debugging("Could not convert tax value for $province into a float.");
        }
    }

    /**
     * Returns the tax info.
     *
     * @param  int $cost
     * @return array
     */
    public function get_tax_info($cost) {
        global $USER;
        profile_load_data($USER);

        // If the option is disabled, return.
        $hastaxes = $this->get_config('definetaxes');
        if (!$hastaxes) {
            return ['taxpercent' => 0, 'taxstring' => ''];
        }

        $taxdefs        = $this->get_config('taxdefinitions');
        $countrytax     = $this->get_config('countrytax');
        $usertaxregion  = $USER->profile_field_taxregion;

        // If country tax are set and the user country is empty. Force user to edit his profile.
        if (!empty($countrytax) && empty($USER->country)) {
            $urltogo = new moodle_url('/user/edit.php', ['id' => $USER->id]);
            redirect($urltogo, 'You must choose your country', null, \core\output\notification::NOTIFY_WARNING);
        }

        if (!empty($countrytax)) {
            $taxholder = $this->get_tax_amount($countrytax, $USER->country);
            return $taxholder;
        }

        // If the tax country is not empty, use it. Otherwise use the tax region.
        $taxdefs = $this->get_config('taxdefinitions');
        $taxdeflines = explode("\n", $taxdefs);

        // Return if this is empty.
        if (empty($taxdefs)) {
            return ['taxpercent' => 0, 'taxstring' => ''];
        }

        foreach ($taxdeflines as $taxline) {
            $taxholder = $this->get_tax_amount($taxline, $USER->profile_field_taxregion);
        }

        return $taxholder;
    }

    /**
     * Creates course enrol form, checks if form submitted
     * and enrols user if necessary. It can also redirect.
     *
     * @param stdClass $instance
     * @return string html text, usually a form in a text box
     */
    public function enrol_page_hook(stdClass $instance) {
        global $PAGE;

        $config     = $this->get_instance_configuration($instance);
        $renderable = new main($instance, $config);
        $renderer   = $PAGE->get_renderer('enrol_payment');
        return $renderer->render($renderable);

    }

    protected function get_instance_configuration(stdClass $instance) {
        $config                 = new stdClass;
        $config->taxinfo        = $this->get_tax_info($instance->cost, $instance->courseid);
        $config->allowmultiple  = ($this->get_config('allowmultipleenrol') && $instance->customint5);
        $config->haspaypal      = (bool) trim($this->get_config('paypalbusiness'));
        $config->paypalaccount  = $this->get_config('paypalbusiness');

        $stripesecret           = $this->get_config('stripesecretkey');
        $config->stripekey      = $this->get_config('stripepublishablekey');
        $config->hasstripe      = ((bool) trim($stripesecret)) && ((bool) trim($config->stripekey));

        return $config;
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB;
        if ($step->get_task()->get_target() == backup::TARGET_NEW_COURSE) {
            $merge = false;
        } else {
            $merge = array(
                'courseid'   => $data->courseid,
                'enrol'      => $this->get_name(),
                'roleid'     => $data->roleid,
                'cost'       => $data->cost,
                'currency'   => $data->currency,
            );
        }
        if ($merge and $instances = $DB->get_records('enrol', $merge, 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } else {
            $instanceid = $this->add_instance($course, (array)$data);
        }
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    /**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $oldinstancestatus
     * @param int $userid
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
    }

    /**
     * Return an array of valid options for the status.
     *
     * @return array
     */
    protected function get_status_options() {
        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no'));
        return $options;
    }

    /**
     * Return an array of valid options for the roleid.
     *
     * @param stdClass $instance
     * @param context $context
     * @return array
     */
    protected function get_roleid_options($instance, $context) {
        if ($instance->id) {
            $roles = get_default_enrol_roles($context, $instance->roleid);
        } else {
            $roles = get_default_enrol_roles($context, $this->get_config('roleid'));
        }
        return $roles;
    }

    /**
     * Add elements to the edit instance form.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $context
     * @return bool
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {

        // Add "float2" element for float formatting.
        require_once('HTML/QuickForm.php');
        MoodleQuickForm::registerElementType('float2', dirname(__FILE__) . '/classes/float2.php',
            "MoodleQuickForm_float2");

        // Custom fields:
        // customint1 - Send course welcome message (bool)
        // customint2 - Enrol user into a group (Group id)
        // customint3 - Discount type (0: No discount, 1: Percentage discount, 2: Value discount)
        // customint4 - require shipping info at checkout (bool)
        // customint5 - allow multiple enrollments (bool)
        // customint6 - Enable custom tax calculation based on province in "msn" field (bool)
        // customint7 - Discount code required (bool)
        // customtext1 - Custom welcome message
        // customtext2 - Discount code
        // customdec1 - Discount amount.

        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $options = $this->get_status_options();
        $mform->addElement('select', 'status', get_string('status', 'enrol_payment'), $options);
        $mform->setDefault('status', $this->get_config('status'));

        $mform->addElement('text', 'cost', get_string('cost', 'enrol_payment'), array('size' => 4));
        $mform->setType('cost', PARAM_RAW);
        $mform->setDefault('cost', format_float($this->get_config('cost'), 2, true));

        $paypalcurrencies = $this->get_currencies();
        $mform->addElement('select', 'currency', get_string('currency', 'enrol_payment'), $paypalcurrencies);
        $mform->setDefault('currency', $this->get_config('currency'));

        $roles = $this->get_roleid_options($instance, $context);
        $mform->addElement('select', 'roleid', get_string('assignrole', 'enrol_payment'), $roles);
        $mform->setDefault('roleid', $this->get_config('roleid'));

        $options = array('optional' => true, 'defaultunit' => 86400);
        $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'enrol_payment'), $options);
        $mform->setDefault('enrolperiod', $this->get_config('enrolperiod'));
        $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_payment');

        $options = array('optional' => true);
        $mform->addElement('date_time_selector', 'enrolstartdate', get_string('enrolstartdate', 'enrol_payment'), $options);
        $mform->setDefault('enrolstartdate', 0);
        $mform->addHelpButton('enrolstartdate', 'enrolstartdate', 'enrol_payment');

        $options = array('optional' => true);
        $mform->addElement('date_time_selector', 'enrolenddate', get_string('enrolenddate', 'enrol_payment'), $options);
        $mform->setDefault('enrolenddate', 0);
        $mform->addHelpButton('enrolenddate', 'enrolenddate', 'enrol_payment');

        $mform->addElement('select', 'customint1',
                           get_string('sendcoursewelcomemessage', 'enrol_payment'),
                           enrol_send_welcome_email_options());
        $mform->setDefault('customint1', $this->get_config('sendcoursewelcomemessage'));
        $mform->addHelpButton('customint1', 'sendcoursewelcomemessage', 'enrol_payment');

        $options = array('cols' => '60', 'rows' => '8');
        $mform->addElement('textarea', 'customtext1', get_string('customwelcomemessage', 'enrol_payment'), $options);
        $mform->setDefault('customtext1', $this->get_config('defaultcoursewelcomemessage'));
        $mform->addHelpButton('customtext1', 'customwelcomemessage', 'enrol_payment');

        $groups = groups_get_all_groups($instance->courseid);
        $options = array();
        $options[0] = get_string('enrolnogroup', 'enrol_payment');
        foreach ($groups as $group) {
            $options[$group->id] = $group->name;
        }
        $mform->addElement('select', 'customint2', get_string('enrolgroup', 'enrol_payment'), $options);

        if ($this->get_config('enablediscounts')) {
            // Discount type radio buttons.
            $radioarray = array();
            $radioarray[] = $mform->createElement('radio', 'customint3', '', get_string('nodiscount', 'enrol_payment'), 0);
            $radioarray[] = $mform->createElement('radio', 'customint3', '', get_string('percentdiscount', 'enrol_payment'), 1);
            $radioarray[] = $mform->createElement('radio', 'customint3', '', get_string('valuediscount', 'enrol_payment'), 2);
            $mform->addGroup($radioarray, 'customint3', get_string('discounttype', 'enrol_payment'), array(' '), false);
            $mform->addHelpButton('customint3', 'discounttype', 'enrol_payment');

            // Discount amount.
            $attributes = ['size' => 10];
            $mform->addElement('float2', 'customdec1', get_string('discountamount', 'enrol_payment'), $attributes);
            $mform->setType('customdec1', PARAM_RAW);
            $mform->disabledIf('customdec1', 'customint3', 'eq', 0);
            $mform->addHelpButton('customdec1', 'discountamount', 'enrol_payment');

            $mform->addElement('advcheckbox', 'customint7', get_string('requirediscountcode', 'enrol_payment'));
            $mform->addHelpButton('customint7', 'requirediscountcode', 'enrol_payment');
            $mform->setType('customint7', PARAM_INT);
            $mform->setDefault('customint7', 0);

            // Discount code - text.
            $mform->addElement('text', 'customtext2', get_string('discountcode', 'enrol_payment'));
            $mform->setType('customtext2', PARAM_TEXT);
            $mform->setDefault('customtext2', '');
            $mform->disabledIf('customtext2', 'customint3', 'eq', 0);
            $mform->disabledIf('customtext2', 'customint7', 'eq', 0);

            // Discount threshold - customint8.
            $mform->addElement('text', 'customint8', get_string('discountthreshold', 'enrol_payment'));
            $mform->setType('customint8', PARAM_INT);
            $mform->setDefault('customint8', 1);
            $mform->disabledIf('customint8', 'customint3', 'eq', 0);
            $mform->addHelpButton('customint8', 'discountthreshold', 'enrol_payment');
        }

        $mform->addElement('advcheckbox', 'customint4', get_string('requireshipping', 'enrol_payment'));
        $mform->setType('customint4', PARAM_INT);

        if ($this->get_config('allowmultipleenrol')) {
            $mform->addElement('advcheckbox', 'customint5', get_string('allowmultipleenrol', 'enrol_payment'));
            $mform->setType('customint5', PARAM_INT);
        }

        if (enrol_accessing_via_instance($instance)) {
            $warningtext = get_string('instanceeditselfwarningtext', 'core_enrol');
            $mform->addElement('static', 'selfwarn', get_string('instanceeditselfwarning', 'core_enrol'), $warningtext);
        }
    }

    /**
     * Perform custom validation of the data used to edit the instance.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @param object $instance The instance loaded from the DB
     * @param context $context The context of the instance we are editing
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK.
     * @return void
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = array();

        if (!empty($data['enrolenddate']) and $data['enrolenddate'] < $data['enrolstartdate']) {
            $errors['enrolenddate'] = get_string('enrolenddaterror', 'enrol_payment');
        }

        $cost = str_replace(get_string('decsep', 'langconfig'), '.', $data['cost']);
        if (!is_numeric($cost)) {
            $errors['cost'] = get_string('costerror', 'enrol_payment');
        }

        if (array_key_exists("customdec1", $data)) {
            if (!empty($data['customdec1'])) {
                $discountamount = str_replace(get_string('decsep', 'langconfig'), '.', $data['customdec1']);
                if (!is_numeric($discountamount)) {
                    $errors['customdec1'] = get_string('discountamounterror', 'enrol_payment');
                }

                // Display error if the value is negative.
                if ($data['customdec1'] < 0) {
                    $errors['customdec1'] = get_string('negativediscounterror', 'enrol_payment');
                }

                $totaldigits = strlen(str_replace('.', '', $discountamount));
                $digitsafterdecimal = strlen(substr(strrchr($discountamount, "."), 1));
                if ($totaldigits > 12 || $digitsafterdecimal > 7) {
                    $errors['customdec1'] = get_string('discountdigitserror', 'enrol_payment');
                }

                // If discount amount is filled, discount code must be filled.
                if ((!array_key_exists("customtext2", $data)) || empty($data['customtext2'])) {
                    // Ensure that "Require discount code" is checked before showing the error.
                    if (array_key_exists("customint7", $data) && ($data["customint7"] != 0)) {
                        // Ensure that the discount type is not "No discount".
                        if (array_key_exists("customint3", $data) && ($data["customint3"] != 0)) {
                            $errors['customtext2'] = get_string('needdiscountcode', 'enrol_payment');
                        }
                    }
                }
            }
        }

        // If discount code is filled, discount amount must be filled.
        if (array_key_exists("customtext2", $data) && (!empty($data['customtext2']))) {
            if ((!array_key_exists("customdec1", $data)) || empty($data['customdec1'])) {
                if (array_key_exists("customint3", $data) && ($data["customint3"] != 0)) {
                    $errors['customdec1'] = get_string('needdiscountamount', 'enrol_payment');
                }
            }
        }

        if (array_key_exists("customint8", $data)) {
            // Ensure Discount Threshold is greater than 0.
            if ($data['customint8'] < 1) {
                $errors['customint8'] = get_string('discountthresholdtoolow', 'enrol_payment');
            } else if ($data['customint8'] > 1) {
                // Ensure Multiple Enrolment is enabled if discount threshold is greater than 1.
                if (array_key_exists("customint5", $data)) {
                    if (!$data['customint5']) {
                        $errors['customint8'] = get_string('discountthresholdbutnomultipleenrol', 'enrol_payment');
                    }
                }

                if (!$this->get_config('allowmultipleenrol')) {
                    $errors['customint8'] = get_string('discountthresholdbutnomultipleenrol', 'enrol_payment');
                }
            }
        }

        $validstatus = array_keys($this->get_status_options());
        $validcurrency = array_keys($this->get_currencies());
        $validroles = array_keys($this->get_roleid_options($instance, $context));
        $tovalidate = array(
            'name' => PARAM_TEXT,
            'status' => $validstatus,
            'currency' => $validcurrency,
            'roleid' => $validroles,
            'enrolperiod' => PARAM_INT,
            'enrolstartdate' => PARAM_INT,
            'enrolenddate' => PARAM_INT
        );

        $typeerrors = $this->validate_param_types($data, $tovalidate);

        $errors = array_merge($errors, $typeerrors);

        return $errors;
    }

    /**
     * Execute synchronisation.
     * @param progress_trace $trace
     * @return int exit code, 0 means ok
     */
    public function sync(progress_trace $trace) {
        $this->process_expirations($trace);
        return 0;
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/payment:config', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/payment:config', $context);
    }

}

/**
 * Serve the files from the enrol_payment file areas.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function enrol_payment_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'stripelogo') {
        return false;
    }

    // Make sure the user is logged in and has access to the module
    // (plugins that are not course modules should leave out the 'cm' part).
    require_login($course, true, $cm);

    // Extract the filename / filepath from the $args array.
    $filename = array_pop($args); // The last item in the $args array.

    $filepath = '/';

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'enrol_payment', $filearea, 0, $filepath, $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // We can now send the file back to the browser - in this case with a cache lifetime of 1 day and no filtering.
    // From Moodle 2.3, use send_stored_file instead.
    send_file($file, 86400, 0, $forcedownload, $options);
}
