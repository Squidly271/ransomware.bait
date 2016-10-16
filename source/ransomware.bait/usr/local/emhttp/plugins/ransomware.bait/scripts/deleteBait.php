#!/usr/bin/php
<?PHP
#########################################################
#                                                       #
# Ransomware Protection copyright 2016, Andrew Zawadzki #
#                                                       #
#########################################################

require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");

function logger($string) {
  shell_exec('logger ransomware protection:"'.$string.'"');
}
function isfile($filename) {
  clearstatcache();
  return is_file($filename);
}
function isdir($path) {
  clearstatcache();
  return is_dir($path);
}

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
$filelist = @file_get_contents("/boot/config/plugins/ransomware.bait/filelist");
$totalFiles = 0;
if ( $filelist ) {
  $allfiles = explode("\n",$filelist);
  foreach ( $allfiles as $baitFile) {
    if ( isfile($baitFile) ) {
      @unlink($baitFile);
      ++$totalFiles;
    }
  }
}
@unlink($ransomwarePaths['startupStatus']);
logger("$totalFiles Bait Files Deleted");
@unlink("/boot/config/plugins/ransomware.bait/filelist");
@unlink($ransomwarePaths['deleteProgress']);
@unlink($ransomwarePaths['numMonitored']);

?>
