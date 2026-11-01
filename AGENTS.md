# HRIS Lite

- Baca docs/BRIEF.md, docs/PLAN.md, docs/ERD.md dan docs/PROGRESS.md.
- Kerjakan langsung di checkout ini sesuai permintaan pengguna; jangan deploy/push tanpa instruksi.
- PHP lokal minimum 8.3.36 (patch PHP2Shell). Composer: php .tools/composer.phar.
- MySQL development/test harus terpisah; jangan migrate:fresh pada database development pengguna.
- .env, .env.testing, .local/ dan .tools/ tidak boleh di-commit atau dicetak isinya.
- Inertia 3, React TypeScript, Vite, Tailwind. UI Indonesia, timestamp UTC, tampilan WIB.
- Verifikasi: php artisan test; php vendor/bin/pint --test; npm run lint; npm run format:check; npm run types:check; npm run build.
- Jangan memasang tool tambahan atau mengganti runtime global hanya untuk mengikuti default skeleton.
