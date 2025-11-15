<?php
$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/utils.php'; // já tem h() e redirect()
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

require_login();
$user = current_user();
$pdo  = getPDO();

$userId = (int)$user['id'];

$stmt = $pdo->prepare("
  SELECT id, area, lang, score, created_at
  FROM attempts
  WHERE user_id = ?
  ORDER BY id DESC
  LIMIT 50
");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function fmt_datetime($str) {
    try { $dt = new DateTime($str); return $dt->format('d/m/Y H:i'); }
    catch (Throwable $e) { return h($str); }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Histórico de tentativas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="index.php"><?= h($config['app_name']) ?></a>
    <div class="d-flex align-items-center">
      <span class="me-3">Olá, <?= h($user['name']) ?></span>
      <a class="btn btn-outline-danger btn-sm" href="logout.php">Sair</a>
    </div>
  </div>
</nav>

<main class="container pt-navbar">
  <div class="page-head d-flex justify-content-between align-items-center">
    <h1 class="mb-0">Histórico</h1>
    <a class="btn btn-outline-secondary" href="index.php">Início</a>
  </div>

  <?php if (!$rows): ?>
    <div class="alert alert-info">Você ainda não possui tentativas registradas.</div>
  <?php else: ?>
    <div class="card card-table">
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th>Área</th>
              <th>Linguagem</th>
              <th>Pontuação</th>
              <th>Data</th>
              <th class="text-end">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= h($r['area']) ?></td>
                <td><?= h($r['lang']) ?></td>
                <td><?= (int)$r['score'] ?></td>
                <td><?= fmt_datetime($r['created_at']) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="result.php?id=<?= (int)$r['id'] ?>">Ver detalhes</a>
                  <a class="btn btn-sm btn-success" href="quiz.php?area=<?= urlencode($r['area']) ?>&lang=<?= urlencode($r['lang']) ?>">Repetir tema</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
