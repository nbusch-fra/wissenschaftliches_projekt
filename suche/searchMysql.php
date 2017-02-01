<?php
    $start               = microtime(true);
    $iMySQLOperationTime = 0;

    $_oTargetDatabase = new PDO('mysql:host=192.168.178.32;dbname=magazine;charset=utf8', "monty", "some_pass", array(
        PDO::ATTR_PERSISTENT => true
    ));

    $aSearchTerms = [
        "+Obama +Präsident" => "politik",
        "+Obama +Inauguration" => "politik",
        "+Kartell +Strafe" => null,
        "\"Thomas Müller\" -Ribery" => "sport",
        "+Bundestag +Abstimmung" => "politik",
        "+Merkel +Kanzler +Seehofer +Deutschland +Berlin +Steinmeier" => "politik",
        "+Fußball +Weltmeisterschaft" => null,
    ];

    $sql = "SELECT *, " .
        "((MATCH (heading) AGAINST (:fts IN BOOLEAN MODE)) * 3 + (MATCH (teaser) AGAINST (:fts IN BOOLEAN MODE)) * 2 + (MATCH (articletext) AGAINST (:fts IN BOOLEAN MODE))) AS score " .
        "FROM article " .
        "WHERE (MATCH(heading) AGAINST(:fts IN BOOLEAN MODE) " .
        "OR MATCH(teaser) AGAINST(:fts IN BOOLEAN MODE) " .
        "OR MATCH(articletext) AGAINST(:fts IN BOOLEAN MODE)) " .
        "order by score desc LIMIT 0, 25";

    $sqlRessort = "SELECT *, " .
        "((MATCH (heading) AGAINST (:fts IN BOOLEAN MODE)) * 3 + (MATCH (teaser) AGAINST (:fts IN BOOLEAN MODE)) * 2 + (MATCH (articletext) AGAINST (:fts IN BOOLEAN MODE))) AS score " .
        "FROM article " .
        "WHERE (MATCH(heading) AGAINST(:fts IN BOOLEAN MODE) " .
        "OR MATCH(teaser) AGAINST(:fts IN BOOLEAN MODE) " .
        "OR MATCH(articletext) AGAINST(:fts IN BOOLEAN MODE)) " .
        "AND ressort = :ressort " .
        "order by score desc LIMIT 0, 25";

    $sqlWithTime = "SELECT *, " .
        "((MATCH (heading) AGAINST (:fts IN BOOLEAN MODE)) * 3 + (MATCH (teaser) AGAINST (:fts IN BOOLEAN MODE)) * 2 + (MATCH (articletext) AGAINST (:fts IN BOOLEAN MODE))) AS score " .
        "FROM article " .
        "WHERE (MATCH(heading) AGAINST(:fts IN BOOLEAN MODE) " .
        "OR MATCH(teaser) AGAINST(:fts IN BOOLEAN MODE) " .
        "OR MATCH(articletext) AGAINST(:fts IN BOOLEAN MODE)) " .
        "AND publicationdate BETWEEN :pubstart AND :pubend " .
        "order by score desc LIMIT 0, 25";

    $sqlRessortWithTime = "SELECT *, " .
        "((MATCH (heading) AGAINST (:fts IN BOOLEAN MODE)) * 3 + (MATCH (teaser) AGAINST (:fts IN BOOLEAN MODE)) * 2 + (MATCH (articletext) AGAINST (:fts IN BOOLEAN MODE))) AS score " .
        "FROM article " .
        "WHERE (MATCH(heading) AGAINST(:fts IN BOOLEAN MODE) " .
        "OR MATCH(teaser) AGAINST(:fts IN BOOLEAN MODE) " .
        "OR MATCH(articletext) AGAINST(:fts IN BOOLEAN MODE)) " .
        "AND ressort = :ressort " .
        "AND publicationdate BETWEEN :pubstart AND :pubend " .
        "order by score desc LIMIT 0, 25";

    $aUsedTime = $aUsedTimeBetweenDates = [];

    for ($i = 0; $i < 10; $i++) {
        foreach ($aSearchTerms as $sSearchTerm => $sRessort) {
            if (!isset($aUsedTime[$sSearchTerm])) {
                $aUsedTime[$sSearchTerm] = [];
            }

            if ($sRessort != null) {
                list($iCurrentEsOperationTime, $iCount) = executeDatabase($_oTargetDatabase, $sqlRessort, ['fts' => $sSearchTerm], ['ressort' => $sRessort]);
            }
            else {
                list($iCurrentEsOperationTime, $iCount) = executeDatabase($_oTargetDatabase, $sql, ['fts' => $sSearchTerm]);
            }

            $aUsedTime[$sSearchTerm][] = $iCurrentEsOperationTime;
            $iMySQLOperationTime     = $iMySQLOperationTime + $iCurrentEsOperationTime;

            for ($iYear = 1999; $iYear <= 2016; $iYear++) {

                if (!isset($aUsedTimeBetweenDates[$sSearchTerm])) {
                    $aUsedTimeBetweenDates[$sSearchTerm] = [];
                }

                if ($sRessort != null) {
                    list($iCurrentEsOperationTime, $iCount) = executeDatabase($_oTargetDatabase, $sqlRessortWithTime, ['fts' => $sSearchTerm], ['ressort' => $sRessort, 'pubstart' => $iYear . '-01-01 00:00:00', 'pubend' => $iYear . '-12-31 23:59:59']);
                }
                else {
                    list($iCurrentEsOperationTime, $iCount) = executeDatabase($_oTargetDatabase, $sqlWithTime, ['fts' => $sSearchTerm], ['pubstart' => $iYear . '-01-01 00:00:00', 'pubend' => $iYear . '-12-31 23:59:59']);
                }

                $aUsedTimeBetweenDates[$sSearchTerm][$iYear][] = $iCurrentEsOperationTime;// . '; ' . $iCount;
                $iMySQLOperationTime     = $iMySQLOperationTime + $iCurrentEsOperationTime;
            }
        }
    }

    foreach ($aUsedTime as $sSearchTerm => $aTimes) {
        $iSum = 0;
        foreach ($aTimes as $sKey => $sTime) {
            $iSum += $sTime;
            file_put_contents('/home/nico/Uni/Master/Wissenschaftliches Projekt/Search/search_mysql.txt', $sSearchTerm . "; " . $sTime . PHP_EOL, FILE_APPEND);
        }
        file_put_contents('/home/nico/Uni/Master/Wissenschaftliches Projekt/Search/search_mysql_mittelwert.txt', $sSearchTerm . "; " . $sTime . PHP_EOL, FILE_APPEND);
    }

    foreach ($aUsedTimeBetweenDates as $sSearchTerm => $aYears) {
        foreach ($aYears as $sYear => $aTimes) {
            foreach ($aTimes as $sKey => $sTime) {
                file_put_contents('/home/nico/Uni/Master/Wissenschaftliches Projekt/Search/search_mysql_years.txt', $sSearchTerm . "; " . $sYear . "; " . $sTime . PHP_EOL, FILE_APPEND);
            }
        }
    }


    //Aufwändige Berechnung
    $end = microtime(true);

    $laufzeit = $end - $start;
    echo "Laufzeit der MySQL Operationen: " . $iMySQLOperationTime . " Sekunden!" . PHP_EOL;
    echo "Laufzeit des gesamten Scripts: " . $laufzeit . " Sekunden!";

    function executeDatabase($_oTargetDatabase, $sSql, $aParams = [], $aValues = []) {
        $stmt = $_oTargetDatabase->prepare($sSql);

        foreach ($aParams as $sKey => $sValue) {
            $stmt->bindParam(':' . $sKey, $sValue, PDO::PARAM_STR);
        }

        foreach ($aValues as $sKey => $sValue) {
            $stmt->bindValue(':' . $sKey, $sValue, PDO::PARAM_STR);
        }

        $iStartMySQLOperations = microtime(true);
        try {
            $stmt->execute();
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        $iEndMySQLOperations = microtime(true);

        $aResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $iCount = count($aResult);

        $iCurrentEsOperationTime = ($iEndMySQLOperations - $iStartMySQLOperations);

        return [$iCurrentEsOperationTime, $iCount];
    }
