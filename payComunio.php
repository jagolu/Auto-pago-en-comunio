<?php
    /**
     * Obtiene una unica cookie
     */
    function getOneCookie($string, $start){
        $start = strpos($string, $start."=");
        $end = strpos ($string, ";");
        $length = $end - $start +1;
        return substr ($string, $start, $length);
    }

    /**
     * Obtiene todas las cookies
     */
    function getCookies($http_response_header){
        $cookie =       'language=es_ES; '.
                        'tiplineup_table=false; '.
                        getOneCookie($http_response_header[7], 'PHPSESSID').' '.
                        'session_language=es_ES; '.
                        getOneCookie($http_response_header[15], 'c').' '.
                        'tz=Europe%2FMadrid; '.
                        getOneCookie($http_response_header[13], 'phpbb2mysql_data').' '.
                        getOneCookie($http_response_header[14], 'phpbb2mysql_sid');
        return $cookie;
    }

    /**
     * Obtiene le PID de un usuario
     */
    function getPID($string){
        $start = strpos($string, "pid=");
        return substr($string, $start+4, strlen($string));
    }

    /**
     * Petition HTTP POST
     */
    function requestPost($url, $data, $cookies, $show = false){
        $options = array(
            'http' => array(
                'header'    =>  "content-Type: application/x-www-form-urlencoded\r\n".
                                "cookie: ".$cookies."\r\n",
                'method'    => 'POST',
                'content'   => http_build_query($data)
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents ($url, false, $context);
        if($show){
            echo 'request--><br/>'.var_dump($options).'<br/><br/><br/><br/>response--><br/>';
            echo var_dump($http_response_header).'<br/><br/><br/><br/><br/><br/><br/><br/>';
        }
        return $http_response_header;
    }

    /**
     * Funcion HTTP GET
     */
    function requestGet($url, $cookies){
        $Options = array(
            'http'=>array(
                'method'=>"GET",
                'header'=> "Cookie: ".$cookies."\r\n"
            )
        );
        $context = stream_context_create($Options);
        $file = file_get_contents($url, false, $context);
        return $file;
    }

    /**
     * Obtiene el ranking de una room
     */
    function getRanking($cookies){
        $file = requestGet(
            'https://www.comunio.es/standings.phtml?currentweekonly_x', 
            $cookies
        );
        try{
            $html = new DOMDocument();
            @$html->loadHTML($file);
            $tableRanking = $html->getElementById('tablestandings');
            foreach($tableRanking->getElementsByTagName('tr') as $player) {
                $playerStats=$player->childNodes;
                $user = [];
                $n_col = 0;
                foreach($player->childNodes as $playerStats){
                    $value = $playerStats->nodeValue;
                    foreach($playerStats->getElementsByTagName('a') as $link){
                        $user [] = getPID($link->getAttribute('href'));
                    }
                    if($value != "Jugador" && $value != "Puntos" && $n_col!=0 && $n_col!=1){
                        $user [] = $value;
                    }
                    $n_col++;
                }
                if(count($user)!=0) $ranking [] = $user;
            }
        }catch(Exception $e){
            echo "adfadsfasdfas";
        }
        return $ranking;
    }

    /**
     * Obtiene la cantidad ganada por la position en la que ha quedado.
     */
    function getAmountAndMessageByPosition($position, $coinsPerPosition){
        $position = $position + 1;
        if(array_key_exists($position, $coinsPerPosition)){
            $amountAndMessage = array(
                "amount"    => $coinsPerPosition[$position],
                "message"   => $position."ª posicion."
            );
        }
        else{
            $amountAndMessage = array(
                "amount"    => 0,
                "message"   => "Tu posición no está registrada"
            );
        }
        return $amountAndMessage;
    }

    /**
     * Obtiene la cantidad segun los puntos que se han conseguido
     */
    function getAmountAndMessageByPoints($points, $coinsPerPoint){
        return array(
            "amount"    => intval($points)*$coinsPerPoint,
            "message"   => $points." puntos (100000 monedas por punto)."
        );
    }

    /**
     * Inserta las monedas a un unico jugador
     */
    function payToAPlayer($url, $player_id, $amountAndMessage, $cookies){
        requestPost(
            $url,
            array(
                'newsDis'       => 'messageDis',
                'pid_to'        => $player_id,
                'amount'        => $amountAndMessage["amount"],
                'content'       => $amountAndMessage["message"]
            ),
            $cookies
        );
    }

    /**
     * Paga
     */
    function pay($ranking, $coinsPerPosition, $coinsPerPoint,$cookies){
        $url = 'https://www.comunio.es/administration.phtml?penalty_x';
        $file = requestGet( $url,  $cookies );
        //new
        $position = -1;
        $beforePoints = -10000000;
        $samePosition = 0;
        //end new
        for($i=0;$i<count($ranking);$i++){
            $player_id = $ranking[$i][0];
            $points = $ranking[$i][1];
            //new
            if($points != $beforePoints){
                $position = $position + $samePosition + 1;
                $samePosition = 0;
                $beforePoints = $points;
            }
            else $samePosition++; 
            //end new
            payToAPlayer(
                $url, $player_id, 
                getAmountAndMessageByPosition($position, $coinsPerPosition),
                $cookies
            );

            payToAPlayer(
                $url, $player_id, 
                getAmountAndMessageByPoints($points, $coinsPerPoint),
                $cookies
            );
        }
    }

    /**
     * Se registra
     */
    function login($userAndPass){
        $dontHaveCookiesYet ="";
        $responseHeaders = requestPost(
            'https://www.comunio.es/login.phtml',
            array(
                'login'     => $userAndPass["user"],
                'pass'      => $userAndPass["pass"],
                'action'    => 'login'
            ),
            $dontHaveCookiesYet
        );
        return getCookies($responseHeaders);
    }

    
/*--------------------------------------------MAIN-----------------------------------*/

    $userAndPass = array(
        "user"      => "Usuario administrador del grupo",
        "pass"      => "Contraseña del administrador"
    );
    $coinsPerPoint = 100000;
    $coinsPerPosition = array(
        '1'     => '6000000',
        '2'     => '4000000',
        '3'     => '2000000'
    );


    try{
        $sessionCookies = login($userAndPass);
        
        $ranking = getRanking($sessionCookies);
        pay($ranking, $coinsPerPosition, $coinsPerPoint ,$sessionCookies);
    }catch(Error $e){
        echo "Ha habido un error y no se ha podido pagar a los jugadores.";
    }
?>
