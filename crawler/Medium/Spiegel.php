<?php
    require_once 'Abstract.php';

/**
 * Created by PhpStorm.
 * User: nico
 * Date: 16.12.16
 * Time: 19:44
 */
class Medium_Spiegel extends Medium_Abstract {

    protected $_iStartYear = 2014;

    protected $_iStartPage = 1;

    protected $_aRessortUrls = array(
        "wirtschaft" => "http://www.spiegel.de/wirtschaft/archiv-%u%s.html",
        "politik" => "http://www.spiegel.de/politik/archiv-%u%s.html",
        "panorama" => "http://www.spiegel.de/panorama/archiv-%u%s.html",
        "sport" => "http://www.spiegel.de/sport/archiv-%u%s.html",
        "kultur" => "http://www.spiegel.de/kultur/archiv-%u%s.html",
        "netzwelt" => "http://www.spiegel.de/netzwelt/archiv-%u%s.html",
        "wissenschaft" => "http://www.spiegel.de/wissenschaft/archiv-%u%s.html",
        "gesundheit" => "http://www.spiegel.de/gesundheit/archiv-%u%s.html"
    );

    protected $_oDatabase = null;

    public function __construct() {
        $this->_oDatabase = new PDO('mysql:host=localhost;dbname=articles;charset=utf8', "root", "some_pass", array(
            PDO::ATTR_PERSISTENT => true
        ));
    }

    public function crawl() {
        foreach ($this->_aRessortUrls as $sRessortName => $sRessortUrl) {
            $this->_processRessort($sRessortName, $sRessortUrl);
        }
    }

    protected function _processRessort($sRessortName, $sRessortUrl) {
        $iStartPage = $this->_iStartPage;

        for ($iParsingYear = $this->_iStartYear; $iParsingYear <= 2016; $iParsingYear++) {
            while (true) {
                $aArticles = $this->_parseListPage($this->_getPage(sprintf($sRessortUrl, $iParsingYear, str_pad($iStartPage, 3, 0, STR_PAD_LEFT))));

                list($bContinue, $aArticles) = $this->_processArticles($aArticles, $iParsingYear);

                $this->_insertArticles($aArticles, $sRessortName);

                if (!$bContinue) {
                    $iStartPage = 1;
                    break;
                }
                else {
                    $iStartPage = $iStartPage + 2;
                }
            }
        }
    }

    protected function _parseListPage($sHtml) {
        $oDocument = new DOMDocument();
        // We don't want to bother with white spaces
        $oDocument->preserveWhiteSpace = false;

        libxml_use_internal_errors(true);
        $oDocument->loadHTML($sHtml);
        libxml_clear_errors();

        $oXPath = new DOMXPath($oDocument);

        // We starts from the root element
        $query = '/html/body/div/div/div/div/div/div[@class="teaser"]';

        $entries = $oXPath->query($query);

        $sHeadingXPath = "h2/a/@title";
        $sArticleURLXPath = "h2/a/@href";
        $sArticleDateXPath = "div[@class='source-date']";

        $aArticles = [];

        foreach ($entries as $oArticleEntry) {
            $oArticleDocument = new DOMDocument();
            $oClonedArticleEntry = $oArticleEntry->cloneNode(true);
            $oArticleDocument->appendChild($oArticleDocument->importNode($oClonedArticleEntry, true));

            $oHeading = new DOMXPath($oArticleDocument);
            $oArticleURL = new DOMXPath($oArticleDocument);
            $oArticleDate = new DOMXPath($oArticleDocument);

            $oHeading = $oHeading->query($sHeadingXPath);
            $oArticleUrl = $oArticleURL->query($sArticleURLXPath);
            $oArticleDate = $oArticleDate->query($sArticleDateXPath);

            $aArticles[] = (object)[
                "heading"   => trim($oHeading->item(0)->textContent),
                "url"       => trim($oArticleUrl->item(0)->textContent),
                "date"      => trim($oArticleDate->item(0)->textContent)
            ];
        }

        return $aArticles;
    }

    protected function _processArticles($aArticles, $iParsingYear) {
        $bContinue = true;

        foreach ($aArticles as $oArticle) {
            $oDate = (DateTime::createFromFormat('d.m.Y, H:i \U\h\r', $oArticle->date));

            if ($oDate === false) {
                $oDate = (DateTime::createFromFormat('d.m.Y', $oArticle->date));
            }

            if ($oDate !== false && $oDate->format("Y") == $iParsingYear) {
                $this->_parseArticlePage($oArticle, "http://www.spiegel.de" . str_replace(".html", "-druck.html", $oArticle->url));
            }
            elseif ($oDate !== false && $oDate->format("Y") > $iParsingYear) {
                $bContinue = false;
            }
        }

        return [$bContinue, $aArticles];
    }

    protected function _parseArticlePage($oArticle, $sUrl) {
        $sArticlePage = $this->_getPage($sUrl);

        if (empty($sArticlePage)) {
            return;
        }

        $oDocument = new DOMDocument();
        // We don't want to bother with white spaces
        $oDocument->preserveWhiteSpace = false;

        libxml_use_internal_errors(true);
        $oDocument->loadHTML($sArticlePage);
        libxml_clear_errors();

        $oXPath = new DOMXPath($oDocument);

        $sTeaserXPath = "/html/body/div/p/strong";
        $sAuthorXPath = "/html/body/div/p[last()]";
        //$sArticleTextXPath = "/html/body/div/p[position() < last()]";
        $sArticleTextXPath = "/html/body/div/p";

        $oTeaser = $oXPath->query($sTeaserXPath);
        $oAuthor = $oXPath->query($sAuthorXPath);
        $oArticleTexts = $oXPath->query($sArticleTextXPath);

        $oArticle->teaser = $oTeaser->length > 0 ? $oTeaser->item(0)->textContent : "";
        //$oArticle->author = trim(str_replace("Von", "", $oAuthor->item(0)->textContent));

        $aArticle = [];
        foreach ($oArticleTexts as $oArticleText) {
            $aArticle[] = preg_replace('/\s+/', ' ', trim(strip_tags($oArticleText->textContent)));
        }

        $oArticle->articletext = $aArticle;
    }

    protected function _insertArticles($aArticles, $sRessort) {
        $sMedium = "Spiegel";
        foreach ($aArticles as $oArticle) {

            if (!isset($oArticle->articletext)) {
                continue;
            }

            $sArticleText = json_encode(array_values(array_filter($oArticle->articletext, 'strlen')), JSON_UNESCAPED_UNICODE);
            $oPublicationDate = (DateTime::createFromFormat('d.m.Y, H:i \U\h\r', $oArticle->date));

            if ($oPublicationDate === false) {
                $oPublicationDate = (DateTime::createFromFormat('d.m.Y H:i:s', $oArticle->date . " 00:00:00"));
            }

            if ($oPublicationDate !== false) {
                $sPublicationDate = $oPublicationDate->format("Y-m-d H:i:s");
            }
            else {
                continue;
            }

            $oStatement = $this->_oDatabase->prepare("INSERT INTO article (heading, teaser, url, publicationdate, articletext, ressort, medium) VALUES (:heading, :teaser, :url, :publicationdate, :articletext, :ressort, :medium)");
            $oStatement->bindParam(':heading', $oArticle->heading);
            $oStatement->bindParam(':teaser', $oArticle->teaser);
            $oStatement->bindParam(':url', $oArticle->url);
            $oStatement->bindParam(':publicationdate', $sPublicationDate);
            $oStatement->bindParam(':articletext', $sArticleText);
            $oStatement->bindParam(':ressort', $sRessort);
            $oStatement->bindParam(':medium', $sMedium);

            try {
                $oStatement->execute();
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
    }
}
