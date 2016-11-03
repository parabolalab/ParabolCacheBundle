## Installation
###1) Install via Composer:
```
composer require parabol/parabol-cache-bundle 
```
###2) Enable bundle in app/AppKernel.php by adding in registerBundles():
```
new Parabol\CacheBundle\ParabolCacheBundle()
```
###3) Change default Doctrine EntityManager by adding to app/config/config.yml:
```
parameters:
	doctrine.orm.entity_manager.class: Parabol\CacheBundle\Doctrine\ORM\EntityManager
```

###Congratulation your HTML Cache System should be working now.

## Configuration

###Default bundle configuration
```
parabol_cache:
	cache_dev: false
	minifier_command:
	minifier_command_params: -o :target :source --case-sensitive --collapse-boolean-attributes  --collapse-inline-tag-whitespace --collapse-whitespace --html5 --keep-closing-slash --remove-attribute-quotes --remove-comments --remove-empty-attributes --use-short-doctype --minify-css --minify-js
	exclude: []
```