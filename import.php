<?php

include_once '../../../wp-load.php';

class CsvImport {

    public $curlUrl = "https://site-where-csv-file-is/products.csv";
    private $ftphost = "ftp host";
    private $ftpuser = "ftp user";
    private $ftppass = "ftp pass";
    private $serverdirpath = "/dir/path";
    private $uploaddirpath = "/photos/";
    private $csvfile = "products.csv";
    private $imagetype = "jpg";

    public function __construct() {

        $this->connectCurl($this->curlUrl);
        
    }

    public function connectCurl($curlUrl) {

        ob_start();
        /* curl initiate */
        $ch = curl_init($curlUrl);
        curl_setopt($ch, CURLOPT_POST, 1);

        /* Assign post fields and params */
        curl_setopt($ch, CURLOPT_POSTFIELDS, "user_code=your code&password=password");
        $data = curl_exec($ch);
        curl_close($ch);

        /* buffers the content */
        $contents = ob_get_clean();
        $this->putCsvcontent($contents);
        $this->importpost();

    }

    private function putCsvcontent($contents) {
        /* putting contents to local csv file */
        file_put_contents($this->csvfile, $contents);
    }

    private function importpost() {

        $handle = fopen($this->csvfile, 'r');
        $row = fgetcsv($handle);

        /* loop the csv content */ echo "<pre>"; print_r($row); echo "</pre>"; 
        while (($row = fgetcsv($handle)) !== false) {
            /* post arquments */
            $my_post = array(
                'post_title' => $row[3],
                'post_content' => $row[2],
                'post_status' => 'publish',
                'post_type' => 'product'
            );
            $title = $row[3];
            $postcontent = $row[2];
            $post_id = $this->check_post_existbytitle($title);
            var_dump($post_id);
            /* inserting post */
            if ($post_id==false) {
                $post_id = wp_insert_post($my_post);
            } else { echo "update";echo "</br>";
                $my_post = array(
                'ID' => $post_id,
                'post_content' => $postcontent,
                'post_status' => 'publish',
                'post_type' => 'product'
                );
                wp_update_post($my_post);
            }
            /* here you can define your meta key and values */
            $metaarr = array('product_name' => $row[13],
			     'product_description' => $row[554],
                'product_id' => $row[3],
                'product_cat' => $row[184],
                'style' => $row[279],
                'some_tax' => $row[76],
                'a_c' => $row[0],
                'ml_num' => $row[130],
                'park_spcs' => $row[167],
		 'maiin_image'  => $row[124],
		'taxes' =>  $row[282],
	        'cross_salet' => $row[50],
		'product_price' => $row[292],
	       'input_date' => $row[93],
                'front_sl_slider'=>'1');

            $this->update_metadetails($metaarr, $post_id);
            $this->setfilevars($row[3], $post_id);
        }
        fclose($handle);
    }

    public function check_post_existbytitle($title) {
        global $wpdb;
        $result = $wpdb->get_row("SELECT ID, post_title FROM wp_posts WHERE post_title = '".$title."' and post_status='publish'", 'ARRAY_A');
        
        if ($wpdb->num_rows > 0) {
            
            return $result['ID'];
        } else {
            return false;
        }
    }

    private function update_metadetails($metaarr, $post_id) {
        foreach ($metaarr as $rowkey => $rowval) {
            /* updating post meta */
            update_post_meta($post_id, $rowkey, $rowval);
        }
    }

    private function setfilevars($ml_num, $post_id) {

        $dir = substr($ml_num, -3);
				
        $fileno = $ml_num;
        $wp_upload_dir = wp_upload_dir();
        $ftpup = $wp_upload_dir["basedir"] . $this->uploaddirpath;
        $local_file = $wp_upload_dir["basedir"] . $this->uploaddirpath . "{$fileno}." . $this->imagetype;
        $server_file = $this->serverdirpath . "/{$dir}/{$fileno}." . $this->imagetype;
		  $path = $this->serverdirpath . "/".$dir."/";
		  //$path = $this->serverdirpath . "/ven/";        
        $this->transfer_images_fromremote($local_file, $server_file,$dir,$path,$fileno);
        $this->setpost_featured_image($post_id, $local_file);

		  
}

    /* transfer images from remote server */

    private function transfer_images_fromremote($local_file, $server_file,$dir,$path,$fileno) {
        // set up basic connection
        $conn_id = ftp_connect($this->ftphost) or die("Couldn't connect to $ftp_server");
		  $wp_upload_dir = wp_upload_dir();
        // login with username and password
        $login_result = ftp_login($conn_id, $this->ftpuser, $this->ftppass);
//        $files = ftp_nlist($conn_id, $path); 
  //      var_dump($files);
        for($i=1;$i<=10;$i++){
        	      if($i==1){
               $srcFile = $this->serverdirpath .$i.'/' .$dir."/".$fileno.".jpg"; 
               $localFile = $wp_upload_dir["basedir"].$this->uploaddirpath."/".$fileno.".jpg";
               }else{
               $srcFile = $this->serverdirpath .$i.'/' .$dir."/".$fileno."_".$i.".jpg"; 
               $localFile = $wp_upload_dir["basedir"].$this->uploaddirpath."/".$fileno."_".$i.".jpg";	
               }
               exec("touch {$localFile}");                 
               if (ftp_get($conn_id, $localFile, $srcFile, FTP_BINARY)) 
               { 
                   echo "file is written successfully";
               }else{
               	 echo "There was a problem\n";		
               }
        }
/*        if (ftp_get($conn_id, $local_file, $server_file, FTP_BINARY)) {
            echo "Successfully written to $local_file\n";
            // $upload = f_put($conn_id, $ftpup . "/" . $file, $localFile, FTP_BINARY); 
        } else {
            echo "There was a problem\n";
        }*/
    }

// try to download $server_file and save to $local_file/* download images from remote server and set featured image to post */

    private function setpost_featured_image($post_id, $file) {

        $wp_filetype = wp_check_filetype(basename($file), null);
        $wp_upload_dir = wp_upload_dir();

        $attachment = array(
            'guid' => $wp_upload_dir['url'] . '/' . basename($file),
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($file)),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_parent' => $post_id
        );

        $attach_id = wp_insert_attachment($attachment, $file, $post_id);
        update_post_meta($post_id, '_thumbnail_id', $attach_id, true);
        // you must first include the image.php file
        // for the function wp_generate_attachment_metadata() to work
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        wp_update_attachment_metadata($attach_id, $attach_data);
        echo "image updated";
    }

}


$csvimp = new CsvImport();




?>
