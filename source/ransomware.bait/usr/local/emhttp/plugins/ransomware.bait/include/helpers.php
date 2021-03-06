<?PHP
#########################################################
#                                                       #
# Ransomware Protection copyright 2016, Andrew Zawadzki #
#                                                       #
#########################################################

require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");

function stopEverything($path,$settings) {
  global $ransomwarePaths;

  exec("/usr/bin/smbstatus",$output);
  if ( $settings['readOnlySMB'] == "true" ) { exec("/etc/rc.d/rc.samba stop"); }
  if ( $settings['readOnlyAFP'] == "true" ) { exec("/etc/rc.d/rc.atalk stop"); }
  if ( ($settings['readOnlySMB'] == "true") || ($settings['stopArray'] == "true") || ($settings['readOnlyAFP'] == "true") ) {
    exec("/usr/local/emhttp/plugins/ransomware.bait/scripts/smbReadOnly.php");
  }
# stop it again just in case unRaid restarted it in the interim

  if ( $settings['stopArray'] == "true" ) {
    logger("Stopping AFP");
    exec("/etc/rc.d/rc.atalk stop");    
    logger("Stopping NFS");
    exec("/etc/rc.d/rc.nfsd stop");
  }
  notify("Ransomware Protection","Possible Ransomware Attack Detected","Possible Attack On $path","","alert");
  logger("..");
  logger("Possible Ransomware attack detected on file $path");

  file_put_contents($ransomwarePaths['smbStatusFile'],"******************************************************************************************",FILE_APPEND);
  file_put_contents($ransomwarePaths['smbStatusFile'],"\r\n\r\nTime Of Attack:".date("r",time())."\r\n\r\n",FILE_APPEND);
  file_put_contents($ransomwarePaths['smbStatusFile'],"Attacked File: $path\r\n\r\n",FILE_APPEND);
  
  $shareCFG = my_parse_ini_file("/boot/config/share.cfg");
  if ( $shareCFG['shareSMBEnabled'] == "yes" ) {
    logger("SMB Status:");
    foreach($output as $statusLine) {
      logger($statusLine);
      file_put_contents($ransomwarePaths['smbStatusFile'],$statusLine."\r\n",FILE_APPEND);
    }
  } else {
    logger("SMB Not Enabled.  Cannot display SMB status");
  }
  if ( $settings['stopScript'] ) {
    exec($settings['stopScript']);
  }
}

######################################################
#                                                    #
# Bunch of functions to deal with the status display #
#                                                    #
######################################################

function shareStatus($statusMessage) {
  global $ransomwarePaths;
  
  file_put_contents($ransomwarePaths['shareStatus'],$statusMessage);
}

function clearShareStatus() {
  global $ransomwarePaths;
  
  @unlink($ransomwarePaths['shareStatus']);
}

function baitStatus($statusMessage) {
  global $ransomwarePaths;
  
  file_put_contents($ransomwarePaths['startupStatus'],$statusMessage);
}

function clearBaitStatus() {
  global $ransomwarePaths;
  
  @unlink($ransomwarePaths['startupStatus']);
}

####################################################################################################
#                                                                                                  #
# 2 Functions because unRaid includes comments in .cfg files starting with # in violation of PHP 7 #
#                                                                                                  #
####################################################################################################

if ( ! function_exists('my_parse_ini_file') ) {
  function my_parse_ini_file($file,$mode=false,$scanner_mode=INI_SCANNER_NORMAL) {
    return parse_ini_string(preg_replace('/^#.*\\n/m', "", @file_get_contents($file)),$mode,$scanner_mode);
  }
}

if ( ! function_exists('my_parse_ini_string') ) {
  function my_parse_ini_string($string, $mode=false,$scanner_mode=INI_SCANNER_NORMAL) {
    return parse_ini_string(preg_replace('/^#.*\\n/m', "", $string),$mode,$scanner_mode);
  }
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

##############################################################################
#                                                                            # 
# Function to read the plugin's ini file (and merge it with the default.ini) #
#                                                                            #
##############################################################################

function readSettingsFile() {
  global $ransomwarePaths;

  if ( isfile($ransomwarePaths['settingsRAM']) ) {
    $user = @my_parse_ini_file($ransomwarePaths['settingsRAM'],true);
  }
  if ( isfile($ransomwarePaths['settings']) ) {
    copy($ransomwarePaths['settings'],$ransomwarePaths['settingsRAM']);
    $user = @my_parse_ini_file($ransomwarePaths['settingsRAM'],true);
  }
  if ( ! $user ) {
    $user = array();
  }
  $default = @my_parse_ini_file($ransomwarePaths['defaultSettings'],true);
  $defaultKeys = array_keys($default);
  foreach ($defaultKeys as $keys) {
    $entries = array_keys($default[$keys]);
    foreach ($entries as $entry) {
      if ( ! isset($user[$keys][$entry]) ) {
        $user[$keys][$entry] = $default[$keys][$entry];
      }
    }
  }
  return $user;
}

################################################################
#                                                              #
# Creates an INI file parseable by parse_ini_file              #
# Set $mode to be true when dealing with multi-dimension array #
#                                                              #
################################################################

function create_ini_file($settings,$mode=false) {
  if ( $mode ) {
    $keys = array_keys($settings);

    foreach ($keys as $key) {
      $iniFile .= "[$key]\r\n";
      $entryKeys = array_keys($settings[$key]);
      foreach ($entryKeys as $entry) {
        $iniFile .= $entry.'="'.$settings[$key][$entry].'"'."\r\n";
      }
    }
  } else {
    $entryKeys = array_keys($settings);
    foreach ($entryKeys as $entry) {
      $iniFile .= $entry.'="'.$settings[$entry].'"'."\r\n";
    }
  }
  return $iniFile;
}

#########################################################
#                                                       #
# Returns an array of all of the appdata shares present #
#                                                       #
#########################################################

function getAppData() {
  $excludedShares = array();
  $dockerOptions = @my_parse_ini_file("/boot/config/docker.cfg");
  $sharename = $dockerOptions['DOCKER_APP_CONFIG_PATH'];
  if ( $sharename ) {
    $sharename = str_replace("/mnt/cache/","",$sharename);
    $sharename = str_replace("/mnt/user/","",$sharename);
    $pathinfo = explode("/",$sharename);
    $excludedShares[$pathinfo[0]] = $pathinfo[0];
  }
  
  if ( isfile("/boot/config/plugins/ca.backup/BackupOptions.json") ) {
    $backupOptions = readJsonFile("/boot/config/plugins/ca.backup/BackupOptions.json");
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

function smbReadOnly($settings) {
  global $ransomwarePaths;
exec("/etc/rc.d/rc.samba stop");
exec("/etc/rc.d/rc.atalk stop");
}
/*   # get the user list
  
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

  # now change unRaid's settings to stop it fucking with them
  exec("rm -rf /boot/config/plugins/ransomware.bait/shareBackup");
  exec("mkdir -p /boot/config/plugins/ransomware.bait/shareBackup");
  exec("cp /boot/config/shares/* /boot/config/plugins/ransomware.bait/shareBackup");
  
  $shareList = scan("/boot/config/shares/");
  if ( ! $shareList ) { $shareList = array(); }
  foreach ($shareList as $share) {
    $shareSettings = my_parse_ini_file("/boot/config/shares/$share");
    $shareSettings['shareComment'] = "Read Only Mode.  Restore normal settings via <a href='/Settings/Ransomware'>Ransomware Protection Settings</a>";
# smb
    if ( $settings['actions']['readOnlySMB'] == "true" ) {
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
    if ( $settings['actions']['readOnlyAFP'] == "true" ) {
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
  $shareSettings = @my_parse_ini_file("/boot/config/disk.cfg");

  for ($disk = 1; $disk <= 28; $disk++) {
    $shareSettings["diskComment.$disk"] = "Read Only Mode.  Restore normal settings via <a href='/Settings/Ransomware'>Ransomware Protection Settings</a>";
#smb
    if ( $settings['actions']['readOnlySMB'] == "true" ) {
      if ( $shareSettings["diskWriteList.$disk"] ) {
        $shareSettings["diskReadList.$disk"] = $shareSettings["diskWriteList.$disk"].",".$shareSettings["diskReadList.$disk"];
      }
      $shareSettings["diskWriteList.$disk"] = "";
      if ( ($shareSettings["diskSecurity.$disk"] == "public") || ! $shareSettings["diskSecurity.$disk"] ) {
        $shareSettings["diskSecurity.$disk"] = "secure";
        $shareSettings["diskReadList.$disk"] = "$users";
      }
    }
#afp
    if ( $settings['actions']['readOnlyAFP'] == "true" ) {
      if ( $shareSettings["diskWriteListAFP.$disk"] ) {
        $shareSettings["diskReadListAFP.$disk"] = $shareSettings["diskWriteListAFP.$disk"].",".$shareSettings["diskReadListAFP.$disk"];
      }
      $shareSettings["diskWriteListAFP.$disk"] = "";
      if ( ($shareSettings["diskSecurityAFP.$disk"] == "public") || ! $shareSettings["diskSecurityAFP.$disk"] ) {
        $shareSettings["diskSecurityAFP.$disk"] = "secure";
        $shareSettings["diskReadListAFP.$disk"] = "$users";
      }
    }
  }
#handle the cache drive
  $shareSettings["cacheComment"] = "Read Only Mode.  Restore normal settings via <a href='/Settings/Ransomware'>Ransomware Protection Settings</a>";
#smb
  if ( $settings['actions']['readOnlySMB'] == "true" ) {
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
  if ( $settings['actions']['readOnlyAFP'] == "true" ) {
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
  
  $allShares = scan("/mnt/user");
  foreach ($allShares as $share) {
    if ( ! is_file("/boot/config/shares/$share.cfg") ) {
      copy("/usr/local/emhttp/plugins/ransomware.bait/include/defaultShare.cfg","/boot/config/shares/$share.cfg");
    } 
  }
  
  # now work on UD mounted shareSecurity
  
  exec("mkdir -p ".$ransomwarePaths['udBackup']);
  $udShares = scan("/etc/samba/unassigned-shares");
  foreach ($udShares as $udShare) {
    copy("/etc/samba/unassigned-shares/$udShare",$ransomwarePaths['udBackup']."/$udShare");
    $iniFile = my_parse_ini_file("/etc/samba/unassigned-shares/$udShare",true);    
    $keys = array_keys($iniFile);
    foreach ($keys as $key) {
      $iniFile[$key]['valid users'] = "";
    }
    $newConfig = create_ini_file($iniFile,true);
    # now reformat it to be the same as before
    $newConfig = str_replace('"',"",$newConfig);
    $newConfig = str_replace("="," = ",$newConfig);
    
    file_put_contents("/etc/samba/unassigned-shares/$udShare",$newConfig);
  }
    

} */

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

############################################################################################
#                                                                                          #
# Function to get $_POST settings (easier than typing the whole thing over and over again) #
#                                                                                          #
############################################################################################

function getPost($setting,$default) {
  return isset($_POST[$setting]) ? urldecode(($_POST[$setting])) : $default;
}
?>
