#!/bin/bash

archiveCreationTempFolder="/var/tmp/"
archiveName="site.tar.gz"
dbDumpPath="/var/www/your_site_database_dump_path.sql"

# creating postgresql db dump to site folder
# chould be create file .pgpass in /root/.pgpass
# with text: localhost:your_db_port:your_dbname:your_username:your_username_password
create_db_dump () {
	pg_dump --format=c --file=${dbDumpPath} --host=localhost --username=your_username --dbname=your_dbname --encoding=utf8
	echo "DB dump created in ${dbDumpPath}" 
}

# copy site archive to yandex disk
create_site_archive () {
	tar --exclude='/your-should-be-exclude-folder-1' --exclude='/your-should-be-exclude-folder-2' -czf ${archiveCreationTempFolder}${archiveName} /var/www
	echo "Site archive created in ${archiveCreationTempFolder}/${archiveName}" 
}

# remove db dump from site folder
remove_working_archive () {
	rm ${dbDumpPath}
	echo "Temp DB dump removed in ${dbDumpPath}" 
}


# main action
echo "Backuping start..."
create_db_dump
create_site_archive
echo "Backuping end."
