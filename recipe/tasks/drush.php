<?php

/**
 * Deployer drush Tasks
 *
 * @author    Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright 2024 BC Libraries Cooperative
 * @license   MIT
 */

namespace Deployer;

// Locate drush binary
set('bin/drush', function () {
    if (test('[ -f {{release_or_current_path}}/vendor/bin/drush ]')) {
        return "{{bin/php}} {{release_or_current_path}}/vendor/bin/drush";
    } elseif (commandExist('drush')) {
        return '{{bin/php}}' . which('drush');
    } elseif (test('[ -f ~/.composer/vendor/bin/drush ]')) {
        return '{{bin/php}} ~/.composer/vendor/bin/drush';
    } else {
        throw new \RuntimeException('Cannot find drush. Please specify path to drush manually');
    }
});

/**
 * Run drush deploy task
 */
task('drush:postdeploy', function () {
    within('{{release_or_current_path}}', function () {
        /**
         * Setting `no_throw` so we don't get a sudo password prompt if the command fails
         */
        run('sudo -u {{ http_group }} {{bin/drush}} deploy -v -y', ['real_time_output' => true, 'no_throw' => true]);
    });
})
    ->desc('Clear Drupal cache, etc');

/**
 * Clear/Flush everything on a new release
 */
before('deploy:success', 'drush:postdeploy');
