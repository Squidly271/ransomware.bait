#!/usr/bin/php
<?PHP
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/helpers.php");
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");

function stopEverything($path) {
  global $settings;
  
  logger("Possible Ransomware attack detected on file $path");
  if ( $settings['stopSMB'] ) {
    logger("Stopping SMB");
    exec("/etc/rc.d/rc.samba stop");
  }
  if ( $settings['stopAFP'] ) {
    logger("Stopping AFP");
    exec("/etc/rc.d/rc.atalk stop");
  }
  if ( $settings['stopNFS'] ) {
    logger("Stopping NFS");
    exec("/etc/rc.d/rc.nfsd stop");
  }
  notify("Ransomware Protection","Possible Ransomware Attack Detected","Possible Attack On $path","","alert");
}

function createBait($path) {
  global $settings,$appdata, $root, $rootContents, $totalBait, $errorBait;
  
  $contents = scan($path);

  foreach ($contents as $entry) {
    $test = str_replace("/mnt/user/","","$path/$entry");
    if ($appdata[$test]) {
      continue;
    }
    if ( (is_dir("$path/$entry")) && ( $settings['folders'] != "root" ) ) {
      createBait("$path/$entry");
    }
    if ( is_file("$path/$entry") ) {
      continue;
    }

    foreach ($rootContents as $baitFile) {
      if ( is_file("$path/$entry/$baitFile") ) {
        $errorBait[] = "$path/$entry/$baitFile";
        continue;
      }
      $destination = "$path/$entry/$baitFile";
      $source = "$root/$baitFile";

      if ( !copy($source,$destination) ) {
        $errorBait[] = "$path/$entry/$baitFile";
        @unlink($destination);
      } else {
        ++$totalBait;
        file_put_contents("/tmp/ransomware/filelist","$path/$entry/$baitFile\n",FILE_APPEND);
      }
    }
  }
}

$settings = readJsonFile($ransomwarePaths['settings']);
if ( $settings['enableService'] != "true" ) {
  exit;
}
if ( ! is_file("/usr/bin/inotifywait") {
  logger("inotify tools not installed.  Install it via NerdPack plugin available within Community Applications");
}
exec("mkdir -p /tmp/ransomware/");
@unlink($ransomwarePaths['event']);
@unlink($ransomwarePaths['detected']);

$pid = getmypid();
file_put_contents($ransomwarePaths['PID'],$pid);

while (true) {
  unset($totalDeleted);
  $filelist = @file_get_contents($ransomwarePaths['filelist']);
  if ( $filelist ) {
    logger("Deleting previously set ransomware bait files");
    $allFiles = explode("\n",$filelist);
    foreach ($allFiles as $baitFile) {
      if ( is_file($baitFile) ) {
        ++$totalDeleted;
        echo "$baitFile\n";
        unlink($baitFile);
      }
    }
    logger("Deleted $totalDeleted bait files");
    unlink("/boot/config/plugins/ransomware.bait/filelist");
  }

  $appdata = getAppData();
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
    logger("Creating bait files, root of shares only");
  } else {
    logger("Creating bait files, all folders of all shares.  This may take a bit");
  }
  if ( is_dir("/mnt/user") {
    $userBase = "/mnt/user";
  } else {
    $userBase = "/mnt";
  }
  createBait($userBase);
  exec("mkdir -p /boot/config/plugins/ransomware.bait");
  copy("/tmp/ransomware/filelist","/boot/config/plugins/ransomware.bait/filelist");
  if ( $errorBait ) {
    logger("The following bait files could not be created");
    foreach ($errorBait as $error) {
      logger($error);
    }
  }
  logger("Total bait files created: $totalBait");

  logger("Starting Background Monitoring Of Bait Files");
  while ( true ) {
    @unlink("/tmp/ransomware/event");
    exec("inotifywait --fromfile /boot/config/plugins/ransomware.bait/filelist -e move,delete,delete_self,move_self,close_write --format %w -o ".$ransomwarePaths['event']);
    $affectedFile = trim(file_get_contents($ransomwarePaths['event']));
    file_put_contents($ransomwarePaths['detected'],$affectedFile);
    if ( ! is_file($affectedFile) ) {
      stopEverything($affectedFile);
      break;
    } else {
      if ( md5_file($affectedFile) != $md5Array[basename($affectedFile)] ) {
        stopEverything($affectedFile);
        break;
      }
    }
  }
  if ( $settings['stopArray'] == 'true' ) {
    break;
  }
}
@unlink($ransomwarePaths['PID']);


?>