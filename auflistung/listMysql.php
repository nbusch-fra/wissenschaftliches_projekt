<?php
    $start               = microtime(true);
    $iMySQLOperationTime = 0;

    $_oTargetDatabase = new PDO('mysql:host=192.168.178.32;dbname=magazine;charset=utf8', "monty", "some_pass", array(
        PDO::ATTR_PERSISTENT => true
    ));

    $aRessorts = [
        "politik",
        "wirtschaft",
        "sport",
        "kultur",
        "netzwelt",
        "panorama",
        "wissenschaft"
    ];

    $sql = "SELECT * " .
        "FROM article " .
        "WHERE ressort = :ressort " .
        "AND publicationdate <= :pubstart " .
        "order by publicationdate desc " .
        "LIMIT %s, 25";

    $aUsedTime = $aUsedTimeBetweenDates = [];

    for ($i = 0; $i < 10; $i++) {
        foreach ($aRessorts as $iKey => $sRessort) {

            for ($iYear = 1999; $iYear <= 2016; $iYear++) {
                foreach ([0, 1, 2, 10, 15, 50] as $iPage) {

                    if (!isset($aUsedTimeBetweenDates[$sRessort])) {
                        $aUsedTimeBetweenDates[$sRessort] = [];
                    }

                    list($iCurrentEsOperationTime, $iCount) = executeDatabase($_oTargetDatabase, sprintf($sql, $iPage * 25), [], ['ressort' => $sRessort, 'pubstart' => $iYear . '-01-01 00:00:00']);

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
                file_put_contents('/home/nico/Uni/Master/Wissenschaftliches Projekt/Search/list_mysql_years.txt', $sSearchTerm . "; " . $sYear . "; " . $sTime . PHP_EOL, FILE_APPEND);
            }
            file_put_contents('/home/nico/Uni/Master/Wissenschaftliches Projekt/Search/list_mysql_years_durchschnitt.txt', $sSearchTerm . "; " . $sYear . "; " . ($iSumYear / count($aTimes)) . PHP_EOL, FILE_APPEND);
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
