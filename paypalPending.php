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
 * Notify the user that their PayPal payment is pending
 *
 * @package    enrol_payment
 * @copyright  2018 Seth Yoder
 * @author     Seth Yoder <seth.a.yoder@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$reason = required_param('reason', PARAM_TEXT);

$context = context_course::instance($id, MUST_EXIST);
$PAGE->set_context($context);

$PAGE->set_url("$CFG->wwwroot/enrol/payment/paypalpending.php");

$a = new stdClass();
$a->supportemaillink = $CFG->supportemail ? "<a href=\"mailto:$CFG->supportemail\">contact</a>" : "contact";
$a->reason = $reason;

echo $OUTPUT->header();
echo '<div style="text-align: center;" class="paypal-pending">';
echo $OUTPUT->box(get_string('errorpaymentpending', 'enrol_payment', $a), 'generalbox', 'notice');
echo '</div>';
echo $OUTPUT->footer();
