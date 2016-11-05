#ParabolCacheBundle
The ParabolCacheBundle provides view caching functionality for Symfony/Doctrine (for now only ORM) projects based on requests/responses events. All actions responses, excluding these not permitted in config, are stored in separated and minified files in symfony cache directory.
The bundle also creates mapping for Doctrine queries to automatically clear only those cached files that are related to changed entities. 

Pros:
- Very simple installation
- Fully operational just after 3 simple installation steps
- Automaticlly creates cache for all not excluded actions
- Automaticlly creates mapping between actions and entities
- Automaticlly clear cached files related to Doctrine Entity after changes.
- Minify HTML output - useful for Google PageSpeed Tools better results ;)

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

###Congratulations, your HTML Cache System should be working now.


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
In symfony config.yml file put minifier_command parameter with path to html-minifier proceded by path to node.js:
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

If you want to use some other minfier just put right path in minifier_command and proper command parameters in minifier_command_params.


###Excluding actions from caching
To exclude some actions from caching put their names (Bundle:Controller:action) into exclude array
```
parabol_cache:
	exclude: [AppBundle:Default:index, AppBundle:Other:show]
```
