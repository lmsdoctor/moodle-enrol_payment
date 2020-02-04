@enrol @enrol_payment
Feature: User has discount when purchasing a course
  In order for the user to purchase a course with discount
  As an authenticated user
  I must access to the course and see the pricing and the button

    Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | John      | Doe      | student1@example.com |

    And the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1        | 0        | 1         |

    And the following config values are set as admin:
      | paypalbusiness  | user@mail.com | enrol_payment |
      | cost            | 100           | enrol_payment |
      | enablediscounts | 1             | enrol_payment |

    When I log in as "admin"
    And I navigate to "Plugins > Enrolments > Manage enrol plugins" in site administration
    And I click on "Enable" "link" in the "Payment" "table_row"
    And I am on course index
    And I am on "Course 1" course homepage

    And I add "Payment" enrolment method with:
      | Custom instance name  | Discount course 1  |
      | Enrol cost            | 200                |
      | Currency              | US Dollar          |
      | Percentage discount   | 1                  |
      | Discount amount       | 25                 |
      | Require discount code | 1                  |
      | Discount code         | discount           |

    And I am on "Course 1" course homepage
    And I log out

    @javascript
    Scenario: Add the correct discount code to apply the discount
    When I log in as "student1"
    Then I am on "Course 1" course homepage
    And I should see "The fee for Course 1"
    And I should see "is $200.00 USD"
    And I set the following fields to these values:
    | discountcode | discount |
    And I click on "Apply discount" "button"
    And I should see "150"
    And I should see "Send payment via PayPal"
