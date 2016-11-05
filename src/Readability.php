<?php

namespace Readability;

use DOMDocument;
use Exception;
use RuntimeException;

class Readability
{
    /**
     * Readability version.
     *
     * @var string
     */
    const VERSION = '1.0';

    /**
     * Content score identifier.
     *
     * @var string
     */
    const ATTR_CONTENT_SCORE = "contentScore";

    /**
     * Default charset.
     *
     * @var string
     */
    const DOM_DEFAULT_CHARSET = "utf-8";

    /**
     * Error message.
     *
     * @var string
     */
    const MESSAGE_CAN_NOT_GET = "Unable to parse this page for content.";

    /**
     * DOMDocument instance.
     *
     * @var \DOMDocument
     */
    protected $dom;

    /**
     * Html source.
     *
     * @var string
     */
    protected $source;

    /**
     * Article content.
     *
     * @var string
     */
    protected $content;

    /**
     * Article node.
     *
     * @var \DOMNode
     */
    protected $node;

    /**
     * Parent nodes.
     *
     * @var array
     */
    protected $parentNodes = [];

    /**
     * Junk tags which will be removed.
     *
     * @var array
     */
    protected $junkTags = [
        "style", "form", "iframe", "script", "button", "input", "textarea",
        "noscript", "select", "option", "object", "applet", "basefont",
        "bgsound", "blink", "canvas", "command", "menu", "nav", "datalist",
        "embed", "frame", "frameset", "keygen", "label", "marquee", "link"
    ];

    /**
     * Junk attributes which will be removed.
     *
     * @var array
     */
    protected $junkAttrs = [
        "style", "class", "onclick", "onmouseover", "align", "border", "margin"
    ];

    /**
     * Create a new Readability instance.
     *
     * @param string $source
     * @param string $charset
     */
    public function __construct($source = null, $charset = "utf-8")
    {
        if (! is_null($source)) {
            $this->load($source, $charset);
        }
    }

    /**
     * Create a new Readability instance.
     *
     * @param string $source
     * @param string $charset
     */
    public static function make($source = null, $charset = "utf-8")
    {
        return new static($source, $charset);
    }

    /**
     * Load html with its charset.
     *
     * @param  string $source
     * @param  string $charset
     * @return static
     */
    public function load($source, $charset = "utf-8")
    {
        $this->clear();
        
        $this->source = $source;

        // Convert charset to HTML-ENTITIES.
        $source = mb_convert_encoding($source, 'HTML-ENTITIES', $charset);

        // Prepocessing html tags.
        $source = $this->preProcess($source);

        // Get the DOMDocument instance.
        $this->dom = new DOMDocument('1.0', $charset);

        try {
            //libxml_use_internal_errors(true);
            if (! @$this->dom->loadHTML('<?xml encoding="'.static::DOM_DEFAULT_CHARSET.'">'.$this->source)) {
                throw new Exception("Parse HTML Error!");
            }

            foreach ($this->dom->childNodes as $item) {
                if ($item->nodeType == XML_PI_NODE) {
                    $this->dom->removeChild($item); // remove hack
                }
            }

            $this->dom->encoding = static::DOM_DEFAULT_CHARSET;
        } catch (Exception $e) {
            // ...
        }

        return $this;
    }

    public function clear()
    {
        $this->node = null;
        $this->content = null;
    }

    /**
     * Preprocess html tags and charset.
     *
     * @param  string $html
     * @return string
     */
    protected function preProcess($html)
    {
        // Remove the charset attribute.
        preg_match("/charset=([\w|\-]+);?/", $html, $match);

        if (isset($match[1])) {
            $html = preg_replace("/charset=([\w|\-]+);?/", "", $html, 1);
        }

        // Replace all doubled-up <br> tags with <p> tags, and remove fonts.
        $html = preg_replace("/<br\/?>[ \r\n\s]*<br\/?>/i", "</p><p>", $html);
        $html = preg_replace("/<\/?font[^>]*>/i", "", $html);
        $html = preg_replace("#<script(.*?)>(.*?)</script>#is", "", $html);

        return trim($html);
    }

    /**
     * Remove junk tags.
     *
     * @param  \DOMDocument $rootNode
     * @param  string $tagName
     * @return \DOMDocument
     */
    protected function removeJunkTag($rootNode, $tagName)
    {
        $tags = $rootNode->getElementsByTagName($tagName);
        
        //Note: always index 0, because removing a tag removes it from the results as well.
        while ($tag = $tags->item(0)) {
            $parentNode = $tag->parentNode;
            $parentNode->removeChild($tag);
        }
        
        return $rootNode;
    }

    /**
     * Remove junk attributes.
     *
     * @param  \DOMDocument $rootNode
     * @param  string $attr
     * @return \DOMDocument
     */
    protected function removeJunkAttr($rootNode, $attr)
    {
        $tags = $rootNode->getElementsByTagName("*");

        $i = 0;
        while ($tag = $tags->item($i++)) {
            $tag->removeAttribute($attr);
        }

        return $rootNode;
    }

    /**
     * Get title from title tag.
     *
     * @return string
     */
    public function getTitle()
    {
        $delimiter = ' - ';
        $titleNodes = $this->dom->getElementsByTagName("title");

        if ($titleNodes->length && $titleNode = $titleNodes->item(0)) {
            // @see http://stackoverflow.com/questions/717328/how-to-explode-string-right-to-left
            $title  = trim($titleNode->nodeValue);
            $result = array_map('strrev', explode($delimiter, strrev($title)));
            return sizeof($result) > 1 ? array_pop($result) : $title;
        }

        return null;
    }

   /**
     * Get title from title tag.
     *
     * @return string
     */
    public function title()
    {
        return $this->getTitle();
    }

    /**
     * Get publish date using regular expression.
     *
     * @return string
     */
    public function getDate()
    {
        $pattern = '~(\d{4}[年\s-]+\d{1,2}[月\s-]+\d{1,2})~s';

        if (! preg_match($pattern, $this->source, $matches)) {
            return;
        }

        return strpos($matches[1], '月') === false ? $matches[1] : $matches[1].'日';
    }

    /**
     * Get publish date using regular expression.
     *
     * @return string
     */
    public function date()
    {
        return $this->getDate();
    }

    /**
     * Get Leading Image Url
     *
     * @return string
     */
    public function getImages()
    {
        $images = [];

        foreach ($this->getNode()->getElementsByTagName("img") as $image) {
            $images[] = $image->getAttribute("src");
        }

        return $images;
    }

    /**
     * Get Leading Image Url
     *
     * @return string
     */
    public function images()
    {
        return $this->getImages();
    }

    /**
     * Get the article node.
     *
     * @return \DOMNode
     */
    public function getNode()
    {
        if (is_null($this->node)) {
            $this->node = $this->extract();
        }

        return $this->node;
    }

    /**
     * Get the html content.
     *
     * @return string
     */
    public function getContent()
    {
        if (is_null($this->content)) {
            $this->content = $this->makeContent();
        }

        return $this->content;
    }

    /**
     * Get the html content.
     *
     * @return string
     */
    public function content()
    {
        return $this->getContent();
    }

    /**
     * Get the text content.
     *
     * @return string
     */
    public function getText()
    {
        return trim(strip_tags($this->content()));
    }

    /**
     * Get the text content.
     *
     * @return string
     */
    public function text()
    {
        return $this->getText();
    }

    /**
     * Word count.
     *
     * @return int
     */
    public function wordCount()
    {
        return mb_strlen($this->text(), static::DOM_DEFAULT_CHARSET);
    }

    /**
     * Make the content after readability.
     *
     * @return string
     */
    public function makeContent()
    {
        if (! $this->dom) {
            return false;
        }

        $article = $this->getNode();
        
        //Check if we found a suitable top-box.
        if ($article === null) {
            throw new RuntimeException(static::MESSAGE_CAN_NOT_GET);
        }
        
        // Copy to the new DOMDocument
        $target = new DOMDocument;
        $target->appendChild($target->importNode($article, true));

        // Rremove junk tags.
        foreach ($this->junkTags as $tag) {
            $target = $this->removeJunkTag($target, $tag);
        }

        // Rremove junk attributes.
        foreach ($this->junkAttrs as $attr) {
            $target = $this->removeJunkAttr($target, $attr);
        }

        return trim(mb_convert_encoding(
            $target->saveHTML(), static::DOM_DEFAULT_CHARSET, "HTML-ENTITIES"
        ));
    }

    /**
     * Extract the content dom node.
     * Reference：http://code.google.com/p/arc90labs-readability/
     *
     * @return \DOMNode
     */
    protected function extract()
    {
        // Get all paragraphs
        $paragraphs = $this->dom->getElementsByTagName("p");

        // Study all the paragraphs and find the chunk that has the best score.
        // A score is determined by things like: Number of <p>'s, commas, special classes, etc.
        $i = 0;
        while ($paragraph = $paragraphs->item($i++)) {
            $parentNode   = $paragraph->parentNode;
            $contentScore = intval($parentNode->getAttribute(static::ATTR_CONTENT_SCORE));
            $className    = $parentNode->getAttribute("class");
            $id           = $parentNode->getAttribute("id");

            // Look for a special classname
            if (preg_match("/(comment|meta|footer|footnote)/i", $className)) {
                $contentScore -= 50;
            } elseif (preg_match(
                "/((^|\\s)(post|hentry|entry[-]?(content|text|body)?|article[-]?(content|text|body)?)(\\s|$))/i",
                $className)) {
                $contentScore += 25;
            }

            // Look for a special ID
            if (preg_match("/(comment|meta|footer|footnote)/i", $id)) {
                $contentScore -= 50;
            } elseif (preg_match(
                "/^(post|hentry|entry[-]?(content|text|body)?|article[-]?(content|text|body)?)$/i",
                $id)) {
                $contentScore += 25;
            }

            // Add a point for the paragraph found
            // Add points for any commas within this paragraph
            if (strlen($paragraph->nodeValue) > 10) {
                $contentScore += strlen($paragraph->nodeValue);
            }

            // Save parent node's content score.
            $parentNode->setAttribute(static::ATTR_CONTENT_SCORE, $contentScore);

            // Store parent node.
            array_push($this->parentNodes, $parentNode);
        }
        
        // Assignment from index for performance.
        // See http://www.peachpit.com/articles/article.aspx?p=31567&seqNum=5
        for ($i = 0, $len = sizeof($this->parentNodes); $i < $len; $i++) {
            $parentNode      = $this->parentNodes[$i];
            $contentScore    = intval($parentNode->getAttribute(static::ATTR_CONTENT_SCORE));
            $orgContentScore = intval($this->node ? $this->node->getAttribute(static::ATTR_CONTENT_SCORE) : 0);

            if ($contentScore && $contentScore > $orgContentScore) {
                $this->node = $parentNode;
            }
        }
        
        return $this->node;
    }
}
