<?php 
/**
 * Process and audio file and split it in chuncks and send it to google speech
 * websrvice.
 * @param stdClass $audio_file
 * @return string
 * 
 * @TODO variables are created for convience and need clean up
 */
function padre_scripts_google_transcribe($audio_file) {
  $filename = drupal_realpath($audio_file->uri); 
  $url = "http://www.google.com/speech-api/v1/recognize?xjerr=1&client=chromium&lang=en-US";
  // Convert ot flac 
  $converted_file_name = '/tmp/audio_flac.flac';
  $conversion_command = "sox $filename $converted_file_name";
  exec($conversion_command);
  // get the lendth of the flac file
  $length_command = "soxi -D $converted_file_name" ;
  dpm($length_command);
  exec($length_command, $out, $ret);
  $length = $out;
  $length = $length[0];
  $i = 0;
  // index the array of chunked results for reassembly  
  $x = 0;
  // Trimmed file legnth
  $trim_lenght = 8;
  
  $result = array();
  dpm($length);
  
  $temp_trimmed_file = '/tmp/trimmed.flac';
  // Temp file to convert the sample rate to 16000
  $temp_trimmed_file_2 = '/tmp/trimmed_rate.flac';
  while( $i < $length) {
    $command = "sox $converted_file_name $temp_trimmed_file trim 0:$i 0:$trim_lenght";
    exec($command);
    // Convert to 16000 sample rate
    $command2 =  "sox $temp_trimmed_file -r 16000 $temp_trimmed_file_2";
    exec($command2);
    
    $audio = file_get_contents($temp_trimmed_file_2);
    $speech_info_request = curl_init();
    curl_setopt($speech_info_request, CURLOPT_URL, $url);
    curl_setopt($speech_info_request, CURLOPT_HTTPHEADER, array('Content-Type: audio/x-flac; rate=16000' ));
    curl_setopt($speech_info_request, CURLOPT_POST, TRUE);
    curl_setopt($speech_info_request, CURLOPT_POSTFIELDS, $audio);
    curl_setopt($speech_info_request, CURLOPT_RETURNTRANSFER, 1);
    $speech_info_response = curl_exec($speech_info_request);
  
    $responseCode = curl_getinfo($speech_info_request,CURLINFO_HTTP_CODE);
  
    if($responseCode==200) {
      $jsonObj = json_decode($speech_info_response, TRUE);
      $result[$x] = $jsonObj['hypotheses'][0]['utterance'];
    }
    elseif($responseCode != 200) {
      // Try 3 times
      $count = 0;
      while($count < 3) {
        sleep(0.5);
        $speech_info_request_2 = curl_init();
        curl_setopt($speech_info_request_2, CURLOPT_URL, $url);
        curl_setopt($speech_info_request_2, CURLOPT_HTTPHEADER, array('Content-Type: audio/x-flac; rate=16000' ));
        curl_setopt($speech_info_request_2, CURLOPT_POST, TRUE);
        curl_setopt($speech_info_request_2, CURLOPT_POSTFIELDS, $audio);
        curl_setopt($speech_info_request_2, CURLOPT_RETURNTRANSFER, 1);
        $speech_info_response_2 = curl_exec($speech_info_request);
        
        $response_code = curl_getinfo($speech_info_request_2,CURLINFO_HTTP_CODE);
        if($response_code == 200) {
          $jsonObj = json_decode($speech_info_response, TRUE);
          $result[$x] = $jsonObj['hypotheses'][0]['utterance'];
          break;
        }
        
        else {
          watchdog('padre_scripts',"unable to get transcription for part $i of audio file after $count attempts code: $response_code" );
        }
        
        curl_close($speech_info_request_2);
        $count++;
      }
    }
  
    curl_close($speech_info_request);
     
    $x++;
    $i = $i + $trim_lenght;
  }
  $script = '';
  foreach ($result as $key => $value) {
    $script .= ' '.  $value;
  }
  return $script;
}

