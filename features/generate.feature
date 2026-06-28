Feature: Documentation generation

  Scenario: Generates a file with a heading per public method
    Given an empty directory
    And a composer.json file:
      """
      {
        "name": "test/my-plugin",
        "extra": {
          "wp-cli-commands": {
            "my-plugin": {
              "class": "My_Command",
              "file": "my-command.php"
            }
          }
        }
      }
      """
    And a my-command.php file:
      """
      <?php
      class My_Command {
          /**
           * List items.
           */
          public function list( $args, $assoc_args ) {}

          /**
           * Create item.
           */
          public function create( $args, $assoc_args ) {}
      }
      """
    When I run `wp manify generate`
    Then the docs/my-plugin.md file should exist
    And the docs/my-plugin.md file should contain:
      """
      # wp my-plugin list
      """
    And the docs/my-plugin.md file should contain:
      """
      # wp my-plugin create
      """

  Scenario: Method name underscores are converted to hyphens in heading
    Given an empty directory
    And a composer.json file:
      """
      {
        "name": "test/my-plugin",
        "extra": {
          "wp-cli-commands": {
            "my-plugin": {
              "class": "My_Command",
              "file": "my-command.php"
            }
          }
        }
      }
      """
    And a my-command.php file:
      """
      <?php
      class My_Command {
          /**
           * List all items.
           */
          public function list_items( $args, $assoc_args ) {}
      }
      """
    When I run `wp manify generate`
    Then the docs/my-plugin.md file should contain:
      """
      # wp my-plugin list-items
      """

  Scenario: @subcommand annotation overrides method name in heading
    Given an empty directory
    And a composer.json file:
      """
      {
        "name": "test/my-plugin",
        "extra": {
          "wp-cli-commands": {
            "my-plugin": {
              "class": "My_Command",
              "file": "my-command.php"
            }
          }
        }
      }
      """
    And a my-command.php file:
      """
      <?php
      class My_Command {
          /**
           * List items.
           *
           * @subcommand list
           */
          public function list_items( $args, $assoc_args ) {}
      }
      """
    When I run `wp manify generate`
    Then the docs/my-plugin.md file should contain:
      """
      # wp my-plugin list
      """
    And the docs/my-plugin.md file should not contain:
      """
      list-items
      """

  Scenario: Single-method config omits subcommand from heading
    Given an empty directory
    And a composer.json file:
      """
      {
        "name": "test/my-plugin",
        "extra": {
          "wp-cli-commands": {
            "my-plugin run": {
              "class": "My_Command",
              "file": "my-command.php",
              "method": "run"
            }
          }
        }
      }
      """
    And a my-command.php file:
      """
      <?php
      class My_Command {
          /**
           * Run the command.
           */
          public function run( $args, $assoc_args ) {}
      }
      """
    When I run `wp manify generate`
    Then the docs/my-plugin-run.md file should contain:
      """
      # wp my-plugin run
      """

  Scenario: Invokable class heading has no subcommand suffix
    Given an empty directory
    And a composer.json file:
      """
      {
        "name": "test/my-plugin",
        "extra": {
          "wp-cli-commands": {
            "my-plugin": {
              "class": "My_Invokable_Command",
              "file": "my-command.php"
            }
          }
        }
      }
      """
    And a my-command.php file:
      """
      <?php
      class My_Invokable_Command {
          /**
           * Run the command.
           */
          public function __invoke( $args, $assoc_args ) {}
      }
      """
    When I run `wp manify generate`
    Then the docs/my-plugin.md file should contain:
      """
      # wp my-plugin
      """
    And the docs/my-plugin.md file should not contain:
      """
      # wp my-plugin __invoke
      """

  Scenario: Methods without a docblock are skipped
    Given an empty directory
    And a composer.json file:
      """
      {
        "name": "test/my-plugin",
        "extra": {
          "wp-cli-commands": {
            "my-plugin": {
              "class": "My_Command",
              "file": "my-command.php"
            }
          }
        }
      }
      """
    And a my-command.php file:
      """
      <?php
      class My_Command {
          /**
           * Documented method.
           */
          public function documented( $args, $assoc_args ) {}

          public function undocumented( $args, $assoc_args ) {}
      }
      """
    When I run `wp manify generate`
    Then the docs/my-plugin.md file should contain:
      """
      # wp my-plugin documented
      """
    And the docs/my-plugin.md file should not contain:
      """
      # wp my-plugin undocumented
      """

  Scenario: Warning when class has no documented methods
    Given an empty directory
    And a composer.json file:
      """
      {
        "name": "test/my-plugin",
        "extra": {
          "wp-cli-commands": {
            "my-plugin": {
              "class": "My_Empty_Command",
              "file": "my-command.php"
            }
          }
        }
      }
      """
    And a my-command.php file:
      """
      <?php
      class My_Empty_Command {
          public function run( $args, $assoc_args ) {}
      }
      """
    When I try `wp manify generate`
    Then STDERR should contain:
      """
      No documented methods on My_Empty_Command
      """
    And the docs/my-plugin.md file should not exist

  Scenario: Custom destination writes file to specified folder
    Given an empty directory
    And a composer.json file:
      """
      {
        "name": "test/my-plugin",
        "extra": {
          "wp-cli-commands": {
            "my-plugin": {
              "class": "My_Command",
              "file": "my-command.php"
            }
          }
        }
      }
      """
    And a my-command.php file:
      """
      <?php
      class My_Command {
          /**
           * List items.
           */
          public function list( $args, $assoc_args ) {}
      }
      """
    When I run `wp manify generate --destination=api-docs`
    Then the api-docs/my-plugin.md file should exist

  Scenario: Dry run prints path without writing file
    Given an empty directory
    And a composer.json file:
      """
      {
        "name": "test/my-plugin",
        "extra": {
          "wp-cli-commands": {
            "my-plugin": {
              "class": "My_Command",
              "file": "my-command.php"
            }
          }
        }
      }
      """
    And a my-command.php file:
      """
      <?php
      class My_Command {
          /**
           * List items.
           */
          public function list( $args, $assoc_args ) {}
      }
      """
    When I run `wp manify generate --dry-run`
    Then STDOUT should contain:
      """
      Would generate: docs/my-plugin.md
      """
    And the docs/my-plugin.md file should not exist

  Scenario: Command slug with spaces produces a sanitized filename
    Given an empty directory
    And a composer.json file:
      """
      {
        "name": "test/my-plugin",
        "extra": {
          "wp-cli-commands": {
            "my-plugin run": {
              "class": "My_Command",
              "file": "my-command.php",
              "method": "run"
            }
          }
        }
      }
      """
    And a my-command.php file:
      """
      <?php
      class My_Command {
          /**
           * Run the command.
           */
          public function run( $args, $assoc_args ) {}
      }
      """
    When I run `wp manify generate`
    Then the docs/my-plugin-run.md file should exist
    And the docs/my-plugin run.md file should not exist

  Scenario: OPTIONS section is rendered verbatim without a code fence
    Given an empty directory
    And a composer.json file:
      """
      {
        "name": "test/my-plugin",
        "extra": {
          "wp-cli-commands": {
            "my-plugin": {
              "class": "My_Command",
              "file": "my-command.php"
            }
          }
        }
      }
      """
    And a my-command.php file:
      """
      <?php
      class My_Command {
          /**
           * List items.
           *
           * ## OPTIONS
           *
           * [--count=<count>]
           * : Max number of items.
           */
          public function list( $args, $assoc_args ) {}
      }
      """
    When I run `wp manify generate`
    Then the docs/my-plugin.md file should contain:
      """
      ## OPTIONS

      [--count=<count>]
      : Max number of items.
      """

  Scenario: EXAMPLES section is rendered inside a code fence
    Given an empty directory
    And a composer.json file:
      """
      {
        "name": "test/my-plugin",
        "extra": {
          "wp-cli-commands": {
            "my-plugin": {
              "class": "My_Command",
              "file": "my-command.php"
            }
          }
        }
      }
      """
    And a my-command.php file:
      """
      <?php
      class My_Command {
          /**
           * List items.
           *
           * ## EXAMPLES
           *
           *     # List all items
           *     $ wp my-plugin list
           */
          public function list( $args, $assoc_args ) {}
      }
      """
    When I run `wp manify generate`
    Then the docs/my-plugin.md file should contain:
      """
      ## EXAMPLES

      ```
      # List all items
      $ wp my-plugin list
      ```
      """
