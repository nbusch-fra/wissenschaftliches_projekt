<?php
    $start               = microtime(true);
    $iMySQLOperationTime = 0;

    $aRessorts = [
        "politik",
        "wirtschaft",
        "sport",
        "kultur",
        "netzwelt",
        "panorama",
        "wissenschaft"
    ];

    $sRequest = '{
  "from": %s,
  "size" : 25,
  "sort": [
    {"publicationdate": {"order" : "desc"}}
  ],
  "query": { 
    "bool": { 
      "must": [
      ],
      "filter": [ 
        { "term": { "ressort": "%s"}} ,
        { "range": { "publicationdate": { "lte": "%s" }}}
      ]
    }
  }
}';

    $aUsedTime = $aUsedTimeBetweenDates = [];

    for ($i = 0; $i < 10; $i++) {
        foreach ($aRessorts as $iKey => $sRessort) {

            for ($iYear = 1999; $iYear <= 2016; $iYear++) {
                foreach ([0, 1, 2, 10, 15, 50] as $iPage) {

                    if (!isset($aUsedTimeBetweenDates[$sRessort])) {
                        $aUsedTimeBetweenDates[$sRessort] = [];
                    }

                    list($iCurrentEsOperationTime, $iCount) = executeRequest(sprintf($sRequest, $iPage * 25, $sRessort, $iYear . '-01-01 00:00:00'));

                    $aUsedTimeBetweenDates[$sRessort][$iYear][] = $iCurrentEsOperationTime;// . '; ' . $iCount;
                    $iMySQLOperationTime                           = $iMySQLOperationTime + $iCurrentEsOperationTime;
                }
            }
        }
    }

    foreach ($aUsedTimeBetweenDates as $sSearchTerm => $aYears) {
        //$iSum = 0;
        foreach ($aYears as $sYear => $aTimes) {
            $iSumYear = 0;
            foreach ($aTimes as $sKey => $sTime) {
                //$iSum += $sTime;
                $iSumYear += $sTime;
                file_put_contents('/home/nico/Uni/Master/Wissenschaftliches Projekt/Search/list_elasticsearch_years.txt', $sSearchTerm . "; " . $sYear . "; " . $sTime . PHP_EOL, FILE_APPEND);
            }
            file_put_contents('/home/nico/Uni/Master/Wissenschaftliches Projekt/Search/list_elasticsearch_years_durchschnitt.txt', $sSearchTerm . "; " . $sYear . "; " . ($iSumYear / count($aTimes)) . PHP_EOL, FILE_APPEND);
        }
    }


    //Aufwändige Berechnung
    $end = microtime(true);

    $laufzeit = $end - $start;
    echo "Laufzeit der ES Operationen: " . $iMySQLOperationTime . " Sekunden!" . PHP_EOL;
    echo "Laufzeit des gesamten Scripts: " . $laufzeit . " Sekunden!";

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