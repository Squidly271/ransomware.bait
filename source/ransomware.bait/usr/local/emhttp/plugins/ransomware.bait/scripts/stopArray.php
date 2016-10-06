#!/usr/bin/php
<?PHP
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/helpers.php");
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");

$settings = readJsonFile($ransomwarePaths['settings']);
if ( ! $settings['unRaidPort'] ) { $settings['unRaidPort'] = 80; }

$pid = @file_get_contents("/var/run/ransomware.bait.pid");
if ( $pid ) {
  exec("kill -9 $pid");
}
#exec("wget -qO /dev/null http://localhost:$(lsof -nPc emhttp | grep -Po 'TCP[^\d]*\K\d+')/update.htm?cmdStop=Stop");
exec("wget /dev/null  http://localhost:".$settings['unRaidPort']."/update.htm?cmdStop=Stop");

?>

