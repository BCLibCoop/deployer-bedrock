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
 * Declare recipe early so it can be checked for is tasks
 */
add('recipes', ['bedrock']);

/**
 * Require tasks
 */
require_once __DIR__ . '/tasks/wp-cli.php';
require_once __DIR__ . '/tasks/db.php';

/**
 * WordPress content directory, defaults to `app` for Bedrock
 */
set('wp_content_dir', 'app');

set('db_export_command', '{{bin/wp}} db export - {{db_export_options}}');
set('db_import_command', '{{bin/wp}} db import -');
set('wp_replace_options', '--skip-plugins --skip-themes --skip-columns=guid --all-tables-with-prefix --report-changed-only');
set('wp_ms_replace_options', '--all-tables  --skip-plugins --skip-themes --no-recurse-objects --report-changed-only');
set('wp_tables_options', '--skip-plugins --skip-themes --all-tables-with-prefix --scope=blog --format=csv');

/**
 * Copy some dirs to speed things up
 */
set('copy_dirs', [
    'vendor', // Composer dir, faster to copy and update than start from scratch
    'web/{{wp_content_dir}}/languages', // WP languages dir
]);

/**
 * Shared files/dirs between deploys
 */
set('shared_files', [
    '.env',
    'web/.htaccess',
    'web/.user.ini',
]);
set('shared_dirs', [
    'web/{{wp_content_dir}}/uploads',
]);

/**
 * Writable dirs by web server
 */
set('writable_dirs', [
    'web/{{wp_content_dir}}/uploads',
]);

/**
 * Directories to be pulled/pushed, used in tasks/uploads.php. rsync format, so
 * directories should have a trailing slash to sync the whole dir
 */
set('sync_dirs', [
    'web/{{wp_content_dir}}/uploads/',
]);
