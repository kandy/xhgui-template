# Install XHProf profiler and XHGui on Magento Commerce Cloud

## Step 1: Install XHGui

### Deploy XHGui

On one of integration environment push the template from https://github.com/kandy/xhgui-template.git to deploy XHGui app
```
git remote add xhgui https://github.com/kandy/xhgui-template.git
git fetch xhgui
git push origin refs/remotes/xhgui/main:refs/heads/{integration_branch-name}
```

Get the URL of environment $url (example: http://master-7rqtwti-6qamweaqmyxdk.eu-4.magentosite.cloud)


### Add configuration 

You need to set environment variables from the table below

| Env Name                | Env Value         | Type of environment                                                               |
|-------------------------|-------------------|-----------------------------------------------------------------------------------|
| env:XHGUI_UPLOAD_TOKEN  | {UID}             | on global levels or for xhgui integration and any environment you want to profile | 
| env:XHGUI_UPLOAD_URL    | {$url}/run/import | any environment you want to profile                                               |

You can use `magento-cloud vset {Env Name} {Env Value}` cli command or Platform UI.

To add an environment variable from UI:

1. In the _Project Web UI_, click the configuration icon in the upper left corner.

2. In the _Configure Project_ view, click the **Variables** tab.

3. Click **Add Variable**.

4. In the _Name_ field, enter `{Env Name}`.

5. In the _Value_ field, add the following `{Env Value}`

6. Click **Add Variable**.

## Step 2: Setup XHprof

[Checkout project](https://devdocs.magento.com/cloud/before/before-setup-env-2_clone.html) that you have to profile 

1. Copy to the root of your project:
   * `profiler.php` file from the [gist](https://gist.githubusercontent.com/kandy/7d5716389463c406beb5f9f24d2de3e3/raw/fe3e6fb5a0d1ba6d84b6c3b7c7804dbe937699c3/profiler.php) 
   * `xhprof.so` 
     * Can be get by running of command. 
        ```bash
         docker run  --rm  -v $(cwd):/target -t php:8.1.0-zts bash -c "pecl install xhprof && cp \$(php -r 'echo ini_get(\"extension_dir\");')/xhprof.so /target"`) 
2. Add `extension="${MAGENTO_CLOUD_APP_DIR}/xhprof.so"` to the end of `php.ini` file
```ini
; php.ini

;
; Increase PHP memory limit
;
memory_limit = 1G

;
; enable resulting html compression
;
zlib.output_compression = on

;
; Increase realpath cache size
;
realpath_cache_size = 32k

;
; Increase realpath cache ttl
;
realpath_cache_ttl = 7200

;
; Multi store support
;
auto_prepend_file = /app/magento-vars.php

;
; Increase max input variables value
;
max_input_vars = 10000

;
; Setup the session garbage collector
;
session.gc_probability = 1

;
; Setup opcache configuration
;
opcache.validate_timestamps = 0
opcache.blacklist_filename="${MAGENTO_CLOUD_APP_DIR}/op-exclude.txt"
opcache.max_accelerated_files=16229
opcache.consistency_checks=0


; enable xhprof

extension="${MAGENTO_CLOUD_APP_DIR}/xhprof.so" ;<<------
```

3. Add `require  __DIR__ . '/profiler.php';` to the end of  `magento-vars.php` file
```php
<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Enable, adjust and copy this code for each store you run
 *
 * Store #0, default one
 *
 * if (isHttpHost("example.com")) {
 *    $_SERVER["MAGE_RUN_CODE"] = "default";
 *    $_SERVER["MAGE_RUN_TYPE"] = "store";
 * }
 *
 * @param string $host
 * @return bool
 */
function isHttpHost(string $host)
{
    if (!isset($_SERVER['HTTP_HOST'])) {
        return false;
    }
    return $_SERVER['HTTP_HOST'] === $host;
}

require  __DIR__ . '/profiler.php'; // <<-----

```

and push this changes
```bash
git add profiler.php xhprof.so php.ini magento-vars.php
git commit -m "Enable XHProf"
git push
```

## Step 3: Profiling

Add XHPROF=1 to URL, cookies or ENV variable to profile page or script
like
```bash
curl https://shop.example.com/contact?XHPROF=1
```
```bash
XHPROF=1 bin/magento indexer:reindex
```

Review recent profiles in XHGui application
