<?php
	session_start();
	if(!isset($_SESSION["USERNAME"])){
		session_destroy();

		header("location:../index.php");
	}

?>

<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title></title>
</head>
<body>
	<table border="1" style="width:800px; margin: auto;">
		<tr>
			<td style="width:25%;"><?php echo $_SESSION['FNAME']; ?></td>
			<td>
			</td>
		</tr>
		<tr>
			<td>
				<h3><a href = "index.php">Homepage</a></h3>
				<h3><a href = "reels.php">Reels</a></h3>
				<h3><a href = "marketplace.php">Market Place</a></h3>
				<h3><a href = "#">Notifications</a></h3>
				<h3><a href = "logout.php">Logout</a></h3>
			</td>
			<td style="height:500px;">
				<h1>Market Place CONTENT</h1>
			</td>
		</tr>
	</table>


</body>
</html>