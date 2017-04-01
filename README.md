# Terminus Upstream Testing

[![Terminus v1.x Compatible](https://img.shields.io/badge/terminus-v1.x-green.svg)](https://github.com/terminus-plugin-project/terminus-upstream-testing/tree/1.x)

| Option                | Description    |
| --------------------- | -------------- |
| --repo                | Repository to use and pull code from. When omitted this will use the sites upstream |
| --branch              | Branch from the repository to use. |
| --env                 | Environment to use for testing |
| --copy                | Environment to copy database and files from |
| --teardown            | Tear down the environment before applying any changes |
| --rebuild             | Rebuild the database and files |
| --username            | Change user 1's username |
| --password            | Change user 1's password |

## Slack Integration

Integration with [Slack](https://slack.com) using [incoming webhooks](https://my.slack.com/services/new/incoming-webhook)

| Option                | Description |
| --------------------- | ----------- |
| --slack_url           | Slack URL to post to |
| --slack_channel       | Slack Channel to post message to. Should be prefixed with \# |
| --slack_message       | Message to post to slack. Following variables are available {env} {site_id} {site_name} {url}|
| --slack_username      | Username to push through as slack message |
| --slack_icon          | Icon to use for slack message |

## Examples
### Default Running
The following will apply all updates from the master branch of the site's upstream to the upstream multi-dev 
```
$ terminus site:upstream:test companysite-33
```

### Separate Upstream
If the `--repo` argument is omitted the plugin with default to the site's upstream git repository. Otherwise a git
url could be used to pull from a completely different repo.
```
$ terminus site:upstream:test companysite-33 --repo="https://github.com/pantheon-systems/drops-7.git"
```

### Specifying the environment to copy database and files
The `--copy` argument will allow you to specify the environment to bring the database and files from. This environment
must already be initialized. Otherwise, the live/test/dev environments will be used respectively  
```
$ terminus site:upstream:test companysite-33 --copy="dev"
```

### Upstream Branch
To specify a particular branch that code would live in the `--branch` argument could be used. That branch will need to exist
within the upstream or `--repo` repository.
```
$ terminus site:upstream:test companysite-33 --branch="v1.3.3"
```

### Rebuild
To rebuild or copy the database and files from a particular environment use the `--rebuild` flag as it will dictate
that you want to completely pull new content. In conjunction with `--copy` these could be helpful in automation if
wanting to specify the environment that data should be rebuilt from.
```
$ terminus site:upstream:test companysite-33 --rebuild
```

### Reset User 1 Username and Password
The following will reset the username and password of the user 1 account. This is helpful if you would like a standard
account username and password for any other sort of automated testing like behat. Each argument may be used independently
and do not need to be combined. 

#### Reset Username
```
$ terminus site:upstream:test companysite-33 --username="admin"
```

#### Reset Password
```
$ terminus site:upstream:test companysite-33 --password="admin"
```

### Slack Notification
There is an ability to have a slack message sent to a particular channel to notify when an environment has been updated.
This is particularly useful if and when you are using this plugin for any sort of Continuous Integration work.
```
$ terminus site:upstream.test companysite-33 --slack-url="[REDACTED]" --slack-channel="#general"
```

## Installation
For help installing, see [Manage Plugins](https://pantheon.io/docs/terminus/plugins/)
```
mkdir -p ~/.terminus/plugins
composer create-project -d ~/.terminus/plugins terminus-plugin-project/terminus-upstream-testing:~1
```

## Install Notes
Occasionally you may get this issue:
```
[Pantheon\Terminus\Exceptions\TerminusException]
  The plugin terminus-plugin-project/terminus-upstream-testing-plugin has installed the project guzzlehttp/psr7: 1.4.2, but Terminus has installed guzzlehttp/psr7: 1.4.1. To resolve this, try running 'com
  poser update' in both the plugin directory, and the terminus directory.
```

The way to solve this will be to go to your terminus project and run `composer update`. If you globally require it you
will need to go directly into terminus it's self as the lock file within the terminus may contain an older version of guzzle/psr7.

## Help
Run `terminus list site:upstream:test` for a complete list of available commands. Use `terminus help <command>` to get help on one command.
