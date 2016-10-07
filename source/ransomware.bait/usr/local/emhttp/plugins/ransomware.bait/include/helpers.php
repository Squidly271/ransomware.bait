<?PHP


require_once("/usr/local/emhttp/plugins/dynamix/include/Wrappers.php");
require_once("/usr/local/emhttp/plugins/dynamix.docker.manager/include/DockerClient.php");



###################################################################################
#                                                                                 #
# returns a random file name                                                      #
#                                                                                 #
###################################################################################

function randomFile($basePath) {
  global $communityPaths;
  while (true) {
    $filename = $basePath."/".mt_rand().".tmp";
    if ( ! is_file($filename) ) {
      break;
    }
  }
  return $filename;
}

##################################################################
#                                                                #
# 2 Functions to avoid typing the same lines over and over again #
#                                                                #
##################################################################

function readJsonFile($filename) {
  return @json_decode(@file_get_contents($filename),true);
}

function writeJsonFile($filename,$jsonArray) {
  file_put_contents($filename,json_encode($jsonArray, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

##############################################################
#                                                            #
# Searches an array of docker mappings (host:container path) #
# for a container mapping of /config and returns the host    #
# path                                                       #
#                                                            #
##############################################################

function findAppdata($volumes) {
  $path = false;
  $dockerOptions = @parse_ini_file("/boot/config/docker.cfg");
  $defaultShareName = basename($dockerOptions['DOCKER_APP_CONFIG_PATH']);
  $shareName = str_replace("/mnt/user/","",$defaultShareName);
  $shareName = str_replace("/mnt/cache/","",$defaultShareName);
  if ( ! is_file("/boot/config/shares/$shareName.cfg") ) { 
    $shareName = "****";
  }
  file_put_contents("/tmp/test",$defaultShareName);
  if ( is_array($volumes) ) {
    foreach ($volumes as $volume) {
      $temp = explode(":",$volume);
      $testPath = strtolower($temp[1]);
    
      if ( (startsWith($testPath,"/config")) || (startsWith($temp[0],"/mnt/user/$shareName")) || (startsWith($temp[0],"/mnt/cache/$shareName")) ) {
        $path = $temp[0];
        break;
      }
    }
  }
  return $path;
}

############################################
#                                          #
# Function to write a string to the syslog #
#                                          #
############################################

function logger($string) {
  shell_exec('logger ransomware protection:"'.$string.'"');
}

###########################################
#                                         #
# Function to send a dynamix notification #
#                                         #
###########################################

function notify($event,$subject,$description,$message,$type="normal") {
  $command = '/usr/local/emhttp/plugins/dynamix/scripts/notify -e "'.$event.'" -s "'.$subject.'" -d "'.$description.'" -m "'.$message.'" -i "'.$type.'"';
  shell_exec($command);
}



#########################################################
#                                                       #
# Returns an array of all of the appdata shares present #
#                                                       #
#########################################################

function getAppData() {
  $dockerRunning = is_dir("/var/lib/docker/tmp");
  $excludedShares = array();
  
  if ( $dockerRunning ) {
    $DockerClient = new DockerClient();
    $info = $DockerClient->getDockerContainers();

    foreach ($info as $docker) {
      $appData = findAppData($docker['Volumes']);
      if ( ! $appData ) {
        continue;
      }
      $appData = str_replace("/mnt/cache/","/mnt/user/",$appData);
      $appData = str_replace("/mnt/user/","",$appData);
      $pathinfo = explode("/",$appData);
      $excludedShares[$pathinfo[0]] = $pathinfo[0];
    }
  }  
  $dockerOptions = @parse_ini_file("/boot/config/docker.cfg");
  $sharename = $dockerOptions['DOCKER_APP_CONFIG_PATH'];
  if ( $sharename ) {
    $sharename = str_replace("/mnt/cache/","",$sharename);
    $sharename = str_replace("/mnt/user/","",$sharename);
    $pathinfo = explode("/",$sharename);
    $excludedShares[$pathinfo[0]] = $pathinfo[0];
  }
  
  if ( is_file("/boot/config/plugins/community.applications/BackupOptions.json") ) {
    $backupOptions = readJsonFile("/boot/config/plugins/community.applications/BackupOptions.json");
    $backupDestination = $backupOptions['destinationShare'];
    $backupShare = explode("/",$backupDestination);
    $excludedShares[$backupShare[0]] = $backupShare[0]." (Community Applications Backup Appdata Destination)";
  }

  return $excludedShares;  
}
#################################################################
#                                                               #
# Helper function to determine if $haystack begins with $needle #
#                                                               #
#################################################################

function startsWith($haystack, $needle) {
  return $needle === "" || strripos($haystack, $needle, -strlen($haystack)) !== FALSE;
}

###############################################
#                                             #
# Function to get the contents of a directory #
#                                             #
###############################################

function scan($path) {
  return @array_diff(@scandir($path),array(".",".."));
}

#######################################
#                                     #
# Function to set SMB to be read-only #
#                                     #
#######################################

function smbReadOnly() {
  global $ransomwarePaths;

  # this first bit resets the smb settings before unRaid fucks with them within a minute;
  
  logger("Setting SMB to read-only mode");
  copy("/etc/samba/smb-shares.conf",$ransomwarePaths['smbShares']);
  $smb = explode("\n",file_get_contents("/etc/samba/smb-shares.conf"));
  foreach ($smb as $smbLine) {
    $smbLineNew = trim($smbLine);
    if ( startsWith($smbLineNew,"writeable") ) {
      $smbLineNew = "read only = yes";
    }
    if ( startsWith($smbLineNew,"write list") ) {
      continue;
    }
    if ( startsWith($smbLineNew,"public = yes") ) {
      $smbLineNew = "public = yes\nread only = yes";
    }
    $newSMB .= $smbLineNew."\n";
  }
  file_put_contents("/etc/samba/smb-shares.conf",$newSMB);
  exec("/etc/rc.d/rc.samba restart");
  
  # now change unRaid's settings to stop it fucking with them
  exec("rm -rf /boot/config/plugins/ransomware.bait/shareBackup");
  exec("mkdir -p /boot/config/plugins/ransomware.bait/shareBackup");
  exec("cp /boot/config/shares/* /boot/config/plugins/ransomware.bait/shareBackup");
  
  $shareList = scan("/boot/config/shares/");
  if ( ! $shareList ) { $shareList = array(); }
  foreach ($shareList as $share) {
    $shareSettings = parse_ini_file("/boot/config/shares/$share");
    if ( $shareSettings['shareWriteList'] ) {
      $shareSettings['shareReadList'] = $shareSettings['shareWriteList'].",".$shareSettings['shareReadList'];
    }
    $shareSettings['shareWriteList'] = "";
    if ( $shareSettings['shareSecurity'] == "public" ) {
      $shareSettings['shareSecurity'] = "secure";
      $shareSettings['shareReadList'] = "";
    }
    unset($cfg);
    $newSettings = "# Read-only Settings Generated By Ransomware.Bait\r\n";
    $cfg = array_keys($shareSettings);
    foreach ($cfg as $cfgSetting) {
      $newSettings .= $cfgSetting.'="'.$shareSettings[$cfgSetting].'"'."\r\n";
    }
    file_put_contents("/boot/config/shares/$share",$newSettings);
  }
  # all previously configured shares are now hopefully readonly.  Set unconfigured shares to be read-only
  
  # get the user list
  
  unset($output);
  exec("cat /etc/passwd | grep :100:",$output);  # group of 100 (nobody)
  foreach ($output as $line) {
    $userLine = explode(":",$line);
    if ( $userLine[0] == "nobody" ) {
      continue;
    }
    $users .= $userLine[0].",";
  }
  $users = rtrim($users,",");
  $defaultConfig = file_get_contents("/usr/local/emhttp/plugins/ransomware.bait/include/defaultShare.cfg");
  $shareList = scan("/mnt/user");
  foreach ($shareList as $share) {
    if ( ! is_file("/boot/config/shares/$share.cfg") ) {
      file_put_contents("/boot/config/shares/$share.cfg",$defaultConfig."shareReadList=\"$users\"\r\n");
    }
  }
  exec("/etc/rc.d/rc.samba restart");
}

?>
