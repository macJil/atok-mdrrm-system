<?php
	include 'dbconn.php';

	if(isset($_POST['delete'])){
		$checkIDs = $_POST['checkIDs']; # one variable with many data

		foreach ($checkIDs as $id) {
			$delete = "DELETE FROM items WHERE ID = '$id' "; #in php this is just pure text/string
			$result = mysqli_query($conn, $delete);
		}

		header("location: checkDelete.php");
		exit();
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
	<form method = "post">

		<table>
			<tr>
				<td>
					<button name = "delete">
						
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash3" viewBox="0 0 16 16">
						  <path d="M6.5 1h3a.5.5 0 0 1 .5.5v1H6v-1a.5.5 0 0 1 .5-.5M11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3A1.5 1.5 0 0 0 5 1.5v1H1.5a.5.5 0 0 0 0 1h.538l.853 10.66A2 2 0 0 0 4.885 16h6.23a2 2 0 0 0 1.994-1.84l.853-10.66h.538a.5.5 0 0 0 0-1zm1.958 1-.846 10.58a1 1 0 0 1-.997.92h-6.23a1 1 0 0 1-.997-.92L3.042 3.5zm-7.487 1a.5.5 0 0 1 .528.47l.5 8.5a.5.5 0 0 1-.998.06L5 5.03a.5.5 0 0 1 .47-.53Zm5.058 0a.5.5 0 0 1 .47.53l-.5 8.5a.5.5 0 1 1-.998-.06l.5-8.5a.5.5 0 0 1 .528-.47M8 4.5a.5.5 0 0 1 .5.5v8.5a.5.5 0 0 1-1 0V5a.5.5 0 0 1 .5-.5"/>
						</svg>
						
					</button>
				</td>
			</tr>
			<?php
				$fetch = "SELECT * FROM items"; # in php this is just a text only
				$result = mysqli_query($conn, $fetch); # php already known that $fetch is an sql query

				while ($row = mysqli_fetch_assoc($result)) {
			?>
					<tr>
						<td>
							<input type="checkbox" name="checkIDs[]" value = "<?php echo $row['ID']; ?>">
						</td>
						<td>
							<?php echo $row['Item_Name']; ?>
						</td>
					</tr>
			<?php
				}
			?>
		</table>
	</form>
</body>
</html>