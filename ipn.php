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
 * @copyright  2018 Seth Yoder
 * @copyright  based on work by 2010 Eugene Venter (originally for enrol_paypal)
 * @author     Seth Yoder <seth.a.yoder@gmail.com> - based on code by others
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

use enrol_payment\helper;

// PayPal does not like when we return error messages here,
// the custom handler just logs exceptions and stops.
set_exception_handler(\enrol_payment\util::get_exception_handler());

// Make sure we are enabled in the first place.
if (!enrol_is_enabled('payment')) {
    http_response_code(503);
    throw new moodle_exception('errdisabled', 'enrol_payment');
}

// Keep out casual intruders
if (empty($_POST) or !empty($_GET)) {
    http_response_code(400);
    echo get_string('invalidrequest', 'core_error');
}

// Read all the data from PayPal and get it ready for later;
// we expect only valid UTF-8 encoding, it is the responsibility
// of user to set it up properly in PayPal business account,
// it is documented in docs wiki.

$req = 'cmd=_notify-validate';

$data = new stdClass();

foreach ($_POST as $key => $value) {
    if ($key !== clean_param($key, PARAM_ALPHANUMEXT)) {
        throw new moodle_exception('invalidrequest', 'core_error', '', null, $key);
    }
    if (is_array($value)) {
        throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Unexpected array param: '.$key);
    }
    $req .= "&$key=".urlencode($value);
    $data->$key = fix_utf8($value);
}

if (empty($data->custom)) {
    // ... throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Missing request param: custom');
    // The line above has been commented out because PayPal interprets the moodle_exception
    // with an HTTP response code 500 when anything else but a course is purchased.
    die();
}

$payment = helper::get_payment_from_token($data->custom);

if (empty($payment)) {
    throw new moodle_exception('invalidrequest', 'core_error', '', null, "Invalid value of prepay token: $data->custom");
}

unset($data->custom);

$data->userid           = (int)$payment->userid;
$data->courseid         = (int)$payment->courseid;
$data->instanceid       = (int)$payment->instanceid;
$data->payment_gross    = $data->mc_gross;
$data->payment_currency = $data->mc_currency;
$data->timeupdated      = time();

$multiple         = (bool)$payment->multiple;
if ($multiple) {
    $multiple_userids = explode(',',$payment->multiple_userids);
    if(empty($multiple_userids)) {
        throw new moodle_exception('invalidrequest', 'core_error', '', null, "Multiple purchase specified, but no userids found.");
    }
}

$user = $DB->get_record("user", array("id" => $data->userid), "*", MUST_EXIST);
$course = $DB->get_record("course", array("id" => $data->courseid), "*", MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

$PAGE->set_context($context);

$plugininstance = $DB->get_record("enrol", array("id" => $data->instanceid, "enrol" => "payment", "status" => 0), "*", MUST_EXIST);
$plugin = enrol_get_plugin('payment');

// Open a connection back to PayPal to validate the data
$paypaladdr = empty($CFG->usepaypalsandbox) ? 'ipnpb.paypal.com' : 'ipnpb.sandbox.paypal.com';
$c = new curl();
$options = array(
    'returntransfer' => true,
    'httpheader' => array('application/x-www-form-urlencoded', "Host: $paypaladdr"),
    'timeout' => 30,
    'CURLOPT_HTTP_VERSION' => CURL_HTTP_VERSION_1_1,
);
$location = "https://$paypaladdr/cgi-bin/webscr";
$result = $c->post($location, $req, $options);

if ($c->get_errno()) {
    throw new moodle_exception('errpaypalconnect', 'enrol_payment', '', array('url' => $paypaladdr, 'result' => $result),
        json_encode($data));
}

// Connection is OK, so now we post the data to validate it.
// Now read the response and check if everything is OK.
if (strlen($result) > 0) {
    if (strcmp($result, "VERIFIED") == 0) {

        // Check the paymentstatus and payment_reason
        // If status is not completed or pending then unenrol the student if already enrolled and notify admin
        if ($data->paymentstatus != "Completed" and $data->paymentstatus != "Pending") {
            if($multiple) {
                foreach($multiple_userids as $muid) {
                    $plugin->unenrol_user($plugininstance, $muid);
                }
            } else {
                $plugin->unenrol_user($plugininstance, $data->userid);
            }
            \enrol_payment\util::message_paypal_error_to_admin("Status not completed or pending. User unenrolled from course",
                                                              $data);
            die;
        }

        // If currency is incorrectly set then someone maybe trying to cheat the system.
        if ($data->mc_currency != $plugininstance->currency) {
            \enrol_payment\util::message_paypal_error_to_admin(
                "Currency does not match course settings, received: ".$data->mc_currency,
                $data);
            die;
        }

        // If status is pending and reason is other than echeck then we are on hold until further notice
        // Email user to let them know. Email admin.
        if ($data->paymentstatus == "Pending" and $data->pendingreason != "echeck") {
            $eventdata->courseid          = empty($data->courseid) ? SITEID : $data->courseid;
            $eventdata = new \core\message\message();
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'enrol_payment';
            $eventdata->name              = 'payment_enrolment';
            $eventdata->userfrom          = get_admin();
            $eventdata->userto            = $user;
            $eventdata->subject           = "E-Learning Payment pending";
            $eventdata->fullmessage       = "Your PayPal payment is pending.";
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);

            \enrol_payment\util::message_paypal_error_to_admin("Payment pending", $data);

            $DB->insert_record("enrol_payment_transaction", $data);
            $DB->update_record("enrol_payment_session", array("id" => $payment->id, "paypaltxnid" => $data->txnid));

            die;
        }

        // If our status is not completed or not pending on an echeck clearance then ignore and die.
        // This check is redundant at present but may be useful if paypal extend the return codes in the future.
        if (! ( $data->paymentstatus == "Completed" or
               ($data->paymentstatus == "Pending" and $data->pendingreason == "echeck") ) ) {
            die;
        }

        // At this point we only proceed with a status of completed or pending with a reason of echeck.
        // Make sure this transaction doesn't exist already.
        if ($existing = $DB->get_record("enrol_payment_transaction", array("txnid" => $data->txnid), "*", IGNORE_MULTIPLE)) {
            \enrol_payment\util::message_paypal_error_to_admin("Transaction $data->txnid is being repeated!", $data);
            die;
        }

        // Check that the receiver email is the one we want it to be.
        if (isset($data->business)) {
            $recipient = $data->business;
        } else if (isset($data->receiver_email)) {
            $recipient = $data->receiver_email;
        } else {
            $recipient = 'empty';
        }

        if (core_text::strtolower($recipient) !== core_text::strtolower($plugin->get_config('paypalbusiness'))) {
            \enrol_payment\util::message_paypal_error_to_admin("Business email is {$recipient} (not ".
                    $plugin->get_config('paypalbusiness').")", $data);
            die;
        }

        if (!$user = $DB->get_record('user', array('id'=>$data->userid))) {   // Check that user exists
            \enrol_payment\util::message_paypal_error_to_admin("User $data->userid doesn't exist", $data);
            die;
        }

        if (!$course = $DB->get_record('course', array('id'=>$data->courseid))) { // Check that course exists
            \enrol_payment\util::message_paypal_error_to_admin("Course $data->courseid doesn't exist", $data);
            die;
        }

        $coursecontext = context_course::instance($course->id, IGNORE_MISSING);

        // Check that amount paid is the correct amount.
        if ( (float) $plugininstance->cost <= 0 ) {
            $originalcost = (float) $plugin->get_config('cost');
        } else {
            $originalcost = (float) $plugininstance->cost;
        }

        // Use the same rounding of floats as on the enrol form.
        $originalcost = format_float($originalcost, 2, false);

        // What should the user have paid? Verify using info stored in the database.
        $cost = helper::calculate_cost($plugininstance, $payment)["subtotal"];

        if ($data->payment_gross + 0.01 < $cost) {
            // This shouldn't happen unless the user spoofs their requests, but
            // if it does, the discount is just invalid.
            \enrol_payment\util::message_paypal_error_to_admin("Amount paid is not enough ($data->payment_gross < $cost))", $data);
            die;
        }

        // Use the queried course's full name for the item_name field.
        $data->item_name = $course->fullname;

        // All clear.
        $DB->insert_record("enrol_payment_transaction", $data);
        $DB->update_record("enrol_payment_session", array("id" => $payment->id, "paypaltxnid" => $data->txnid));


        if ($plugininstance->enrolperiod) {
            $timestart = time();
            $timeend   = $timestart + $plugininstance->enrolperiod;
        } else {
            $timestart = 0;
            $timeend   = 0;
        }

        if(!$multiple) {
            // Make a singleton array so that we can do this whole thing in a foreach loop.
            $multiple_userids = [$user->id];
        }

        $mailstudents = $plugin->get_config('mailstudents');
        $mailteachers = $plugin->get_config('mailteachers');
        $mailadmins   = $plugin->get_config('mailadmins');
        $shortname = $course->shortname;

        // Pass $view=true to filter hidden caps if the user cannot see them
        if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                                             '', '', '', '', false, true)) {
            $users = sort_by_roleassignment_authority($users, $context);
            $teacher = array_shift($users);
        } else {
            $teacher = false;
        }

        foreach($multiple_userids as $uid) {
            if (!$user = $DB->get_record('user', array('id'=>$uid))) {   // Check that user exists
                \enrol_payment\util::message_paypal_error_to_admin("User $data->userid doesn't exist", $data);
                die;
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

    } else if (strcmp ($result, "INVALID") == 0) { // ERROR
        $DB->insert_record("enrol_payment_transaction", $data, false);
        throw new moodle_exception('erripninvalid', 'enrol_payment', '', null, json_encode($data));
    }
}
