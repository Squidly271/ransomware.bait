#!/usr/bin/php
<?PHP
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/helpers.php");

@unlink($ransomwarePaths['detected']);
$pid = @file_get_contents($ransomwarePaths['PID']);
if ($pid) {
  logger("Stopping the ransomware protection service");
  exec("kill -9 $pid");
} else {
  logger("Ransomware protection service not running");
}
@unlink($ransomwarePaths['PID']);

$filelist = @file_get_contents($ransomwarePaths['filelist']); 
if ( $filelist ) {
  logger("Deleting previously set ransomware bait files");
  $allFiles = explode("\n",$filelist);
  foreach ($allFiles as $baitFile) {
    if ( is_file($baitFile) ) {
      ++$totalDeleted;
 #     echo "$baitFile\n";
      unlink($baitFile);
    }
  }
  logger("Deleted $totalDeleted bait files");
  unlink("/boot/config/plugins/ransomware.bait/filelist");
} else {
  logger("No bait files were found");
}
?>
