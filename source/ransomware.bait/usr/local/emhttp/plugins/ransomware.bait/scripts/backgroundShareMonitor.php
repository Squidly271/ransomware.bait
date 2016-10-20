#!/usr/bin/php
<?
#########################################################
#                                                       #
# Ransomware Protection copyright 2016, Andrew Zawadzki #
#                                                       #
#########################################################

require_once("/usr/local/emhttp/plugins/ransomware.bait/include/helpers.php");
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");

$allSettings = readSettingsFile();
$shareSettings = $allSettings['shareSettings'];
if ( $shareSettings['enableShareService'] != "true" ) {
  logger("Bait share service not enabled.  Exiting");
  exit;
}

if ( $shareSettings['shareRecreate'] == "true") {
  logger("Deleting previously set bait shares and recreating");
  exec("/usr/local/emhttp/plugins/ransomware.bait/scripts/deleteBaitShare.php");
}
exec("/usr/local/emhttp/plugins/ransomware.bait/scripts/createShareBait.php");
?>