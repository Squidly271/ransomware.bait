#!/usr/bin/php
<?PHP
#########################################################
#                                                       #
# Ransomware Protection copyright 2016, Andrew Zawadzki #
#                                                       #
#########################################################

require_once("/usr/local/emhttp/plugins/ransomware.bait/include/helpers.php");
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");

if ( isfile($ransomwarePaths['smbReadOnlyProcess']) ) {
  exit;
}
if ( isdir("/boot/config/plugins/ransomware.bait/shareBackup") ) {
  logger("Double attack detected.  Possible misconfigured settings allowing a share (downloads?) to be deleted locally");
  exit;
}
$settings = readSettingsFile();
smbReadOnly($settings);

?>
