@mod @mod_doomed
Feature: Teachers set up Doomed activities and review attempts, students play
  In order to use a Doom-engine game in a course
  As a teacher or a student
  I need to add the activity, open the player and see recorded attempts

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Terry     | Teacher  |
      | student1 | Sam       | Student  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name     | course | idnumber | startmap | grademode | grade |
      | doomed   | Doom one | C1     | doom1    | E1M1     | 2         | 100   |

  Scenario: A teacher adds a Doomed activity to a course
    Given I log in as "teacher1"
    When I add a "doomed" activity to course "Course 1" section "1" and I fill the form with:
      | Name          | Hangar practice                          |
      | Starting map  | E1M1                                     |
      | Grading mode  | Percentage from kills, items and secrets |
    And I am on "Course 1" course homepage
    Then I should see "Hangar practice"

  Scenario: A starting map named in the wrong format for the bundled Freedoom is refused
    Given I log in as "teacher1"
    When I add a "doomed" activity to course "Course 1" section "1" and I fill the form with:
      | Name         | Wrong map |
      | Starting map | MAP01     |
    Then I should see "This game data names its maps like E1M1."
    And I am on "Course 1" course homepage
    And I should not see "Wrong map"

  @javascript
  Scenario: A student opens the activity and the player loads
    When I am on the "Doom one" "doomed activity" page logged in as "student1"
    Then "Start game at E1M1" "button" should exist
    # The status line is empty in the markup; amd/src/player.js sets it once the module has initialised.
    And I should see "Ready." in the "[data-region='status']" "css_element"

  Scenario: A teacher sees a student's recorded attempt in the attempts report, a student cannot
    Given the following Doomed attempts exist:
      | activity | user     | outcome   | map  | skill | kills | totalkills | items | totalitems | secrets | totalsecrets | leveltime | partime |
      | doom1    | student1 | completed | E1M1 | 3     | 20    | 29         | 30    | 49         | 2       | 4            | 192       | 30      |
    When I am on the "Doom one" "doomed activity" page logged in as "teacher1"
    And I navigate to "Attempts report" in current page administration
    Then I should see "Sam Student" in the "region-main" "region"
    And I should see "Attempts: 1"
    And I should see "20 / 29"
    And I should see "3:12"
    And I log out
    And I am on the "Doom one" "doomed activity" page logged in as "student1"
    And "Attempts report" "link" should not exist in current page administration
    And I should not see "Attempts report"
