# Deployer Bedrock Recpie

A (fairly opinionated) [Deployer](https://deployer.org) recpie to deploy a [Bedrock](https://github.com/roots/bedrock) based WordPress site.

Inspired by [capistrano-wpcli](https://github.com/lavmeiker/capistrano-wpcli)

## Usage

Include the following in your `deployer.php` file
```
require_once dirname(DEPLOYER_DEPLOY_FILE) . '/vendor/autoload.php';

require_once 'recipe/bedrock.php';

// Also support for multisite
// require_once 'recipe/bedrock-multisite.php';
```

And then define your hosts and override any variables required

## Future Functionality

 - Fully handle multisite DB search/replace
 - Theme asset building (local or remote)
