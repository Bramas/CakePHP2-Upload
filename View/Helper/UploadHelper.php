<?php

App::uses('AppHelper', 'View/Helper');

class UploadHelper extends AppHelper {

    public $helpers = array('Form', 'Html');

    private $uploaderId = 0;

    private $_model = null;


    public function setModel($model){
        $this->_model = $model;
    }
    public function setFormHelper($helper){
        $this->Form = $helper;
    }


    public function input($name, $option) {
        
        ob_start();

        echo $this->Html->script('Upload.plupload.full.min');

?>
<div id="upload-fl-<?php echo $name ?>">Your browser doesn't have Flash, Silverlight or HTML5 support.</div>
<br />
 
<div id="upload-container-<?php echo $name ?>">
    <div class="uploader-images-progressbar">
        <div class="uploader-images-progressbar-overlay">
        </div>
    </div>
    <div class="uploader-images"><?php if($this->_model 
    && !empty($this->request->data[$this->_model]) 
    && !empty($this->request->data[$this->_model][$name]))
    {
        echo $this->Html->image($this->request->data[$this->_model][$name], array('width' => "150"));
    }
    echo $this->Html->image(' ', array('class' => 'upload-default-image', 'style' => 'display:none', 'width' => "150"));
    ?>
    </div>
    <a id="upload-pick-<?php echo $name ?>" href="javascript:;"><?php echo '['.__('Selectionner un fichier').']'; ?></a>
    <a style="display:none" id="upload-uploaded-<?php echo $name ?>" href="javascript:;"><?php echo '['.__('Uploader').']'; ?></a>
</div>
 
<br />
<pre id="console"></pre>
<style type="text/css">

.uploader-images-progressbar {
    position: relative;
    width: 150px;
}
.uploader-images-progressbar-overlay {
    position: absolute;
    top: 0;
    left: 89%;
    width: 27%;
    opacity: 0.5;
    background-color: black;
    height: 100%;
}
</style>
 
<script type="text/javascript">
// Custom example logic
 function createObjectURL(object) {
    return (window.URL) ? window.URL.createObjectURL(object) : window.webkitURL.createObjectURL(object);
}

function revokeObjectURL(url) {
    return (window.URL) ? window.URL.revokeObjectURL(url) : window.webkitURL.revokeObjectURL(url);
}



var uploader<?php echo $this->uploaderId ?> = new plupload.Uploader({
    runtimes : 'html5,flash,silverlight,html4',
     
    browse_button : 'upload-pick-<?php echo $name ?>', // you can pass in id...
    container: document.getElementById('upload-container-<?php echo $name ?>'), // ... or DOM Element itself
     
    url : "<?php echo $this->Html->url('/upload/uploadHandler/') ?>",
     
    filters : {
        max_file_size : '100mb',
        mime_types: [
            {title : "Image files", extensions : "jpg,gif,png"},
            {title : "Zip files", extensions : "zip"},
            {title : "Executables", extensions : "exe,dmg"},
            {title : "Documents", extensions : "pdf,doc,docx,ods"},
            {title : "Autre", extensions : "*"}
        ]
    },
    
    multi_selection : <?php echo empty($option['multiple'])?'false':'true'; ?>,
    
    // Flash settings
    flash_swf_url : 'http://rawgithub.com/moxiecode/moxie/master/bin/flash/Moxie.cdn.swf',
 
    // Silverlight settings
    silverlight_xap_url : 'http://rawgithub.com/moxiecode/moxie/master/bin/silverlight/Moxie.cdn.xap',
     
    chunk_size: '500kb',
    max_retries: 3,
 
    init: {
        PostInit: function() {
            document.getElementById('upload-fl-<?php echo $name ?>').innerHTML = '';
 
            document.getElementById('upload-uploaded-<?php echo $name ?>').onclick = function() {
                uploader<?php echo $this->uploaderId ?>.start();
                return false;
            };
        },
 
        FilesAdded: function(up, files) {
            plupload.each(files, function(file) {
                document.getElementById('upload-fl-<?php echo $name ?>').innerHTML += '<div id="' + file.id + '">' + file.name + ' (' + plupload.formatSize(file.size) + ') <b></b></div>';
            });
            uploader<?php echo $this->uploaderId ?>.start();
            $('#upload-container-<?php echo $name ?> img').fadeOut(1000);

            var src = createObjectURL(files[0].getNative());

            $('#upload-container-<?php echo $name ?> img.upload-default-image').fadeOut(0)
            .first().clone().removeClass('upload-default-image')
            .appendTo('#upload-container-<?php echo $name ?> .uploader-images-progressbar').attr('src', src).fadeIn(1000);
            
            //$('#upload-container-<?php echo $name ?> img.upload-default-image').fadeOut(0)
            //.first().clone().removeClass('upload-default-image')
            //.prependTo('#upload-container-<?php echo $name ?>').attr('src', src).fadeIn(1000);
        },
 
        UploadProgress: function(up, file) {
            var p = file.percent < 100 ? file.percent : 99;
            $('#upload-container-<?php echo $name ?> .uploader-images-progressbar-overlay')
            .css('left', p+'%')
            .css('width', (100-p)+'%');
            document.getElementById(file.id).getElementsByTagName('b')[0].innerHTML = '<span>' + p + "%</span>";
        },
        FileUploaded: function(up, file, info) {
            $('#upload-container-<?php echo $name ?> .uploader-images-progressbar-overlay')
            .css('left', '100%')
            .css('width', '0');
            document.getElementById(file.id).getElementsByTagName('b')[0].innerHTML = '<span>100%</span>';
            
            // Called when a file has finished uploading
            var data = eval('(' + info.response + ')');

            $('#upload-input-<?php echo $name; ?>-name').val(data.name);
            $('#upload-input-<?php echo $name; ?>-tmp_name').val(data.tmp_name);
            <?php
            if(!empty($option['onFileUploaded']))
            {
                echo $option['onFileUploaded'];
            }
            ?>
            //$('#upload-container-<?php echo $name ?> img').attr('src', '<?php echo $this->Html->link('/'); ?>'+data.tmp_name).show('fast');
        },
 
        Error: function(up, err) {
            document.getElementById('console').innerHTML += "\nError #" + err.code + ": " + err.message;
        }
    }
});
 console.log('test');
uploader<?php echo $this->uploaderId ?>.init();
 
</script>

<?php
        $this->Form->unlockField('Upload.'.$name.'.name');
        $this->Form->unlockField('Upload.'.$name.'.tmp_name');
		echo $this->Form->input('Upload.'.$name.'.name', array('secure'=>false,'type' => 'hidden', 'id' => 'upload-input-'.$name.'-name'));
		echo $this->Form->input('Upload.'.$name.'.tmp_name', array('secure'=>false,'type' => 'hidden', 'id' => 'upload-input-'.$name.'-tmp_name'));
		
		$this->uploaderId += 1;

        return ob_get_clean();

    }
}