<?
#########################################################
#                                                       #
# Ransomware Protection copyright 2016, Andrew Zawadzki #
#                                                       #
#########################################################

function getLineCount($directory) {
  global $lineCount;

  $allFiles = array_diff(scandir($directory),array(".",".."));
  foreach ($allFiles as $file) {
    if (is_dir("$directory/$file")) {
      getLineCount("$directory/$file");
      continue;
    }
    $extension = pathinfo("$directory/$file",PATHINFO_EXTENSION);
    if ( $extension == "sh" || $extension == "php" || $extension == "page" ) {
      $lineCount = $lineCount + count(file("$directory/$file"));
    }
  }
}
$rpCredits = "
    <center><table align:'center'>
      <tr>
        <td><img src='http://www.jrj-socrates.com/Cartoon%20Pics/Misc/Tripping%20The%20Rift/Chode_300.gif' width='50px';height='48px'></td>
        <td><strong>Andrew Zawadzki</strong></td>
        <td>Main Development</td>
      </tr>
      <tr>
        <td></td>
        <td><strong>RobJ</strong></td>
        <td>Additional Ideas</td>
      </tr>
      <tr>
        <td><img src='https://upload.wikimedia.org/wikipedia/commons/3/34/Bram_Stoker_1906.jpg' width='50px'></td>
        <td><strong>Bram Stoker</strong></td>
        <td><em>Dracula</em> &copy;1897 (copyright expired)<br>Random word dictionary</br>Read it <a href='http://literature.org/authors/stoker-bram/dracula/index.html'>Here</a></td>
      </tr>
    </table></center>
    <br>
    <center><em><font size='1'>Copyright 2016 Andrew Zawadzki</font></em></center>
    <center><a href='https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=7M7CBCVU732XG' target='_blank'><img src='https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif'></a></center>
    <br><center><a href='http://lime-technology.com/forum/index.php?topic=52462.0' target='_blank'>Plugin Support Thread</a></center>
  ";
getLineCount("/usr/local/emhttp/plugins/ransomware.bait");
$rpCredits .= "$lineCount Lines of code and counting!";
$rpCredits = str_replace("\n","",$rpCredits);
?>