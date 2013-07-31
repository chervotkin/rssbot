<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
define('DRUPAL_ROOT', getcwd().'/..');

if ( !isset( $_SERVER['REMOTE_ADDR'] ) ) {
  $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}

require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
require_once DRUPAL_ROOT . '/phpQuery/phpQuery.php';

drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

// Create Table if not exist
// $db_query("create table rss_article (hash char(32), src_link text, title text, status int)");

$table_scheme = array(
    'description' => 'RSS Articles List',
    'fields' => array(
        'hash' => array('type' => 'char', 'length' => 32),
        'src_link' => array('type' => 'text'),
        'title' => array('type' => 'text'),
        'status' => array('type' => 'int')
    )
);
if (!db_table_exists('rss_article')) {
    db_create_table('rss_article', $table_scheme);
}

//Define RSS feeds list
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
        $exists = db_query('select * from rss_article where hash = :stamp', array(':stamp' => $stamp));
        if ($exists->rowCount() > 0) {
//            print "Article $title already in table\n";
        } else {
            db_insert('rss_article')->fields(array(
                'hash' => $stamp,
                'src_link' => $link,
                'title' => $title,
                'status' => 0
            )) -> execute();
        }
        //break;
    }
    
    /* TODO:
     * - get list from rss_articles
     * - parse page
     * - download images
     * - rewrite links
     * - translate text
     * - add node to drupal
     */
}
?>