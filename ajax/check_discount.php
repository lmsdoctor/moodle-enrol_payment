<?php

/**
 * AJAX handler for checking a discount code
 *
 * @package    enrol_payment
 * @copyright  2018 Seth Yoder
 * @author     Andrés Ramos <andres.ramos@lmsdoctor.com>
 * @author     Seth Yoder <seth.a.yoder@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../config.php');
require_once("$CFG->libdir/moodlelib.php");
require_once(dirname(__FILE__) . '/util.php');

use enrol_payment\helper;

global $DB;

$instanceid = required_param('instanceid', PARAM_INT);
$prepayToken = required_param('prepaytoken', PARAM_ALPHANUM);
$discountcode = required_param('discountcode', PARAM_RAW);

$instance = $DB->get_record('enrol', ['id' => $instanceid]);
$correctcode = (trim($discountcode) == trim($instance->customtext2));
$payment = null;

if ($correctcode) {
    try {
        $payment = helper::get_payment_from_token($prepayToken);
    } catch (Exception $e) {
        echo json_encode([ 'success' => false
                         , 'failmessage' => $e->getMessage() ]);
        die();
    }

    try {
        $payment->code_given = true;
        $DB->update_record('enrol_payment_session', $payment);
        $to_return = helper::calculate_cost($instance, $payment);
    } catch (Exception $e) {
        echo json_encode([ 'success' => false
                         , 'failmessage' => "$e->getMessage"]);
        die();
    }

    $to_return["success"] = true;
    echo json_encode($to_return);
} else {
    echo json_encode([ "success" => false
                     , "failmessage" => get_string('incorrectdiscountcode_desc','enrol_payment')]);
}

?>
