<?php

/**
 * Drupal Deployer Recpie
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
 * Declare recipe early so it can be checked for is tasks
 */
add('recipes', ['drupal']);

/**
 * Require tasks
 */
require_once __DIR__ . '/tasks/drush.php';
require_once __DIR__ . '/tasks/db.php';

/**
 * Default drupal site, for syncing content
 */
set('drupal_site', 'default');

set('db_export_command', '{{bin/drush}} sql-dump --extra="{{db_export_options}}"');
set('db_import_command', '{{bin/drush}} sql-cli');

/**
 * Shared files/dirs between deploys
 */
set('shared_files', [
    '.env',
    'web/.user.ini',
]);
set('shared_dirs', [
    'web/sites/{{drupal_site}}/files',
]);

/**
 * Writable dirs by web server
 */
set('writable_dirs', [
    'web/sites/{{drupal_site}}/files',
]);

/**
 * Directories to be pulled/pushed, used in tasks/uploads.php. rsync format, so
 * directories should have a trailing slash to sync the whole dir
 */
set('sync_dirs', [
    'web/sites/{{drupal_site}}/files/',
]);
