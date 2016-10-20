#!/usr/bin/php
<?
#########################################################
#                                                       #
# Ransomware Protection copyright 2016, Andrew Zawadzki #
#                                                       #
#########################################################

require_once("/usr/local/emhttp/plugins/ransomware.bait/include/helpers.php");
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");

$unRaidVars = parse_ini_file("/var/local/emhttp/var.ini");
if ( strtolower($unRaidVars['mdState']) != "started" ) {
  logger("Array Not Started.  Exiting");
  exit;
}

if ( isfile($ransomwarePaths['deleteBaitSharePID']) ) {
  logger("It appears that a bait share deletion process is in progress.  Exiting");
  exit();
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
    file_put_contents($ransomwarePaths['shareStatus'],"Deleting $share");
    exec("rm -rf $deleteShare");
    ++$count;
  }
}
logger("Deleted $count bait shares");
@unlink($ransomwarePaths['shareStatus']);
@unlink($ransomwarePaths['baitShares']);
@unlink($ransomwarePaths['deleteBaitSharePID']);
@unlink($ransomwarePaths['baitShareCount']);

?>