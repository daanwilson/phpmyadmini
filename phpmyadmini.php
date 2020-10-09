<?php
session_start();
/** Turn on error reporting.* */
ini_set('display_errors',1);
error_reporting(-1);
/** Set Date/time zone * */
date_default_timezone_set(date_default_timezone_get());//Europe/Amsterdam
/** Set charset * */
define("CHARSET","utf-8");
define("DB_CHARSET","utf8");
define("DB_DATABASE","");		//fill in the database if you want to force to use this database.
define("DB_HOST","localhost");	//default host.
define("MAX_ROWS_PER_PAGE",50);	//default record limit

define("APP_NAME","phpMyAdmini");
define("APP_VERSION","1.0");
define("APP_VERSION_RD","2015-06-07");
define("INLOG_LOCK_FILE",sys_get_temp_dir()."/".md5(APP_NAME).'.lock');	//For max inlog attemptions

/** Set external client files locations * */
define("JQUERY","https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js");
define("BOOTSTRAP_CSS","https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css");
define("BOOTSTRAP_JS","https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js");

/* CAUTION!!!! USE YOUR OWN PROTECTION, BECAUSE YOU WILL OPEN THE FRONTDOOR!!! */
//auth::autoConnect('root','','localhost'); 

if(_G('phpinfo')=='true'){
	phpinfo();
	die;
}
if(get_magic_quotes_gpc()){
	$_GET=killmq($_GET);
	$_POST=killmq($_POST);
	$_REQUEST=killmq($_REQUEST);
}
if(!guard::check_xss()){
	auth::disconnect();
}else{
	if(_P('sign_in') && _P('username') && guard::check_lock()){
		if(!auth::connect(_P('username'),_P('password'),_P('host'))){
			guard::update_lock();
		}
	}
	if(auth::check()){
		if(_G('m')=='process'){
			q()->add("SHOW PROCESSLIST");
		}elseif(_G('m')=='stats'){
			q()->add("SHOW STATUS");
		}elseif(_G('m')=='vars'){
			q()->add("SHOW VARIABLES");
		}elseif(_R('a')=='IMPORT' && isset($_FILES['file'])){
			Import::run($_FILES['file']);
		}elseif(_P('exec') && _P('query')!=''){
			q()->add(_P('query'));
		}elseif(_P('exec')=="EXPORT" && is_array(_P('tables'))){
			if(_P('options')){
				Export::run(_P('tables'),_P('options'));
			}
		}elseif(_R('exec') && (is_array(_P('tables')) || (_R('table')))){
			if(is_array(_P('tables'))){
				$tables=_P('tables');
			}elseif(_R('table')){
				$tables=array(_R('table')=>_R('table'));
			}
			if(is_array($tables)){
				foreach($tables as $t){
					q()->add(sprintf('%s `%s`',_R('exec'),$t));
				}
				if(_R('exec')=="DROP TABLE" || _R('exec')=='TRUNCATE'){
					q()->setConfirm();
				}
			}
		}elseif(_R('record')!==false && _P('save_record')){
			db()->saveRecord();
		}elseif(_R('record')!==false && _P('remove_record')){
			db()->removeRecord();
		}elseif(db()->getTable()){
			q()->add(q()->build());
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="<?php echo CHARSET;?>">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
		<meta name="description" content="<?php echo APP_NAME;?>">
		<meta name="author" content="Daan Wilson">
		<title><?php echo APP_NAME;?></title>
		<!-- Bootstrap core CSS -->
		<link href="<?php echo BOOTSTRAP_CSS;?>" rel="stylesheet">
		<!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
		<!--[if lt IE 9]>
				<script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
				<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
		<![endif]-->
		<style>
			html{overflow-y:scroll;}
			.form-signin{max-width:330px; margin:0 auto;padding:15px;}
			.container-fluid{margin-top:60px;}
			.sidebar{position:fixed;max-height:100%;overflow:hidden;overflow-y:auto;}
			.sidebar .nav > li > a {padding:5px 15px;}
			/*.main>.panel{overflow:hidden;overflow-x: auto;}*/
			.table .ellipsis{max-width:250px;}
			.ellipsis{white-space:nowrap;display:block;overflow:hidden;text-overflow:ellipsis;}
			.table-extras{height:110px;overflow:auto;}
			.table-extras .selection{font-size:10px;}
			.table-extras .selection:hover{text-decoration: underline;}
			.export-tables{height:400px;overflow:auto;}
		</style>
	</head>
	<body>
		<?php
		if(!auth::check()){
			?>
			<div class="container">
				<form class="form-signin" method="POST" action="<?php echo lnk::link();?>">
					<h2 class="form-signin-heading">Please sign in</h2>
					<?=msg::get();?>
					<label for="inputUser" class="sr-only">Username</label>
					<input type="text" name="username" id="inputUser" class="form-control" placeholder="Username" required autofocus value="<?=(_P('sign_in') ? _P('username') : '');?>">
					<br/>
					<label for="inputPassword" class="sr-only">Password</label>
					<input type="password" name="password" id="inputPassword" class="form-control" placeholder="Password">
					<br/>
					<label for="inputPassword" class="sr-only">Host</label>
					<input type="text" name="host" id="inputHost" class="form-control" placeholder="Host" value="<?=(_P('host') ? _P('host') : DB_HOST);?>">
					<br/>
					<button class="btn btn-lg btn-primary btn-block" name="sign_in" value="true" type="submit">Sign in</button>
					<hr/><small><?=APP_NAME;?> <?=APP_VERSION;?> by Daan Wilson</small>
				</form>
			</div>
			<?php
		}else{
			?>
			<!-- Fixed navbar -->
			<nav class="navbar navbar-default navbar-fixed-top">
				<div class="container">
					<div class="navbar-header">
						<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
							<span class="sr-only">Toggle navigation</span>
							<span class="icon-bar"></span>
							<span class="icon-bar"></span>
							<span class="icon-bar"></span>
						</button>
						<a class="navbar-brand" href=""><?php echo APP_NAME;?></a>
					</div>
					<div id="navbar" class="navbar-collapse collapse">
						<ul class="nav navbar-nav">
							<li class="<?=((!_G('m') && !_G('a')) ? 'active' : '');?>"><a href="<?=lnk::link();?>">Show databases</a></li>
							<li class="<?=(_G('m') ? 'active' : '');?>">
								<a href="#"  class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">MySQL<span class="caret"></span></a>
								<ul class="dropdown-menu" role="menu">
									<li><a href="<?=lnk::link();?>&m=process">Show processlist</a></li>
									<li><a href="<?=lnk::link();?>&m=stats">Show statistics</a></li>
									<li><a href="<?=lnk::link();?>&m=vars">Show Configuration Variables</a></li>
								</ul>
							</li>
							<li class="<?=(_G('a')=='info' ? 'active' : '');?>"><a href="<?=lnk::database();?>&a=info">PHP info</a></li>
							<li class="<?=(_G('a')=='about' ? 'active' : '');?>"><a href="<?=lnk::database();?>&a=about">About</a></li>								
						</ul>
						<ul class="nav navbar-nav navbar-right">
							<li><a href="<?=$_SERVER['PHP_SELF'];?>" title="Logout">Welcome <strong><?=auth::get_user();?></strong>&nbsp;<span class="glyphicon glyphicon-log-out"></span></a></li> 
						</ul>
					</div><!--/.nav-collapse -->
				</div>
			</nav>
			<div class="container-fluid">
				<div class="col-sm-3 col-md-2 sidebar hidden-xs">
					<?php
					$dbs=db()->query('SHOW DATABASES');
					if(db()->getDB()){
						?>
						<form method="GET"><input type="hidden" name="xss" value="<?=guard::get_xss();?>" />
							<select name="db" onchange="form.submit();" class="form-control"><option value=""> - select database - </option>
								<?php foreach($dbs as $db){?>
									<option value="<?=$db['Database'];?>" <?=($db['Database']==db()->getDB() ? 'selected' : '');?>><?=$db['Database'];?></option>
								<?php }?>
							</select>
						</form>
						<input value="<?=_C('t_search');?>" placeholder="search table" id="t_search" class="form-control"/>
						<ul class="nav nav-sidebar" id="tables">
							<?php
							$tbs=db()->query('SHOW TABLES');
							foreach($tbs as $tb){
								reset($tb);
								$table=$tb[key($tb)];
								echo '<li class="'.($table==db()->getTable() ? 'open' : '').'"><a href="'.lnk::table($table).'" title="'.$table.'" class="ellipsis">'.$table.'</a></li>';
							}
							?>
						</ul>
					<?php }else{?>
						<input value="<?=_C('db_search');?>" placeholder="search database" id="db_search" class="form-control"/>
						<ul class="nav nav-sidebar" id="databases"> 
							<?php
							foreach($dbs as $db){
								echo '<li class="'.($db['Database']==db()->getDB() ? 'open' : '').'"><a href="'.lnk::database($db['Database']).'" title="'.$db['Database'].'" class="ellipsis">'.$db['Database'].'</a></li>';
							}
							?>
						</ul>
					<?php }?>
				</div>
				<div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main" >
					<?php echo HTML::get();?>
				</div>
			</div>
			<?php
		}
		?>
		<!-- Bootstrap core JavaScript -->
		<!-- Placed at the end of the document so the pages load faster -->
		<script src="<?=JQUERY;?>"></script>
		<script src="<?=BOOTSTRAP_JS;?>"></script>
		<!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
		<?php /* <script src="../../assets/js/ie10-viewport-bug-workaround.js"></script> */?>
		<script type="text/javascript">
						var delay = (function() {
							var timer = 0;
							return function(callback, ms) {
								clearTimeout(timer);
								timer = setTimeout(callback, ms);
							};
						})();
						SideBarHeight();
						$(window).resize(function() {
							SideBarHeight();
						});
						qap = 0;
						$('[data-toggle="tooltip"]').tooltip();
						if($(".export-tables").length>0){
							calcBytes();
							$(".export-tables li input[type=checkbox]").on("change",function(){
								var l = $(this).parent('label');
								if($(this).prop("checked")){
									l.addClass('bg-success');
									l.removeClass('text-muted');
								}else{
									l.addClass('text-muted');
									l.removeClass('bg-success');
								}
								calcBytes();
							});
						}
						$("#querytextarea").on("click keyup", function() {
							qap = $(this).prop("selectionStart");
						});
						$(".table-extra-tabs a").on("click", function() {
							$(".table-extra-tabs a").removeClass('active');
							$(this).addClass('active');
							document.cookie = "t_tabs=" + $(this).attr('href') + "; path=/";
						});
						$(".select-column").on("dblclick", function() {
							var query = $("#querytextarea").val();
							var kol = '`' + $(this).text() + '`';
							var prf = query.substr((qap - 1), 1);
							if (qap>0 && prf !== ' ' && prf !== ',') {
								kol = "," + kol;
							}
							query = query.substr(0, qap) + kol + query.substr(qap);
							qap = qap + kol.length;
							$("#querytextarea").val(query);
							$("#querytextarea").prop("selectionStart", qap);
							$("#querytextarea").prop("selectionEnd", qap);
							$("#querytextarea").focus();
						});
						$(".select-query").on("dblclick", function() {
							$("#querytextarea").val($(this).text());
							$("#querytextarea").focus();
						});
						$("#searchoptions").on("click", function(e) {
							$(this).hasClass('open') && e.stopPropagation();
						});
						$(".pagination li a").on("click", function(e) {
							if (!$(this).hasClass('disabled') && !$(this).hasClass('active') && $(this).data('page') > -1) {
								$("#query_page").val($(this).data('page'));
								$("#query_exec").trigger("click");
							}
						});
						function setOrderBy(kol) {
							if ($("#query_order_by").data('value') == kol) {
								kol = kol + " DESC";
							}
							$("#query_order_by").val(kol);
							$("#query_exec").trigger("click");
						}
						function calcBytes(){
							var b=0;
							$(".export-tables li input[type=checkbox]:checked").each(function(){
								b+= $(this).parent('label').data('bytes');
							});
							$("#byteslabel").text(bytesToSize(b));
						}
						if ($("#t_search").length > 0) {
							var val = $("#t_search").val();
							LstSrch(val, '#tables');
							$("#t_search").on("keyup", function(e) {
								val = $(this).val();
								document.cookie = "t_search=" + val + "; path=/";
								delay(LstSrch(val, '#tables'), 300);
							});
						}
						if ($("#db_search").length > 0) {
							var val = $("#db_search").val();
							LstSrch(val, "#databases");
							$("#db_search").on("keyup", function(e) {
								val = $(this).val();
								document.cookie = "db_search=" + val + "; path=/";
								delay(LstSrch(val, "#databases"), 300);
							});
						}
						function LstSrch(val, ulID) {
							$(ulID + " li").each(function(i, e) {
								var db = $(e).find('a').text();
								if (db.indexOf(val) >= 0) {
									$(this).show();
								} else {
									$(this).hide();
								}
							});
						}
						function SideBarHeight() {
							$(".sidebar").height(($(window).height() - 60));
						}
						function bytesToSize(bytes) {
							if(bytes == 0) return '0 Byte';
							var k = 1024;
							var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
							var i = Math.floor(Math.log(bytes) / Math.log(k));
							return (bytes / Math.pow(k, i)).toPrecision(3) + ' ' + sizes[i];
						 }
		</script>
	</body>
</html>
<?php
class msg{
	const typeInfo='info';
	const typeError='danger';
	protected static $msg=array();
	protected static function setMsg($string,$type){
		if(strlen($string)>0){
			self::$msg[$type][]=$string;
		}
	}
	static function info($string){
		self::setMsg($string,self::typeInfo);
	}
	static function error($string){
		self::setMsg($string,self::typeError);
	}
	static function get(){
		$html=null;
		foreach(self::$msg as $type=> $msgs){
			$html.='<div class="alert alert-'.$type.'">'.implode("<hr />",$msgs).'</div>';
		}
		return $html;
	}
}
class guard{
	protected static $xss;
	/**
	 * Generate a random xss string
	 * @return string
	 */
	static function get_xss(){
		if(self::$xss===null){
			self::$xss = substr(str_shuffle(md5(time())),0,16);
			$_SESSION[APP_NAME]['xss'] = self::$xss;
		}
		return self::$xss;
	}
	static function last_xss(){
		return (isset($_SESSION[APP_NAME]['xss'])?$_SESSION[APP_NAME]['xss']:false);
	}
	static function refreshed(){
		return (isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] === 'max-age=0');
	}
	/**
	 * Check if de present xss value match the known value
	 * @return boolean
	 */
	static function check_xss(){
		if(_R('xss')!=false && (self::last_xss()==_R('xss') || self::refreshed())){
			return true;
		}
		return false;
	}
	static function lockfile(){
		if(defined('INLOG_LOCK_FILE') && INLOG_LOCK_FILE!=''){
			if(!is_file(INLOG_LOCK_FILE)){
				file_put_contents(INLOG_LOCK_FILE,0);
			}
			return INLOG_LOCK_FILE;
		}
		return false;
	}
	static function check_lock(){
		if(self::lockfile()){
			$mt =filemtime(self::lockfile());
			$locktime = 60*2; //5 minutes
			if(($mt+$locktime)>time()){
				if((int)file_get_contents(self::lockfile())>5){
					msg::error("To many wrong logins, wait ".(($mt+$locktime)-time())." seconds before retry");
					return false;
				}
			}else{
				self::update_lock(true);
			}
		}
		return true;
	}
	static function update_lock($reset=false){
		if(self::lockfile()){
			$c = ($reset ? 0 : file_get_contents(self::lockfile()));
			file_put_contents(self::lockfile(),($c+1));
		}
	}
}
class lnk{
	static function link(){
		return $_SERVER['PHP_SELF']."?xss=".guard::get_xss();
	}
	static function database($db=null){
		return self::link()."&db=".($db==null ? DB()->getDB() : $db);
	}
	static function table($t=null){
		return self::database()."&table=".($t==null ? DB()->getTable() : $t);
	}
	static function record($id=null){
		return self::table()."&record=".urlencode($id==null ? DB()->getRecordID() : $id);
	}
}
/**
 * Database authenticate class
 */
class auth{
	protected static $DB;
	protected static $connected=false;
	/**
	 * @return database
	 */
	static function DB(){
		if(self::$DB===null){
			self::$DB=new database();
		}
		return self::$DB;
	}
	static function autoConnect($user,$pass,$host){
		$_SESSION[APP_NAME]['db']=array();
		$_SESSION[APP_NAME]['db']['user']=$user;
		$_SESSION[APP_NAME]['db']['pass']=$pass;
		$_SESSION[APP_NAME]['db']['host']=$host;
		if(!(_R('xss'))){
			$_REQUEST['xss'] = guard::get_xss();
		}
	}
	static function connect($user,$pass,$host=null){
		$host=($host!='' ? $host : DB_HOST);
		self::$connected=self::DB()->connect($user,$pass,$host);
		if(!self::$connected){
			self::disconnect();
			guard::update_lock();
			sleep(3); //small protection against brute force
		}else{
			self::autoConnect($user,$pass,$host);
		}
		return self::$connected;
	}
	static function disconnect(){
		$_SESSION[APP_NAME]=array();
		session_regenerate_id(true);
		self::$connected=false;
	}
	static function check(){
		if(self::$connected==false && isset($_SESSION[APP_NAME]['db']['user'])){
			self::connect($_SESSION[APP_NAME]['db']['user'],$_SESSION[APP_NAME]['db']['pass'],$_SESSION[APP_NAME]['db']['host']);
		}
		return (self::$connected && guard::check_xss());
	}
	static function get_user(){
		return $_SESSION[APP_NAME]['db']['user'];
	}
}
/**
 * Database management
 */
class database{
	static $conn;
	protected $totCount;
	function connect($user=null,$pass=null,$host=null){
		if(self::$conn===null){
			try{
				$db=(DB()->getDB() ? 'dbname='.DB()->getDB().';' : '');
				self::$conn=new PDO('mysql:host='.$host.';'.$db.'charset='.DB_CHARSET,$user,$pass,array(
				  PDO::ATTR_PERSISTENT=>true,
				));
				self::$conn->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
			}catch(PDOException $e){
				msg::error("Error!: ".$e->getMessage());
				return false;
			}
		}
		return self::$conn;
	}
	function query($q){
		try{
			$sth=self::$conn->prepare($q);
			$sth->execute();
			if(preg_match("/^select|show|explain|desc/i",$q)){
				$result=array();
				while($row=$sth->fetch(PDO::FETCH_ASSOC)){
					$result[]=$row;
				}
				if(preg_match("/\sfrom\s([^]]*)\slimit\s/i",$q,$r)){
					$org = $q;
					$q = "SELECT COUNT(*) ".substr(trim($r[0]),0,-5);
					$sth=self::$conn->prepare($q);
					$sth->execute();

					Pagination::$tcount=$sth->fetchColumn();
					$p = extract_query($org);
					if(isset($p['limit'])){
						$l = get_limit($p['limit']);
						Pagination::$limit=$l[1];
						Pagination::$page=($l[0]>0 ? ceil($l[0]/$l[1]):0);
					}
				}
				return $result;
			}elseif(preg_match("/^insert/i",$q)){
				return self::$conn->lastInsertId();
			}elseif(preg_match("/^update|delete/i",$q)){
				return $sth->rowCount();
			}
		}catch(PDOException $e){
			msg::error("Error!: ".$e->getMessage());
			msg::error("Query: ".$q);
		}
	}
	function getVersion(){
		$v=$this->query("SELECT VERSION() as mysql_version");
		return $v[0]['mysql_version'];
	}
	function getDB(){
		if(defined("DB_DATABASE") && DB_DATABASE!=''){
			return DB_DATABASE;
		}
		return _R('db');
	}
	function getTable(){
		return _R('table');
	}
	function getRecordId(){
		return _R('record');
	}
	function describeTable($table=null){
		$table=($table=='' ? $this->getTable() : $table);
		$t=$this->query("SHOW FULL COLUMNS FROM `".$table."`");
		$pk=array();
		$idx=array();
		$cmts=array();
		foreach((array)$t as $kol){
			if($kol['Key']=='PRI'){
				$pk[]=$kol['Field'];
			}elseif($kol['Key']=='MUL'){
				$idx[]=$kol['Field'];
			}
			if($kol['Comment']!=''){
				$cmts[$kol['Field']]=$kol['Comment'];
			}
		}
		return array("tname"=>$table,"t"=>$t,'pk'=>$pk,'indexes'=>$idx,'comments'=>$cmts);
	}
	function getPkLink($pk,$record){
		if(is_array($pk)){
			$pk_link = array();
			foreach($pk as $k){
				if(key_exists($k,$record)){
					$pk_link[$k]=$record[$k];
				}else{
					return false;
				}
			}
			return serialize($pk_link);
		}
		return false;
	}
	function getPkWhere($pk,$record){
		$record = unserialize($record);
		$where = array();
		foreach($pk as $k){
			$where[] = "`".$k."`='".esc($record[$k])."'";
		}
		return implode(" AND ",$where);
		
	}
	function saveRecord(){
		if(_P('save_record') && $this->getTable()!=''){
			$t=$this->describeTable();
			foreach($t['t'] as $kol){
				$k=$kol['Field'];
				if(in_array($k,$t['pk']) and count($t['pk'])==1)
					continue;

				$v=_P($k);
				$fields[]="`".$k."`='".esc($v)."'";
			}
			if(_R('record')!=''){
				$query='UPDATE `'.$t['tname'].'` SET '.implode(", ",$fields)." WHERE ".$this->getPkWhere($t['pk'],_R('record'));
				db()->query($query);
			}else{
				$query='INSERT INTO `'.$t['tname'].'` SET '.implode(", ",$fields);
				$id=db()->query($query);
				$_REQUEST['record']=$_POST['record']=$_GET['record']=$id;
			}
			msg::info("Query executed : ".$query);
		}
	}
	function removeRecord(){
		if(_P('remove_record') && $this->getTable()!='' && _R('record')!=''){
			$t=$this->describeTable();
			$query="DELETE FROM `".$t['tname']."`  WHERE ".$this->getPkWhere($t['pk'],_R('record'));
			db()->query($query);
			$_REQUEST['record']=$_POST['record']=$_GET['record']=false;
			msg::error("Query executed : ".$query);
		}
	}
}
class query{
	protected static $q=array();
	protected static $cfrm=false;
	function getConfirm(){
		if(self::$cfrm && !empty(self::$q)){
			$s='Are you sure to run the next queries ?<br/>';
			$s.= trim(implode(";<br/>",self::$q),';').';';
			$s.= '<form method="post" action="'.lnk::table().'">';
			$s.= '<input type="hidden" name="query" value="'.implode(";<br/>",self::$q).'">';
			$s.= '<a class="btn btn-default btn-sm" href="'.$_SERVER['HTTP_REFERER'].'">NO</a>&nbsp;';
			$s.= '<button class="btn btn-primary btn-sm" name="exec" value="'._P('exec').'">YES</button>';
			$s.= '</form>';
			msg::error($s);
		}
		return self::$cfrm;
	}
	function setConfirm($bool=true){
		self::$cfrm=$bool;
	}
	function add($q){
		$q=$this->build($q);
		self::$q[]=$q;
		$this->saveQuery($q);
	}
	function present(){
		return (count(self::$q)>0);
	}
	function buildSearch($srch,$kols=array()){
		$t=db()->describeTable();
		$w=array();
		foreach($t['t'] as $k){
			if(!is_array($kols) || in_array($k['Field'],$kols)){
				$w[]="`".$k['Field']."` LIKE '".$srch."'";
			}
		}
		if(count($w)>0){
			return "WHERE ".implode(" OR ",$w);
		}
	}
	function build($q=null){
		if(_P('exec') && _P('query')!=''){
			$q=_P('query');
			if(_P('exec')=='explain'){
				if(!preg_match("/^explain/i",$q)){
					$q='EXPLAIN '.$q;
					return $q;
				}
			}
			if(_P('srch')!=''){
				$parts = extract_query($q);
				$parts['where'] = $this->buildSearch(_P('srch'),_P('column'));
				$q=build_query($parts);
			}
			if(_P('orderby')!='' || _P('page')!==false){
				$parts = extract_query($q);
				if(_P('orderby')!=''){
					$parts['order by']="ORDER BY "._P('orderby');
				}
				if(_P('page')!==''){
					$limit = get_limit($parts['limit']);
					Pagination::$page=(int)_P('page');
					Pagination::$limit=(int)$limit[1];
					$parts['limit']="LIMIT ".((int)_P('page')*(int)$limit[1]).",".$limit[1];
				}
				$q=build_query($parts);
			}
		}
		if(empty($q)){
			$q=sprintf('SELECT * FROM `%s` LIMIT 0,'.MAX_ROWS_PER_PAGE,db()->getTable());
		}
		return $q;
	}
	function get(){
		return (array)self::$q;
	}
	function saveQuery($q){
		if(trim($q)!=''){
			unset($_SESSION[APP_NAME]['queries'][md5($q)]);
			$_SESSION[APP_NAME]['queries'][md5($q)]=array('q'=>$q,'d'=>time());
		}
	}
	function getSavedQueries(){
		return array_reverse((array)$_SESSION[APP_NAME]['queries']);
	}
}
class Pagination{
	static $limit=MAX_ROWS_PER_PAGE;
	static $tcount=0;
	static $page=0;
}
class HTML{
	static function get(){
		$content='';
		if(_G('a')=='info'){
			$content=self::getInfo();
		}elseif(_G('a')=='about'){
			$content=self::getAbout();
		}elseif(_R('a')=='IMPORT'){
			$content=self::getImport();
		}elseif(db()->getRecordId()!==false){
			$content=self::getEditForm();
		}elseif(q()->present()){
			if(!q()->getConfirm()){
				foreach(q()->get() as $q){
					$content.= '<div class="row"><div class="col-md-12">'.self::getTableData($q).'</div></div>';
					if(preg_match('/^drop table/i',$q)){
						header('Location: '.lnk::database());die;
					}
				}
			}
		}elseif(_P('exec')=="EXPORT" && is_array(_P('tables'))){
			$content=self::getExport();
		}elseif(!db()->getDB()){
			$content=self::getDatabases();
		}elseif(!db()->getTable()){
			$content=self::getTables();
		}
		$html='<div class="panel panel-primary">';
		$html.= '<div class="panel-heading">';
		if(db()->getDB()!=''){
			$html.='<a href="'.lnk::database().'&a=IMPORT" class="btn btn-default btn-xs pull-right">Import</a>';
		}
		$html.= '<h3 class="panel-title">';
		if(db()->getDB()!=''){
			$html.= 'Database : <a href="'.lnk::database().'">'.db()->getDB().'</a>';
		}
		if(db()->getTable()){
			$html.= '&nbsp;<small class="glyphicon glyphicon-triangle-right" aria-hidden="true"></small>table : <a href="'.lnk::table().'">'.DB()->getTable().'</a>';
		}
		$html.= '</h3>';

		$html.= '</div>';
		$html.= '<div class="panel-body">';
		$html.=msg::get();
		$html.=$content;
		$html.= '</div>';
		$html.= '</div>';
		return $html;
	}
	static function getInfo(){
		return '<iframe frameborder=0 scrolling=0 allowfullscreen="true" width="100%" height="500px" src="'.$_SERVER['PHP_SELF'].'?phpinfo=true"></iframe>';
	}
	static function getAbout(){
		$html='<div class="well">';
		$html.= '<div>Developed by : Daan Wilson</div>';
		$html.= '<div>Licence : <a href="'.(is_file('LICENSE')?'LICENSE':'http://en.wikipedia.org/wiki/MIT_License').'" target="_blank">MIT licence</a></div>';
		$html.= '<div>Application name : '.APP_NAME.'</div>';
		$html.= '<div>Application version : '.APP_VERSION.'</div>';
		$html.= '<div>Application last update : '.APP_VERSION_RD.'</div>';
		$html.= '<hr/>';
		$html.= '<div>Your MySQL version: '.db()->getVersion().'</div>';
		$html.= '<div>Your PHP version: '.phpversion().'</div>';
		$html.= '<div>Your Apache version: '.$_SERVER['SERVER_SOFTWARE'].' </div>';
		return $html;
	}
	static function getDatabases(){
		$dbs=db()->query('SHOW DATABASES');
		$html='<div class="table-responsive">';
		$html.= '<table class="table table-hover table-striped table-condensed">';
		$html.= '<thead><tr><th>Database</th></tr></thead>';
		$html.= '<tbody>';
		foreach($dbs as $db){
			$link=lnk::database($db['Database']);
			$html.='<tr><td><span><a href="'.$link.'" title="'.$db['Database'].'">'.$db['Database'].'</a></span></td></tr>';
		}
		$html.= '</tbody>';
		$html.= '</table>';
		$html.= '</div>';
		return $html;
	}
	static function getTableActions(){
		$actions=array();
		$actions[""]=" - Table action - ";
		$actions["EXPORT"]="Export table(s)";
		$actions["EXPLAIN"]="Explain table(s)";
		$actions["SHOW CREATE TABLE"]="Show create of table(s)";
		$actions["SHOW INDEXES FROM"]="Show indexes of table(s)";
		$actions["OPTIMIZE TABLE"]="Optimize table(s)";
		$actions["REPAIR TABLE"]="Repair table(s)";
		$actions["TRUNCATE"]="TRUNCATE table(s)";
		$actions["DROP TABLE"]="DROP table(s)";
		$html='<select name="exec" onchange="form.submit();" class="form-control input-sm">';
		foreach($actions as $a=> $d){
			$html.='<option value="'.$a.'">'.$d.'</option>';
		}
		$html.='</select>';
		return $html;
	}
	static function getTables(){
		$display=array('Name','Rows','Size','Create_time','Update_time','Engine','Collation');
		$tables=db()->query('SHOW TABLE STATUS');

		//print_r($tables);
		$html='<form action="'.lnk::database().'" method="post" class="form-inline">';
		$html.='<span class="glyphicon glyphicon-arrow-down" aria-hidden="true"></span>&nbsp;';
		$html.=self::getTableActions();
		$html.= '<div class="table-responsive">';
		$html.= '<table class="table table-hover table-striped table-condensed">';
		$html.= '<thead><tr><th>';
		$html.= '<input type="checkbox" name="" onchange="$(\'tbody.tables tr input[type=checkbox]\').prop(\'checked\',$(this).prop(\'checked\'));" />&nbsp;';
		$html.= '</th>';
		foreach($display as $d){
			$html.= '<th>'.$d.'</th>';
		}
		$html.= '</tr></thead>';
		$html.= '<tbody class="tables">';
		foreach($tables as $table){
			$link=lnk::table($table['Name']);
			$html.='<tr><td>';
			$html.='<input type="checkbox" name="tables['.$table['Name'].']" value="'.$table['Name'].'"/>&nbsp;';
			$html.='</td>';
			foreach($display as $d){
				if($d=='Size'){
					$v=fBytes($table['Data_length']+$table['Index_length']);
				}elseif($d=='Rows'){
					$v=number_format($table['Rows']);
				}else{
					$v=$table[$d];
				}
				$html.='<td><span><a href="'.$link.'" title="'.$table['Name'].'">'.$v.'</a></span></td>';
			}
			$html.='</tr>';
		}
		$html.= '</tbody>';
		$html.= '</table>';
		$html.= '</div>';
		$html.= '</form>';
		return $html;
	}
	static function getForm($query){
		$html='';
		if(!empty($query)){
			$html.='<div class="row"><div class="col-md-8">';
			$html.='<form method="POST" action="'.lnk::table().'" class="form-horizontal" id="query_form">';
			$html.='<div class="row"><div class="col-md-12">';
			$html.='<textarea name="query" class="form-control" rows="3" id="querytextarea">'.$query.'</textarea>';
			$html.='<input type="hidden" name="orderby" data-value="'._P('orderby').'" id="query_order_by" />';
			$html.='<input type="hidden" name="page" id="query_page" />';
			$html.='</div></div>';
			$html.='<div class="row"><div class="col-md-8">';
			$html.='<button type="submit" name="exec" value="run" class="btn btn-primary btn-sm" id="query_exec">Run</button>&nbsp;';
			if(db()->getTable()){
				$html.='<button type="submit" name="exec" value="explain" class="btn btn-sm">Explain query</button>&nbsp;';
				$html.='<button type="button" value="reset" class="btn btn-warning btn-sm" onclick="window.location=\''.lnk::table().'\'">Reset</button>';
			}else{
				$html.='<button type="button" value="cancel" class="btn btn-warning btn-sm" onclick="window.location=\''.@$_SERVER['HTTP_REFERER'].'\'">Cancel</button>';
			}
			$html.='</div></form>';
			if(db()->getTable()){
				$html.='<div class="col-md-4"><form method="POST" action="'.lnk::table().'">';
				$html.='<input type="hidden" name="tables['.db()->getTable().']" value="'.db()->getTable().'" />';
				$html.=self::getTableActions();
				$html.='</form></div>';
			}
			$html.='</div></div>';


			if(db()->getTable()){
				$html.='<div class="col-md-4"><div class="row hidden-xs hidden-sm"><div class="col-md-4">';
				$html.='<div class="btn-group-vertical table-extra-tabs" role="group" aria-label="Vertical button group">'
					.'<a type="button" class="btn btn-default btn-xs '.((_C('t_tabs')=='#columns' || _C('t_tabs')=='') ? 'active' : '').'" data-toggle="tab" role="tab" href="#columns">Columns</a>'
					.'<a type="button" class="btn btn-default btn-xs '.((_C('t_tabs')=='#queries') ? 'active' : '').'" data-toggle="tab" role="tab" href="#queries">Queries</a>'
					.'</div></div>';
				$html.='<div class="table-extras col-md-8 bg-info">';
				$t=db()->describeTable();
				$html.='<div class="tab-content">';
				$html.='<div role="tabpanel" class="tab-pane '.((_C('t_tabs')=='#columns' || _C('t_tabs')=='') ? 'active' : '').'" id="columns">';
				$html.='<ul class="list-unstyled">';
				foreach($t['t'] as $c){
					$html.='<li class="select-column ellipsis selection">'.$c['Field'].'</li>';
				}
				$html.='</ul></div>';
				$html.='<div role="tabpanel" class="tab-pane '.((_C('t_tabs')=='#queries') ? 'active' : '').'" id="queries"><ul class="list-unstyled">';
				$qs=q()->getSavedQueries();
				foreach($qs as $q){
					$html.='<li class="select-query ellipsis selection" title="'.$q['q'].'">'.$q['q'].'</li>';
				}
				$html.='</ul></div>';
				$html.='</div></div>';
			}
			$html.='</div></div>';
		}
		return $html;
	}
	static function getTableData($query){
		$records=db()->query($query);
		$html='<div>';
		$html.= self::getForm($query);
		$html.= '</div>';

		if(db()->getTable()){
			$html.= '<div class="row-fluid"><div class="col-md-12"><br/>';
			$html.= '<ul role="tablist" class="nav nav-tabs">';
			$html.= '<li role="presentation" class="'.(_G('exec')=='' ? 'active' : '').'"><a aria-expanded="true" aria-controls="data" data-toggle="tab-link" role="tab" href="'.lnk::table().'">Data</a></li>';
			$html.= '<li role="presentation" class="'.(_G('exec')=='EXPLAIN' ? 'active' : '').'"><a aria-controls="structure" data-toggle="tab-link" role="tab" href="'.lnk::table().'&exec=EXPLAIN">Structure</a></li>';
			$html.= '<li role="presentation" class="'.(_G('exec')=='SHOW INDEXES FROM' ? 'active' : '').'"><a aria-controls="structure" data-toggle="tab-link" role="tab" href="'.lnk::table().'&exec=SHOW INDEXES FROM">Show indexes</a></li>';
			$html.= '<li role="presentation" class="'.(_G('exec')=='SHOW CREATE TABLE' ? 'active' : '').'"><a aria-controls="structure" data-toggle="tab-link" role="tab" href="'.lnk::table().'&exec=SHOW CREATE TABLE">Show create</a></li>';
			$html.= '</ul></div></div>';
		}

		$html.= '<div class="row-fluid"><div class="col-md-12"><div class="tab-content"><br />';
		if((_R('exec')=='' || _R("exec")=='run') && _R('m')=='' && db()->getTable()){
			$t=db()->describeTable();
			$pk=$t['pk'];
			$idx=$t['indexes'];
			$cmts=$t['comments'];
			$html.='<div class="row">';
			$html.='<div class="col-md-6">'.self::getPagination().'</div>';
			$html.='<div class="col-md-6 text-right">'.self::getSearchInput($query).'</div>';
			$html.='</div>';
		}
		$pk_link = false;
		if(is_array($records) && count($records)>0){
			$html.= '<div class="table-responsive"><table class="table table-hover table-striped table-condensed">';
			$html.= '<thead><tr>';
			foreach($records[0] as $kol=> $value){
				$html.= '<th title="'.$kol.'"><div class="ellipsis">';
				if(_G('exec')=='' && _G('m')==''){
					$html.='<a href="javascript:setOrderBy(\'`'.$kol.'`\')">'.$kol.'</a>';
					if(isset($pk) && is_array($pk) and in_array($kol,$pk)){
						$html.='<span class="glyphicon glyphicon-star" title="Primary key"></span>';
					}elseif(isset($idx) && is_array($idx) && in_array($kol,$idx)){
						$html.='<span class="glyphicon glyphicon-flash" title="Index"></span>';
					}
					if(isset($cmts) && is_array($cmts) && array_key_exists($kol,$cmts)){
						$html.='<span class="glyphicon glyphicon-info-sign small" title="'.$cmts[$kol].'" data-toggle="tooltip"></span>';
					}
				}else{
					$html.=$kol;
				}
				$html.= '</div></th>';
			}
			$html.= '</tr></thead>';
			$html.= '<tbody>';
			foreach($records as $record){
				$html.='<tr>';
				$pk_link = (isset($pk)?db()->getPkLink($pk,$record):false);
				foreach($record as $kol=> $value){
					$v=hs($value);
					if(_R('exec')=='SHOW CREATE TABLE' && $kol=='Create Table'){
						$v='<pre>'.$v.'</pre>';
					}elseif(isset($pk) && is_array($pk) && in_array($kol,$pk) && $pk_link!=false){
						$v='<a href="'.lnk::table().'&record='.urlencode($pk_link).'" title="'.$v.'">'.$v.'</a>';
					}else{
						$v='<span title="'.$v.'" class="ellipsis">'.$v.'</span>';
					}
					$html.='<td>'.$v.'</td>';
				}
				$html.='</tr>';
			}
			$html.= '</tbody>';
			$html.= '</table></div>';
		}else{
			$html.= '<div class="alert alert-warning">No records found</div>';
		}
		if(isset($pk) && is_array($pk) && $pk_link!=false){
			$html.= '<a class="btn btn-primary" href="'.lnk::record().'">New record</a></div></div></div>';
		}
		return $html;
	}
	static function getPagination(){
		$html='';
		$c=(int)Pagination::$page;
		if(Pagination::$tcount>Pagination::$limit){
			$pages=ceil(Pagination::$tcount/Pagination::$limit);
			$start=0;
			$end=$pages;
			if($pages>10){
				$start=max(0,$c-5);
				$end=$start+10;
				if($end>$pages){
					$end=$pages;
					$start=$end-10;
				}
			}
			$html='<ul class="pagination pagination-sm" style="margin:0;">';
			$html.='<li class="'.($c>0 ? '' : 'disabled').'"><a href="#" data-page="'.($c-1).'">&laquo;</a></li>';
			for($i=$start; $i<$end; $i ++){
				$html.='<li class="'.($c==$i ? 'active' : '').'"><a href="#" data-page="'.$i.'">'.($i+1).'</a></li>';
			}
			$html.='<li class="'.($c<($pages-1) ? '' : 'disabled').'"><a href="#" data-page="'.($c<($pages-1) ? ($c+1) : $c).'">&raquo;</a></li>';
			$html.='</ul>';
		}
		$f = min((($c*Pagination::$limit)+1),Pagination::$tcount);
		$t = min(($c+1)*Pagination::$limit,Pagination::$tcount);
		$tot = Pagination::$tcount;
		$html.='<div class="small text-muted"><i>Record '.number_format($f).' - '.number_format($t).' of '.number_format($tot).' records</i></div>';
		return $html;
	}
	static function getSearchInput($query){
		$t=db()->describeTable();
		$s=(array)_P('column');
		$html='<form class="navbar-form" role="search" action="'.lnk::table().'" method="POST" style="margin:0;">';
		$html.= '<input type="hidden" name="query" value="'.$query.'">';
		$html.= '<div class="input-group">';
		$html.= '<div class="input-group-btn" id="searchoptions">';
		$html.= '<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-expanded="false">Options <span class="caret"></span></button>';
		$html.= '<ul class="dropdown-menu" role="menu">';
		$html.= '<li><a>Search specific columns</a></li>';
		$html.= '<li class="divider"></li>';
		foreach((array)$t['t'] as $k){
			$html.='<li><a class="checkbox"><label><input type="checkbox" name="column['.$k['Field'].']" value="'.$k['Field'].'" '.((_P('srch') && isset($s[$k['Field']])) ? 'checked' : '').'>&nbsp;'.$k['Field'].'</label></a></li>';
		}
		$html.='</ul>';
		$html.= '</div>';
		$html.= '<input type="search" class="form-control" placeholder="Search (use % wildcards)" name="srch" value="'._P('srch').'">';
		$html.= '<div class="input-group-btn">';
		$html.= '<button class="btn btn-default" type="submit" name="exec" value="run"><i class="glyphicon glyphicon-search"></i></button>';
		$html.= '</div>';
		$html.= '</div>';
		$html.= '</form>';
		return $html;
	}
	static function getEditForm(){
		$t=db()->describeTable();
		$record=array();
		if(isset($t['pk']) && _R('record')!=''){
			$record=db()->query("SELECT * FROM `".$t['tname']."` WHERE ".db()->getPkWhere($t['pk'],_R('record'))." LIMIT 1");
			$record=(array)$record[0];
		}

		$html='<form method="POST" action="'.lnk::record().'">';
		foreach($t['t'] as $kol){
			$type=$kol['Type'];
			$f=$kol['Field'];
			$k=$kol['Key'];
			$cm=$kol['Comment'];
			$v=@$record[$f];
			$i=array(0=>array('t'=>'text','n'=>$f,'v'=>$v,'c'=>'col-md-12'));
			if($k=='PRI' && count($t['pk'])==1){
				$i[0]['t']='pk';
			}elseif(stristr($type,'text')!==false){
				$i[0]['t']="textarea";
			}elseif(stristr($type,'varchar')!==false && (int)btwn_brackets($type)>100){
				$i[0]['t']="textarea";
			}elseif(stristr($type,'enum')!==false){
				$opts=explode(",",btwn_brackets($type));
				$opts=array_map(function($x){
					return trim($x,"'");
				},$opts);

				$i[0]['t']='radio';
				$i[0]['v']='';
				$radios=array();
				$c=0;
				foreach($opts as $o){
					$radios[$c]=$i[0];
					$radios[$c]['v']=$o;
					$radios[$c]['checked']=($v==$o ? 'checked' : '');
					$c ++;
				}
				$i=$radios;
			}
			$html.='<div class="form-group"><label for="input_'.$f.'">'.$f.'</label>';
			if($cm!='')
				$html.='&nbsp;<i class="glyphicon glyphicon-info-sign small" title="'.$cm.'" data-toggle="tooltip"></i>';
			$html.='<span class="small text-muted pull-right"><i>'.$type.'</i></span>';
			$html.='<div class="row">';
			foreach($i as $inp){
				$html.='<div class="'.$inp['c'].'">';
				if($inp['t']=='pk'){
					$html.=$inp['v'];
				}elseif($inp['t']=='textarea'){
					$html.='<textarea class="form-control" name="'.$inp['n'].'" rows="3">'.$inp['v'].'</textarea>';
				}elseif($inp['t']=='radio'){
					$html.='<label class="radio-inline"><input type="'.$inp['t'].'" name="'.$inp['n'].'" value="'.$inp['v'].'" '.$inp['checked'].'>'.$inp['v'].'</label>';
				}else{
					$html.='<input class="form-control" type="'.$inp['t'].'" name="'.$inp['n'].'" value="'.$inp['v'].'">';
				}
				$html.='</div>';
			}
			$html.= '</div></div>';
		}
		$html.= '<button type="submit" class="btn btn-primary" name="save_record" value="true">Save</button>';
		$html.= '<button type="button" class="btn btn-default" onclick="window.location=\''.lnk::table().'\'">Cancel</button>';
		if(_R('record')!='')
			$html.= '<button type="submit" class="btn btn-danger pull-right" name="remove_record" value="true" onclick="return confirm(\'Are you sure!\')">Delete</button>';
		$html.= '</form>';
		return $html;
	}
	static function getExport(){
		$html='<fieldset><legend>Export</legend>';
		if(is_array(_P('tables')) && count(_P('tables')>0)){
			$tables = db()->query('SHOW TABLE STATUS');
			$selected = _P('tables');
			$html.='<form method="POST" action="'.lnk::database().'">';
			$html.='<div class="row-fluid">';
			$html.='<div class="col-md-6"><strong>Table(s)</strong> - <i class="small" id="byteslabel"></i><div class="export-tables">';
			$html.='<ul class="list-unstyled">';
			foreach($tables as $t){
				$s='';$c='text-muted';
				if(in_array($t['Name'],$selected)){
					$s='checked';$c='bg-success';
				}
				$bytes=($t['Data_length']+$t['Index_length']); 
				$html.='<li><div class="checkbox"><label class="'.$c.'" data-bytes="'.$bytes.'"><input type="checkbox" name="tables['.$t['Name'].']" value="'.$t['Name'].'" '.$s.' />'.$t['Name'].' - <small><i>'.number_format($t['Rows'],0).' rows | '.fBytes($bytes).'</i></small></label></div></li>';
			}
			$html.='</ul></div></div>';
			$html.='<div class="col-md-6"><strong>Options</strong>';
			$html.='<div class="checkbox"><label><input type="checkbox" name="options[structure]" checked>Export structure</label></div>';
			$html.='<div class="checkbox"><label><input type="checkbox" name="options[data]" checked>Export data</label></div>';
			$html.='<hr/>';
			$html.='<div class="radio"><label><input type="radio" name="options[type]" value="sql" checked>Export as .sql file</label></div>';
			if(count($tables)==1){
				$html.='<div class="radio"><label><input type="radio" name="options[type]" value="csv_c">Export as .csv file (comma seperated, data only)</label></div>';
				$html.='<div class="radio"><label><input type="radio" name="options[type]" value="csv_sc">Export as .csv file (semicolon seperated,data only)</label></div>';
			}
			$html.='<hr/>';
			$html.='<div class="checkbox"><label><input type="checkbox" name="options[droptable]" >Add \'DROP TABLE IF EXISTS\'</label></div>';
			$html.='<div class="checkbox"><label><input type="checkbox" name="options[compress]" >Compress as .gz file</label></div>';
			$html.='<hr/>';
			$html.='<button class="btn btn-primary" type="submit" name="exec" value="EXPORT" value="true">Download</button>&nbsp;';
			$html.='<a href="'.lnk::database().'" class="btn btn-default">Cancel</a>';
			$html.='</div>';
		}
		$html.= '</fieldset>';
		return $html;
	}
	static function getImport(){
		$html='<fieldset><legend>Import</legend>';
		$html.='<form method="POST" action="'.lnk::database().'" enctype="multipart/form-data">';
		$html.='<div class="form-group">
				<label for="InputFile">Import file</label>
				<input type="file" id="InputFile" name="file" required >
				<p class="help-block">Import a .sql or a .gz file.</p>
			</div>';
		$html.='<button class="btn btn-primary" type="submit" name="a" value="IMPORT" value="true">Import</button>&nbsp;';
		$html.='<a href="'.lnk::database().'" class="btn btn-default">Cancel</a>';
		$html.='</div>';
		$html.= '</fieldset>';
		return $html;
	}
}
class Export{
	static $_EOL="\r\n";
	static $tmpFile;
	static function file(){
		if(self::$tmpFile===null){
			self::$tmpFile=tempnam(sys_get_temp_dir(),APP_NAME);
		}
		return self::$tmpFile;
	}
	static function appendFile($string){
		file_put_contents(self::file(),$string.self::$_EOL,FILE_APPEND|LOCK_EX);
	}
	static function getTableData($table){
		return db()->query(sprintf("SELECT * FROM `%s`",$table));
	}
	static function createCsv($table,$sep=";"){
		$r=self::getTableData($table);
		if(is_array($r) && count($r)>0){
			$cols=array_keys($r[0]);
			self::appendFile(implode($sep,$cols));
			foreach($r as $row){
				self::appendFile(implode($sep,$row));
			}
		}
	}
	static function getHeader(){
		$d=array();
		$d[]='-- '.APP_NAME;
		$d[]='-- version '.APP_VERSION;
		//$d[]= '-- http://www.phpmyadmin.net
		$d[]='--';
		$d[]='-- Machine: '.$_SESSION[APP_NAME]['db']['host'];
		$d[]='-- Generate time: '.date(DATE_RFC2822);
		$d[]='-- Serverversie: '.db()->getVersion();
		$d[]='-- PHP-versie: '.phpversion();
		$d[]='';
		$d[]='/*!40030 SET NAMES '.DB_CHARSET.' */';
		self::appendFile(implode(self::$_EOL,$d));
	}
	static function getTableDropQuery($table){
		self::appendFile(sprintf("DROP TABLE IF EXISTS `%s`;".self::$_EOL,$table));
	}
	static function getTableExportStructure($table){
		$c=db()->query(sprintf("SHOW CREATE TABLE `%s`",$table));
		$c=preg_replace("/\n\r|\r\n|\n|\r/",self::$_EOL,$c[0]['Create Table']);
		self::appendFile($c.";".self::$_EOL);
	}
	static function getTableExportData($table){
		$r=self::getTableData($table);
		if(is_array($r) && count($r)>0){
			$cols=array_keys($r[0]);
			$cols=implode(",",array_map(function($col){
					return "`".$col."`";
				},$cols));
			$head=sprintf("INSERT INTO `%s` (%s) VALUES",$table,$cols);

			$d=array();
			$parts=array_chunk($r,100); //do inset values for each 100 records.
			foreach($parts as $part){

				self::appendFile($head);
				$rows=array();
				foreach($part as $row){
					$rows[]="(".implode(",",array_map(function($data){
								return '"'.esc($data).'"';
							},$row)).")";
				}
				self::appendFile(implode(",".self::$_EOL,$rows).";");
			}
		}
	}
	static function run($tables=array(),$options=array()){
		if(is_array($tables) && count($tables)>0){
			if($options['type']=='sql'){
				self::getHeader();
			}
			foreach($tables as $table){
				if($options['type']=='sql'){
					if(isset($options['droptable'])){
						self::getTableDropQuery($table);
					}
					if(isset($options['structure'])){
						self::getTableExportStructure($table);
					}
					if(isset($options['data'])){
						self::getTableExportData($table);
					}
				}elseif($options['type']=='csv_c' || $options['type']=='csv_sc'){
					self::createCsv($table,($options['type']=='csv_c' ? ',' : ';'));
				}
			}
			self::output($options);
			die;
		}
	}
	static function output($options){
		$fname=db()->getDB()."-".date('Ymd');
		$fcont=file_get_contents(self::file());
		if($options['type']=='sql'){
			$fname.= ".sql";
			$ftype="text/plain";
		}elseif($options['type']=='csv_c' || $options['type']=='csv_sc'){
			$fname.= ".csv";
			$ftype="text/csv";
		}
		if(isset($options['compress'])){
			$fname.= ".gz";
			$ftype="application/x-gzip";
			$fcont=gzencode($fcont);
		}
		header("Content-type: ".$ftype);
		header("Content-Disposition: attachment; filename=\"".$fname."\"");
		echo $fcont;
		die;
	}
}
class Import{
	static function run($f){
		$file=$f['tmp_name'];
		$pi=pathinfo($f['name']);
		if($pi['extension']=='gz'){
			$tmpfile=tempnam(sys_get_temp_dir(),APP_NAME);
			if(($gz=gzopen($file,'rb')) && ($tf=fopen($tmpfile,'wb'))){
				while(!gzeof($gz)){
					if(fwrite($tf,gzread($gz,8192),8192)===FALSE){
						msg::error('Error during gz file extraction to tmp file');
						break;
					}
				}//extract to tmp file
				gzclose($gz);
				fclose($tf);
			}else{
				msg::error('Error opening gz file');
			}
			$file=$tmpfile;
		}
		self::importData($file);
	}
	static function importData($file){
		@set_time_limit(600);
		$comment=false;
		$f=fopen($file,'r');
		$q='';
		$c=0;
		while(!feof($f)){
			$l=trim(fgets($f));
			if(strlen($l)==0)
				continue;
			if(substr($l,0,2)=='--' || substr($l,0,1)=='#')
				continue;
			if(substr($l,0,2)=='/*')
				$comment=true;
			//if(substr($l,-2)=='*/' || substr($l,-3)=='*/;')
			if($comment){
				$comment=!(substr($l,-2)=='*/' || substr($l,-3)=='*/;');
			}else{
				$q.= $l;
				if(substr($l,-1)==';'){
					db()->query($q);
					$c++;
					$q='';
				}
			}
		}
		fclose($f);
		msg::info(sprintf("%s querys executed.",number_format($c,0)));
	}
}
/** shortcut functions * */
function _R($k){
	return (isset($_REQUEST[$k]) ? $_REQUEST[$k] : false);
}
function _P($k){
	return (isset($_POST[$k]) ? $_POST[$k] : false);
}
function _G($k){
	return (isset($_GET[$k]) ? $_GET[$k] : false);
}
function _C($k){
	return (isset($_COOKIE[$k]) ? $_COOKIE[$k] : false);
}
/**
 * @return database
 */
function db(){
	return auth::DB();
}
function q(){
	return new query();
}
function killmq($val){
	return is_array($val) ? array_map('killmq',$val) : stripslashes($val);
}
function fBytes($size,$prec=2){
	if($size>0){
		$b=log($size,1024);
		$s=array('b','kb','Mb','Gb','Tb');
		return round(pow(1024,$b-floor($b)),$prec).$s[floor($b)];
	}
	return $size;
}
function hs($s){
	return htmlspecialchars($s,ENT_COMPAT,CHARSET);
}
function esc($s){
	return addslashes($s);
}
function btwn_brackets($str){
	return (preg_match("/\(([^]]*)\)/i",$str,$r) ? $r[1] : false);
}
function extract_query($q){
	$r = array();
	$parts=array_reverse(array('where','group by','order by','limit','having'));
	foreach($parts as $part){
		$r[$part]=null;
		$p=strripos($q,$part.' ');
		if($p!==false){
			$r[$part]=trim(substr($q,$p));
			$q=rtrim(substr($q,0,$p));
		}
	}
	$r['base'] = $q;
	return array_reverse($r);
}
function build_query($parts){
	return implode(" ",array_map("trim",array_filter($parts)));
}
function get_limit($str){
	$l = array(0=>0,1=>MAX_ROWS_PER_PAGE);//limit
	if($str!='' && preg_match_all('/\d+/',$str,$m)){
		if(count($m[0])>1){
			$l=$m[0];
		}else{
			$l[1]=$m[0][0];
		}
	}
	return $l;
}
