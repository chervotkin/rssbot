<?php
header('Content-Type: text/html; charset=utf-8');
/* 
 * Populate db table rss_article by hand-written links
 */
define('DRUPAL_ROOT', getcwd().'/..');

if ( !isset( $_SERVER['REMOTE_ADDR'] ) ) {
  $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}

require_once 'rss_bot_settings.php';
require_once 'translator.php';
require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
require_once 'phpQuery/phpQuery.php';

drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

// Create Table if not exist

$table_scheme = array(
    'description' => 'RSS Articles List',
    'fields' => array(
        'hash' => array('type' => 'char', 'length' => 32),
        'src_link' => array('type' => 'text'),
        'title' => array('type' => 'text'),
        'rss_id' => array('type' => 'char', 'length' => 2),
        'status' => array('type' => 'int')
    )
);
if (!db_table_exists('rss_article')) {
    db_create_table('rss_article', $table_scheme);
}

// Define RSS feeds list
$feeds[] = array("id" => "do", "url" => "http://www.digitaloffroad.com/feed/");
$feeds[] = array("id" => "e3", "url" => "http://www.enduro360.com/feed/");
//$feeds[] = array("id" => "e3", url => "test.rss");

$links[] = array(
	"id" => "e3",
	"url" => "http://www.enduro360.com/2013/04/01/featured/april-trophy-girl-alanna/",
	"title" => "Alanna");


$articles = array();

foreach ($links as $link) {
	$rss_id = $link['id'];
    $title = $link['title'];
    $url = $link['url'];
    $stamp = md5($rss_id.$url);
    $exists = db_query('select * from rss_article where hash = :stamp', array(':stamp' => $stamp));
    if ($exists->rowCount() > 0) {
            print "Article $title already in table\n";
    } else {
        db_insert('rss_article')->fields(array(
            'hash' => $stamp,
            'src_link' => $url,
            'title' => $title,
            'rss_id' => $rss_id,
            'status' => 0,
            'nid' => 1
        )) -> execute();
    }
    //break;
}
    
?>
