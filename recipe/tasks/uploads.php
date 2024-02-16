<?php

/**
 * Bedrock Deployer Uploads Tasks
 *
 * @author            Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright         2024 BC Libraries Cooperative
 * @license           MIT
 */

namespace Deployer;

/**
 * Push uploads from the local env to a remote
 */
task('uploads:push', function () {
    $env = get('labels', ['env' => ''])['env'];

    if ($env === 'production' && !askConfirmation("Are you sure to push uploads to the $env environment?")) {
        writeln('Upload push aborted!');
        return;
    }

    $sharedPath = '{{deploy_path}}/shared/';

    foreach (get('sync_dirs') as $path) {
        writeln('✈︎ Uploading ' . $path . ' to <fg=cyan>{{hostname}}</fg=cyan>');
        upload($path, $sharedPath . $path);
    }
})
    ->desc('Pulls uploads from remote env to local');

/**
 * Pull uploads a remote to the local env
 */
task('uploads:pull', function () {
    $env = get('labels', ['env' => ''])['env'];

    if ($env === 'staging' && !askConfirmation("Are you sure to pull uploads from the $env environment?")) {
        writeln('Upload pull aborted!');
        return;
    }

    $sharedPath = '{{deploy_path}}/shared/';

    foreach (get('sync_dirs') as $path) {
        writeln('✈︎ Downloading ' . $path . ' from <fg=cyan>{{hostname}}</fg=cyan>');
        download($sharedPath . $path, $path);
    }
})
    ->desc('Pushes uploads from local to remote env');
