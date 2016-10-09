#!/usr/bin/php
<?PHP
#########################################################
#                                                       #
# Ransomware Protection copyright 2016, Andrew Zawadzki #
#                                                       #
#########################################################

require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/helpers.php");

exec("mkdir -p /tmp/ransomware/");
file_put_contents($ransomwarePaths['stoppingService'],"stopping");
@unlink($ransomwarePaths['detected']);
$pid = @file_get_contents($ransomwarePaths['PID']);
if ($pid) {
  logger("Stopping the ransomware protection service");
  exec("kill -9 $pid > /dev/null 2>&1");
} else {
  logger("Ransomware protection service not running");
}
@unlink($ransomwarePaths['PID']);

# if the tmp file exists, service is being stopped prior to completion, so save the damn file so that the bait doesn't get orphaned
if ( isfile("/tmp/ransomware/filelist") ) {
  copy("/tmp/ransomware/filelist",$ransomwarePaths['filelist']);
}

/* $filelist = @file_get_contents($ransomwarePaths['filelist']); 
if ( $filelist ) {
  logger("Deleting previously set ransomware bait files");
  $allFiles = explode("\n",$filelist);
  foreach ($allFiles as $baitFile) {
    if ( isfile($baitFile) ) {
      ++$totalDeleted;
 #     echo "$baitFile\n";
      unlink($baitFile);
    }
  }
  logger("Deleted $totalDeleted bait files");
  unlink("/boot/config/plugins/ransomware.bait/filelist");
} else {
  logger("No bait files were found");
} */
@unlink($ransomwarePaths['stoppingService']);
?>
