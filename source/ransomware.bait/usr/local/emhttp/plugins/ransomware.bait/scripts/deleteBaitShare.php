#!/usr/bin/php
<?
#########################################################
#                                                       #
# Ransomware Protection copyright 2016, Andrew Zawadzki #
#                                                       #
#########################################################

require_once("/usr/local/emhttp/plugins/ransomware.bait/include/helpers.php");
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");

if ( isfile($ransomwarePaths['deleteBaitSharePID']) ) {
  logger("It appears that a bait share deletion process is in progress.  Exiting");
  exit();
}
if ( ! isfile($ransomwarePaths['baitShares']) ) {
  logger("It appears that no bait shares are present.  Exiting");
  exit();
}

file_put_contents($ransomwarePaths['deleteBaitSharePID'],getmypid());

$baitShares = explode("\n",file_get_contents($ransomwarePaths['baitShares']));

foreach ($baitShares as $share) {
  if ( ! trim($share) ) { continue; }
  $deleteShare = escapeshellarg($share);
  echo "Deleting $deleteShare\n";
  file_put_contents($ransomwarePaths['shareStatus'],"Deleting $share");
  exec("rm -rf $deleteShare");
}
logger("Deleted ".count($baitShares)." bait shares");
@unlink($ransomwarePaths['shareStatus']);
@unlink($ransomwarePaths['baitShares']);
@unlink($ransomwarePaths['deleteBaitSharePID']);
@unlink($ransomwarePaths['baitShareCount']);

?>