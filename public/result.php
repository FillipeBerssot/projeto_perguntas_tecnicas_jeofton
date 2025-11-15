<?php
$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/utils.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/questions.php';

require_login();
$user = current_user();

/* ----------------- POST: salvar e redirecionar ----------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $area        = $_POST['area'] ?? '';
    $lang        = $_POST['lang'] ?? '';
    $answers     = $_POST['answers'] ?? [];
    $questionIds = $_POST['question_ids'] ?? [];

    // validações simples
    $allowed = [
        'Front-end' => ['JavaScript'],
        'Back-end'  => ['PHP','Python','SQL'],
        'Dados'     => ['SQL','Python'],
    ];
    if (!isset($allowed[$area]) || !in_array($lang, $allowed[$area], true)) {
        redirect('/');
    }

    // garantir que todas as 10 foram respondidas
    $providedQids = array_map('intval', array_keys($answers));
    $expectedQids = array_map('intval', (array)$questionIds);
    sort($providedQids);
    sort($expectedQids);
    if ($providedQids !== $expectedQids || count($expectedQids) !== 10) {
        // faltou questão
        redirect('/');
    }

    // salva tentativa e manda para GET /result.php?id=...
    $attemptId = save_attempt((int)$user['id'], $area, $lang, array_map('intval', $answers));
    redirect('/result.php?id=' . $attemptId);
    exit;
}

/* ----------------- GET: exibir resultado ----------------- */
$attemptId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($attemptId <= 0) redirect('/');

$data = load_attempt_detail($attemptId);
if (!$data) redirect('/');

$attempt = $data['attempt'];
$rows    = $data['answers'];
$total   = count($rows);
$score   = (int)$attempt['score'];
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <title>Resultado — <?= h($config['app_name']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="mb-0">Resultado</h1>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="index.php">Início</a>
      <a class="btn btn-primary" href="history.php">Ver histórico</a>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <p class="mb-1"><strong>Usuário:</strong> <?= h($user['name']) ?> (<?= h($user['email']) ?>)</p>
      <p class="mb-1"><strong>Área / Linguagem:</strong> <?= h($attempt['area']) ?> / <?= h($attempt['lang']) ?></p>
      <p class="mb-0"><strong>Pontuação:</strong> <?= $score ?> / <?= $total ?></p>
    </div>
  </div>

  <h4>Revisão das questões</h4>
  <?php foreach ($rows as $idx => $row): ?>
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <h5 class="card-title mb-2">Questão - <?= $idx+1 ?>) <?= h($row['enunciado']) ?></h5>
          <?php if ((int)$row['correta'] === 1): ?>
            <span class="badge text-bg-success align-self-start">Correta</span>
          <?php else: ?>
            <span class="badge text-bg-danger align-self-start">Incorreta</span>
          <?php endif; ?>
        </div>

        <?php if ((int)$row['correta'] !== 1): ?>
          <p class="mb-1"><strong>Sua resposta:</strong> <?= h($row['chosen_text']) ?></p>
          <p class="mb-0"><strong>Gabarito:</strong> <?= h($row['correct_text']) ?></p>
        <?php else: ?>
          <p class="mb-0"><strong>Resposta marcada:</strong> <?= h($row['chosen_text']) ?></p>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="d-flex gap-2">
    <a href="quiz.php?area=<?= urlencode($attempt['area']) ?>&lang=<?= urlencode($attempt['lang']) ?>" class="btn btn-success">Repetir mesmo tema</a>
    <a href="index.php" class="btn btn-outline-secondary">Voltar</a>
  </div>
</body>
</html>