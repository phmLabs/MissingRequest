# MissingRequest

MissingRequest is a tool for checking if given urls are producing a defined set of http requests.

The tool is based on phantomJS and is able to execute javascript so you can be sure *all* the requests are called.

## Installation
Installation of MissingRequest is easy. Just download the phar archive and run the tool.

```
curl -O -LSs http://pharchive.phmlabs.com/archive/phmLabs/MissingRequest/current/Missing.phar && chmod +x Missing.phar
```

Additionally phantomJS must be installed. If not already done you can find the installation guide here: http://phantomjs.org/download.html.

## Commands

### run
The run command runs checks if a given list of urls produce the right requests.

*Example*
```
php bin/Missing.php run example/requests.list /tmp/test.xml
```


### info

The info command can be used to show all requests an url triggers when called.

*Example*
```
php bin/Missing.php info http://www.amilio.de
```

### create

The create command is used to create a config file. It calls an url an adds all triggered requests to the given yaml file. Afterwards you can remove the optional requests.

*Example*
```
php bin/Missing.php create http://www.amilio.de /tmp/amilio.yml
```

## Configuration

*Example*

```yml
# amilio_example.yml

urls:
  startpage:
    url: http://www.amilio.de
    requests:
      - http://www.google.com
      - http://www.amilio.de
      - www.(.*).de
  blog:
    url: http://www.amilio.de/blog/2015/
    requests:
      - http://www.amilio.de
      - www.(.*).de

```