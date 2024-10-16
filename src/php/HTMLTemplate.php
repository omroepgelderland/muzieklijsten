<?php
/**
 * 
 * @author Remy Glaser <rglaser@gld.nl>
 */

namespace muzieklijsten;

/**
 * Class voor het maken van stukjes HTML5 zonder volledige pagina.
 */
class HTMLTemplate {

    public \DOMDocument $doc;
    public \DOMElement $body;

    /**
     * @param $source HTML-template (optioneel)
     * @param $options Opties voor \DOMDocument::loadHTML() (optioneel)
     */
    public function __construct( string $source = '', int $options = 0 ) {
        $template = <<<EOT
        <!doctype html>
        <html>
            <head>
                <meta charset="utf-8">
                <title>0</title>
            </head>
            <body>{$source}</body>
        </html>
        EOT;
        $this->doc = new \DOMDocument();
        $this->doc->loadHTML($template, $options);
        $this->body = $this->doc->getElementsByTagName('body')->item(0);
    }

    /**
     * Voegt een node toe aan de body.
     * @param $node
     */
    public function appendChild( \DOMNode $node ): \DOMNode {
        return $this->body->appendChild($node);
    }

    /**
     * Maakt een element.
     * @param $localName
     * @param $value
     */
    public function createElement( string $localName, string $value = '' ): \DOMElement {
        return $this->doc->createElement($localName, $value);
    }

    /**
     * Maakt een textnode
     * @param $data
     */
    public function createTextNode( string $data ): \DOMText {
        return $this->doc->createTextNode($data);
    }

    /**
     * Geeft de HTML als string.
     */
    public function saveHTML(): string {
        $respons = '';
        foreach ( $this->body->childNodes as $child ) {
            $respons .= $this->doc->saveHTML($child);
        }
        return $respons;
    }

    /**
     * @return \DOMNodeList<\DOMElement>
     */
    public function getElementsByTagName( string $qualifiedName ): \DOMNodeList {
        return $this->body->getElementsByTagName($qualifiedName);
    }

    public function createAttribute( string $localName ): \DOMAttr {
        return $this->doc->createAttribute($localName);
    }

}
