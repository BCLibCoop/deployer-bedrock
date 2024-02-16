<?php

/**
 * Bedrock Deployer PHP Tasks
 *
 * @author            Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright         2024 BC Libraries Cooperative
 * @license           MIT
 */

namespace Deployer;

set('php_fpm_service', 'php{{php_version}}-fpm.service');

task('php:reload', function () {
    run('sudo /usr/bin/systemctl reload {{php_fpm_service}}');
})
    ->desc('Reload PHP-FPM Daemon');

task('php:restart', function () {
    run('sudo /usr/bin/systemctl restart {{php_fpm_service}}');
})
    ->desc('Restart PHP-FPM Daemon');
