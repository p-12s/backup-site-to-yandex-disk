# backup-site-to-yandex-disk

### Crontab job (run the backup scripts at 1:00am)
0 1 * * * php /path_to_script/backup_script_name.php >> /path_to_log/work_backup.txt 2>&1

### Looking resources
https://yandex.ru/dev/disk/poligon/#!//v1/disk/resources/GetResource  
https://snipp.ru/php/disk-yandex#link-udalenie-fayla-ili-papki  

### Work tasks
- [x] Success work script
- [ ] Refactor with classes (OOP) and put to composer package
- [ ] Rewrite script on some other language
