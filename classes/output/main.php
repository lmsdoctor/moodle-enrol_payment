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

    protected $instance;
    protected $config;
    protected $originalcost;

    /**
     * Constructor.
     *
     * @param string $instance
     * @param array  $config
     */
    public function __construct(stdClass $instance, stdClass $config) {
        $this->instance = $instance;
        $this->config = $config;
        $this->originalcost = $instance->cost;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $CFG, $USER, $PAGE, $DB;
        profile_load_data($USER);
        ob_start();

        // No cost, other enrolment methods (instances) should be used.
        if (abs($this->originalcost) < 0.01) {
            echo html_writer::tag('p', get_string('nocost', 'enrol_payment'));
            die();
        }

        $course = $DB->get_record('course', array('id' => $this->instance->courseid));
        $context = context_course::instance($course->id);
        $originalcost = $this->originalcost;

        if (is_enrolled($context, $USER)) {
            return ob_get_clean();
        }

        // Get stripe logo if is found.
        $stripelogourl  = helper::get_stripe_logo_url();

        // Pass $view=true to filter hidden caps if the user cannot see them.
        $teacher = false;
        if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                                             '', '', '', '', false, true)) {
            $users = sort_by_roleassignment_authority($users, $context);
            $teacher = array_shift($users);
        }

        $taxstring  = $this->config->taxinfo['taxstring'];
        $taxpercent = $this->config->taxinfo['taxpercent'];

        $gatewaysenabled = ((int) $this->config->haspaypal) + ((int) $this->config->hasstripe);

        // Force login only for guest user, not real users with guest role.
        // Used to verify payment data so that it can't be spoofed.
        $prepaytoken    = bin2hex(random_bytes(16));
        $coderequired   = ($this->instance->customint7) ?? 0;
        $threshold      = $this->instance->customint8;

        $paymentid = helper::store_payment_session(
                $prepaytoken, $USER->id, $course->id,
                $this->instance->id, $originalcost, $taxpercent);

        // Calculate localised and "." cost, make sure we send PayPal/Stripe the same value,
        // please note PayPal expects amount with 2 decimal places and "." separator.
        $paymentobj = $DB->get_record('enrol_payment_session', ['id' => $paymentid]);

        $calculatecost = helper::calculate_cost($this->instance, $paymentobj, true);
        $calculatecostuntaxed = helper::calculate_cost($this->instance, $paymentobj, false);
        $localisedcost = $calculatecost['subtotallocalised'];
        $localisedcostuntaxed = $calculatecostuntaxed['subtotallocalised'];
        $originalcost = format_float($originalcost, 2, false);
        $coursefullname  = format_string($course->fullname, true, array('context' => $context));

        // Are discounts enabled in the admin settings?
        $enablediscountcodes = get_config('enablediscounts') && $this->instance->customint7 && $this->instance->customint3;
        $validatezipcode = get_config('validatezipcode');
        $billingaddressrequired = get_config('billingaddress');
        $discountamount = format_float($this->instance->customdec1, 2, true);

        $symbol = enrol_payment_get_currency_symbol($this->instance->currency);

        $jsdata = [
            $this->instance->id,
            $this->config->stripekey,
            $originalcost,
            $prepaytoken,
            $course->fullname,
            $this->instance->customint4,
            $stripelogourl,
            $taxpercent,
            $localisedcostuntaxed,
            $validatezipcode,
            $billingaddressrequired,
            $USER->email,
            $this->instance->currency,
            $symbol,
            $coderequired,
            $threshold
        ];
        $PAGE->requires->js_call_amd('enrol_payment/enrolpage', 'init', $jsdata);
        $PAGE->requires->css('/enrol/payment/style/styles.css');

        // Sanitise some fields before building the PayPal form.
        $USER->userfullname = fullname($USER);
        $stripeshipping    = $this->instance->customint4;
        $taxamountstring = format_float($taxpercent * $originalcost, 2, true);

        $singleuser = false;
        if ($threshold == 1) {
            $singleuser = true;
        }

        $cost                        = new stdClass;
        $cost->coursename            = $coursefullname;
        $cost->courseshortname       = $course->shortname;
        $cost->localisedcostuntaxed  = $localisedcostuntaxed;
        $cost->taxstring             = $taxstring;
        $cost->taxamountstring       = $taxamountstring;
        $cost->localisedcost         = $localisedcost;
        $cost->currency              = $this->instance->currency;
        $cost->symbol                = $symbol;
        $cost->currencysign          = $symbol;
        $cost->notaxedcost           = helper::calculate_cost($this->instance, $paymentobj, false)['subtotal'];
        $cost->taxamount             = format_float($taxpercent * $originalcost, 2, false);
        $cost->threshold             = $threshold;

        $discounttype = $this->instance->customint3;
        // If percentage discount, get the percentage amount to display.
        $cost->discountispercentage = false;
        $cost->discountisvalue = false;
        switch ($discounttype) {
            case 1:
                $cost->discountispercentage = true;
                break;
            case 2:
                $cost->discountisvalue = true;
        }

        if ($discounttype) {
            $percentdisplay = $calculatecost['percentdiscount'];
        }

        // Check if applies for multiple users.
        $cost->perseat = '';
        $multipleusers = false;
        if ($threshold > 1 && $discounttype > 0) {
            $multipleusers = true;
            $cost->perseat = ($discounttype == 2) ? ' per-person' : '';
        }

        // TODO: Refactor this logic.
        if ($discounttype > 0) {
            $cost->discountamount    = $discountamount;
            $cost->percentsymbol     = '';
            if ($discounttype == 1) {
                $cost->discountamount    = $discountamount;
                $cost->percentsymbol     = '%';
            }
        }

        $USER->taxregion = $USER->profile_field_taxregion;

        // Store all payment related values in an object.
        $payment                = new stdClass;
        $payment->paypalaction  = 'https://www.paypal.com/cgi-bin/webscr';
        $payment->stripeaction  = $CFG->wwwroot . '/enrol/payment/stripecharge.php';
        $payment->paypalaccount = $this->config->paypalaccount;
        $payment->prepaytoken   = $prepaytoken;
        $payment->shipping      = $this->instance->customint4 ? 2 : 1;
        $payment->ipnurl        = $CFG->wwwroot . '/enrol/payment/ipn.php';
        $payment->returnurl     = new moodle_url('/enrol/payment/return.php', [
            'id' => $course->id, 'token' => $prepaytoken
        ]);
        $payment->cancelurl     = $CFG->wwwroot;
        $payment->strcontinue   = get_string('continuetocourse');

        $totemplate = [
            'allowmultiple'         => $this->config->allowmultiple,
            'coderequired'          => $coderequired,
            'cost'                  => $cost,
            'discounttype'          => $discounttype,
            'gatewaysenabled'       => $gatewaysenabled,
            'hasdiscountcode'       => $enablediscountcodes,
            'hastax'                => (empty($taxstring)) ? false : true,
            'multipleusers'         => $multipleusers,
            'payment'               => $payment,
            'paypalenabled'         => $this->config->haspaypal,
            'singleuser'            => $singleuser,
            'user'                  => $USER,
        ];

        return $totemplate;
    }
}
