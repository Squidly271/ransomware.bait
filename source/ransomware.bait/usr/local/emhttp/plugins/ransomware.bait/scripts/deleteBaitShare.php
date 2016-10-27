#!/usr/bin/php
<?
#########################################################
#                                                       #
# Ransomware Protection copyright 2016, Andrew Zawadzki #
#                                                       #
#########################################################

require_once("/usr/local/emhttp/plugins/ransomware.bait/include/helpers.php");
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");

$unRaidVars = my_parse_ini_file("/var/local/emhttp/var.ini");
if ( strtolower($unRaidVars['mdState']) != "started" ) {
  logger("Array Not Started.  Exiting");
  exit;
}

if ( isfile($ransomwarePaths['deleteBaitSharePID']) ) {
  logger("It appears that a bait share deletion process is in progress.  Exiting");
  exit();
}
if ( ! isdir("/mnt/user") ) {
  logger("User Shares Must be enabled to use this plugin");
  exit;
}

file_put_contents($ransomwarePaths['deleteBaitSharePID'],getmypid());

$baitShares = explode("\n",@file_get_contents($ransomwarePaths['baitShares']));

$count = 0;
if ( is_array($baitShares) ) {
  foreach ($baitShares as $share) {
    if ( ! trim($share) ) { continue; }
    $deleteShare = escapeshellarg($share);
    echo "Deleting $deleteShare\n";
    logger("Deleting $deleteShare");
    shareStatus("Deleting $share");
    for ($retry = 0; $retry < 5; $retry++ ) {
      if ( isdir($share) ) {
        exec("rm -rf $deleteShare",$output,$return);
        if ( $return ) {
          logger("Error on deleting $deleteShare... Retrying in 20 seconds");
          shareStatus("Error on deleting $deleteShare...Retrying in 20 seconds");
          sleep(20);
        } else {
          break;
        }
      } else {
        break;
      }
    }
    ++$count;
  }
}
logger("Deleted $count bait shares");
clearShareStatus();
@unlink($ransomwarePaths['baitShares']);
@unlink($ransomwarePaths['deleteBaitSharePID']);
@unlink($ransomwarePaths['baitShareCount']);

?>