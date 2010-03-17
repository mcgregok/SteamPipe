<?
Class SteamApi
{
    private $steam64ID;
    
    private $profile_cache;
    private $games_cache;
    private $friends_cache;
    private $achievement_cache;
    
    public $base_profile_url;    
    public $error;
    public $message;
    
    public function __construct($id) 
    {
        if(is_numeric($id))
        {
            $this->base_profile_url = 'http://steamcommunity.com/profiles/';
        }
        else
        {
            $this->base_profile_url = 'http://steamcommunity.com/id/';
        }
        
        $this->steam64ID = $id;
    }
    
    public function fetch_profile()
    {
        $this->error   = 0;
        $this->message = ''; 
        //use cache if present
        if($this->profile_cache != '')
        {
            return $this->profile_cache;
        }
            
        //fetch data
        $xml = $this->fetch($this->base_profile_url . $this->steam64ID . '?xml=1');
        if($xml === false)
        {
            //error and message set in fetch
            return false;
        }
        
        $simplexml  = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if($simplexml === false)
        {
            $this->error   = 1;
            $this->message = 'Error Parsing steam profile xml';
            return false;
        }
        
        
        $data = $this->process_xml($simplexml);
        
        if(empty($data['steamID']))
        {
            //steam community profile not yet set up
            $this->error   = 1;
            $this->message = $data['privacyMessage'];
            return false;
        }
        
        //data fixing //todo: refactor to common code to fix_profile_section($parentkey, $childkey)
        if(array_key_exists('mostPlayedGames', $data))
        {
            $data['mostPlayedGames'] = array_merge($data['mostPlayedGames'],$data['mostPlayedGames']['mostPlayedGame']);
            unset($data['mostPlayedGames']['mostPlayedGame']);
        
            if($data['mostPlayedGames'][0] == '')
            {
                $data['mostPlayedGames'] = array($data['mostPlayedGames']);
            }
        }
        else
        {
            $data['mostPlayedGames'] = NULL;
        }
        
        //fix friends
        if(array_key_exists('friends', $data))
        {
            $data['friends'] = array_merge($data['friends'], $data['friends']['friend']);
            unset($data['friends']['friend']);
        
            if($data['friends'][0] == '')
            {
                $data['friends'] = array($data['friends']);
            }
        }
        else
        {
            $data['friends'] = NULL;
        }
        
        //fix groups
        if(array_key_exists('groups', $data))
        {
            $data['groups'] = array_merge($data['groups'], $data['groups']['group']);
            unset($data['groups']['group']);
            
            if($data['groups'][0] == '')
            {
                $data['groups'] = array($data['groups']);
            }
        }
        else
        {
            $data['groups'] = NULL;
        }
        
        //fix weblinks
        if(array_key_exists('weblinks', $data) && $data['weblinks'][0] != NULL)
        {
            $data['weblinks'] = array_merge($data['weblinks'], $data['weblinks']['weblink']);
            unset($data['weblinks']['weblink']);
            
            if($data['weblinks'][0] == '')
            {
                $data['weblinks'] = array($data['weblinks']);
            }
        }
        else
        {
            $data['weblinks'] = NULL;
        }                
        
        //store in cache
        $this->profile_cache = $data;

        return $data;
    }
    
    public function fetch_games()
    {
        $this->error   = 0;
        $this->message = '';
        
        //use cache if present
        if($this->games_cache != '')
        {
            return $this->games_cache;
        }
        
        $games     = array();
          
        $html = $this->fetch($this->base_profile_url . $this->steam64ID . '/games/');
        
        if($html === false)
        {
            //error and message set in fetch
            return false;
        }
        
        $domhtml = new DOMDocument();
        @$domhtml->loadHTML($html);
        
        $xpath = new Domxpath($domhtml);
        //parse game names
        foreach($xpath->query("//h4") as $node)
        {
            $hoursPlayed = 0;
            $hoursPlayedEnglish = "Played 0 hours past 2 weeks";
            $statsName = '';
            $loop_node = $node->nextSibling;
            
            //find hours played and statsName
            while($loop_node)
            {
                //echo $loop_node->nodeName;
                if($loop_node->nodeName == 'h5')//find hours played if any
                {
                    $hoursPlayed = str_replace(array('Played ', ' hours past 2 weeks'), '', $loop_node->nodeValue);
                    $hoursPlayedEnglish = $loop_node->nodeValue;
                    //var_export($loop_node->nodeValue);
                }
                
                if($loop_node->nodeName=='a' && $loop_node->nodeValue == 'View stats')
                {
                    $statsName = array_pop(explode('/', $loop_node->attributes->getNamedItem('href')->value));
                    //echo $statsName;
                }
                
                if($loop_node->nodeName=='div') //hack to end searching after current game...
                {
                    break;
                }
                
                $loop_node = $loop_node->nextSibling;
                //echo'<br>';
            }
            
            $game_info = array('gameName'           => $node->nodeValue,
                               'hoursPlayed'        => $hoursPlayed,
                               'hoursPlayedEnglish' => $hoursPlayedEnglish);
            if($statsName != '')
            {
                $game_info['statsName'] = $statsName;
            }
            
            array_push($games, $game_info);
        }
 
        //parse gameLogo / gamelink
        $counter = 0;
        foreach($xpath->query("//div[@class='gameLogo']/a/img[@src]") as $node)
        {
        
            $games[$counter]['gameLogo'] = $node->attributes->getNamedItem('src')->value;
            
            $games[$counter]['gameLink'] = $node->parentNode->attributes->getNamedItem('href')->value;
            $counter++;
        }
        
        //todo: figure out a way to parse the game stats links correctly
        //var_export($games);
        $this->games_cache = $games;
        return $games;
    }
    
    public function fetch_friends()
    {
        $this->error   = 0;
        $this->message = '';
        
        //use cache if present
        if($this->friends_cache != '')
        {
            return $this->friends_cache;
        }
        
        $friends = array();
        
        $html = $this->fetch($this->base_profile_url . $this->steam64ID . '/friends/');
        
        if($html === false)
        {
            //error and message set in fetch
            return false;
        }
        
        $domhtml = new DOMDocument();
        @$domhtml->loadHTML($html);
        
        $xpath = new Domxpath($domhtml);
        //parse game names
        
        //todo: find a way to refactor this to one xpath query
        foreach($xpath->query("//a[contains(@class,'linkFriend_')]") as $node)//ignore spans with this class...they are not friends
        {
            $status = explode('_', $node->attributes->getNamedItem('class')->value);
            $status = $status[1];
            
            $steamID64 = array_pop(explode('/', $node->attributes->getNamedItem('href')->value));
            array_push($friends, array('steamID64' => $steamID64,
                                       'steamID' => $node->nodeValue,
                                       'onlineStat' => $status,));
        }
        
        //get icon
        $counter = 0;
        foreach($xpath->query("//*[@class='avatarIcon']") as $node)
        {
            $friends[$counter]['avatarIcon'] = $node->firstChild->firstChild->attributes->getNamedItem('src')->value;
            $counter++;
        }
        
        //state message and friendsSince
        $counter = 0;
        foreach($xpath->query("//*[@class='friendSmallText']") as $node)
        {
            list($stateMessage, $friendsSince) = explode("\t",trim($node->nodeValue), 2);
            
            $friends[$counter]['stateMessage']        = $stateMessage;
            $friends[$counter]['friendsSince']        = strtotime(str_replace('Friends since ', '', trim($friendsSince)));
            
            $friends[$counter]['friendsSince'] = ($friends[$counter]['friendsSince'] === false)? 0 : $friends[$counter]['friendsSince'];
            
            $friends[$counter]['friendsSinceEnglish'] = trim($friendsSince); 
            $counter++;
        }
        
        $this->friends_cache = $friends;
        return $friends;
        
    }
    
    public function fetch_achievements($statsName)
    {
        $this->error   = 0;
        $this->message = ''; 
        //use cache if present
        if($this->achievement_cache[$statsName] != '')
        {
            return $this->achievement_cache[$statsName];
        }
            
        //fetch data
        $xml = $this->fetch($this->base_profile_url . $this->steam64ID . '/stats/' . $statsName . '?xml=1');
        if($xml === false)
        {
            //error and message set in fetch
            return false;
        }
        
        $simplexml  = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if($simplexml === false)
        {
            $this->error   = 1;
            $this->message = 'Error Parsing steam profile xml';
            return false;
        }
        
        $data = $this->process_xml($simplexml);
        
        //data fixing
        $data = $data['achievements']['achievement'];
        
        foreach($data as &$achievement)
        {
            $achievement['closed'] = $achievement['@attributes']['closed'];
            unset($achievement['@attributes']);
        }
        
        $this->achievement_cache[$statsName] = $data;
        
        return $data; 
    }

    private function process_xml($input, $recurse = false)
    {
        $data = ((!$recurse) && is_string($input))? simplexml_load_string($input, 'SimpleXMLElement', LIBXML_NOCDATA): $input;
       
        if ($data instanceof SimpleXMLElement)
        {
            $data = (array) $data;
            if(empty($data))
            {
                $data = '';
            }
        }
        
        if (is_array($data)) 
        {
            foreach ($data as &$item) 
            {
                $item = $this->process_xml($item, true); //edit by reference
            }
        }

        return $data;
    }

    private function fetch($url)
    {
    
        $curl = curl_init();
		
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
		
		$data = curl_exec($curl);
		$info = curl_getinfo($curl);
        
        //var_export($info);
		
		curl_close($curl);
        
        if($info['http_code'] == 200)
        {

            return $data;
        }
        else
        {
            $this->error   = 1;
            $this->message = 'Error fetching steam information';
            return false;
        }
    }
}