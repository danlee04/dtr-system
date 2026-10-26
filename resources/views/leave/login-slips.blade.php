<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login slips</title>
    <style>
        @page { size: A4; margin: 12mm; }
        body { margin: 0; padding: 16px; font-family: Arial, Helvetica, sans-serif; color: #111; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 16px; font-size: 14px; }
        .toolbar button { padding: 8px 16px; font-size: 14px; cursor: pointer; }
        .slips { display: grid; grid-template-columns: repeat(auto-fill, minmax(85mm, 1fr)); }
        .slip { padding: 14px 16px; border: 1px dashed #777; break-inside: avoid; }
        .slip h2 { margin: 0 0 10px; font-size: 15px; }
        .slip dl { display: grid; grid-template-columns: max-content 1fr; gap: 4px 12px; margin: 0 0 10px; font-size: 13px; }
        .slip dt { color: #555; }
        .slip dd { margin: 0; font-weight: bold; overflow-wrap: anywhere; }
        .slip .password { font-family: "Courier New", Courier, monospace; font-size: 16px; letter-spacing: 1px; }
        .slip p { margin: 0; font-size: 12px; }
        @media print {
            body { padding: 0; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ $backUrl }}">Back to Employees</a>
        @if ($slips !== [])
            <button type="button" onclick="window.print()">Print</button>
            <span>Cut along the dashed lines. These passwords are shown once.</span>
        @endif
    </div>

    @if ($slips === [])
        <p>No new logins to show. Passwords are shown once.</p>
    @else
        <div class="slips">
            @foreach ($slips as $slip)
                <section class="slip">
                    <h2>DTR System login</h2>
                    <dl>
                        <dt>Name</dt>
                        <dd>{{ $slip['name'] }}</dd>
                        <dt>Employee number</dt>
                        <dd>{{ $slip['employee_number'] }}</dd>
                        <dt>Temporary password</dt>
                        <dd class="password" data-password="{{ $slip['password'] }}">{{ $slip['password'] }}</dd>
                        <dt>Sign in at</dt>
                        <dd>{{ $signInUrl }}</dd>
                    </dl>
                    <p>Change this password when you first sign in.</p>
                </section>
            @endforeach
        </div>
    @endif
</body>
</html>
