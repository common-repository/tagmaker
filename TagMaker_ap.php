<?php
$noTagPost="";
function printAdminPage(){

	$querysql="";
	$postCount=0;
	$noTagPostCount=0;

	global  $wpdb;

	//get total posts
	$querysql="select count(1) from wp_posts where post_status='publish' and post_type='post' ";
	$postCount=$wpdb->get_var($querysql);


	//get non-tag posts
	$querysql="select a.id, a.post_title ,sum(case when b.taxonomy='post_tag' then 1 else 0 end) rs from wp_posts a,wp_term_taxonomy b,wp_term_relationships c, wp_terms d where a.post_status='publish' and a.post_type='post' and a.id=c.object_id and c.term_taxonomy_id=b.term_taxonomy_id and b.term_id=d.term_id group by a.id, a.post_title having sum(case when b.taxonomy='post_tag' then 1 else 0 end)=0";
	$noTagPost=$wpdb->get_results($querysql);
	$noTagPostCount=count($noTagPost);

	//show form
	require_once 'TagMakerAdminPanel.php';
}


?>