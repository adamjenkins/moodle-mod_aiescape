@mod @mod_aiescape
Feature: View an AI Escape Room activity
  In order to play or review an AI Escape Room
  As a student or teacher
  I need to see the activity page with the right information and controls for my role

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | teacher1 | Teacher   | 1        | teacher1@example.com  |
      | student1 | Student   | 1        | student1@example.com  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity  | course | idnumber  | name              | premise                  | goal             | gamemode    | showpremise | showgoal |
      | aiescape  | C1     | escape1   | Escape Room One   | You wake up in a cave.   | Find the exit.   | multichoice | 1           | 1        |

  Scenario: Student sees the premise and goal and can start the game
    When I am on the "Escape Room One" "aiescape activity" page logged in as student1
    Then I should see "You wake up in a cave."
    And I should see "Find the exit."
    And I should see "Start Game"

  Scenario: Student without permission cannot see the attempts report link
    When I am on the "Escape Room One" "aiescape activity" page logged in as student1
    Then "Attempts report" "link" should not exist

  Scenario: Teacher can open the attempts report for the activity
    When I am on the "Escape Room One" "aiescape activity" page logged in as teacher1
    And I navigate to "Attempts report" in current page administration
    Then I should see "Attempts report"
