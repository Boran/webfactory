
Webfactory UI: Drupal 7 version
Module to interface to the Docker API, allowing operations on containers.



You need:
- System: Ubuntu 14.04+ docker
- this module+ webfact feature
- theme function for status fields


Default Settings
----------------
To change the default settings, eedit settings.php

Base image for creating containers:
$conf['webfact_cont_image']= 'boran/drupal';

Docker API URL:
$conf['webfact_dserver']   = 'http://dock.example.ch:2375';

Domain name to postfix to all hostnames.
$conf['webfact_fserver']   = 'webfact.example.ch';

$conf['webfact_loglines']  = '90';




Programming notes
- tested with API v1.14
- The guzzle http client is used for easiere porting with Drupal8, hence the dependancy on the composer_manager module


TODO
- clean up test code for bootstrap and angularjs e.g. /bob, sites/1/2 and /webang


