<?php
App::uses('AppController', 'Controller');

class UploadController extends AppController {

	public $components = array('Upload.PluploadHandler');

    public function admin_uploadHandler()
    {
		$name = isset($_REQUEST['name']) ? $_REQUEST['name'] : $_FILES['file']["name"];
	    $this->PluploadHandler->no_cache_headers();
		$this->PluploadHandler->cors_headers();
		
		
		$chunk = isset($_REQUEST['chunk']) ? intval($_REQUEST['chunk']) : 0;
		$chunks = isset($_REQUEST['chunks']) ? intval($_REQUEST['chunks']) : 0;
		
		$tmpName =  uniqid('file_');
		if($chunks)
		{
			if($chunk)
			{
				$tmpName =  $this->Session->read('Upload.tmp_names.'.$name);
				if(empty($tmpName))
				{
					die(json_encode(array(
						'OK' => 0, 
						'error' => array(
							'message' => 'Temp name does not exist in the Session'
						)
					)));
				}
			}
			else
			{
				$this->Session->write('Upload.tmp_names.'.$name, $tmpName);
			}
		}
        
		
		
		if (!$this->PluploadHandler->handle(array(
			'target_dir' => TMP,
			'file_name' => $tmpName,
			'allow_extensions' => array('jpg','jpeg','png','dmg','exe')
		))) {
			die(json_encode(array(
				'OK' => 0, 
				'error' => array(
					'code' => $this->PluploadHandler->get_error_code(),
					'message' => $this->PluploadHandler->get_error_message()
				)
			)));
		} else {
			die(json_encode(array('OK' => 1, "name" => $name , "tmp_name" => TMP.$tmpName)));
		}
    
    

    }
}