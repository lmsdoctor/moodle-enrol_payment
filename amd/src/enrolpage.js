/**
 * Enrol page JS for Payment plugin
 *
 * @package enrol_payment
 * @copyright 2018 Seth Yoder <seth.a.yoder@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'jquery',
    'core/modal_factory',
    'core/modal_events',
    'core/str',
    'core/config',
    'enrol_payment/spin',
    'core/ajax',
    'core/notification',
],
function($, ModalFactory, ModalEvents, MoodleStrings, MoodleCfg, Spinner, Ajax, Notification) {

    /**
     * JavaScript functionality for the enrol_payment enrol.html page
     */
    var EnrolPage = {

        /**
         * Set at init time. Moodle strings
         */
        mdlstr: undefined,

        /**
         * The payment gateway that will be used. Either "paypal" or "stripe"
         */
        gateway: null,

        /**
         * Set at init time.
         */
        originalCost: undefined,

        /**
         * Subtotal used for purchase.
         */
        subtotal: undefined,

        /**
         * Tax as a percentage of the purchase subtotal
         */
        taxPercent: undefined,

        /**
         * Dollar tax amount
         */
        taxAmount: undefined,

        /**
         * ID of this enrollment instance. Set at init time.
         */
        instanceid: undefined,

        /**
         * Unique ID for this page visit
         */
        prepayToken: undefined,

        /**
         * Billing address required
         */
        billingAddressRequired: undefined,

        /**
         * Should stripe validate zip code
         */
        validateZipCode: undefined,

        /**
         * Require user to enter shipping address?
         */
        shippingAddressRequired: undefined,

        /**
         * User's email address
         */
        userEmail: undefined,

        /**
         * Instance currency string
         */
        currency: undefined,

        /**
         * Currency symbol (currently only $ supported)
         */
        symbol: undefined,

        /**
         * Does the user need a code in order to get a discount?
         */
        discountCodeRequired: undefined,

        /**
         *
         */
        discountThreshold: undefined,
        units: undefined,

        /**
         * Functions dealing with the multi-user registration system
         */
        MultipleRegistration: {

            enabled: false,

            /**
             * Counts up to make sure no two email inputs will ever
             * have the same ID.
             */
            nextEmailID: 1,

            /**
             * Reads email input fields, ignoring any that are whitespace-only.
             * @return An array of emails that have been inputted
             */
            getEmails: function() {
                var emails = [];

                $(".mr-email-line input").each(function() {
                    var email = $(this).val();
                    if($.trim(email) !== "") {
                        emails.push(email);
                    }
                });
                return emails;
            },

            /*
             * Handles a click on the plus sign next to each email input field,
             * adding a new input field and updating the enumeration.
             *
             * @param plus      The plus icon to attach a handler to
             */
            addPlusClickHandler: function(plus, mdlstr) {
                var self = this;

                plus.click(function(e) {
                    e.preventDefault();
                    // Get HTML for the field we will create.
                    var nextHtml = self.makeEmailEntryLine(mdlstr);

                    // Remove all plus signs (there should only be one at any given time).
                    $(".plus-container").remove();

                    // Add the new HTML to the bottom of our container, and update its click handlers.
                    var newLine = $("#multiple-registration-container").append(nextHtml);
                    self.addPlusClickHandler($('.plus-container'), mdlstr);
                    self.addMinusClickHandler(newLine.find('.minus-container'), mdlstr);
                });
            },

            /*
             * Handles a click on the minus sign next to each email input field,
             * adding a new input field and updating the enumeration.
             *
             * @param minus      The minus icon to attach a handler to
             * @param mdlstr     Moodle Strings
             */
            addMinusClickHandler: function(minus, mdlstr) {
                var self = this;

                minus.click(function(e) {
                    e.preventDefault();
                    // Pop the whole email input line off the DOM.
                    $(this).parent().parent().remove();

                    // Add a plus icon to the last line, if it's not already there.
                    if (! $(".form-group:last .plus-container").length) {
                        $(".form-group:last").append(self.makePlusSign(mdlstr));
                        self.addPlusClickHandler($('.plus-container'), mdlstr);
                    }

                    // Re-number our rows for the user.
                    self.refreshEmailNums();
                });
            },

            /**
             * Returns HTML for a plus icon
             */
            makePlusSign: function(mdlstr) {
                var title = mdlstr["addaregistrant"];
                var plusSign = ' <a href="#" class="plus-container mb-2" title="' + title + '"><i style="color: green;" class="icon fa fa-user-plus"></i></a> ';
                return plusSign;
            },

            /**
             * Returns HTML for a plus sign and a minus sign if there is more
             * than one row already, and just a plus sign if there is only one
             * row.
             *
             * @param n         Number of rows (including this one) that already exist
             * @param mdlstr    Moodle Strings
             */
            makePlusAndMinusSigns: function(n, mdlstr) {
                var plusSign = this.makePlusSign(mdlstr);
                var title = mdlstr["removearegistrant"];
                var minusSign = '<a href="#" class="minus-container mb-2" title="' + title + '"><i style="color: red;" class="icon fa fa-user-times fa-fw"></i></a href="#">';
                if (n > 1) {
                    return plusSign + minusSign;
                } else {
                    return plusSign;
                }
            },

            /**
             * Re-numbers the email labels on the frontend.
             *
             * @return The next number to use.
             */
            refreshEmailNums: function() {
                var lastIndex = -1;
                $('.email-num').each(function(index) {
                    $(this).text(index + 1);
                    lastIndex = index;
                });
                return lastIndex + 2;
            },

            /**
             * @return HTML for one row of the email entry form.
             */
            makeEmailEntryLine: function(mdlstr) {
                var self = this;
                var m = self.refreshEmailNums();
                var n = self.nextEmailID;
                self.nextEmailID = self.nextEmailID + 1;
                var strRegistrant = mdlstr["registrant"];
                var strEnterEmail = mdlstr["enteremail"];

                var inputID = "\"multiple-registration-email-" + n + "\"";
                var div2 = '<div class="form-inline mr-email-line">'
                            + '<div class="form-group row">'
                                + '<label for=' + inputID + ' class="mb-2 col-form-label">' + strRegistrant + '&nbsp;<span class="email-num">' + m + ' </span></label>'
                                + '<div class="mb-2 mx-sm-3">'
                                    + '<input class="form-control multiple-registration-email" id=' + inputID + ' type="text" placeholder="' + strEnterEmail + '">';
                var endDiv = "</div></div></div>";

                // Passing n into makePlusAndMinusSigns works because the first
                // row never gets a minus.
                return div2 + this.makePlusAndMinusSigns(n, mdlstr) + endDiv;
            },

            checkoutConfirmModal: function(enrolPage, successmessage) {
                var trigger = $("#success-modal-trigger");
                trigger.off();

                ModalFactory.create({
                    type: ModalFactory.types.SAVE_CANCEL,
                    title: enrolPage.mdlstr["confirmpurchase"],
                    body: successmessage,
                    large: true,
                }, trigger).done(function(modal) {
                    modal.setSaveButtonText(enrolPage.mdlstr["continue"]);
                    modal.getRoot().on(ModalEvents.save, function() {
                        enrolPage.checkoutFinal();
                    });
                    enrolPage.removeDimmer(modal);
                });

                $("#success-modal-trigger").click();
            },

            /**
             * @param r The raw AJAX response
             */
            handleEmailSubmitAJAXResponse: function(r, enrolPage) {
                var self = this;
                var response = JSON.parse(r);
                if(response["success"]) {
                    enrolPage.subtotal = response["subtotal"];
                    $("span.units").text(response["units"]);
                    enrolPage.updateCostView();
                    self.checkoutConfirmModal(enrolPage, response["successmessage"]);
                } else {
                    var trigger = $("#error-modal-trigger");
                    trigger.off();

                    // Display error directly in the page.
                    enrolPage.errorFromEnrollment(response["failmessage"]);
                }
            },

            /**
             * Checks emails for multiple registration, and submits payment to
             * PayPal.
             */
            verifyAndSubmit: function(enrolPage) {
                var self = this;

                if((enrolPage.gateway !== 'paypal') && (enrolPage.gateway !== 'stripe')) {
                    alert(enrolPage.mdlstr["invalidpaymentprovider"]);
                    throw new Error(enrolPage.mdlstr["invlidpaymentprovider"]);
                }

                var emails = self.getEmails();

                if (!emails.length) {
                    enrolPage.genericErrorModal(enrolPage.mdlstr["novalidemailsentered"],
                                                enrolPage.mdlstr["novalidemailsentered_desc"],
                                                "no-valid-emails-entered");
                    $("#dimmer").css("display", "none");
                } else {

                    // Always clean this div first.
                    $("div.enrol-payment-enrollment").hide();

                    Ajax.call([{
                        methodname: 'enrol_payment_multiple_enrollment',
                        args: {
                            enrolid: enrolPage.instanceid,
                            prepaytoken: enrolPage.prepayToken,
                            emails: JSON.stringify(emails),
                            ipnid: $("#" + enrolPage.gateway + "-custom").val(),
                            symbol: enrolPage.symbol,
                        },
                        done: function(response) {
                            self.handleEmailSubmitAJAXResponse(response, enrolPage);
                        }.bind(this),
                        fail: Notification.exception
                    }]);

                }

            },

            /**
             * Handles a click on the Multiple Registration button and builds
             * the Multiple Registration form
             *
             * @param btn           JQuery object for the button
             */
            buildForm: function(btn, mdlstr, enrolPage) {
                var self = this;

                // If the button is to enable, build the multiple registration form.
                if(!self.enabled) {
                    self.enabled = true;
                    self.nextEmailID = 1;
                    // Build DOM for a multiple-registration form.

                    btn.text(mdlstr["cancelenrolothers"]);
                    btn.removeClass('enable-mr').addClass('disable-mr');
                    $("#multiple-registration-container").html(this.makeEmailEntryLine(mdlstr)).show();
                    self.addPlusClickHandler($(".plus-container"), mdlstr);
                    $("#multiple-registration-btn-container img.iconhelp").css("display", "none");

                } else {
                    $('#dimmer').css('display', 'block');
                    self.enabled = false;

                    // Return to single registration mode.
                    btn.text(mdlstr['enrolothers']);
                    btn.removeClass('disable-mr').addClass('enable-mr');
                    $('.mr-email-line').remove();
                    $('#multiple-registration-btn-container img.iconhelp').css('display', 'inline-block');
                    $("#multiple-registration-container").hide();

                    Ajax.call([{
                        methodname: 'enrol_payment_single_enrollment',
                        args: {
                            enrolid: enrolPage.instanceid,
                            prepaytoken: enrolPage.prepayToken,
                        },
                        done: function(r) {

                            var response = JSON.parse(r);
                            $('#dimmer').css('display', 'none');
                            if(response['success']) {
                                enrolPage.subtotal = response['subtotal'];
                                enrolPage.updateCostView();
                            } else {
                                var trigger = $('#error-modal-trigger');
                                trigger.off();
                                ModalFactory.create({
                                    type: ModalFactory.types.DEFAULT,
                                    body: response['failmessage'],
                                    closebuttontitle: enrolPage.mdlstr['dismiss'],
                                    large: true,
                                }, trigger).done(function(modal) { enrolPage.removeDimmer(modal); });
                                $('#error-modal-trigger').click();
                            }

                        }.bind(this),
                        fail: Notification.exception
                    }]);

                }
            },
        },

        Discount: {
            checkDiscountCode: function(enrolPage) {
                var discountCode = $("#discountcode").val();

                // Always clean this div first.
                $("span.discount-threshold-info").hide();
                $("div.enrol-payment-error-text").hide();

                Ajax.call([{
                    methodname: 'enrol_payment_check_discount',
                    args: {
                        enrolid: enrolPage.instanceid,
                        prepaytoken: enrolPage.prepayToken,
                        discountcode: discountCode,
                    },
                    done: function(response) {
                        var obj = JSON.parse(response);

                        if (obj.error) {
                            enrolPage.errorFromDiscount(obj.errormsg);
                        } else {

                            // Hide the discount-container.
                            $('#discount-container').hide();

                            enrolPage.subtotal = obj.subtotal;
                            enrolPage.updateCostView();
                            $('.discount-threshold-info').css('display','block');
                        }

                    }.bind(this),
                    fail: Notification.exception
                }]);
            },
        },

        checkoutFinal: function() {
            this.updateCostView();
            if(this.gateway === "paypal") {
                $("#paypal-form input[name=amount]").val(this.subtotal);
                $("#paypal-form input[name=tax]").val(this.taxAmount);
                $("#paypal-form").submit();
            } else if(this.gateway === "stripe") {
                $("#stripe-form input[name=amount]").val(this.getTaxedAmount());
                $("#stripe-form input[name=tax]").val(this.taxAmount);
                this.stripeCheckout();
            } else {
                throw new Error(this.mdlstr["invalidgateway"]);
            }
        },

        getTaxedAmount: function() {
            // return (parseFloat(this.subtotal) + parseFloat(this.taxAmount)).toFixed(2);
            return (Number(this.subtotal) + Number(this.taxAmount)).toFixed(2);
        },

        errorFromDiscount: function(message) {
            $("span.discount-threshold-info").hide();
            $("div.enrol-payment-error-text").show();
            $("div.enrol-payment-error-text").text(message);
        },

        errorFromEnrollment: function(message) {
            $("div.enrol-payment-enrollment").show();
            $("div.enrol-payment-enrollment").html(message);
        },

        updateCostView: function() {
            this.taxAmount = (this.subtotal * this.taxPercent).toFixed(2);
            $("span.localisedcost-untaxed").text(this.subtotal);
            $("span.localisedcost").text(this.getTaxedAmount());
            $("span.subtotal-display").text(this.getTaxedAmount());

            // Hide part of the string with the span in the string lang file if the tax is 0.
            // Otherwise, display the whole string.
            if (Number(this.taxAmount).toFixed(0) > 0) {
                $("span.taxamountstring").text(Number(this.taxAmount).toFixed(2));
            } else {
                $("span.tax-container").hide();
            }

            $("span.taxamountstring").text(this.taxAmount);
            $("span#banktransfer-cost").text(this.symbol + this.getTaxedAmount());
        },

        stripeCheckout: function() {
            var self = this;

            $.getScript("https://checkout.stripe.com/checkout.js", function() {

                // StripeCheckout is now globally available, but we will only
                // use it here. Since this javascript code is called from PHP,
                // we can't load in checkout.js from the HTML, so we have to
                // do it here.
                var stripeHandler = StripeCheckout.configure({
                    key: self.stripePublishableKey,
                    image: self.stripeLogo || 'https://stripe.com/img/documentation/checkout/marketplace.png',
                    locale: 'auto',
                    billingAddress: self.billingAddressRequired,
                    shippingAddress: self.shippingAddressRequired,
                    email: self.userEmail || null,
                    allowRememberMe: false,
                    token: function(token) {
                        $('#stripe-form')
                            .append('<input type="hidden" name="stripeToken" value="' + token.id + '" />')
                            .append('<input type="hidden" name="stripeTokenType" value="' + token.type + '" />')
                            .append('<input type="hidden" name="stripeEmail" value="' + token.email + '" />')
                            .submit();
                    }
                });

                stripeHandler.open({
                    name: decodeURIComponent(self.courseFullName),
                    description: self.mdlstr["totalenrolmentfee"] + " " + self.symbol + self.getTaxedAmount() + " " + self.currency,
                    zipCode: self.validateZipCode ? "true" : "false",
                    // Stripe amount is in pennies.
                    amount: Math.floor(Number(self.getTaxedAmount()) * 100),
                    currency: self.currency,
                    closed: function() {
                        // Empty.
                    }
                });

            }).fail(function() {
                throw new Error("Could not load Stripe checkout library.");
            });
        },

        /**
         * Create a generic error popup.
         */
        genericErrorModal: function(titleString, errorString, id) {
            var self = this;
            if ($("#" + id).length == 0) {
                $("#moodle-modals").append('<a id="' + id + '"></a>');
            }
            var trigger = $("#moodle-modals #" + id);
            trigger.off();

            ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: titleString,
                body: errorString,
            }, trigger).done(function(modal) {
                self.removeDimmer(modal);
            });

            trigger.click();
        },

        /**
         * Attach events to Moodle modal box to destroy dimmer.
         */
        removeDimmer: function(modal) {
            modal.getRoot().on(ModalEvents.destroyed, function() {
                $("#dimmer").css('display','none');
            });
            modal.getRoot().on(ModalEvents.cancel, function() {
                $("#dimmer").css('display','none');
            });
            modal.getRoot().on(ModalEvents.hidden, function() {
                $("#dimmer").css('display','none');
            });
        },

        initClickHandlers: function() {
            var self = this;

            $("#apply-discount").click(function(event) {
                event.preventDefault();
                self.Discount.checkDiscountCode(self);
            });

            $("#multiple-registration-btn").click(function() {
                self.MultipleRegistration.buildForm($(this), self.mdlstr, self);
            });

            $(".payment-checkout").click(function(e) {
                $('#dimmer').css('display', 'block');
                e.preventDefault();
                if (e.target.id === "paypal-button") {
                    self.gateway = "paypal";
                } else if (e.target.id === "stripe-button") {
                    self.gateway = "stripe";
                }

                if(self.MultipleRegistration.enabled) {
                    self.MultipleRegistration.verifyAndSubmit(self);
                } else {
                    self.checkoutFinal();
                }
            });
        },

        /**
         * Process a list of enrol_payment language strings into a list index
         * by string name.
         *
         * Then in the callback, we can do e.g.
         * function(strs) { console.log(strs["enrolothers"]); }
         * to write the "enrolothers" language string.
         */
        loadStrings: function(keys, callback) {
            var strs = [];
            for(var i = 0; i < keys.length; i++) {
                strs.push({ key : keys[i], component : "enrol_payment" });
            }
            var str_promise = MoodleStrings.get_strings(strs);
            str_promise.done(function(strs) {
                var ret = [];
                for(var i = 0; i < keys.length; i++) {
                    ret[keys[i]] = strs[i];
                }
                callback(ret);
            });
        },

        /**
         * Get currency symbol.
         */
        init: function(
            instanceid,
            stripePublishableKey,
            cost,
            prepayToken,
            courseFullName,
            shippingAddressRequired,
            stripeLogo,
            taxPercent,
            subtotal,
            validateZipCode,
            billingAddressRequired,
            userEmail,
            currency,
            symbol,
            discountCodeRequired,
            discountThreshold,
            units) {

            var self = this;
            var stringKeys = [
                "discounttypeerror",
                "discountamounterror",
                "invalidgateway",
                "errcommunicating",
                "addaregistrant",
                "removearegistrant",
                "enrolothers",
                "cancelenrolothers",
                "confirmpurchase",
                "continue",
                "dismiss",
                "invalidpaymentprovider",
                "novalidemailsentered",
                "novalidemailsentered_desc",
                "totalenrolmentfee",
                "error",
                "incorrectdiscountcode",
                "incorrectdiscountcode_desc",
                "registrant",
                "enteremail",
            ];
            self.loadStrings(stringKeys, function(strs) {
                self.mdlstr = strs;
                self.originalCost = cost;
                self.taxPercent = taxPercent;
                self.subtotal = subtotal;
                self.taxAmount = taxPercent * cost;
                self.instanceid = instanceid;
                self.stripePublishableKey = stripePublishableKey;
                self.courseFullName = courseFullName;
                self.shippingAddressRequired = shippingAddressRequired == 1 ? true : false;
                self.prepayToken = prepayToken;
                self.stripeLogo = stripeLogo;
                self.validateZipCode = validateZipCode == 1 ? true : false;
                self.billingAddressRequired = billingAddressRequired == 1 ? true : false;
                self.userEmail = userEmail;
                self.currency = currency;
                self.symbol = symbol;
                self.discountCodeRequired = discountCodeRequired == 1 ? true : false;
                self.discountThreshold = discountThreshold;
                self.units = units;

                self.initClickHandlers();
                self.updateCostView();

            });
        }
    };

    return EnrolPage;

});
