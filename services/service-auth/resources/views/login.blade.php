<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Login</title>
	<style>
		body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;background:#f7fafc;margin:0;padding:0}
		.container{max-width:420px;margin:5rem auto;background:#fff;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.06);padding:24px}
		.input{width:100%;padding:10px 12px;margin:8px 0;border:1px solid #e2e8f0;border-radius:6px}
		.button{width:100%;padding:10px 12px;margin-top:12px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer}
		.error{color:#b91c1c;background:#fee2e2;border:1px solid #fecaca;padding:8px 10px;border-radius:6px;margin-bottom:8px}
	</style>
</head>
<body>
	<div class="container">
		<h2>Login</h2>
		@if ($error)
			<div class="error">{{ $error }}</div>
		@endif
		<form method="POST" action="/login">
			<input type="hidden" name="redirect" value="{{ $redirect }}">
			@csrf
			<input class="input" type="email" name="email" placeholder="Email" required>
			<input class="input" type="password" name="password" placeholder="Password" required>
			<label style="display:flex;align-items:center;gap:8px;margin-top:8px">
				<input type="checkbox" name="remember" value="1"> Remember me
			</label>
			<button class="button" type="submit">Sign in</button>
		</form>
	</div>
</body>
</html>
