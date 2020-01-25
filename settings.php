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
 * Paypal enrolments plugin settings and presets.
 *
 * @package    enrol_payment
 * @copyright  2020 LMS Doctor
 * @author     Andrés Ramos <andres.ramos@lmsdoctor.com>
 * @author     Seth Yoder <seth.a.yoder@gmail.com> - based on code by Eugene Venter, Petr Skoda, and others
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once(dirname(__FILE__) . '/classes/util.php');
$PAGE->requires->js_call_amd('enrol_payment/settings', 'init');

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_heading(
        'enrol_payment_settings',
        '',
        get_string('pluginname_desc', 'enrol_payment')
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_payment/paypalbusiness',
        get_string('businessemail', 'enrol_payment'),
        get_string('businessemail_desc', 'enrol_payment'),
        '',
        PARAM_EMAIL
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_payment/paypalmixeduse',
        get_string('paypalmixeduse', 'enrol_payment'),
        get_string('paypalmixeduse_desc', 'enrol_payment'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_payment/stripesecretkey',
        get_string('stripesecretkey', 'enrol_payment'),
        get_string('stripesecretkey_desc', 'enrol_payment'),
        '',
        0
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_payment/stripepublishablekey',
        get_string('stripepublishablekey', 'enrol_payment'),
        get_string('stripepublishablekey_desc', 'enrol_payment'),
        '',
        0
    ));

    $settings->add(new admin_setting_configstoredfile(
        'enrol_payment/stripelogo',
        get_string('stripelogo', 'enrol_payment'),
        get_string('stripelogo_desc', 'enrol_payment'),
        'stripelogo'
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_payment/mailstudents',
        get_string('mailstudents', 'enrol_payment'),
        '',
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_payment/mailteachers',
        get_string('mailteachers', 'enrol_payment'),
        '',
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_payment/mailadmins', get_string('mailadmins', 'enrol_payment'), '', 0
    ));

    // Note: let's reuse the ext sync constants and strings here, internally it is very similar,
    // it describes what should happen when users are not supposed to be enrolled any more.
    $options = [
        ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
    ];

    $settings->add(new admin_setting_configselect(
        'enrol_payment/expiredaction',
        get_string('expiredaction', 'enrol_payment'),
        get_string('expiredaction_help', 'enrol_payment'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES, $options
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_payment/allowmultipleenrol',
        get_string('allowmultipleenrol', 'enrol_payment'),
        get_string('allowmultipleenrol_help', 'enrol_payment'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_payment/enablediscounts',
        get_string('allowdiscounts', 'enrol_payment'),
        get_string('allowdiscounts_help', 'enrol_payment'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox('enrol_payment/validatezipcode',
            get_string('validatezipcode', 'enrol_payment'),
            get_string('validatezipcode_desc', 'enrol_payment'), 0));
    $settings->add(new admin_setting_configcheckbox('enrol_payment/billingaddress',
            get_string('billingaddress', 'enrol_payment'),
            get_string('billingaddress_desc', 'enrol_payment'), 0));

    // Enrol instance defaults.
    $settings->add(new admin_setting_heading('enrol_payment_defaults',
        get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

    $options = [
        ENROL_INSTANCE_ENABLED  => get_string('yes'),
        ENROL_INSTANCE_DISABLED => get_string('no')
    ];

    $settings->add(new admin_setting_configselect(
        'enrol_payment/status',
        get_string('status', 'enrol_payment'),
        get_string('status_desc', 'enrol_payment'),
        ENROL_INSTANCE_ENABLED,
        $options
    ));

    $settings->add(new admin_setting_configtext(
        'enrol_payment/cost',
        get_string('cost', 'enrol_payment'),
        get_string('cost_desc', 'enrol_payment'),
        0,
        PARAM_FLOAT,
        4
    ));

    $paymentcurrencies = enrol_get_plugin('payment')->get_currencies();
    $settings->add(new admin_setting_configselect(
        'enrol_payment/currency',
        get_string('currency', 'enrol_payment'),
        '',
        'USD',
        $paymentcurrencies
    ));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect(
            'enrol_payment/roleid',
            get_string('defaultrole', 'enrol_payment'),
            get_string('defaultrole_desc', 'enrol_payment'),
            $student->id,
            $options
        ));
    }

    $settings->add(new admin_setting_configduration(
        'enrol_payment/enrolperiod',
        get_string('enrolperiod', 'enrol_payment'),
        get_string('enrolperiod_desc', 'enrol_payment'),
        0
    ));

    $settings->add(new admin_setting_configselect(
        'enrol_payment/sendcoursewelcomemessage',
            get_string('sendcoursewelcomemessage', 'enrol_payment'),
            get_string('sendcoursewelcomemessage_help', 'enrol_payment'),
            ENROL_SEND_EMAIL_FROM_COURSE_CONTACT,
            enrol_send_welcome_email_options()));

    $settings->add(new admin_setting_configtextarea(
        'enrol_payment/defaultcoursewelcomemessage',
        get_string('defaultcoursewelcomemessage', 'enrol_payment'),
        null,
        null));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_payment/definetaxes',
        get_string('definetaxes', 'enrol_payment'),
        get_string('definetaxes_desc', 'enrol_payment'),
        0));

    $settings->add(new admin_setting_configtext(
        'enrol_payment/countrytax',
        get_string('countrytax', 'enrol_payment'),
        get_string('countrytax_desc', 'enrol_payment'),
        '',
        PARAM_NOTAGS,
    ));

    $settings->add(new admin_setting_configtextarea(
        'enrol_payment/taxdefinitions',
        get_string('taxdefinitions', 'enrol_payment'),
        get_string('taxdefinitions_help', 'enrol_payment'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_payment/allowbanktransfer',
        get_string('allowbanktransfer', 'enrol_payment'),
        '',
        0));

    $settings->add(new \enrol_payment\admin_setting_confightmleditor_nodefaultinfo(
        'enrol_payment/transferinstructions',
        get_string('transferinstructions', 'enrol_payment'),
        get_string('transferinstructions_help', 'enrol_payment'),
        get_string('transferinstructions_default', 'enrol_payment')
    ));
}
