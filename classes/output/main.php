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
 * Renderable main class.
 *
 * @package    enrol_payment
 * @copyright  2020 LMS Doctor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_payment\output;
defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;
use enrol_payment\helper;
use stdClass;
use context_course;
use moodle_url;
use html_writer;

require_once($CFG->dirroot . '/lib/enrollib.php');

/**
 * Renderable main class.
 *
 * @package   enrol_payment
 * @copyright 2020 LMS Doctor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class main implements renderable, templatable {

    private $instance;

    /**
     * Constructor.
     *
     * @param string $instance
     * @param array  $config
     */
    public function __construct(stdClass $instance) {
        $this->instance = $instance;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $CFG, $USER, $PAGE, $DB, $OUTPUT;
        profile_load_data($USER);
        ob_start();

        // Allow Payment Enrollments is true when No is selected
        // in the parent plugin setting.
        if (get_config('enrol_payment', 'status')) {
            return false;
        }

        $config = $this->get_settings();

        // No cost, other enrolment methods (instances) should be used.
        if (abs((float) $this->instance->cost) < 0.01) {
            echo html_writer::tag('p', get_string('nocost', 'enrol_payment'));
            die();
        }

        $course = $DB->get_record('course', array('id' => $this->instance->courseid));

        // Force login only for guest user, not real users with guest role.
        // Used to verify payment data so that it can't be spoofed.
        $token      = bin2hex(random_bytes(16));
        $price      = format_float((float) $this->instance->cost, 2, false);
        $taxpercent = $config->taxinfo['taxpercent'];
        $paymentid  = helper::store_payment_session($token, $USER->id, $course->id,
                                $this->instance->id, $price, $taxpercent);
        $session    = $DB->get_record('enrol_payment_session', ['id' => $paymentid]);
        $costslist  = helper::calculate_cost($this->instance, $session, true);

        // Are discounts enabled in the admin settings?
        $symbol = enrol_payment_get_currency_symbol($this->instance->currency);

        $jsdata = [
            $this->instance->id,
            $config->stripekey,
            $price,
            $token,
            $course->fullname,
            $this->instance->customint4,
            helper::get_stripe_logo_url(),
            $taxpercent,
            $costslist['subtotal'],
            $config->validatezipcode,
            $config->addressrequired,
            $USER->email,
            $this->instance->currency,
            $symbol,
            $config->codeisrequired,
            $config->threshold,
            $session->units,
        ];
        $PAGE->requires->js_call_amd('enrol_payment/enrolpage', 'init', $jsdata);
        $PAGE->requires->css('/enrol/payment/style/styles.css');

        // Sanitise some fields before building the PayPal form.
        $USER->userfullname = fullname($USER);
        $taxamountstring    = format_float($taxpercent * $price, 2, true);
        $originaltotal      = $price + $taxamountstring;

        $singleuser = false;
        $multiplesingle = false;
        if ($config->threshold == 1 && !$config->allowmultiple) {
            $singleuser = true;
        } else if ($config->threshold == 1 && $config->allowmultiple) {
            $multiplesingle = true;
        }

        $cost                        = new stdClass;
        $cost->price                 = $price;
        $cost->total                 = $originaltotal;
        $cost->units                 = ($session->units > 1) ? $session->units : '';
        $cost->unitdiscount          = $costslist['percentdiscountunit'];
        $cost->coursename            = $course->fullname;
        $cost->courseshortname       = $course->shortname;
        $cost->localisedcostuntaxed  = $costslist['subtotal'];
        $cost->taxstring             = $config->taxinfo['taxstring'];
        $cost->taxamountstring       = $taxamountstring;
        $cost->localisedcost         = $costslist['subtotal'];
        $cost->currency              = $this->instance->currency;
        $cost->symbol                = $symbol;
        $cost->notaxedcost           = $costslist['subtotal'];
        $cost->taxamount             = format_float($taxpercent * $price, 2, false);
        $cost->threshold             = $config->threshold;
        $cost->discountamount        = (float) format_float($this->instance->customdec1, 2, true);

        // If percentage discount, get the percentage amount to display.
        $cost->discountispercentage = false;
        $cost->discountisvalue = false;
        switch ($config->discounttype) {
            case 1:
                $cost->discountispercentage = true;
                break;
            case 2:
                $cost->discountisvalue = true;
                break;
            default:
                $config->hasdiscount = false;
                break;
        }

        // Check if applies for multiple users.
        $cost->perseat = '';
        $multipleusers = false;
        if ($config->threshold > 1 && $config->discounttype > 0) {
            $multipleusers = true;
            $cost->perseat = ($config->discounttype == 2) ? ' per-person' : '';
        }

        // Refactor this logic.
        if ($config->discounttype == 1) {
            $cost->discountamount = helper::normalize_percent_discount($cost->discountamount, $config->discounttype) * 100;
            $cost->percentsymbol  = '%';
        }

        $USER->taxregion = isset($USER->profile_field_taxregion) ?? '';

        // Store all payment related values in an object.
        $payment                = new stdClass;
        $payment->paypalaction  = 'https://www.paypal.com/cgi-bin/webscr';
        $payment->stripeaction  = $CFG->wwwroot . '/enrol/payment/stripecharge.php';
        $payment->paypalaccount = $config->paypalaccount;
        $payment->prepaytoken   = $token;
        $payment->shipping      = $this->instance->customint4 ? 2 : 1;
        $payment->ipnurl        = $CFG->wwwroot . '/enrol/payment/ipn.php';
        $payment->returnurl     = new moodle_url('/enrol/payment/return.php', [
            'id' => $course->id, 'token' => $token
        ]);
        $payment->cancelurl     = $CFG->wwwroot;
        $payment->strcontinue   = get_string('continuetocourse');

        $transferinstructions = helper::get_transfer_instructions($costslist['subtotal'],
                                            $course->fullname, $course->shortname);

        $multipleregicon = $OUTPUT->help_icon('multipleregistration', 'enrol_payment');

        $totemplate = [
            'allowmultiple'         => $config->allowmultiple,
            'coderequired'          => $config->codeisrequired,
            'cost'                  => $cost,
            'discounttype'          => $config->discounttype,
            'gatewaysenabled'       => ($config->haspaypal || $config->hasstripe),
            'hasbothpayments'       => ($config->haspaypal && $config->hasstripe),
            'hasdiscountcode'       => $config->codeisrequired,
            'hasdiscount'           => helper::has_discount($config->discounttype),
            'hastax'                => (empty($config->taxinfo['taxstring'])) ? false : true,
            'multipleusers'         => $multipleusers,
            'multiplesingle'        => $multiplesingle,
            'payment'               => $payment,
            'paypalenabled'         => $config->haspaypal,
            'stripeenabled'         => $config->hasstripe,
            'singleuser'            => $singleuser,
            'user'                  => $USER,
            'transferinstructions'  => $transferinstructions,
            'multipleregicon'       => $multipleregicon,
        ];

        return $totemplate;
    }

    /**
     * Returns multiple configuration values.
     *
     * @param  stdClass $instance
     * @return stdClass
     */
    private function get_settings() {

        $config                 = new stdClass;
        $config->discounttype   = $this->instance->customint3;
        $config->codeisrequired = $this->instance->customint7;

        $config->taxinfo        = $this->get_tax_info($this->instance->cost, $this->instance->courseid);
        $config->allowmultiple  = (get_config('enrol_payment', 'allowmultipleenrol') && $this->instance->customint5);
        $config->threshold      = $this->instance->customint8;

        $config->haspaypal      = (bool) trim(get_config('enrol_payment', 'paypalbusiness'));
        $config->paypalaccount  = get_config('enrol_payment', 'paypalbusiness');

        $stripesecret           = get_config('enrol_payment', 'stripesecretkey');
        $config->stripekey      = get_config('enrol_payment', 'stripepublishablekey');
        $config->hasstripe      = ((bool) trim($stripesecret)) && ((bool) trim($config->stripekey));

        $config->transferinstructions = get_config('enrol_payment', 'transferinstructions');
        $config->validatezipcode        = get_config('enrol_payment', 'validatezipcode');
        $config->addressrequired = get_config('enrol_payment', 'billingaddress');

        return $config;
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
        if (stripos($taxregion, strtolower(trim($userfield))) === false) {
            return ['taxpercent' => 0, 'taxstring'  => ''];
        }

        if (!is_numeric($taxrate)) {
            debugging('Encountered non-numeric tax value.');
        }

        try {
            $floattaxrate = floatval($taxrate);
            return [
                'taxpercent' => $floattaxrate,
                'taxstring'  => '(' . floor($floattaxrate * 100) . '% ' . get_string('tax', 'enrol_payment') . ')'
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
    private function get_tax_info($cost) {
        global $USER;
        profile_load_data($USER);

        // If the option is disabled, return.
        $hastaxes = get_config('enrol_payment', 'definetaxes');
        if (!$hastaxes) {
            return ['taxpercent' => 0, 'taxstring' => ''];
        }

        $taxdefs        = get_config('enrol_payment', 'taxdefinitions');
        $countrytax     = get_config('enrol_payment', 'countrytax');

        // If country tax are set and the user country is empty. Force user to edit his profile.
        if (!empty($countrytax) && empty($USER->country)) {
            $urltogo = new moodle_url('/user/edit.php', ['id' => $USER->id]);
            redirect($urltogo, 'You must choose your country', null, \core\output\notification::NOTIFY_WARNING);
        }

        if (!empty($countrytax)) {
            $taxholder = $this->get_tax_amount($countrytax, $USER->country);
            return $taxholder;
        }

        // If the tax field is not set, let's not calculate taxes.
        if (!isset($USER->profile_field_taxregion)) {
            return ['taxpercent' => 0, 'taxstring' => ''];
        }

        // If the tax country is not empty, use it. Otherwise use the tax region.
        $taxdefs = get_config('enrol_payment', 'taxdefinitions');
        $taxdeflines = explode("\n", $taxdefs);

        // Return if this is empty.
        if (empty($taxdefs)) {
            return ['taxpercent' => 0, 'taxstring' => ''];
        }

        foreach ($taxdeflines as $taxline) {
            $taxholder = $this->get_tax_amount($taxline, $USER->profile_field_taxregion);
            if (!empty($taxholder['taxpercent'])) {
                break;
            }
        }

        return $taxholder;
    }
}
