<html>
	<head>
		<title>Serial Console</title>
	</head>
	<body>
		<script>
			PVE_vnc_console_event = function(appletid, action, err) {
				
			};
		</script>
		<?php
		$applet='<APPLET id=\'pveKVMConsole-1018-vncapp\' CODE=\'com.tigervnc.vncviewer.VncViewer\' ARCHIVE=\'VncViewer.jar\' WIDTH=100% HEIGHT=100%>
				<param value=\''.$_GET['0'].'\' name=\'host\'>
				<param value=\''.$_GET['1'].'\' name=\'PVECert\'>
				<param value=\''.$_GET['2'].'\' name=\'Port\'>
				<param name=\'USERNAME\' value=\''.$_GET['3'].'\'>
				<param name=\'PASSWORD\' value=\''.$_GET['4'].'\'>
				</APPLET>';
		?>
		<?php echo $applet ; ?>
	</body>
</html>