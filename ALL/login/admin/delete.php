<?php
	include "../dbconn.php";

	$id = $_GET['id'];

	$delete = "DELETE FROM accounts WHERE id = '$id' ";
	$result = mysqli_query($conn, $delete);

	header("location:index.php");
?>