#ParabolCacheBundle
The ParabolCacheBundle provides view caching functionality for Symfony/Doctrine (for now only ORM) projects based on request/response events. All responses for GET-requests (expect actions defined as excluded in bundle config) are stored in separated and minified files in symfony cache directory.
The bundle also creates mapping for Doctrine queries in order to automatically clear only those cached files that are related to changing entity. 

Pros:
- Very easy installation
- Start working just after 3 simple installation steps
- Automatically creates cache for all not excluded actions
- Automatically creates mapping between actions and entities
- Automatically clear cached files related to Doctrine Entity after changes.
- Minify HTML output - useful for getting Google PageSpeed Tools better results ;)

##How it works
...

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
	excludePattern: ^\/(_|assetic|admin)
```

###Enable html-minifier
In symfony config.yml file put minifier_command parameter with path to html-minifier proceeded by path to node.js:
```
parabol_cache:
	minifier_command: /usr/local/bin/node /usr/local/bin/html-minifier
```
or for better project movability in parameters.yml 
```
parameters:
	minifier_command: /usr/local/bin/node /usr/local/bin/html-minifier
```
You can modify minified output by change minifier_command_params but keep in mind that the value must include the keywords: :target and :source

###Other minifier

If you want to use an other minfier just put right path in minifier_command and proper command parameters in minifier_command_params.


###Excluding actions from caching
To exclude some actions from caching put their names (Bundle:Controller:action) into exclude array
```
parabol_cache:
	exclude: [AppBundle:Default:index, AppBundle:Other:show]
```
