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
 * Strings for component 'enrol_payment', language 'en'.
 *
 * @package    enrol_payment
 * @copyright  2018 Seth Yoder <seth.a.yoder@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['assignrole'] = 'Assign role';
$string['businessemail'] = 'PayPal business email';
$string['businessemail_desc'] = 'The email address of your business PayPal account';
$string['cost'] = 'Enrol cost';
$string['cost_desc'] = 'Note that the cost specified in the individual course setting overrides the site-wide cost.';
$string['costerror'] = 'The enrolment cost is not numeric';
$string['costorkey'] = 'Please choose one of the following methods of enrolment.';
$string['currency'] = 'Currency';
$string['customwelcomemessage'] = 'Custom welcome message';
$string['defaultrole'] = 'Default role assignment';
$string['defaultrole_desc'] = 'Select role which should be assigned to users during enrolments.';
$string['enrolenddate'] = 'End date';
$string['enrolenddate_help'] = 'If enabled, users can be enroled until this date only.';
$string['enrolenddaterror'] = 'Enrolment end date cannot be earlier than start date';
$string['enrolgroup'] = 'Group enrolment';
$string['enrolnogroup'] = 'No group';
$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperiod_desc'] = 'Default length of time that the enrolment is valid. If set to zero, the enrolment duration will be unlimited by default.';
$string['enrolperiod_help'] = 'Length of time that the enrolment is valid, starting with the moment the user is enroled. If disabled, the enrolment duration will be unlimited.';
$string['enrolstartdate'] = 'Start date';
$string['enrolstartdate_help'] = 'If enabled, users can be enroled from this date onward only.';
$string['errcommunicating'] = 'There was an error communicating with the server. Please refresh the page and try again. If the problem persists, please contact the site administrator.';
$string['errdisabled'] = 'The Payment enrolment plugin is disabled and does not handle payment notifications.';
$string['erripninvalid'] = 'Instant payment notification has not been verified by PayPal.';
$string['errpaypalconnect'] = 'Could not connect to {$a->url} to verify the instant payment notification: {$a->result}';
$string['expiredaction'] = 'Enrolment expiry action';
$string['expiredaction_help'] = 'Select action to carry out when user enrolment expires. Please note that some user data and settings are purged from course during course unenrolment.';
$string['mailadmins'] = 'Notify admin';
$string['mailstudents'] = 'Notify students';
$string['mailteachers'] = 'Notify teachers';
$string['messageprovider:payment_enrolment'] = 'Payment enrolment messages';
$string['nocost'] = 'There is no cost associated with enroling in this course!';
$string['payment:config'] = 'Configure Payment enrol instances';
$string['payment:manage'] = 'Manage enroled users';
$string['payment:unenrol'] = 'Unenrol users from course';
$string['payment:unenrolself'] = 'Unenrol self from the course';
$string['paypalaccepted'] = 'PayPal payments accepted';
$string['pluginname'] = 'Payment';
$string['pluginname_desc'] = 'The Payment module allows you to set up paid courses.';
$string['privacy:metadata:enrol_payment:enrol_payment'] = 'Information about the Payment transactions for Payment enrolments.';
$string['privacy:metadata:enrol_payment:enrol_payment:business'] = 'Email address or PayPal account ID of the payment recipient (that is, the merchant).';
$string['privacy:metadata:enrol_payment:enrol_payment:courseid'] = 'The ID of the course that is sold.';
$string['privacy:metadata:enrol_payment:enrol_payment:instanceid'] = 'The ID of the enrolment instance in the course.';
$string['privacy:metadata:enrol_payment:enrol_payment:item_name'] = 'The full name of the course that its enrolment has been sold.';
$string['privacy:metadata:enrol_payment:enrol_payment:memo'] = 'A note that was entered by the buyer in PayPal website payments note field.';
$string['privacy:metadata:enrol_payment:enrol_payment:option_selection1_x'] = 'Full name of the buyer.';
$string['privacy:metadata:enrol_payment:enrol_payment:parent_txn_id'] = 'In the case of a refund, reversal, or canceled reversal, this would be the transaction ID of the original transaction.';
$string['privacy:metadata:enrol_payment:enrol_payment:paymentstatus'] = 'The status of the payment.';
$string['privacy:metadata:enrol_payment:enrol_payment:payment_type'] = 'Holds whether the payment was funded with an eCheck (echeck), or was funded with PayPal balance, credit card, or instant transfer (instant).';
$string['privacy:metadata:enrol_payment:enrol_payment:pendingreason'] = 'The reason why payment status is pending (if that is).';
$string['privacy:metadata:enrol_payment:enrol_payment:reason_code'] = 'The reason why payment status is Reversed, Refunded, Canceled_Reversal, or Denied (if the status is one of them).';
$string['privacy:metadata:enrol_payment:enrol_payment:receiveremail'] = 'Primary email address of the payment recipient (that is, the merchant).';
$string['privacy:metadata:enrol_payment:enrol_payment:receiver_id'] = 'Unique PayPal account ID of the payment recipient (i.e., the merchant).';
$string['privacy:metadata:enrol_payment:enrol_payment:tax'] = 'Amount of tax charged on payment.';
$string['privacy:metadata:enrol_payment:enrol_payment:timeupdated'] = 'The time of Moodle being notified by PayPal about the payment.';
$string['privacy:metadata:enrol_payment:enrol_payment:txnid'] = 'The merchant\'s original transaction identification number for the payment from the buyer, against which the case was registered';
$string['privacy:metadata:enrol_payment:enrol_payment:userid'] = 'The ID of the user who bought the course enrolment.';
$string['privacy:metadata:enrol_payment:paypal_com'] = 'The Payment enrolment plugin transmits user data from Moodle to the PayPal website.';
$string['privacy:metadata:enrol_payment:paypal_com:address'] = 'Address of the user who is buying the course.';
$string['privacy:metadata:enrol_payment:paypal_com:city'] = 'City of the user who is buying the course.';
$string['privacy:metadata:enrol_payment:paypal_com:country'] = 'Country of the user who is buying the course.';
$string['privacy:metadata:enrol_payment:paypal_com:custom'] = 'A hyphen-separated string that contains ID of the user (the buyer), ID of the course, ID of the enrolment instance.';
$string['privacy:metadata:enrol_payment:paypal_com:email'] = 'Email address of the user who is buying the course.';
$string['privacy:metadata:enrol_payment:paypal_com:first_name'] = 'First name of the user who is buying the course.';
$string['privacy:metadata:enrol_payment:paypal_com:last_name'] = 'Last name of the user who is buying the course.';
$string['privacy:metadata:enrol_payment:paypal_com:os0'] = 'Full name of the buyer.';
$string['processexpirationstask'] = 'Payment enrolment send expiry notifications task';
$string['sendcoursewelcomemessage'] = 'Send course welcome message';
$string['sendcoursewelcomemessage_help'] = '';
$string['sendpaymentbutton_paypal'] = 'Send payment via PayPal';
$string['sendpaymentbutton_stripe'] = 'Send payment via Stripe';
$string['status'] = 'Allow Payment enrolments';
$string['status_desc'] = 'Allow users to use PayPal and Stripe to enrol.';
$string['transactions'] = 'PayPal transactions';
$string['unenrolselfconfirm'] = 'Do you really want to unenrol yourself from course "{$a}"?';
$string['customwelcomemessage_help'] = 'A custom welcome message may be added as plain text or Moodle-auto format, including HTML tags and multi-lang tags. <br>The following placeholders may be included in the message:<br><br>* Course name {$a->coursename}<br>* Link to user\'s profile page {$a->profileurl}<br>* User email {$a->email}<br>* User fullname {$a->fullname}';
$string['discounttype'] = 'Discount type';
$string['discounttype_help'] = 'Select type of discount. If "Value discount" is selected and multiple enrolments are purchased, the discount will be applied per-item.';
$string['nodiscount'] = 'No discount &nbsp;&nbsp;';
$string['percentdiscount'] = 'Percentage discount &nbsp;&nbsp;';
$string['valuediscount'] = 'Value discount &nbsp;&nbsp;';
$string['applydiscount'] = 'Apply discount';
$string['discountthreshold'] = 'Discount threshold';
$string['discountthreshold_help'] = 'Minimum number of seats that a user must purchase in order for a discount to be applied. This allows course creators to set up a discount for bulk purchases. The value "1" means there is no discount threshold.';
$string['discountthresholdtoolow'] = 'Discount threshold must be greater than 0.';
$string['discountthresholdbutnomultipleenrol'] = 'If multiple enrolment is disabled, the discount threshold must not be greater than 1.';
$string['requirediscountcode'] = 'Require discount code';
$string['requirediscountcode_help'] = 'If checked, the user will need to enter the discount code in order to be eligible for a discount. If unchecked, the discount will automatically be applied to purchases that meet the discount threshold.';
$string['discountcode'] = 'Discount code';
$string['discountamount'] = 'Discount amount';
$string['discountamount_help'] = 'If a Percentage discount is used, values under 1.00 will be treated as a percentage (out of 1.00). For example, you may set a 25% discount by entering either "25", "25.00", or "0.25".';
$string['discounttypeerror'] = 'Invalid discount type.';
$string['discountamounterror'] = 'The discount amount is not numeric.';
$string['discountdigitserror'] = 'The discount amount must have fewer than 12 digits.';
$string['negativediscounterror'] = 'The discount amount cannot be negative.';
$string['percentdiscountover100error'] = 'A percentage discount cannot be set above 100.';
$string['allowdiscounts'] = 'Allow course enrolment to include a discount';
$string['allowdiscounts_help'] = 'Allow enrolment instances to include a discount code.';
$string['nogatewayenabled'] = 'Payments are disabled. Please contact the site administrator.';
$string['invalidgateway'] = 'Unrecognized payment gateway. Please contact the site administrator.';
$string['notenoughunits'] = 'Attempting to make a purchase for fewer than 1 users.';
$string['billingaddress'] = 'Require users to enter their billing address';
$string['billingaddress_desc'] = 'This sets the Stripe payment option for whether the user should be asked to input their billing address. It is off by default, but it is a good idea to turn it on.';
$string['validatezipcode'] = 'Validate the billing postal code (Stripe)';
$string['validatezipcode_desc'] = 'This sets the Stripe payment option for whether the billing address should be verified as part of processing the payment. They strongly recommend that this option should be on, to reduce fraud.';
$string['requireshipping'] = 'Require shipping address';
$string['multipleregistration'] = 'Multiple Registration';
$string['multipleregistration_help'] = 'Enrol other(s) by entering the email address associated with their account on the site. To register yourself as well as others, enter your email address and click on the "Add registrant" icon.';
$string['allowmultipleenrol'] = 'Allow multiple registration';
$string['allowmultipleenrol_help'] = 'Allow a user to enrol others by entering their email address. Note that the other registrants need to already have created their account on the site.';
$string['sameemailaccountsallowed'] = "Error: Accounts sharing the same email address are allowed on this Moodle site. Because of this, the Multiple Registration cannot be used. Please contact your site administrator.";
$string['duplicateemail'] = "There are duplicate emails.";
$string['paypalaccountnotneeded'] = "<b>A PayPal account is not needed to pay by credit card.</b> <br>At the PayPal site, there is a <i>\"Pay with a credit or Visa Debit card\"</i> <br>button. Please note that the name and address on the form <b>must</b><br><b>match</b> the name and address associated with the credit card.";
$string['or'] = "OR";
$string['usersnotfoundwithemail'] = 'Either the registrant(s) have not yet created an account OR<br />their account is associated with a different email address: {$a}';
$string['totalcost'] = '<p>If your intention is to register <b>yourself as well</b> and your name is not in the list, click <b>Cancel</b>. On the Enrolment page, enter your email address as one of the <b>others</b> to enrol.</p><p>{$a->discount}</p><p>Total cost: {$a->calculation}</p>';
$string['multipleregistrationconfirmuserlist'] = "You are purchasing a registration for each of the following: <p></p><ul><li>";
$string['enabletaxcalculation'] = "Enable tax calculation";
$string['enabletaxcalculation_help'] = "If the \"msn\" user profile field is overloaded to store a canadian province abbreviation, calculate the tax and factor into the cost.";
$string['defaultcoursewelcomemessage'] = "Default course welcome message";
$string['stripesecretkey'] = "Stripe Secret Key";
$string['stripesecretkey_desc'] = "The API secret key of your Stripe account";
$string['stripepublishablekey'] = "Stripe Publishable Key";
$string['stripepublishablekey_desc'] = "The API Publishable Key of your Stripe account";
$string['stripelogo'] = "Stripe logo";
$string['stripelogo_desc'] = "128x128 store logo used for Stripe checkout";
$string['charge_description1'] = "create customer for email receipt";
$string['charge_description2'] = 'Charge for Course Enrolment';
$string['addaregistrant'] = 'Add registrant';
$string['removearegistrant'] = 'Remove registrant';
$string['definetaxes'] = 'Allow custom tax definitions';
$string['definetaxes_desc'] = 'Depending on whether there is an entry in the country <strong>or</strong> region input box, the script will process <strong>either</strong> a single tax rate based on the user’s country or a regional tax rate based on the user’s <b>taxregion</b> user profile field value.';
$string['taxdefinitions'] = 'Regional tax rates';
$string['taxdefinitions_help'] = '<p><b>IMPORTANT:</b> <i>Country tax rate must be empty for regional tax to work.</i><br><br> A region can be a province, state, territory, department or anything that has an associated tax rate. The format for each entry is Region : 0.## for tax rate. For instance, assume there are only two taxable provinces: Ontario (rate 13%) and Quebec (rate 5%), the entries would be:<br><br>Ontario : 0.13<br>Quebec : 0.05<br><br>Enter each tax definition on a <b>separate</b> line. The label has to be spelled the same way as in the corresponding entry in the “Menu options (one per line)” setting in the (taxregion) user profile field.</p>';
$string['countrytax'] = 'Country tax rate';
$string['countrytax_desc'] = 'The format is Country Code : 0.## for tax rate. For instance, assume you are setting a tax rate for Colombia (rate 19%) the entry would be: <b>CO : 0.19</b>';
$string['feestringtaxed'] = 'The fee for <b>{$a->coursename}</b><br>is <b>{$a->symbol}<span class="localisedcost-untaxed">{$a->localisedcostuntaxed}</span></b> + {$a->symbol}<span class="taxamountstring">{$a->taxamountstring}</span> <span class="taxstring">{$a->taxstring}</span> = <b>{$a->symbol}<span class="localisedcost">{$a->localisedcost}</span></b> {$a->currency}.<br>';
$string['feestringnotax'] = 'The fee for <b>{$a->coursename}</b><br>is <b>{$a->symbol}<span class="localisedcost">{$a->localisedcost}</span></b> {$a->currency}.<br>';
$string['discountwillbeapplied'] = 'A <b>{$a->symbol}{$a->discountamount}{$a->percentsymbol}{$a->perSeat}</b> discount will be applied to a purchase of <b>{$a->discountthreshold}</b> or more registrations.';
$string['allowbanktransfer'] = 'Allow Bank/Email transfer payment';
$string['transferinstructions'] = 'Bank/Email transfer payment instructions';
$string['transferinstructions_help'] = "This text will appear on the course enrolment page. Bank or email money transfer instructions are customizable, but it is advisable to not alter {{AMOUNT}}, {{COURSEFULLNAME}} or {{COURSESHORTNAME}}. These variables are replaced by the plugin code. Note that {{AMOUNT}} is replaced with the course fee + tax (if applicable).";
$string['transferinstructions_default'] = "<h4>Prefer to pay directly from your bank account?</h4><br><p>Email money transfer (known as <a href=\"https://www.youtube.com/watch?time_continue=4&v=zL9yoZZXyOE\" target=\"_blank\"><em>Interac</em> e-Transfer</a>) is an option if you:</p><ul><li>have an email address or a mobile number, and</li><li>are registered for <em>Interac</em> e-Transfer service with your financial institution.</li></ul><p>To send an <em>Interac</em> e-Transfer payment:</p><ol><li><p>Log in to your financial institution's online or mobile banking and navigate to <em>Interac</em> e-Transfer menu.</p></li><li><p>Select the account from which to withdraw the funds.</p></li><li><p>Add a new recipient using <strong>ENTER RECIPIENT NAME</strong> as the name, <strong>ENTER EMAIL ADDRESS TO RECEIVE THE FUNDS</strong> as the email address, <strong>Who is offering {{COURSESHORTNAME}}?</strong> as the security question, and <strong>ENTER THE ANSWER WITH A MINIMUM OF 5 CHARACTERS</strong> as the answer.</p></li><li><p>Select <strong>ENTER RECIPIENT NAME</strong> as the recipient.</p></li><li><p>Fill in the amount of <strong>{{AMOUNT}}</strong> and enter <strong>your name</strong> in the message area.</p></li><li><p>Follow the on-screen instructions to confirm the information and complete the transfer.</p></li></ol><p>Once payment is received, you will be notified of your enrolment in <strong>{{COURSEFULLNAME}}</strong>.</p>";
$string['paypalwait'] = 'Please wait while PayPal confirms your payment. You will be given access to <i>{$a}</i> when the payment has completed.';
$string['errorcheckingenrolment'] = "Failure checking user enrolment. Please contact your server administrator. In the meantime, you should navigate to the course manually.";
$string['thanksforpaypal'] = 'Thank you for your multiple enrolment purchase. The registrant(s) have been successfully enroled in <i>{$a}</i>.';
$string['correctdiscountcode'] = 'Valid discount code';
$string['correctdiscountcode_desc'] = 'The discount code you have entered is valid.';
$string['incorrectdiscountcode'] = 'Invalid discount code';
$string['incorrectdiscountcode_desc'] = 'The discount code is incorrect.';
$string['errorpaymentpending'] = 'Your PayPal payment is stuck in "{$a->reason}" status. This can be due to currency mismatch or other PayPal configuration issues. Please {$a->supportemaillink} the site administrator.';
$string['needdiscountcode'] = 'Please provide a discount code.';
$string['needdiscountamount'] = 'Please provide a discount amount.';
$string['enrolothers'] = 'Enrol other(s)';
$string['cancelenrolothers'] = 'Cancel multiple enrolment';
$string['confirmpurchase'] = 'Confirm Purchase';
$string['continue'] = 'Continue';
$string['invalidpaymentprovider'] = 'Invalid payment provider.';
$string['dismiss'] = 'Dismiss';
$string['novalidemailsentered_desc'] = 'No valid email address(es) have been entered. Either enter at least one email address or click on the <i>Cancel multiple enrolment</i> button.';
$string['novalidemailsentered'] = 'No registrant(s) specified.';
$string['totalenrolmentfee'] = 'Total enrolment fee:';
$string['charge_enrolment'] = 'Enrolment in: ';
$string['error'] = 'Error';
$string['paypalmixeduse'] = 'Mixed-use PayPal account';
$string['paypalmixeduse_desc'] = 'If other products then enrolments are processes via the PayPal account (e.g., videos, books, etc) than tick the checkbox.';
$string['getvaluediscount'] = 'The {$a->symbol}{$a->discountvalue} discount per-person has been applied.';
$string['getvaluecalculation'] = '{$a->symbol}{$a->originalcost} - {$a->symbol}{$a->discountvalue} discount × {$a->units} {$a->taxstring} = <b>{$a->symbol}{$a->subtotaltaxed}</b> {$a->currency}';
$string['nodiscountvaluecalculation'] = '{$a->symbol}{$a->originalcost} × {$a->units} {$a->taxstring} = <b>{$a->symbol}{$a->subtotaltaxed}</b> {$a->currency}';
$string['percentcalculation'] = '{$a->symbol}{$a->originalcost} × {$a->units} {$a->taxstring} = <b>{$a->symbol}{$a->subtotaltaxed}</b> {$a->currency}';
$string['percentcalculationdiscount'] = '{$a->symbol}{$a->originalcost} - {$a->symbol}{$a->unitdiscount} ({$a->percentdiscount}% discount) × {$a->units} {$a->taxstring} = <b>{$a->symbol}{$a->subtotaltaxed}</b> {$a->currency}';
$string['percentdiscountstring'] = 'The {$a->percentdiscount}% discount has been applied.';
$string['registrant'] = 'Registrant';
$string['enteremail'] = 'Enter email address';
$string['feeforcoursename'] = '<p>The fee for <strong>{$a}</strong>';
$string['is'] = 'is';
$string['thefeeisnow'] = 'The fee is now';
$string['discountwillapply'] = 'discount will be applied to a purchase of <b>{$a}</b> or more registrations.';
$string['a'] = 'A';
$string['discount'] = 'discount';
$string['tax'] = 'tax';
$string['paymentsuccess'] = 'The payment was successful';
$string['paymentfailed'] = 'The payment failed';
$string['enablesandbox'] = 'Enable Sandbox';
$string['notcompleted'] = 'Status not completed or pending. User unenroled from course';
$string['currencynotmatch'] = 'Currency does not match course settings, received: {$a}';
$string['txnrepeated'] = 'The transaction {$a} already exist.';
$string['txnrepeated'] = 'The business email is {$a} which does not match with the email in the settings';