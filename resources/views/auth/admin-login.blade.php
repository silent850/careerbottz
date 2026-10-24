<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrator Login | CareerBotTz</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            display: grid;
            place-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .card {
            background: white;
            padding: 32px;
            border-radius: 12px;
            width: min(360px, calc(100% - 64px));
            box-shadow: 0 8px 30px rgba(0, 0, 0, .08);
        }
        h1 { margin-top: 0; font-size: 24px; }
        label { display: block; margin: 16px 0 6px; }
        input {
            box-sizing: border-box;
            width: 100%;
            padding: 11px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
        }
        button {
            width: 100%;
            padding: 12px;
            margin-top: 22px;
            background: #1d4ed8;
            color: white;
            border: 0;
            border-radius: 6px;
            cursor: pointer;
        }
        .error { color: #b91c1c; font-size: 14px; }
    </style>
</head>
<body>
    <main class="card">
        <h1>CareerBotTz Admin</h1>
        <p>Sign in to manage the platform.</p>

        @if ($errors->any())
            <div class="error" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <label for="email">Email address</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                autocomplete="username"
                required
                autofocus
            >

            <label for="password">Password</label>
            <input
                id="password"
                type="password"
                name="password"
                autocomplete="current-password"
                required
            >

            <button type="submit">Sign in</button>
        </form>
    </main>
</body>
</html>
