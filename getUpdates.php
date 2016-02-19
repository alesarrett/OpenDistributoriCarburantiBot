#!/usr/bin/php

<?php
  /**
   * Telegram Bot Example whitout WebHook.
   * It uses getUpdates Telegram's API.
   * Author: Cesare Gerbino - designed starting from Gabriele Grillo (Eleirbag89) code in https://github.com/Eleirbag89/TelegramBotPHP
  */

  include("main.php");

  //aggiorna con getUpdates
  function getUpdates($telegram){

	date_default_timezone_set('Europe/Rome');
	$today = date("Y-m-d H:i:s");
	
	$update_manager= new mainloop();

	// Get all the new updates and set the new correct update_id
	$req = $telegram->getUpdates();
	
	for ($i = 0; $i < $telegram-> UpdateCount(); $i++) {
		// You NEED to call serveUpdate before accessing the values of message in Telegram Class
		$telegram->serveUpdate($i);
		$text = $telegram->Text();
		//echo "Input utente in Telegram: " .$text;
		$chat_id = $telegram->ChatID();
		//echo "ChatID: " .$chat_id;
		$user_id= $telegram->User_id();
		//echo "User ID: " .$user_id;
		$location= $telegram->Location();
		//echo "Location: " .print_r($location);
		$reply_to_msg= $telegram->ReplyToMessage();
		//echo "Reply to message: " .$reply_to_msg;
		$update_manager->shell($telegram,$text,$chat_id,$user_id,$location,$reply_to_msg);
	}

  }
?>

