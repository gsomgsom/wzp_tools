<?php
/**
 * YFAPP.WZP Pack
 *
 * @Author: Zhelneen Evgeniy
 * @Contacts: skype: zhelneen
 * @Date: 24 sep 2013
 *
 * @Notes: Compile using BanCompile: http://www.bambalam.se/bamcompile/
 */

// No time limits
set_time_limit(0);

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Recursive files list
function listdir($start_dir = '.') {
	global $in_dir;
	$files = array();
	if (is_dir($start_dir)) {
		$fh = opendir($start_dir);
		while (($file = readdir($fh)) !== false) {
			if ((strcmp($file, '.') == 0) || (strcmp($file, '..') == 0)) continue;
			$filepath = $start_dir.'/'.$file;
			if (is_dir($filepath)) {
				array_push($files, str_replace("/", "\\", substr($filepath, strlen($in_dir)+1)."/"));
				$files = array_merge($files, listdir($filepath));
			}
			else {
				array_push($files, str_replace("/", "\\", substr($filepath, strlen($in_dir)+1)));
			}
		}
		closedir($fh);
	}
	else {
		$files = false;
	}
	return $files;
}

// Usage info
echo "YFAPP.WZP file packer by Zhelneen Evgeniy.\n";
echo "usage: wzp_pack [in_dir] [out_yfapp.wzp] [method] [hex_flag]\n";
echo "example: wzp_pack Input out_yfapp.wzp 8 0xCCCCCCCC\n\n";

// Input directory (default is "Input")
if (@$argv[1] == "")
  $in_dir = "Input";
else
  $in_dir = $argv[1];

if (!file_exists($in_dir)) {
	echo "Error: Can't open directory ".$in_dir."\n";
	die();
}

// Check root directory
if (
	(!file_exists($in_dir."\\YFAPP")) &&
	(!file_exists($in_dir."\\YFAP20")) &&
	(!file_exists($in_dir."\\YFAP30"))) {
	echo "Error: YFAPP / YFAP20 / YFAP30 root directory is not found in ".$in_dir."\n";
	die();
}

// WZP file, yfapp.wzp
if (@$argv[2] == "")
  $wzp_file = "out_yfapp.wzp";
else
  $wzp_file = $argv[2];

// Clear out file
if (file_exists($wzp_file)) {
	unlink($wzp_file);
}

// Set packing method (0-9)
$method = 8; // default value
if (@$argv[3] != "") {
echo $argv[3];
	$method = intval($argv[3]);
}
echo "Pack method (0-9) is: ".$method."\n";

// Define default flag for files (if not specified)
$flag = 0xCCCCCCCC;
if (file_exists($in_dir."\\YFAPP")) {
	$flag = 0xCCCCCCCC;
}
if (file_exists($in_dir."\\YFAP20")) {
	$flag = 0x0154F4E4;
}
if (file_exists($in_dir."\\YFAP30")) {
	$flag = 0x0012D830;
}

// Check flag for files
if (@$argv[4] != "") {
	$flag = hexdec($argv[4]);
}
echo "File attribute flag is: 0x".strtoupper(dechex($flag))."\n";

// Make files list
$fnames = listdir($in_dir);

// Processing files
echo "Processing files...\n\n";
$table2 = "";
$offest2 = 0;
$wzp = fopen($wzp_file, "wb");

foreach ($fnames as $fname) {
	echo $fname."\n";

	$contents = "";
	$packed_contents = "";

	$is_file = false;
	// File
	if (!(substr($fname, -1, 1) == '\\')) {
		$contents = file_get_contents($in_dir."\\".$fname);
		$is_file = true;
	}

	// Table 1 entry
	$entry1 = "";
	$entry1 .= pack("v", 0xd8ff);            // block magic
	$entry1 .= pack("v", 0x0403);            // block type
	$entry1 .= pack("v", 0x000a);            // ver
	$entry1 .= pack("v", 0x0000);            // flag (unused)
	$entry1 .= pack("v", $method);           // compression method
	$entry1 .= pack("v", 0x0000);            // modtime (unused)
	$entry1 .= pack("v", 0x0000);            // moddate (unused)
	$entry1 .= pack("V", crc32($contents));  // CRC32 of file
	$entry1 .= pack("v", strlen($fname));    // length of filename
	$entry1 .= $fname;

	if (($is_file) && (strlen($contents))) {
		$packed_contents = gzcompress($contents, $method);
	}

	// Chunks table (TODO: split into 16384-bytes chunks)
	$chunks = 0;
	$chinks_table = "";
	if (strlen($contents) > 0) {
		$chunks = 1;
		$chinks_table .= pack("V", strlen($contents));
		$chinks_table .= pack("V", strlen($packed_contents));
		$chinks_table .= pack("V", $flag);
	}

	// Table 2 entry
	$entry2 = "";
	$entry2 .= pack("v", 0xd8ff);            // block magic
	$entry2 .= pack("v", 0x0201);            // block type
	$entry2 .= pack("v", 0x000a);            // ver_made
	$entry2 .= pack("v", 0x000a);            // ver_need
	$entry2 .= pack("v", 0x0000);            // flag (unused)
	$entry2 .= pack("v", $method);           // compression method
	$entry2 .= pack("v", 0x0000);            // modtime (unused)
	$entry2 .= pack("v", 0x0000);            // moddate (unused)
	$entry2 .= pack("V", crc32($contents));  // CRC32 of file
	$entry2 .= pack("v", strlen($fname));    // length of filename
	$entry2 .= pack("V", $chunks);           // chunks count
	$entry2 .= $fname;
	$entry2 .= $chinks_table;

	$table2 .= $entry2;

	fwrite($wzp, $entry1);
	fwrite($wzp, $packed_contents);

	$offest2 += (strlen($entry1) + strlen($packed_contents));
}

fwrite($wzp, $table2);

// Header
$header = "";
$header .= pack("v", 0xd9ff);            // block magic
$header .= pack("v", 0x0403);            // block type
$header .= pack("v", count($fnames));    // files count
$header .= pack("V", strlen($table2));   // size (todo)
$header .= pack("V", $offest2);          // table 2 offest
$header .= "\\SDMMC\\";                  // source path (?)
$header .= pack("v", 0x0007);            // length of source path string
fwrite($wzp, $header);

fclose($wzp);

echo "\nDone.\n";
echo "Files processed: ".count($fnames)."\n";
