<?php

define('ARCHIVE_NAME', 'site.tar.gz');
define('SITE_ARCHIVE_PATH', '/var/tmp/site.tar.gz');
define('DB_BACKUP_PATH', '/var/www/your_site_database_dump_path.sql');
define('SITE_ROOT', '/var/www/');
define('BASH_SCRIPT_PATH', './create_site_archive.sh');
define('UPLOAD_API_URL', 'https://cloud-api.yandex.net/v1/disk/resources/upload?');
define('API_TOKEN', 'API_TOKEN');
define('BACKUP_FOLDER_IN_YANDEX_DISK', '/BACKUP_FOLDER_IN_YANDEX_DISK/');
define('REQUIRED_AMOUNT_OF_FREE_SPACE_IN_GB', 5);
define('API_DISK', 'https://cloud-api.yandex.net/v1/disk/');
define('NUMBER_OF_BACKUPS_STORED', 100);

/*
 * Подготовка архива сайта:
 * создание бекапа БД в директории /var/www, а после архивация /var/www
 * */
function createSiteArchive() {
    $currentDir = getcwd();
    chdir(SITE_ROOT);
    if (!is_executable(BASH_SCRIPT_PATH)) {
        throw new Exception('File should be executable: '. BASH_SCRIPT_PATH);
    }

    shell_exec(BASH_SCRIPT_PATH);

    chdir($currentDir);
    if(!file_exists(DB_BACKUP_PATH)) {
        throw new Exception('DB backup file does not exists in '. DB_BACKUP_PATH);
    }
    if(!file_exists(SITE_ARCHIVE_PATH)) {
        throw new Exception('Site archive does not exists in '. SITE_ARCHIVE_PATH);
    }
}

/*
 * Загрузка архива на Яндекс.Диск
 * */
function uploadArchiveToYandexDisk($date) {
    echo "\nUpload to Yandex.Disk start: ". date("h:i:sa");
    $destinationPathInYandexDisk = BACKUP_FOLDER_IN_YANDEX_DISK. $date ."/";
    createFolderOnYandexDisk($destinationPathInYandexDisk);

    // Запрашиваем URL для загрузки.
    $ch = curl_init(UPLOAD_API_URL .'path=' . urlencode($destinationPathInYandexDisk . basename(SITE_ARCHIVE_PATH)));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: OAuth '. API_TOKEN));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $res = curl_exec($ch);
    curl_close($ch);

    $res = json_decode($res, true);
    if (!empty($res['error'])) {
        throw new Exception("\nError while getting download URL for archive upload to Yandex.Disk\n". $res['message']);
    }

    $fp = fopen(SITE_ARCHIVE_PATH, 'r');
    $ch = curl_init($res['href']);
    curl_setopt($ch, CURLOPT_PUT, true);
    curl_setopt($ch, CURLOPT_UPLOAD, true);
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize(SITE_ARCHIVE_PATH));
    curl_setopt($ch, CURLOPT_INFILE, $fp);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 201) {
        throw new Exception("\nError while download archive to Yandex Disk");
    }
    echo "\nUpload to Yandex.Disk end: ". date("h:i:sa");
}

function createFolderOnYandexDisk($path) {
    $ch = curl_init('https://cloud-api.yandex.net/v1/disk/resources/?path=' . urlencode($path));
    curl_setopt($ch, CURLOPT_PUT, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: OAuth ' . API_TOKEN));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $res = curl_exec($ch);
    curl_close($ch);
}

function checkBackupSuccessfulyUploadedInYandexDisk($findableFolderName, $requiredArchiveSize) {
    $findablePath = BACKUP_FOLDER_IN_YANDEX_DISK. $findableFolderName .'/';
    $fields = '_embedded.items.name,_embedded.items.type,_embedded.items.size';
    $limit = 100;

    $ch = curl_init('https://cloud-api.yandex.net/v1/disk/resources?path='. urlencode($findablePath) .'&fields='. $fields  .'&limit='. $limit);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: OAuth ' . API_TOKEN));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $res = curl_exec($ch);
    curl_close($ch);

    $res = json_decode($res, true);

    if (!empty($res['error'])) {
        throw new Exception("\nError check file in Yandex.Disk by path: ". $findablePath ."\n". $res['message']);
    }

    if(count($res['_embedded']['items']) == 0 ||
        $res['_embedded']['items'][0]['type'] != 'file' ||
        $res['_embedded']['items'][0]['name'] != ARCHIVE_NAME ||
        $res['_embedded']['items'][0]['size'] != $requiredArchiveSize)
    {
        throw new Exception('Archive '. ARCHIVE_NAME .' in Yandex.Disk not found (or type, name and size does not equal as server archive file)');
    }
}

/*
 * Проверка достаточности свободного места на Я.Диске
 * */
function checkFreeSpaceOnYandexDiskExists() {
    $freeSpaceInGigaByte = getFreeSpaceOnYandexDisk();
    if ($freeSpaceInGigaByte < REQUIRED_AMOUNT_OF_FREE_SPACE_IN_GB) {
        throw new Exception("Free space is NOT enough: $freeSpaceInGigaByte Gb, need more");
    }
    echo "\nFree space enough: $freeSpaceInGigaByte Gb\n";
}

/*
 * Получение инфо, сколько еще свободного места на Яндекс.Диске (в Gb)
 * */
function getFreeSpaceOnYandexDisk() {
    $ch = curl_init(API_DISK);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: OAuth ' . API_TOKEN));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $res = curl_exec($ch);
    curl_close($ch);

    $res = json_decode($res, true);
    if (!empty($res['error'])) {
        echo $res['message'];
        throw new Exception("Error while check free space on Yandex Disk");
    }

    $totalSpace = $res['total_space'];
    $usedSpace = $res['used_space'];
    $freeSpaceInByte = $totalSpace - $usedSpace;
    $freeSpaceInGigaByte = $freeSpaceInByte / (1024*1024*1024);

    return round($freeSpaceInGigaByte,2);
}

function cleanOldArchiveFromYandexDisk() {
    $folderNamesToDelete = getOldFolderNamesToDelete();
    if (empty($folderNamesToDelete)) {
        return;
    }
    foreach ($folderNamesToDelete as &$value) {
        deleteFolderOnYandexDisk($value);
    }
    waitUntilFoldersMoveToTrash();
    cleanTrashFolderOnYandexDisk();
}

function waitUntilFoldersMoveToTrash() {
    sleep(60);
}

function getOldFolderNamesToDelete() {
    $ch = curl_init('https://cloud-api.yandex.net/v1/disk/resources?path='. urlencode(BACKUP_FOLDER_IN_YANDEX_DISK) .'&fields=_embedded.items.name&limit=200');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: OAuth ' . API_TOKEN));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    $res = json_decode($res, true);

    if (!empty($res['error'])) {
        throw new Exception("Error get old folders in Yandex.Disk: ". $res['message']);
    }

    if (count($res['_embedded']['items']) == 0) {
        return array();
    }

    $existsFolderNames = array_reverse($res['_embedded']['items']);
    $folderNamesForDelete = array();
    for ($i = 0; $i < count($existsFolderNames); $i++) {
        if ($i >= NUMBER_OF_BACKUPS_STORED) {
            array_push($folderNamesForDelete, $existsFolderNames[$i]['name']);
        }
    }

    return $folderNamesForDelete;
}

function deleteFolderOnYandexDisk($deleteFolder) {
    $deleteFolderPath = BACKUP_FOLDER_IN_YANDEX_DISK. $deleteFolder .'/';

    $ch = curl_init('https://cloud-api.yandex.net/v1/disk/resources?path=' . urlencode($deleteFolderPath) . '&permanently=true');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: OAuth ' . API_TOKEN));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!in_array($http_code, array(202, 204))) {
        throw new Exception('Failed to delete old archive folder '. $deleteFolder);
    }
    echo 'Папка '. $deleteFolderPath .' на Яндекс.Диске удалена';
}

function cleanTrashFolderOnYandexDisk() {
    $ch = curl_init(API_DISK .'trash/resources' );
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: OAuth '. API_TOKEN));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 202) {
        throw new Exception('Error while cleaning trash folder on Yandex Disk');
    }
    echo "\nTrash folder on Yandex Disk successfully cleaned";
}



function cleanArchiveFromTmp() {
    if (file_exists(SITE_ARCHIVE_PATH)) {
        unlink (SITE_ARCHIVE_PATH);
    }
    if (file_exists(DB_BACKUP_PATH)) {
        unlink (DB_BACKUP_PATH);
    }
    if (file_exists(SITE_ARCHIVE_PATH) || file_exists(DB_BACKUP_PATH)) {
        throw new Exception('After sending backup to Yandex.Disk, failed to delete local archives' );
    }
    echo "\nSite archive and DB dump on local server successfully cleaned";
}


function getLocalCreatedArchiveSize() {
    if(!file_exists(SITE_ARCHIVE_PATH)) {
        throw new Exception('Site archive file does not exists in '. SITE_ARCHIVE_PATH);
    }
    $archiveSize = filesize(SITE_ARCHIVE_PATH);
    if($archiveSize <= 0) {
        throw new Exception('Site archive does not have size '. SITE_ARCHIVE_PATH);
    }
    return $archiveSize;
}


function run() {
    echo "\n";
    // запомним название папки, т.к. загрузка архива может затянуться на несколько часов
    $backupFolderName = date('Y-m-d');
	try {
        cleanArchiveFromTmp();
        checkFreeSpaceOnYandexDiskExists();
        createSiteArchive();
		uploadArchiveToYandexDisk($backupFolderName);
        checkBackupSuccessfulyUploadedInYandexDisk($backupFolderName, getLocalCreatedArchiveSize());
		cleanOldArchiveFromYandexDisk();
        cleanArchiveFromTmp();
	}
	catch (exception $e) {
        echo $e->getMessage();
	}
	echo "\n";
}

run();
