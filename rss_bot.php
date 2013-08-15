<?php
header('Content-Type: text/html; charset=utf-8');
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
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
                'status' => 0,
                'nid' => 1
            )) -> execute();
        }
        //break;
    }
    
    /* TODO:
     * + get list from rss_articles
     * + parse page
     * + download images
     * + rewrite links
     * + translate text
     * + add node to drupal
     */
    $articles = db_query("select * from rss_article where status = 0");
    foreach ($articles as $article){
    	print $article->title;
//    	print "\n";
        $hash = $article->hash;
        $html = file_get_contents($article->src_link);
	$html = html_entity_decode($html,ENT_QUOTES,'UTF-8');
        $doc = phpQuery::newDocumentHTML($html, 'utf-8');
        if ($article->rss_id == 'e3') { // If souce site is http://www.enduro360.com
            $doc->find("div.yarpp-related")->remove();
            $doc->find("div.addtoany_share_save_container")->remove();
            $doc->find("small")->remove();
            $content = $doc->find("div.singlepost > div > div");
        }  elseif ($article->rss_id == 'do') { // If cource site is http://www.digitaloffroad.com
            $content = $doc->find("article");
 			$content->find("style")->remove();
			$content->find("h1")->remove();
			$content->find("like")->remove();
			$content->find("div.clearfix")->remove();
			$content->find("div.title-block")->remove();
        	if ($content == ''){ // Try grab video content
        		$content = $doc->find("div.video");
        	}
        }

        $content->find('script')->remove(); // Remove any scripts

        // Check there is video
        $is_video = 0;
        if (strpos($content->html(),'youtube') !== false) {
    		$is_video = 1;
		}

		$img_dir = DRUPAL_ROOT."/sites/default/files/img/$hash/";
		if (file_exists($img_dir) or mkdir($img_dir)){
			$img_doc = pq($content);
			$imgs = $img_doc->find("img");
			foreach ($imgs as $img){
				$i = pq($img);
				$src = $i->attr('src');
				$ext = pathinfo($src, PATHINFO_EXTENSION);
				$dst = $img_dir.md5($src).'.'.$ext;
				copy($src, $dst);
				$dst = "http://".$srv_url."/sites/default/files/img/$hash/".md5($src).".".$ext;
				$i->attr('src', $dst);
				$i->removeAttr('class');
				$i->removeAttr('onclick');
			}
			// Replace anchors
			$anchors = $img_doc->find("a");
			foreach ($anchors as $anchor){
				$i = pq($anchor);
				$src = $i->attr('href');
				$ext = pathinfo($src, PATHINFO_EXTENSION);
				if ((strtolower($ext) == 'jpg') or (strtolower($ext) == 'jpeg')) {
				    $dst = $img_dir.md5($src).'.'.$ext;
				    copy($src, $dst);
				    $dst = "http://".$srv_url."/sites/default/files/img/$hash/".md5($src).".".$ext;
				    $i->attr('href', $dst);
				    $i->removeAttr('class');
				    $i->removeAttr('onclick');
				}
			}
			$content->find("style")->remove();
//			print $content->html();
			$body = utf8_decode($content->html());
//			$body = $content->html();
//			$translated = $body;
			$translated = gtranslate($body);
				
//			print $translated;
			
			/* ----------------------------------------------
			** Create Node
			**-----------------------------------------------
			*/
			$node = new stdClass(); // Create a new node object
			$node->type = "article"; // Or page, or whatever content type you like
			node_object_prepare($node); // Set some default values
			// If you update an existing node instead of creating a new one,
			// comment out the three lines above and uncomment the following:
			// $node = node_load($nid); // ...where $nid is the node id
			
			$node->title    = gtranslate($article->title);
			$node->language = LANGUAGE_NONE; // Or e.g. 'en' if locale is enabled
			
			$node->uid = 1; // UID of the author of the node; or use $node->name
			
			$node->body[$node->language][0]['value']   = $translated;
			$node->body[$node->language][0]['summary'] = text_summary($translated);
			$node->body[$node->language][0]['format']  = 'full_html';
			if ($is_video == 1){
				$node->field_tags[$node->language][]['tid'] = 2;
			}
			// I prefer using pathauto, which would override the below path
			$path = 'node_created_on_' . date('YmdHis');
			//$node->path = array('alias' => $path);
			
			if($node = node_submit($node)) { // Prepare node for saving
			    node_save($node);
			    $query = "update rss_article set status=1, nid=".strval($node->nid)." where hash = '".$article->hash."'";
			    //print $query;
			    db_query($query);
			    echo "  Node with nid " . $node->nid . " saved!\n";
			    sleep(5);
			}
		} else {
			print "Can't create directory $img_dir";
		}
    }
}
?>
