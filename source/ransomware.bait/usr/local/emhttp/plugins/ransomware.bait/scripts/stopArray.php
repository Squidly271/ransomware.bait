#!/usr/bin/php
<?PHP
$pid = @file_get_contents("//var/run/ransomware.bait.pid");
if ( $pid ) {
  exec("kill -9 $pid");
}
#exec("wget -qO /dev/null http://localhost:$(lsof -nPc emhttp | grep -Po 'TCP[^\d]*\K\d+')/update.htm?cmdStop=Stop");
exec("wget /dev/null  http://localhost/update.htm?cmdStop=Stop");

?>

