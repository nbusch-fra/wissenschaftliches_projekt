<?php
    $start = microtime(true);
    $iESOperationTime = 0;
    $iCount = 10000;

    $_oDatabase = new PDO('mysql:host=localhost;dbname=articles;charset=utf8', "root", "some_pass", array(
        PDO::ATTR_PERSISTENT => true
    ));

    $bContinue = true;
     $i = 0;
    while ($bContinue) {
        $sql= "SELECT * FROM article order by id asc LIMIT :offset, " . $iCount;
        $stmt = $_oDatabase->prepare($sql);
        $iOffset = $i * $iCount;
        $stmt->bindParam(':offset', $iOffset, PDO::PARAM_INT);
        $stmt->execute();
        $bContinue = false;
        $i++;
        $sPost = "";

        while ($oRow = $stmt->fetchObject()) {
            $sPost .= sprintf("{ \"create\" : { \"_index\" : \"magazine\", \"_type\" : \"article\", \"_id\" : \"%s\" } }", $oRow->id) . PHP_EOL;
            $aArticleText = json_decode($oRow->articletext);
            $sPost .= json_encode([
                "heading"   => $oRow->heading,
                "teaser"    => $oRow->teaser,
                "url"       => $oRow->url,
                "publicationdate"   => $oRow->publicationdate,
                "articletext"       => implode(" " , (is_array($aArticleText) ? $aArticleText : array())),
                "ressort"           => $oRow->ressort,
                "medium"            => $oRow->medium,
                "id"                => $oRow->id
            ]) . PHP_EOL;
            $bContinue = true;
        }

        if ($bContinue) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://192.168.178.32:9200/magazine/_bulk");
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $sPost);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $startESOperations = microtime(true);
            $response          = curl_exec($ch);
            $endESOperations   = microtime(true);
            curl_close($ch);

            $iCurrentEsOperationTime = ($endESOperations - $startESOperations);
            $iESOperationTime = $iESOperationTime + $iCurrentEsOperationTime;
            //file_put_contents('/home/nico/Uni/Master/Wissenschaftliches Projekt/Crawler/es' . $iCount . '.txt', $i * $iCount . ": Time - " . $iCurrentEsOperationTime . PHP_EOL, FILE_APPEND);
        }
    }

    //Aufwändige Berechnung
    $end = microtime(true);

    $laufzeit = $end - $start;
    echo "Laufzeit der Elasticsearch Operationen: " . $iESOperationTime . " Sekunden!" . PHP_EOL;
    echo "Laufzeit des gesamten Scripts: " . $laufzeit . " Sekunden!";
