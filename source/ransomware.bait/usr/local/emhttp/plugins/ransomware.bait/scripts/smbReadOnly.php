#!/usr/bin/php
<?PHP
#########################################################
#                                                       #
# Ransomware Protection copyright 2016, Andrew Zawadzki #
#                                                       #
#########################################################

if ( isfile($ransomwarePaths['smbReadOnlyProcess']) ) {
  exit;
}

require_once("/usr/local/emhttp/plugins/ransomware.bait/include/helpers.php");
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");

smbReadOnly();

@unlink($ransomwarePaths['smbReadOnlyProcess']);
?>
