<?PHP

require_once("/usr/local/emhttp/plugins/ransomware.bait/include/helpers.php");

function getSettings() {
  $rawSettings = $_POST['settings'];
  
  foreach ($rawSettings as $setting) {
    $settings[$setting[0]] = $setting[1];
  }
  return $settings;
}

function createBait($path) {
  global $settings,$appdata, $baitPath, $root, $rootContents, $totalBait, $errorBait;
  
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
      $destination = escapeshellarg("$path/$entry/$baitFile");
      $source = escapeshellarg("$root/$baitFile");

      exec("cp $source $destination");
      ++$totalBait;
      file_put_contents("/tmp/ransomware/filelist","$path/$entry/$baitFile\n",FILE_APPEND);
    }
  }
}

exec("mkdir -p /tmp/ransomware/md5");

switch ($_POST['action']) {
  case 'createBait':
    $settings = getSettings();
    $appdata = getAppData();
    if ( scan("/boot/config/plugins/ransomware.bait/bait") ) {
      $root = "/boot/config/plugins/ransomware.bait/bait";
    } else {
      $root = "/usr/local/emhttp/plugins/ransomware.bait/bait";
    }
    $rootContents = scan($root);
    
    foreach ($rootContents as $baitFile) {
      $md5 = md5_file("$root/$baitFile");
      file_put_contents("/tmp/ransomware/md5/$baitFile.md5",$md5);
    }
    
    @unlink("/tmp/ransomware/filelist");
    createBait("/mnt/user");
    exec("mkdir -p /boot/config/plugins/ransomware.bait");
    exec("cp /tmp/ransomware/filelist /boot/config/plugins/ransomware.bait/filelist");
    if ( $errorBait ) {
      echo "The following bait files could not be created:<br>";
      foreach ($errorBait as $error) {
        echo "<font color='red'>$error</font><br>";
      }
      echo "<br>";
    }
    echo "<font color='green'>Total files created: $totalBait</font>";
    exec("/usr/local/emhttp/plugins/ransomware.bait/scripts/startBackgroundMonitor.sh");
    break;
    
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
    break;
}
?>
