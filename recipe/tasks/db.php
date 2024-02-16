<?php

/**
 * Bedrock Deployer Database Tasks
 *
 * @author            Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright         2024 BC Libraries Cooperative
 * @license           MIT
 */

namespace Deployer;

use Deployer\Exception\Exception;
use Symfony\Component\Console\Input\InputOption;

set('wp_export_options', '--single-transaction --skip-lock-tables');
set('wp_replace_options', '--skip-plugins --skip-themes --skip-columns=guid --all-tables-with-prefix');
set('wp_tables_options', '--skip-plugins --skip-themes --all-tables-with-prefix --scope=blog --format=csv');

option('full-replace', null, null, 'For database tasks, replace all URLs, not just blog/site/options tables');
option('url', null, InputOption::VALUE_REQUIRED, 'For database backup tasks, single site URL to back up');

task('db:push', function () {
    $env = get('labels', ['env' => ''])['env'];

    if ($env === 'production' && !askConfirmation("Are you sure to push the database to the $env environment?")) {
        writeln("DB push aborted!");
        return;
    }

    $hash        = substr(md5(mt_rand()), 0, 7);
    $result_file = sprintf('%s-%s-%s.sql.gz', get('application'), date('Y-m-d'), $hash);

    writeln("✈︎ Pushing local database to <fg=cyan>{{hostname}}</fg=cyan>");

    runLocally("{{bin/wp}} db export {{wp_export_options}} - | gzip > $result_file");

    if (!testLocally("[ -f $result_file ]") || !testLocally("[ -s $result_file ]")) {
        throw new Exception('Database Export Failed');
    }

    upload("$result_file", "{{deploy_path}}/$result_file");
    runLocally("rm $result_file");

    if (!test("[ -f {{deploy_path}}/$result_file ]") || !test("[ -s {{deploy_path}}/$result_file ]")) {
        throw new Exception('Database Export Copy Failed');
    }

    within('{{release_or_current_path}}', function () use ($result_file) {
        writeln("Importing database");
        run("gunzip -c {{deploy_path}}/$result_file | {{bin/wp}} db import -");
        run("rm {{deploy_path}}/$result_file");

        writeLn("Replacing URLs in database");

        /**
         * Gating this to the specific recipe as it sets the variables we need
         */
        if (in_array('bedrock-multisite', get('recipes', []))) {
            run("{{bin/wp}} search-replace "
                . "'https?://([\w\.]*\.){{local_base_url}}' 'http://$1{{base_url}}' 'wp_*options' "
                . "--url={{local_url}} --network  --skip-plugins --skip-themes --regex --regex-delimiter='%'");
            run("{{bin/wp}} search-replace '{{local_base_url}}' '{{base_url}}' wp_blogs wp_site "
                . "--url={{local_url}} --network --skip-plugins --skip-themes");

            if (input()->getOption('full-replace')) {
                // TODO: Get all blog URLs and loop through to change URLs? Probably faster than a regex.
                // TODO: Be more surgical about tables/columns to look at to speed up?
                info("--full-replace not fully implemented. Main URL will be changed, but not sub-sites.");
                run("{{bin/wp}} search-replace 'http://{{local_url}}' 'http://{{url}}' "
                    . "--url={{local_url}} --network {{wp_replace_options}}");
            }
        } else {
            // Assume https for prod
            run("{{bin/wp}} search-replace 'http://{{local_url}}' 'https://{{url}}' {{wp_replace_options}}");
        }
    });
})
    ->desc("Pushes the database from local to remote env, replacing URLs as appropriate");

task('db:pull', function () {
    $env = get('labels', ['env' => ''])['env'];

    if ($env === 'staging' && !askConfirmation("Are you sure to pull the database from the $env environment?")) {
        writeln("DB pull aborted!");
        return;
    }

    $hash        = substr(md5(mt_rand()), 0, 7);
    $result_file = sprintf('%s-%s-%s.sql.gz', get('application'), date('Y-m-d'), $hash);
    $remote_result_file = "{{release_or_current_path}}/backup/$result_file";

    writeln("✈︎ Pulling database from <fg=cyan>{{hostname}}</fg=cyan>");

    within('{{release_or_current_path}}', function () use ($result_file, $remote_result_file) {
        run("{{bin/wp}} db export {{wp_export_options}} - | gzip > $remote_result_file");

        if (!test("[ -f $remote_result_file ]") || !test("[ -s $remote_result_file ]")) {
            throw new Exception('Database Export Failed');
        }

        download("$remote_result_file", "$result_file");
        run("rm $remote_result_file");
    });

    if (!test("[ -f $result_file ]") || !test("[ -s $result_file ]")) {
        throw new Exception('Database Export Copy Failed');
    }

    writeln("Importing database");
    runLocally("gunzip -c $result_file | {{bin/wp}} db import -");
    runLocally("rm $result_file");

    writeLn("Replacing URLs in database");

    /**
     * Gating this to the specific recipe as it sets the variables we need
     */
    if (in_array('bedrock-multisite', get('recipes', []))) {
        runLocally("{{bin/wp}} search-replace 'https?://([\w\.]*\.){{base_url}}' 'http://$1{{local_base_url}}' "
            . "'wp_*options' --url={{url}} --skip-plugins --skip-themes --network --regex --regex-delimiter='%' ");
        runLocally("{{bin/wp}} search-replace '{{base_url}}' '{{local_base_url}}' wp_blogs wp_site "
            . "--url={{url}} --network --skip-plugins --skip-themes");

        if (input()->getOption('full-replace')) {
            info("--full-replace not fully implemented. Main URL will be changed, but not sub-sites.");
            runLocally("{{bin/wp}} search-replace 'https://{{url}}' 'http://{{local_url}}' "
                . "--url={{url}} --network {{wp_replace_options}}");
        }
    } else {
        // Do http and https when pulling to local
        runLocally("{{bin/wp}} search-replace 'http://{{url}}' 'http://{{local_url}}' {{wp_replace_options}}");
        runLocally("{{bin/wp}} search-replace 'https://{{url}}' 'http://{{local_url}}' {{wp_replace_options}}");
    }
})
    ->desc('Pulls the database from remote env to local, replacing URLs as appropriate');

task('db:backup', function () {
    $env = get('labels', ['env' => ''])['env'];

    if ($env === 'production' && !askConfirmation("Are you sure you want to backup the $env database?)")) {
        writeln("DB backup aborted!");
        return;
    }

    $url         = input()->getOption('url');
    $hash        = substr(md5(mt_rand()), 0, 7);
    $result_file = sprintf(
        "%s/%s-%s%s-%s-%s.sql.gz",
        '{{release_or_current_path}}/config/backup',
        get('application'),
        $env,
        $url ? '-' . preg_replace('/[^A-Za-z0-9-]+/', '-', $url) : null,
        date('Y-m-d'),
        $hash
    );

    within('{{release_or_current_path}}', function () use ($result_file, $url) {
        $tables = '';

        if (!empty($url)) {
            try {
                $tables = run("{{bin/wp}} db tables {{wp_tables_options}} --url={$url}");
                $tables = $tables ? "--tables={$tables}" : '';
            } catch (\Exception $e) {
                throw new Exception("Unable to find any database tables for URL {$url}, "
                    . "are you sure it exists in this environment?");
            }
        }

        writeln("✈︎ Backing up database on <fg=cyan;options=bold>{{hostname}}</> to: "
            . "<fg=green;options=bold>{$result_file}</>");

        run("{{bin/wp}} db export $tables {{wp_export_options}} - | gzip > $result_file");

        if (!test("[ -f $result_file ]") || !test("[ -s $result_file ]")) {
            throw new Exception('Database Export Failed');
        }
    });
})
    ->desc('Makes a backup of the database');
