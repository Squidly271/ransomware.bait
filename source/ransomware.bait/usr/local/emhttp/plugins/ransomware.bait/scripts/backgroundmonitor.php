#!/usr/bin/php
<?PHP
#########################################################
#                                                       #
# Ransomware Protection copyright 2016, Andrew Zawadzki #
#                                                       #
#########################################################

require_once("/usr/local/emhttp/plugins/ransomware.bait/include/helpers.php");
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");

function stopEverything($path) {
  global $settings, $ransomwarePaths;
  
  exec("/usr/bin/smbstatus",$output);
  if ( $settings['readOnlySMB'] == "true" ) { exec("/etc/rc.d/rc.samba stop"); }
  if ( $settings['readOnlyAFP'] == "true" ) { exec("/etc/rc.d/rc.atalk stop"); }
  if ( ($settings['readOnlySMB'] == "true") || ($settings['stopArray'] == "true") || ($settings['readOnlyAFP'] == "true") ) {
    smbReadOnly();
  }
/*   if ( $settings['stopSMB'] ) {
    logger("Stopping SMB");
    exec("/etc/rc.d/rc.samba stop");
  } */
  if ( $settings['stopArray'] == "true" ) {
    logger("Stopping AFP");
    exec("/etc/rc.d/rc.atalk stop");    
    logger("Stopping NFS");
    exec("/etc/rc.d/rc.nfsd stop");
  }
/*   if ( $settings['stopAFP'] ) {
    logger("Stopping AFP");
    exec("/etc/rc.d/rc.atalk stop");
  }
  if ( $settings['stopNFS'] ) {
    logger("Stopping NFS");
    exec("/etc/rc.d/rc.nfsd stop");
  } */
    
  notify("Ransomware Protection","Possible Ransomware Attack Detected","Possible Attack On $path","","alert");
  logger("..");
  logger("Possible Ransomware attack detected on file $path");
  logger("SMB Status:");
  file_put_contents($ransomwarePaths['smbStatusFile'],"******************************************************************************************",FILE_APPEND);
  file_put_contents($ransomwarePaths['smbStatusFile'],"\r\n\r\nTime Of Attack:".date("r",time())."\r\n\r\n",FILE_APPEND);
  file_put_contents($ransomwarePaths['smbStatusFile'],"Attacked File: $path\r\n\r\n",FILE_APPEND);
  foreach($output as $statusLine) {
    logger($statusLine);
    file_put_contents($ransomwarePaths['smbStatusFile'],$statusLine."\r\n",FILE_APPEND);
  }
}


function createBait($path) {
  global $settings,$appdata, $root, $rootContents, $totalBait, $errorBait, $excludedShares;
  
  $contents = scan($path);

  foreach ($contents as $entry) {
    $test = str_replace("/mnt/user/","","$path/$entry");
    if ($appdata[$test]) {
      continue;
    }
    $flag = false;
    foreach ($excludedShares as $excluded) {
      $excluded = rtrim($excluded,"/");
      if ( $excluded == "$path/$entry" ) {
        $flag = true;
        break;
      }
    }
    if ( $flag ) {
      continue;
    }
    if ( (isdir("$path/$entry")) && ( $settings['folders'] != "root" ) ) {
      createBait("$path/$entry");
    }
    if ( isfile("$path/$entry") ) {
      continue;
    }

    foreach ($rootContents as $baitFile) {
      if ( isfile("$path/$entry/$baitFile") ) {
        $errorBait[] = "$path/$entry/$baitFile";
        continue;
      }
      $destination = "$path/$entry/$baitFile";
      $source = "$root/$baitFile";

      if ( !copy($source,$destination) ) {
        $errorBait[] = $destination;
        @unlink($destination);
      } else {
        ++$totalBait;
        file_put_contents("/tmp/ransomware/filelist","$path/$entry/$baitFile\n",FILE_APPEND);
      }
    }
  }
}







$unRaidVars = parse_ini_file("/var/local/emhttp/var.ini");
if ( strtolower($unRaidVars['mdState']) != "started" ) {
  logger("Array Not Started.  Exiting");
  exit;
}
$settings = readJsonFile($ransomwarePaths['settings']);
if ( $settings['enableService'] != "true" ) {
  logger("No Settings Defined For Ransomware Protection - Exiting");
  exit;
}
if ( ! $settings['enableService'] ) { $settings['enableService'] = "false"; }  
if ( ! $settings['folders'] )       { $settings['folders'] = "root"; }
if ( ! $settings['stopSMB'] )       { $settings['stopSMB'] = "true"; }
if ( ! $settings['stopNFS'] )       { $settings['stopNFS'] = "true"; }
if ( ! $settings['stopAFP'] )       { $settings['stopAFP'] = "true"; }
if ( ! $settings['stopArray'] )     { $settings['stopArray'] = "true";}
if ( ! $settings['readOnlySMB'] )   { $settings['readOnlySMB'] = "true"; }
if ( ! $settings['unRaidPort'] )    { $settings['unRaidPort'] = 80; }
if ( ! $settings['excludeAppdata'] ) { $settings['excludeAppdata'] = 'true'; }

if ( ! isfile("/usr/bin/inotifywait") ) {
  logger("inotify tools not installed.  Install it via NerdPack plugin available within Community Applications");
  notify("Ransomware Protection","inotify-tools not installed","inotify tools must be installed (via NerdPack plugin) for this plugin to operate","","warning");
  exit;
}
if ( isfile($ransomwarePaths['PID']) ) {
  logger("ransomware protection appears to be already running");
  exit;
}

exec("mkdir -p /tmp/ransomware/");
@unlink($ransomwarePaths['stoppingService']);
@unlink($ransomwarePaths['event']);
@unlink($ransomwarePaths['detected']);
@unlink($ransomwarePaths['smbShares']);

$pid = getmypid();
file_put_contents($ransomwarePaths['PID'],$pid);
while ( true ) {
  if ( ! isfile($ransomwarePaths['deleteProgress']) ) {
    exec("/usr/local/emhttp/plugins/ransomware.bait/scripts/deleteBait.sh");
    sleep(5);
  }
  while ( true ) {
    if ( isfile($ransomwarePaths['deleteProgress']) ) {
      logger("Waiting For deletion of bait files to complete");
      sleep(30);
      clearstatcache();
    } else {
      break;
    }
  }
  
  if ( ($settings['excludeAppdata'] == 'true') || ($settings['folders'] != 'root') ) {
    $appdata = getAppData();
  }
  $excludedShares = explode(",",$settings['excluded']);
  if ( scan("/boot/config/plugins/ransomware.bait/bait") ) {
    $root = "/boot/config/plugins/ransomware.bait/bait";
  } else {
    $root = "/usr/local/emhttp/plugins/ransomware.bait/bait";
  }
  $rootContents = scan($root);
  
  foreach ($rootContents as $baitFile) {
    $md5Array[$baitFile] = md5_file("$root/$baitFile");
  }

  unset($totalBait);
  @unlink("/tmp/ransomware/filelist");
  if ( $settings['folders'] == "root" ) {
    $logMsg = "Creating bait files, root of shares only";
  } else {
    $logMsg = "Creating bait files, all folders of all shares.  This may take a bit";
  }
  file_put_contents($ransomwarePaths['startupStatus'],$logMsg);
  if ( isdir("/mnt/user") ) {
    $userBase = "/mnt/user";
  } else {
    $userBase = "/mnt";
  }
  unset($errorBait);
  createBait($userBase);
  exec("mkdir -p /boot/config/plugins/ransomware.bait");
  copy("/tmp/ransomware/filelist","/boot/config/plugins/ransomware.bait/filelist");
  @unlink("/tmp/ransomware/filelist");
  @unlink($ransomwarePaths['startupStatus']);  
  if ( $errorBait ) {
    logger("The following bait files could not be created");
    foreach ($errorBait as $error) {
      logger($error);
    }
  }
  logger("Total bait files created: $totalBait");
# check for available # of max_user_watches

  $totalWatches = explode(" ",exec("cat /proc/sys/fs/inotify/max_user_watches"));
  if ( ($totalBait * 3) > $totalWatches[0] ) {
    logger("Increasing inotify_max_user_watches to ".$totalBait * 3);
    file_put_contents("/proc/sys/fs/inotify/max_user_watches", $totalBait * 3);
  }

  logger("Starting Background Monitoring Of Bait Files");
  while ( true ) {
    @unlink("/tmp/ransomware/event");
    exec("inotifywait --fromfile /boot/config/plugins/ransomware.bait/filelist -e move,delete,delete_self,move_self,close_write --format %w -o ".$ransomwarePaths['event']." 2>&1 | logger -i");
    $tmpEvent = @file_get_contents("/tmp/ransomware/event");
    if ( ! trim($tmpEvent) ) {
      logger("Something went wrong and inotify exited.  Exiting");
      break;
    }
    if ( isfile($ransomwarePaths['stoppingService']) ) {
      unlink($ransomwarePaths['stoppingService']);
      exit;
    }      
    $affectedFile = trim(file_get_contents($ransomwarePaths['event']));
    file_put_contents($ransomwarePaths['detected'],$affectedFile);
    if ( ! isfile($affectedFile) ) {
      stopEverything($affectedFile);
      break;
    } else {
      if ( md5_file($affectedFile) != $md5Array[basename($affectedFile)] ) {
        stopEverything($affectedFile);
        break;
      } else {
        logger("Event on $affectedFile, but MD5 matches.  Checking again in 1 second");
        sleep(1);
        if ( md5_file($affectedFile) != $md5Array[basename($affectedFile)] ) {
          stopEverything($affectedFile);
          break;
        } else {
          logger("Event on $affectedFile, but MD5 matches.  Remonitoring");
        }
      }
    } 
  }
  $settings = readJsonFile($ransomwarePaths['settings']);
  if ( $settings['stopArray'] == 'true' ) {
    exec("/usr/local/emhttp/plugins/ransomware.bait/scripts/stopArray.sh");
    break;
  }
}
@unlink($ransomwarePaths['PID']);


?>