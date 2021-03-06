<?php


define('PLUPLOAD_MOVE_ERR', 103);
define('PLUPLOAD_INPUT_ERR', 101);
define('PLUPLOAD_OUTPUT_ERR', 102);
define('PLUPLOAD_TMPDIR_ERR', 100);
define('PLUPLOAD_TYPE_ERR', 104);
define('PLUPLOAD_UNKNOWN_ERR', 111);
define('PLUPLOAD_SECURITY_ERR', 105);



App::uses('Component', 'Controller');

class PluploadHandlerComponent extends Component {


	public $conf;

	private $_error = null;

	private  $_errors = array(
		PLUPLOAD_MOVE_ERR => "Failed to move uploaded file.",
		PLUPLOAD_INPUT_ERR => "Failed to open input stream.",
		PLUPLOAD_OUTPUT_ERR => "Failed to open output stream.",
		PLUPLOAD_TMPDIR_ERR => "Failed to open temp directory.",
		PLUPLOAD_TYPE_ERR => "File type not allowed.",
		PLUPLOAD_UNKNOWN_ERR => "Failed due to unknown error.",
		PLUPLOAD_SECURITY_ERR => "File didn't pass security check."
	);


	/**
	 * Retrieve the error code
	 *
	 * @return int Error code
	 */
	 function get_error_code()
	{
		if (!$this->_error) {
			return null;
		} 

		if (!isset($this->_errors[$this->_error])) {
			return PLUPLOAD_UNKNOWN_ERR;
		}

		return $this->_error;
	}


	/**
	 * Retrieve the error message
	 *
	 * @return string Error message
	 */
	function get_error_message()
	{
		if ($code = $this->get_error_code()) {
			return $this->_errors[$code];
		}
		return '';
	}


	/**
	 * 
	 */
	function handle($conf = array())
	{
		// 5 minutes execution time
		@set_time_limit(5 * 60);

		$conf = $this->conf = array_merge(array(
			'file_data_name' => 'file',
			'tmp_dir' => ini_get("upload_tmp_dir") . DIRECTORY_SEPARATOR . "plupload",
			'target_dir' => false,
			'cleanup' => true,
			'max_file_age' => 5 * 3600,
			'chunk' => isset($_REQUEST['chunk']) ? intval($_REQUEST['chunk']) : 0,
			'chunks' => isset($_REQUEST['chunks']) ? intval($_REQUEST['chunks']) : 0,
			'file_name' => isset($_REQUEST['name']) ? $_REQUEST['name'] : uniqid('file_'),
			'allow_extensions' => false,
			'delay' => 0,
			'cb_sanitize_file_name' => array(__CLASS__, 'sanitize_file_name'),
			'cb_check_file' => false,
		), $conf);
		$realName = isset($_REQUEST['name']) ? $_REQUEST['name'] : $_FILES[$conf['file_data_name']]["name"];

		$this->_error = null; // start fresh

		try {
			// Cleanup outdated temp files and folders
			if ($conf['cleanup']) {
				$this->cleanup();
			}

			// Fake network congestion
			if ($conf['delay']) {
				usleep($conf['delay']);
			}

			if (is_callable($conf['cb_sanitize_file_name'])) {
				$file_name = call_user_func($conf['cb_sanitize_file_name'], $conf['file_name']);
			}

			// Check if file type is allowed
			if ($conf['allow_extensions']) {
				if (is_string($conf['allow_extensions'])) {
					$conf['allow_extensions'] = preg_split('{\s*,\s*}', $conf['allow_extensions']);
				}

				if (!in_array(strtolower(pathinfo($realName, PATHINFO_EXTENSION)), $conf['allow_extensions'])) {
					throw new Exception('', PLUPLOAD_TYPE_ERR);
				}
			}

			$file_path = rtrim($conf['target_dir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file_name;
			$tmp_path = $file_path . ".part";

			// Write file or chunk to appropriate temp location
			if ($conf['chunks']) {				
				$this->write_file_to("$file_path.dir.part" . DIRECTORY_SEPARATOR . $conf['chunk']);

				// Check if all chunks already uploaded
				if ($conf['chunk'] == $conf['chunks'] - 1) { 
					$this->write_chunks_to_file("$file_path.dir.part", $tmp_path);
				}
			} else {
				$this->write_file_to($tmp_path);
			}

			// Upload complete write a temp file to the final destination
			if (!$conf['chunks'] || $conf['chunk'] == $conf['chunks'] - 1) {
				rename($tmp_path, $file_path);

				if (is_callable($conf['cb_check_file']) && !call_user_func($conf['cb_check_file'], $file_path)) {
					@unlink($file_path);
					throw new Exception('', PLUPLOAD_SECURITY_ERR);
				}
			}
		} catch (Exception $ex) {
			$this->_error = $ex->getCode();
			return false;
		}

		return true;
	}


	/**
	 * Writes either a multipart/form-data message or a binary stream 
	 * to the specified file.
	 *
	 * @throws Exception In case of error generates exception with the corresponding code
	 *
	 * @param string $file_path The path to write the file to
	 * @param string [$file_data_name='file'] The name of the multipart field
	 */
	function write_file_to($file_path, $file_data_name = false)
	{
		if (!$file_data_name) {
			$file_data_name = $this->conf['file_data_name'];
		}

		$base_dir = dirname($file_path);
		if (!file_exists($base_dir) && !@mkdir($base_dir, 0777, true)) {
			throw new Exception('', PLUPLOAD_TMPDIR_ERR);
		}

		if (!empty($_FILES) && isset($_FILES[$file_data_name])) {
			if ($_FILES[$file_data_name]["error"] || !is_uploaded_file($_FILES[$file_data_name]["tmp_name"])) {
				throw new Exception('', PLUPLOAD_MOVE_ERR);
			}
			move_uploaded_file($_FILES[$file_data_name]["tmp_name"], $file_path);
		} else {	
			// Handle binary streams
			if (!$in = @fopen("php://input", "rb")) {
				throw new Exception('', PLUPLOAD_INPUT_ERR);
			}

			if (!$out = @fopen($file_path, "wb")) {
				throw new Exception('', PLUPLOAD_OUTPUT_ERR);
			}

			while ($buff = fread($in, 4096)) {
				fwrite($out, $buff);
			}

			@fclose($out);
			@fclose($in);
		}
	}


	/**
	 * Combine chunks from the specified folder into the single file.
	 *
	 * @throws Exception In case of error generates exception with the corresponding code
	 *
	 * @param string $chunk_dir Temp directory with the chunks
	 * @param string $file_path The file to write the chunks to
	 */
	function write_chunks_to_file($chunk_dir, $file_path)
	{
		if (!$out = @fopen($file_path, "wb")) {
			throw new Exception('', PLUPLOAD_OUTPUT_ERR);
		}

		for ($i = 0; $i < $this->conf['chunks']; $i++) {
			$chunk_path = $chunk_dir . DIRECTORY_SEPARATOR . $i;
			if (!file_exists($chunk_path)) {
				throw new Exception('', PLUPLOAD_MOVE_ERR);
			}

			if (!$in = @fopen($chunk_path, "rb")) {
				throw new Exception('', PLUPLOAD_INPUT_ERR);
			}

			while ($buff = fread($in, 4096)) {
				fwrite($out, $buff);
			}
			@fclose($in);

			// chunk is not required anymore
			@unlink($chunk_path);
		}
		@fclose($out);

		// Cleanup
		$this->rrmdir($chunk_dir);
	}


	function no_cache_headers() 
	{
		// Make sure this file is not cached (as it might happen on iOS devices, for example)
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
	}


	function cors_headers($headers = array(), $origin = '*')
	{
		$allow_origin_present = false;

		if (!empty($headers)) {
			foreach ($headers as $header => $value) {
				if (strtolower($header) == 'access-control-allow-origin') {
					$allow_origin_present = true;
				}
				header("$header: $value");
			}
		}

		if ($origin && !$allow_origin_present) {
			header("Access-Control-Allow-Origin: $origin");
		}

		// other CORS headers if any...
		if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
			exit; // finish preflight CORS requests here
		}
	}


	private function cleanup() 
	{
		// Remove old temp files	
		if (file_exists($this->conf['target_dir'])) {
			foreach(glob($this->conf['target_dir'] . '/*.part') as $tmpFile) {
				if (time() - filemtime($tmpFile) < $this->conf['max_file_age']) {
					continue;
				}
				if (is_dir($tmpFile)) {
					$this->rrmdir($tmpFile);
				} else {
					@unlink($tmpFile);
				}
			}
		}
	}


	/**
	 * Sanitizes a filename replacing whitespace with dashes
	 *
	 * Removes special characters that are illegal in filenames on certain
	 * operating systems and special characters requiring special escaping
	 * to manipulate at the command line. Replaces spaces and consecutive
	 * dashes with a single dash. Trim period, dash and underscore from beginning
	 * and end of filename.
	 *
	 * @author WordPress
	 *
	 * @param string $filename The filename to be sanitized
	 * @return string The sanitized filename
	 */
	private function sanitize_file_name($filename) 
	{
	    $special_chars = array("?", "[", "]", "/", "\\", "=", "<", ">", ":", ";", ",", "'", "\"", "&", "$", "#", "*", "(", ")", "|", "~", "`", "!", "{", "}");
	    $filename = str_replace($special_chars, '', $filename);
	    $filename = preg_replace('/[\s-]+/', '-', $filename);
	    $filename = trim($filename, '.-_');
	    return $filename;
	}


	/** 
	 * Concise way to recursively remove a directory 
	 * http://www.php.net/manual/en/function.rmdir.php#108113
	 *
	 * @param string $dir Directory to remove
	 */
	private function rrmdir($dir) 
	{
		foreach(glob($dir . '/*') as $file) {
			if(is_dir($file))
				$this->rrmdir($file);
			else
				unlink($file);
		}
		rmdir($dir);
	}
}