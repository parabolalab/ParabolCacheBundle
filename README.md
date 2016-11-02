## Installation
###1) Install via Composer:
```
composer require parabol/parabol-cache-bundle 
```
###2) Enable bundle in app/AppKernel.php adding in registerBundles():
```
new Parabol\CacheBundle\ParabolCacheBundle()
```
###3) Change default Doctrine EntityManager by adding to app/config/config.yml:
```
parameters:
	doctrine.orm.entity_manager.class: Parabol\CacheBundle\Doctrine\ORM\EntityManager
```

###Congratulation your HTML Cache System sholde be working.

