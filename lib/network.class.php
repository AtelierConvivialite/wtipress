<?php

class Network {
    
  public $api_key;
  
  function __construct($api_key = null) {
    $this->api_key = $api_key;
  }
  
  // call Project API
  function get_project_info() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, "https://webtranslateit.com/api/projects/" . $this->api_key . ".json");
    $response = curl_exec($ch);
    $p = json_decode($response, true);
    return $p;
  }
  
  function push_post($post_id) {
    $post = get_post($post_id);
    // create pseudo language file
    $file_path = wp_upload_dir();
    $file_path = $file_path['path']. "/". $post->post_date.'-'.$post->post_name.'.wordpress';
    $array = array('post_title' => $post->post_title, 'post_excerpt' => $post->post_excerpt, 'post_content' => $post->post_content, 'post_name' => $post->post_name, 'post_content_filtered' => $post->post_content_filtered);
    $dumper = new sfYamlDumper();
    $handle = fopen($file_path, 'w') or die("can't open file");
    fwrite($handle, $dumper->dump($array));
    fclose($handle);
    
    $translation = Translation::get_translations_for_post($post);
    if ($translation[0]->wti_file_id != NULL) {
      // get source locale
      $source_locale = Language::get_all(true);
      // already have translations in DB? Update.
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://webtranslateit.com/api/projects/" . $this->api_key . "/files/" . $translation->wti_file_id . "/locales/" . $source_locale[0]->code);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
      curl_setopt($ch, CURLOPT_POSTFIELDS, array("file" => "@".$file_path, 'name' => $post->post_date.'-'.$post->post_name.'.wordpress')); 
      $response = curl_exec($ch);
      
      // update entries for each language in wtipress table
      foreach(Language::get_all() as $target_locale) {
        $translation = Translation::get_translation($post, $target_locale);
        $translation->last_pushed_at = date("Y-m-d H:i:s", time());
        $translation->updated_at = date("Y-m-d H:i:s", time());
        $translation->save();
      }
    }
    else {
      // create
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://webtranslateit.com/api/projects/" . $this->api_key . "/files");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, array("file" => "@".$file_path, 'name' => $post->post_date.'-'.$post->post_name.'.wordpress')); 
      $response = curl_exec($ch);
      // create entries for each language in wtipress table
      foreach(Language::get_all() as $target_locale) {
        $translation = new Translation($post, $target_locale);
        $translation->wti_file_id = $response;
        $translation->save();
      }
    }
    // unlink($file_path);
  }
  
  function pull_post($post_id) {
    $post = get_post($post_id);
    // pull post from wti for each language
    
    // update entries for each language in wtipress table
  }
  
}
?>
