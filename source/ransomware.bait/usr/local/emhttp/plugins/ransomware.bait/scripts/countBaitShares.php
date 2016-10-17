#!/usr/bin/php
<?
#########################################################
#                                                       #
# Ransomware Protection copyright 2016, Andrew Zawadzki #
#                                                       #
#########################################################

require_once("/usr/local/emhttp/plugins/ransomware.bait/include/helpers.php");
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");

if ( isfile($ransomwarePaths['baitShareCountPID']) ) {
  logger("Bait share count process already in progress.  Exiting");
  exit();
}
if ( ! isfile($ransomwarePaths['baitShares']) ) {
  logger("Could not find any bait shares to inventory.  Exiting");
  exit();
}
file_put_contents($ransomwarePaths['baitShareCountPID'],getmypid());
file_put_contents($ransomwarePaths['baitShareCount'],"Calculating");

$allShares = @file_get_contents($ransomwarePaths['baitShares']);
$shares = explode("\n",$allShares);
foreach ($shares as $share) {
  getCount($share);
}
file_put_contents($ransomwarePaths['baitShareCount'],$totalFiles);
@unlink($ransomwarePaths['baitShareCountPID']);

###########################################################################

function getCount($folder) {
  global $totalFiles;
  
  if ( ! isdir($folder) ) {
    return;
  }
  $dirContents = scan($folder);
  $dirs = 0;
  foreach ($dirContents as $entry) {
    if ( isdir("$folder/$entry") ) {
      echo "$folder/$entry\n";
      ++$dirs;
      getCount("$folder/$entry");
    } else {
      if ( isfile("$folder/$entry") ) {
        ++$totalFiles;
      }
    }
  }
}
?>