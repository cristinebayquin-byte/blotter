<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

session_timeout_guard();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim((string)($_POST['username'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if ($username === '' || $password === '') {
    $error = 'Please fill in all fields.';
  } else {
    $ok = login_user($username, $password);
    if ($ok) {
      header('Location: ?route=dashboard');
      exit;
    }
    $error = 'Invalid username or password.';
  }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login - Barangay Blotter</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --navy: #111a2e; --slate: #667792; --gold: #dca400; --line: #dce4ef; }
    * { box-sizing: border-box; }
    body {
      min-height: 100vh;
      margin: 0;
      color: var(--navy);
      font-family: 'Poppins', Arial, sans-serif;
      background-color: #fbfcfe;
      background-image: radial-gradient(circle, #edf1f6 1.8px, transparent 2px);
      background-size: 17px 17px;
    }
    .login-page { width: min(100% - 2rem, 460px); margin: 0 auto; padding: 1rem 0 2rem; }
    .brand { padding: .2rem 1rem 1.5rem; text-align: center; }
    .brand h1 { margin: 0; font: 700 clamp(1.8rem, 7vw, 2.35rem)/1.08 'Poppins', Arial, sans-serif; letter-spacing: -1.5px; }
    .brand-line { width: 42px; height: 3px; margin: .45rem auto .7rem; background: var(--gold); border-radius: 99px; }
    .brand p { margin: 0; color: #71819b; font: 700 .7rem/1 Arial, sans-serif; letter-spacing: .28em; }
    .login-card { overflow: hidden; background: #fff; border-radius: 10px 10px 0 0; box-shadow: 0 10px 25px rgba(17, 26, 46, .07); }
    .card-accent { height: 7px; background: linear-gradient(100deg, var(--navy) 0%, var(--navy) 25%, #c99d32 56%, var(--navy) 100%); }
    .card-content { padding: 1.7rem 1.8rem 1.6rem; }
    .card-heading { margin: 0; text-align: center; font: 700 1.35rem/1.2 'Poppins', Arial, sans-serif; }
    .card-subheading { margin: .4rem 0 1.45rem; color: var(--slate); text-align: center; font-size: .93rem; }
    .form-label { display: flex; align-items: center; gap: .5rem; margin-bottom: .45rem; color: #53647d; font-size: .76rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .form-label svg { width: 17px; height: 17px; color: var(--gold); }
    .form-control { min-height: 48px; padding: .65rem .85rem; border: 1px solid var(--line); border-radius: 8px; color: var(--navy); font-size: .9rem; }
    .form-control::placeholder { color: #697a95; opacity: 1; }
    .form-control:focus { border-color: var(--gold); box-shadow: 0 0 0 2px var(--gold); }
    .password-field { position: relative; }
    .password-field .form-control { padding-right: 3.5rem; }
    .password-toggle { position: absolute; top: 50%; right: 1rem; z-index: 2; width: 1.4rem; height: 1.4rem; padding: 0; color: #455671; background: transparent; border: 0; transform: translateY(-50%); }
    .password-toggle:focus-visible { outline: 2px solid var(--gold); outline-offset: 2px; border-radius: .25rem; }
    .password-toggle svg { width: 100%; height: 100%; }
    .login-button { min-height: 50px; margin-top: 1.5rem; color: #fff; background: var(--navy); border: 0; border-radius: 8px; box-shadow: 0 4px 7px rgba(17, 26, 46, .16); font-size: 1rem; font-weight: 700; }
    .login-button:hover, .login-button:focus { color: #fff; background: #1b2945; }
    .page-footer { padding: 1.2rem 1rem 0; color: #99a6b9; text-align: center; font-size: .7rem; }
    .page-footer strong { display: block; margin-bottom: .45rem; color: #8c9bb0; font-size: .72rem; letter-spacing: .08em; }
    @media (max-width: 480px) { .brand { padding-bottom: 1.6rem; } .brand p { font-size: .67rem; letter-spacing: .25em; } .card-content { padding: 1.8rem 1.35rem; } }
  </style>
</head>
<body>
  <main class="login-page">
    <header class="brand">
      <h1>Barangay San Jose</h1>
      <div class="brand-line"></div>
      <p>Automated Blotter System</p>
    </header>
    <section class="login-card" aria-labelledby="portal-title">
      <div class="card-accent"></div>
      <div class="card-content">
        <h2 class="card-heading" id="portal-title">Staff Portal</h2>
        <p class="card-subheading">Access official barangay records</p>
        <?php if ($error): ?>
          <div class="alert alert-danger py-2"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" id="loginForm" novalidate>
          <div class="mb-3">
            <label class="form-label" for="username"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg>Username</label>
            <input type="text" id="username" name="username" class="form-control" placeholder="Enter your assigned username" required maxlength="60" autocomplete="username">
            <div class="invalid-feedback">Username is required.</div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="password"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="15" r="3"/><path d="m10.5 12.5 8-8a2.1 2.1 0 0 1 3 3l-8 8"/><path d="m15 6 3 3"/></svg>Password</label>
            <div class="password-field">
              <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required minlength="4" autocomplete="current-password">
              <button class="password-toggle" type="button" id="togglePassword" aria-label="Show password" aria-pressed="false">
                <svg id="togglePasswordIcon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" />
                  <circle cx="12" cy="12" r="2.5" />
                  <path d="M3 3l18 18" />
                </svg>
              </button>
            </div>
            <div class="invalid-feedback">Password is required.</div>
          </div>
          <button class="btn w-100 login-button" type="submit">Login</button>
        </form>
      </div>
    </section>
    <footer class="page-footer"><strong>OFFICIAL GOVERNMENT USE ONLY</strong>© <?php echo date('Y'); ?> Barangay San Jose. All rights reserved.</footer>
  </main>

  <script>
    (function () {
      const form = document.getElementById('loginForm');
      form.addEventListener('submit', function (e) {
        if (!form.checkValidity()) {
          e.preventDefault();
          e.stopPropagation();
        }
        form.classList.add('was-validated');
      });

      const toggle = document.getElementById('togglePassword');
      const password = document.getElementById('password');
      if (toggle && password) {
        toggle.addEventListener('click', function () {
          const isPassword = password.type === 'password';
          password.type = isPassword ? 'text' : 'password';
          toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
          toggle.setAttribute('aria-pressed', String(isPassword));
          document.getElementById('togglePasswordIcon').innerHTML = isPassword
            ? '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" /><circle cx="12" cy="12" r="2.5" />'
            : '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" /><circle cx="12" cy="12" r="2.5" /><path d="M3 3l18 18" />';
        });
      }
    })();
  </script>
</body>
</html>


