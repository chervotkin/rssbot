<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
define('DRUPAL_ROOT', getcwd());

require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
require_once DRUPAL_ROOT . '/phpQuery/phpQuery.php';

drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

// Define RSS feeds list
//$feeds[] = array("id" => "do", "url" => "http://www.digitaloffroad.com/feed/");
//$feeds[] = array("id" => "e3", "url" => "http://www.enduro360.com/feed/");
$feeds[] = array("id" => "e3", url => "test.rss");

$articles = array();

foreach ($feeds as $feed) { // Collect articles
    $rss_id = $feed['id'];
    $rss_url = $feed['url'];
    
    $rss_feed = file_get_contents($rss_url);
    
    $document = phpQuery::newDocument($rss_feed);
    $items = $document->find("item");
    foreach ($items as $item) {
        $p = pq($item);
        $title = $p->find("title")->html();
        $link = $p->find("link")->html();
        $date = $p->find("pubDate")->html();
        $stamp = md5($rss_id.$link);
        print $stamp."\n";
        //break;
    }
}
?>