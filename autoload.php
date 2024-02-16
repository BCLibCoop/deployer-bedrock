<?php

/**
 * Bedrock Multisite Deployer Include Path Autoloader
 *
 * Adds the root directory to the include path, so that the recipe or
 * tasks can be included in a project's Deployer config the same as included
 * recipes.
 *
 * @author            Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright         2024 BC Libraries Cooperative
 * @license           MIT
 */

if (defined('DEPLOYER') && DEPLOYER) {
    set_include_path(__DIR__ . PATH_SEPARATOR . get_include_path());
}
