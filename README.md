# Deployer Bedrock Recpie

A (fairly opinionated) [Deployer](https://deployer.org) recpie to deploy a [Bedrock](https://github.com/roots/bedrock) based WordPress site. Also now supports Drupal deployments, using Drush for post-deploy actions.

Inspired by [capistrano-wpcli](https://github.com/lavmeiker/capistrano-wpcli)

## Usage

Include the following in your `deployer.php` file
```php
require_once dirname(DEPLOYER_DEPLOY_FILE) . '/vendor/autoload.php';

require_once 'recipe/bedrock.php';
```

Or for multisite installations
```php
require_once dirname(DEPLOYER_DEPLOY_FILE) . '/vendor/autoload.php';

require_once 'recipe/bedrock-multisite.php';
```

Or now Drupal (the auto-load file should put this package's "recipe" folder ahead of Deployer's in the `include_path`, so you will get this one rather than the contrib recpie)
```php
require_once dirname(DEPLOYER_DEPLOY_FILE) . '/vendor/autoload.php';

require_once 'recipe/drupal.php';
```

And then define your hosts and override any variables required

## Future Functionality

 - Fully handle multisite DB search/replace
 - Theme asset building (local or remote)
