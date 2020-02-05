# enrol_payment
This Moodle plugin is a superset of Moodle's **enrol\_paypal** functionality. Please note that a public URL is needed.

## Features
- Use Stripe or PayPal payment backend
- Define discount codes with percentage-based or amount-based discounts (see **Discounts**, below)
- Allow users to enrol other users (see **Multiple Enrolment**, below)
- Define custom tax rules (see **Country tax rate** or **Adding a Province/State Field**, below)

## Discounts
To set up a discount code:
- Ensure that "Allow course enrolment to include a discount" is checked in the site-wide plugin settings.
- In the settings for an individual enrolment instance, select a discount type and enter the corresponding 
discount amount and discount code.

## Multiple Enrolment
To enable the Multiple Enrolment system:
- In the site-wide plugin settings, check "Allow multiple registration".
- In the settings for an individual enrolment instance, check "Allow multiple registration".

This will allow users to enroll 1 or more other users in the course, provided the users' email addressses 
are known.

## Adding a Country Tax Rate
Country tax rate allows you to define a tax for an specific country. To set this up, follow
the instructions below:

- Go to Site Administration > Plugins > Enrollments > Payment
- Check the Allow custom tax definitions
- Set the Country tax rate, example: CO : 0.19 or BR : 0.15 or AR : 0.09

## Adding a Province/State Field
Since Moodle does not natively collect province/state info, we can create a
user profile field to calculate the tax user information.

### STEP 1: Configure the regional tax rates
Go to Site **Administration > Plugins > Enrollments > Payment**

1. Check **Allow custom tax definitions**
2. Add the list of regions into the **Regional tax rates** field, one per line.

The format for each entry is Region : **0.##** for tax rate. For instance, assume there are only two taxable provinces: Ontario (rate 13%) and Quebec (rate 5%), the entries would be:

**Ontario : 0.13
Quebec : 0.05**

Enter each tax definition on a separate line

### STEP 2: Create a user profile field
- Go to Site **Administration > Users > User profile fields**
- Create a new profile field: **Drop-down menu**
- Short name: **taxregion**
- Name: Can be Province, State, Region or anything that is more related to your location
- Menu options: Add your regions one per line (**only the region name**)
- Is this field required? Yes if you want to force taxes when users purchase a course
- Display on signup page? Yes if you allow self-registration
