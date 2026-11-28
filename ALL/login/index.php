<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

	<style>
		table{
			height:100vh;
			display:grid;
			place-content: center;
		}
		table th{
			font-size: 14pt;
			font-variant: small-caps;
		}
		table tr td{
			padding:5px;
		}
		button{
			float: right;
		}
	</style>
</head>
<body>

	<form method = "post" action = "login.php">
		<table>
			<tr>
				<th colspan="2">School of Information Technology</th>
			</tr>
			<tr>
				<td colspan="2">
					<input type="text" name="username" placeholder="Username" class="form-control">
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<input type="password" name="password" placeholder="Password" class="form-control">
				</td>
			</tr>
			<tr>
				<td>
					<a href = "register.php" class="btn btn-link">Register</a>
				</td>
				<td>
					<button name = "login_btn" class="btn btn-primary">Login</button>
				</td>
			</tr>
		</table>
	</form>











<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>