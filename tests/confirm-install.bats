#!/usr/bin/env bats

#
# confirm-install.bats
#
# Ensure that Terminus and the Composer plugin have been installed correctly
#

@test "confirm terminus version" {
  terminus --version
}

@test "get help on plugin command" {
  run terminus help site:upstream:test
  [[ $output == *"Push upstream updates from a specific branch to a multi-dev"* ]]
  [ "$status" -eq 0 ]
}