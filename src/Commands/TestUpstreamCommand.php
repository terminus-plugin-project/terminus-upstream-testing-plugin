<?php

namespace Pantheon\TerminusUpstreamDev\Commands;

use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;
use Pantheon\Terminus\Exceptions\TerminusException;
use Maknz\Slack\Client;

/**
 * Class TestUpstreamCommand
 */
class TestUpstreamCommand extends TerminusCommand implements SiteAwareInterface
{
    use SiteAwareTrait;

  /** @var \Pantheon\Terminus\Models\Site $site */
    protected $site;

  /**
   * Push upstream updates from a specific branch to a multi-dev
   *
   * @authorize
   *
   * @command site:upstream:test
   *
   * @param string $site_id Site in the format `site-name`
   * @option string $env Environment ID to pull tests into (optional) (Default: upstream)
   * @option string $copy Environment ID to copy db and files from (optional)
   *  (Defaults to highest initialized environment live/test/dev respectively)
   * @option boolean $teardown Delete the env and branch before starting (optional)
   * @option boolean $rebuild Clone database and files from $copy environment (optional)
   * @option string $repo Git Repository to pull code from (optional) (Default: Site upstream repo)
   * @option string $branch Branch from $repo to pull code from (optional) (Default: master)
   * @option string $message Message to append to commits (optional)
   * @option string $username Username to reset user 1 to (optional)
   * @option string $password Password to reset user 1 to (optional)
   * @option string $slack_url Slack URL to post to (optional)
   * @option string $slack_channel Slack channel to post to (optional)
   * @option string $slack_message Slack Message to post (optional) (Default: #general)
   * @option string $slack_username Slack User to post as (optional)
   * @option string $slack_icon Slack Icon to user for user (optional)
   *
   * @usage terminus site:upstream:test <site_id>
   * @usage terminus site:upstream:test <site_id> --env="<env>"
   * @usage terminus site:upstream:test <site_id> --slack_url="<url>" --slack_channel="<channel>"
   * @usage terminus site:upstream:test <site_id> --repo="<url>" --branch="<branch>"
   */
    public function testUpdate($site_id, $options = [
    'repo' => null,
    'env' => 'upstream',
    'copy' => null,
    'teardown' => false,
    'branch' => 'master',
    'message' => '',
    'rebuild' => false,
    'username' => null,
    'password' => null,
    'slack_message' => null,
    'slack_url' => null,
    'slack_channel' => null,
    'slack_username' => 'Pantheon',
    'slack_icon' => ':computer:'
    ])
    {
        $this->site = $this->sites->get($site_id);
        $settings = $this->site->get('settings');
        $data = $this->site->serialize();

        $site_name = $data['label'];

        $repo = $options['repo'];
        $branch = $options['branch'];

        if (is_null($repo)) {
            $upstream_data = $this->site->getUpstream();
            $repo_info = $this->session()->getUser()->getUpstreams()->get($upstream_data->get('id'));
            $repo = $repo_info->get('upstream');
        }

        $this->log()->notice('Repository being used: {repo}', ['repo' => $repo]);

        $env = strtolower($options['env']);
        $env = preg_replace("/[^A-Za-z0-9\-]/", "", $env);
        $env = substr($env, 0, 11);
        if (in_array($env, ['dev', 'test', 'live'])) {
            throw new TerminusException('Provided environment must not be dev, test, or live');
        }

        $multi_devs = $this->site->getEnvironments()->multidev();
        if ((count($multi_devs) >= $settings->max_num_cdes) && !in_array($env, array_keys($multi_devs))) {
            throw new TerminusException('Max Number of Multi-Devs and {env} is not initialized', ['env' => $env]);
        }

        $this->log()->notice('Environment being used: {env}', ['env' => $env]);

        $copy = $options['copy'];
        if (is_null($copy)) {
            if ($this->isInitialized('dev')) {
                $copy = 'dev';
            }
            if ($this->isInitialized('test')) {
                $copy = 'test';
            }
            if ($this->isInitialized('live')) {
                $copy = 'live';
            }
        } else {
            if (!$this->isInitialized($copy)) {
                throw new TerminusException('{copy} is not an initialized environment with a database and files.', ['copy' => $copy]);
            }
        }

        $this->log()->notice('Environment being copied from: {env}', ['env' => $copy]);

        if ($this->site->getEnvironments()->has($env) && $options['teardown']) {
            $this->log()->notice('{env} is initialized and will be torn down', ['env' => $env]);
            $this->site->getEnvironments()->get($env)->delete(['delete_branch' => true]);
            $this->site->unsetEnvironments();
        }

        if (!$this->site->getEnvironments()->has($env)) {
            $this->log()
            ->notice('Creating multi-dev named {env} copying data from {copy}', [
              'env' => $env,
              'copy' => $copy
            ]);

            /** @var \Pantheon\Terminus\Models\Environment $copy_env */
            $copy_env = $this->site->getEnvironments()->get($copy);
            $workflow = $this->site->getEnvironments()->create($env, $copy_env);
            while (!$workflow->checkProgress()) {
            }
            $this->log()->notice($workflow->getMessage());
            $this->site->unsetEnvironments();
        }

        $current_env = $this->site->getEnvironments()->get($env);
        if ($current_env->get('connection_mode') !== 'git') {
          $change_count = count((array)$current_env->diffstat());
          if ($change_count > 0) {
            $this->log()->notice('{site}: Code uncommitted. Committing now.', ['site' => $this->site->get('name')]);
            $workflow = $current_env->commitChanges($options['message']);
            while (!$workflow->checkProgress()) {}
          }

          $this->log()->notice('{site}: Changing connection mode to git', ['site' => $this->site->get('name')]);
          $workflow = $current_env->changeConnectionMode('git');
          if (is_string($workflow)) {
            $this->log()->notice($workflow);
          } else {
            while (!$workflow->checkProgress()) {}
            $this->log()->notice($workflow->getMessage());
          }
        }

        $site_git = $this->site->getEnvironments()->get('dev')->gitConnectionInfo()['url'];
        $git_location = '/tmp/' . $site_id;
        $this->passthru("rm -rf {$git_location}");
        $this->log()->notice('Cloning Repository');
        $this->passthru("git clone {$site_git} {$git_location}");
        $this->log()->notice('Repository cloned');
        $this->passthru("git -C '{$git_location}/.git' --work-tree='{$git_location}' remote add upstream {$repo}");
        $this->passthru("git -C '{$git_location}/.git' --work-tree='{$git_location}' fetch -q upstream");
        $this->log()->notice('Checking out branch {env}', ['env' => $env]);
        $this->passthru("git -C '{$git_location}/.git' --work-tree='{$git_location}' checkout -q -B {$env}");
        $this->passthru("git -C '{$git_location}/.git' --work-tree='{$git_location}' pull -q origin {$env}");
        $this->log()->notice('Merging in master environment');
        $this->passthru("git -C '{$git_location}/.git' --work-tree='{$git_location}' merge -q --no-ff --no-edit --commit -m 'Merged Master branch' -X theirs master");
        $this->log()->notice('Merging repo {repo} and branch {branch}', ['repo' => $repo, 'branch' => $branch]);
        $message = (empty($options['message']) ? '' : "-m '{$options['message']}'");
        $this->passthru("git -C '{$git_location}/.git' --work-tree='{$git_location}' merge -q --no-ff --no-edit --commit {$message} -X theirs upstream/{$branch}");

        $this->log()->notice('Pushing to origin {env}', ['env' => $env]);
        $this->passthru("git -C '{$git_location}/.git' --work-tree='{$git_location}' push origin {$env}");
        $this->log()->notice('Deleting repository from {location}', ['location' => $git_location]);
        $this->passthru("rm -rf {$git_location}");

        if ($options['rebuild']) {
            $workflow = $this->site->getEnvironments()->get($env)->cloneFiles($copy);
            $this->log()->notice(
                "Cloning files from {from_name} environment to {target_env} environment",
                compact(['from_name', 'target_env'])
            );
            while (!$workflow->checkProgress()) {
            }
            $this->log()->notice($workflow->getMessage());

            $workflow = $this->site->getEnvironments()->get($env)->cloneDatabase($copy);
            $this->log()->notice(
                "Cloning database from {from_name} environment to {target_env} environment",
                compact(['from_name', 'target_env'])
            );
            while (!$workflow->checkProgress()) {
            }
            $this->log()->notice($workflow->getMessage());
        }

        $this->log()->notice('Running Drush Clear Cache All');
        $this->sendDrushCommand($env, 'cc all');
        $this->log()->notice('Running Drush Updatedb');
        $this->sendDrushCommand($env, 'updatedb -y');
        $this->log()->notice('Running Drush Clear Cache All');
        $this->sendDrushCommand($env, 'cc all');

        if (!is_null($options['username'])) {
            $this->sendDrushCommand($env, "sql-query \"UPDATE users SET name='{$options['username']}' WHERE uid=1\"");
            $this->log()->notice('{env} updated user 1 username to {username}', ['env' => $env, 'username' => $options['username']]);
        }

        if (!is_null($options['username']) && !is_null($options['password'])) {
            $this->sendDrushCommand($env, "user-password \"{$options['username']}\" --password=\"{$options['password']}\"");
            $this->log()->notice('{env} updated user 1 password to {password}', ['env' => $env, 'password' => $options['password']]);
        }

        if (!is_null($options['slack_url'])) {
            $client = new Client($options['slack_url'], [
            'channel' => $options['slack_channel'],
            'username' => $options['slack_username'],
            'icon' => $options['slack_icon']
            ]);

            $url = $this->message("http://{env}-{site_id}.pantheonsite.io", ['env' => $env, 'site_id' => $site_id]);
            $message = $options['slack_message'];
            if (is_null($message)) {
                  $message = "<{url}|{site_name} - {env}> Environment created with upstream tests";
            }

            $message = $this->message($message, ['env' => $env, 'site_id' => $site_id, 'site_name' => $site_name, 'url' => $url]);
            $client->send($message);
            $this->log()->notice('Message sent to slack channel: {url}: {channel}', ['url' => $options['slack_url'], 'channel' => $options['slack_channel']]);
        }

        $this->log()->notice('{site}: Upstream Test Push Completed', ['site' => $site_name]);
    }

  /**
   * Short hand function to pass Drush Commands
   */
    protected function sendDrushCommand($env, $command)
    {
        return $this->site->getEnvironments()->get($env)->sendCommandViaSsh('drush ' . $command);
    }

  /**
   * Short hand function to test if environment is initialized.
   * @param String $env
   * @return boolean
   */
    protected function isInitialized($env)
    {
        return $this->site->getEnvironments()->get($env)->isInitialized();
    }

  /**
   * Replace the variables into the message string.
   *
   * @param string $message      The raw, uninterpolated message string
   * @param array  $replacements The values to replace into the message
   * @return string
   */
    protected function message($message, $replacements)
    {
        $tr = [];
        foreach ($replacements as $key => $val) {
            $tr['{' . $key . '}'] = $val;
        }
        return strtr($message, $tr);
    }

  /**
   * Call passthru; throw an exception on failure.
   *
   * @param string $command
   */
    protected function passthru($command, $loggedCommand = '')
    {
        $result = 0;
        $loggedCommand = empty($loggedCommand) ? $command : $loggedCommand;
        $this->log()->notice("Running {cmd}", ['cmd' => $loggedCommand]);
        passthru($command, $result);
        if ($result != 0) {
            throw new TerminusException('Command `{command}` failed with exit code {status}', ['command' => $loggedCommand, 'status' => $result]);
        }
    }
}
