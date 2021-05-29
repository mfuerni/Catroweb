@web @achievements @perfect_profile
Feature: Every user that changed his profile picture must have the perfect_profile badge

  Scenario: On profile avatar change users should get their first achievement
    Given I run the update achievements command
    And there are users:
      | name    |
      | NewUser |
    And I log in as "NewUser"
    And I am on "/app/achievements"
    And I wait for the page to be loaded
    Then the "#unlocked-achievements" element should not contain "Initiative"
    When I am on "/app/user"
    And I wait for AJAX to finish
    Then I should see "My Profile"
    And I click "#avatar-upload"
    When I attach the avatar "logo.png" to "file"
    And I wait for the element ".text-img-upload-success" to be visible
    When I am on "/app/achievements"
    And I wait for the page to be loaded
    Then the "#unlocked-achievements" element should contain "Initiative"

  Scenario: Old users must get their achievements too, if they should already unlocked it
    And there are users:
      | name    |
      | OldUser |
    And I log in as "OldUser"
    When I am on "/app/user"
    And I wait for AJAX to finish
    Then I should see "My Profile"
    And I click "#avatar-upload"
    When I attach the avatar "logo.png" to "file"
    And I wait for the element ".text-img-upload-success" to be visible
    And I run the update achievements command
    And I am on "/app/achievements"
    And I wait for the page to be loaded
    Then the "#unlocked-achievements" element should not contain "Initiative"
    And I run the special update command
    And I am on "/app/achievements"
    And I wait for the page to be loaded
    Then the "#unlocked-achievements" element should contain "Initiative"

  Scenario: Old users must only get the achievement if they really changed their avatar
    And there are users:
      | name    |
      | OldUser |
    And I log in as "OldUser"
    And I run the update achievements command
    And I am on "/app/achievements"
    And I wait for the page to be loaded
    Then the "#unlocked-achievements" element should not contain "Initiative"
    And I run the special update command
    And I am on "/app/achievements"
    And I wait for the page to be loaded
    Then the "#unlocked-achievements" element should not contain "Initiative"