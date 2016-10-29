#!/usr/bin/php
<?PHP
#########################################################
#                                                       #
# Ransomware Protection copyright 2016, Andrew Zawadzki #
#                                                       #
#########################################################

require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/helpers.php");

$allSettings = readSettingsFile();

if ( isfile($ransomwarePaths['deleteProgress']) ) {
  logger("Deletion already in progress");
  exit();
}
if ( ! isdir("/mnt/user") ) {
  logger("Array not started... Skipping Deletion Of Bait Files");
  exit();
}
file_put_contents($ransomwarePaths['deleteProgress'],"in progress");
file_put_contents($ransomwarePaths['startupStatus'],"Deleting previous bait files");
logger("Deleting previously set ransomware bait files");

$preserveMTime = $allSettings['baitFile']['preserveMTime'] == true;

$filelist = @file_get_contents("/boot/config/plugins/ransomware.bait/filelist");
$totalFiles = 0;
if ( $filelist ) {
  $allfiles = explode("\n",$filelist);
  foreach ( $allfiles as $baitFile) {
    if ( isfile($baitFile) ) {
      if ( $preserveMTime ) {
        $dir = $baitFile;
        while (true) {
          $dir = dirname($dir);
          if ($dir == "/" || $dir == "." ) {
            break;
          }
          if ( $dirArray[$dir] ) {
            continue;
          }
          echo $dir."\n";
          $tmpArray['dir'] = $dir;
          $tmpArray['mtime'] = filemtime($dir);
          $dirArray[$dir] = $tmpArray;
        }
      }
      baitStatus("Deleting $baitFile");
      @unlink($baitFile);
      ++$totalFiles;
    }
  }
}
if ( $preserveMTime ) {
  file_put_contents("/tmp/ransomware/tmpScript.sh","#!/bin/bash\n");
  $allDisks = array_diff(scandir("/mnt"),array("user","user0","disks",".",".."));
  print_r($dirArray);
  foreach ($dirArray as $dir) {
    foreach ( $allDisks as $disk) {
      $testPath = str_replace("/mnt/user","/mnt/$disk",$dir['dir']);
      if ( isdir($testPath) ) {
        file_put_contents("/tmp/ransomware/tmpScript.sh","touch ".escapeshellarg($testPath)." -d @".$dir['mtime']."\n", FILE_APPEND);
      }
    }
  }
  exec("chmod +x /tmp/ransomware/tmpScript.sh");
  exec("/tmp/ransomware/tmpScript.sh");
} else {
  @unlink("/tmp/ransomware/tmpScript.sh");
}

clearBaitStatus();

logger("$totalFiles Bait Files Deleted");
@unlink("/boot/config/plugins/ransomware.bait/filelist");
@unlink($ransomwarePaths['deleteProgress']);
@unlink($ransomwarePaths['numMonitored']);

?>
