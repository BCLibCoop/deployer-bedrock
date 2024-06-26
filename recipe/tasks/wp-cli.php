<?php

/**
 * Bedrock Deployer WP-CLI Tasks
 *
 * @author            Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright         2024 BC Libraries Cooperative
 * @license           MIT
 */

namespace Deployer;

/**
 * Locate WP-CLI binary
 */
set('bin/wp', function () {
    if (test('[ -f {{release_or_current_path}}/vendor/bin/wp ]')) {
        return "{{bin/php}} {{release_or_current_path}}/vendor/bin/wp";
    } elseif (commandExist('wp')) {
        return '{{bin/php}} ' . which('wp');
    } elseif (test('[ -f ~/.composer/vendor/bin/wp ]')) {
        return '{{bin/php}}  ~/.composer/vendor/bin/wp';
    } else {
        throw new \RuntimeException('Cannot find wp-cli. Please specify path manually');
    }
});

/**
 * Flag to confirm database update
 */
option('update-db', null, null, 'Confirm a multisite WordPress DB Update');

/**
 * Delete main site and network transients
 *
 * @todo: Decide if we want to clear all sites with something like:
 *         wp site list --field=url | xargs -n1 -I % wp --url=% transient delete --all
 */
task('wpcli:cleartransients', function () {
    cd('{{release_or_current_path}}');

    run('{{bin/wp}} transient delete --all');
    run('{{bin/wp}} transient delete --all --network');
})
    ->desc('Clear all WordPress transients');

/**
 * Flushes the WordPress object cache. Doesn't do anything useful unless an
 * external persistant cache is in use, but no harm in doing it
 */
task('wpcli:flushcache', function () {
    cd('{{release_or_current_path}}');

    run('{{bin/wp}} cache flush');
})
    ->desc('Flush WordPress object cache');

/**
 * Flush rewrites. The `--hard` flag implies/requires Apache with the correct
 * config in `wp-cli.yml`
 */
task('wpcli:flushrewrite', function () {
    cd('{{release_or_current_path}}');

    run('{{bin/wp}} rewrite flush --hard');
})
    ->desc('Flush WordPress rewrites');

/**
 * Flushes the OPCache with a plugin that makes an HTTP call to ensure it
 * targets the php-fpm pool cache. Less disruptive than doing a full reload
 */
task('wpcli:opcache_clear', function () {
    cd('{{release_or_current_path}}');

    if (!test('{{bin/wp}} plugin is-installed wp-cli-clear-opcache')) {
        writeln('<comment>Skipped because wp-cli-clear-opcache is not installed.</comment>');
        return;
    }

    run('{{bin/wp}} plugin activate wp-cli-clear-opcache --quiet');
    run('{{bin/wp}} opcache clear');
})
    ->desc('Clear OPcache');

/**
 * Run WP DB update
 */
task('wpcli:update_db', function () {
    cd('{{release_or_current_path}}');

    try {
        $is_multisite = test('{{bin/wp}} core is-installed --network');

        if ($is_multisite && !input()->getOption('update-db')) {
            writeln('<comment>Databse update can be time intensive on multisite, pass `--update-db` to really do it</comment>');
            run('{{bin/wp}} core update-db --dry-run', ['real_time_output' => true]);
            return;
        }

        run('{{bin/wp}} core update-db' . ($is_multisite ? ' --network' : ''), ['real_time_output' => true]);
    } catch (\Throwable $t) {
        writeln('<error>WordPress database could not be updated. '
            . 'Run manually via wp-admin/upgrade.php if necessary.</error>');
    }
})
    ->desc('Runs the WordPress database update procedure');

/**
 * Shortcut to do all of the above
 */
task('wpcli:clearcache', [
    'wpcli:opcache_clear',
    'wpcli:cleartransients',
    'wpcli:flushcache',
    'wpcli:flushrewrite',
])
    ->desc('Clear WordPress transients, cache, and rewrites');

/**
 * Clear/Flush everything on a new release
 */
after('deploy:symlink', 'wpcli:clearcache');

/**
 * Attempt a DB update
 */
after('deploy:symlink', 'wpcli:update_db');
