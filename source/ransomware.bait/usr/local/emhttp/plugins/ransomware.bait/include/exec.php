<?PHP

require_once("/usr/local/emhttp/plugins/ransomware.bait/include/helpers.php");
exec("mkdir -p /tmp/ransomware");
function getSettings() {
  $rawSettings = $_POST['settings'];
  
  foreach ($rawSettings as $setting) {
    $settings[$setting[0]] = $setting[1];
  }
  return $settings;
}
echo "ok";
switch ($_POST['action']) {
  case 'deleteBait':
    $filelist = file_get_contents("/boot/config/plugins/ransomware.bait/filelist");
    $allFiles = explode("\n",$filelist);
    
    foreach ($allFiles as $baitFile) {
      if ( is_file($baitFile) ) {
        ++$totalBait;
        unlink($baitFile);
      }
    }
    unlink("/boot/config/plugins/ransomware.bait/filelist");
    echo "<font color='green'>Total bait files deleted: $totalBait</font>";
    break;
    
  case 'applySettings':
    $settings = getSettings();
    
    writeJsonFile("/boot/config/plugins/ransomware.bait/settings.json",$settings);
    if ( $settings['enableService'] == "true" ) {
      exec("/usr/local/emhttp/plugins/ransomware.bait/scripts/startBackgroundMonitor.sh");
    } else {
      exec("/usr/local/emhttp/plugins/ransomware.bait/scripts/stopService.php");
    }
    echo "done";
    break;
}
?>
