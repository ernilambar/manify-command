Feature: Error handling

  Scenario: Error when no composer.json found
    Given an empty directory
    When I try `wp manify`
    Then STDERR should contain:
      """
      No composer.json file found
      """
    And the return code should be 1

  Scenario: Error when composer.json contains invalid JSON
    Given an empty directory
    And a composer.json file:
      """
      { not valid json
      """
    When I try `wp manify`
    Then STDERR should contain:
      """
      Invalid JSON in composer.json
      """
    And the return code should be 1

  Scenario: Error when composer.json has no extra section
    Given an empty directory
    And a composer.json file:
      """
      {"name":"test/plugin"}
      """
    When I try `wp manify`
    Then STDERR should contain:
      """
      No "extra" section found
      """
    And the return code should be 1

  Scenario: Error when no wp-cli-commands are configured
    Given an empty directory
    And a composer.json file:
      """
      {"name":"test/plugin","extra":{}}
      """
    When I try `wp manify`
    Then STDERR should contain:
      """
      No WP-CLI commands found
      """
    And the return code should be 1

  Scenario: Warning when command config has no class key
    Given an empty directory
    And a composer.json file:
      """
      {"name":"test/plugin","extra":{"wp-cli-commands":{"my-cmd":{}}}}
      """
    When I try `wp manify`
    Then STDERR should contain:
      """
      No class specified for command 'my-cmd'
      """

  Scenario: Warning when command class cannot be loaded
    Given an empty directory
    And a composer.json file:
      """
      {"name":"test/plugin","extra":{"wp-cli-commands":{"my-cmd":{"class":"Missing_Class","file":"missing.php"}}}}
      """
    When I try `wp manify`
    Then STDERR should contain:
      """
      Class not found: Missing_Class
      """
