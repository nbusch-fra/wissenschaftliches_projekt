<?php

/**
 * Created by PhpStorm.
 * User: nico
 * Date: 16.12.16
 * Time: 19:45
 */
abstract class Medium_Abstract {
    public abstract function crawl();

    protected function _getPage($sUrl) {
        // erzeuge einen neuen cURL-Handle
        $ch = curl_init();

        // setze die URL und andere Optionen
        curl_setopt($ch, CURLOPT_URL, $sUrl);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // führe die Aktion aus und gib die Daten an den Browser weiter
        $oResult = curl_exec($ch);

        curl_close($ch);

        return $oResult;
    }
}