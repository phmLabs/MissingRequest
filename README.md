# MissingRequest

## Install
```curl -O -LSs http://pharchive.phmlabs.com/archive/phmLabs/MissingRequest/current/Missing.phar && chmod +x Missing.phar```


## Example

run - Runs a test
```php bin/Missing.php run example/requests.list /tmp/test.xml```

info - Returns all called request urls
```php bin/Missing.php info http://www.amilio.de```

create - creates a config file with all called requests
```php bin/Missing.php create http://www.amilio.de /tmp/amilio.yml```