<?php
    $start               = microtime(true);
    $iMySQLOperationTime = 0;
    $iCount = 10000;

    $_oDatabase = new PDO('mysql:host=localhost;dbname=articles;charset=utf8', "root", "some_pass", array(
        PDO::ATTR_PERSISTENT => true
    ));

    $_oTargetDatabase = new PDO('mysql:host=192.168.178.32;dbname=magazine;charset=utf8', "monty", "some_pass", array(
        PDO::ATTR_PERSISTENT => true
    ));

    $bContinue = true;
     $j = 0;
    while ($bContinue) {
        $sql= "SELECT * FROM article order by id asc LIMIT :offset, " . $iCount;
        $stmt = $_oDatabase->prepare($sql);
        $iOffset = $j * $iCount;
        $stmt->bindParam(':offset', $iOffset, PDO::PARAM_INT);
        $stmt->execute();
        $bContinue = false;
        $j++;
        $aInserts = [];

        while ($oRow = $stmt->fetchObject()) {
            $aArticleText = json_decode($oRow->articletext);
            $aInserts[] = [
                "id"        => $oRow->id,
                "heading"   => $oRow->heading,
                "teaser"    => $oRow->teaser,
                "url"       => $oRow->url,
                "publicationdate"   => $oRow->publicationdate,
                "articletext"       => implode(" " , (is_array($aArticleText) ? $aArticleText : array())),
                "ressort"           => $oRow->ressort,
                "medium"            => $oRow->medium
            ];
            $bContinue = true;
        }

        if ($bContinue) {
            $sInsertQuery = "INSERT INTO article (heading, teaser, url, publicationdate, articletext, ressort, medium) VALUES ";
            $qPart = array_fill(0, count($aInserts), "(?, ?, ?, ?, ?, ?, ?)");
            $sInsertQuery .= implode(",", $qPart);
            $oInsertStatement = $_oTargetDatabase->prepare($sInsertQuery);
            $i = 1;

            foreach($aInserts as $item) { //bind the values one by one
                //$oInsertStatement->bindValue($i++, $item['id']);
                $oInsertStatement->bindValue($i++, $item['heading']);
                $oInsertStatement->bindValue($i++, $item['teaser']);
                $oInsertStatement->bindValue($i++, $item['url']);
                $oInsertStatement->bindValue($i++, $item['publicationdate']);
                $oInsertStatement->bindValue($i++, $item['articletext']);
                $oInsertStatement->bindValue($i++, $item['ressort']);
                $oInsertStatement->bindValue($i++, $item['medium']);
            }

            $iStartMySQLOperations = microtime(true);
            try {
                $oInsertStatement->execute();
            } catch (Exception $e) {
                echo $e->getMessage();
            }
            $iEndMySQLOperations = microtime(true);


            $iCurrentEsOperationTime = ($iEndMySQLOperations - $iStartMySQLOperations);
            $iMySQLOperationTime = $iMySQLOperationTime + $iCurrentEsOperationTime;
            file_put_contents('/home/nico/Uni/Master/Wissenschaftliches Projekt/Crawler/mysql' . $iCount . '.txt', $j * $iCount . ": Time - " . $iCurrentEsOperationTime . PHP_EOL, FILE_APPEND);
        }
    }

    //Aufwändige Berechnung
    $end = microtime(true);

    $laufzeit = $end - $start;
    echo "Laufzeit der MySQL Operationen: " . $iMySQLOperationTime . " Sekunden!" . PHP_EOL;
    echo "Laufzeit des gesamten Scripts: " . $laufzeit . " Sekunden!";
