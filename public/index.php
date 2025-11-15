<?php
if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/utils.php';
require_once __DIR__ . '/../src/auth.php';

$user = current_user();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title><?= h($config['app_name']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="index.php"><?= h($config['app_name']) ?></a>
    <div class="d-flex align-items-center">
      <?php if ($user): ?>
        <span class="me-3">Olá, <?= h($user['name']) ?></span>
        <a class="btn btn-outline-danger btn-sm" href="logout.php">Sair</a>
      <?php else: ?>
        <a class="btn btn-outline-primary btn-sm me-2" href="login.php">Entrar</a>
        <a class="btn btn-primary btn-sm" href="register.php">Cadastrar</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<main class="container pt-navbar">
  <div class="hero mb-4">
    <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3">
      <div>
        <h1 class="mb-2">Jogo de Perguntas Técnicas</h1>
        <p class="text-muted mb-0">Pratique <strong>10 perguntas</strong> por tema, receba pontuação e revise os erros para acelerar sua carreira.</p>
      </div>
      <?php if ($user): ?>
        <div class="d-flex gap-2 hero-actions">
          <a class="btn btn-outline-secondary" href="history.php"><i class="bi bi-clock-history me-1"></i>Histórico</a>
          <a class="btn btn-secondary" href="profile.php"><i class="bi bi-person-circle me-1"></i>Perfil</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <h2 class="h4 mb-3">Selecione seu desafio</h2>
  <form class="row g-3" action="quiz.php" method="get">
    <div class="col-md-6">
      <label class="form-label"><i class="bi bi-diagram-3 me-1"></i>Área de Foco</label>
      <select class="form-select" name="area" required>
        <option value="">Selecione</option>
        <option>Back-end</option>
        <option>Front-end</option>
        <option>Dados</option>
      </select>
    </div>

    <div class="col-md-6">
      <label class="form-label"><i class="bi bi-code-square me-1"></i>Linguagem</label>
      <select class="form-select" name="lang" required>
        <option value="">Selecione</option>
        <option>PHP</option>
        <option>JavaScript</option>
        <option>Python</option>
        <option>SQL</option>
      </select>
    </div>

    <div class="col-12">
      <button class="btn btn-success">Iniciar Quiz</button>
    </div>
  </form>
</main>

<script src="/assets/js/app.js"></script>
<script>
(function() {
  const areaSel = document.querySelector('select[name="area"]');
  const langSel = document.querySelector('select[name="lang"]');

  const allowed = {
    "Front-end": ["JavaScript"],
    "Back-end":  ["PHP","Python","SQL"],
    "Dados":     ["SQL","Python"]
  };

  function refreshLang() {
    const area = areaSel.value;
    const allowedLangs = allowed[area] || [];
    Array.from(langSel.options).forEach(opt => {
      if (!opt.value) return;
      opt.hidden = allowedLangs.length ? !allowedLangs.includes(opt.value) : false;
      opt.disabled = opt.hidden;
    });
    if (langSel.value && !allowedLangs.includes(langSel.value)) {
      langSel.value = "";
    }
  }

  areaSel.addEventListener('change', refreshLang);
  refreshLang();
})();
</script>
</body>
</html>
