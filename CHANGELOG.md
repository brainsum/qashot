#Changelog

## 2017.08.14
* Update scripts so composer is managed by docker.
* Revert docker from 7.1 to 7.0 PHP
* Note: From now on, composer should be used from docker. For this, fixing permissions is advised:
    * sudo chown 82:82 vendor/ web/core/ web/modules/contrib/ web/themes/contrib/ -R
