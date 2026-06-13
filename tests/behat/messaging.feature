# Behat coverage for the mod_coursemail mailbox UI.
#
# NOTE: these scenarios are @javascript (the mailbox is an AMD single-page app) and
# require a JavaScript-capable Behat environment (Selenium/chromedriver). They were
# authored against the template selectors/labels but have not been executed in the
# development environment, so they may need minor adjustments on first run.

@mod @mod_coursemail @javascript
Feature: Send, read and reply to course mailbox messages
  In order to communicate inside a course
  As a teacher and a student
  I need to send, read and reply to messages in the course mailbox

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity   | name    | course | idnumber |
      | coursemail | Mailbox | C1     | cmail1   |

  Scenario: A teacher sends a message and the student reads and replies to it
    When I am on the "Mailbox" "coursemail activity" page logged in as "teacher1"
    And I press "Compose"
    And I set the field "Select recipients" to "Student One"
    And I set the field "Subject" to "Greetings"
    And I set the field "Message" to "Welcome to the course"
    And I press "Send"
    Then I should see "Greetings"
    When I am on the "Mailbox" "coursemail activity" page logged in as "student1"
    Then I should see "Greetings"
    And I should see "Teacher One"
    When I click on "Greetings" "button"
    Then I should see "Welcome to the course"
    When I set the field "Reply" to "Thank you"
    And I press "Reply"
    Then I should see "Thank you"
    When I am on the "Mailbox" "coursemail activity" page logged in as "teacher1"
    And I click on "Inbox" "button"
    And I click on "Greetings" "button"
    Then I should see "Thank you"

  Scenario: Sending a message without a subject is rejected
    When I am on the "Mailbox" "coursemail activity" page logged in as "teacher1"
    And I press "Compose"
    And I set the field "Select recipients" to "Student One"
    And I set the field "Message" to "Body without a subject"
    And I press "Send"
    Then I should see "Please enter a subject"

  Scenario: A draft is saved and listed in the Drafts folder
    When I am on the "Mailbox" "coursemail activity" page logged in as "teacher1"
    And I press "Compose"
    And I set the field "Select recipients" to "Student One"
    And I set the field "Subject" to "Work in progress"
    And I set the field "Message" to "To be finished later"
    And I press "Save draft"
    And I click on "Drafts" "button"
    Then I should see "Work in progress"

  Scenario: A student with a single teacher gets a direct message-to-teacher shortcut
    When I am on the "Mailbox" "coursemail activity" page logged in as "student1"
    Then I should see "Send message to teacher"
    When I press "Send message to teacher"
    Then I should see "To: Teacher One"
    And I should not see "Conversations require a response by default"
    When I set the field "Subject" to "A doubt"
    And I set the field "Message" to "I have a question"
    And I click on "Send" "button" in the "[data-region=compose]" "css_element"
    Then I should see "A doubt"
    When I am on the "Mailbox" "coursemail activity" page logged in as "teacher1"
    Then I should see "A doubt"

  Scenario: A student with several teachers can target selected teachers
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher2 | Teacher   | Two      | teacher2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher2 | C1     | editingteacher |
    When I am on the "Mailbox" "coursemail activity" page logged in as "student1"
    And I press "Compose"
    Then I should see "All teachers"
    And I should see "Selected teachers"
    And I should not see "Conversations require a response by default"
    When I click on "Selected teachers" "radio"
    And I set the field "Select recipients" to "Teacher Two"
    And I set the field "Subject" to "Only for Two"
    And I set the field "Message" to "Hello Teacher Two"
    And I press "Send"
    Then I should see "Only for Two"
    When I am on the "Mailbox" "coursemail activity" page logged in as "teacher2"
    Then I should see "Only for Two"
    When I am on the "Mailbox" "coursemail activity" page logged in as "teacher1"
    Then I should not see "Only for Two"

  # Regression: sending used to crash with an err_system completion error when the
  # activity used manual (not automatic) completion tracking. The send must succeed
  # and the recipients must get the message.
  Scenario: A teacher sends to the whole class when completion is manual
    Given the following "courses" exist:
      | fullname | shortname | enablecompletion |
      | Course M | CM        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | CM     | editingteacher |
      | student1 | CM     | student        |
    And the following "activities" exist:
      | activity   | name       | course | idnumber | completion |
      | coursemail | ManualMail | CM     | cmailm   | 1          |
    When I am on the "ManualMail" "coursemail activity" page logged in as "teacher1"
    And I press "Compose"
    And I click on "Everyone in the course" "radio"
    And I set the field "Subject" to "Hello class"
    And I set the field "Message" to "Welcome everyone"
    And I press "Send"
    Then I should see "Hello class"
    When I am on the "ManualMail" "coursemail activity" page logged in as "student1"
    Then I should see "Hello class"
    And I should see "Welcome everyone"

  Scenario: A student can mark a read message as unread again
    When I am on the "Mailbox" "coursemail activity" page logged in as "teacher1"
    And I press "Compose"
    And I set the field "Select recipients" to "Student One"
    And I set the field "Subject" to "Read me"
    And I set the field "Message" to "Important info"
    And I press "Send"
    When I am on the "Mailbox" "coursemail activity" page logged in as "student1"
    And I click on "Read me" "button"
    Then I should see "Important info"
    And I should see "Mark as unread"
    When I press "Mark as unread"
    Then ".coursemail-item.font-weight-bold" "css_element" should exist
    And I should see "Read me"

  Scenario: The folder navigation can be collapsed and expanded
    When I am on the "Mailbox" "coursemail activity" page logged in as "teacher1"
    Then ".coursemail-nav-collapsed" "css_element" should not exist
    When I click on "[data-action=toggle-nav]" "css_element"
    Then ".coursemail-nav-collapsed" "css_element" should exist
    When I click on "[data-action=toggle-nav]" "css_element"
    Then ".coursemail-nav-collapsed" "css_element" should not exist
