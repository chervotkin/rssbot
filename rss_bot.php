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
        'rss_id' => array('type' => 'char', 'length' => 2),
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
                'rss_id' => $rss_id,
                'status' => 0
            )) -> execute();
        }
        //break;
    }
    
    /* TODO:
     * + get list from rss_articles
     * - parse page
     * - download images
     * - rewrite links
     * - translate text
     * - add node to drupal
     */
    $articles = db_query("select * from rss_article where status = 0 limit 1");
    foreach ($articles as $article){
        $hash = $article->hash;
        //$html = file_get_contents($article->src_link);
        $html = file_get_contents("test.html");
        $doc = phpQuery::newDocument($html);
        if ($article->rss_id == 'e3') {
            $doc->find("div.yarpp-related")->remove();
            $doc->find("div.addtoany_share_save_container")->remove();
            $doc->find("small")->remove();
            $content = $doc->find("div.singlepost > div > div");
//            print $content;
        }
	$img_dir = DRUPAL_ROOT."/sites/default/files/img/$hash/";
	// Replace <img> by downloaded copies
	$img_doc = pq($content);
	$imgs = $img_doc->find("img");
	foreach ($imgs as $img){
		$i = pq($img);
		$src = $i->attr('src');
		$ext = pathinfo($src, PATHINFO_EXTENSION);
		$dst = $img_dir.md5($src).'.'.$ext;
		//copy($src, $dst);
		$dst = "http://".$_SERVER['REMOTE_ADDR']."/sites/default/files/img/$hash/".md5($src).".".$ext;
		$i->attr('src', $dst);
		$i->removeAttr('class');
		$i->removeAttr('onclick');
	}
	print $content->html();
	//print $imgs;
/*	if (mkdir($img_dir)){

	} else {
		print "Can't create directory $img_dir";
	}*/
    }
}
?>
