@enrol @enrol_payment @javascript
Feature: User has a simple discount when purchasing a course
  In order for the user to purchase a course with discount
  As an authenticated user
  I must see the discounted amount in the course home page

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
      | Enrol cost            | 200           |
      | Currency              | US Dollar     |
      | Percentage discount   | 1             |
      | Discount amount       | 25            |

    And I am on "Course 1" course homepage
    And I log out

    Scenario: See the discounted amount
    When I log in as "student1"
    Then I am on "Course 1" course homepage
    And I should see "The fee for Course 1"
    And I should see "is $150.00 USD"
    And I should see "The fee is now"
    And I should see "Send payment via PayPal"
