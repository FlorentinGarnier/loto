Feature: Behat wiring
  In order to verify Behat is correctly configured
  As a developer
  I want a trivial scenario that always passes when contexts load

  Scenario: Behat bootstraps
    Then Behat is set up
