<?php
	include '../dbconn.php';
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
	<h1>Welcome <?php echo $_SESSION['USERNAME']; ?></h1> <a href = "logout.php">Logout</a>
	<br><br><hr>

	<table>
		<tr>
			<th>First Name</th>
			<th>Last Name</th>
			<th>Username</th>
			<th>Actions</th>
		</tr>
		<?php
			$fetch = "SELECT * FROM accounts WHERE role = 'student' ";
			$result = mysqli_query($conn, $fetch);

			while ($row = mysqli_fetch_assoc($result)) {
		?>
				<tr>
					<td><?php echo $row['firstname']; ?></td>
					<td><?php echo $row['lastname']; ?></td>
					<td><?php echo $row['username']; ?></td>
					<td>
						<a href = "update.php">Update</a>
						<a href = "delete.php?id=<?php echo $row['id']; ?>">Delete</a>
					</td>
				</tr>
		<?php
			}
		?>
	</table>
</body>
</html>