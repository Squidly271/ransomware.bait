<?PHP
#########################################################
#                                                       #
# Ransomware Protection copyright 2016, Andrew Zawadzki #
#                                                       #
#########################################################

require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/helpers.php");

exec("mkdir -p /tmp/ransomware");

function getSettings() {
  $rawSettings = $_POST['settings'];
  
  foreach ($rawSettings as $setting) {
    $settings[$setting[0]] = $setting[1];
  }
  return $settings;
}

switch ($_POST['action']) {
  case 'applyBaitFileSettings':
    $settings = parse_ini_file($ransomwarePaths['settings'],true);
    $settings['baitFile'] = getSettings();
    file_put_contents($ransomwarePaths['settings'],create_ini_file($settings,true));
    file_put_contents($ransomwarePaths['settingsRAM'],create_ini_file($settings,true));
    file_put_contents($ransomwarePaths['restartRequired'],"uh huh");
    echo "done";
    break;
  case 'applyActionSettings':
    $settings = parse_ini_file($ransomwarePaths['settings'],true);
    $settings['actions'] = getSettings();
    file_put_contents($ransomwarePaths['settings'],create_ini_file($settings,true));
    file_put_contents($ransomwarePaths['settingsRAM'],create_ini_file($settings,true));
    file_put_contents($ransomwarePaths['restartRequired'],"uh huh");
    echo "done";
    break;
  case 'applyShareSettings':
    $settings = parse_ini_file($ransomwarePaths['settings'],true);
    $settings['shareSettings'] = getSettings();
    file_put_contents($ransomwarePaths['settings'],create_ini_file($settings,true));
    file_put_contents($ransomwarePaths['settingsRAM'],create_ini_file($settings,true));
    file_put_contents($ransomwarePaths['restartRequired'],"uh huh");
    echo "done";
    break;
  case 'validateScript':
    $script = trim(getPost("script",""));
    if ( ! $script ) {
      echo "ok";
      break;
    }
    if ( isdir($script) ) {
      echo "Must Select A File";
      break;
    }
    if ( ! is_executable($script) ) {
      echo "Not Executable";
      break;
    }
    echo "ok";
    break;
  case 'resetSMBPermissions':
    if ( ! isdir($ransomwarePaths['shareBackup']) ) { break; }
    logger("Resetting SMB permissions to normal per user selection");
    rename($ransomwarePaths['smbShares'],"/etc/samba/smb-shares.conf");
    exec("rm -rf /boot/config/shares");
    exec("mkdir -p /boot/config/shares");
    exec("cp /boot/config/plugins/ransomware.bait/shareBackup/* /boot/config/shares");
    exec("rm -rf /boot/config/plugins/ransomware.bait/shareBackup");
    copy("/boot/config/plugins/ransomware.bait/shareBackupDisk");
    @unlink($ransomwarePaths['detected']); # also kill the event
    exec("/etc/rc.d/rc.samba stop");
    break;
  case 'setReadOnly':
    $settings['readOnlySMB'] = "true";
    $settings['readOnlyAFP'] = "true";
    smbReadOnly();
    logger("SMB/AFP now set to be read-only per user request");
    break;
  case 'getStatus':
    $settings = readSettingsFile();
    $message = @file_get_contents($ransomwarePaths['startupStatus']);
    $shareMessage = @file_get_contents($ransomwarePaths['shareStatus']);
    
    if ( ! $message ) {
      if ( isfile($ransomwarePaths['PID']) ) {
        $message =  "<font color=green>Running</font>";
        $running = true;
        $script .= "var stoppable = false;";
        $script .= "var startable = true;";
        } else {
        $message =  "<font color=red>Not Running</font>";
        $script .= "var stoppable = true;";
        if ( $settings['baitFile']['enableService'] == "true" ) {
          $script .= "var startable = false;";
        } else {
          $script .= "var startable = true;";
        }
      }
    }
    
    if ( ! $shareMessage ) {
      if ( isfile($ransomwarePaths['sharePID']) ) {
        $shareMessage = "<font color=green>Running</font>";
      } else {
        $shareMessage = "<font color=red>Not Running</font>";
      }
    }    
    if ( ( isfile($ransomwarePaths['filelist']) || isfile($ransomwarePaths['baitShares'])) && ! isfile($ransomwarePaths['deleteProgress']) && ! $running && ! isfile($ransomwarePaths['deleteBaitSharePID']) ) {
      $script .= "var deleteable = false;";
    } else {
      $script .= "var deleteable = true;";
    }
    if ( isfile($ransomwarePaths['restartRequired']) && $running ) {
      $script .= "var restartRequired = true;";
    } else {
      $script .= "var restartRequired = false;";
    }
    if ( isfile($ransomwarePaths['numMonitored']) ) {
      $script .= "var totalMonitored = ".file_get_contents($ransomwarePaths['numMonitored']).";";
    } else {
      $script .= "var totalMonitored = '0';";
    }
    if ( isfile($ransomwarePaths['smbStatusFile']) ) {
      $script .= "var smbHistory = true;";
    } else {
      $script .= "var smbHistory = false;";
    }
    if ( isfile($ransomwarePaths['creationErrors']) ) {
      $script .= "var creationErrors = true;";
    } else {
      $script .= "var creationErrors = false;";
    }
    $totalShareFiles = @file_get_contents($ransomwarePaths['baitShareCount']);
    if ( $totalShareFiles ) {
      $script .= "$('#totalShareMonitored').html('$totalShareFiles');";
    } else {
      $script .= "$('#totalShareMonitored').html('0');";
    }
    echo "<script>$('#running').html('$message');$script";
    echo "$('#shareStatus').html('$shareMessage');</script>";
    break;
  case 'getAttackStatus':
    if ( isdir($ransomwarePaths['shareBackup']) ) {
      $attack = @file_get_contents($ransomwarePaths['detected']);
      if ( ! $attack ) {
        $attack = "user";
      }
    } else {
      $attack = "ok";
    }
    echo $attack;
    break;
  case 'stopServices':
    exec("/usr/local/emhttp/plugins/ransomware.bait/scripts/stopService.php");
    @unlink($ransomwarePaths['restartRequired']);
    break;
  case 'startServices':
    exec("/usr/local/emhttp/plugins/ransomware.bait/scripts/startBackgroundMonitor.sh");
    @unlink($ransomwarePaths['restartRequired']);
    break;
  case 'deleteBait':
    exec("/usr/local/emhttp/plugins/ransomware.bait/scripts/deleteBait.sh");
    break;
  case 'validateShareSettings':
    $settings = getSettings();
    $settings['sharePrefix'] = trim($settings['sharePrefix']);

    if ( $settings['enableShareService'] == "false" ) {
      $comment .= "Bait Share Service Not Enabled<br>";
    }

    if ( ! $settings['sharePrefix'] ) {
      $comment .= "Share prefix cannot be blank<br>";
    }
    
    if ( $settings['numberShares'] < 1 ) {
      $comment .= "Number of shares to create must be at least 1<br>";
    }
    if ( $settings['numberFoldersPerShare'] < 1 ) {
      $comment .= "Number of folders per share must be at least 1<br>";
    }
    if ( $settings['folderDepth'] < 1 ) {
      $comment .= "Folder depth must be at least 1<br>";
    }
    if ( $settings['numberBaitPerFolder'] < 1 ) {
      $comment .= "Number of bait files per folder must be at least 1<br>";
    }
    $totalBaitToCreate = $settings['numberShares'] * $settings['numberFoldersPerShare'] * $settings['numberFoldersPerShare'] * $settings['folderDepth'] * $settings['numberBaitPerFolder'] * $settings['numberBaitPerFolder'];
 $script = "<script>";
    if ( ! $comment ) {
      $script .= "$('#shareStatus').html('ok');";
      $script .= "$('#shareComments').html('Total bait files to create is approximately $totalBaitToCreate');";
    } else {
      if ( $settings['enableShareService'] == "false" ) {
        $script .= "$('#shareStatus').html('ok');";
      } else {
        $script .= "$('#shareStatus').html('buggered');";
      }
      $script .= "$('#shareComments').html('$comment');";
    }
    echo $script;
   
    break;
}
?>
