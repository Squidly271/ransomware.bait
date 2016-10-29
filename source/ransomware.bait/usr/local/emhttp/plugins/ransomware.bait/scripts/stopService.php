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
    logger("Stopping the $description ($pid)");
    # get child PIDs
    exec("pgrep -P $pid",$output);

#    posix_kill($pid,9);
    exec("kill -9 $pid > /dev/null 2>&1");
    for ( $i = 0; $i < 100; $i++ ) {
      if ( isdir("/proc/$pid") ) {
        sleep(1);
      } else {
        logger("$description Stopped");
        @unlink($pidFile);
        $killFlag = true;
        break;
      }
    }
    
    if ( ! $killFlag ) {
      logger("For some reason, $description is unable to be stopped...");
    }

    foreach ($output as $childPID) {
      file_put_contents("/tmp/ransomware/tempPID",$childPID);
      killPID("/tmp/ransomware/tempPID","Child Processes");
    }
  } else {
    logger("$description not running");
  }

}

function rogueKill($path,$description,$pidFile=false) {
  exec("ps aux | grep $path | grep -v grep",$output);
  foreach ($output as $line) {
    $line = preg_replace("/[[:blank:]]+/"," ",$line);
    $lineParse = explode(" ",$line);
    file_put_contents("/tmp/ransomware/tempPID",$lineParse[1]);
    killPID("/tmp/ransomware/tempPID",$description);
  }
  if ( $pidFile ) {
    @unlink($pidFile);
  }
}

exec("mkdir -p /tmp/ransomware/");
file_put_contents($ransomwarePaths['stoppingService'],"stopping");
@unlink($ransomwarePaths['detected']);

killPID($ransomwarePaths['PID'],"ransomware protection service");
killPID($ransomwarePaths['deletePID'],"ransomware deletion process");
killPID($ransomwarePaths['deleteBaitSharePID'],"ransomware share deletion process");
killPID($ransomwarePaths['createSharePID'],"ransomeware bait share creation process");
killPID($ransomwarePaths['baitShareCountPID'],"ransomware bait share count process");
killPID($ransomwarePaths['sharePID'],"ransomware bait share monitor process");

# now do the emergency kill routines just in case somehow somewhere the main processes are still running

rogueKill("/usr/local/emhttp/plugins/ransomware.bait/scripts/backgroundmonitor.php","rogue ransomware file process",$ransomwarePaths['PID']);
rogueKill("/usr/local/emhttp/plugins/ransomware.bait/scripts/backgroundShareMonitor.php","rogue ransomware share process",$ransomwarePaths['sharePID']);
rogueKill("/usr/local/emhttp/plugins/ransomware.bait/scripts/deleteBaitShare.php","rogue share deletion process",$ransomwarePaths['deleteBaitSharePID']);
rogueKill("/usr/local/emhttp/plugins/ransomware.bait/scripts/deleteBait.php","rogue file deletion process",$ransomwarePaths['deletePID']);
rogueKill("/usr/local/emhttp/plugins/ransomware.bait/scripts/countBaitShares.php","rogue share count process",$ransomwarePaths['baitShareCountPID']);

# if the tmp file exists, service is being stopped prior to completion, so save the damn file so that the bait doesn't get orphaned
if ( isfile("/tmp/ransomware/filelist") ) {
  copy("/tmp/ransomware/filelist",$ransomwarePaths['filelist']);
}
@unlink($ransomwarePaths['shareStatus']);
@unlink($ransomwarePaths['startupStatus']);
@unlink($ransomwarePaths['stoppingService']);
?>
