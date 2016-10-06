<?PHP
$history = file_get_contents("/boot/config/plugins/ransomware.bait/smbStatusFile.txt");
$history = str_replace("\r","",$history);
$history = str_replace("\n","<br>",$history);
$history = str_replace(" ","&nbsp;",$history);
echo "<tt>$history</tt>";
?>

