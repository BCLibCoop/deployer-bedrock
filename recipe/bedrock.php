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
 * Inherit `composer` recipe
 */
require_once 'recipe/composer.php';

/**
 * Require tasks
 */
require_once __DIR__ . '/tasks/cleanup.php';
require_once __DIR__ . '/tasks/db.php';
require_once __DIR__ . '/tasks/php.php';
require_once __DIR__ . '/tasks/uploads.php';
require_once __DIR__ . '/tasks/wp-cli.php';

add('recipes', ['bedrock']);

/**
 * Set "application" name to base dir name
 */
set('application', basename(dirname(\DEPLOYER_DEPLOY_FILE)));

/**
 * Keep fewer releases
 */
set('keep_releases', 3);

/**
 * URLs and Paths,used in tasks/db.php
 */
set('url', '{{application}}.com');
set('local_url', '{{application}}.test');
set('deploy_path', '/var/www/{{url}}');

/**
 * Writeable Dirs Settings
 */
set('writable_mode', 'chown');
set('writable_use_sudo', true);
set('writable_recursive', true);
set('http_user', 'www-data:www-data');
set('http_group', 'www-data');

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

/**
 * Add default localhost
 */
localhost()
    ->setLabels(['env' => 'development'])
    ->setDeployPath('.')
    ->set('release_path', '.')
    ->set('current_path', '.');
