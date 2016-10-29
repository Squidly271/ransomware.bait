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
$settings = readSettingsFile();
smbReadOnly($settings);

?>
