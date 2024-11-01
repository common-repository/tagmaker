<?php
/*

*/
global $tags;

function makeTags($posts){
	init_tags();
	foreach ($posts as $post)
	{
		echo "处理id为".$post->id."的文章<BR>";
		makeTag($post->id);
	}
}
function init_tags(){

	global  $wpdb;
	global $tags;
	$querysql="select a.term_taxonomy_id,b.name,0 cc from wp_term_taxonomy a,wp_terms b where a.term_id=b.term_id and a.taxonomy='post_tag'";
	$tags=$wpdb->get_results($querysql);
	echo "共有标签".count($tags)."个<BR>";
}

function makeTag($post_id){

	global $tags;
	foreach ($tags as $tag)
	{
		//echo get_post($post_id)->post_title.$tag->name."<br>";
		$tag->cc=substr_count(get_post($post_id)->post_title.get_post($post_id)->post_content,$tag->name);
		//echo $tag->cc."<br>";
	}
	usort($tags,"tagSort");

	$maxTagCount=3;
	$curTagCount=0;
	foreach ($tags as $tag)
	{
		if($curTagCount<$maxTagCount&&$tag->cc>0){
			echo "为文章".get_post($post_id)->post_title."添加标签".$tag->name;
			addTag($post_id,$tag->term_taxonomy_id);
			$curTagCount=$curTagCount+1;
		}
		$tag->cc=0;
	}
	echo "<br>";
}

function tagSort($tagA,$tagB){
	if ($tagA == $tagB) return 0;
	return ($tagA->cc < $tagB->cc) ? -1 : 1;
}
function addTag($post_id,$term_taxonomy_id){

	global  $wpdb;
	$addsql="INSERT INTO   wp_term_relationships   (  object_id  ,  term_taxonomy_id ,  term_order ) VALUES('".$post_id."', '".$term_taxonomy_id."', 0)";
	$wpdb->query($addsql);
}
function addOneTag($TagName,$post_id){

	global  $wpdb;
	$querysql="select a.term_taxonomy_id ,b.name,0 cc from wp_term_taxonomy a,wp_terms b where a.term_id=b.term_id and a.taxonomy='post_tag' and b.name='".$TagName."'";
	$tagID=$wpdb->get_results($querysql);
	if(count($tagID)>0){
		addTag($post_id,$tagID[0]->term_taxonomy_id);
	}
	else {
		$reslut=wp_insert_term($TagName,'post_tag');
		addTag($post_id,$reslut["term_taxonomy_id"]);
		

	}
}

function  splimakeTags($startid,$endid){
	for ($i=$startid; $i<=$endid; $i++){
		@splimakeTagsbyjson($i);
		echo '处理ID为'.$i.'文章<br>';
	};

}

function  splimakeTagsbyxml($post_id){
	require_once 'HttpClient.php';
	$addTags = array();
	$parts=split(' ',strip_tags(get_post($post_id)->post_content));


	$request=new Http_Client(false);
	$request->addPostField('data',strip_tags(get_post($post_id)->post_content));
	$request->addPostField('respond','xml');

	$succeed=false;
	$trycount=3;
	try{
		while(!$succeed&&$trycount>0){
			$trycount=$trycount-1;
			$undate=($request->Post('http://www.ftphp.com/scws/api.php'));
			$waitcount=15;
			while ($request->getStatus()<>'200'&&$waitcount>0){
				$waitcount=$waitcount-1;
				sleep(1);
			}
			$date=@simplexml_load_string($undate);
			if(is_bool($date)){
				$date=@simplexml_load_string(StripSlashes($undate));
			}
			if(is_bool($date)){
				$succeed==$date;
			}else {
				$succeed=true;
			}
		}
	}catch (Exception $e) {
	}
	echo $trycount;
	if(is_bool($date)){
		if ($date==false) {
			echo 'XML解析失败';
		}
	}else{
		if( $date[status]=='error'){
			echo $date[message];
		}else{
			foreach (  $date->words as $word){
				if ($word->attr<>'un'&&(substr_count($word->attr,'n')>0||strlen($word->attr)>5))
				{
					if(array_key_exists( (string)$word->word,$addTags)){
						$addTags[ (string)$word->word]=$addTags[ (string)$word->word]+1;
					}else
					{
						$addTags[ (string)$word->word]=0;
					}
				}
			}
			foreach($addTags as $key=>$value){
				echo $key.':'.$value.'<br>';
			}
		}
	}
}


function  splimakeTagsbyphp($post_id){
	require_once 'HttpClient.php';
	$addTags = array();
	$parts=split ('。',strip_tags(get_post($post_id)->post_content));
	foreach ($parts as $part) {
		$request=new Http_Client(false);
		$request->addPostField('data',$part);
		$request->addPostField('respond','php');

		$succeed=false;
		$trycount=3;
		try{
			while(!$succeed&&$trycount>0){
				$trycount=$trycount-1;
				$undate=($request->Post('http://www.ftphp.com/scws/api.php'));
				$waitcount=15;
				while ($request->getStatus()<>'200'&&$waitcount>0){
					$waitcount=$waitcount-1;
					sleep(1);
				}
				$date=@unserialize($undate);
				if(is_bool($date)){
					$date=@mb_unserialize(StripSlashes($undate));
				}
				if(is_bool($date)){
					$date=@asc_unserialize(StripSlashes($undate));
				}
				if(is_bool($date)){
					$succeed==$date;
				}else {
					$succeed=true;
				}
			}
		}catch (Exception $e) {
		}
		echo $trycount;
		if(is_bool($date)){
			if ($date==false) {
				echo 'PHP解析失败';
			}
		}else{
			if( $date[status]=='error'){
				echo $date[message];
			}else{
				foreach (  $date[words] as $word){
					if ($word[attr]<>'un'&&(substr_count($word[attr],'n')>0||strlen($word[attr])>5))
					{
						if(array_key_exists( $word[word],$addTags)){
							$addTags[ $word[word]]=$addTags[ $word[word]]+1;
						}else
						{
							$addTags[ $word[word]]=0;
						}
					}
				}
			}
		}
	}
	foreach($addTags as $key=>$value){
		echo $key.':'.$value.'<br>';
	}
}

function  splimakeTagsbyjson($post_id){
	require_once 'HttpClient.php';
	$addTags = array();
	$parts=split ('。',strip_tags(get_post($post_id)->post_content));
	$request=new Http_Client(false);
	foreach ($parts as $part) {
		$request->addPostField('data',$part);
		$request->addPostField('respond','json');
		$succeed=false;
		$trycount=1;
		try{
			while(!$succeed&&$trycount>0){
				$trycount=$trycount-1;
				$undate=($request->Post('http://www.ftphp.com/scws/api.php'));
				$waitcount=15;
				while ($request->getStatus()<>'200'&&$waitcount>0){
					$waitcount=$waitcount-1;
					sleep(1);
				}
				$date=@json_decode($undate);
				if(is_bool($date)){
					$succeed==$date;
				}else {
					$succeed=true;
				}
			}
		}catch (Exception $e) {
		}
		if(is_bool($date)){
			if ($date==false) {
				echo 'XML解析失败';
			}
		}else{
			if( $date->status=='error'){
				echo $date->message;
			}else{
				foreach (  $date->words as $word){
					if ($word->attr<>'un'&&(substr_count($word->attr,'n')>0||strlen($word->attr)>5))
					{
						if(array_key_exists( (string)$word->word,$addTags)){
							$addTags[ (string)$word->word]=$addTags[ (string)$word->word]+1;
						}else
						{
							$addTags[ (string)$word->word]=1;
						}
					}
				}
			}
		}
	}
	arsort($addTags);
	$addcount =5;
	foreach($addTags as $key=>$value){
		if($addcount>0&&$value>1){
			addOneTag($key, $post_id);
		}
		$addcount=$addcount-1;
	}
}
function cmp($a,$b){

	if($a==$b){return 0;}
	echo $a.'<br>';
	return ($a>$b)?-1:1;
}
function mb_unserialize($serial_str) {
	$serial_str= preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.strlen('$2').':\"$2\";'", $serial_str );
	$serial_str= str_replace("\r", "", $serial_str);
	return unserialize($serial_str);
}

function asc_unserialize($serial_str) {

	$serial_str = preg_replace('!s:(\d+):"(.*?)";!se', '"s:".strlen("$2").":\"$2\";"', $serial_str );
	$serial_str= str_replace("\r", "", $serial_str);
	return unserialize($serial_str);
}

function xml_to_array( $xmlstr )
{
	$xml=simplexml_load_string($xmlstr);
	return $xml;
}
?>