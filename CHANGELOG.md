#Changelog

## 2017.08.14
* Update scripts so composer is managed by docker.
* Revert docker from 7.1 to 7.0 PHP
* Note: From now on, composer should be used from docker. For this, fixing permissions is advised:
    * sudo chown 33:33 vendor/ web/core/ web/modules/contrib/ web/themes/contrib/ -R
    * sudo chown 33:33 private_files/ web/sites/default/files -R
    * sudo chown 33:33 composer.json composer.lock

## 2017.08.30
* Merged the docker_update branch into master
* When using docker, you have to use qashot.docker.localhost:8000 to reach the site
    * It was localhost:8000 in the older version
    * First time start will build a node image. If you don't have a strong machine, or don't want to wait just comment out the full backstop_node key from docker-compose.yml (and if needed, from docker-compose.prod.yml)
* You can now set the path to the backstopjs binary
    * See /admin/config/qa_shot/backstopjs_settings
    * Leave it empty if it's in the system path

## 2017.10.25
* Update core to 8.4
* # IMPORTANT! Before updating, disable the cors_ui module or the site will break!
