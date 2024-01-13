<?php
// keeps running script forever
ini_set('max_execution_time', '0');

/** Set error reporting */
error_reporting(E_ALL);

class TwitchChatDownloader
{	
	// Twitch API authorization
	// https://dev.twitch.tv/docs/api/
	
	public $tw_access_token;	
	
	private $tw_api_url 			= 'https://api.twitch.tv/helix/';
	
	private $tw_auth_url 			= 'https://id.twitch.tv/oauth2/';
	
	private $tw_client_id 			= 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
	
	private $tw_client_secret 		= 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
	
	// Working Directory => current Directory
	private $working_dir 			= __DIR__.'/';
	
	// number of streams to be downloaded
	private $max_streams			= 3;
	
	// the file to store vods to be download later
	private $download_later_file 	= 'download_later.txt';
	
	// broadcasters username & user id
	// https://www.streamweasels.com/tools/convert-twitch-username-to-user-id/
	private $twitch_users = array(
									'xxxxxxxx' 		=> 'xxxxxxxx',
									'xxxxxxxx' 			=> 'xxxxxxxx', 
									'xxxxxxxx' 		=> 'xxxxxxxx', 
	);	
	
	public function __construct()
	{
		$this->tw_access_token = $this->getTwitchAccessToken();
	}		
	
	public function run()
	{
		echo 'START run() <br>';
		echo '------------------------------------------------------------<br>';
		echo 'Checking for old VOSs to be downloaded...<br>';
		
		// check if vod needs to be download cuz the user was online
		if(file_exists($this->working_dir.$this->download_later_file))
		{
			
			echo $this->download_later_file.' found. Downloading VODs...<br>';
			
			$file_contents = file_get_contents($this->working_dir.$this->download_later_file);
			
			$vods = explode("\n", $file_contents);
			
			foreach($vods as $vod)
			{
				$vod_data = explode("\n", $vod);
				echo 'Downloading VOD: '.$vod_data[0].' from '.$this->working_dir.$this->download_later_file.'<br>';
				echo $this->downloadChatFile($vod_data[0], $vod_data[1]).'<br>';
				
			}
			echo 'Deleting '.$this->download_later_file.'...<br>';
			unset($later_file);
		}
		else
		{
			echo 'No VODS need to be downloaded, continue...<br>';
		}

		foreach($this->twitch_users as $tw_username => $tw_userid)
		{
			echo 'Start loop for '.$tw_username.'...<br>';
			echo '---------------<br>';
			
			$strm_data =  $this->getStreamData($tw_userid);

			foreach($strm_data as $video_data)
			{
				$vodid = $video_data['id'];
				
				if($this->isOnline($tw_username) === true)
				{
					$message = $vodid.'-'.$tw_username."\r\n";
					file_put_contents($this->working_dir.$this->download_later_file, $message, FILE_APPEND);
					echo $tw_username . " is ONLINE! Setting vod " . $vodid . ' to be downloaded later.<br>';
				}
				else
				{
					echo "Downloading VOD: " . $vodid.'<br>';
					echo  $this->downloadChatFile($vodid, $tw_userid);
					echo "... done<br>";
				}
			}
			echo '---------------<br>';
			echo 'Loop finished...<br>';
		}	
		
		echo 'All loops finished...<br>';
		echo '------------------------------------------------------------<br>';
		echo 'END run()';
	}
	
	private function isOnline($twitch_username)
	{
		$stream_data = $this->runCurlCommand('search/channels?query='.$twitch_username.'&first=1');
		$status = $stream_data['data'][0]['is_live'];
		return $status;
	}	
	
	private function getStreamData($twitch_user_id)
	{
		echo "Getting stream data for user ".$twitch_user_id."...<br>";
		$stream_data = $this->runCurlCommand('videos?user_id='.$twitch_user_id.'&first='.$this->max_streams.'&type=archive');
		return $stream_data['data'];
	}	
	
	private function downloadChatFile($vodid, $twitch_user_id)
	{
		try
		{
			echo $this->working_dir.'chats/'.$twitch_user_id.'/'.$vodid.'.json<br>';
			return exec($this->working_dir.'TwitchDownloaderCLI.exe chatdownload --id '.$vodid.' -o chats/'.$twitch_user_id.'/'.$vodid.'.json --chat-connections 4');
		}
		catch (Exception $e)
		{
			return $e->getMessage();
		}	
	}	
	
	private function runCurlCommand($url)
	{
		echo 'Running curl stream: '.$url.'<br>';
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_AUTOREFERER, 		TRUE);
		curl_setopt($ch, CURLOPT_HEADER, 			0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 	1);
		curl_setopt($ch, CURLOPT_URL, 				$this->tw_api_url.$url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 	TRUE);     
		curl_setopt($ch, CURLOPT_HTTPHEADER, 		array
													(
														'Authorization: Bearer '.$this->tw_access_token,
														'Client-ID: '.$this->tw_client_id
													));
		$curl_data = curl_exec($ch);
		curl_close($ch);
		echo 'Done getting stream data...<br>';

		return json_decode($curl_data, true);
	}	
	
	private function getTwitchAccessToken()
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 	1);
		curl_setopt($ch, CURLINFO_HEADER_OUT, 		1);
		curl_setopt($ch, CURLOPT_URL, 				$this->tw_auth_url.'token?client_id='.$this->tw_client_id.'&client_secret='.$this->tw_client_secret.'&grant_type=client_credentials');
		curl_setopt($ch, CURLOPT_POST, 				1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, 		array
													(
														'Content-Type: application/json'
													));
		$response = curl_exec($ch);
		curl_close($ch);
		$data = json_decode($response, true);
		return $data['access_token'];
	}
}

$tcd = new TwitchChatDownloader();
$tcd->run();
?>
