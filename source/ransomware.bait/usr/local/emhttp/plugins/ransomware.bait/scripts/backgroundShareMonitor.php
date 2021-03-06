#!/usr/bin/php
<?
#########################################################
#                                                       #
# Ransomware Protection copyright 2016, Andrew Zawadzki #
#                                                       #
#########################################################

require_once("/usr/local/emhttp/plugins/ransomware.bait/include/helpers.php");
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");


################################################################################################

$unRaidVars = my_parse_ini_file("/var/local/emhttp/var.ini");
if ( strtolower($unRaidVars['mdState']) != "started" ) {
  logger("Array Not Started.  Exiting");
  exit;
}
if ( !isdir("/mnt/user") ) {
  logger("User Shares Must Be Enabled to use this plugin");
  exit;
}

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
if ( isfile($ransomwarePaths['sharePID']) ) {
  logger("Bait share monitor already running.  Exiting...");
  exit;
}
file_put_contents($ransomwarePaths['sharePID'],getmypid());

exec("/usr/local/emhttp/plugins/ransomware.bait/scripts/createShareBait.php");

if ( ! isfile($ransomwarePaths['baitShareFileList']) || ! isfile($ransomwarePaths['baitShares']) ) {
  logger("Something went wrong creating the bait shares.  Exiting...");
  @unlink($ransomwarePaths['sharePID']);
  exit;
}



while (true) {
# check that the bait shares still exist, as they are deleted in case of an attack

  unset($newShareList);
  $baitShareList = explode("\n",file_get_contents($ransomwarePaths['baitShares']));
  foreach ($baitShareList as $share) {
    if ( ! trim($share) ) { continue; }
    if ( isdir($share) ) {
      $newShareList .= "$share\n";
    }
  }
  $baitShareList = explode("\n",file_get_contents($ransomwarePaths['baitShares']));

  file_put_contents($ransomwarePaths['baitShares'],$newShareList);

  exec("/usr/local/emhttp/plugins/ransomware.bait/scripts/startBackgroundCount.sh");
  
# get the md5s for each bait file
  $filelist = explode("\n",file_get_contents($ransomwarePaths['baitShareFileList']));

  foreach ($filelist as $file) {
    if ( ! trim($file) ) continue;
    $md5Array[pathinfo($file,PATHINFO_EXTENSION)][pathinfo($file,PATHINFO_DIRNAME)] = pathinfo($file,PATHINFO_DIRNAME);
    $md5Array[pathinfo($file,PATHINFO_EXTENSION)]['md5'] = md5_file($file);
  }

  print_r($md5Array);
  logger("Starting Background Monitoring of Baitshares");

  @unlink($ransomwarePaths['shareEvent']);
  exec("inotifywait --fromfile ".$ransomwarePaths['baitShares']." -r -e move,delete,delete_self,move_self,close_write,attrib --format %w*%e*%f -o ".$ransomwarePaths['shareEvent']." 2>&1 | logger -i");
  $tmpEvent = @file_get_contents($ransomwarePaths['shareEvent']);
  if ( ! trim($tmpEvent) ) {
    logger("Something went wrong and inotify exited.  Exiting ransomware bait share protection");
    @unlink($ransomwarePaths['PID']);
    exit;
  }
  $event = explode("*",$tmpEvent);
  $eventFile = $event[0].$event[2];
  logger($event[1]);
  switch ($event[1]) {
    case "DELETE":
      stopEverything($eventFile,$allSettings['actions']);
      $stopFlag = true;
      break;
    case "MOVED_FROM":
      stopEverything($eventFile,$allSettings['actions']);
      $stopFlag = true;
      break;
    case "MOVED_TO":
      stopEverything($eventFile,$allSettings['actions']);
      $stopFlag = true;
      break;
    default:
      if ( ! isfile($eventFile) ) {
        stopEverything($eventFile,$allSettings['actions'],$allSettings['actions']);
        $stopFlag = true;
        break;
      }
      $eventExtension = pathinfo($eventFile,PATHINFO_EXTENSION);
      if ( ! is_array($md5Array[$eventExtension]) ) {   #ie we didn't put that file in there, so no problems with any mods to it.
        continue; 
      }
      if ( md5_file($eventFile) != $md5Array[$eventExtension]['md5'] ) {
        stopEverything($eventFile);
        $stopFlag = true;
        break;
      } else {
        logger("md5 matches on $eventFile.  Checking again in 1 second");
        sleep(1);
        if ( md5_file($eventFile) != $md5Array[$eventExtension]['md5'] ) {
          logger("md5 attack");
          stopEverything($eventFile,$allSettings['actions']);
          $stopFlag = true;
          break;
        } else {
          logger("md5 matches on $eventFile.  Remonitoring");
          $stopFlag = false;
          break;
        }
      }
      break;
  }
# now if an attack happened, smb is stopped other wise we can remonitor, but need to delete all the extension types

  if ( ! $stopFlag ) {
    continue;
  }
  #exit this shit if stop array is set
  if ( $allSettings['actions']['stopArray'] != "true" ) {
    logger("Deleting the affected shares");
    foreach ($baitShareList as $share) {
      if ( ! isdir($share) ) {
        continue;
      }
      if (startsWith($eventFile,$share) ) {
        logger("Deleting $share");
        file_put_contents($ransomwarePaths['shareStatus'],"Deleting $share");
        exec("rm -rf ".escapeshellarg($share));
        @unlink($ransomwarePaths['shareStatus']);
      }
    }
  } else {
    @unlink($ransomwarePaths['sharePID']);
    logger("Ransomware Bait Share Monitoring Exiting");
    exit();
  }  

  
}


?>