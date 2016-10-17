#!/usr/bin/php
<?PHP
#########################################################
#                                                       #
# Ransomware Protection copyright 2016, Andrew Zawadzki #
#                                                       #
#########################################################

require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");

function logger($string) {
  shell_exec('logger ransomware protection:"'.$string.'"');
}
function isfile($filename) {
  clearstatcache();
  return is_file($filename);
}
function isdir($path) {
  clearstatcache();
  return is_dir($path);
}

function killPID($pidFile,$description) {
  $pid = @file_get_contents($pidFile);
  if ($pid) {
    logger("Stopping the $description");
    exec("kill -9 $pid > /dev/null 2>&1");
  } else {
    logger("$description not running");
  }
  @unlink($pidFile);
}

exec("mkdir -p /tmp/ransomware/");
file_put_contents($ransomwarePaths['stoppingService'],"stopping");
@unlink($ransomwarePaths['detected']);

killPID($ransomwarePaths['PID'],"ransomware protection service");
killPID($ransomwarePaths['deletePID'],"ransomware deletion process");
killPID($ransomwarePaths['createSharePID'],"ransomeware bait share creation process");
killPID($ransomwarePaths['baitShareCountPID'],"ransomware bait share count process");


@unlink($ransomwarePaths['detected']);

# if the tmp file exists, service is being stopped prior to completion, so save the damn file so that the bait doesn't get orphaned
if ( isfile("/tmp/ransomware/filelist") ) {
  copy("/tmp/ransomware/filelist",$ransomwarePaths['filelist']);
}

@unlink($ransomwarePaths['stoppingService']);
?>
