<?php

/**
 * Bedrock Deployer Recpie
 *
 * @author            Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright         2024 BC Libraries Cooperative
 * @license           MIT
 */

namespace Deployer;

/**
 * Inherit `bclc-base` recipe
 */
require_once __DIR__ . '/bclc-base.php';

/**
 * Require tasks
 */
require_once __DIR__ . '/tasks/db-wordpress.php';
require_once __DIR__ . '/tasks/wp-cli.php';

add('recipes', ['bedrock']);

/**
 * WordPress content directory, defaults to `app` for Bedrock
 */
set('wp_content_dir', 'app');

/**
 * Shared files/dirs between deploys
 */
set('shared_files', [
    '.env',
    'web/.htaccess',
    'web/.user.ini',
    'config/application.local.php',
]);
set('shared_dirs', [
    'config/backup',
    'web/{{wp_content_dir}}/uploads',
    'web/{{wp_content_dir}}/fonts',
]);

/**
 * Writable dirs by web server
 */
set('writable_dirs', [
    'web/{{wp_content_dir}}/uploads',
    'web/{{wp_content_dir}}/fonts',
]);

/**
 * Directories to be pulled/pushed, used in tasks/uploads.php. rsync format, so
 * directories should have a trailing slash to sync the whole dir
 */
set('sync_dirs', [
    'web/{{wp_content_dir}}/uploads/',
]);
