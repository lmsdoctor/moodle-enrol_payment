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
 * Listens for Instant Payment Notification from PayPal
 *
 * This script waits for Payment notification from PayPal,
 * then double checks that data by sending it back to PayPal.
 * If PayPal verifies this then it sets up the enrolment for that
 * user.
 *
 * @package    enrol_payment
 * @copyright  2020 LMS Doctor
 * @copyright  based on work by 2010 Eugene Venter (originally for enrol_paypal)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Disable moodle specific debug messages and any errors in output,
// comment out when debugging or better look into error log!
define('NO_DEBUG_DISPLAY', true);

// @codingStandardsIgnoreLine This script does not require login.
require(__DIR__ . '/../../config.php');
require_once("lib.php");
require_once($CFG->libdir.'/enrollib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->libdir . '/adminlib.php');

use enrol_payment\helper;
use enrol_payment\util;
use enrol_payment\paypalipn;

$ipn = new paypalipn();

// Set this to true to use the sandbox endpoint during testing:
$enablesandbox = get_config('enrol_payment', 'enablesandbox');

// Use the sandbox endpoint during testing.
if ($enablesandbox) {
    $ipn->usesandbox();
}

$verified = $ipn->verifyipn();

// PayPal does not like when we return error messages here,
// the custom handler just logs exceptions and stops.
// set_exception_handler(helper::get_exception_handler());

// Make sure we are enabled in the first place.
if (!enrol_is_enabled('payment')) {
    http_response_code(503);
    throw new moodle_exception('errdisabled', 'enrol_payment');
}

$txn                = new stdClass;
$txn->business      = optional_param('business', '', PARAM_TEXT);
$txn->custom        = optional_param('custom', '', PARAM_TEXT);
$txn->optionname1   = optional_param('option_name1', '',  PARAM_TEXT);
$txn->optionselection1x = optional_param('option_selection1', '',  PARAM_TEXT);
$txn->paymentstatus = required_param('payment_status', PARAM_TEXT);
$txn->paymenttype   = required_param('payment_type', PARAM_TEXT);
$txn->receiveremail = required_param('receiver_email', PARAM_TEXT);
$txn->receiverid    = required_param('receiver_id', PARAM_TEXT);
$txn->tax           = required_param('tax', PARAM_TEXT);
$txn->txnid         = required_param('txn_id', PARAM_TEXT);
$txn->payment_gross = optional_param('mc_gross', '', PARAM_TEXT);
$txn->mc_currency   = optional_param('mc_currency', '', PARAM_TEXT);

if (empty($txn->custom)) {
    // If the this is a mixed-use paypal, then die.
    if (get_config('paypalmixeduse', 'enrol_payment')) {
        // ... PayPal interprets the moodle_exception
        // with an HTTP response code 500 when anything else but a course is purchased.
        die();
    }
}

$payment = helper::get_payment_from_token($txn->custom);

if (empty($payment)) {
    throw new moodle_exception('invalidrequest', 'core_error', '', null, "Invalid value of prepay token: $txn->custom");
}

unset($txn->custom);

$txn->userid           = (int)$payment->userid;
$txn->courseid         = (int)$payment->courseid;
$txn->instanceid       = (int)$payment->instanceid;
$txn->timeupdated      = time();

$multiple = (bool)$payment->multiple;
if ($multiple) {
    $multipleuserids = explode(',', $payment->multipleuserids);
    if (empty($multipleuserids)) {
        throw new moodle_exception('invalidrequest', 'core_error', '', null,
            "Multiple purchase specified, but no userids found.");
    }
}

$user = $DB->get_record("user", array("id" => $txn->userid), "*", MUST_EXIST);
$course = $DB->get_record("course", array("id" => $txn->courseid), "*", MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);
$txn->itemname = $course->fullname;

$PAGE->set_context($context);

$plugininstance = $DB->get_record("enrol", array("id" => $txn->instanceid, "enrol" => "payment", "status" => 0));
$plugin = enrol_get_plugin('payment');

// If the connection is not OK, save the transaction and throw a moodle exception.
if (!$verified) {
    $DB->insert_record("enrol_payment_transaction", $txn, false);
    throw new moodle_exception('erripninvalid', 'enrol_payment', '', null, json_encode($txn));
}

// Check the payment_status and payment_reason
// If status is not completed or pending then unenrol the student if already enrolled and notify admin.
if ($txn->paymentstatus != "Completed" and $txn->paymentstatus != "Pending") {
    if ($multiple) {
        foreach ($multipleuserids as $muid) {
            $plugin->unenrol_user($plugininstance, $muid);
        }
    } else {
        $plugin->unenrol_user($plugininstance, $txn->userid);
    }
    helper::message_paypal_error_to_admin(get_string('notcompleted', 'enrol_payment'), $txn);
    die();
}

// If currency is incorrectly set then someone maybe trying to cheat the system.
if ($txn->mc_currency != $plugininstance->currency) {
    helper::message_paypal_error_to_admin(
        get_string('currencynotmatch', 'enrol_payment', $txn->mc_currency),
        $txn
    );
    die();
}

// If status is pending and reason is other than echeck then we are on hold until further notice
// Email user to let them know. Email admin.
if ($txn->paymentstatus == "Pending" and $txn->pendingreason != "echeck") {

    // Notify the user.
    $subject = 'The payment is pending';
    $messagehtml = '<p>Your PayPal payment is pending</p>';
    $messagetext = html_to_text($messagehtml);
    $fromuser = self::get_fromuser_object();
    email_to_user($user, $fromuser, $subject, $messagetext, $messagehtml, ", ", false);

    // Notify the admin.
    helper::message_paypal_error_to_admin($subject, $txn);

    $DB->insert_record("enrol_payment_transaction", $txn);
    $DB->update_record("enrol_payment_session", array("id" => $payment->id, "paypaltxnid" => $txn->txnid));
    die();
}

// If our status is not completed or not pending on an echeck clearance then ignore and die.
// This check is redundant at present but may be useful if paypal extend the return codes in the future.
if (!($txn->paymentstatus == "Completed" ||
       ($txn->paymentstatus == "Pending" && $txn->pendingreason == "echeck"))) {
    helper::message_paypal_error_to_admin($subject, $txn);
    die();
}

// At this point we only proceed with a status of completed or pending with a reason of echeck.
// Make sure this transaction doesn't exist already.
if ($existing = $DB->get_record("enrol_payment_transaction", array("txnid" => $txn->txnid), "*", IGNORE_MULTIPLE)) {
    helper::message_paypal_error_to_admin(get_string('txnrepeated', 'enrol_payment', $txn->txnid), $txn);
    die();
}

// Check that the receiver email is the one we want it to be.
if (isset($txn->business)) {
    $recipient = $txn->business;
} else if (isset($txn->receiveremail)) {
    $recipient = $txn->receiveremail;
} else {
    $recipient = 'empty';
}

if (strtolower($recipient) !== strtolower($plugin->get_config('paypalbusiness'))) {
    helper::message_paypal_error_to_admin(
        get_string('recipientnotmatch', 'enrol_payment', $recipient), $txn
    );
    die();
}

// Check that user exists.
if (!$user = $DB->get_record('user', array('id' => $txn->userid))) {
    helper::message_paypal_error_to_admin("User $txn->userid doesn't exist", $txn);
    die();
}

// Check if the course exists.
if (!$course = $DB->get_record('course', array('id' => $txn->courseid))) {
    helper::message_paypal_error_to_admin("Course $txn->courseid doesn't exist", $txn);
    die;
}

$coursecontext = context_course::instance($course->id, IGNORE_MISSING);

// Check that amount paid is the correct amount.
if ((float) $plugininstance->cost <= 0) {
    $originalcost = (float) $plugin->get_config('cost');
} else {
    $originalcost = (float) $plugininstance->cost;
}

// Use the same rounding of floats as on the enrol form.
$originalcost = format_float($originalcost, 2, false);

// What should the user have paid? Verify using info stored in the database.
$cost = helper::calculate_cost($plugininstance, $payment)["subtotal"];

if ($txn->payment_gross + 0.01 < $cost) {
    // This shouldn't happen unless the user spoofs their requests, but
    // if it does, the discount is just invalid.
    helper::message_paypal_error_to_admin("Amount paid is not enough ($txn->payment_gross < $cost))", $txn);
    die;
}

// All clear.
$DB->insert_record("enrol_payment_transaction", $txn);
$DB->update_record("enrol_payment_session", array("id" => $payment->id, "paypaltxnid" => $txn->txnid));

if ($plugininstance->enrolperiod) {
    $timestart = time();
    $timeend   = $timestart + $plugininstance->enrolperiod;
} else {
    $timestart = 0;
    $timeend   = 0;
}

if (!$multiple) {
    // Make a singleton array so that we can do this whole thing in a foreach loop.
    $multipleuserids = [$user->id];
}

$mailstudents = $plugin->get_config('mailstudents');
$mailteachers = $plugin->get_config('mailteachers');
$mailadmins   = $plugin->get_config('mailadmins');
$shortname    = $course->shortname;

// Pass $view = true to filter hidden caps if the user cannot see them.
if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                                     '', '', '', '', false, true)) {
    $users = sort_by_roleassignment_authority($users, $context);
    $teacher = array_shift($users);
} else {
    $teacher = false;
}

foreach ($multipleuserids as $uid) {
    // Check that user exists.
    if (!$user = $DB->get_record('user', array('id' => $uid))) {
        helper::message_paypal_error_to_admin("User $txn->userid doesn't exist", $txn);
        die();
    }

    // Enrol user.
    $plugin->enrol_user($plugininstance, $user->id, $plugininstance->roleid, $timestart, $timeend);

    if ($plugininstance->customint1 != ENROL_DO_NOT_SEND_EMAIL) {
        $plugin->email_welcome_message($plugininstance, $user);
    }

    // If group selection is not null.
    if ($plugininstance->customint2) {
        groups_add_member($plugininstance->customint2, $user);
    }

    if (!empty($mailstudents)) {
        $a = new stdClass();
        $a->coursename = $course->fullname;
        $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";

        $eventdata = new \core\message\message();
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'enrol_payment';
        $eventdata->name              = 'paypal_enrolment';
        $eventdata->userfrom          = empty($teacher) ? core_user::get_noreply_user() : $teacher;
        $eventdata->userto            = $user;
        $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
        $eventdata->fullmessage       = get_string('welcometocoursetext', '', $a);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);

    }

    if (!empty($mailteachers) && !empty($teacher)) {
        $a = new stdClass();
        $a->course = $course->fullname;
        $a->user = fullname($user);

        $eventdata = new \core\message\message();
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'enrol_payment';
        $eventdata->name              = 'payment_enrolment';
        $eventdata->userfrom          = $user;
        $eventdata->userto            = $teacher;
        $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
        $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);
    }

    if (!empty($mailadmins)) {
        $a->course = $course->fullname;
        $a->user = fullname($user);
        $admins = get_admins();
        foreach ($admins as $admin) {
            $eventdata = new \core\message\message();
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'enrol_payment';
            $eventdata->name              = 'payment_enrolment';
            $eventdata->userfrom          = $user;
            $eventdata->userto            = $admin;
            $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
            $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);
        }
    }
}

header('HTTP/1.1 200 OK');
