<?php

/**
 * Drupal Deployer Database Tasks
 *
 * @author    Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright 2024 BC Libraries Cooperative
 * @license   MIT
 */

namespace Deployer;

use Deployer\Exception\Exception;

task('db:push', function () {
    $env = get('labels', ['env' => ''])['env'];

    if (
        ($env === 'production' && askConfirmation("Are you sure to push the database to the $env environment?"))
        || $env !== 'production'
    ) {
        $hash        = substr(md5(mt_rand()), 0, 7);
        $result_file = sprintf('%s-%s-%s.sql.gz', get('application'), date('Y-m-d'), $hash);

        writeln("✈︎ Pushing local database to <fg=cyan>{{hostname}}</fg=cyan>");

        on(localhost(), function () use ($result_file) {
            run("{{bin/drush}} sql-dump --extra=--skip-lock-tables | gzip > $result_file");

            if (!test("[ -f $result_file ]") || !test("[ -s $result_file ]")) {
                throw new Exception('Database Export Failed');
            }
        });

        upload("$result_file", "{{deploy_path}}/$result_file");
        runLocally("rm $result_file");

        if (test("[ -f {{deploy_path}}/$result_file ]") && test("[ -s {{deploy_path}}/$result_file ]")) {
            within('{{release_or_current_path}}', function () use ($result_file) {
                writeln("Importing database");
                run("gunzip -c {{deploy_path}}/$result_file | {{bin/drush}} sql-cli");
                run("rm {{deploy_path}}/$result_file");
            });
        } else {
            throw new Exception('Database Export Copy Failed');
        }
    }
})
    ->desc("Pushes the database from local to remote env, replacing URLs as appropriate");

task('db:pull', function () {
    $env = get('labels', ['env' => ''])['env'];

    if (
        ($env === 'staging' && askConfirmation("Are you sure to pull the database from the $env environment?"))
        || $env !== 'staging'
    ) {
        $hash        = substr(md5(mt_rand()), 0, 7);
        $result_file = sprintf('%s-%s-%s.sql.gz', get('application'), date('Y-m-d'), $hash);

        writeln("✈︎ Pulling database from <fg=cyan>{{hostname}}</fg=cyan>");

        within('{{release_or_current_path}}', function () use ($result_file) {
            run("{{bin/drush}} sql-dump --extra=--skip-lock-tables | gzip > {{deploy_path}}/$result_file");
            download("{{deploy_path}}/$result_file", "$result_file");
            run("rm {{deploy_path}}/$result_file");
        });

        writeln("Importing database");
        on(localhost(), function () use ($result_file) {
            run("gunzip -c $result_file | {{bin/drush}} sql-cli");
            run("rm $result_file");
        });
    }
})
    ->desc('Pulls the database from remote env to local, replacing URLs as appropriate');

task('db:backup', function () {
    $env = get('labels', ['env' => ''])['env'];

    if (
        $env !== 'production'
        || ($env === 'production' && askConfirmation("Are you sure to backup the $env database?"))
    ) {
        $hash        = substr(md5(mt_rand()), 0, 7);
        $result_file = sprintf(
            "%s/%s-%s-%s-%s.sql.gz",
            '{{current_path}}/backup',
            get('application'),
            $env,
            date('Y-m-d'),
            $hash
        );

        within('{{current_path}}', function () use ($result_file) {
            run("{{bin/drush}} sql-dump --extra=--skip-lock-tables | gzip > $result_file");

            if (!test("[ -f $result_file ]") || !test("[ -s $result_file ]")) {
                throw new Exception('Database Export Failed');
            } else {
                info("✈︎ Successfully backed up database on <fg=cyan;options=bold>{{hostname}}</> to: <fg=green;options=bold>{$result_file}</>");
            }
        });
    }
})
    ->desc("Makes a backup of the database");
