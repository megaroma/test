<?php
require_once "dropbox-sdk-php-1.1.4/lib/Dropbox/autoload.php";
use \Dropbox as dbx;

//conf
$accessToken = 'Iu10mt0MPisAAAAAAADZ2t5j-C-C1VgpzPslCg8t9Ak0wf-YR81r4mQBEktaPSX_';

$dbxClient = new dbx\Client($accessToken, "PHP-Example/1.0");

//$folder = "files";
$folder = "/mnt/Backups/WW/dropbox";
$dropbox_dir = "/1. SHARED FOLDERS";

//------Classes-----------------------------------
function dirToArray($folder, $dir) {
   $result = array();
   $result['folders']= array(); 
	$result['files']= array(); 

   $cdir = scandir($folder.DIRECTORY_SEPARATOR.$dir);
   foreach ($cdir as $key => $value)
   {
      if (!in_array($value,array(".","..")))
      {
         if (is_dir($folder.DIRECTORY_SEPARATOR.$dir.DIRECTORY_SEPARATOR.$value))
         {
            $result['folders'][] = "/".$dir ."/". $value;
         }
         else
         {
            $result['files'][] = "/".$dir ."/". $value;
         }
      }
   }  
   return $result;
} 


function DropBoxToArray($db_data) {
$data = array();
$root = '';	
if(isset($db_data['path'])) {
$data = $db_data;
$root = $db_data['path'];
} elseif(isset($db_data[1]['path'])) {
$data = $db_data[1];
$root = $db_data[1]['path'];
}
$res = array();
if ($root == '') return $data;

	if((isset($data['contents']) && (count($data['contents']) > 0) )) {
		get_dirs($root,$data['contents'],$res);
	}

return $res;
}

function get_dirs($root,$arr, &$res) {
	foreach($arr as $d) {
		if($d['is_dir'] == 1) {
			$res['folders'][] = $root.$d['path']; 
			if((isset($d['contents']) && (count($d['contents']) > 0) )) {
				get_dirs($root.$d['path'],$d['contents'],$res);
			}
		} else {
			$res['fiels'][] = $root.$d['path']; 
		}
	}
}

//--hashes 
Class HashDir {
	private static $data = array();
	public static function load($filename) {
		if(file_exists($filename)) {
			$buf = file_get_contents($filename);
			$lines = preg_split ('/$\R?^/m', $buf);
			foreach ($lines as $line) {
				$d = explode('|', $line, 2);
				if(isset($d[1])) {
					self::$data[$d[1]] = $d[0];
				}
			}	
		}
	} 
	public static function set($dir , $hash) {
		if((isset(self::$data[$dir])) &&(self::$data[$dir] == $hash )) {
			return false;
		} else {
			self::$data[$dir] = $hash; 
			return true;
		}
	}
	public static function remove($dir) {
		if(isset(self::$data[$dir])) unset(self::$data[$dir]);
	} 
	public static function save($filename) {
		$buf = "";
		foreach (self::$data as $dir => $hash) {
			$buf .= $hash.'|'.$dir."\n";
		}
		file_put_contents($filename, $buf);
	}
}
//--
class Stack {
	private static $data = array();
	public static function add($dir) {
		array_push(self::$data, $dir);
	} 
	public static function get() {
		$dir = array_pop(self::$data);
		if($dir !== NULL ) {
			return $dir;
		} else {
			return false;
		}
	}
} 

class DropBox {
	private static $root_hdd_folder = "";
	private static $files = array();
	private static $folders = array();
	private static $hash = '';
	private static $dir = '';
	private static $dbx;

	public static function Init($dbx,$root) {
		self::$dbx = $dbx;
		self::$root_hdd_folder = $root;
	}
	public static function CheckDir($dir) {
		self::$files = array();
		self::$folders = array();
		self::$hash = '';
		self::$dir = '';
		$folderMetadata = self::$dbx->getMetadataWithChildren($dir);
		self::$hash = $folderMetadata['hash'];
		self::$dir = $dir;
		foreach ($folderMetadata['contents'] as $d) {
			if($d['is_dir'] == 1) {
				self::$folders[] = $d['path'];
			} else {
				self::$files[] = $d['path'];
			}
		}
	}

	public static function apply_dirs($folders) {
		foreach (self::$folders as $folder) {
			if(in_array($folder,$folders)) {
				$key = array_search($folder, $folders);
				unset($folders[$key]);
			} else {
				mkdir(self::$root_hdd_folder.$folder);	
			}
		}
		foreach($folders as $folder) {
			foreach(glob(self::$root_hdd_folder.$folder.'*.*') as $v){
				unlink($v);
			}
			rmdir(self::$root_hdd_folder.$folder);
		}
	}

	public static function apply_files($files) {
		foreach (self::$files as $file) {
			if(in_array($file,$files)) {
				$key = array_search($file, $files);
				unset($files[$key]);
			} else {
				$f = fopen(self::$root_hdd_folder.$file, "w+b");
				$fileMetadata = self::$dbx->getFile($file, $f);
				fclose($f);				
			}
		}
		foreach($files as $file) {
				unlink(self::$root_hdd_folder.$file);
		}
	}

	public static function get_hash() {
		return self::$hash;
	}
	public static function get_files() {
		return self::$files;
	}
	public static function get_folders() {
		return self::$folders;
	}
}

//------------------------code----------------------------------

DropBox::init($dbxClient,$folder);
Stack::add($dropbox_dir);
$dir = $folder.$dropbox_dir;
if (!((file_exists($dir)) && (is_dir($dir)))) {
    mkdir($dir);         
} 

HashDir::load('cron_dropbox.dat');

while ($dir = Stack::get()) {

DropBox::CheckDir($dir);

$folders = DropBox::get_folders();
$hdd = dirToArray($folder,trim($dir,"/"));
DropBox::apply_dirs($hdd['folders']);
foreach ($folders as $f) {
	Stack::add($f);
}

if(HashDir::set($dir, DropBox::get_hash())) {
	DropBox::apply_files($hdd['files']);
}

}

HashDir::save('cron_dropbox.dat');

echo "Ok";
?>
