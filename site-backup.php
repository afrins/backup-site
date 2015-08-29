<?php
ini_set("max_execution_time", 0);

class BackupSite {
    var $dir = "site-backup-dreamsoft";
    var $host = "localhost"; //host name
    var $username = "root"; //username
    var $password = ""; // your password
    var $dbname = "blog"; // database name
    var $con, $folder_name, $folder_len;

    function __construct() {
        if(!(file_exists($this->dir))) :
            mkdir($this->dir, 0777);
        endif;

        $path = dirname($_SERVER['PHP_SELF']);
        $position = strrpos($path,'/') + 1;
        $this->folder_name = substr($path,$position);
        $this->folder_len = strlen($this->folder_name);

        $this->DbConnect($this->host, $this->username, $this->password, $this->dbname);
        $this->backup_tables();
        $this->zipDb();
        $this->zipFilesFolders();
        $this->MoveZipToDirectory();
    }

    function DbConnect($h, $user, $pass, $db) {
        $this->con = mysqli_connect($h, $user, $pass, $db);

        // Check connection
        if (mysqli_connect_errno()) :
            echo "Failed to connect to MySQL: " . mysqli_connect_error();
            die();
        endif;
    }

    function backup_tables($tables = '*')
    {
        //get all of the tables
        $tables = array();
        $result = mysqli_query($this->con, 'SHOW TABLES');
        while($row = mysqli_fetch_row($result)) :
            $tables[] = $row[0];
        endwhile;

        $return = "";
      
        //cycle through
        foreach($tables as $table) :
            $result = mysqli_query($this->con, 'SELECT * FROM '.$table);
            $num_fields = mysqli_num_fields($result);
            $return.= 'DROP TABLE '.$table.';';
            $row2 = mysqli_fetch_row(mysqli_query($this->con, 'SHOW CREATE TABLE '.$table));
            $return.= "\n\n".$row2[1].";\n\n";
      
            while($row = mysqli_fetch_row($result)) :
                $return.= 'INSERT INTO '.$table.' VALUES(';
                for($j=0; $j<$num_fields; $j++) :
                    $row[$j] = addslashes($row[$j]);
                    $row[$j] = preg_replace("#\n#","\\n",$row[$j]);
                    if (isset($row[$j])) { $return.= '"'.$row[$j].'"' ; } else { $return.= '""'; }
                    if ($j<($num_fields-1)) { $return.= ','; }
                endfor;
                $return.= ");\n";
            endwhile;
            $return.="\n\n\n";
        endforeach;
      
        //save file
        $handle = fopen('db-backup-dreamsoft-'.time().'-'.(md5(implode(',',$tables))).'.sql','w+');
        fwrite($handle,$return);
        fclose($handle);
    }

    function zipDb() {
        $zipDb = new ZipArchive();
        if (glob("*.sql") != false) :
            $filecount = count(glob("*.sql"));
            $arr_file = glob("*.sql");
            for($j=0;$j<$filecount;$j++) :
                $res = $zipDb->open($arr_file[$j].".zip", ZipArchive::CREATE);
                if ($res === TRUE) :
                    $zipDb->addFile($arr_file[$j]);
                    $zipDb->close();
                    unlink($arr_file[$j]);
                endif;
            endfor;
        endif;
    }

    function zipFilesFolders() {
        $zipFF = new ZipArchive();
        $zipname = date('Y/m/d').'-'.time();
        $str = "dreamsoft-".$zipname.".zip";
        $str = str_replace("/", "-", $str);

        if ($zipFF->open($str, ZIPARCHIVE::CREATE) !== TRUE) :
            die ("Could not open archive");
        endif;

        $main_dir = "../".$this->folder_name;
        $this->create_zip($main_dir, $zipFF);

        $zipFF->close();
        echo "Your backup created successfully.";
    }

    function create_zip($dir, &$zip) {
        if ($handle = opendir($dir)) :
            while (($file = readdir($handle)) !== false) :
                if(($file != '.') && ($file != '..') ) :
                    if( strstr(realpath($file), "dreamsoft") == FALSE) :
                        if ( is_dir($dir.'/'.$file) ) :
                            $actual_dir = substr($dir, $this->folder_len + 4);
                            if ( !empty($actual_dir) ) :
                                $zip->addEmptyDir($actual_dir.'/'.$file);
                            endif;
                            $this->create_zip($dir.'/'.$file, $zip);
                        else :
                            $actual_dir = substr($dir, $this->folder_len + 4);
                            if ( !empty($actual_dir) ) :
                                $zip->addFile($actual_dir.'/'.$file);
                            else :
                                $zip->addFile($file);
                            endif;
                        endif;
                    endif;
                endif;
            endwhile;
          closedir($handle);
        endif;
    }

    function MoveZipToDirectory() {
        $arr_zip = $delete_zip = array();
        if (glob("*.zip") != false) :
            $arr_zip = glob("*.zip");
        endif;

        //copy the backup zip file to site-backup-dreamsoft folder
        foreach ($arr_zip as $key => $value) :
            if (strstr($value, "dreamsoft")) :
                $delete_zip[] = $value;
                copy("$value", $this->dir."/$value");
            endif;
        endforeach;

        for ($i=0; $i < count($delete_zip); $i++) :
            unlink($delete_zip[$i]);
        endfor;
    }
}

$objBackup = new BackupSite();
?>