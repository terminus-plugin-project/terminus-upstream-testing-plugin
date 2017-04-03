#!/usr/bin/env bats

#
# plugin-tests.bats
# Contains all sorts of tests for individual flags.
#

SITENAME="terminus-upstream-testing-plugin";

setup(){
  terminus site:create $SITENAME "Plugin Test" 21e1fada-199c-492b-97bd-0b36b53a9da0 >&2
  terminus drush $SITENAME.dev -- site-install pantheon --account-name=admin --account-pass=admin123 --site-name="Plugin Test"
  [[ `terminus site:info $SITENAME --field="name"` == "${SITENAME}" ]]
}

createtest(){
  terminus env:deploy $SITENAME.test --note="Deploying From Dev" >&2
}

createlive(){
  terminus env:deploy $SITENAME.live --note="Deploying From Test" >&2
}

createupstream(){
  terminus env:create $SITENAME.dev upstream >&2
}

createtesting(){
  terminus env:create $SITENAME.dev testing >&2
}

teardown(){
  terminus site:delete $SITENAME -y >&2
}

@test "run default command" {
  run terminus site:upstream:test $SITENAME
  [ "$status" -eq 0 ]
  [[ `terminus env:info $SITENAME.upstream --field="id"` == 'upstream' ]]
}

@test "run command with flags: --repo" {
  run terminus site:upstream:test $SITENAME --repo="git@github.com:sean-e-dietrich/drops-7.git"
  [ "$status" -eq 0 ]
  [[ `terminus env:info $SITENAME.upstream --field="id"` == 'upstream' ]]
}

@test "run command with flags: --branch" {
  run terminus site:upstream:test $SITENAME --branch="pantheon_apachesolr_searchapi_class"
  [ "$status" -eq 0 ]
  [[ `terminus env:info $SITENAME.upstream --field="id"` == 'upstream' ]]
}

@test "run command with flags: --env" {
  run terminus site:upstream:test $SITENAME --env="testing"
  [ "$status" -eq 0 ]
  [[ `terminus env:info $SITENAME.testing --field="id"` == 'testing' ]]
}

@test "run command with flags: --message" {
  MESSAGE="Pulling updates for testing"
  run terminus site:upstream:test $SITENAME --message="${MESSAGE}"
  [ "$status" -eq 0 ]
  [[ `terminus env:info $SITENAME.upstream --field="id"` == 'upstream' ]]
  [[ `terminus env:code-log mothership.test-123 | grep "${MESSAGE}" | wc -l` -eq 0 ]]
}

@test "run command with flags: --teardown" {
  run terminus site:upstream:test $SITENAME --teardown
  [ "$status" -eq 0 ]
  [[ `terminus env:info $SITENAME.upstream --field="id"` == 'upstream' ]]
}

@test "run command with flags: --rebuild" {
  createupstream
  run terminus site:upstream:test $SITENAME --rebuild
  [ "$status" -eq 0 ]
  [[ `terminus env:info $SITENAME.upstream --field="id"` == 'upstream' ]]
}

@test "run command with flags: --username" {
  run terminus site:upstream:test $SITENAME --username="globaladmin"
  [ "$status" -eq 0 ]
  [[ `terminus env:info $SITENAME.upstream --field="id"` == 'upstream' ]]
  [[ `terminus drush $SITENAME -- sql-query "SELECT username FROM users WHERE uid=1"` == "globaladmin" ]]
}

@test "run command with flags: --password" {
  run terminus site:upstream:test $SITENAME --password="globaladmin"
  [ "$status" -eq 0 ]
  [[ `terminus env:info $SITENAME.upstream --field="id"` == 'upstream' ]]
  [[ `terminus drush $SITENAME -- sql-query "SELECT username FROM users WHERE uid=1"` == "globaladmin" ]]
}