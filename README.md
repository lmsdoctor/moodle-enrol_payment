# enrol_payment
This Moodle plugin is a superset of Moodle's **enrol\_paypal** functionality. Please note that a public URL is needed.

## Features
- Uses Stripe or PayPal payment backend
- Allows site-wide or course-level custom welcome message
- Allows group enrolment
- Defines discount codes with percentage-based or amount-based discounts
as well as set a threshold for volume discounts (see **Discounts**, below)
- Allows a user to enrol other users (see **Multiple Enrolment**, below)
- Defines custom tax rules (see **Country tax rate** or **Adding a Province/State Field**, below)
- Allows other products than enrolments to be processes via the PayPal account (e.g., videos, books, etc)

## Discounts
To set up a discount code:
- Ensure that "Allow course enrolment to include a discount" is checked in the site-wide plugin settings.
- In the settings for an individual enrolment instance, select a discount type and enter the corresponding
discount amount and discount code.
- Default Discount threshold is 1. For volume discount, set Discount threshold value to 2 or more.
- To enable automatic application of discount when the number of registrations meets the discount threshold value,
ensure that "Require discount code" is unchecked.

## Multiple Enrolment
To enable the Multiple Enrolment feature:
- In the site-wide plugin settings, check "Allow multiple registration".
- In the settings for an individual enrolment instance, check "Allow multiple registration".

This will allow a user to enrol other user(s) in the course, provided their email addressses are in the database.

## Adding a Country Tax Rate
Country tax rate allows you to define a tax for an specific country. To set this up:
- Go to Site Administration > Plugins > Enrolments > Payment
- Check the "Allow custom tax definitions"
- Set the "Country tax rate", example: CO : 0.19 or BR : 0.15 or AR : 0.09

## Adding a Province/State Field
Since Moodle does not natively collect province/state info, a user
profile field needs to be created to calculate the tax user information.

### STEP 1: Configure the regional tax rates
- Go to Site Administration > Plugins > Enrolments > Payment
- Check "Allow custom tax definitions"
- Add the list of regions into the **Regional tax rates** field, one per line.

The format for each entry is Region : **0.##** for tax rate. For instance, assume there are only two taxable provinces: Ontario (rate 13%) and Quebec (rate 5%), the entries would be:

**Ontario : 0.13
Quebec : 0.05**

Enter each tax definition on a separate line

### STEP 2: Create a user profile field
- Go to Site Administration > Users > User profile fields
- Create a new profile field: **Drop-down menu**
- Short name: **taxregion** (do not change this value)
- Name: Can be Province, State, Region or any term that is pertinent to your location
- Menu options: Add your regions one per line (**only the region name**)
- Is this field required? Yes if you want to force taxes when users purchase a course
- Display on signup page? Yes if you allow self-registration

### Extra: Move your taxregion custom field below the city in the regular Moodle signup form
By default, user profile fields are displayed under a category at the end of the regular Moodle sign up form. To display
 the Province, State or Region (the name you called it) between the City and the Country fields follow the instructions below:

- Go to Site Administration > Appearance > Additional HTML
- Paste the piece of code below in **Before BODY is closed** text field.

```
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script>
  $('#fitem_id_profile_field_taxregion').insertAfter('#fitem_id_city');
</script>
```
- Go to Site Administration > Development > Purge caches and click on **Purge all caches**

The code above will take the taxregion user profile field and place it after the City field in the signup form.

If there are no either user profile fields inside the category and it is the **default profile category** the following line code hides the category (as it is not needed). Add the following line above the **</script>** closing tag.

```
$('#id_category_1').hide();
```

If the taxregion user profile field was created in another category than the default one, then change the number "1" for "2" and so on. For example:

Default category:

```
$('#id_category_1').hide();
```

New user defined category: 

```
$('#id_category_2').hide();
```

### For custom signup form
If the script doesn't work, it could be that taxregion and city id in your custom signup form is not the same as the regular Moodle sign up form. To identify the city field id and the taxregion field id within the custom signup form, use the browser inspect element tool to find the correct id. See an example in the following screenshot: https://prnt.sc/r1diam

Assuming the city id reads as: id="custom-city-id" and the taxregion id reads as: id="custom-taxregion-id" then the code should be tweak as follows:

```
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script>
  $('#custom-taxregion-id').insertAfter('#custom-city-id');
</script>
```

