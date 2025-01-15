<?php

/**
 * Bedrock Multisite Deployer Recpie
 *
 * Inherits most everything from the base bedrock recpie, but sets "base" URLs,
 * and the DB tasks will check for this recpie to do further search/replace
 *
 * @author            Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright         2024 BC Libraries Cooperative
 * @license           MIT
 */

namespace Deployer;

add('recipes', ['bedrock-multisite']);

require_once __DIR__ . '/bedrock.php';

/**
 * Configure "base" subdomain URL structure
 *
 * Does not currently work for sub-folder installs
 */
set('base_url', '{{application}}.com');
set('local_base_url', '{{application}}.test');
set('url', '{{application}}.{{base_url}}');
set('local_url', '{{application}}.{{local_base_url}}');
