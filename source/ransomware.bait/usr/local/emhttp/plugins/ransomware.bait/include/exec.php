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
      echo "Not Executable $script";
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
    break;
  case 'getStatus':
    $settings = readSettingsFile();
    $message = @file_get_contents($ransomwarePaths['startupStatus']);

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
    if ( is_file($ransomwarePaths['filelist']) && ! isfile($ransomwarePaths['deleteProgress']) && ! $running ) {
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
    echo "<script>$('#running').html('$message');$script</script>";
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
    
}
?>
