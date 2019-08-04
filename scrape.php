<?php 
require "vendor/autoload.php";
use PHPHtmlParser\Dom;

new ClRequest(); 

class ClRequest {
    private $rows = [];
    private $sortableFields = ['title', 'url', 'bedrooms', 'cost', 'location'];
    public function __construct(){
        $this->scrapeEntries();
        $this->handleRequest();
    }

    protected function scrapeEntries(){
        $url = 'https://portland.craigslist.org/d/apts-housing-for-rent/search/apa';
        $dom = new Dom;
        $dom->loadFromUrl($url);
        $clp = new ClParser($dom);
        $clp->extractEntries();
        $this->rows = $clp->entries;
    }

    protected function handleRequest(){
        $sortBy = $this->getSortBy();
        $sortDir = $this->getSortDir() == 'desc' ? SORT_DESC : SORT_ASC;
        $col = array_column($this->rows, $sortBy);
        array_multisort($col, $sortDir, $this->rows);
        require('template.phtml');
    }

    public function getSortBy(){
        $sortBy = 'cost';
        if (isset($_GET['sortBy'])){
            if (in_array($_GET['sortBy'], $this->sortableFields)){
                $sortBy = $_GET['sortBy'];
            }
        }
        return $sortBy;
    }

    public function getSortDir(){
        $sortDir = 'desc';
        if (isset($_GET['sortDir'])){
            if(in_array($_GET['sortDir'], ['asc', 'desc'])){
                $sortDir = $_GET['sortDir'];
            }
        }
        return $sortDir;
    }

    public function sortLink($type){
        return "<a href='/?sortBy={$type}&sortDir=desc'>&#x2B07;</a><a href='/?sortBy={$type}&sortDir=asc'>&#x2B06;</a>";
    }
}

class ClParser {
    protected $dom;
    public $entries = [];

    public function __construct($dom){
        $this->dom = $dom;
    }

    public function extractEntries(){
        $entriesDom = $this->dom->find('.result-row');
        foreach ($entriesDom as $entry){
            $entry = new ClEntry($entry);
            $this->entries[] = $entry->data;
        }
    }
}

class ClEntry {
    protected $dom;
    public $data = [
        'title' => null,
        'url' => null,
        'bedrooms' => null,
        'squareFootage' => null,
        'cost' => null,
        'location' => null,
        'thumbnail' => null    
    ];

    private $imagePrefix = 'https://images.craigslist.org/';
    private $imageSuffix = '_50x50c.jpg';

    public function __construct($dom){
        $this->dom = $dom;
        $this->extractData();
    }


    protected function extractData(){
        $title = $this->dom->find('.result-title');
        $this->data['url'] = $title->getAttribute('href');
        $this->data['title'] = $title->text();
        $this->data['cost'] = str_replace('$', '', $this->getText('.result-price'));
        $this->data['location'] = $this->getText('.result-hood', '(Portland)');
        $this->extractImageUrl();
        $this->extractBedroomsAndFootage();
    }

    /**
     * Hit enough cases where there was incomplete/nonexistent text data to 
     * justify a seperate method to provide sane defaults
     */
    protected function getText($className, $default = ""){
        try {
            return $this->dom->find($className)->text();
        } catch(Exception $e){
            return $default;
        }
    }

    /**
     * Extracting to a seperate method here to handle edge cases where the bedrooms 
     * aren't given (usually 0br/studio apartment situations)
     */
    protected function extractBedroomsAndFootage(){
        //Set default of 0 beds/0 square footage to deal with edgecases with bad data
        $housing = $this->getText('.housing', '0 - 0ft');
        $housing = explode('-', $housing);
        if (count($housing) == 3){
            $this->data['bedrooms'] = str_replace('br', '', $housing[0]);
            $this->data['squareFootage'] = str_replace('ft', '', $housing[1]);
        } else {
            $this->data['bedrooms'] = 0;
            $this->data['squareFootage'] = str_replace('ft', '', $housing[0]);
        }
        
    }

    /**
     * Build image URL from data attributes on 
     */
    protected function extractImageUrl(){
        try {
            $images = explode(',', $this->dom->find('.gallery')->getAttribute('data-ids'));
            $this->data['thumbnail'] = $this->imagePrefix . str_replace('1:', '', $images[0]) . $this->imageSuffix;
        } catch (Exception $e){
            echo "Failed recovering " . $this->data['title'] . " : " . $e->getMessage() . "\n";
        }
    }
   
}