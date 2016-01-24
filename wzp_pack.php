<?php
/**
 * YFAPP.WZP Pack
 *
 * @Author: Zhelneen Evgeniy
 * @Contacts: skype: zhelneen
 * @Date: 24 sep 2013
 *
 * @Notes: Compile using BamCompile: http://www.bambalam.se/bamcompile/
 */

// No time limits
set_time_limit(0);

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Recursive files list
function listdir($start_dir = '.', $root_dir = null) {
	if ($root_dir === null) {
		$root_dir = $start_dir;
	}
	$files = array();
	if (is_dir($start_dir)) {
		$fh = opendir($start_dir);
		while (($file = readdir($fh)) !== false) {
			if ($file === '.' || $file === '..') continue;
			$filepath = $start_dir.'/'.$file;
			array_push($files, substr($filepath, strlen($root_dir) + 1));
			if (is_dir($filepath)) {
				$files = array_merge($files, listdir($filepath, $root_dir));
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
$in_dir = isset($argv[1]) ? $argv[1] : "Input";

if (!file_exists($in_dir)) {
	fprintf(STDERR, "Error: Can't open directory %s\n", $in_dir);
	exit(1);
}

// Check root directory
if (
	(!file_exists($in_dir."/YFAPP")) &&
	(!file_exists($in_dir."/YFAP20")) &&
	(!file_exists($in_dir."/YFAP30"))) {
	fprintf(STDERR, "Error: YFAPP / YFAP20 / YFAP30 root directory is not found in %s\n", $in_dir);
	exit(1);
}

// WZP file, yfapp.wzp
$wzp_file = isset($argv[2]) ? $argv[2] : "out_yfapp.wzp";

// Clear out file
if (file_exists($wzp_file)) {
	unlink($wzp_file);
}

// Set packing method (0-9, default is 8)
$method = isset($argv[3]) ? intval($argv[3]) : 8;
printf("Pack method (0-9) is: %d\n", $method);

// Define default flag for files (if not specified)
$flag = 0xCCCCCCCC;
if (file_exists($in_dir."/YFAPP")) {
	$flag = 0xCCCCCCCC;
}
if (file_exists($in_dir."/YFAP20")) {
	$flag = 0x0154F4E4;
}
if (file_exists($in_dir."/YFAP30")) {
	$flag = 0x0012D830;
}

// Check flag for files
if (isset($argv[4])) {
	$flag = hexdec($argv[4]);
}
printf("File attribute flag is: 0x%08X\n", $flag);

// Make files list
$fnames = listdir($in_dir);

// Processing files
echo "Processing files...\n\n";
$table2 = "";
$offest2 = 0;
$wzp = fopen($wzp_file, "wb");

foreach ($fnames as $fname) {
	echo $fname."\n";

	$packed_contents = "";
	$prepared_fname = str_replace('/', '\\', $fname);

	if (is_dir($in_dir."/".$fname)) {
		$contents = "";
		$prepared_fname .= "\\";
	} else {
		$contents = file_get_contents($in_dir."/".$fname);
	}

	// Table 1 entry
	$entry1 = "";
	$entry1 .= pack("v", 0xd8ff);                   // block magic
	$entry1 .= pack("v", 0x0403);                   // block type
	$entry1 .= pack("v", 0x000a);                   // ver
	$entry1 .= pack("v", 0x0000);                   // flag (unused)
	$entry1 .= pack("v", $method);                  // compression method
	$entry1 .= pack("v", 0x0000);                   // modtime (unused)
	$entry1 .= pack("v", 0x0000);                   // moddate (unused)
	$entry1 .= pack("V", crc32($contents));         // CRC32 of file
	$entry1 .= pack("v", strlen($prepared_fname));  // length of filename
	$entry1 .= $prepared_fname;

	// Chunks table
	$chunk_size = 1 << 14; // 16384
	$chunks = 0;
	$chunks_table = "";
	if (strlen($contents) > 0) {
		$chunks = ceil(strlen($contents) / $chunk_size);
		for ($i = 0; $i < $chunks; $i++) {
			$unpacked_chunk = substr($contents, $i * $chunk_size, $chunk_size);
			$packed_chunk = gzcompress($unpacked_chunk, $method);
			$chunks_table .= pack("V", strlen($unpacked_chunk));
			$chunks_table .= pack("V", strlen($packed_chunk));
			$chunks_table .= pack("V", $flag);
			$packed_contents .= $packed_chunk;
		}
	}

	// Table 2 entry
	$entry2 = "";
	$entry2 .= pack("v", 0xd8ff);                   // block magic
	$entry2 .= pack("v", 0x0201);                   // block type
	$entry2 .= pack("v", 0x000a);                   // ver_made
	$entry2 .= pack("v", 0x000a);                   // ver_need
	$entry2 .= pack("v", 0x0000);                   // flag (unused)
	$entry2 .= pack("v", $method);                  // compression method
	$entry2 .= pack("v", 0x0000);                   // modtime (unused)
	$entry2 .= pack("v", 0x0000);                   // moddate (unused)
	$entry2 .= pack("V", crc32($contents));         // CRC32 of file
	$entry2 .= pack("v", strlen($prepared_fname));  // length of filename
	$entry2 .= pack("V", $chunks);                  // chunks count
	$entry2 .= $prepared_fname;
	$entry2 .= $chunks_table;

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
$header .= pack("V", strlen($table2));   // table 2 size
$header .= pack("V", $offest2);          // table 2 offest
$header .= "\\SDMMC\\";                  // source path
$header .= pack("v", 0x0007);            // length of source path string
fwrite($wzp, $header);

fclose($wzp);

echo "\nDone.\n";
echo "Files processed: ".count($fnames)."\n";
