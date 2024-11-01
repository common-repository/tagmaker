<?php
/*
 Plugin Name: WP-tagMaker
 Plugin URI: http://thinkdeep.me/tagmaker
 Version: 0.2.2
 Author: lich_wang
 Description: 经典模式:插件的原理是没有标签的文章，然后根据文章内容和标题去匹配网站中已有的标签，根据标签出现在文章中出现的次数，自动添加3个出现最频繁的标签.如果你的网站没有标签的文章比较多，同时标签数量恰巧也比较多的话，生成可能会较慢，请耐心等待。分词模式:分词模式的原理是，首先将文章分词，分词使用的是scws字典分词，分词后，取出出现频率最高的5个最为标签.分词模式如果遇到比较长的文章，速度会比较慢，请耐心等待.最后，如果有任何问题，或者bug反馈，都欢迎到插件主页和我联系
 */



//添加控制板
add_action('admin_menu', 'tagMaker_add_pages');

function tagMaker_add_pages()
{
	add_options_page('TagMaker', 'TagMaker', 'administrator', 'main.php',  'showAdminPage');
}
function showAdminPage()
{

	require_once 'TagMaker_ap.php';
	printAdminPage();
}
?>