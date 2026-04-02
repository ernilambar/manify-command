Feature: Basic behavior

  Scenario: Command is registered
    Given an empty directory
    When I run `wp manify --help`
    Then STDOUT should contain:
      """
      wp manify
      """
