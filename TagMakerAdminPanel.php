
<div id="message">
<H1>经典模式</H1>
	<p>插件的原理是没有标签的文章，然后根据文章内容和标题去匹配网站中已有的标签，根据标签出现在文章中出现的次数，自动添加3个出现最频繁的标签</p>
	
	<p>如果你的网站没有标签的文章比较多，同时标签数量恰巧也比较多的话，生成可能会较慢，请耐心等待。</p>
	<p>
		您的网站总计文章有
		<?php echo  $postCount; ?>
		篇，其中没有标签的文章有 :
		<?php echo $noTagPostCount; ?>
		篇。
	</p>
	
<H1>分词模式</H1>
	<p>分词模式的原理是，首先将文章分词，分词使用的是scws字典分词，分词后，取出出现频率最高的5个最为标签</p>
	<p>分词模式如果遇到比较长的文章，速度会比较慢，请耐心等待</p>
	
	<p>最后，如果有任何问题，或者bug反馈，都欢迎到<a href="http://thinkdeep.me/tagmaker">插件主页</a>和我联系</p>
</div>
<form id="act" action="" method="post">
	<p class="submit">
		<input type="submit" name="submit" value="经典生成" /><br>
		<input type="number" name="startid" value="0" />
		<input type="number" name="endtid" value="10" />
		<input type="submit" name="submit" value="分词生成" />
	</p>
</form>
		<?php
		echo $_REQUEST["submit"];
		if($_REQUEST["submit"]=="经典生成"){
			echo "稍等，正在生成<br>";
			require_once 'TagMaker.php';
			makeTags($noTagPost);
			echo "生成完成<br>";
		}
		if($_REQUEST["submit"]=="分词生成"){
			require_once 'TagMaker.php';
			splimakeTags($_REQUEST["startid"],$_REQUEST["endtid"]);
		}
		?>
