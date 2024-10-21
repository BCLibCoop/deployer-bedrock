<?php

/**
 * Deployer Database Tasks
 *
 * @author            Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright         2024 BC Libraries Cooperative
 * @license           MIT
 */

namespace Deployer;

use Deployer\Exception\Exception;
use Symfony\Component\Console\Input\InputOption;

option('skip-import', null, null, 'For database tasks, only pushes or pulls the database, does not import');

// TODO: Implement backup before push/pull
// option('skip-backup', null, null, 'For database tasks, skip the backup that should occur before push/pull');

set('db_export_options', '--single-transaction --skip-lock-tables');

set('hash', substr(md5(mt_rand()), 0, 7));
set('date', date('Y-m-d\TH-i-s'));
set('url_slug', '');
set('db_environment', '{{environment}}');
set('result_file', '{{application}}-{{db_environment}}{{url_slug}}-{{date}}-{{hash}}.sql.gz');
set('local_result_file', '{{backup_path}}/{{result_file}}');
set('remote_result_file', '{{remote_backup_path}}/{{result_file}}');

/**
 * These options only apply to WordPress imports/exports
 */
if (in_array('bedrock', get('recipes'))) {
    option('full-replace', null, null, 'For database tasks, replace all URLs, not just blog/site/options tables');
}

if (in_array('bedrock-multisite', get('recipes'))) {
    option('url', null, InputOption::VALUE_REQUIRED, 'For database backup tasks, single site URL to back up');
}

task('db:push', function () {
    if (in_array(get('environment'), get('protected_environments')) && !askConfirmation('Are you sure you want to push the database to the {{environment}} environment?')) {
        writeln('DB push aborted!');
        return;
    }

    writeln('✈︎ Pushing local database to <fg=cyan>{{hostname}}</fg=cyan>');

    // Override the db_environment to indicate it's the localhost's environment we're exporting
    set('db_environment', host('localhost')->getLabels()['env']);

    /**
     * Use this `on` closure instead of `runLocally()` so we resolve the local
     * PHP version correctly
     */
    on(host('localhost'), function () {
        /**
         * Ensure local backup directory exists
         */
        run('[ -d {{backup_path}} ] || mkdir {{backup_path}}');

        run('{{pipefail}} {{db_export_command}} | gzip > {{local_result_file}}', ['timeout' => null]);

        if (!test('[ -s {{local_result_file}} ]')) {
            throw new Exception('Database Export Failed');
        }
    });

    /**
     * Ensure remote backup directory exists
     */
    run('[ -d {{remote_backup_path}} ] || mkdir {{remote_backup_path}}');

    upload('{{local_result_file}}', '{{remote_result_file}}');

    if (!test('[ -s {{remote_result_file}} ]')) {
        throw new Exception('Database Export Upload Failed');
    }

    runLocally('rm {{local_result_file}}');

    if (input()->getOption('skip-import')) {
        writeln('Skipping import. File is located at {{remote_result_file}}');
        return;
    }

    writeln('Importing database');

    cd('{{current_path}}');

    run('{{pipefail}} gunzip -c {{remote_result_file}} | {{mariadb_fix}} {{db_import_command}}', ['timeout' => null]);
    run('rm {{remote_result_file}}');

    /**
     * Gating this to the specific recipe as it sets the variables we need
     */
    if (in_array('bedrock-multisite', get('recipes'))) {
        writeLn('Replacing base URLs in multi-site database');

        run("{{bin/wp}} search-replace 'https?://([\w\.]*\.){{local_base_url}}' 'http://$1{{base_url}}' 'wp_*options' "
            . "--url={{local_url}} --network  --skip-plugins --skip-themes --regex --regex-delimiter='%'");
        run("{{bin/wp}} search-replace '{{local_base_url}}' '{{base_url}}' wp_blogs wp_site "
            . "--url={{local_url}} --network --skip-plugins --skip-themes");

        if (input()->getOption('full-replace')) {
            // TODO: Get all blog URLs and loop through to change URLs? Probably faster than a regex.
            // TODO: Be more surgical about tables/columns to look at to speed up?
            info('--full-replace not fully implemented. Main URL will be changed, but not sub-sites.');
            run("{{bin/wp}} search-replace 'http://{{local_url}}' 'http://{{url}}' --url={{local_url}} --network {{wp_replace_options}}");
        }
    } elseif (in_array('bedrock', get('recipes'))) {
        writeLn('Replacing URLs in database');

        // Assume https for prod
        run("{{bin/wp}} search-replace 'http://{{local_url}}' 'https://{{url}}' {{wp_replace_options}}");
    }
})
    ->desc('Pushes the database from local to remote env, replacing URLs as appropriate');

task('db:pull', function () {
    if (in_array(get('environment'), get('protected_environments')) && !askConfirmation('Are you sure you want to pull the database from the {{environment}} environment?')) {
        writeln('DB pull aborted!');
        return;
    }

    /**
     * Ensure remote backup directory exists
     */
    run('[ -d {{remote_backup_path}} ] || mkdir {{remote_backup_path}}');

    writeln('✈︎ Pulling database from <fg=cyan>{{hostname}}</fg=cyan>');

    cd('{{current_path}}');

    run('{{pipefail}} {{db_export_command}} | gzip > {{remote_result_file}}', ['timeout' => null]);

    if (!test('[ -s {{remote_result_file}} ]')) {
        throw new Exception('Database Export Failed');
    }

    /**
     * Ensure local backup directory exists
     */
    runLocally('[ -d {{backup_path}} ] || mkdir {{backup_path}}');

    download('{{remote_result_file}}', '{{local_result_file}}');

    if (!testLocally('[ -s {{local_result_file}} ]')) {
        throw new Exception('Database Export Download Failed');
    }

    run('rm {{remote_result_file}}');

    if (input()->getOption('skip-import')) {
        writeln('Skipping import, file is located at {{local_result_file}}');
        return;
    }

    /**
     * Export env and URL of the current host. I haven't found a cleaner way to
     * either reference these inside of the below localhost closure, or
     * conversely to re-evaluate the PHP/WP-CLI binary location for localhost
     */
    $db_env = get('db_environment');
    $url = get('url');

    writeln('Importing database');

    /**
     * Use this `on` closure instead of `runLocally()` so we resolve the local
     * PHP version correctly
     */
    on(host('localhost'), function () use ($db_env, $url) {
        /**
         * Set these vars correctly in the localhost context based on the env
         * that we were called for
         */
        set('db_environment', $db_env);
        set('url', $url);

        run('{{pipefail}} gunzip -c {{local_result_file}} | {{mariadb_fix}} {{db_import_command}}', ['timeout' => null]);
        run('rm {{local_result_file}}');

        /**
         * Gating this to the specific recipe as it sets the variables we need
         */
        if (in_array('bedrock-multisite', get('recipes'))) {
            writeLn('Replacing base URLs in multi-site database');

            run("{{bin/wp}} search-replace 'https?://([\w\.]*\.){{base_url}}' 'http://$1{{local_base_url}}' "
                . "'wp_*options' --url={{url}} --skip-plugins --skip-themes --network --regex --regex-delimiter='%' ");
            run("{{bin/wp}} search-replace '{{base_url}}' '{{local_base_url}}' wp_blogs wp_site "
                . "--url={{url}} --network --skip-plugins --skip-themes");

            if (input()->getOption('full-replace')) {
                info("--full-replace not fully implemented. Main URL will be changed, but not sub-sites.");
                run("{{bin/wp}} search-replace 'https://{{url}}' 'http://{{local_url}}' "
                    . "--url={{url}} --network {{wp_replace_options}}");
            }
        } elseif (in_array('bedrock', get('recipes'))) {
            writeLn('Replacing URLs in database');

            // Do http and https when pulling to local
            run("{{bin/wp}} search-replace 'http://{{url}}' 'http://{{local_url}}' {{wp_replace_options}}");
            run("{{bin/wp}} search-replace 'https://{{url}}' 'http://{{local_url}}' {{wp_replace_options}}");
        }
    });
})
    ->desc('Pulls the database from remote env to local, replacing URLs as appropriate');

task('db:backup', function () {
    if (in_array(get('environment'), get('protected_environments')) && !askConfirmation('Are you sure you want to backup the database in the {{environment}} environment?')) {
        writeln('DB backup aborted!');
        return;
    }

    /**
     * Ensure backup directory exists
     */
    run('[ -d {{remote_backup_path}} ] || mkdir {{remote_backup_path}}');

    cd('{{current_path}}');

    if (in_array('bedrock-multisite', get('recipes'))) {
        if ($url = input()->getOption('url')) {
            try {
                if ($tables = run("{{bin/wp}} db tables {{wp_tables_options}} --url=$url")) {
                    set('url_slug', '-' . preg_replace('/[^A-Za-z0-9-]+/', '_', $url));
                    set('db_export_options', parse("{{db_export_options}} $tables"));
                }
            } catch (\Exception $e) {
                throw new Exception("Unable to find any database tables for URL $url, are you sure it exists in this environment?");
            }
        }
    }

    writeln('✈︎ Backing up database on <fg=cyan;options=bold>{{hostname}}</> to: <fg=green;options=bold>{{remote_result_file}}</>');

    run('{{pipefail}} {{db_export_command}} | gzip > {{remote_result_file}}', ['timeout' => null]);

    if (!test('[ -s {{remote_result_file}} ]')) {
        throw new Exception('Database Backup Failed');
    }
})
    ->desc('Makes a backup of the database');
