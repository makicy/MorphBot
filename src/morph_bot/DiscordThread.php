<?php

namespace morph_bot;

use Discord\Discord;
use Discord\WebSockets\Event;
use Discord\Parts\Channel\Message;
use Discord\WebSockets\Intents;
use Generator;

define('APP_ID', '');
define('BOT_TOKEN', '');

define('GUILD_ID', '');
define('MAIN_CHAT_CHANNEL_ID', '');

class DiscordThread extends \Thread
{
	private $path;
	private $queue = [];
	private $shutdown = false;

    public function __construct(string $path, array $words) {
	    $this->path = $path;
	    $this->queue["words"] = $words;
	    $this->queue["morph"] = $this->analyze();
    }

	public function shutdown() {
		$this->shutdown = true;
		$this->join();
	}

	public function run() {
		include $this->path.'/vendor/autoload.php';

		$discord = new Discord([
			'token' => BOT_TOKEN,
			'logging' => false,
			'intents' => [Intents::GUILD_MESSAGES]
		]);

		$discord->on('ready', function(Discord $discord) {
			$discord->getLoop()->addPeriodicTimer(20*60*5, function() {
				$this->analyze();
			});

			$discord->getLoop()->addPeriodicTimer(1, function($timer) use ($discord) {
				if($this->shutdown) {
					$discord->getLoop()->cancelTimer($timer);
					$discord->close();
					$discord->getLoop()->stop();
				}
			});
		});

		$discord->on(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) {
			if($message->author === null)
				return;

			if($message->author->id === $discord->user->id)
				return;

			if($message->channel_id === MAIN_CHAT_CHANNEL_ID) {
				$message->channel->sendMessage($this->parse());
				$this->queue["words"][] = $message->content;
			}
		});

		$discord->run();
	}

    private function analyze(): array {
	    $sentence = "";
	    foreach($this->queue["words"] as $word)
		    $sentence .= "。".$word;

	    $data = array('app_id' => APP_ID, 'sentence' => $sentence);
	    $curl = curl_init("https://labs.goo.ne.jp/api/morph");

	    curl_setopt($curl, CURLOPT_POST, true);
	    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
	    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	    curl_setopt($curl, CURLOPT_COOKIEFILE, 'tmp');
	    curl_setopt($curl, CURLOPT_COOKIEJAR, 'cookie');
	    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));

	    return json_decode(curl_exec($curl),true)["word_list"];
    }

    private function parse(): string {
	    $subject = [];
	    $predicate = [];
    	$data = $this->queue["morph"];

    	foreach($data as $word_list) {
    		for($i=0; $i<count($word_list); $i++) {
    			$word = $word_list[$i][0];
			    $next = $word_list[$i+1][0] ?? null;
			    $next_class = $word_list[$i+1][1] ?? null;

    			switch($word_list[$i][1]) {
				    case "名詞":
				    	$next_class === "名詞接尾辞" ? $subject[] = $word.$next : $subject[] = $word;
				    	break;
				    case "動詞語幹":
					    $next_class === "動詞接尾辞" ? $predicate[] = $word.$next : $predicate[] = $word;
					    break;
				    case "形容詞語幹":
					    $next_class === "形容詞接尾辞" ? $predicate[] = $word.$next : $predicate[] = $word;
					    break;
			    }
		    }
 	    }

    	return array_unique($subject).array_unique($predicate);
    }

	public function fetchMessages(): Generator {
		foreach($this->queue["words"] as $word)
			yield $word;
	}
}