<?php
  /**
   * Telegram Bot example for Italian Fuel Stations
   * Data are based on a daily copy of open data from Ministero dello Sviluppo Economico 
   * (rif. http://www.sviluppoeconomico.gov.it/index.php/it/open-data/elenco-dataset/2032336-carburanti-prezzi-praticati-e-anagrafica-degli-impianti)
   * with license iodl2.0 (rif. http://www.dati.gov.it/iodl/2.0/)
   * Author: Cesare Gerbino - designed starting from Francesco Piero Paolicelli (@piersoft) code in https://github.com/piersoft/MuseiMibactBot 
  */

  //include("settings_t.php");
  include("Telegram.php");
  include("SearchFuelStation.php");
  



  class mainloop{
    
    function start($telegram,$update)
      {
	date_default_timezone_set('Europe/Rome');
	$today = date("Y-m-d H:i:s");
	//$data=new getdata();
	// Instances the class

	/* If you need to manually take some parameters
	*  $result = $telegram->getData();
	*  $text = $result["message"] ["text"];
	*  $chat_id = $result["message"] ["chat"]["id"];
	*/

	$text = $update["message"] ["text"];
	$chat_id = $update["message"] ["chat"]["id"];
	$user_id=$update["message"]["from"]["id"];
	$location=$update["message"]["location"];
	$reply_to_msg=$update["message"]["reply_to_message"];

	$this->shell($telegram,$text,$chat_id,$user_id,$location,$reply_to_msg);
	$db = NULL;
      }

    //gestisce l'interfaccia utente
    function shell($telegram,$text,$chat_id,$user_id,$location,$reply_to_msg)
      {

        $MAX_LENGTH = 4096;
        $base_path = BASE_PATH;

        //## Aggiorno il contatore degli accessi ...
        $access_counter = 0;
        $db_data_sessions = new SQLite3($base_path.'/OpenDistributoriCarburantiBot/DataSessionsDB');
        $q="SELECT * FROM access_numbers";
        try {
             $stmt = $db_data_sessions->prepare($q);
             $results = $stmt->execute();
             while ($row = $results->fetchArray(SQLITE3_ASSOC)){
                    $access_counter = $row['Counter'];
             } 

             $access_counter = $access_counter + 1;

             $update = "UPDATE access_numbers SET Counter=?";
             $stmt = $db_data_sessions->prepare($update);

             //## Bind parameters to statement variables
             $stmt->bindValue(1,$access_counter);
 
             //## Execute statement
             $stmt->execute();
        }               
        catch(PDOException $e) {
                print "Something went wrong or Connection to database failed! ".$e->getMessage();
        }
        $db_data_sessions = null;

	date_default_timezone_set('Europe/Rome');
	$today = date("Y-m-d H:i:s");

        $my_lat = 0;
        $my_lon = 0;

        if(!empty($location)) {
          $my_lat = $location['latitude'];
          $my_lon = $location['longitude'];
        }

	if (($my_lat != 0) AND ($my_lon != 0)) {
                 $this->Comune_Vs_LatLon($chat_id,'',$my_lat,$my_lon,0);

                 //## Preparo la keyboard con le opzioni di scelta per il raggio di ricerca intorno al punto di interesse ...
                 $search_distances = array(["500","1000"],["2000","3000"]);
                 $keyb = $telegram->buildKeyBoard($search_distances, $onetime=true);
	         $content = array('chat_id' => $chat_id, 'reply_markup' => $keyb, 'text' => "Seleziona il raggio di ricerca intorno al punto di interesse ".$my_lat." - ".$my_lon);
	         $telegram->sendMessage($content);
		}

	if (strtoupper($text) == "/START") {
		$reply = "Benvenuta/o! Quest'applicazione Le permettera' di ricercare il distributore di carburante piu' economico nell intorno di un punto di Suo interesse o all'interno dell'area in un Comune italiano.\n 
E' necessario che mi indichi la Sua posizione corrente (controlli che sia attiva la geolocalizzazione sul suo dispositivo ...); in caso contrario non saro' in grado di individuare la Sua posizione e non potro' calcolare il percorso per farLe raggiungere il distributore di Suo interesse.\n
Se non puo', o non vuole, fornire la Sua attuale posizione provero' lo stesso a darLe una indicazione: mi fornisca il nome di un Comune italiano e Le indichero' il/i distributori piu' economici all'interno del suo territorio e li potra' comunque visualizzare su mappa.\n
Per fornire la Sua posizione e' sufficiente cliccare sulla graffetta  e poi selezionare l'opzione 'Posizione'.\n
Per indicare il Comune di interesse e' sufficiente scriverne il nome.\n
Verra' interrogata una copia del DataBase openData del Ministero dello Sviluppo Economico (rif. http://www.sviluppoeconomico.gov.it/index.php/it/open-data/elenco-dataset/2032336-carburanti-prezzi-praticati-e-anagrafica-degli-impianti),utilizzabile con licenza iodl2.0 (rif. http://www.dati.gov.it/iodl/2.0/).\n
I dati sono aggiornati giornalmente dalle ore 9.00.\n
E' possibile visualizzare questo messaggio in qualsiasi momento scrivendo /start.\n
Le mappe utilizzate sono quelle derivate dai dati di OpenStreetMap e OSMBuildings con licenza ?????\n
Il calcolo dei percorsi viene realizzato avvalendosi del servizio ???? di MapQuest con licenza ??????\n
L'abbreviazione delle url viene realizzata avvalendosi del servizio ???? di Google con licenza ??????\n
Questo bot e' stato realizzato a titolo sperimentale  da Cesare Gerbino (cesare.gerbino@gmail.com)\n
Per maggiori dettagli http://cesaregerbino.wordpress.com/xxxxxxxxxxxx";

		$content = array('chat_id' => $chat_id, 'text' => $reply,'disable_web_page_preview'=>true);
		$telegram->sendMessage($content);
		$log=$today. ";new chat started;" .$chat_id. "\n";
		}
        elseif ((strtoupper($text) == "500") OR (strtoupper($text) == "1000") OR (strtoupper($text) == "2000") OR (strtoupper($text) == "3000"))  {
                 //## Accedo al db di sessione per recuperare il dato del comune di interesse per l'id di chat ...
                 $db_data_sessions = new SQLite3($base_path.'/OpenDistributoriCarburantiBot/DataSessionsDB');
                 $q="SELECT ds.Chat_id, ds.Comune, ds.My_Lat, ds.My_Lon, ds.Search_Distance
                     FROM data_sessions as ds
                     WHERE ds.Chat_id = :Chat_id";
                 try {
                   $stmt = $db_data_sessions->prepare($q);
                   $stmt->bindvalue(':Chat_id', $chat_id, SQLITE3_TEXT);
                   $results = $stmt->execute();
                   while ($row = $results->fetchArray(SQLITE3_ASSOC)){
                     $comune = $row['Comune'];
                     $my_lat = $row['My_Lat'];
                     $my_lon = $row['My_Lon'];
                     $search_distance = $row['Search_Distance'];
                   } 
                 }
                 catch(PDOException $e) {
                     print "Something went wrong or Connection to database failed! ".$e->getMessage();
                   }
                
                 $db_data_sessions = null;
                 
                 $this->Comune_Vs_LatLon($chat_id,strtoupper($comune),$my_lat,$my_lon,$text);

                 //## Preparo la keyboard con le opzioni di scelta per il tipo di carburante ...
                 //$option = array(["Benzina->".$text,"Gasolio->".$text],["GPL->".$text,"Metano->".$text],["Qualunque->".$text,""]);
                 $option = array(["BENZINA","GASOLIO"],["GPL","METANO"],["QUALUNQUE"]);
                 $keyb = $telegram->buildKeyBoard($option, $onetime=true);
	         $content = array('chat_id' => $chat_id, 'reply_markup' => $keyb, 'text' => "Seleziona il tipo di carburante nell'intorno del punto ".$my_lat." - ".$my_lon);
	         $telegram->sendMessage($content);
 		}
        //elseif($text !=null)
        //elseif (strpos($text,'->')){
        elseif ((strtoupper($text) == "BENZINA") OR (strtoupper($text) == "GASOLIO") OR (strtoupper($text) == "GPL") OR (strtoupper($text) == "METANO") OR (strtoupper($text) == "QUALUNQUE"))  {
                //## Accedo al db di sessione per recuperare il dato del comune di interesse per l'id di chat ...
                $db_data_sessions = new SQLite3($base_path.'/OpenDistributoriCarburantiBot/DataSessionsDB');
                $q="SELECT ds.Chat_id, ds.Comune, ds.My_Lat, ds.My_Lon, ds.Search_Distance
                    FROM data_sessions as ds
                    WHERE ds.Chat_id = :Chat_id";
                try {
                  $stmt = $db_data_sessions->prepare($q);
                  $stmt->bindvalue(':Chat_id', $chat_id, SQLITE3_TEXT);
                  $results = $stmt->execute();
                  while ($row = $results->fetchArray(SQLITE3_ASSOC)){
                    $comune = $row['Comune'];
                    $my_lat = $row['My_Lat'];
                    $my_lon = $row['My_Lon'];
                    $search_distance = $row['Search_Distance'];
                  }
                }
                catch(PDOException $e) {
                    print "Something went wrong or Connection to database failed! ".$e->getMessage();
                  }
                
                $db_data_sessions = null;

		if (strtoupper($text) == 'QUALUNQUE') {
		  if ($comune != '') {
		    $location = "Sto cercando tutti i distributori del Comune di ".$comune.". Attendere qualche secondo per la risposta ......";
		    $content = array('chat_id' => $chat_id, 'text' => $location,'disable_web_page_preview'=>true);
		    $telegram->sendMessage($content);
		    sleep (1);
                    $data = SearchFuelStation(strtoupper($comune),'','','','','','','');
                    if (strlen($data) == 0) {
                      $warning = "La ricerca NON ha prodotto risultati !!! Cambiare comune, tipo di carburante, posizione o raggio di ricerca";
		      $content = array('chat_id' => $chat_id, 'text' => $warning,'disable_web_page_preview'=>true);
		      $telegram->sendMessage($content);                      
                    }                    
                    elseif (strlen($data) < $MAX_LENGTH) {
		        $content = array('chat_id' => $chat_id, 'text' => $data,'disable_web_page_preview'=>true);
		        $telegram->sendMessage($content);                      
                      }
                      else {
                        $warning = "La ricerca ha prodotto troppi risultati !!! Cambiare comune, tipo di carburante, posizione o raggio di ricerca";
		        $content = array('chat_id' => $chat_id, 'text' => $warning,'disable_web_page_preview'=>true);
		        $telegram->sendMessage($content);                      
                      }
                   }
                  else {
		    $location = "Sto cercando tutti i distributori nell'intorno di ".$search_distance." m. dal punto ".$my_lat." - ".$my_lon.". Attendere qualche secondo per la risposta ......";
		    $content = array('chat_id' => $chat_id, 'text' => $location,'disable_web_page_preview'=>true);
		    $telegram->sendMessage($content);
		    sleep (1);
                    $data = SearchFuelStation('','',$my_lat,$my_lon,$search_distance,'','');
                    if (strlen($data) == 0) {
                      $warning = "La ricerca NON ha prodotto risultati !!! Cambiare comune, tipo di carburante, posizione o raggio di ricerca";
		      $content = array('chat_id' => $chat_id, 'text' => $warning,'disable_web_page_preview'=>true);
		      $telegram->sendMessage($content);                      
                    }                    
                    elseif (strlen($data) < $MAX_LENGTH) {
		        $content = array('chat_id' => $chat_id, 'text' => $data,'disable_web_page_preview'=>true);
		        $telegram->sendMessage($content);                      
                      }
                      else {
                        $warning = "La ricerca ha prodotto troppi risultati !!! Cambiare comune, tipo di carburante, posizione o raggio di ricerca";
		        $content = array('chat_id' => $chat_id, 'text' => $warning,'disable_web_page_preview'=>true);
		        $telegram->sendMessage($content);                      
                      }
                    }
                  }
                else {
		  if ($comune != '') {
		    $location = "Sto cercando i distributori di ".strtoupper($text)." del Comune di ".strtoupper($comune).". Attendere qualche secondo per la risposta ......";
		    $content = array('chat_id' => $chat_id, 'text' => $location,'disable_web_page_preview'=>true);
		    $telegram->sendMessage($content);
		    sleep (1);
		    
		    $text_new = "";
		    if ($text != "GPL") {
		        $text_new = ucfirst(strtolower($text));
		    }
		    else {
		        $text_new = $text;
		    }
		    
                    $data = SearchFuelStation(strtoupper($comune),$text_new,'','','','','','');
                    if (strlen($data) == 0) {
                      $warning = "La ricerca NON ha prodotto risultati !!! Cambiare comune, tipo di carburante, posizione o raggio di ricerca";
		      $content = array('chat_id' => $chat_id, 'text' => $warning,'disable_web_page_preview'=>true);
		      $telegram->sendMessage($content);                      
                    }                    
                    elseif (strlen($data) < $MAX_LENGTH) {
		        $content = array('chat_id' => $chat_id, 'text' => $data,'disable_web_page_preview'=>true);
		        $telegram->sendMessage($content);                      
                      }
                      else {
                        $warning = "La ricerca ha prodotto troppi risultati !!! Cambiare comune, tipo di carburante, posizione o raggio di ricerca";
		        $content = array('chat_id' => $chat_id, 'text' => $warning,'disable_web_page_preview'=>true);
		        $telegram->sendMessage($content);                      
                      }
                    }
                  else {
		    $location = "Sto cercando i distributori di ".strtoupper($text)." nell'intorno di ".$search_distance." m. dal punto ".$my_lat." - ".$my_lon.". Attendere qualche secondo per la risposta ......";
		    $content = array('chat_id' => $chat_id, 'text' => $location,'disable_web_page_preview'=>true);
		    $telegram->sendMessage($content);
		    sleep (1);
		    
		    $text_new = "";
		    if ($text != "GPL") {
		        $text_new = ucfirst(strtolower($text));
		    }
		    else {
		        $text_new = $text;
		    }
		    
		    
                    $data = SearchFuelStation('',$text_new,$my_lat,$my_lon,$search_distance,'','');
                    if (strlen($data) == 0) {
                      $warning = "La ricerca NON ha prodotto risultati !!! Cambiare comune, tipo di carburante, posizione o raggio di ricerca";
		      $content = array('chat_id' => $chat_id, 'text' => $warning,'disable_web_page_preview'=>true);
		      $telegram->sendMessage($content);                      
                    }                    
                    elseif (strlen($data) < $MAX_LENGTH) {
		        $content = array('chat_id' => $chat_id, 'text' => $data,'disable_web_page_preview'=>true);
		        $telegram->sendMessage($content);                      
                      }
                      else {
                        $warning = "La ricerca ha prodotto troppi risultati !!! Cambiare comune, tipo di carburante, posizione o raggio di ricerca";
		        $content = array('chat_id' => $chat_id, 'text' => $warning,'disable_web_page_preview'=>true);
		        $telegram->sendMessage($content);                      
                      }
                    }
                  }
   	      }
        elseif (strtoupper($text) != "") {
        //else {
               $this->Comune_Vs_LatLon($chat_id,strtoupper($text),0,0,0);

               //## Preparo la keyboard con le opzioni di scelta per il tipo di carburante ...
               //$option = array(["Benzina->".$text,"Gasolio->".$text],["GPL->".$text,"Metano->".$text],["Qualunque->".$text,""]);
               $option = array(["BENZINA","GASOLIO"],["GPL","METANO"],["QUALUNQUE"]);
               $keyb = $telegram->buildKeyBoard($option, $onetime=true);
	       $content = array('chat_id' => $chat_id, 'reply_markup' => $keyb, 'text' => "Seleziona il tipo di carburante per i distributori di ".strtoupper($text));
	       $telegram->sendMessage($content);
   	      }
      }
      
    function Comune_Vs_LatLon($chat_id,$comune,$lat,$lon,$search_distance)
      {
               try {                    
                    $base_path = BASE_PATH;

                    //## Preparo di dati di sessione da memorizzare nel data base per il'id di chat  ...
                    $session_data = array(
                                      array('Chat_id' => $chat_id,
                                            'Comune' => $comune,
                                            'My_Lat' => $lat,
                                            'My_Lon' => $lon,
                                            'Search_Distance' => $search_distance
                                           )
                                     );

                    //## Accedo al db di sessione per memorizzare i dati per il'id di chat  ...
                    $db_data_sessions = new SQLite3($base_path.'/OpenDistributoriCarburantiBot/DataSessionsDB');

                    //## Accedo al db di sessione per eliminare i dati di sessione per di interesse per l'id di chat ...
                    $q="DELETE FROM data_sessions WHERE Chat_id = :Chat_id";
                    try {
                      $stmt = $db_data_sessions->prepare($q);
                      $stmt->bindvalue(':Chat_id', $chat_id, SQLITE3_TEXT);
                      $results = $stmt->execute();
                    }
                    catch(PDOException $e) {
                        print "Something went wrong or Connection to database failed! ".$e->getMessage();
                      }
                    
                    //## Prepare INSERT statement to SQLite3 file db
                    $insert = "INSERT INTO data_sessions (Chat_id, Comune, My_Lat, My_Lon, Search_Distance) VALUES (:Chat_id, :Comune, :My_Lat, :My_Lon, :Search_Distance)";
                    $stmt = $db_data_sessions->prepare($insert);
 
                    //## Bind parameters to statement variables
                    $stmt->bindParam(':Chat_id', $Chat_id);
                    $stmt->bindParam(':Comune', $Comune);
                    $stmt->bindParam(':My_Lat', $My_Lat);
                    $stmt->bindParam(':My_Lon', $My_Lon);
                    $stmt->bindParam(':Search_Distance', $Search_Distance);
 
                    //## Loop thru all messages and execute prepared insert statement
                    foreach ($session_data as $data) {
                      //## Set values to bound variables
                      $Chat_id = $data['Chat_id'];
                      $Comune = $data['Comune'];
                      $My_Lat = $data['My_Lat'];
                      $My_Lon = $data['My_Lon'];
                      $Search_Distance = $data['Search_Distance'];
 
                      //## Execute statement
                      $stmt->execute();
                      
                      $db_data_sessions = null;
                    }
 
               }
               catch(PDOException $e) {
                 print "Something went wrong or Connection to database failed! ".$e->getMessage();
               }
      
      }
      
      
  }
?>

