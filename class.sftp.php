<?PHP
/* ******************************************************************************************************************** / 
CLASS NAME: SFTP
DESCRIPTION: This class is used to make SFTP connections
***********************************************************************************************************************/
class Sftp {
    private $connection;
    private $sftp;

    public function __construct($host, $port=22) {
        $this->connection = ssh2_connect($host, $port);
        
        if (! $this->connection) {
            echo "Could not connect to $host on port $port.";
        }        
    }
    
    /* ******************************************************************************************************************** / 
    METHOD: login
    DESCRIPTION: This method will make the sftp connection
    PARAMETERS: $username - username for connection
                $password - password for connection
    RETURNS: 
    ***********************************************************************************************************************/
    public function login($username, $password) {
        if (! @ssh2_auth_password($this->connection, $username, $password)) {
            throw new Exception("Could not authenticate with username $username " . "and password $password.");
        }
        
        $this->sftp = @ssh2_sftp($this->connection);
        
        if (! $this->sftp) {
            throw new Exception("Could not initialize SFTP subsystem.");
        }
    }
    
    /* ******************************************************************************************************************** / 
    METHOD: uploadFile
    DESCRIPTION: This method will upload a file
    PARAMETERS: $localfile - file to upload
                $remotefile - name of file for remote server
    RETURNS: 
    ***********************************************************************************************************************/
    public function uploadFile($localfile, $remotefile) {
        $sftp = $this->sftp;      
        $stream = @fopen("ssh2.sftp://$sftp$remotefile", 'w');
        
        if (!$stream){
            throw new Exception("Could not open file: $remotefile");
        }
        
        $data_to_send = @file_get_contents($localfile);
        
        if ($data_to_send === false) {
            throw new Exception("Could not open local file: $localfile.");
        }
        
        if (@fwrite($stream, $data_to_send) === false) {
            throw new Exception("Could not send data from file: $localfile.");
        }
        
        @fclose($stream);
        
        return 'Success';
    }
    
    /* ******************************************************************************************************************** / 
    METHOD: uploadFileContents
    DESCRIPTION: This method will upload contents for file
    PARAMETERS: $contents - content for file
                $remotefile - name of file for remote server
    RETURNS: 
    ***********************************************************************************************************************/
    public function uploadFileContents($contents, $remotefile) {
        $sftp = $this->sftp;
        $stream = @fopen("ssh2.sftp://$sftp$remotefile", 'w');
        
        if (!$stream){
            throw new Exception("Could not open file: $remotefile");
        }
        
        if ($data_to_send === false) {
            throw new Exception("Could not open local file: $localfile.");
        }
        
        if (@fwrite($stream, $contents) === false) {
            throw new Exception("Could not send data from file: $localfile.");
        }
        
        @fclose($stream);
        
        return 'Success';
    }    
    
    /* ******************************************************************************************************************** / 
    METHOD: scanFilesystem
    DESCRIPTION: This method will return files in a directory
    PARAMETERS: $remotedir - folder
    RETURNS: $fileArray - array of files from folder
    ***********************************************************************************************************************/    
    function scanFilesystem($remotedir) {
        $sftp = $this->sftp;
        $dir = "ssh2.sftp://$sftp$remotedir";        
                
        $fileArray = array();
        $handle = opendir($dir);
        
        // List all the files //////////////////////////
        while (false !== ($file = readdir($handle))) {
            if (substr("$file", 0, 1) != ".") {
                if(!is_dir($file)) {
                     $fileArray[] = $file;
                } 
            }
        }
        
        closedir($handle);
        return $fileArray;
    }   
    
    /* ******************************************************************************************************************** / 
    METHOD: downloadFile
    DESCRIPTION: This method download file from remote server
    PARAMETERS: $remotefile - file to get
                $localfile - name to save file as
    RETURNS: 
    ***********************************************************************************************************************/
    public function downloadFile($remotefile, $localfile){
        $sftp = $this->sftp;
        ssh2_scp_recv($this->connection, $remotefile, $localfile);
    }

    /* ******************************************************************************************************************** / 
    METHOD: receiveFile
    DESCRIPTION: This method will get file contents from remote server and save to local file
    PARAMETERS: $remotefile - file to get
                $localfile - name to save file as
    RETURNS: 
    ***********************************************************************************************************************/ 
    public function receiveFile($remotefile, $localfile, $contentsonly = 'no') {
        $sftp = $this->sftp;
        $stream = @fopen("ssh2.sftp://$sftp$remotefile", 'r');
        
        if (! $stream){
            throw new Exception("Could not open file: $remotefile");
        }        
        
        $size = $this->getFileSize($remotefile);            
        $contents = '';
        $read = 0;
        $len = $size;
        
        while ($read < $len && ($buf = fread($stream, $len - $read))) {
            $read += strlen($buf);
            $contents .= $buf;
        }  
              
        if($contentsonly == 'no'){
            file_put_contents ($localfile, $contents);
        }
        else return $contents;
        
        @fclose($stream);
    }
    
    /* ******************************************************************************************************************** / 
    METHOD: getFileSize
    DESCRIPTION: This method will return the file's size
    PARAMETERS: $file - file to get size
    RETURNS: size of file
    ***********************************************************************************************************************/
    public function getFileSize($file){
        $sftp = $this->sftp;
        return filesize("ssh2.sftp://$sftp$file");
    }
    
    /* ******************************************************************************************************************** / 
    METHOD: deleteFile
    DESCRIPTION: This method will delete a file
    PARAMETERS: $remotefile - file to delete
    RETURNS: 
    ***********************************************************************************************************************/     
    public function deleteFile($remotefile) {
        $sftp = $this->sftp;
        unlink("ssh2.sftp://$sftp$remotefile");
    }
}