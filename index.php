<?php
require_once('config.php');
function send($url, $values, $post = false){
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	if ($post){
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $values);
	}
	else{
		curl_setopt($ch, CURLOPT_URL, $url.'?'.http_build_query($values, "", "&"));
	}
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);

	$server_output = curl_exec ($ch);
	curl_close ($ch);

	return json_decode($server_output);
}
/**
* Class that gets user subscription status.
*/
class sub
{
	public $subbed = false;
	public $userinfo;

	function __construct($client_id, $client_secret, $url, $code, $channel)
	{
		/**
		 * authorize user
		 */
		$auth = send("https://api.twitch.tv/kraken/oauth2/token", array(
			'client_id' => $client_id,
			'client_secret' => $client_secret,
			'grant_type' => 'authorization_code',
			'redirect_uri' => $url,
			'code' => $_REQUEST['code']), true);

		/**
		 * get user information such as username
		 */
		$this->userinfo = send("https://api.twitch.tv/kraken/user", array('oauth_token' => $auth->access_token));

		/**
		 * store their subscription status
		 */
		$subinfo = send("https://api.twitch.tv/kraken/users/".$this->userinfo->name."/subscriptions/$channel",
			array('oauth_token'=> $auth->access_token));

		if ($subinfo->status != 404) {
			$this->subbed = true;
		}

	}
}
if (empty($_REQUEST['code'])) {
	//We don't have an access code, redirect them to twitch to authorize the app.
	header("Location: https://api.twitch.tv/kraken/oauth2/authorize?".
		"response_type=code&".
		"client_id=$client_id&".
		"redirect_uri=$url&".
		"scope=user_read user_subscriptions");
}
else{
?>
<html>
<head>
	<title><?php echo $title ?></title>
</head>
<body>
<?php
	$user = new sub($client_id, $client_secret, $url, $_REQUEST['code'], $channel);

	if (isset($_POST['username']) && $user->subbed) {
		if (!file_exists('whitelist.txt'))
			file_put_contents('whitelist.txt', '');
		$list = file('whitelist.txt');
		//if they already have a username on the list, remove it
		if (is_array($list)) {
			for ($i=0; $i < count($list); $i++) {
				if (substr($list[$i], 0, strlen($user->userinfo->name)) === $user->userinfo->name) {
					unset($list[$i]);
				}
			}
			//reindex array
			$list = array_values($list);

			var_dump($list);
			//output whitelist file
			$fp = fopen('whitelist.txt', 'w+');
			foreach ($list as $line) {
				fwrite($fp, trim($line).PHP_EOL);
			}
			echo "$_POST[username] has been added to the whitelist.";
		}
		fwrite($fp, $user->userinfo->name." $_POST[username]");
	}
	else if ($user->subbed) {
		echo "<form method='post'> ".
			"Minecraft Username: <input name='username' type='text'>".
			"</form>";
	}
	else{ //they aren't subscribed to the channel
		echo "You aren't subscribed, please subscribe and try again.";
	}
}
?>
</body>
</html>