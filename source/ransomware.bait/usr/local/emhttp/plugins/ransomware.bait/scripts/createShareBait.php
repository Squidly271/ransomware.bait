#!/usr/bin/php
<?
#########################################################
#                                                       #
# Ransomware Protection copyright 2016, Andrew Zawadzki #
#                                                       #
#########################################################

require_once("/usr/local/emhttp/plugins/ransomware.bait/include/helpers.php");
require_once("/usr/local/emhttp/plugins/ransomware.bait/include/paths.php");

$unRaidVars = parse_ini_file("/var/local/emhttp/var.ini");
if ( strtolower($unRaidVars['mdState']) != "started" ) {
  logger("Array Not Started.  Exiting");
  exit;
}

$allSettings = readSettingsFile();
if ( ! $allSettings ) {
  logger("No Settings Defined For Ransomware Protection - Exiting");
  exit;
}

$settings = $allSettings['shareSettings'];
if ( $settings['enableShareService'] != "true" ) {
  logger("Bait Share Service Not Enabled.  Exiting");
  exit;
}
if ( ! trim($settings['sharePrefix']) ) {
  logger("Share Prefix Not Defined.  Exiting");
  exit;
}
if ( is_file($ransomwarePaths['createSharePID']) ) {
  logger("Bait Share Creation Appears To Be Already In Progress.  Exiting");
  exit;
}
if ( ! isfile("/usr/bin/inotifywait") ) {
  logger("inotify tools not installed.  Install it via NerdPack plugin available within Community Applications");
  notify("Ransomware Protection","inotify-tools not installed","inotify tools must be installed (via NerdPack plugin) for this plugin to operate","","warning");
  exit;
}

if ( isfile($ransomwarePaths['baitShares']) ) {
  logger("It appears previous bait shares still exist on array.  Exiting");
  exit;
}
$numberShares = $settings['numberShares'];
$numberFoldersPerShare = $settings['numberFoldersPerShare'];
$numberLevels = $settings['folderDepth'];
$numberFinalFolders = $numberFoldersPerShare;
$numberFiles = $settings['numberBaitPerFolder'];
$sharePrefix = trim($settings['sharePrefix']);

if ( ($numberShares * $numberFoldersPerShare * $numberLevels * $numberFiles) < 1 ) {
  logger("Invalid settings for bait share");
}

file_put_contents($ransomwarePaths['createSharePID'],getmypid());
@unlink($ransomwarePaths['baitShareCount']);
$baitContents = array_diff(scandir("/usr/local/emhttp/plugins/ransomware.bait/bait"),array(".",".."));
foreach ($baitContents as $bait) {
  $fileExtensions[] = pathinfo($bait,PATHINFO_EXTENSION);
}
$separatorList = array(" ",".","_","-");
$dict = file_get_contents("/usr/local/emhttp/plugins/ransomware.bait/superBait/dictionary.txt");
$dict = preg_replace("/[^A-Za-z0-9]/", ' ', $dict);
$dictionary = explode(" ",$dict);

logger("Creating Folder Structure");
file_put_contents($ransomwarePaths['shareStatus'],"Creating Folder Structure");

for ( $i = 0; $i < $numberShares; $i++ ) {
  while ( true ) {
    $basepath = "/mnt/user/$sharePrefix-".randomWord($dictionary)."/";
    if ( ! is_dir($path) ) {
      mkdir($basepath);
      break;
    } 
  }
  $baitShares[] = $basepath;
  file_put_contents($ransomwarePaths['baitShares'],"$basepath\n",FILE_APPEND);
  for ($jj=0; $jj < $numberFoldersPerShare; $jj++ ) {
    $path = $basepath;
    for ( $j = 0; $j < $numberLevels; $j++ ) {
      $path .= randomWord($dictionary)."_".randomWord($dictionary);
      if ( mt_rand(0,1) ) {
        $path.= "_".randomWord($dictionary);
      }
      $path .= "/";

      for ( $k = 0; $k < $numberFinalFolders; $k++ ) {
        $createFolder[] = $path.randomWord($dictionary)."/";
        ++$count;
      }
    }
  }
}
for ($kk = count($createFolder) -1 ; $kk >= 0; $kk--) {
  if ( is_dir($createFolder[$kk]) ) { continue; }
  $createPath = escapeshellarg($createFolder[$kk]);
  echo $createPath."\n";
  mkdir($createFolder[$kk],0777,true);
#  exec("mkdir -p $createPath");
  $createCount++;
}

file_put_contents($ransomwarePaths['shareStatus'],"Creating Bait Files");
logger("Creating Bait Files");
$total = count($baitShares);
$completed = 0;

$startTime = time();
foreach ($baitShares as $share) {
  exec("cp /usr/local/emhttp/plugins/ransomware.bait/bait/* $share"); 
  unset($linkArray);
  $initialBait = array_diff(scandir($share),array(".",".."));
  
  foreach ($initialBait as $bait) {
    $linkArray[pathinfo($bait,PATHINFO_EXTENSION)] = $share."/".$bait;
  }
  createBait($share);
  ++$completed;
  $timeElapsed = time() - $startTime;
  logger("Bait Files Created: $filecount (".intval($filecount / $timeElapsed)."/second) Completed: ".intval($completed / $total * 100)."%");
  file_put_contents($ransomwarePaths['shareStatus'],"Bait Files Created: $filecount (".intval($filecount / $timeElapsed)."/second) Completed: ".intval($completed / $total * 100)."%");

}
echo "estimate: ".($numberFoldersPerShare * $numberFiles * $numberLevels * $numberFinalFolders);
exec("/usr/local/emhttp/plugins/ransomware.bait/scripts/countBaitShares.php");
@unlink($ransomwarePaths['shareStatus']);
@unlink($ransomwarePaths['createSharePID']);

##############################################################################################

function createBait($path) {
  global $numberFiles, $filecount, $linkArray, $ransomwarePaths;
  
  $contents = array_diff(scandir($path),array(".",".."));
    
  foreach ($contents as $directory) {
    if ( is_dir($path.$directory."/") ) {
      createBait($path.$directory."/");
    }
    file_put_contents($ransomwarePaths['shareStatus'],"Creating Bait Files: (so far, $filecount created)");
  }
  for ( $i = 0; $i < $numberFiles; $i++ ) {
    $newFile = $path.randomFile();
    $newExtension = pathinfo($newFile,PATHINFO_EXTENSION);
    echo "Linking $newFile\n";
    link($linkArray[$newExtension],$newFile);
#    exec("ln $linkArray[$newExtension] '$newFile'");  #create a hardlink
    ++$filecount;
  }
}


function randomFile() {
  global $fileExtensions, $separatorList, $dictionary;
  
  $extension = randomArray($fileExtensions);
  $separator = randomArray($separatorList);
  
  $filename = randomWord($dictionary).$separator.randomWord($dictionary).$separator.randomWord($dictionary);
  if ( mt_rand(0,1) ) {
    $filename .= $separator.randomWord($dictionary);
  }
  if ( mt_rand(0,4) == 1 ) {
    $filename .= "-".date("m.d.y",mt_rand(0,time()));
  }
  $filename .= ".$extension";
  return $filename;
}

  
function randomWord($wordArray) {
  while (true) {
    $word = randomArray($wordArray);
    if ( strlen($word) < 4 ) {
      continue;
    }
    break;
  }
  return $word;
}

function randomArray($inputArray) {
  return $inputArray[mt_rand(0,count($inputArray)-1)];
}

    
?>
