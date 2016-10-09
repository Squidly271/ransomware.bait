<?PHP
#########################################################
#                                                       #
# Ransomware Protection copyright 2016, Andrew Zawadzki #
#                                                       #
#########################################################

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
    if ( ! isfile($filename) ) {
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
  if ( ! isfile("/boot/config/shares/$shareName.cfg") ) { 
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
  $dockerRunning = isdir("/var/lib/docker/tmp");
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
  
  if ( isfile("/boot/config/plugins/community.applications/BackupOptions.json") ) {
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
  if ( ( ! $needle ) || ( ! $haystack ) ) { return false; }
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
  global $ransomwarePaths,$settings;

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

  # this first bit resets the smb settings before unRaid fucks with them within a minute;
  
/*   logger("Setting SMB to read-only mode");
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
   */
  # now change unRaid's settings to stop it fucking with them
  exec("rm -rf /boot/config/plugins/ransomware.bait/shareBackup");
  exec("mkdir -p /boot/config/plugins/ransomware.bait/shareBackup");
  exec("cp /boot/config/shares/* /boot/config/plugins/ransomware.bait/shareBackup");
  
  $shareList = scan("/boot/config/shares/");
  if ( ! $shareList ) { $shareList = array(); }
  foreach ($shareList as $share) {
    $shareSettings = parse_ini_file("/boot/config/shares/$share");
    $shareSettings['shareComment'] = "Read Only Mode.  Restore normal settings via <a href='/Settings/ransomware'>Ransomware Protection Settings</a>";
# smb
    if ( $settings['readOnlySMB'] == "true" ) {
      if ( $shareSettings['shareWriteList'] ) {
        $shareSettings['shareReadList'] = $shareSettings['shareWriteList'].",".$shareSettings['shareReadList'];
      }
      $shareSettings['shareWriteList'] = "";
      if ( $shareSettings['shareSecurity'] == "public" ) {
        $shareSettings['shareSecurity'] = "secure";
        $shareSettings['shareReadList'] = "$users";
      }
    }
# afp
    if ( $settings['readOnlyAFP'] == "true" ) {
      if ( $shareSettings['shareWriteListAFP'] ) {
        $shareSettings['shareReadListAFP'] = $shareSettings['shareWriteListAFP'].",".$shareSettings['shareReadListAFP'];
      }
      $shareSettings['shareWriteListAFP'] = "";
      if ( $shareSettings['shareSecurityAFP'] == "public" ) {
        $shareSettings['shareSecurityAFP'] = "secure";
        $shareSettings['shareReadListAFP'] = "$users";
      }
    }

    file_put_contents("/boot/config/shares/$share",createIniFile($shareSettings));
  }
  
# now handle disk shares
  exec("mkdir -p /boot/config/plugins/ransomware.bait/shareBackupDisks");
  copy("/boot/config/disk.cfg","/boot/config/plugins/ransomware.bait/shareBackupDisks/disk.cfg");
  $shareSettings = parse_ini_file["/boot/config/disk.cfg"];

  for ($disk = 1; $disk <= 28; $disk++) {
    $shareSettings["diskComment.$disk"] = "Read Only Mode.  Restore normal settings via <a href='/Settings/ransomware'>Ransomware Protection Settings</a>";
#smb
    if ( $settings['readOnlySMB'] == "true" ) {
      if ( $shareSettings["diskWriteList.$disk"] ) {
        $shareSettings["diskReadList.$disk"] = $shareSettings["diskWriteList.$disk"].",".$shareSettings["diskReadList.$disk"];
      }
      $shareSettings["diskWriteList.$disk"] = "";
      if ( $shareSettings["diskSecurity.$disk"] == "public" ) {
        $shareSettings["diskSecurity.$disk"] = "secure";
        $shareSettings["diskReadList.$disk"] = "$users";
      }
    }
#afp
    if ( $settings['readOnlyAFP'] == "true" ) {
      if ( $shareSettings["diskWriteListAFP.$disk"] ) {
        $shareSettings["diskReadListAFP.$disk"] = $shareSettings["diskWriteListAFP.$disk"].",".$shareSettings["diskReadListAFP.$disk"];
      }
      $shareSettings["diskWriteListAFP.$disk"] = "";
      if ( $shareSettings["diskSecurityAFP.$disk"] == "public" ) {
        $shareSettings["diskSecurityAFP.$disk"] = "secure";
        $shareSettings["diskReadListAFP.$disk"] = "$users";
      }
    }
  }
#handle the cache drive
  $shareSettings["cacheComment"] = "Read Only Mode.  Restore normal settings via <a href='/Settings/ransomware'>Ransomware Protection Settings</a>";
#smb
  if ( $settings['readOnlySMB'] == "true" ) {
    if ( $shareSettings["cacheWriteList"] ) {
      $shareSettings["cacheReadList"] = $shareSettings["cacheWriteList"].",".$shareSettings["cacheReadList"];
    }
    $shareSettings["cacheWriteList"] = "";
    if ( $shareSettings["cacheSecurity"] == "public" ) {
      $shareSettings["cacheSecurity"] = "secure";
      $shareSettings["cacheReadList"] = "$users";
    }
  }
#afp
  if ( $settings['readOnlyAFP'] == "true" ) {
    if ( $shareSettings["cacheWriteListAFP"] ) {
      $shareSettings["cacheReadListAFP"] = $shareSettings["cacheWriteListAFP"].",".$shareSettings["cacheReadListAFP"];
    }
    $shareSettings["cacheWriteListAFP"] = "";
    if ( $shareSettings["cacheSecurityAFP"] == "public" ) {
      $shareSettings["cacheSecurityAFP"] = "secure";
      $shareSettings["cacheReadListAFP"] = "$users";
    }
  }
  file_put_contents("/boot/config/disk.cfg",createIniFile($shareSettings));

  # all previously configured shares are now hopefully readonly.  Set unconfigured shares to be read-only
  #exec("/etc/rc.d/rc.samba restart");
}

function createIniFile($shareSettings) {
  unset($cfg);
  $newSettings = "# Read-only Settings Generated By Ransomware.Bait\r\n";
  $cfg = array_keys($shareSettings);
  foreach ($cfg as $cfgSetting) {
    $newSettings .= $cfgSetting.'="'.$shareSettings[$cfgSetting].'"'."\r\n";
  }
  return $newSettings;
}

###############################################################################
#                                                                             #
# 2 functions to avoid PHP caching results that could throw things for a loop #
#                                                                             #
###############################################################################

function isfile($filename) {
  clearstatcache();
  return is_file($filename);
}
function isdir($path) {
  clearstatcache();
  return is_dir($path);
}

?>
