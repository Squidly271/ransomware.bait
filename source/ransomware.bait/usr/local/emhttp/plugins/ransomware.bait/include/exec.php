<?PHP
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
  case 'applySettings':
    $settings = getSettings();
    
    writeJsonFile("/boot/config/plugins/ransomware.bait/settings.json",$settings);
    if ( $settings['enableService'] == "true" ) {
      exec("/usr/local/emhttp/plugins/ransomware.bait/scripts/stopService.php");
      exec("/usr/local/emhttp/plugins/ransomware.bait/scripts/startBackgroundMonitor.sh");
    } else {
      exec("/usr/local/emhttp/plugins/ransomware.bait/scripts/stopService.php");
    }
    echo "done";
    break;
  case 'resetSMBPermissions':
    if ( ! is_file($ransomwarePaths['smbShares']) ) { break; }
    logger("Resetting SMB permissions to normal per user selection");
    rename($ransomwarePaths['smbShares'],"/etc/samba/smb-shares.conf");
    exec("rm -rf /boot/config/shares");
    exec("mkdir -p /boot/config/shares");
    exec("cp /boot/config/plugins/ransomware.bait/shareBackup/* /boot/config/shares");
    @unlink($ransomwarePaths['detected']); # also kill the event
    exec("/etc/rc.d/rc.samba restart");
    break;
  case 'setReadOnly':
    smbReadOnly();
    break;
  case 'getStatus':
    if ( is_file($ransomwarePaths['PID']) ) {
      echo "<font color='green'>Running</font>";
    } else {
      echo "<font color='red'>Not Running</font>";
    }
    break;
}
?>
