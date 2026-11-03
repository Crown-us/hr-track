<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>Akses ditolak · HRIS Lite</title>
        <style>
            :root { color-scheme: light; }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100dvh;
                display: grid;
                place-items: center;
                padding: 32px 20px;
                background: #fafafa;
                color: #202328;
                font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', sans-serif;
                line-height: 1.6;
            }
            main {
                width: 100%;
                max-width: 520px;
                padding: 40px;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
            }
            .brand {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 28px;
                color: #202328;
                font-size: 15px;
                font-weight: 600;
                text-decoration: none;
            }
            .brand span {
                display: grid;
                place-items: center;
                width: 30px;
                height: 30px;
                border: 1px solid #dedfe4;
                border-radius: 7px;
                font-size: 14px;
            }
            .code { margin: 0 0 8px; font-size: 12px; letter-spacing: 0.12em; color: #b42318; }
            h1 { margin: 0 0 12px; font-size: 28px; font-weight: 500; letter-spacing: -0.03em; }
            p { margin: 0 0 28px; color: #5f646d; }
            .actions { display: flex; flex-wrap: wrap; gap: 10px; }
            a.action {
                display: inline-flex;
                align-items: center;
                min-height: 40px;
                padding: 8px 16px;
                border: 1px solid #d4d7dd;
                border-radius: 6px;
                color: #202328;
                font-size: 13px;
                text-decoration: none;
            }
            a.action.primary { background: #202328; border-color: #202328; color: #fff; }
            a.action:focus-visible { outline: 2px solid #4163a5; outline-offset: 3px; }
        </style>
    </head>
    <body>
        <main>
            <a class="brand" href="/"><span>H</span> HRIS Lite</a>
            <p class="code">403 · AKSES DITOLAK</p>
            <h1>Anda tidak memiliki akses ke halaman ini.</h1>
            <p>Halaman ini hanya tersedia untuk peran tertentu. Kembali ke beranda atau masuk dengan akun yang sesuai.</p>
            <div class="actions">
                <a class="action primary" href="/">Kembali ke beranda</a>
                <a class="action" href="/login">Masuk</a>
            </div>
        </main>
    </body>
</html>
