Chiron - Sirio
=====

This plugin integrates a new module within the [Shopware](https://www.shopware.de) backend that allows for the integration 
of the Sirio Snippet and the configuration of the profiling content.

The plugin offers the following features:

* Prepend the Sirio Snippet to the head Tag of every page
* Use Twig syntax to insert variables and alter values

Requirements
-----
* Shopware >= 6.0

Installation
====
Download it from the shopware store and install it manually within the shopware backend.

Alternative Installation via composer
```
composer require chiron/sirio
```

After installation, use the following commands to install and activate the plugin in shopware
```
bin/console plugin:refresh
bin/console plugin:install --activate ChironSirio
```

or the following to update, if previously installed
```
bin/console plugin:refresh
bin/console plugin:update ChironSirio 
```


Additional Twig functions/filters
=====

uuid2bytes
-----

The Twig filter `|uuid2bytes` will convert uuid strings to binary format for the use in `dbquery` functions and the like.

```
{{ item.id|uuid2bytes }}
```

languageid
-----

Function that returns the current language id in binary format. For use in `dbquery` functions when fetching translations and the like.

```
{{ languageid }}
```

currencyiso
-----

Function that returns the 3 letter ISO code of the storefronts currency.

```
{{ currencyiso }}
```

getparam
-----

Function that returns a specific GET/POST/route parameter if available.

```
{{ getparam('id') }}
```
