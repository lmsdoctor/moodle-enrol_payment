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
 * Payment utility script
 *
 * @package    enrol_payment
 * @author     Seth Yoder <seth.a.yoder@gmail.com>
 * @copyright  2018 Seth Yoder (based on enrol_paypal code by 2004 Martin Dougiamas (http://dougiamas.com))
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require("../../config.php");
require_once("$CFG->dirroot/enrol/paypal/lib.php");

use enrol_payment\helper;

$id = required_param('id', PARAM_INT);
$token = required_param('custom', PARAM_TEXT);
$txnid = required_param('txn_id', PARAM_TEXT);
$userid = $USER->id;
$payment = helper::get_payment_from_token($token);
$payment->paypaltxnid = $txnid;
$DB->update_record('enrol_payment_session', $payment);
$purchasingforself = true;

if (!$course = $DB->get_record("course", array("id" => $id))) {
    redirect($CFG->wwwroot);
}

$context = context_course::instance($course->id, MUST_EXIST);
$PAGE->set_context($context);

require_login();

if (!empty($SESSION->wantsurl)) {
    $destination = $SESSION->wantsurl;
    unset($SESSION->wantsurl);
} else {
    $destination = "$CFG->wwwroot/course/view.php?id=$course->id";
}

if ($payment->multiple) {
    $userids = explode(',', $payment->multipleuserids);

    if (!in_array(strval($userid), $userids)) {
        $purchasingforself = false;
    }
}

$fullname = format_string($course->fullname, true, array('context' => $context));

if ($purchasingforself) {
    if (is_enrolled($context, null, '', true)) { // TODO: use real paypal check.
        redirect($destination, get_string('paymentthanks', '', $fullname));
    } else {   // IPN is slow, and doesn't always complete immediately...
        $ajaxurl = "$CFG->wwwroot/enrol/payment/ajax/checkEnrol.php";

        $PAGE->requires->css('/enrol/payment/style/styles.css');
        $PAGE->requires->js_call_amd('enrol_payment/return', 'init', array($destination, $ajaxurl, $course->id, $payment->id));
        $PAGE->set_url($destination);

        echo $OUTPUT->header();
        $a = new stdClass();
        $a->teacher = get_string('defaultcourseteacher');
        $a->fullname = $fullname;
        echo '<div style="text-align: center;" class="paypal-wait">';
        echo $OUTPUT->box(get_string('paypalwait', 'enrol_payment', $course->fullname), 'generalbox', 'notice');
        echo '</div>';
        echo '<div id="spin-container"></div>';
        echo $OUTPUT->footer();
    }
} else {
    $PAGE->set_url($destination);
    echo $OUTPUT->header();
    echo '<div style="text-align: center;">';
    echo $OUTPUT->box(get_string('thanksforpaypal', 'enrol_payment', $course->fullname), 'generalbox', 'notice');
    echo '</div>';
    echo $OUTPUT->footer();
}
