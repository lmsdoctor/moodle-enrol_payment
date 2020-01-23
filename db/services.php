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
 * Web Services.
 *
 * @package    enrol_payment
 * @copyright  2020 Andrés Ramos, LMS Doctor
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'enrol_payment_check_discount' => [
            'classname'   => 'enrol_payment_external',
            'methodname'  => 'check_discount',
            'classpath'   => 'enrol/payment/externallib.php',
            'description' => 'This function validates if the code is correct and returns the discounted value.',
            'type'        => 'write',
            'ajax'        => true,
            'capabilities'  => '',
    ],
    'enrol_payment_multiple_enrollment' => [
            'classname'   => 'enrol_payment_external',
            'methodname'  => 'multiple_enrollment',
            'classpath'   => 'enrol/payment/externallib.php',
            'description' => 'Validates the users that are being enrolled, if they exist or not.',
            'type'        => 'write',
            'ajax'        => true,
            'capabilities'  => '',
    ],
    'enrol_payment_single_enrollment' => [
            'classname'   => 'enrol_payment_external',
            'methodname'  => 'single_enrollment',
            'classpath'   => 'enrol/payment/externallib.php',
            'description' => 'Validates the user that is being enrolled.',
            'type'        => 'write',
            'ajax'        => true,
            'capabilities'  => '',
    ],
];

// During the plugin installation/upgrade, Moodle installs these services as pre-build services.
// A pre-build service is not editable by administrator.
$services = [
    'Check Discount' => [
        'functions' => ['enrol_payment_check_discount'],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'check_discount'
    ],
    'Multiple Enrollment Validation' => [
        'functions' => ['enrol_payment_multiple_enrollment'],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'multiple_enrollment'
    ],
    'Single Enrollment Validation' => [
        'functions' => ['enrol_payment_single_enrollment'],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'single_enrollment'
    ],
];