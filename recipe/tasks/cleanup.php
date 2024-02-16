<?php

/**
 * Deployer Tasks Cleanup
 *
 * Cleans up some tasks that are added automatically by the `common` recipe, and
 * which we don't want or need them
 *
 * @author            Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright         2024 BC Libraries Cooperative
 * @license           MIT
 */

namespace Deployer;

$deployer = Deployer::get();
$tasks = $deployer->tasks->all();
$remove_prefixes = ['provision', 'logs:caddy'];

foreach ($tasks as $task_name => $task) {
    if (
        !empty(array_filter(
            $remove_prefixes,
            fn ($remove_prefix) => strpos($task_name, $remove_prefix) === 0
        ))
    ) {
        $deployer->tasks->remove($task_name);
    }
}
