<?php
    $start = microtime(true);
    $iESOperationTime = 0;

    $aSearchTerms = [
        "+Obama +Präsident" => "politik",
        "+Obama +Inauguration" => "politik",
        "+Kartell +Strafe" => null,
        "Thomas AND Müller -Ribery" => "sport",
        //"\"Kanzlerin Merkel\"@5" => null,
        "+Bundestag +Abstimmung" => "politik",
        "+Merkel +Kanzler +Seehofer +Deutschland +Berlin +Steinmeier" => "politik",
        "+Fußball +Weltmeisterschaft" => null,
    ];

    $aUsedTime = $aUsedTimeBetweenDates = [];

    $sQuery = '{
      "from": 0,
      "size" : 25,
      "query": { 
        "bool": { 
          "must": [
            { "multi_match": { "query": "%s", "fields": ["heading*^3", "teaser*^2", "articletext"], "type":"cross_fields"       }} 
          ]
        }
      }
    }';

    $sQueryRessort = '{
      "from": 0,
      "size" : 25,
      "query": { 
        "bool": { 
          "must": [
            { "multi_match": { "query": "%s", "fields": ["heading*^3", "teaser*^2", "articletext"], "type":"cross_fields"       }} 
          ],
          "filter": [ 
            { "term": { "ressort": "%s"}}
          ]
        }
      }
    }';

    $sQueryDate = '{
      "from": 0,
      "size" : 25,
      "query": { 
        "bool": { 
          "must": [
            { "multi_match": { "query": "%s", "fields": ["heading*^3", "teaser*^2", "articletext"], "type":"cross_fields"       }} 
          ],
          "filter": [ 
            { "range": { "publicationdate": { "gte": "%s", "lte": "%s" }}}
          ]
        }
      }
    }';

    $sQueryRessortDate = '{
      "from": 0,
      "size" : 25,
      "query": { 
        "bool": { 
          "must": [
            { "multi_match": { "query": "%s", "fields": ["heading*^3", "teaser*^2", "articletext"], "type":"cross_fields"       }} 
          ],
          "filter": [ 
            { "term": { "ressort": "%s"}},
            { "range": { "publicationdate": { "gte": "%s", "lte": "%s" }}}
          ]
        }
      }
    }';

    for ($i = 0; $i < 10; $i++) {
        foreach ($aSearchTerms as $sSearchTerm => $sRessort) {
            if (!isset($aUsedTime[$sSearchTerm])) {
                $aUsedTime[$sSearchTerm] = [];
            }

            if ($sRessort != null) {
                list($iCurrentEsOperationTime, $iCount) = executeRequest(sprintf($sQueryRessort, $sSearchTerm, $sSearchTerm, $sSearchTerm, $sRessort));
            }
            else {
                list($iCurrentEsOperationTime, $iCount) = executeRequest(sprintf($sQuery, $sSearchTerm, $sSearchTerm, $sSearchTerm));
            }

            $aUsedTime[$sSearchTerm][] = $iCurrentEsOperationTime;
            $iESOperationTime     = $iESOperationTime + $iCurrentEsOperationTime;

            for ($iYear = 1999; $iYear <= 2016; $iYear++) {

                if (!isset($aUsedTimeBetweenDates[$sSearchTerm])) {
                    $aUsedTimeBetweenDates[$sSearchTerm] = [];
                }

                if ($sRessort != null) {
                    list($iCurrentEsOperationTime, $iCount) = executeRequest(sprintf($sQueryRessortDate, $sSearchTerm, $sSearchTerm, $sSearchTerm, $sRessort, $iYear . '-01-01 00:00:00', $iYear . '-12-31 23:59:59'));
                }
                else {
                    list($iCurrentEsOperationTime, $iCount) = executeRequest(sprintf($sQueryDate, $sSearchTerm, $sSearchTerm, $sSearchTerm, $iYear . '-01-01 00:00:00', $iYear . '-12-31 23:59:59'));
                }

                $aUsedTimeBetweenDates[$sSearchTerm][$iYear][] = $iCurrentEsOperationTime . '; ' . $iCount;
                $iESOperationTime     = $iESOperationTime + $iCurrentEsOperationTime;
            }
        }
    }

    foreach ($aUsedTime as $sSearchTerm => $aTimes) {
        $iSum = 0;
        foreach ($aTimes as $sKey => $sTime) {
            $iSum += $sTime;
            file_put_contents('/home/nico/Uni/Master/Wissenschaftliches Projekt/Search/search_elasticsearch.txt', $sSearchTerm . "; " . $sTime . PHP_EOL, FILE_APPEND);
        }
        file_put_contents('/home/nico/Uni/Master/Wissenschaftliches Projekt/Search/search_elasticsearch_mittelwert.txt', $sSearchTerm . "; " . ($iSum / count($aTimes)) . PHP_EOL, FILE_APPEND);
    }

    foreach ($aUsedTimeBetweenDates as $sSearchTerm => $aYears) {
        foreach ($aYears as $sYear => $aTimes) {
            foreach ($aTimes as $sKey => $sTime) {
                file_put_contents('/home/nico/Uni/Master/Wissenschaftliches Projekt/Search/search_elasticsearch_years.txt', $sSearchTerm . "; " . $sYear . "; " . $sTime . PHP_EOL, FILE_APPEND);
            }
        }
    }


    function executeRequest($sPost) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://192.168.178.32:9200/magazine/_search");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $sPost);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $startESOperations = microtime(true);
        $response          = curl_exec($ch);
        $endESOperations   = microtime(true);
        curl_close($ch);

        $aResult = json_decode($response, true);

        return [($endESOperations - $startESOperations), (isset($aResult["hits"]["hits"]) ? count($aResult["hits"]["hits"]) : 0)];
    }

    //Aufwändige Berechnung
    $end = microtime(true);

    $laufzeit = $end - $start;
    echo "Laufzeit der Elasticsearch Operationen: " . $iESOperationTime . " Sekunden!" . PHP_EOL;
    echo "Laufzeit des gesamten Scripts: " . $laufzeit . " Sekunden!";