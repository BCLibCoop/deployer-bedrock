<?php

/**
 * Base Deployer Recpie
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
require_once __DIR__ . '/tasks/php.php';
require_once __DIR__ . '/tasks/uploads.php';

add('recipes', ['bclc-base']);

/**
 * Get the environment we're targeting
 */
set('environment', fn() => get('labels', ['env' => ''])['env']);

/**
 * Set protected environments for DB actions
 */
set('protected_environments', ['production']);

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
set('backup_path', '{{deploy_path}}/backup');

/**
 * Writeable Dirs Settings
 */
set('writable_mode', 'chown');
set('writable_use_sudo', true);
set('writable_recursive', true);
set('http_user', 'www-data:www-data');
set('http_group', 'www-data');

/**
 * Add default localhost
 */
localhost()
    ->setLabels(['env' => 'development'])
    ->setDeployPath('.')
    ->set('release_path', '.')
    ->set('current_path', '.');
