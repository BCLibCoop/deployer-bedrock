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

set('db_export_options', '--single-transaction --skip-lock-tables');

option('skip-import', null, null, 'For database tasks, only pushes or pulls the database, does not import');

/**
 * These options only apply to WordPress imports/exports
 */
if (in_array('bedrock', get('recipes'))) {
    option('full-replace', null, null, 'For database tasks, replace all URLs, not just blog/site/options tables');
    option('url', null, InputOption::VALUE_REQUIRED, 'For database backup tasks, single site URL to back up');
}

task('db:push', function () {
    /**
     * Get the environment we're targeting
     */
    set('environment', get('labels', ['env' => ''])['env']);

    if (get('environment') === 'production' && !askConfirmation(sprintf('Are you sure you want to push the database to the %s environment?', get('environment')))) {
        writeln('DB push aborted!');
        return;
    }

    $hash        = substr(md5(mt_rand()), 0, 7);
    set('result_file', sprintf('{{application}}-{{environment}}-%s-%s.sql.gz', date('Y-m-d\TH-i-s'), $hash));
    set('remote_result_file', '{{backup_path}}/{{result_file}}');

    writeln('✈︎ Pushing local database to <fg=cyan>{{hostname}}</fg=cyan>');

    runLocally('{{db_export_command}} | gzip > {{result_file}}');

    if (!testLocally('[ -s {{result_file}} ]')) {
        throw new Exception('Database Export Failed');
    }

    /**
     * Ensure backup directory exists
     */
    run('[ -d {{backup_path}} ] || mkdir {{backup_path}}');

    upload('{{result_file}}', '{{remote_result_file}}');

    if (!test('[ -s {{remote_result_file}} ]')) {
        throw new Exception('Database Export Upload Failed');
    }

    runLocally('rm {{result_file}}');

    if (input()->getOption('skip-import')) {
        writeln('Skipping import. File is located at {{remote_result_file}}');
        return;
    }

    writeln('Importing database');

    cd('{{current_path}}');

    run('gunzip -c {{remote_result_file}} | {{db_import_command}}');
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
    /**
     * Get the environment we're targeting
     */
    set('environment', get('labels', ['env' => ''])['env']);

    if (get('environment') === 'production' && !askConfirmation(sprintf('Are you sure you want to pull the database from the %s environment?', get('environment')))) {
        writeln('DB pull aborted!');
        return;
    }

    $hash = substr(md5(mt_rand()), 0, 7);
    set('result_file', sprintf('{{application}}-{{environment}}-%s-%s.sql.gz', date('Y-m-d\TH-i-s'), $hash));
    set('remote_result_file', '{{backup_path}}/{{result_file}}');

    writeln('✈︎ Pulling database from <fg=cyan>{{hostname}}</fg=cyan>');

    cd('{{current_path}}');

    /**
     * Ensure backup directory exists
     */
    run('[ -d {{backup_path}} ] || mkdir {{backup_path}}');

    run('{{db_export_command}} | gzip > {{remote_result_file}}');

    if (!test('[ -s {{remote_result_file}} ]')) {
        throw new Exception('Database Export Failed');
    }

    download('{{remote_result_file}}', '{{result_file}}');

    if (!testLocally('[ -s {{result_file}} ]')) {
        throw new Exception('Database Export Download Failed');
    }

    run('rm {{remote_result_file}}');

    if (input()->getOption('skip-import')) {
        writeln('Skipping import, file is located at {{result_file}}');
        return;
    }

    writeln('Importing database');
    runLocally('gunzip -c {{result_file}} | {{db_import_command}}');
    runLocally('rm {{result_file}}');

    /**
     * Gating this to the specific recipe as it sets the variables we need
     */
    if (in_array('bedrock-multisite', get('recipes'))) {
        writeLn('Replacing base URLs in multi-site database');

        runLocally("{{bin/wp}} search-replace 'https?://([\w\.]*\.){{base_url}}' 'http://$1{{local_base_url}}' "
            . "'wp_*options' --url={{url}} --skip-plugins --skip-themes --network --regex --regex-delimiter='%' ");
        runLocally("{{bin/wp}} search-replace '{{base_url}}' '{{local_base_url}}' wp_blogs wp_site "
            . "--url={{url}} --network --skip-plugins --skip-themes");

        if (input()->getOption('full-replace')) {
            info("--full-replace not fully implemented. Main URL will be changed, but not sub-sites.");
            runLocally("{{bin/wp}} search-replace 'https://{{url}}' 'http://{{local_url}}' "
                . "--url={{url}} --network {{wp_replace_options}}");
        }
    } elseif (in_array('bedrock', get('recipes'))) {
        writeLn('Replacing URLs in database');

        // Do http and https when pulling to local
        runLocally("{{bin/wp}} search-replace 'http://{{url}}' 'http://{{local_url}}' {{wp_replace_options}}");
        runLocally("{{bin/wp}} search-replace 'https://{{url}}' 'http://{{local_url}}' {{wp_replace_options}}");
    }
})
    ->desc('Pulls the database from remote env to local, replacing URLs as appropriate');

task('db:backup', function () {
    /**
     * Get the environment we're targeting
     */
    set('environment', get('labels', ['env' => ''])['env']);

    if (get('environment') === 'production' && !askConfirmation(sprintf('Are you sure you want to backup the database in the %s environment?', get('environment')))) {
        writeln('DB backup aborted!');
        return;
    }

    cd('{{current_path}}');

    /**
     * Ensure backup directory exists
     */
    run('[ -d {{backup_path}} ] || mkdir {{backup_path}}');

    $url = '';
    if (in_array('bedrock', get('recipes'))) {
        $url = input()->getOption('url');

        if (!empty($url)) {
            set('single_url', $url);
        }
    }

    set(
        'result_file',
        sprintf(
            '{{backup_path}}/{{application}}-{{environment}}%s-%s-%s.sql.gz',
            $url ? '-' . preg_replace('/[^A-Za-z0-9-]+/', '_', $url) : null,
            date('Y-m-d\TH-i-s'),
            substr(md5(mt_rand()), 0, 7)
        )
    );

    if (has('single_url')) {
        try {
            if ($tables = run('{{bin/wp}} db tables {{wp_tables_options}} --url={{single_url}}')) {
                set('db_export_options', parse('{{db_export_options}}') . " $tables");
            }
        } catch (\Exception $e) {
            throw new Exception(sprintf('Unable to find any database tables for URL %s, are you sure it exists in this environment?', get('single_url')));
        }
    }

    writeln('✈︎ Backing up database on <fg=cyan;options=bold>{{hostname}}</> to: <fg=green;options=bold>{{result_file}}</>');

    run('{{db_export_command}} | gzip > {{result_file}}');

    if (!test('[ -s {{result_file}} ]')) {
        throw new Exception('Database Backup Failed');
    }
})
    ->desc('Makes a backup of the database');
